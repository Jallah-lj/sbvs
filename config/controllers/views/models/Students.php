<?php
class Student {
    private $conn;
    private $table_name = "students";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generates a unique, sequential Student ID.
     * Format: STU-{YEAR}-{NNNN}  (e.g., STU-2026-0001)
     * The counter is global across all branches and resets each calendar year.
     *
     * @return string
     * @throws Exception
     */
    public function generateStudentId(): string {
        $year   = date('Y');
        $prefix = 'STU-' . $year . '-';

        $stmt = $this->conn->prepare(
            "SELECT student_id FROM students
             WHERE student_id LIKE :prefix
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

    // Advanced: Create student with transaction to ensure user & student records are atomic
    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Create the User record first
            $user_query = "INSERT INTO users (branch_id, name, email, password_hash, role) 
                           VALUES (:branch_id, :name, :email, :password, 'Student')";
            $stmt1 = $this->conn->prepare($user_query);
            $stmt1->execute([
                ':branch_id' => $data['branch_id'],
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':password' => password_hash($data['dob'], PASSWORD_DEFAULT) // Default password is DOB
            ]);
            $user_id = $this->conn->lastInsertId();

            // 2. Create the Student record
            $student_query = "INSERT INTO students (user_id, branch_id, student_id, gender, dob, phone, address, photo_url, registration_date)
                              VALUES (:user_id, :branch_id, :student_id, :gender, :dob, :phone, :address, :photo_url, :reg_date)";
            $stmt2 = $this->conn->prepare($student_query);
            $stmt2->execute([
                ':user_id'    => $user_id,
                ':branch_id'  => $data['branch_id'],
                ':student_id' => $data['student_id'],
                ':gender'     => $data['gender'],
                ':dob'        => $data['dob'],
                ':phone'      => $data['phone'],
                ':address'    => $data['address'],
                ':photo_url'  => $data['photo_url'] ?? null,
                ':reg_date'   => date('Y-m-d')
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT s.id, s.student_id, s.gender, s.dob, s.phone, s.address, s.branch_id,
                    s.photo_url, u.id AS user_id, u.name, u.email
             FROM students s
             JOIN users u ON s.user_id = u.id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        try {
            $this->conn->beginTransaction();

            $s = $this->conn->prepare("SELECT user_id FROM students WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Student not found");

            $this->conn->prepare(
                "UPDATE users SET name=:name, email=:email, branch_id=:branch_id WHERE id=:uid"
            )->execute([':name' => $data['name'], ':email' => $data['email'],
                        ':branch_id' => $data['branch_id'], ':uid' => $row['user_id']]);

            $photoSql = isset($data['photo_url']) ? ', photo_url=:photo_url' : '';
            $updStmt = $this->conn->prepare(
                "UPDATE students SET gender=:gender, dob=:dob, phone=:phone,
                                     address=:address, branch_id=:branch_id{$photoSql} WHERE id=:id"
            );
            $params = [':gender' => $data['gender'], ':dob' => $data['dob'],
                       ':phone' => $data['phone'], ':address' => $data['address'],
                       ':branch_id' => $data['branch_id'], ':id' => $id];
            if (isset($data['photo_url'])) $params[':photo_url'] = $data['photo_url'];
            $updStmt->execute($params);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }
}