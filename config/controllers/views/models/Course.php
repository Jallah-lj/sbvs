<?php
class Course {
    private $conn;
    private $table = "courses";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($branch_id = null) {
        $sql = "SELECT c.*, b.name AS branch_name
                FROM {$this->table} c
                LEFT JOIN branches b ON c.branch_id = b.id";
        if ($branch_id) {
            $sql .= " WHERE c.branch_id = :branch_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['branch_id' => $branch_id]);
        } else {
            $stmt = $this->conn->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT c.*, b.name AS branch_name
             FROM {$this->table} c
             LEFT JOIN branches b ON c.branch_id = b.id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table}
                (branch_id, name, duration, registration_fee, tuition_fee, description)
             VALUES (:branch_id, :name, :duration, :registration_fee, :tuition_fee, :description)"
        );
        return $stmt->execute([
            'branch_id'        => $data['branch_id'],
            'name'             => $data['name'],
            'duration'         => $data['duration'],
            'registration_fee' => $data['registration_fee'] ?? 0,
            'tuition_fee'      => $data['tuition_fee']      ?? 0,
            'description'      => $data['description']      ?? '',
        ]);
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET branch_id = :branch_id, name = :name, duration = :duration,
                 registration_fee = :registration_fee, tuition_fee = :tuition_fee,
                 description = :description
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'               => $id,
            'branch_id'        => $data['branch_id'],
            'name'             => $data['name'],
            'duration'         => $data['duration'],
            'registration_fee' => $data['registration_fee'] ?? 0,
            'tuition_fee'      => $data['tuition_fee']      ?? 0,
            'description'      => $data['description']      ?? '',
        ]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
