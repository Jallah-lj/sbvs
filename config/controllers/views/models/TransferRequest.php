<?php
class TransferRequest {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generate a unique TRF ID like TRF-2603-XYZ123
     */
    private function generateTransferId() {
        return 'TRF-' . date('ym') . '-' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Log an action to the immutable audit log
     */
    public function logAudit($transfer_id, $actor_id, $action, $prev_status, $new_status, $rationale = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO transfer_audit_logs 
            (transfer_request_id, actor_id, action, previous_status, new_status, rationale)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$transfer_id, $actor_id, $action, $prev_status, $new_status, $rationale]);
    }

    /**
     * Initiates a new transfer request (Phase 1)
     */
    public function createTransfer($student_id, $origin_branch, $dest_branch, $reason, $actor_id) {
        try {
            $this->conn->beginTransaction();

            $transfer_id_str = $this->generateTransferId();
            $status = 'Pending Origin Approval';

            $stmt = $this->conn->prepare("
                INSERT INTO transfer_requests 
                (transfer_id, student_id, origin_branch_id, destination_branch_id, reason, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$transfer_id_str, $student_id, $origin_branch, $dest_branch, $reason, $status]);
            
            $new_tr_id = $this->conn->lastInsertId();

            $this->logAudit($new_tr_id, $actor_id, 'Transfer Submitted', null, $status, 'Student initiated transfer request.');

            $this->conn->commit();
            return ['status' => 'success', 'id' => $new_tr_id, 'transfer_id_str' => $transfer_id_str];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Add a document with a SHA256 checksum to prevent tampering
     */
    public function addDocument($transfer_id, $doc_type, $file_path, $checksum) {
        $stmt = $this->conn->prepare("
            INSERT INTO transfer_documents (transfer_request_id, document_type, file_path, checksum)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$transfer_id, $doc_type, $file_path, $checksum]);
    }

    /**
     * Fetch a single transfer request with student and branch details
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("
            SELECT t.*, 
                   s.student_id as student_code, u.name as student_name, u.email as student_email,
                   bo.name as origin_branch_name, bd.name as destination_branch_name,
                   uo.name as origin_admin_name, ud.name as dest_admin_name
            FROM transfer_requests t
            JOIN students s ON t.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN branches bo ON t.origin_branch_id = bo.id
            JOIN branches bd ON t.destination_branch_id = bd.id
            LEFT JOIN users uo ON t.origin_admin_id = uo.id
            LEFT JOIN users ud ON t.destination_admin_id = ud.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch the audit log for a request
     */
    public function getAuditLog($transfer_id) {
        $stmt = $this->conn->prepare("
            SELECT a.*, u.name as actor_name, u.role as actor_role
            FROM transfer_audit_logs a
            JOIN users u ON a.actor_id = u.id
            WHERE a.transfer_request_id = ?
            ORDER BY a.created_at ASC
        ");
        $stmt->execute([$transfer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update the status of a transfer (Phase 2 and 3)
     */
    public function updateStatus($id, $new_status, $actor_id, $actor_role, $rationale = null, $conditional_notes = null) {
        try {
            $this->conn->beginTransaction();

            // Get current
            $curr = $this->getById($id);
            if (!$curr) throw new Exception("Transfer not found.");
            
            $prev_status = $curr['status'];

            // Update record
            $upd = "UPDATE transfer_requests SET status = ?";
            $params = [$new_status];

            if ($actor_role === 'origin') {
                $upd .= ", origin_admin_id = ?";
                $params[] = $actor_id;
            } elseif ($actor_role === 'destination') {
                $upd .= ", destination_admin_id = ?, conditional_notes = ?";
                $params[] = $actor_id;
                $params[] = $conditional_notes;
            }

            $upd .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $this->conn->prepare($upd);
            $stmt->execute($params);

            // Log action
            $this->logAudit($id, $actor_id, "Status changed to $new_status", $prev_status, $new_status, $rationale);

            // Phase 4: Auto-Migration if Complete
            if ($new_status === 'Transfer Complete') {
                $student_id = $curr['student_id'];
                $dest_branch = $curr['destination_branch_id'];

                // 1. Update Student Table
                $s_stmt = $this->conn->prepare("UPDATE students SET branch_id = ? WHERE id = ?");
                $s_stmt->execute([$dest_branch, $student_id]);

                // 2. Update User Table (for login/access)
                $u_stmt = $this->conn->prepare("UPDATE users SET branch_id = ? WHERE id = (SELECT user_id FROM students WHERE id = ?)");
                $u_stmt->execute([$dest_branch, $student_id]);

                // 3. Update Enrollments (map to destination branch batches if needed - currently keeps existing IDs but logically under new branch)
                
                $this->logAudit($id, $actor_id, "System Migration", $new_status, $new_status, "Automatically migrated student records to new branch.");
            }

            $this->conn->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
?>
