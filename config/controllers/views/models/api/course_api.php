<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../../../../database.php';
require_once '../Course.php';

$database = new Database();
$db = $database->getConnection();
$courseModel = new Course($db);

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');

$action = $_GET['action'] ?? '';

// Lightweight list for dropdowns (approval workflow etc.)
if ($action === 'list_simple') {
    $bid = $isSuperAdmin ? (int)($_GET['branch_id'] ?? 0) : $sessionBranch;
    $sql = "SELECT id, name FROM courses";
    $args = [];
    if ($bid) { $sql .= " WHERE branch_id = ?"; $args[] = $bid; }
    $sql .= " ORDER BY name";
    $st  = $db->prepare($sql);
    $st->execute($args);
    echo json_encode(["data" => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// LIST
if ($action === 'list') {
    $branch_id = $isSuperAdmin ? ($_GET['branch_id'] ?: null) : ($sessionBranch ?: null);
    echo json_encode(['data' => $courseModel->getAll($branch_id)]);
    exit;
}

// GET single
if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID']); exit; }
    $course = $courseModel->getById($id);
    // Ownership check
    if (!$isSuperAdmin && $sessionBranch && $course && $course['branch_id'] != $sessionBranch) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }
    echo json_encode($course ?: ['status' => 'error', 'message' => 'Not found']);
    exit;
}

// SAVE (create)
if ($action === 'save') {
    $name             = trim($_POST['name'] ?? '');
    $duration         = trim($_POST['duration'] ?? '');
    $registration_fee = floatval($_POST['registration_fee'] ?? 0);
    $tuition_fee      = floatval($_POST['tuition_fee']      ?? 0);
    $branch_id        = $isSuperAdmin ? intval($_POST['branch_id'] ?? 0) : $sessionBranch;
    $description      = trim($_POST['description'] ?? '');

    if (!$name || !$duration || !$branch_id) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    try {
        $ok = $courseModel->create([
            'name'             => $name,
            'duration'         => $duration,
            'registration_fee' => $registration_fee,
            'tuition_fee'      => $tuition_fee,
            'branch_id'        => $branch_id,
            'description'      => $description,
        ]);
        echo json_encode($ok
            ? ['status' => 'success', 'message' => 'Course created successfully.']
            : ['status' => 'error',   'message' => 'Failed to create course.']
        );
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// UPDATE
if ($action === 'update') {
    $id               = intval($_POST['id'] ?? 0);
    $name             = trim($_POST['name'] ?? '');
    $duration         = trim($_POST['duration'] ?? '');
    $registration_fee = floatval($_POST['registration_fee'] ?? 0);
    $tuition_fee      = floatval($_POST['tuition_fee']      ?? 0);
    $description      = trim($_POST['description'] ?? '');

    if (!$id || !$name || !$duration) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Ownership check for non-Super Admin
    if (!$isSuperAdmin && $sessionBranch) {
        $chk = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ?");
        $chk->execute([$id, $sessionBranch]);
        if (!$chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied: course not in your branch.']);
            exit;
        }
    }

    $branch_id = $isSuperAdmin ? intval($_POST['branch_id'] ?? 0) : $sessionBranch;

    try {
        $ok = $courseModel->update($id, [
            'name'             => $name,
            'duration'         => $duration,
            'registration_fee' => $registration_fee,
            'tuition_fee'      => $tuition_fee,
            'branch_id'        => $branch_id,
            'description'      => $description,
        ]);
        echo json_encode($ok
            ? ['status' => 'success', 'message' => 'Course updated successfully.']
            : ['status' => 'error',   'message' => 'Failed to update course.']
        );
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// DELETE
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID']); exit; }

    // Ownership check for non-Super Admin
    if (!$isSuperAdmin && $sessionBranch) {
        $chk = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ?");
        $chk->execute([$id, $sessionBranch]);
        if (!$chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied: course not in your branch.']);
            exit;
        }
    }

    try {
        $ok = $courseModel->delete($id);
        echo json_encode($ok
            ? ['status' => 'success', 'message' => 'Course deleted.']
            : ['status' => 'error',   'message' => 'Failed to delete course.']
        );
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
