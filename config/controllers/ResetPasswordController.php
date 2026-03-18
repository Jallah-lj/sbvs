<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($token) || empty($password) || empty($password_confirm)) {
        $_SESSION['reset_error'] = "All fields are required.";
        header("Location: views/reset_password.php?token=" . urlencode($token));
        exit;
    }

    if ($password !== $password_confirm) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: views/reset_password.php?token=" . urlencode($token));
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION['reset_error'] = "Password must be at least 8 characters long.";
        header("Location: views/reset_password.php?token=" . urlencode($token));
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Verify token again before updating
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expires > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update password and invalidate token
        $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $updateStmt->bindParam(':hash', $hashed_password);
        $updateStmt->bindParam(':id', $user['id']);
        
        if ($updateStmt->execute()) {
            $_SESSION['success'] = "Your password has been successfully reset. Please log in.";
            // We reuse 'error' session variable because the login page uses it to show alerts (though it's styled as an error, maybe we adjust it, or just use a generic message)
            // Let's redirect to login
            header("Location: views/login.php");
            exit;
        } else {
            $_SESSION['reset_error'] = "An error occurred while updating the password.";
            header("Location: views/reset_password.php?token=" . urlencode($token));
            exit;
        }
    } else {
        $_SESSION['reset_error'] = "The reset link is invalid or has expired.";
        header("Location: views/reset_password.php?token=" . urlencode($token));
        exit;
    }
}
