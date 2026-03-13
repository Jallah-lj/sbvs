<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Attempts to log a user in.
     * @param string $email
     * @param string $password
     * @return true|string Returns true on success, or an error string on failure.
     */
    public function login($email, $password) {
        try {
            $query = "SELECT id, name, email, password_hash, role, branch_id FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (password_verify($password, $row['password_hash'])) {
                    $_SESSION['user_id']    = $row['id'];
                    $_SESSION['user_name']  = $row['name'];
                    $_SESSION['name']       = $row['name'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_role']  = $row['role'];
                    $_SESSION['branch_id']  = $row['branch_id'];
                    return true;
                } else {
                    return "Invalid password.";
                }
            } else {
                return "No user found with that email address.";
            }
        } catch (PDOException $e) {
            // Log the error securely in production, rather than returning the raw SQL exception
            error_log("Login Error: " . $e->getMessage());
            return "An internal system error occurred. Please try again later.";
        }
    }
}
?>