<?php
ob_start();
session_start();

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../../../../database.php';
require_once '../Students.php';

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$userId        = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin = ($role === 'Admin');
$isTeacher = ($role === 'Teacher');

function getTeacherScope(PDO $db, int $userId): array {
    $stmt = $db->prepare("SELECT branch_id, specialization FROM teachers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'branch_id' => (int)($row['branch_id'] ?? 0),
        'specialization' => trim((string)($row['specialization'] ?? '')),
    ];
}

function teacherCanAccessStudent(PDO $db, int $studentId, int $teacherBranchId, string $teacherSpec): bool {
    if ($studentId <= 0 || $teacherBranchId <= 0) return false;

    if ($teacherSpec === '') {
        $chk = $db->prepare("SELECT id FROM students WHERE id = ? AND branch_id = ? LIMIT 1");
        $chk->execute([$studentId, $teacherBranchId]);
        return (bool)$chk->fetchColumn();
    }

    $chk = $db->prepare(
        "SELECT s.id
         FROM students s
         JOIN enrollments e ON e.student_id = s.id
         JOIN courses c ON c.id = e.course_id
         WHERE s.id = ?
           AND s.branch_id = ?
           AND c.name = ?
         LIMIT 1"
    );
    $chk->execute([$studentId, $teacherBranchId, $teacherSpec]);
    return (bool)$chk->fetchColumn();
}

function moveStudentPhotoIfUploaded(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return ['ok' => true, 'path' => null, 'message' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'message' => 'Photo upload failed. Please try again.'];
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['ok' => false, 'path' => null, 'message' => 'Invalid photo format. Allowed: JPG, JPEG, PNG, GIF, WEBP.'];
    }

    $relativeDir = 'uploads/students';
    $projectRoot = realpath(dirname(__DIR__, 5)) ?: dirname(__DIR__, 5);
    $candidateRoots = [$projectRoot, dirname($projectRoot)]; // fallback supports legacy /sbvs/uploads path
    $absoluteDir = '';

    foreach ($candidateRoots as $root) {
        $dir = rtrim($root, '/') . '/' . $relativeDir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            continue;
        }

        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }

        if (is_writable($dir)) {
            $absoluteDir = $dir;
            break;
        }
    }

    if ($absoluteDir === '') {
        return ['ok' => false, 'path' => null, 'message' => 'Upload directory is not writable. Please grant write permission to uploads/students.'];
    }

    $filename = 'stu_' . uniqid('', true) . '.' . $ext;
    $relativePath = $relativeDir . '/' . $filename;
    $absolutePath = $absoluteDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        return ['ok' => false, 'path' => null, 'message' => 'Failed to save uploaded photo.'];
    }

    return ['ok' => true, 'path' => $relativePath, 'message' => ''];
}

// ────────────────────────────────────────────────────────────────────────────
// SCHEMA AUTO-MIGRATION
// ────────────────────────────────────────────────────────────────────────────

function ensureStudentColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $cols = ['locked_by' => 'INT(11) NULL', 'locked_by_role' => 'VARCHAR(50) NULL',
             'last_edited_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'];
    foreach ($cols as $col => $def) {
        $r = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = '$col'");
        if ((int)$r->fetchColumn() === 0) {
            $db->exec("ALTER TABLE students ADD COLUMN $col $def");
        }
    }
}

// ────────────────────────────────────────────────────────────────────────────
// APPROVAL WORKFLOW HELPERS
// ────────────────────────────────────────────────────────────────────────────

