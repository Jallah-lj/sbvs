<?php
/**
 * global_programs_api.php
 * CRUD for the global_programs table.
 * Super Admin only.
 */
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$db     = (new Database())->getConnection();
$action = $_GET['action'] ?? 'list';

// ── Auto-create table if missing ───────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS global_programs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    program_code      VARCHAR(50)  NOT NULL UNIQUE,
    program_name      VARCHAR(200) NOT NULL,
    category          VARCHAR(100) NULL,
    duration_weeks    INT          NOT NULL DEFAULT 0,
    min_hours         INT          NOT NULL DEFAULT 0,
    cert_template     VARCHAR(255) NULL,
    description       TEXT         NULL,
    status            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_by        INT          NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── LIST ──────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $stmt = $db->query(
        "SELECT p.id, p.program_code, p.program_name, p.category,
                p.duration_weeks, p.min_hours, p.status,
                p.created_at, COALESCE(u.name,'—') AS created_by_name
         FROM global_programs p
         LEFT JOIN users u ON p.created_by = u.id
         ORDER BY p.program_name"
    );
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── SAVE ──────────────────────────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code     = strtoupper(trim($_POST['program_code'] ?? ''));
    $name     = trim($_POST['program_name']   ?? '');
    $cat      = trim($_POST['category']       ?? '');
    $weeks    = (int)($_POST['duration_weeks'] ?? 0);
    $hours    = (int)($_POST['min_hours']      ?? 0);
    $desc     = trim($_POST['description']    ?? '');
    $userId   = (int)($_SESSION['user_id']    ?? 0);

    if (!$code || !$name) {
        echo json_encode(['status' => 'error', 'message' => 'Program code and name are required']);
        exit;
    }

    $chk = $db->prepare("SELECT id FROM global_programs WHERE program_code = ?");
    $chk->execute([$code]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Program code already exists']);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO global_programs
            (program_code, program_name, category, duration_weeks, min_hours, description, created_by)
         VALUES (?,?,?,?,?,?,?)"
    );
    if ($stmt->execute([$code, $name, $cat, $weeks, $hours, $desc, $userId ?: null])) {
        echo json_encode(['status' => 'success', 'message' => 'Program added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add program']);
    }
    exit;
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id']             ?? 0);
    $name     = trim($_POST['program_name']    ?? '');
    $cat      = trim($_POST['category']        ?? '');
    $weeks    = (int)($_POST['duration_weeks'] ?? 0);
    $hours    = (int)($_POST['min_hours']      ?? 0);
    $desc     = trim($_POST['description']     ?? '');
    $status   = $_POST['status'] ?? 'Active';

    if (!$id || !$name) {
        echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE global_programs SET program_name=?, category=?, duration_weeks=?,
         min_hours=?, description=?, status=? WHERE id=?"
    );
    if ($stmt->execute([$name, $cat, $weeks, $hours, $desc, $status, $id])) {
        echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update program']);
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $stmt = $db->prepare("DELETE FROM global_programs WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['status' => 'success', 'message' => 'Program deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete program']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
