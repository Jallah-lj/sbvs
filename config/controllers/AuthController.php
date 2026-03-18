<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../database.php';
require_once './views/models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // Sanitize input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: " . BASE_URL . "config/controllers/views/login.php");
        exit;
    }

    $login_result = $user->login($email, $password);

    if ($login_result === true) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_success'] = "Login successful! Welcome back.";
        $_SESSION['role'] = $_SESSION['user_role'];
        header("Location: " . BASE_URL . "config/controllers/views/dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = $login_result;
        header("Location: " . BASE_URL . "config/controllers/views/login.php");
        exit;
    }
}
?>