<?php
require_once __DIR__ . '/config.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Build DSN with socket for XAMPP compatibility
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4;unix_socket=/opt/lampp/var/mysql/mysql.sock";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Set error mode securely based on environment
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else {
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); // Do not leak errors in production
            }
            
            // Standardize fetch mode
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Real prepared statements

        } catch (PDOException $exception) {
            // Log this to a file securely instead of echoing to the user screen in production
            if (defined('APP_ENV') && APP_ENV === 'development') {
                die("Database Connection Error: " . $exception->getMessage());
            } else {
                die("A critical system error occurred. Please contact the administrator.");
            }
        }
        return $this->conn;
    }
}
?>