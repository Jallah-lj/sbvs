<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once '../../../../database.php';
$db = (new Database())->getConnection();

header('Content-Type: application/json');

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$userId        = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ────────────────────────────────────────────────────────────────────────────
// SCHEMA & LOCK CHECKING
// ────────────────────────────────────────────────────────────────────────────

function ensureEnrollmentColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // locked_fee column
    $chk = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'enrollments'
           AND COLUMN_NAME = 'locked_fee'"
    );
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE enrollments ADD COLUMN locked_fee DECIMAL(10,2) NULL");
    }

    // Lock tracking columns
    $cols = ['locked_by' => 'locked_by INT NULL', 
             'locked_by_role' => 'locked_by_role VARCHAR(50) NULL',
             'last_edited_at' => 'last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'];
    foreach ($cols as $colName => $colDef) {
        $chk = $db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'enrollments'
               AND COLUMN_NAME = '{$colName}'"
        );
        if ((int)$chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE enrollments ADD COLUMN {$colDef}");
        }
    }
}

ensureEnrollmentColumns($db);

function isEnrollmentLockedBySuperAdmin(PDO $db, $enrollmentId) {
    $stmt = $db->prepare("SELECT locked_by_role FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && $row['locked_by_role'] === 'Super Admin';
}

function getEnrollmentLockStatus(PDO $db, $enrollmentId) {
    $stmt = $db->prepare(
        "SELECT e.locked_by_role, u.name as locked_by_name 
         FROM enrollments e
         LEFT JOIN users u ON e.locked_by = u.id
         WHERE e.id = ?"
    );
    $stmt->execute([$enrollmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['locked_by_role'] === 'Super Admin') {
        return [
            'is_locked' => true,
            'locked_by' => $row['locked_by_name'] ?? 'Super Admin',
            'message' => 'This enrollment was modified by Super Admin and is locked from direct edits.'
        ];
    }
    return ['is_locked' => false];
}

// Helper: verify student ownership
function checkStudentAccess(PDO $db, int $sid, bool $isSuperAdmin, int $sessionBranch): ?array
{
    $stmt = $db->prepare(
        "SELECT s.id, s.branch_id, u.name AS student_name, s.student_id AS code
         FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$sid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (!$isSuperAdmin && (int)$row['branch_id'] !== $sessionBranch) return null;
    return $row;
}

switch ($action) {

    // ── List enrollments for a student (with per-enrollment paid/outstanding) ─
    case 'list':
        $sid = (int)($_GET['student_id'] ?? 0);
        if (!$sid) { echo json_encode(['success' => false, 'message' => 'student_id required']); break; }
        if (!checkStudentAccess($db, $sid, $isSuperAdmin, $sessionBranch)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        $stmt = $db->prepare(
            "SELECT e.id AS enrollment_id,
                    e.status, e.enrollment_date,
                    c.id AS course_id, c.name AS course_name, c.duration,
                    COALESCE(e.locked_fee, c.fees) AS fees,
                    b.id AS batch_id, b.name AS batch_name, b.start_date, b.end_date,
                    COALESCE((
                        SELECT SUM(p.amount) FROM payments p
                        WHERE p.enrollment_id = e.id AND p.status = 'Active'
                    ), 0) AS total_paid
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             JOIN batches  b ON e.batch_id  = b.id
             WHERE e.student_id = ?
             ORDER BY e.enrollment_date DESC");
        $stmt->execute([$sid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['balance'] = max(0, round((float)$r['fees'] - (float)$r['total_paid'], 2));
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ── Get available courses + batches for student's branch ─────────────────
    case 'get_courses':
        $sid = (int)($_GET['student_id'] ?? 0);
        $sRow = checkStudentAccess($db, $sid, $isSuperAdmin, $sessionBranch);
        if (!$sRow) { echo json_encode(['success' => false, 'message' => 'Student not found or access denied']); break; }

        $branchId = (int)$sRow['branch_id'];
        $stmt = $db->prepare(
            "SELECT c.id, c.name, c.fees, c.duration FROM courses c WHERE c.branch_id = ? ORDER BY c.name");
        $stmt->execute([$branchId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;

    // ── Get batches for a given course ────────────────────────────────────────
    case 'get_batches':
        $cid = (int)($_GET['course_id'] ?? 0);
        if (!$cid) { echo json_encode(['success' => false, 'data' => []]); break; }
        $stmt = $db->prepare(
            "SELECT id, name, start_date, end_date FROM batches WHERE course_id = ? ORDER BY start_date DESC");
        $stmt->execute([$cid]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── Enroll student in a course + batch ───────────────────────────────────
    case 'enroll':
        $sid    = (int)($_POST['student_id']     ?? 0);
        $cid    = (int)($_POST['course_id']      ?? 0);
        $bid    = (int)($_POST['batch_id']       ?? 0);
        $eDate  = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
        $status = trim($_POST['status']          ?? 'Active');

        if (!$sid || !$cid || !$bid) {
            echo json_encode(['success' => false, 'message' => 'Student, course, and batch are required.']); break;
        }

        $sRow = checkStudentAccess($db, $sid, $isSuperAdmin, $sessionBranch);
        if (!$sRow) { echo json_encode(['success' => false, 'message' => 'Access denied']); break; }

        // Prevent duplicate active enrollment in same course
        $dup = $db->prepare(
            "SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status='Active'");
        $dup->execute([$sid, $cid]);
        if ($dup->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student is already actively enrolled in this course.']); break;
        }

        // Verify batch belongs to same course
        $bc = $db->prepare("SELECT id FROM batches WHERE id=? AND course_id=?");
        $bc->execute([$bid, $cid]);
        if (!$bc->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Selected batch does not belong to the chosen course.']); break;
        }

        // Snapshot current course fee into enrollment (locked fee)
        $cf = $db->prepare("SELECT fees FROM courses WHERE id=? LIMIT 1");
        $cf->execute([$cid]);
        $lockedFee = (float)$cf->fetchColumn();

        $ins = $db->prepare(
            "INSERT INTO enrollments (student_id, course_id, batch_id, locked_fee, status, enrollment_date)
             VALUES (?,?,?,?,?,?)");
        $ins->execute([$sid, $cid, $bid, $lockedFee, $status, $eDate]);

        echo json_encode(['success' => true, 'message' => 'Student enrolled successfully!',
                          'enrollment_id' => (int)$db->lastInsertId()]);
        break;

    // ── Update enrollment status ──────────────────────────────────────────────
    case 'update_status':
        $eid    = (int)($_POST['enrollment_id'] ?? 0);
        $status = trim($_POST['status']         ?? '');

        if (!$eid || !in_array($status, ['Active', 'Completed', 'Dropped'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); break;
        }

        // Verify ownership via student's branch
        $eq = $db->prepare(
            "SELECT e.id, s.branch_id FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.id=?");
        $eq->execute([$eid]);
        $eRow = $eq->fetch(PDO::FETCH_ASSOC);
        if (!$eRow) { echo json_encode(['success' => false, 'message' => 'Enrollment not found']); break; }
        if (!$isSuperAdmin && (int)$eRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        // ──── CHECK LOCK STATUS ────
        if (!$isSuperAdmin && isEnrollmentLockedBySuperAdmin($db, $eid)) {
            $lockStatus = getEnrollmentLockStatus($db, $eid);
            echo json_encode([
                'success' => false,
                'message' => 'This enrollment is locked by Super Admin. You can request changes.',
                'locked' => true,
                'lock_info' => $lockStatus,
                'action_available' => 'request_change'
            ]);
            break;
        }

        $db->prepare("UPDATE enrollments SET status=? WHERE id=?")->execute([$status, $eid]);

        // If Super Admin, mark as locked
        if ($isSuperAdmin) {
            $db->prepare("UPDATE enrollments SET locked_by = ?, locked_by_role = 'Super Admin' WHERE id = ?")
                ->execute([$userId, $eid]);
        }

        echo json_encode(['success' => true, 'message' => "Enrollment status updated to {$status}."]);
        break;

    // ── Remove enrollment (only if no payments are linked) ───────────────────
    case 'delete':
        $eid = (int)($_POST['enrollment_id'] ?? 0);
        if (!$eid) { echo json_encode(['success' => false, 'message' => 'enrollment_id required']); break; }

        $eq = $db->prepare(
            "SELECT e.id, s.branch_id FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.id=?");
        $eq->execute([$eid]);
        $eRow = $eq->fetch(PDO::FETCH_ASSOC);
        if (!$eRow) { echo json_encode(['success' => false, 'message' => 'Enrollment not found']); break; }
        if (!$isSuperAdmin && (int)$eRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        // ──── CHECK LOCK STATUS ────
        if (!$isSuperAdmin && isEnrollmentLockedBySuperAdmin($db, $eid)) {
            $lockStatus = getEnrollmentLockStatus($db, $eid);
            echo json_encode([
                'success' => false,
                'message' => 'This enrollment is locked by Super Admin. You can request deletion.',
                'locked' => true,
                'lock_info' => $lockStatus,
                'action_available' => 'request_change'
            ]);
            break;
        }

        // Block deletion if payments are linked
        $pc = $db->prepare("SELECT COUNT(*) FROM payments WHERE enrollment_id=? AND status='Active'");
        $pc->execute([$eid]);
        if ((int)$pc->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove enrollment — active payments are linked to it. Void the payments first.']); break;
        }

        $db->prepare("DELETE FROM enrollments WHERE id=?")->execute([$eid]);
        echo json_encode(['success' => true, 'message' => 'Enrollment removed.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
