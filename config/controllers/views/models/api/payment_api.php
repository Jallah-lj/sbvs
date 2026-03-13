<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once '../../../../database.php';
require_once '../../../../EmailService.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isAdmin       = ($role === 'Admin');
$userId        = (int)($_SESSION['user_id'] ?? 0);

// Only Super Admin, Branch Admin, and Admin may access payments
if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ────────────────────────────────────────────────────────────────────────────
// SCHEMA AUTO-MIGRATION
// ────────────────────────────────────────────────────────────────────────────

// Ensure locked_fee column exists for fee locking behavior
function ensureLockedFeeColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $chk = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'enrollments'
           AND COLUMN_NAME = 'locked_fee'"
    );
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE enrollments ADD COLUMN locked_fee DECIMAL(10,2) NULL AFTER batch_id");
    }
}

// Ensure lock tracking columns exist
function ensurePaymentLockColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $cols = ['locked_by' => 'locked_by INT NULL', 
             'locked_by_role' => 'locked_by_role VARCHAR(50) NULL',
             'last_edited_at' => 'last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'];
    foreach ($cols as $colName => $colDef) {
        $chk = $db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'payments'
               AND COLUMN_NAME = '{$colName}'"
        );
        if ((int)$chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE payments ADD COLUMN {$colDef}");
        }
    }
}

ensureLockedFeeColumn($db);
ensurePaymentLockColumns($db);

// Ensure registration_fee + tuition_fee split columns exist on courses
function ensureFeeSplitColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // 1. Add registration_fee if missing
    $chk = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'registration_fee'");
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN registration_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER description");
    }

    // 2. Add tuition_fee if missing
    $chk = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'tuition_fee'");
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN tuition_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER registration_fee");
        // Migrate existing data: all existing fees become tuition_fee
        $db->exec("UPDATE courses SET tuition_fee = fees WHERE tuition_fee = 0 AND fees > 0");
    }

    // 3. Convert fees to a STORED generated column (sum of both) if it's still a regular column
    $chk = $db->query("SELECT EXTRA FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'fees'");
    $extra = $chk->fetchColumn();
    if ($extra !== false && stripos($extra, 'GENERATED') === false) {
        // Drop the old plain fees column and replace with a generated one
        try {
            $db->exec("ALTER TABLE courses DROP COLUMN fees");
            $db->exec("ALTER TABLE courses ADD COLUMN fees DECIMAL(10,2) GENERATED ALWAYS AS (registration_fee + tuition_fee) STORED");
        } catch (\PDOException $e) {
            // If conversion fails (e.g. old MySQL), leave fees as-is — queries still work
        }
    }
}
ensureFeeSplitColumns($db);


// ────────────────────────────────────────────────────────────────────────────
// LOCK CHECKING HELPERS
// ────────────────────────────────────────────────────────────────────────────

