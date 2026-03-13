<?php
/**
 * attendance_api.php
 * CRUD for daily attendance.
 * Branch Admin (and Super Admin) only.
 */
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';
require_once '../../../../config.php';

if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db       = (new Database())->getConnection();
$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$isSA     = ($role === 'Super Admin');
$action   = $_GET['action'] ?? 'list';

// ── Auto-create table if missing ───────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS attendance (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    batch_id      INT          NOT NULL,
    student_id    INT          NOT NULL,
    branch_id     INT          NOT NULL,
    attend_date   DATE         NOT NULL,
    status        ENUM('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
    notes         VARCHAR(255) NULL,
    recorded_by   INT          NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_student_date (batch_id, student_id, attend_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── GET STUDENTS FOR A BATCH ──────────────────────────────────────────────────
if ($action === 'batch_students') {
    $batchId = (int)($_GET['batch_id'] ?? 0);
    $date    = $_GET['date'] ?? date('Y-m-d');

    if (!$batchId) {
        echo json_encode(['data' => []]);
        exit;
    }

    $stmt = $db->prepare(
        "SELECT s.id AS student_id, u.name AS student_name, s.student_id AS student_code,
                COALESCE(a.status,'') AS att_status, COALESCE(a.notes,'') AS notes
         FROM enrollments e
         JOIN students s ON e.student_id = s.id
         JOIN users u    ON s.user_id    = u.id
         LEFT JOIN attendance a ON a.student_id = s.id
                               AND a.batch_id   = ?
                               AND a.attend_date = ?
         WHERE e.batch_id = ? AND e.status = 'Active'
         ORDER BY u.name"
    );
    $stmt->execute([$batchId, $date, $batchId]);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET BATCHES FOR BRANCH ────────────────────────────────────────────────────
if ($action === 'batches') {
    $bid  = $isSA ? (int)($_GET['branch_id'] ?? 0) : $branchId;
    $sql  = "SELECT b.id, b.batch_name, c.name AS course_name
             FROM batches b
             JOIN courses c ON b.course_id = c.id";
    $args = [];
    if ($bid) {
        $sql  .= " WHERE b.branch_id = ?";
        $args[] = $bid;
    } elseif (!$isSA) {
        echo json_encode(['data' => []]);
        exit;
    }
    $sql .= " ORDER BY b.batch_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── LIST ATTENDANCE (filter by batch + date range) ────────────────────────────
if ($action === 'list') {
    $batchId  = (int)($_GET['batch_id']  ?? 0);
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

    $sql = "SELECT a.attend_date, u.name AS student_name, s.student_id AS student_code,
                   a.status, a.notes
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN users u    ON s.user_id    = u.id";
    $where  = ['a.attend_date BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if (!$isSA) {
        $where[]  = 'a.branch_id = ?';
        $params[] = $branchId;
    }
    if ($batchId) {
        $where[]  = 'a.batch_id = ?';
        $params[] = $batchId;
    }
    $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.attend_date DESC, u.name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── SAVE (bulk upsert for one session) ────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId    = (int)($_POST['batch_id']  ?? 0);
    $date       = trim($_POST['attend_date'] ?? '');
    $records    = $_POST['records']          ?? [];
    $userId     = (int)($_SESSION['user_id'] ?? 0);
    $bId        = $isSA ? (int)($_POST['branch_id'] ?? $branchId) : $branchId;

    if (!$batchId || !$date || !is_array($records)) {
        echo json_encode(['status' => 'error', 'message' => 'Batch, date, and records are required']);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO attendance (batch_id, student_id, branch_id, attend_date, status, notes, recorded_by)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes), recorded_by=VALUES(recorded_by)"
    );

    $saved = 0;
    foreach ($records as $rec) {
        $sid    = (int)($rec['student_id'] ?? 0);
        $status = in_array($rec['status'] ?? '', ['Present','Absent','Late','Excused'])
                  ? $rec['status'] : 'Present';
        $notes  = trim($rec['notes'] ?? '');
        if (!$sid) continue;
        $stmt->execute([$batchId, $sid, $bId, $date, $status, $notes, $userId ?: null]);
        $saved++;
    }

    echo json_encode(['status' => 'success', 'message' => "Attendance saved for {$saved} student(s)"]);
    exit;
}

// ── SUMMARY (for dashboard widget) ───────────────────────────────────────────
if ($action === 'summary') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $bid  = $isSA ? 0 : $branchId;

    $sql    = "SELECT
                 SUM(status='Present') AS present,
                 SUM(status='Absent')  AS absent,
                 SUM(status='Late')    AS late,
                 SUM(status='Excused') AS excused,
                 COUNT(*) AS total
               FROM attendance
               WHERE attend_date = ?";
    $params = [$date];
    if ($bid) {
        $sql    .= " AND branch_id = ?";
        $params[] = $bid;
    }
    $row = $db->prepare($sql);
    $row->execute($params);
    echo json_encode($row->fetch(PDO::FETCH_ASSOC) ?: ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'total'=>0]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
