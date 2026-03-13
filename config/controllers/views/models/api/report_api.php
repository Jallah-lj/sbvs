<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../../../../database.php';

header('Content-Type: application/json');

$db            = (new Database())->getConnection();
$action        = $_GET['action'] ?? '';
$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');

// Helper: build WHERE clauses from filters
function buildFilters($db, $params, $forceBranch = null) {
    $where   = [];
    $binds   = [];

    if (!empty($params['start_date'])) {
        $where[]              = 'DATE(p.payment_date) >= :start_date';
        $binds['start_date']  = $params['start_date'];
    }
    if (!empty($params['end_date'])) {
        $where[]             = 'DATE(p.payment_date) <= :end_date';
        $binds['end_date']   = $params['end_date'];
    }
    // forceBranch overrides any user-provided branch_id for non-Super Admin
    $effectiveBranch = $forceBranch ?: (!empty($params['branch_id']) ? intval($params['branch_id']) : null);
    if ($effectiveBranch) {
        $where[]             = 'p.branch_id = :branch_id';
        $binds['branch_id']  = $effectiveBranch;
    }

    return [
        'clause' => $where ? ('WHERE ' . implode(' AND ', $where)) : '',
        'binds'  => $binds,
    ];
}

// ── SUMMARY (widgets + table rows) ──────────────────────────────────────────
if ($action === 'summary') {
    $p = array_merge($_GET, $_POST);
    $forceBranch = $isSuperAdmin ? null : $sessionBranch;
    $f = buildFilters($db, $p, $forceBranch);

    // Revenue & outstanding balance from payments
    $sql = "SELECT
                COALESCE(SUM(p.amount), 0)  AS revenue
            FROM payments p
            {$f['clause']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($f['binds']);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);

    // Enrollments in the same date range (use enrollment_date)
    $enrollWhere  = [];
    $enrollBinds  = [];
    if (!empty($p['start_date'])) { $enrollWhere[] = 'e.enrollment_date >= :start_date'; $enrollBinds['start_date'] = $p['start_date']; }
    if (!empty($p['end_date']))   { $enrollWhere[] = 'e.enrollment_date <= :end_date';   $enrollBinds['end_date']   = $p['end_date'];   }
    $enrollBranch = $isSuperAdmin ? (!empty($p['branch_id']) ? intval($p['branch_id']) : null) : $sessionBranch;
    if ($enrollBranch) {
        $enrollWhere[] = 's.branch_id = :branch_id';
        $enrollBinds['branch_id'] = $enrollBranch;
    }
    $eClause = $enrollWhere ? ('WHERE ' . implode(' AND ', $enrollWhere)) : '';
    $eSql = "SELECT COUNT(*) AS cnt
             FROM enrollments e
             JOIN students s ON e.student_id = s.id
             {$eClause}";
    $eStmt = $db->prepare($eSql);
    $eStmt->execute($enrollBinds);
    $enr = $eStmt->fetch(PDO::FETCH_ASSOC);

    // Outstanding = sum of course fees for enrollments in range minus payments
    $pendingSql = "SELECT
                       COALESCE(SUM(c.fees), 0) AS total_fees
                   FROM enrollments e
                   JOIN courses c    ON e.course_id  = c.id
                   JOIN students s   ON e.student_id = s.id
                   {$eClause}";
    $pStmt = $db->prepare($pendingSql);
    $pStmt->execute($enrollBinds);
    $fees = $pStmt->fetch(PDO::FETCH_ASSOC);
    $pending = max(0, floatval($fees['total_fees']) - floatval($rev['revenue']));

    // Transaction rows
    $rowSql = "SELECT
                   DATE_FORMAT(p.payment_date, '%Y-%m-%d')   AS date,
                   u.name                                     AS student_name,
                   COALESCE(p.transaction_id, '—')           AS tx_id,
                   p.payment_method                          AS method,
                   p.amount,
                   b.name                                     AS branch_name
               FROM payments p
               JOIN students s ON p.student_id  = s.id
               JOIN users    u ON s.user_id      = u.id
               JOIN branches b ON p.branch_id    = b.id
               {$f['clause']}
               ORDER BY p.payment_date DESC
               LIMIT 500";
    $rStmt = $db->prepare($rowSql);
    $rStmt->execute($f['binds']);
    $rows = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'  => 'success',
        'summary' => [
            'revenue'     => number_format(floatval($rev['revenue']), 2, '.', ''),
            'enrollments' => intval($enr['cnt']),
            'pending'     => number_format($pending, 2, '.', ''),
        ],
        'data' => $rows,
    ]);
    exit;
}

// ── ENROLLMENTS TAB ──────────────────────────────────────────────────────────
if ($action === 'enrollments') {
    $p = array_merge($_GET, $_POST);
    $where  = [];
    $binds  = [];
    if (!empty($p['start_date'])) { $where[] = 'e.enrollment_date >= :start_date'; $binds['start_date'] = $p['start_date']; }
    if (!empty($p['end_date']))   { $where[] = 'e.enrollment_date <= :end_date';   $binds['end_date']   = $p['end_date'];   }
    $enrollBranch2 = $isSuperAdmin ? (!empty($p['branch_id']) ? intval($p['branch_id']) : null) : $sessionBranch;
    if ($enrollBranch2) { $where[] = 's.branch_id = :branch_id'; $binds['branch_id'] = $enrollBranch2; }
    $clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
                e.enrollment_date                 AS date,
                u.name                            AS student_name,
                s.student_id                      AS student_code,
                c.name                            AS course_name,
                c.duration,
                c.fees,
                e.status,
                b.name                            AS branch_name
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN users    u ON s.user_id    = u.id
            JOIN courses  c ON e.course_id  = c.id
            JOIN branches b ON s.branch_id  = b.id
            {$clause}
            ORDER BY e.enrollment_date DESC
            LIMIT 500";
    $stmt = $db->prepare($sql);
    $stmt->execute($binds);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── BRANCH SUMMARY TAB ───────────────────────────────────────────────────────
if ($action === 'branch_summary') {
    $p = array_merge($_GET, $_POST);
    
    $binds = [];
    $revWhere = [];
    if (!empty($p['start_date'])) {
        $revWhere[] = 'DATE(payment_date) >= :start_date';
        $binds['start_date'] = $p['start_date'];
    }
    if (!empty($p['end_date'])) {
        $revWhere[] = 'DATE(payment_date) <= :end_date';
        $binds['end_date'] = $p['end_date'];
    }
    $revClause = $revWhere ? ' AND ' . implode(' AND ', $revWhere) : '';
    
    $branchWhere = "WHERE b.status = 'Active'";
    $branchFilter = $isSuperAdmin ? (!empty($p['branch_id']) ? intval($p['branch_id']) : null) : $sessionBranch;
    if ($branchFilter) {
        $branchWhere .= ' AND b.id = :branch_id';
        $binds['branch_id'] = $branchFilter;
    }
    
    $sql = "SELECT 
                b.name AS branch_name,
                (SELECT COUNT(*) FROM students s WHERE s.branch_id = b.id) AS total_students,
                (SELECT COUNT(*) FROM teachers t WHERE t.branch_id = b.id AND t.status = 'Active') AS total_staff,
                (SELECT COUNT(*) FROM courses c WHERE c.branch_id = b.id) AS total_courses,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.branch_id = b.id {$revClause}) AS total_revenue
            FROM branches b
            {$branchWhere}
            ORDER BY b.name ASC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($binds);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
