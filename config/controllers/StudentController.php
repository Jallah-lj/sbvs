<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../database.php';
require_once './views/models/Student.php'; // Assuming Student model exists or will be created

$database = new Database();
$db = $database->getConnection();

// Instantiate Student object (Make sure class exists)
if (class_exists('Student')) {
    $student = new Student($db);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'register') {
    // Basic CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // Ensure user has permission (e.g., Admin, Branch Admin, or Super Admin)
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['Admin', 'Branch Admin', 'Super Admin'])) {
        die("Unauthorized access.");
    }

    $branch_id = filter_var($_POST['branch_id'], FILTER_VALIDATE_INT);
    $branch_code = htmlspecialchars(strip_tags($_POST['branch_code']));

    if (!$branch_id || empty($branch_code)) {
        die('Invalid branch data provided.');
    }
    
    // Generate the ID automatically via Model
    if (isset($student)) {
        $new_student_id = $student->generateStudentId($branch_id, $branch_code);
        
        // Example structure for continuing insertion...
        // INSERT INTO students (student_id, branch_id, ...) VALUES ($new_student_id, $branch_id, ...)
    } else {
        die('System Error: User Model not implemented.');
    }
}
?>