<?php
/**
 * SBVS - Liberia Vocational School Management System
 * Main Entry & Role-Based Router
 */
session_start();

// 1. Check if the user is even logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in? Send them to the login screen
    header("Location: views/login.php");
    exit;
}

// 2. If logged in, route them based on their specific Role
// This prevents a Student from accidentally landing on a Super Admin page.
$role = $_SESSION['role'];

switch ($role) {
    case 'Super Admin':
        header("Location: views/dashboard.php");
        break;
        
    case 'Branch Admin':
        header("Location: views/branch_dashboard.php");
        break;
        
    case 'Teacher':
        header("Location: views/teacher_dashboard.php");
        break;
        
    case 'Accountant':
        header("Location: views/accounting_dashboard.php");
        break;
        
    case 'Student':
        header("Location: views/student_profile.php");
        break;
        
    default:
        // If role is undefined, force a logout to clear the corrupted session
        header("Location: controllers/LogoutController.php");
        break;
}
exit;