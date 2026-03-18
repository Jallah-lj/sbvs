<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../../../../database.php';
require_once '../../../../helpers.php';
require_once '../../../../DashboardSecurity.php';
require_once '../Teacher.php';

$database = new Database();
$db = $database->getConnection();
$teacherModel = new Teacher($db);

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

function teacherAudit(PDO $db, string $action, string $details): void {
    DashboardSecurity::auditLog('teachers', $action, $details, $db);
}

function normalizeTeacherName(string $name): string {
    return preg_replace('/\s+/', ' ', trim($name));
}

function isValidTeacherPhone(string $phone): bool {
    return (bool)preg_match('/^[0-9+()\-\s]{7,20}$/', $phone);
}

function isValidTeacherStatus(string $status): bool {
    return in_array($status, ['Active', 'Inactive'], true);
}

function branchIsActive(PDO $db, int $branchId): bool {
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$branchId]);
    return (bool)$stmt->fetchColumn();
}

function removeTeacherPhoto(string $photoPath): void {
    if ($photoPath === '' || strpos($photoPath, 'uploads/teachers/') !== 0) {
        return;
    }

    $projectRoot = realpath(dirname(__DIR__, 5)) ?: dirname(__DIR__, 5);
    $fullPath = $projectRoot . '/' . ltrim($photoPath, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function handleTeacherPhotoUpload(array $file, ?string $oldPhoto = null): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'message' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'message' => 'Photo upload failed. Please try again.'];
    }

    $maxSize = 2 * 1024 * 1024; // 2MB
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize) {
        return ['ok' => false, 'path' => null, 'message' => 'Photo must be less than 2MB.'];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'path' => null, 'message' => 'Only JPG, JPEG, PNG, or WEBP photos are allowed.'];
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'path' => null, 'message' => 'Invalid photo format uploaded.'];
    }

    $relativeDir = 'uploads/teachers';
    $projectRoot = realpath(dirname(__DIR__, 5)) ?: dirname(__DIR__, 5);
    $absoluteDir = $projectRoot . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        return ['ok' => false, 'path' => null, 'message' => 'Unable to prepare photo upload directory.'];
    }

    if (!is_writable($absoluteDir)) {
        @chmod($absoluteDir, 0775);
    }

    if (!is_writable($absoluteDir)) {
        return ['ok' => false, 'path' => null, 'message' => 'Upload directory is not writable. Please contact administrator.'];
    }

    try {
        $random = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $random = (string)mt_rand(100000, 999999);
    }

    $filename = 'teacher_' . date('Ymd_His') . '_' . $random . '.' . $ext;
    $absoluteFile = $absoluteDir . '/' . $filename;
    $relativeFile = $relativeDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absoluteFile)) {
        return ['ok' => false, 'path' => null, 'message' => 'Failed to save uploaded photo.'];
    }

    if (!empty($oldPhoto)) {
        removeTeacherPhoto($oldPhoto);
    }

    return ['ok' => true, 'path' => $relativeFile, 'message' => ''];
}

// ── Auto-migration: ensure teachers.teacher_id column exists and backfill ──
(function (PDO $db, Teacher $model) {
    // Add teacher_id column if it doesn't exist yet
    $exists = (int)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'teachers'
           AND COLUMN_NAME  = 'teacher_id'"
    )->fetchColumn();

    if ($exists === 0) {
        $db->exec("ALTER TABLE teachers ADD COLUMN teacher_id VARCHAR(50) NULL UNIQUE");
    }

    $photoExists = (int)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'teachers'
           AND COLUMN_NAME  = 'photo_url'"
    )->fetchColumn();

    if ($photoExists === 0) {
        $db->exec("ALTER TABLE teachers ADD COLUMN photo_url VARCHAR(255) NULL");
    }

    // Backfill any rows that still have no teacher_id
    $nullRows = $db->query("SELECT id FROM teachers WHERE teacher_id IS NULL ORDER BY id ASC")
                   ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($nullRows as $tid) {
        $newId = $model->generateTeacherId();
        $db->prepare("UPDATE teachers SET teacher_id = ? WHERE id = ?")->execute([$newId, $tid]);
    }
})($db, $teacherModel);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update', 'delete'], true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!DashboardSecurity::verifyToken($token)) {
        teacherAudit($db, 'csrf_validation_failed', 'Blocked teacher mutation due to invalid CSRF token');
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Security validation failed. Refresh and try again."]);
        exit;
    }
}

