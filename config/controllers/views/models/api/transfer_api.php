<?php
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../../../../config.php';
require_once '../../../../database.php';
require_once '../TransferRequest.php';

$db = (new Database())->getConnection();

// ── AUTO-MIGRATION FOR NEW TABLES ──────────────────────────────────────────
function ensureTransferTablesExist($db) {
    // 1. Core transfer_requests table
    $db->exec("CREATE TABLE IF NOT EXISTS transfer_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id VARCHAR(50) NOT NULL UNIQUE,
        student_id INT NOT NULL,
        origin_branch_id INT NOT NULL,
        destination_branch_id INT NOT NULL,
        reason TEXT,
        status ENUM(
            'Pending Origin Approval', 
            'Origin On Hold', 
            'Origin Rejected', 
            'Pending Destination Approval', 
            'Destination Conditionally Approved', 
            'Destination Rejected', 
            'Transfer Complete'
        ) NOT NULL DEFAULT 'Pending Origin Approval',
        origin_admin_id INT NULL,
        destination_admin_id INT NULL,
        conditional_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (origin_branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (destination_branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (origin_admin_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (destination_admin_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. transfer_documents table
    $db->exec("CREATE TABLE IF NOT EXISTS transfer_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_request_id INT NOT NULL,
        document_type ENUM('Application Form', 'Academic Record', 'Clearance Certificate', 'ID Document', 'Other') NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transfer_request_id) REFERENCES transfer_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. transfer_audit_logs table
    $db->exec("CREATE TABLE IF NOT EXISTS transfer_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_request_id INT NOT NULL,
        actor_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        previous_status VARCHAR(50) NULL,
        new_status VARCHAR(50) NULL,
        rationale TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transfer_request_id) REFERENCES transfer_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
ensureTransferTablesExist($db);

$transferModel = new TransferRequest($db);
$action = $_GET['action'] ?? '';
$role = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');

// ── API ENDPOINTS ──────────────────────────────────────────────────────────

// LIST ACTIVE BRANCHES (used to populate selectors in the UI)
if ($action === 'branches') {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name");
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// LIST TRANSFERS
if ($action === 'list') {
    // Super Admin sees all; Branch Admin sees only their own branch (origin or destination)
    if ($isSuperAdmin) {
        $query = "
            SELECT t.id, t.transfer_id, u.name as student_name, s.student_id as student_code,
                   bo.name as origin_branch, bd.name as destination_branch, t.status, t.created_at
            FROM transfer_requests t
            JOIN students s ON t.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN branches bo ON t.origin_branch_id = bo.id
            JOIN branches bd ON t.destination_branch_id = bd.id
            ORDER BY t.created_at DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT t.id, t.transfer_id, u.name as student_name, s.student_id as student_code,
                   bo.name as origin_branch, bd.name as destination_branch, t.status, t.created_at
            FROM transfer_requests t
            JOIN students s ON t.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN branches bo ON t.origin_branch_id = bo.id
            JOIN branches bd ON t.destination_branch_id = bd.id
            WHERE t.origin_branch_id = ? OR t.destination_branch_id = ?
            ORDER BY t.created_at DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$sessionBranch, $sessionBranch]);
    }

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $data]);
    exit;
}

// GET SINGLE TRANSFER
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $transfer = $transferModel->getById($id);
    if (!$transfer) {
        echo json_encode(['status' => 'error', 'message' => 'Transfer not found']);
        exit;
    }

    // Also fetch documents
    $dStmt = $db->prepare("SELECT * FROM transfer_documents WHERE transfer_request_id = ?");
    $dStmt->execute([$id]);
    $docs = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    // Also fetch audit log
    $logs = $transferModel->getAuditLog($id);

    echo json_encode(['status' => 'success', 'data' => $transfer, 'documents' => $docs, 'logs' => $logs]);
    exit;
}

// CREATE A TRANSFER REQUEST (Phase 1)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $origin_branch = (int)$_POST['origin_branch_id'];
    $dest_branch = (int)$_POST['destination_branch_id'];
    $reason = trim($_POST['reason']);

    // Security: Only Super Admin, Branch Admin of origin, or Student themselves can initiate
    if (!$isSuperAdmin && $origin_branch != $sessionBranch) {
         echo json_encode(['status' => 'error', 'message' => 'Unauthorized to initiate transfer for this branch.']);
         exit;
    }

    $result = $transferModel->createTransfer($student_id, $origin_branch, $dest_branch, $reason, $userId);
    echo json_encode($result);
    exit;
}

// UPLOAD DOCUMENT (Phase 1)
if ($action === 'upload_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_id = (int)$_POST['transfer_request_id'];
    $docType = $_POST['document_type'];
    
    // File handling
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Upload failed.']);
        exit;
    }

    $uploadDir = '../../../../assets/uploads/transfers/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
             echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory.']);
             exit;
        }
    }

    $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
    $safeName = 'TRF_' . $transfer_id . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $safeName;

    if (move_uploaded_file($_FILES['document']['tmp_name'], $dest)) {
        // Generate checksum for immutability check
        $checksum = hash_file('sha256', $dest);
        $dbPath = 'config/assets/uploads/transfers/' . $safeName;
        
        $transferModel->addDocument($transfer_id, $docType, $dbPath, $checksum);
        $transferModel->logAudit($transfer_id, $userId, 'Document Uploaded', null, null, "Uploaded $docType. Checksum: $checksum");
        
        echo json_encode(['status' => 'success', 'file' => $dbPath, 'checksum' => $checksum]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File move failed.']);
    }
    exit;
}

// UPDATE STATUS (Phases 2 & 3 & 4)
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_id = (int)$_POST['transfer_request_id'];
    $new_status = $_POST['status'];
    $rationale = trim($_POST['rationale'] ?? '');
    $conditional_notes = trim($_POST['conditional_notes'] ?? '');

    $transfer = $transferModel->getById($transfer_id);
    if (!$transfer) { echo json_encode(['status' => 'error', 'message' => 'Transfer not found']); exit; }

    $actor_role = null;

    // Determine role based on the status phase being transitioned TO
    $originStatuses = ['Pending Destination Approval', 'Origin On Hold', 'Origin Rejected'];
    $destStatuses   = ['Destination Conditionally Approved', 'Destination Rejected', 'Transfer Complete'];

    if (in_array($new_status, $originStatuses)) {
        $actor_role = 'origin';
    } elseif (in_array($new_status, $destStatuses)) {
        $actor_role = 'destination';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status transition.']); exit;
    }

    $result = $transferModel->updateStatus($transfer_id, $new_status, $userId, $actor_role, $rationale, $conditional_notes);
    echo json_encode($result);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