function isPaymentLockedBySuperAdmin(PDO $db, $paymentId) {
    $stmt = $db->prepare("SELECT locked_by_role FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && $row['locked_by_role'] === 'Super Admin';
}

function getPaymentLockStatus(PDO $db, $paymentId) {
    $stmt = $db->prepare(
        "SELECT p.locked_by_role, u.name as locked_by_name 
         FROM payments p
         LEFT JOIN users u ON p.locked_by = u.id
         WHERE p.id = ?"
    );
    $stmt->execute([$paymentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['locked_by_role'] === 'Super Admin') {
        return [
            'is_locked' => true,
            'locked_by' => $row['locked_by_name'] ?? 'Super Admin',
            'message' => 'This payment was modified by Super Admin and is locked from direct edits.'
        ];
    }
    return ['is_locked' => false];
}

// ── CSV export bypasses JSON header ──────────────────────────────────────────
if ($action !== 'export') {
    header('Content-Type: application/json');
}

// ── Unique receipt number generator ──────────────────────────────────────────
function generateReceiptNo(PDO $db): string
{
    $prefix = 'RCP-' . date('Ymd') . '-';
    $stmt   = $db->prepare("SELECT COUNT(*) FROM payments WHERE receipt_no LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Build WHERE clause helper ─────────────────────────────────────────────────
function buildPaymentWhere(bool $isSuperAdmin, int $sessionBranch, array $get): array
{
    $where  = ['1=1'];
    $params = [];

    $bf = $isSuperAdmin ? (int)($get['branch_id'] ?? 0) : $sessionBranch;
    if ($bf) { $where[] = 'p.branch_id = ?'; $params[] = $bf; }

    if (!empty($get['date_from'])) { $where[] = 'DATE(p.payment_date) >= ?'; $params[] = $get['date_from']; }
    if (!empty($get['date_to']))   { $where[] = 'DATE(p.payment_date) <= ?'; $params[] = $get['date_to'];   }
    if (!empty($get['method']))    { $where[] = 'p.payment_method = ?';       $params[] = $get['method'];    }
    if (!empty($get['status']))    { $where[] = 'p.status = ?';               $params[] = $get['status'];    }
    if (!empty($get['type']))      { $where[] = 'p.payment_type = ?';         $params[] = $get['type'];      }
    if (!empty($get['search'])) {
        $sq = '%' . $get['search'] . '%';
        $where[] = '(u.name LIKE ? OR s.student_id LIKE ? OR p.receipt_no LIKE ? OR c.name LIKE ?)';
        array_push($params, $sq, $sq, $sq, $sq);
    }

    // Direct per-student filter (used by student_profile.php)
    if (!empty($get['student_id_filter'])) {
        $where[] = 'p.student_id = ?';
        $params[] = (int)$get['student_id_filter'];
    }

    return [implode(' AND ', $where), $params];
}

switch ($action) {

    // ── Search students (live autocomplete) ──────────────────────────────────
    case 'search_students':
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        if ($isSuperAdmin) {
            $stmt = $db->prepare(
                "SELECT s.id, s.student_id AS code, u.name, b.name AS branch
                 FROM students s
                 JOIN users u    ON s.user_id    = u.id
                 JOIN branches b ON s.branch_id  = b.id
                 WHERE (u.name LIKE ? OR s.student_id LIKE ?)
                 ORDER BY u.name LIMIT 15");
            $stmt->execute([$q, $q]);
        } else {
            $stmt = $db->prepare(
                "SELECT s.id, s.student_id AS code, u.name, b.name AS branch
                 FROM students s
                 JOIN users u    ON s.user_id   = u.id
                 JOIN branches b ON s.branch_id = b.id
                 WHERE s.branch_id = ? AND (u.name LIKE ? OR s.student_id LIKE ?)
                 ORDER BY u.name LIMIT 15");
            $stmt->execute([$sessionBranch, $q, $q]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ── Get enrollments for a student (with balance per enrollment) ───────────
    case 'get_enrollments':
        $sid = (int)($_GET['student_id'] ?? 0);
        if (!$isSuperAdmin) {
            $chk = $db->prepare("SELECT id FROM students WHERE id=? AND branch_id=?");
            $chk->execute([$sid, $sessionBranch]);
            if (!$chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Access denied']); break; }
        }
        $stmt = $db->prepare(
            "SELECT e.id AS enrollment_id,
                    c.name AS course_name,
                    COALESCE(e.locked_fee, c.fees) AS fees,
                    e.status AS enrollment_status,
                    e.enrollment_date,
                    COALESCE((
                        SELECT SUM(p.amount) FROM payments p
                        WHERE p.enrollment_id = e.id AND p.status = 'Active'
                    ), 0) AS total_paid
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             WHERE e.student_id = ?
             ORDER BY e.enrollment_date DESC");
        $stmt->execute([$sid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['balance'] = max(0, round($r['fees'] - $r['total_paid'], 2));
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ── Stats (KPI cards) ─────────────────────────────────────────────────────
    case 'stats':
        $bf     = $isSuperAdmin ? (int)($_GET['branch_id'] ?? 0) : $sessionBranch;
        $bWhere = $bf ? "AND p.branch_id = {$bf}" : "";
        $bWhereE = $bf ? "AND s.branch_id = {$bf}" : "";

        $kpi = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN p.status='Active' THEN p.amount END), 0)   AS total_rev,
                COALESCE(SUM(CASE WHEN p.status='Active'
                    AND MONTH(p.payment_date)=MONTH(CURDATE())
                    AND YEAR(p.payment_date)=YEAR(CURDATE()) THEN p.amount END), 0) AS monthly_rev,
                COUNT(CASE WHEN p.status='Void'   THEN 1 END)  AS void_count,
                COUNT(CASE WHEN p.status='Active' THEN 1 END)  AS active_count
             FROM payments p WHERE 1=1 {$bWhere}"
        )->fetch(PDO::FETCH_ASSOC);

        $totalFees = (float)$db->query(
            "SELECT COALESCE(SUM(COALESCE(e.locked_fee, c.fees)), 0)
             FROM enrollments e
             JOIN courses c  ON e.course_id  = c.id
             JOIN students s ON e.student_id = s.id
             WHERE e.status = 'Active' {$bWhereE}"
        )->fetchColumn();

        $totalPaid = (float)$db->query(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM payments p WHERE p.status = 'Active' {$bWhere}"
        )->fetchColumn();

        $kpi['outstanding'] = max(0, round($totalFees - $totalPaid, 2));
        echo json_encode(['success' => true, 'data' => $kpi]);
        break;

    // ── List payments (DataTable source) ─────────────────────────────────────
    case 'list':
        [$wStr, $params] = buildPaymentWhere($isSuperAdmin, $sessionBranch, $_GET);

        $stmt = $db->prepare(
            "SELECT p.id, p.receipt_no, p.payment_date,
                    u.name AS student_name, s.student_id AS student_code,
                    c.name AS course_name, COALESCE(e.locked_fee, c.fees, 0) AS course_fee,
                    b.name AS branch_name, p.branch_id,
                    p.amount, p.payment_type, p.payment_method,
                    p.transaction_id, p.notes, p.enrollment_id,
                    p.status, p.void_reason, p.voided_at,
                    vu.name AS voided_by_name
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN users u    ON s.user_id    = u.id
             LEFT JOIN enrollments e ON p.enrollment_id = e.id
             LEFT JOIN courses c    ON e.course_id      = c.id
             LEFT JOIN branches b   ON p.branch_id      = b.id
             LEFT JOIN users vu     ON p.voided_by      = vu.id
             WHERE {$wStr}
             ORDER BY p.payment_date DESC, p.id DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach current balance per enrollment
        foreach ($rows as &$r) {
            if ($r['enrollment_id'] && $r['course_fee'] > 0) {
                $bs = $db->prepare(
                    "SELECT COALESCE(SUM(amount),0) FROM payments
                     WHERE enrollment_id=? AND status='Active'");
                $bs->execute([$r['enrollment_id']]);
                $r['total_paid'] = (float)$bs->fetchColumn();
                $r['balance']    = max(0, round($r['course_fee'] - $r['total_paid'], 2));
            } else {
                $r['total_paid'] = (float)$r['amount'];
                $r['balance']    = null;
            }
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ── Record a new payment ──────────────────────────────────────────────────
    case 'record':
        $sid      = (int)($_POST['student_id']    ?? 0);
        $eid      = (int)($_POST['enrollment_id'] ?? 0);
        $amount   = (float)($_POST['amount']      ?? 0);
        $method   = trim($_POST['payment_method'] ?? '');
        $transId  = trim($_POST['transaction_id'] ?? '');
        $notes    = trim($_POST['notes']          ?? '');
        $payDate  = trim($_POST['payment_date']   ?? date('Y-m-d'));

        if (!$sid || !$eid || $amount <= 0 || !$method) {
            echo json_encode(['success' => false, 'message' => 'Student, enrollment, amount, and method are required.']);
            break;
        }

        // Branch ownership check
        $sc = $db->prepare("SELECT branch_id FROM students WHERE id=?");
        $sc->execute([$sid]);
        $sRow = $sc->fetch(PDO::FETCH_ASSOC);
        if (!$sRow) { echo json_encode(['success' => false, 'message' => 'Student not found']); break; }
        if (!$isSuperAdmin && (int)$sRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied — student belongs to another branch']); break;
        }

        // Check outstanding balance
        $bq = $db->prepare(
            "SELECT COALESCE(e.locked_fee, c.fees) AS fees,
                    COALESCE((SELECT SUM(p.amount) FROM payments p
                     WHERE p.enrollment_id=e.id AND p.status='Active'), 0) AS paid
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             WHERE e.id=? AND e.student_id=?");
        $bq->execute([$eid, $sid]);
        $bRow = $bq->fetch(PDO::FETCH_ASSOC);
        if (!$bRow) { echo json_encode(['success' => false, 'message' => 'Enrollment not found for this student']); break; }

        $outstanding = round($bRow['fees'] - $bRow['paid'], 2);
        if ($outstanding <= 0) {
            echo json_encode(['success' => false, 'message' => 'This enrollment is already fully paid.']); break;
        }
        if ($amount > $outstanding + 0.01) {
            echo json_encode(['success' => false, 'message' => "Amount (\${$amount}) exceeds outstanding balance (\${$outstanding})"]); break;
        }

        $payType    = ($amount >= $outstanding - 0.01) ? 'Full' : 'Partial';
        $receiptNo  = generateReceiptNo($db);
        $branchId   = (int)$sRow['branch_id'];
        $transIdVal = $transId !== '' ? $transId : null;

        $ins = $db->prepare(
            "INSERT INTO payments
                (branch_id, student_id, enrollment_id, amount, payment_method,
                 payment_type, transaction_id, receipt_no, notes, status, payment_date, locked_by, locked_by_role)
             VALUES (?,?,?,?,?,?,?,?,?,'Active',?,NULL,NULL)");
        $ins->execute([$branchId, $sid, $eid, $amount, $method,
                       $payType, $transIdVal, $receiptNo, $notes ?: null, $payDate]);

        $paymentId = (int)$db->lastInsertId();

        // If Super Admin, mark as locked immediately
        if ($isSuperAdmin) {
            $db->prepare("UPDATE payments SET locked_by = ?, locked_by_role = 'Super Admin' WHERE id = ?")
                ->execute([$userId, $paymentId]);
        }

        // Fetch details for email receipt
        $eStmt = $db->prepare(
            "SELECT u.name, u.email, c.name AS course_name 
             FROM students s 
             JOIN users u ON s.user_id = u.id 
             JOIN enrollments e ON e.student_id = s.id 
             JOIN courses c ON e.course_id = c.id 
             WHERE s.id = ? AND e.id = ?"
        );
        $eStmt->execute([$sid, $eid]);
        $eRow = $eStmt->fetch(PDO::FETCH_ASSOC);

        if ($eRow && !empty($eRow['email'])) {
            try {
                $emailService = new EmailService();
                $newBalance = max(0, $outstanding - $amount);
                $emailService->sendPaymentReceipt($eRow['email'], $eRow['name'], $receiptNo, $amount, $method, $newBalance, $eRow['course_name']);
            } catch (Exception $ex) {
                // Ignore email failure
            }
        }

        echo json_encode([
            'success'    => true,
            'message'    => 'Payment recorded successfully!',
            'receipt_no' => $receiptNo,
            'payment_id' => (int)$db->lastInsertId(),
        ]);
        break;

    // ── Void a payment (no deletes — audit trail) ─────────────────────────────
    case 'void':
        $pid    = (int)($_POST['payment_id']  ?? 0);
        $reason = trim($_POST['void_reason']  ?? '');

        if (!$pid || !$reason) {
            echo json_encode(['success' => false, 'message' => 'Payment ID and void reason are required.']); break;
        }

        $fq = $db->prepare(
            "SELECT p.*, s.branch_id AS st_branch
             FROM payments p JOIN students s ON p.student_id = s.id
             WHERE p.id=?");
        $fq->execute([$pid]);
        $pay = $fq->fetch(PDO::FETCH_ASSOC);

        if (!$pay) { echo json_encode(['success' => false, 'message' => 'Payment not found']); break; }
        if (!$isSuperAdmin && (int)$pay['st_branch'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }
        if ($pay['status'] === 'Void') {
            echo json_encode(['success' => false, 'message' => 'This payment is already voided.']); break;
        }

        // ──── CHECK LOCK STATUS ────
        if (!$isSuperAdmin && isPaymentLockedBySuperAdmin($db, $pid)) {
            $lockStatus = getPaymentLockStatus($db, $pid);
            echo json_encode([
                'success' => false,
                'message' => 'This payment is locked by Super Admin. You can request to void it.',
                'locked' => true,
                'lock_info' => $lockStatus,
                'action_available' => 'request_change'
            ]);
            break;
        }

        $db->prepare(
            "UPDATE payments SET status='Void', void_reason=?, voided_by=?, voided_at=NOW() WHERE id=?")
           ->execute([$reason, $userId, $pid]);

        // If Super Admin, mark as locked
        if ($isSuperAdmin) {
            $db->prepare("UPDATE payments SET locked_by = ?, locked_by_role = 'Super Admin' WHERE id = ?")
                ->execute([$userId, $pid]);
        }

        echo json_encode(['success' => true, 'message' => 'Payment voided. The record is retained for audit purposes.']);
        break;

    // ── Get receipt data ──────────────────────────────────────────────────────
    case 'get_receipt':
        $pid = (int)($_GET['payment_id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT p.*,
                    u.name  AS student_name,  s.student_id AS student_code,
                    c.name  AS course_name,
                    COALESCE(e.locked_fee, c.fees) AS fees,
                    COALESCE(c.registration_fee, 0) AS registration_fee,
                    COALESCE(c.tuition_fee, 0)      AS tuition_fee,
                    b.name  AS branch_name,   b.address AS branch_address,
                    b.phone AS branch_phone,  b.email   AS branch_email,
                    COALESCE((
                        SELECT SUM(p2.amount) FROM payments p2
                        WHERE p2.enrollment_id = p.enrollment_id AND p2.status='Active'
                    ), 0) AS total_paid_on_enrollment
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN users u    ON s.user_id    = u.id
             LEFT JOIN enrollments e ON p.enrollment_id = e.id
             LEFT JOIN courses c    ON e.course_id      = c.id
             LEFT JOIN branches b   ON p.branch_id      = b.id
             WHERE p.id = ?");
        $stmt->execute([$pid]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r) { echo json_encode(['success' => false, 'message' => 'Receipt not found']); break; }
        if (!$isSuperAdmin && (int)$r['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); break;
        }

        // Previously paid = total_paid_on_enrollment – this payment's amount
        $r['prev_paid'] = max(0, round($r['total_paid_on_enrollment'] - $r['amount'], 2));
        $r['balance']   = max(0, round(($r['fees'] ?? 0) - $r['total_paid_on_enrollment'], 2));
        echo json_encode(['success' => true, 'data' => $r]);
        break;

    // ── Export CSV ────────────────────────────────────────────────────────────
    case 'export':
        [$wStr, $params] = buildPaymentWhere($isSuperAdmin, $sessionBranch, $_GET);

        $stmt = $db->prepare(
            "SELECT p.receipt_no, DATE(p.payment_date) AS date,
                    u.name AS student_name, s.student_id AS student_code,
                    c.name AS course_name, b.name AS branch_name,
                    p.amount, p.payment_type, p.payment_method,
                    COALESCE(p.transaction_id,'') AS transaction_id,
                    p.status,
                    COALESCE(p.void_reason,'') AS void_reason,
                    COALESCE(p.notes,'') AS notes
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN users u    ON s.user_id    = u.id
             LEFT JOIN enrollments e ON p.enrollment_id = e.id
             LEFT JOIN courses c    ON e.course_id      = c.id
             LEFT JOIN branches b   ON p.branch_id      = b.id
             WHERE {$wStr}
             ORDER BY p.payment_date DESC, p.id DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="payments_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
        fputcsv($out, ['Receipt No', 'Date', 'Student Name', 'Student ID', 'Course',
                        'Branch', 'Amount ($)', 'Type', 'Method', 'Transaction ID',
                        'Status', 'Void Reason', 'Notes']);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;

    // ── Check lock status (for UI) ────────────────────────────────────────────
    case 'check_lock':
        $pid = (int)($_GET['payment_id'] ?? 0);
        if (!$pid) {
            echo json_encode(['success' => false, 'message' => 'payment_id required']);
            break;
        }

        $lockStatus = getPaymentLockStatus($db, $pid);
        echo json_encode([
            'success' => true,
            'is_super_admin' => $isSuperAdmin,
            'lock_status' => $lockStatus
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
