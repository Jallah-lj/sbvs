<?php
class Student {
    private $conn;
    private $table_name = "students";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generates a unique, standardized Student ID based on the branch code
     * Format: {BRANCH_CODE}-{YEAR}-{4_DIGIT_AUTO_INCREMENT}
     *
     * @param int $branch_id
     * @param string $branch_code
     * @return string
     * @throws Exception
     */
    public function generateStudentId($branch_id, $branch_code) {
        $year = date('Y');
        $prefix = strtoupper($branch_code) . '-' . $year . '-';

        // Query the latest student ID from this branch to increment
        $query = "SELECT student_id FROM " . $this->table_name . " 
                  WHERE branch_id = :branch_id AND student_id LIKE :prefix 
                  ORDER BY id DESC LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
            $search_prefix = $prefix . '%';
            $stmt->bindParam(':prefix', $search_prefix, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $last_id = $row['student_id'];
                
                // Extract the numeric part (last 4 characters)
                $last_number = (int) substr($last_id, -4);
                $new_number = $last_number + 1;
            } else {
                $new_number = 1; // Start at 0001
            }

            // Pad the number to 4 digits (e.g., 0001, 0045, 1024)
            return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);

        } catch (PDOException $e) {
            error_log("Student ID Generation Error: " . $e->getMessage());
            throw new Exception("Unable to generate Student ID.");
        }
    }
}
?>