function isRecordLockedBySuperAdmin($db, $studentId) {
    $stmt = $db->prepare("SELECT locked_by_role FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && $row['locked_by_role'] === 'Super Admin';
}

function getLockStatusForDisplay($db, $studentId) {
    $stmt = $db->prepare(
        "SELECT s.locked_by_role, u.name as locked_by_name 
         FROM students s
         LEFT JOIN users u ON s.locked_by = u.id
         WHERE s.id = ?"
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['locked_by_role'] === 'Super Admin') {
        return [
            'is_locked' => true,
            'locked_by' => $row['locked_by_name'] ?? 'Super Admin',
            'message' => 'This record was modified by Super Admin and is locked from direct edits.'
        ];
    }
    return ['is_locked' => false];
}

try {
    $database = new Database();
    $db       = $database->getConnection();
    $studentModel = new Student($db);
    ensureStudentColumns($db);

    $action = $_GET['action'] ?? '';

    if ($action == 'list') {
        $teacherScope = $isTeacher ? getTeacherScope($db, $userId) : ['branch_id' => 0, 'specialization' => ''];

        // Non-Super Admin always sees only their branch
        if ($isTeacher) {
            $branch_filter = ($teacherScope['branch_id'] ?: $sessionBranch) ?: null;
        } else {
            $branch_filter = $isSuperAdmin ? ($_GET['branch_id'] ?? null) : ($sessionBranch ?: null);
        }

        $query = "SELECT s.id, s.student_id, u.name, u.email, s.gender, s.phone,
                         b.name as branch_name, s.registration_date
                  FROM students s
                  JOIN users u    ON s.user_id   = u.id
                  JOIN branches b ON s.branch_id = b.id";

        $conditions = [];
        if (!empty($branch_filter)) {
            $conditions[] = "s.branch_id = " . intval($branch_filter);
        }

        if ($isTeacher && $teacherScope['specialization'] !== '') {
            $spec = $db->quote($teacherScope['specialization']);
            $conditions[] = "EXISTS (
                SELECT 1
                FROM enrollments e
                JOIN courses c ON c.id = e.course_id
                WHERE e.student_id = s.id AND c.name = {$spec}
            )";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY s.registration_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo json_encode(["data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Lightweight list for dropdowns (approval workflow etc.)
    if ($action === 'list_simple') {
        $bid = $isSuperAdmin ? (int)($_GET['branch_id'] ?? 0) : $sessionBranch;
        $sql = "SELECT s.id, u.name, s.student_id FROM students s JOIN users u ON s.user_id = u.id";
        $args = [];
        if ($bid) { $sql .= " WHERE s.branch_id = ?"; $args[] = $bid; }
        $sql .= " ORDER BY u.name";
        $st  = $db->prepare($sql);
        $st->execute($args);
        echo json_encode(["data" => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action == 'register' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!$isSuperAdmin && !$isBranchAdmin && !$isAdmin) {
            echo json_encode(["status" => "error", "message" => "Access denied: you cannot register students."]);
            exit;
        }

        $student_id = $studentModel->generateStudentId();
        // Non-Super Admin is always forced into their own branch
        $branchToUse = $isSuperAdmin ? intval($_POST['branch_id']) : $sessionBranch;
        $data = [
            'name'       => trim($_POST['name']),
            'email'      => trim($_POST['email']),
            'gender'     => trim($_POST['gender']),
            'dob'        => trim($_POST['dob']),
            'phone'      => trim($_POST['phone']),
            'address'    => trim($_POST['address']),
            'branch_id'  => $branchToUse,
            'student_id' => $student_id,
            'photo_url'  => null
        ];
        $photoUpload = moveStudentPhotoIfUploaded($_FILES['photo'] ?? []);
        if (!$photoUpload['ok']) {
            echo json_encode(["status" => "error", "message" => $photoUpload['message']]);
            exit;
        }
        if (!empty($photoUpload['path'])) {
            $data['photo_url'] = $photoUpload['path'];
        }
        $result = $studentModel->create($data);
        if ($result === true) {
            $newIdStmt = $db->prepare("SELECT s.id FROM students s WHERE s.student_id = ? LIMIT 1");
            $newIdStmt->execute([$student_id]);
            $dbStudentId = (int)$newIdStmt->fetchColumn();
            echo json_encode(["status" => "success", "message" => "Student Registered", "student_id" => $student_id, "id" => $dbStudentId]);
        } else {
            echo json_encode(["status" => "error", "message" => $result]);
        }
        exit;
    }

    if ($action == 'get' && isset($_GET['id'])) {
        $studentId = intval($_GET['id']);

        if ($isTeacher) {
            $teacherScope = getTeacherScope($db, $userId);
            $branchToUse = $teacherScope['branch_id'] ?: $sessionBranch;
            if (!teacherCanAccessStudent($db, $studentId, $branchToUse, $teacherScope['specialization'])) {
                echo json_encode(["status" => "error", "message" => "Access denied: student is outside your teaching scope."]);
                exit;
            }
        }

        $student = $studentModel->getById($studentId);
        if ($student) {
            echo json_encode(["status" => "success", "data" => $student]);
        } else {
            echo json_encode(["status" => "error", "message" => "Student not found"]);
        }
        exit;
    }

    // ──── CHECK LOCK STATUS (for UI) ────
    if ($action === 'check_lock' && isset($_GET['id'])) {
        $studentId = intval($_GET['id']);
        $lockStatus = getLockStatusForDisplay($db, $studentId);
        echo json_encode([
            'success' => true,
            'is_super_admin' => $isSuperAdmin,
            'lock_status' => $lockStatus
        ]);
        exit;
    }

    if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $studentId = intval($_POST['id']);
        // Ownership check for non-Super Admin
        if (!$isSuperAdmin && $sessionBranch) {
            if ($isBranchAdmin || $isAdmin) {
                $chk = $db->prepare("SELECT id FROM students WHERE id = ? AND branch_id = ?");
                $chk->execute([$studentId, $sessionBranch]);
                if (!$chk->fetch()) {
                    echo json_encode(["status" => "error", "message" => "Access denied: student not in your branch."]);
                    exit;
                }
            } else {
                // Other roles (like Teacher) cannot update
                echo json_encode(["status" => "error", "message" => "Access denied: you do not have permission to update students."]);
                exit;
            }
        }

        // ──── CHECK LOCK STATUS ────
        if (!$isSuperAdmin && isRecordLockedBySuperAdmin($db, $studentId)) {
            $lockStatus = getLockStatusForDisplay($db, $studentId);
            echo json_encode([
                "status" => "error",
                "message" => "This record is locked by Super Admin. You can request changes.",
                "locked" => true,
                "lock_info" => $lockStatus,
                "action_available" => "request_change"
            ]);
            exit;
        }

        $data = [
            'name'      => trim($_POST['name']),
            'email'     => trim($_POST['email']),
            'gender'    => trim($_POST['gender']),
            'dob'       => trim($_POST['dob']),
            'phone'     => trim($_POST['phone']),
            'address'   => trim($_POST['address']),
            'branch_id' => $isSuperAdmin ? intval($_POST['branch_id']) : $sessionBranch
        ];
        $photoUpload = moveStudentPhotoIfUploaded($_FILES['photo'] ?? []);
        if (!$photoUpload['ok']) {
            echo json_encode(["status" => "error", "message" => $photoUpload['message']]);
            exit;
        }
        if (!empty($photoUpload['path'])) {
            $data['photo_url'] = $photoUpload['path'];
        }
        $result = $studentModel->update($studentId, $data);
        if ($result === true) {
            // If Super Admin, mark as locked by Super Admin
            if ($isSuperAdmin) {
                $lockStmt = $db->prepare("UPDATE students SET locked_by = ?, locked_by_role = 'Super Admin' WHERE id = ?");
                $lockStmt->execute([$userId, $studentId]);
            }
            echo json_encode(["status" => "success", "message" => "Student updated"]);
        } else {
            echo json_encode(["status" => "error", "message" => $result]);
        }
        exit;
    }

    // ── DELETE student ────────────────────────────────────────────────────────
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!in_array($role, ['Super Admin', 'Branch Admin'])) {
            echo json_encode(["status" => "error", "message" => "Access denied"]);
            exit;
        }

        $studentId = (int)($_POST['id'] ?? 0);
        if (!$studentId) {
            echo json_encode(["status" => "error", "message" => "Student ID required"]);
            exit;
        }

        // Branch ownership check for Branch Admin
        if (!$isSuperAdmin) {
            $ownerChk = $db->prepare("SELECT branch_id FROM students WHERE id = ?");
            $ownerChk->execute([$studentId]);
            $ownerRow = $ownerChk->fetch(PDO::FETCH_ASSOC);
            if (!$ownerRow || (int)$ownerRow['branch_id'] !== $sessionBranch) {
                echo json_encode(["status" => "error", "message" => "Access denied"]);
                exit;
            }
        }

        // Block deletion if student has active enrollments
        $enrollChk = $db->prepare(
            "SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'Active'");
        $enrollChk->execute([$studentId]);
        if ((int)$enrollChk->fetchColumn() > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Cannot delete student — they have active enrollments. Drop all enrollments first."
            ]);
            exit;
        }

        // Block deletion if student has active payments
        $payChk = $db->prepare(
            "SELECT COUNT(*) FROM payments p
             JOIN enrollments e ON p.enrollment_id = e.id
             WHERE e.student_id = ? AND p.status = 'Active'");
        $payChk->execute([$studentId]);
        if ((int)$payChk->fetchColumn() > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Cannot delete student — active payment records are linked. Void all payments first."
            ]);
            exit;
        }

        // Delete user record (cascades via FK or manual cleanup)
        // First get user_id
        $uidStmt = $db->prepare("SELECT user_id FROM students WHERE id = ?");
        $uidStmt->execute([$studentId]);
        $userId = (int)$uidStmt->fetchColumn();

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$studentId]);
            $db->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
            if ($userId) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            }
            $db->commit();
            echo json_encode(["status" => "success", "message" => "Student deleted successfully."]);
        } catch (Exception $ex) {
            $db->rollBack();
            echo json_encode(["status" => "error", "message" => "Delete failed: " . $ex->getMessage()]);
        }
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid action"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
