<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../../../../database.php';
require_once '../Teacher.php';

$database = new Database();
$db = $database->getConnection();
$teacherModel = new Teacher($db);

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

$action = $_GET['action'] ?? '';

// LIST
if ($action === 'list') {
    $branch_filter = $isSuperAdmin ? ($_GET['branch_id'] ?? null) : $sessionBranch;
    echo json_encode(["data" => $teacherModel->getAll($branch_filter)]);
    exit;
}

// GET single teacher
if ($action === 'get' && isset($_GET['id'])) {
    $teacher = $teacherModel->getById(intval($_GET['id']));
    if ($teacher) {
        echo json_encode(["status" => "success", "data" => $teacher]);
    } else {
        echo json_encode(["status" => "error", "message" => "Instructor not found"]);
    }
    exit;
}

// CREATE
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Super Admin, Branch Admin, and Admin can create
    if (!$isSuperAdmin && !$isBranchAdmin && !$isAdmin) {
        echo json_encode(["status" => "error", "message" => "Access Denied"]);
        exit;
    }
    $branchToUse = $isSuperAdmin ? intval($_POST['branch_id']) : $sessionBranch;

    $data = [
        'name'           => trim($_POST['name']),
        'email'          => trim($_POST['email']),
        'phone'          => trim($_POST['phone']),
        'specialization' => trim($_POST['specialization']),
        'branch_id'      => $branchToUse
    ];
    $result = $teacherModel->create($data);
    if ($result === true) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $result]);
    }
    exit;
}

// UPDATE
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Super Admin, Branch Admin, and Admin can update
    if (!$isSuperAdmin && !$isBranchAdmin && !$isAdmin) {
        echo json_encode(["status" => "error", "message" => "Access Denied"]);
        exit;
    }

    $teacherId = intval($_POST['id']);
    // Ownership check for non-Super Admin
    if (!$isSuperAdmin) {
        $chk = $db->prepare("SELECT id FROM teachers WHERE id = ? AND branch_id = ?");
        $chk->execute([$teacherId, $sessionBranch]);
        if (!$chk->fetch()) {
            echo json_encode(["status" => "error", "message" => "Access denied: instructor not in your branch."]);
            exit;
        }
    }

    $data = [
        'name'           => trim($_POST['name']),
        'email'          => trim($_POST['email']),
        'phone'          => trim($_POST['phone']),
        'specialization' => trim($_POST['specialization']),
        'branch_id'      => $isSuperAdmin ? intval($_POST['branch_id']) : $sessionBranch,
        'status'         => trim($_POST['status'])
    ];
    if ($teacherModel->update($teacherId, $data)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed."]);
    }
    exit;
}

// DELETE
if ($action === 'delete' && isset($_GET['id'])) {
    // Only Super Admin and Branch Admin can delete
    if (!$isSuperAdmin && !$isBranchAdmin) {
        echo json_encode(["status" => "error", "message" => "Access Denied"]);
        exit;
    }
    $id = intval($_GET['id']);
    // Ownership check for non-Super Admin
    if (!$isSuperAdmin) {
        $chk = $db->prepare("SELECT id FROM teachers WHERE id = ? AND branch_id = ?");
        $chk->execute([$id, $sessionBranch]);
        if (!$chk->fetch()) {
            echo json_encode(["status" => "error", "message" => "Access denied: teacher not in your branch."]);
            exit;
        }
    }

    if ($teacherModel->delete($id)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Delete failed."]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
