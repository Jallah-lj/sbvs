<?php
class Teacher {
    private $conn;
    private $table_name = "teachers";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generates a unique, sequential Teacher ID.
     * Format: TCH-{YEAR}-{NNNN}  (e.g., TCH-2026-0001)
     * Distinct from student IDs (STU-YEAR-NNNN) by the TCH prefix.
     *
     * @return string
     */
    public function generateTeacherId(): string {
        $year   = date('Y');
        $prefix = 'TCH-' . $year . '-';

        $stmt = $this->conn->prepare(
            "SELECT teacher_id FROM teachers
             WHERE teacher_id LIKE :prefix
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if ($last !== false) {
            $lastNum = (int) substr($last, -4);
            $newNum  = $lastNum + 1;
        } else {
            $newNum = 1;
        }

        return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }

    public function getAll($branch_id = null) {
        $query = "SELECT t.id, t.teacher_id, u.name, u.email, t.phone, t.specialization,
                         t.branch_id, b.name as branch_name, t.status, t.photo_url, t.user_id
                  FROM " . $this->table_name . " t
                  JOIN users u ON t.user_id = u.id
                  JOIN branches b ON t.branch_id = b.id";
        if ($branch_id) $query .= " WHERE t.branch_id = " . intval($branch_id);
        $query .= " ORDER BY u.name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Create user record
            $user_query = "INSERT INTO users (branch_id, name, email, password_hash, role)
                           VALUES (:branch_id, :name, :email, :password, 'Teacher')";
            $stmt1 = $this->conn->prepare($user_query);
            $stmt1->execute([
                ':branch_id' => $data['branch_id'],
                ':name'      => $data['name'],
                ':email'     => $data['email'],
                ':password'  => password_hash($data['phone'], PASSWORD_DEFAULT) // Default password = phone
            ]);
            $user_id = $this->conn->lastInsertId();

            // 2. Generate teacher ID — done inside the transaction so the SELECT is consistent
            $teacher_id = $this->generateTeacherId();

            // 3. Create teacher record with the generated teacher_id
            $teacher_query = "INSERT INTO teachers (user_id, branch_id, phone, specialization, status, teacher_id, photo_url)
                              VALUES (:user_id, :branch_id, :phone, :specialization, 'Active', :teacher_id, :photo_url)";
            $stmt2 = $this->conn->prepare($teacher_query);
            $stmt2->execute([
                ':user_id'        => $user_id,
                ':branch_id'      => $data['branch_id'],
                ':phone'          => $data['phone'],
                ':specialization' => $data['specialization'],
                ':teacher_id'     => $teacher_id,
                ':photo_url'      => $data['photo_url'] ?? null
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }

    public function getById($id) {
        $query = "SELECT t.id, t.teacher_id, u.name, u.email, t.phone, t.specialization,
                         t.branch_id, b.name as branch_name, t.status, t.user_id, t.photo_url
                  FROM " . $this->table_name . " t
                  JOIN users u ON t.user_id = u.id
                  JOIN branches b ON t.branch_id = b.id
                  WHERE t.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        try {
            $this->conn->beginTransaction();

            // Update teachers table
            $query = "UPDATE " . $this->table_name . "
                      SET phone = :phone, specialization = :specialization,
                          branch_id = :branch_id, status = :status,
                          photo_url = COALESCE(:photo_url, photo_url)
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':id'             => $id,
                ':phone'          => $data['phone'],
                ':specialization' => $data['specialization'],
                ':branch_id'      => $data['branch_id'],
                ':status'         => $data['status'],
                ':photo_url'      => $data['photo_url'] ?? null
            ]);

            // Update user record (name, email) if provided
            if (!empty($data['name']) || !empty($data['email'])) {
                $uQuery = "UPDATE users u JOIN teachers t ON u.id = t.user_id
                           SET u.name = :name, u.email = :email
                           WHERE t.id = :tid";
                $uStmt = $this->conn->prepare($uQuery);
                $uStmt->execute([
                    ':name'  => $data['name'],
                    ':email' => $data['email'],
                    ':tid'   => $id
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }

    public function delete($id) {
        try {
            $this->conn->beginTransaction();
            // Fetch the linked user_id before deactivating the teacher row
            $stmt = $this->conn->prepare("SELECT user_id FROM " . $this->table_name . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Soft-deactivate the teacher record
            $del = $this->conn->prepare("UPDATE " . $this->table_name . " SET status = 'Inactive' WHERE id = :id");
            $del->execute([':id' => $id]);

            // Deactivate linked users record so account cannot be used to log in
            if ($row && !empty($row['user_id'])) {
                $delUser = $this->conn->prepare("UPDATE users SET status = 'Inactive' WHERE id = :uid AND role = 'Teacher'");
                $delUser->execute([':uid' => $row['user_id']]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>
