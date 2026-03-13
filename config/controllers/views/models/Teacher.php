<?php
class Teacher {
    private $conn;
    private $table_name = "teachers";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($branch_id = null) {
        $query = "SELECT t.id, u.name, u.email, t.phone, t.specialization,
                         t.branch_id, b.name as branch_name, t.status
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

            // 2. Create teacher record
            $teacher_query = "INSERT INTO teachers (user_id, branch_id, phone, specialization, status)
                              VALUES (:user_id, :branch_id, :phone, :specialization, 'Active')";
            $stmt2 = $this->conn->prepare($teacher_query);
            $stmt2->execute([
                ':user_id'        => $user_id,
                ':branch_id'      => $data['branch_id'],
                ':phone'          => $data['phone'],
                ':specialization' => $data['specialization']
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }

    public function getById($id) {
        $query = "SELECT t.id, u.name, u.email, t.phone, t.specialization,
                         t.branch_id, b.name as branch_name, t.status, t.user_id
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
                          branch_id = :branch_id, status = :status
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':id'             => $id,
                ':phone'          => $data['phone'],
                ':specialization' => $data['specialization'],
                ':branch_id'      => $data['branch_id'],
                ':status'         => $data['status']
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
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
?>
