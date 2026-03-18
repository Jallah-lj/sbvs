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
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ────────────────────────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────────────────────────

function batchBranchFilter(bool $isSuperAdmin, int $sessionBranch): array
{
    if ($isSuperAdmin) return ['', []];
    return [' AND b.branch_id = ?', [$sessionBranch]];
}

switch ($action) {

    // ── List batches ──────────────────────────────────────────────────────────
    case 'list':
        [$wClause, $wParams] = batchBranchFilter($isSuperAdmin, $sessionBranch);

        // Optional branch filter for SA
        $bfId = (int)($_GET['branch_id'] ?? 0);
        if ($isSuperAdmin && $bfId) {
            $wClause  .= ' AND b.branch_id = ?';
            $wParams[] = $bfId;
        }

        // Optional course filter
        $cfId = (int)($_GET['course_id'] ?? 0);
        if ($cfId) {
            $wClause  .= ' AND b.course_id = ?';
            $wParams[] = $cfId;
        }

        $sql = "SELECT b.id, b.name, b.start_date, b.end_date,
                       b.branch_id, br.name AS branch_name,
                       b.course_id, c.name  AS course_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) AS enrollment_count,
                       CASE
                           WHEN b.start_date > CURDATE() THEN 'Upcoming'
                           WHEN b.end_date   < CURDATE() THEN 'Completed'
                           ELSE 'Active'
                       END AS status
                FROM batches b
                JOIN courses  c  ON b.course_id  = c.id
                JOIN branches br ON b.branch_id  = br.id
                WHERE 1=1 {$wClause}
                ORDER BY b.start_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($wParams);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── Get single batch ──────────────────────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); break; }

        $stmt = $db->prepare(
            "SELECT b.id, b.name, b.start_date, b.end_date, b.branch_id, b.course_id,
                    br.name AS branch_name, c.name AS course_name
             FROM batches b
             JOIN courses  c  ON b.course_id  = c.id
             JOIN branches br ON b.branch_id  = br.id
             WHERE b.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Batch not found']); break; }

        // Branch scope check
        if (!$isSuperAdmin && (int)$row['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }
        echo json_encode(['success' => true, 'data' => $row]);
        break;

    // ── Create batch ──────────────────────────────────────────────────────────
    case 'save':
        $branchId  = $isSuperAdmin ? (int)($_POST['branch_id'] ?? 0) : $sessionBranch;
        $courseId  = (int)($_POST['course_id']  ?? 0);
        $name      = trim($_POST['name']        ?? '');
        $startDate = trim($_POST['start_date']  ?? '');
        $endDate   = trim($_POST['end_date']    ?? '');

        if (!$branchId || !$courseId || $name === '' || !$startDate || !$endDate) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']); break;
        }
        if ($endDate < $startDate) {
            echo json_encode(['success' => false, 'message' => 'End date must be after start date.']); break;
        }

        // Verify course belongs to branch
        $cq = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ?");
        $cq->execute([$courseId, $branchId]);
        if (!$cq->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Course does not belong to the selected branch.']); break;
        }

        $ins = $db->prepare(
            "INSERT INTO batches (branch_id, course_id, name, start_date, end_date)
             VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$branchId, $courseId, $name, $startDate, $endDate]);
        echo json_encode(['success' => true, 'message' => "Batch '{$name}' created successfully!",
                          'id' => (int)$db->lastInsertId()]);
        break;

    // ── Update batch ──────────────────────────────────────────────────────────
    case 'update':
        $id        = (int)($_POST['id']          ?? 0);
        $branchId  = $isSuperAdmin ? (int)($_POST['branch_id'] ?? 0) : $sessionBranch;
        $courseId  = (int)($_POST['course_id']   ?? 0);
        $name      = trim($_POST['name']         ?? '');
        $startDate = trim($_POST['start_date']   ?? '');
        $endDate   = trim($_POST['end_date']      ?? '');

        if (!$id || !$branchId || !$courseId || $name === '' || !$startDate || !$endDate) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']); break;
        }
        if ($endDate < $startDate) {
            echo json_encode(['success' => false, 'message' => 'End date must be after start date.']); break;
        }

        // Ownership check
        $owner = $db->prepare("SELECT branch_id FROM batches WHERE id = ?");
        $owner->execute([$id]);
        $ownerRow = $owner->fetch(PDO::FETCH_ASSOC);
        if (!$ownerRow) { echo json_encode(['success' => false, 'message' => 'Batch not found']); break; }
        if (!$isSuperAdmin && (int)$ownerRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        // Verify course belongs to branch
        $cq = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ?");
        $cq->execute([$courseId, $branchId]);
        if (!$cq->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Course does not belong to the selected branch.']); break;
        }

        $upd = $db->prepare(
            "UPDATE batches SET branch_id=?, course_id=?, name=?, start_date=?, end_date=? WHERE id=?");
        $upd->execute([$branchId, $courseId, $name, $startDate, $endDate, $id]);
        echo json_encode(['success' => true, 'message' => "Batch '{$name}' updated successfully!"]);
        break;

    // ── Delete batch ──────────────────────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'id required']); break; }

        // Ownership check
        $owner = $db->prepare("SELECT branch_id FROM batches WHERE id = ?");
        $owner->execute([$id]);
        $ownerRow = $owner->fetch(PDO::FETCH_ASSOC);
        if (!$ownerRow) { echo json_encode(['success' => false, 'message' => 'Batch not found']); break; }
        if (!$isSuperAdmin && (int)$ownerRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        // Block deletion if enrollments exist
        $ec = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE batch_id = ?");
        $ec->execute([$id]);
        if ((int)$ec->fetchColumn() > 0) {
            echo json_encode(['success' => false,
                'message' => 'Cannot delete batch — students are enrolled in it. Remove enrollments first.']); break;
        }

        $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully.']);
        break;

    // ── Get courses for a branch (used in add/edit modal dropdowns) ───────────
    case 'courses_by_branch':
        $bId = $isSuperAdmin ? (int)($_GET['branch_id'] ?? 0) : $sessionBranch;
        if (!$bId) { echo json_encode(['success' => true, 'data' => []]); break; }

        $stmt = $db->prepare(
            "SELECT id, name, fees FROM courses WHERE branch_id = ? ORDER BY name");
        $stmt->execute([$bId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
