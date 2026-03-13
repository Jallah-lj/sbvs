<?php
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

if ($action == 'list') {
    // Fetch only users with 'Branch Admin' role
    $query = "SELECT u.id, u.name, u.email, u.status, b.name as branch_name 
              FROM users u 
              JOIN branches b ON u.branch_id = b.id 
              WHERE u.role = 'Branch Admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo json_encode(["data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $password  = $_POST['password'] ?? '';

    if (!$name || !$email || !$branch_id || !$password) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Check for duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(["status" => "error", "message" => "Email already in use"]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $query  = "INSERT INTO users (branch_id, name, email, password_hash, role, status) 
               VALUES (?, ?, ?, ?, 'Branch Admin', 'Active')";
    $stmt   = $db->prepare($query);

    if ($stmt->execute([$branch_id, $name, $email, $hashed])) {
        echo json_encode(["status" => "success", "message" => "Branch Admin created successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create Admin"]);
    }
}

if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $status    = $_POST['status'] ?? 'Active';
    $password  = $_POST['password'] ?? '';

    if (!$id || !$name || !$email || !$branch_id) {
        echo json_encode(["status" => "error", "message" => "Required fields missing"]);
        exit;
    }

    // Check for duplicate email (exclude current user)
    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        echo json_encode(["status" => "error", "message" => "Email already in use by another account"]);
        exit;
    }

    if ($password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $db->prepare("UPDATE users SET name=?, email=?, branch_id=?, status=?, password_hash=? WHERE id=? AND role='Branch Admin'");
        $stmt->execute([$name, $email, $branch_id, $status, $hashed, $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, branch_id=?, status=? WHERE id=? AND role='Branch Admin'");
        $stmt->execute([$name, $email, $branch_id, $status, $id]);
    }

    echo json_encode(["status" => "success", "message" => "Admin updated successfully"]);
}

if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'Branch Admin'");
    if ($stmt->execute([$id])) {
        echo json_encode(["status" => "success", "message" => "Admin deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete Admin"]);
    }
}
