<?php
/**
 * approval_workflow_api.php
 * Handles discount approval requests.
 * Branch Admin can submit; Super Admin can approve/reject.
 */
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';

if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db     = (new Database())->getConnection();
$role   = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? 'list';

// ── Auto-create tables if missing ─────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS system_settings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    setting_key  VARCHAR(100) NOT NULL UNIQUE,
    setting_val  TEXT         NOT NULL,
    label        VARCHAR(200) NULL,
    category     VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_by   INT          NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("INSERT IGNORE INTO system_settings (setting_key, setting_val, label, category)
           VALUES ('max_discount_pct','15','Maximum Discount % (without approval)','finance')");

$db->exec("CREATE TABLE IF NOT EXISTS discount_approvals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT             NOT NULL,
    course_id       INT             NOT NULL,
    branch_id       INT             NOT NULL,
    requested_by    INT             NOT NULL,
    discount_pct    DECIMAL(5,2)    NOT NULL,
    justification   TEXT            NULL,
    status          ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    reviewed_by     INT             NULL,
    review_notes    TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     TIMESTAMP       NULL,
    FOREIGN KEY (student_id)   REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (course_id)    REFERENCES courses(id)   ON DELETE CASCADE,
    FOREIGN KEY (branch_id)    REFERENCES branches(id)  ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── LIST ──────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $isSA     = ($role === 'Super Admin');
    $branchId = (int)($_SESSION['branch_id'] ?? 0);
    $status   = $_GET['status'] ?? '';

    $sql = "SELECT da.id,
                   CONCAT(u2.name) AS student_name,
                   s.student_id    AS student_code,
                   c.name          AS course_name,
                   b.name          AS branch_name,
                   u.name          AS requested_by_name,
                   da.discount_pct,
                   da.justification,
                   da.status,
                   COALESCE(rv.name,'—') AS reviewed_by_name,
                   da.review_notes,
                   da.created_at,
                   da.reviewed_at
            FROM discount_approvals da
            JOIN students s ON da.student_id = s.id
            JOIN users u2   ON s.user_id     = u2.id
            JOIN courses c  ON da.course_id  = c.id
            JOIN branches b ON da.branch_id  = b.id
            JOIN users u    ON da.requested_by = u.id
            LEFT JOIN users rv ON da.reviewed_by = rv.id";
    $where  = [];
    $params = [];

    if (!$isSA) {
        $where[]  = 'da.branch_id = ?';
        $params[] = $branchId;
    }
    if ($status) {
        $where[]  = 'da.status = ?';
        $params[] = $status;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY da.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── SUBMIT (Branch Admin requests a discount) ─────────────────────────────────
if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role !== 'Branch Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Only Branch Admins may submit discount requests']);
        exit;
    }
    $studentId     = (int)($_POST['student_id']    ?? 0);
    $courseId      = (int)($_POST['course_id']     ?? 0);
    $discountPct   = (float)($_POST['discount_pct'] ?? 0);
    $justification = trim($_POST['justification']  ?? '');
    $branchId      = (int)($_SESSION['branch_id']  ?? 0);
    $userId        = (int)($_SESSION['user_id']    ?? 0);

    if (!$studentId || !$courseId || !$discountPct || !$branchId) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    // Check max discount policy
    $maxRow = $db->query("SELECT setting_val FROM system_settings WHERE setting_key = 'max_discount_pct'")->fetch(PDO::FETCH_ASSOC);
    $maxPct = $maxRow ? (float)$maxRow['setting_val'] : 15;

    if ($discountPct <= $maxPct) {
        echo json_encode(['status' => 'error', 'message' => "Discounts up to {$maxPct}% do not need approval"]);
        exit;
    }
    if ($discountPct > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Discount cannot exceed 100%']);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO discount_approvals (student_id, course_id, branch_id, requested_by, discount_pct, justification)
         VALUES (?,?,?,?,?,?)"
    );
    if ($stmt->execute([$studentId, $courseId, $branchId, $userId, $discountPct, $justification])) {
        echo json_encode(['status' => 'success', 'message' => 'Discount approval request submitted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit request']);
    }
    exit;
}

// ── REVIEW (Super Admin approves / rejects) ───────────────────────────────────
if ($action === 'review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role !== 'Super Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Only Super Admins may review requests']);
        exit;
    }
    $id     = (int)($_POST['id']           ?? 0);
    $status = $_POST['status']             ?? '';
    $notes  = trim($_POST['review_notes']  ?? '');
    $userId = (int)($_SESSION['user_id']   ?? 0);

    if (!$id || !in_array($status, ['Approved', 'Rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE discount_approvals SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE id=?"
    );
    if ($stmt->execute([$status, $userId ?: null, $notes, $id])) {
        echo json_encode(['status' => 'success', 'message' => "Request {$status} successfully"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update request']);
    }
    exit;
}

// ── GET MAX DISCOUNT POLICY ───────────────────────────────────────────────────
if ($action === 'policy') {
    $row = $db->query("SELECT setting_val FROM system_settings WHERE setting_key = 'max_discount_pct'")->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['max_discount_pct' => $row ? (float)$row['setting_val'] : 15]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