// LIST
if ($action === 'list') {
    $branch_filter = $isSuperAdmin ? ($_GET['branch_id'] ?? null) : $sessionBranch;
    $rows = $teacherModel->getAll($branch_filter);
    if ($role === 'Teacher') {
        $rows = array_values(array_filter($rows, function ($r) {
            return ((int)($r['user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0));
        }));
    }
    foreach ($rows as &$r) {
        unset($r['user_id']);
    }
    unset($r);
    echo json_encode(["data" => $rows]);
    exit;
}

// GET single teacher
if ($action === 'get' && isset($_GET['id'])) {
    $teacherId = intval($_GET['id']);
    $teacher = $teacherModel->getById($teacherId);
    if ($teacher && $role === 'Teacher' && (int)$teacher['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
        teacherAudit($db, 'unauthorized_view_attempt', 'Teacher attempted to view another instructor ID ' . $teacherId);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit;
    }
    if ($teacher && !$isSuperAdmin && (int)$teacher['branch_id'] !== $sessionBranch) {
        teacherAudit($db, 'unauthorized_view_attempt', 'User attempted to view instructor ID ' . $teacherId . ' outside branch scope');
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit;
    }
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

    $name = normalizeTeacherName($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');

    if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 120 || !preg_match('/^[\p{L}\s\.\'\-]+$/u', $name)) {
        echo json_encode(["status" => "error", "message" => "Name must be 3-120 characters and contain only letters, spaces, dot, apostrophe, or hyphen."]);
        exit;
    }

    if (!isValidEmail($email)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit;
    }

    $dup = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $dup->execute([$email]);
    if ($dup->fetchColumn()) {
        echo json_encode(["status" => "error", "message" => "Email already in use."]);
        exit;
    }

    if (!isValidTeacherPhone($phone)) {
        echo json_encode(["status" => "error", "message" => "Phone must be 7 to 20 characters and contain only numbers/spaces/+/-/()."]);
        exit;
    }

    if ($specialization === '' || mb_strlen($specialization) > 120) {
        echo json_encode(["status" => "error", "message" => "Please select a valid specialization."]);
        exit;
    }

    if ($branchToUse <= 0 || !branchIsActive($db, $branchToUse)) {
        echo json_encode(["status" => "error", "message" => "Selected branch is invalid or inactive."]);
        exit;
    }

    $photoUpload = handleTeacherPhotoUpload($_FILES['photo'] ?? []);
    if (!$photoUpload['ok']) {
        echo json_encode(["status" => "error", "message" => $photoUpload['message']]);
        exit;
    }
    $data = [
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'specialization' => $specialization,
        'branch_id'      => $branchToUse,
        'photo_url'      => $photoUpload['path']
    ];
    $result = $teacherModel->create($data);
    if ($result === true) {
        teacherAudit($db, 'create', 'Created instructor ' . $name . ' (' . $email . ')');
        // Return the default password so the admin can communicate it
        echo json_encode([
            "status"           => "success",
            "default_password" => $phone ?: '(phone not set – please reset manually)'
        ]);
    } else {
        if (!empty($photoUpload['path'])) {
            removeTeacherPhoto($photoUpload['path']);
        }
        teacherAudit($db, 'create_failed', 'Create failed for email ' . $email . ': ' . $result);
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

    $existing = $teacherModel->getById($teacherId);
    if (!$existing) {
        echo json_encode(["status" => "error", "message" => "Instructor not found."]);
        exit;
    }

    $name = normalizeTeacherName($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $branchId = $isSuperAdmin ? intval($_POST['branch_id']) : $sessionBranch;

    if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 120 || !preg_match('/^[\p{L}\s\.\'\-]+$/u', $name)) {
        echo json_encode(["status" => "error", "message" => "Name must be 3-120 characters and contain only letters, spaces, dot, apostrophe, or hyphen."]);
        exit;
    }

    if (!isValidEmail($email)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit;
    }

    $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $dup->execute([$email, (int)$existing['user_id']]);
    if ($dup->fetchColumn()) {
        echo json_encode(["status" => "error", "message" => "Email already in use."]);
        exit;
    }

    if (!isValidTeacherPhone($phone)) {
        echo json_encode(["status" => "error", "message" => "Phone must be 7 to 20 characters and contain only numbers/spaces/+/-/()."]);
        exit;
    }

    if ($specialization === '' || mb_strlen($specialization) > 120) {
        echo json_encode(["status" => "error", "message" => "Please select a valid specialization."]);
        exit;
    }

    if (!isValidTeacherStatus($status)) {
        echo json_encode(["status" => "error", "message" => "Invalid status selected."]);
        exit;
    }

    if ($branchId <= 0 || !branchIsActive($db, $branchId)) {
        echo json_encode(["status" => "error", "message" => "Selected branch is invalid or inactive."]);
        exit;
    }

    $photoUpload = handleTeacherPhotoUpload($_FILES['photo'] ?? [], $existing['photo_url'] ?? null);
    if (!$photoUpload['ok']) {
        echo json_encode(["status" => "error", "message" => $photoUpload['message']]);
        exit;
    }

    $data = [
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'specialization' => $specialization,
        'branch_id'      => $branchId,
        'status'         => $status,
        'photo_url'      => $photoUpload['path']
    ];
    if ($teacherModel->update($teacherId, $data)) {
        teacherAudit($db, 'update', 'Updated instructor ID ' . $teacherId);
        echo json_encode(["status" => "success"]);
    } else {
        teacherAudit($db, 'update_failed', 'Failed updating instructor ID ' . $teacherId);
        echo json_encode(["status" => "error", "message" => "Update failed."]);
    }
    exit;
}

// DEACTIVATE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Super Admin and Branch Admin can delete
    if (!$isSuperAdmin && !$isBranchAdmin) {
        teacherAudit($db, 'unauthorized_delete_attempt', 'Role ' . $role . ' attempted to deactivate instructor');
        echo json_encode(["status" => "error", "message" => "Access Denied"]);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid instructor ID."]);
        exit;
    }
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
        teacherAudit($db, 'deactivate', 'Deactivated instructor ID ' . $id);
        echo json_encode(["status" => "success"]);
    } else {
        teacherAudit($db, 'deactivate_failed', 'Failed deactivating instructor ID ' . $id);
        echo json_encode(["status" => "error", "message" => "Deactivate failed."]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
