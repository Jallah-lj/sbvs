<?php
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';

// STRICT SECURITY: Only a Super Admin can manage other Super Admins
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access Denied"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_GET['action'] ?? '';

// ACTION: List all Super Admins
if ($action == 'list') {
    $query = "SELECT id, name, email, status, created_at FROM users WHERE role = 'Super Admin' ORDER BY id DESC";
    $stmt  = $db->prepare($query);
    $stmt->execute();
    echo json_encode(["data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ACTION: Create new Super Admin
if ($action == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = strip_tags(trim($_POST['name'] ?? ''));
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]);
        exit;
    }

    // Check for duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(["status" => "error", "message" => "Email is already in use"]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Super Admins belong to branch_id = 1 (main/head branch)
    $query = "INSERT INTO users (branch_id, name, email, password_hash, role, status) 
              VALUES (1, ?, ?, ?, 'Super Admin', 'Active')";
    $stmt  = $db->prepare($query);

    if ($stmt->execute([$name, $email, $hashed])) {
        echo json_encode(["status" => "success", "message" => "New Super Admin added successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error occurred"]);
    }
}

// ACTION: Update Super Admin
if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = strip_tags(trim($_POST['name'] ?? ''));
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $status   = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';

    if (!$id || !$name || !$email) {
        echo json_encode(["status" => "error", "message" => "Required fields missing"]);
        exit;
    }

    // Prevent editing yourself into a broken state (optional: remove if not needed)
    if ($id == $_SESSION['user_id'] ?? 0) {
        echo json_encode(["status" => "error", "message" => "You cannot edit your own account here"]);
        exit;
    }

    // Check for duplicate email (exclude current)
    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        echo json_encode(["status" => "error", "message" => "Email is already in use by another account"]);
        exit;
    }

    if ($password) {
        if (strlen($password) < 6) {
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $db->prepare("UPDATE users SET name=?, email=?, status=?, password_hash=? WHERE id=? AND role='Super Admin'");
        $stmt->execute([$name, $email, $status, $hashed, $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, status=? WHERE id=? AND role='Super Admin'");
        $stmt->execute([$name, $email, $status, $id]);
    }

    echo json_encode(["status" => "success", "message" => "Super Admin updated successfully"]);
}

// ACTION: Delete Super Admin
if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }

    // Prevent deleting yourself
    if ($id == ($_SESSION['user_id'] ?? 0)) {
        echo json_encode(["status" => "error", "message" => "You cannot delete your own account"]);
        exit;
    }

    // Ensure at least one Super Admin remains
    $countStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'Super Admin'");
    if ((int)$countStmt->fetchColumn() <= 1) {
        echo json_encode(["status" => "error", "message" => "Cannot delete the last Super Admin"]);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'Super Admin'");
    if ($stmt->execute([$id])) {
        echo json_encode(["status" => "success", "message" => "Super Admin deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete Super Admin"]);
    }
}
