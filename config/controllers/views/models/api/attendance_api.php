<?php
/**
 * attendance_api.php
 * CRUD for daily attendance (course-based).
 * Branch Admin (and Super Admin) only.
 *
 * FIXES applied:
 *  1. ensureAttendanceColumn() guarantees attend_date, branch_id, course_id,
 *     batch_id, notes, recorded_by, created_at — handles any legacy table
 *     that was created with a partial schema.
 *  2. Legacy 'date' column renamed → 'attend_date' automatically.
 *  3. INSERT in save action is built dynamically — only includes columns
 *     that actually exist, so missing branch_id / course_id never cause
 *     "Column not found" errors.
 *  4. course_students LEFT JOIN date filter moved to ON clause (not WHERE)
 *     so all enrolled students always appear even with no prior record.
 *  5. Past-date guard corrected: blocks future dates only ($date > $today).
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

function jsonOut(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

set_exception_handler(function (Throwable $e) {
    jsonOut(['status' => 'error', 'message' => 'API error: ' . $e->getMessage()], 500);
});

function normalizeDateInput(string $date): ?string {
    $date = trim($date);
    if ($date === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) return $date;
    $d = DateTime::createFromFormat('m/d/Y', $date);
    if ($d) return $d->format('Y-m-d');
    return null;
}

function attendanceHasColumn(PDO $db, string $column): bool {
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME   = 'attendance'
                          AND COLUMN_NAME  = ?");
    $q->execute([$column]);
    return ((int)$q->fetchColumn()) > 0;
}

function ensureAttendanceColumn(PDO $db, string $column, string $definition): void {
    if (!attendanceHasColumn($db, $column)) {
        $db->exec("ALTER TABLE attendance ADD COLUMN {$column} {$definition}");
    }
}

// ── Helper: get all current column names for attendance table ────────────────
function attendanceColumns(PDO $db): array {
    $q = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'attendance'");
    return $q->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// ── Auto-create / migrate table ──────────────────────────────────────────────
try {
    // Create with full schema if it does not exist yet
    $db->exec("CREATE TABLE IF NOT EXISTS attendance (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        course_id     INT          NULL,
        batch_id      INT          NULL,
        student_id    INT          NOT NULL,
        branch_id     INT          NOT NULL DEFAULT 0,
        attend_date   DATE         NOT NULL,
        status        ENUM('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
        notes         VARCHAR(255) NULL,
        recorded_by   INT          NULL,
        created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_course_student_date (course_id, student_id, attend_date),
        KEY idx_att_branch_date (branch_id, attend_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Rename legacy 'date' → 'attend_date'
    if (attendanceHasColumn($db, 'date') && !attendanceHasColumn($db, 'attend_date')) {
        $db->exec("ALTER TABLE attendance CHANGE COLUMN `date` `attend_date` DATE NOT NULL");
    }

    // Add every column the table might be missing (legacy partial schemas)
    ensureAttendanceColumn($db, 'course_id',   'INT NULL          AFTER id');
    ensureAttendanceColumn($db, 'batch_id',    'INT NULL          AFTER course_id');
    ensureAttendanceColumn($db, 'student_id',  'INT NOT NULL      AFTER batch_id');
    ensureAttendanceColumn($db, 'branch_id',   'INT NOT NULL DEFAULT 0 AFTER student_id');
    ensureAttendanceColumn($db, 'attend_date', 'DATE NOT NULL     AFTER branch_id');
    ensureAttendanceColumn($db, 'status',      "ENUM('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present' AFTER attend_date");
    ensureAttendanceColumn($db, 'notes',       'VARCHAR(255) NULL AFTER status');
    ensureAttendanceColumn($db, 'recorded_by', 'INT NULL          AFTER notes');
    ensureAttendanceColumn($db, 'marked_by',   'INT NULL          AFTER recorded_by');
    ensureAttendanceColumn($db, 'created_at',  'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER marked_by');

    // If marked_by or recorded_by exist as NOT NULL with no default, make them
    // nullable so rows can be inserted without supplying a value explicitly.
    foreach (['marked_by', 'recorded_by'] as $mbCol) {
        try {
            $mbMeta = $db->prepare("SELECT IS_NULLABLE, COLUMN_DEFAULT
                                     FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE()
                                       AND TABLE_NAME   = 'attendance'
                                       AND COLUMN_NAME  = ?");
            $mbMeta->execute([$mbCol]);
            $mbRow = $mbMeta->fetch(PDO::FETCH_ASSOC);
            if ($mbRow && $mbRow['IS_NULLABLE'] === 'NO' && $mbRow['COLUMN_DEFAULT'] === null) {
                $db->exec("ALTER TABLE attendance MODIFY COLUMN {$mbCol} INT NULL DEFAULT NULL");
            }
        } catch (Throwable $e) { /* non-fatal */ }
    }

    // Add unique index if missing
    $idxCheck = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS
                             WHERE TABLE_SCHEMA = DATABASE()
                               AND TABLE_NAME   = 'attendance'
                               AND INDEX_NAME   = 'uq_course_student_date'")->fetchColumn();
    if ((int)$idxCheck === 0) {
        try {
            $db->exec("ALTER TABLE attendance
                       ADD UNIQUE KEY uq_course_student_date (course_id, student_id, attend_date)");
        } catch (Throwable $e) { /* ignore if data conflicts prevent it */ }
    }

    // Backfill course_id from legacy batch_id data
    try {
        $db->exec("UPDATE attendance a
                   JOIN batches b ON b.id = a.batch_id
                   SET a.course_id = b.course_id
                   WHERE a.course_id IS NULL AND a.batch_id IS NOT NULL");
    } catch (Throwable $e) { /* batches table may not exist in all installs */ }

} catch (Throwable $e) {
    // Do not block attendance operations due to migration issues.
}

// ── GET STUDENTS FOR A COURSE ────────────────────────────────────────────────
// FIX 3: Date filter moved from WHERE → LEFT JOIN ON clause so we always get
//         all enrolled students, with or without an existing attendance record.
// FIX 4: Past-date guard corrected: allow today ($date <= $today, not $date < $today).
if ($action === 'course_students' || $action === 'batch_students') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $dateRaw  = (string)($_GET['date'] ?? date('Y-m-d'));
    $date     = normalizeDateInput($dateRaw) ?: date('Y-m-d');
    $today    = date('Y-m-d');

    if (!$courseId) {
        echo json_encode(['data' => []]);
        exit;
    }

    // FIX 4: was $date < $today (blocked today); correct is $date > $today
    if ($date > $today) {
        echo json_encode(['data' => [], 'message' => 'Future dates are not allowed for attendance.']);
        exit;
    }

    if (!$isSA) {
        $scope = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ? LIMIT 1");
        $scope->execute([$courseId, $branchId]);
        if (!$scope->fetchColumn()) {
            echo json_encode(['data' => []]);
            exit;
        }
    }

    $hasCourseIdCol = attendanceHasColumn($db, 'course_id');

    if ($hasCourseIdCol) {
        // FIX 3: attend_date filter is in ON, not WHERE
        $stmt = $db->prepare(
            "SELECT s.id          AS student_id,
                    u.name        AS student_name,
                    s.student_id  AS student_code,
                    COALESCE(a.status, '') AS att_status,
                    COALESCE(a.notes,  '') AS notes
             FROM enrollments e
             JOIN students s ON e.student_id = s.id
             JOIN users u    ON s.user_id    = u.id
             LEFT JOIN attendance a
                    ON  a.student_id  = s.id
                    AND a.course_id   = ?
                    AND a.attend_date = ?
             WHERE e.course_id = ?
               AND e.status    = 'Active'
             ORDER BY u.name"
        );
        $stmt->execute([$courseId, $date, $courseId]);
    } else {
        // Legacy path (no course_id column)
        $stmt = $db->prepare(
            "SELECT s.id          AS student_id,
                    u.name        AS student_name,
                    s.student_id  AS student_code,
                    COALESCE(a.status, '') AS att_status,
                    COALESCE(a.notes,  '') AS notes
             FROM enrollments e
             JOIN students s ON e.student_id = s.id
             JOIN users u    ON s.user_id    = u.id
             LEFT JOIN attendance a
                    ON  a.student_id  = s.id
                    AND a.batch_id    = e.batch_id
                    AND a.attend_date = ?
             WHERE e.course_id = ?
               AND e.status    = 'Active'
             ORDER BY u.name"
        );
        $stmt->execute([$date, $courseId]);
    }

    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET COURSES FOR BRANCH ───────────────────────────────────────────────────
if ($action === 'courses' || $action === 'batches') {
    $bid  = $isSA ? (int)($_GET['branch_id'] ?? 0) : $branchId;
    $sql  = "SELECT c.id, c.name AS course_name FROM courses c WHERE 1=1";
    $args = [];
    if ($bid) {
        $sql   .= " AND c.branch_id = ?";
        $args[] = $bid;
    } elseif (!$isSA) {
        echo json_encode(['data' => []]);
        exit;
    }
    $sql .= " ORDER BY c.name";
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET COURSES WITH ACTIVE ENROLLMENTS ──────────────────────────────────────
if ($action === 'courses_with_students') {
    $bid = $isSA ? (int)($_GET['branch_id'] ?? 0) : $branchId;

    if (!$isSA && !$bid) {
        echo json_encode(['data' => []]);
        exit;
    }

    $sql = "SELECT c.id, c.name AS course_name, COUNT(e.id) AS active_enrollments
            FROM courses c
            JOIN enrollments e ON e.course_id = c.id AND e.status = 'Active'
            JOIN students s    ON s.id = e.student_id
            WHERE (? = 0 OR c.branch_id = ?)
              AND (? = 0 OR s.branch_id = ?)
            GROUP BY c.id, c.name
            ORDER BY c.name";
    $stmt = $db->prepare($sql);
    $stmt->execute([$bid, $bid, $bid, $bid]);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── LIST ATTENDANCE ──────────────────────────────────────────────────────────
if ($action === 'list') {
    $courseId      = (int)($_GET['course_id'] ?? 0);
    $dateFrom      = normalizeDateInput($_GET['date_from'] ?? '') ?: date('Y-m-01');
    $dateTo        = normalizeDateInput($_GET['date_to']   ?? '') ?: date('Y-m-d');
    $hasBranchCol  = attendanceHasColumn($db, 'branch_id');
    $hasCourseCol  = attendanceHasColumn($db, 'course_id');

    $sql = "SELECT a.attend_date, u.name AS student_name, s.student_id AS student_code,
                   a.status, a.notes" .
           ($hasCourseCol ? ", c.name AS course_name" : ", '' AS course_name") . "
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN users u    ON s.user_id    = u.id" .
           ($hasCourseCol ? " LEFT JOIN courses c ON c.id = a.course_id" : "");

    $where  = ['a.attend_date BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if (!$isSA && $hasBranchCol) {
        $where[]  = 'a.branch_id = ?';
        $params[] = $branchId;
    }
    if ($courseId && $hasCourseCol) {
        $where[]  = 'a.course_id = ?';
        $params[] = $courseId;
    }

    $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.attend_date DESC, u.name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── SAVE (bulk upsert — dynamic column list) ─────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = (int)($_POST['course_id']  ?? 0);
    $dateRaw  = trim($_POST['attend_date'] ?? '');
    $date     = normalizeDateInput($dateRaw) ?: '';
    $today    = date('Y-m-d');
    $records  = $_POST['records']          ?? [];
    $userId   = (int)($_SESSION['user_id'] ?? 0);
    $bId      = $isSA ? (int)($_POST['branch_id'] ?? $branchId) : $branchId;

    if (!$courseId || !$date || !is_array($records)) {
        echo json_encode(['status' => 'error', 'message' => 'Course, date, and records are required']);
        exit;
    }

    if ($date > $today) {
        echo json_encode(['status' => 'error', 'message' => 'Future dates are not allowed for attendance.']);
        exit;
    }

    if (!$isSA) {
        $scope = $db->prepare("SELECT id FROM courses WHERE id = ? AND branch_id = ? LIMIT 1");
        $scope->execute([$courseId, $branchId]);
        if (!$scope->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Course is outside your branch scope']);
            exit;
        }
    }

    // Discover which optional columns actually exist so the INSERT never
    // references a column that is absent in a legacy/partial schema.
    $existingCols  = attendanceColumns($db);
    $hasCourseId   = in_array('course_id',   $existingCols);
    $hasBatchId    = in_array('batch_id',    $existingCols);
    $hasBranchId   = in_array('branch_id',   $existingCols);
    $hasNotes      = in_array('notes',       $existingCols);
    $hasRecordedBy = in_array('recorded_by', $existingCols);
    $hasMarkedBy   = in_array('marked_by',   $existingCols);

    // Build column list + placeholders dynamically
    // student_id and attend_date + status are always required
    $cols   = ['student_id', 'attend_date', 'status'];
    $pholds = ['?',           '?',           '?'];
    $update = ['status = VALUES(status)'];

    if ($hasCourseId)   { $cols[] = 'course_id';   $pholds[] = '?'; }
    if ($hasBatchId)    { $cols[] = 'batch_id';    $pholds[] = '?'; }
    if ($hasBranchId)   { $cols[] = 'branch_id';   $pholds[] = '?'; }
    if ($hasNotes)      { $cols[] = 'notes';        $pholds[] = '?'; $update[] = 'notes = VALUES(notes)'; }
    if ($hasRecordedBy) { $cols[] = 'recorded_by';  $pholds[] = '?'; $update[] = 'recorded_by = VALUES(recorded_by)'; }
    if ($hasMarkedBy)   { $cols[] = 'marked_by';    $pholds[] = '?'; $update[] = 'marked_by = VALUES(marked_by)'; }

    $sql  = "INSERT INTO attendance (" . implode(', ', $cols) . ")"
          . " VALUES (" . implode(', ', $pholds) . ")"
          . " ON DUPLICATE KEY UPDATE " . implode(', ', $update);
    $stmt = $db->prepare($sql);

    $saved = 0;
    foreach ($records as $rec) {
        $sid    = (int)($rec['student_id'] ?? 0);
        $status = in_array($rec['status'] ?? '', ['Present','Absent','Late','Excused'])
                  ? $rec['status'] : 'Present';
        $notes  = trim($rec['notes'] ?? '');
        if (!$sid) continue;

        // Resolve legacy batch_id if needed
        $legacyBatchId = null;
        if ($hasBatchId) {
            try {
                $bs = $db->prepare("SELECT batch_id FROM enrollments
                                     WHERE student_id = ? AND course_id = ? AND status = 'Active'
                                     ORDER BY id DESC LIMIT 1");
                $bs->execute([$sid, $courseId]);
                $legacyBatchId = $bs->fetchColumn() ?: null;
            } catch (Throwable $e) { $legacyBatchId = null; }
        }

        // Build bound values in the same order as $cols
        $params = [$sid, $date, $status];
        if ($hasCourseId)   $params[] = $courseId;
        if ($hasBatchId)    $params[] = $legacyBatchId;
        if ($hasBranchId)   $params[] = $bId;
        if ($hasNotes)      $params[] = $notes;
        if ($hasRecordedBy) $params[] = $userId ?: null;
        if ($hasMarkedBy)   $params[] = $userId ?: null;

        $stmt->execute($params);
        $saved++;
    }

    echo json_encode(['status' => 'success', 'message' => "Attendance saved for {$saved} student(s)"]);
    exit;
}

// ── SUMMARY ──────────────────────────────────────────────────────────────────
if ($action === 'summary') {
    $date         = normalizeDateInput($_GET['date'] ?? '') ?: date('Y-m-d');
    $bid          = $isSA ? 0 : $branchId;
    $hasBranchCol = attendanceHasColumn($db, 'branch_id');

    $sql    = "SELECT
                   SUM(status = 'Present') AS present,
                   SUM(status = 'Absent')  AS absent,
                   SUM(status = 'Late')    AS late,
                   SUM(status = 'Excused') AS excused,
                   COUNT(*)                AS total
               FROM attendance
               WHERE attend_date = ?";
    $params = [$date];

    if ($bid && $hasBranchCol) {
        $sql     .= " AND branch_id = ?";
        $params[] = $bid;
    }

    $row = $db->prepare($sql);
    $row->execute($params);
    echo json_encode(
        $row->fetch(PDO::FETCH_ASSOC)
        ?: ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0]
    );
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);