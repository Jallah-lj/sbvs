<?php
class Branch {
    private $conn;
    private $table_name = "branches";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Fetch all branches for the Super Admin list
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Create a new branch
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, address, phone, email, status) 
                  VALUES (:name, :address, :phone, :email, :status)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':name'    => $data['name'],
            ':address' => $data['address'],
            ':phone'   => $data['phone'],
            ':email'   => $data['email'],
            ':status'  => $data['status']
        ]);
    }

    // Update an existing branch
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                  SET name = :name, address = :address, phone = :phone,
                      email = :email, status = :status
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':id'      => $id,
            ':name'    => $data['name'],
            ':address' => $data['address'],
            ':phone'   => $data['phone'],
            ':email'   => $data['email'],
            ':status'  => $data['status']
        ]);
    }

    // Delete a branch
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    // Get a single branch by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>