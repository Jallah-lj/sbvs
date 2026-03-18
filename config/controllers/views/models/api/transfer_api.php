<?php
/**
 * transfer_api.php
 * Defensive version — wraps every query in try/catch so PHP errors
 * never break the JSON output that DataTables expects.
 */
session_start();
// ob_start() AFTER session_start to avoid header conflicts
ob_start();
header('Content-Type: application/json; charset=utf-8');

// ── Catch ALL PHP errors and return them as JSON ────────────────────────────
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => "PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}"
    ]);
    exit;
});
set_exception_handler(function(Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

// ── Auth ────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../../../../config.php';
require_once '../../../../database.php';
require_once '../TransferRequest.php';

$db = (new Database())->getConnection();

// Silence PDO errors so they go through our exception handler
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Helpers ─────────────────────────────────────────────────────────────────
function ok(array $payload = []): void {
    ob_clean();
    echo json_encode(array_merge(['success' => true, 'status' => 'success'], $payload));
    exit;
}
function fail(string $message, int $code = 400): void {
    ob_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $message]);
    exit;
}

// Check if a column exists in a table
function colExists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ?"
    );
    $q->execute([$table, $col]);
    return (int)$q->fetchColumn() > 0;
}

// ── AUTO-MIGRATION ───────────────────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS transfer_requests (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id           VARCHAR(50)  NOT NULL UNIQUE,
        student_id            INT          NOT NULL,
        origin_branch_id      INT          NOT NULL,
        destination_branch_id INT          NOT NULL,
        reason                TEXT         NULL,
        status VARCHAR(60) NOT NULL DEFAULT 'Pending Origin Approval',
        origin_admin_id       INT          NULL,
        destination_admin_id  INT          NULL,
        conditional_notes     TEXT         NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add enhanced columns only if missing
    $extraCols = [
        'fee_policy'        => "VARCHAR(30)   NOT NULL DEFAULT 'migrate'",
        'transfer_fee'      => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'transfer_category' => 'VARCHAR(50)   NULL',
        'priority'          => "VARCHAR(20)   NOT NULL DEFAULT 'Normal'",
        'transfer_date'     => 'DATE          NULL',
        'documents'         => 'VARCHAR(255)  NULL',
    ];
    foreach ($extraCols as $col => $def) {
        if (!colExists($db, 'transfer_requests', $col)) {
            $db->exec("ALTER TABLE transfer_requests ADD COLUMN `{$col}` {$def}");
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS transfer_documents (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        transfer_request_id INT         NOT NULL,
        document_type       VARCHAR(60) NOT NULL DEFAULT 'Other',
        file_path           VARCHAR(255) NOT NULL,
        checksum            VARCHAR(64)  NOT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS transfer_audit_logs (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        transfer_request_id INT          NOT NULL,
        actor_id            INT          NOT NULL,
        action              VARCHAR(100) NOT NULL,
        previous_status     VARCHAR(80)  NULL,
        new_status          VARCHAR(80)  NULL,
        rationale           TEXT         NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

} catch (Throwable $e) {
    // Migration failure must NOT kill the list endpoint — log and continue
    error_log('transfer_api migration error: ' . $e->getMessage());
}

// ── Session vars ─────────────────────────────────────────────────────────────
$transferModel = new TransferRequest($db);
$action        = trim($_GET['action'] ?? '');
$role          = $_SESSION['role']      ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$userId        = (int)($_SESSION['user_id']   ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');

// ── LIST BRANCHES ─────────────────────────────────────────────────────────────
if ($action === 'branches') {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name");
    ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── LIST TRANSFERS ────────────────────────────────────────────────────────────
if ($action === 'list') {
    // Detect which optional columns exist so the query never references missing ones
    $hasFeePolicy    = colExists($db, 'transfer_requests', 'fee_policy');
    $hasPriority     = colExists($db, 'transfer_requests', 'priority');
    $hasCategory     = colExists($db, 'transfer_requests', 'transfer_category');
    $hasTransferFee  = colExists($db, 'transfer_requests', 'transfer_fee');

    // Detect if payments table has a 'status' column
    $pymtHasStatus   = colExists($db, 'payments', 'status');
    $pymtAmtCol      = colExists($db, 'payments', 'amount') ? 'amount' : 'amount_paid';
    $pymtJoin        = $pymtHasStatus
        ? "LEFT JOIN payments p ON p.student_id = s.id AND p.status = 'Active'"
        : "LEFT JOIN payments p ON p.student_id = s.id";
    $pymtSum         = $pymtHasStatus
        ? "COALESCE(SUM(CASE WHEN p.status='Active' THEN p.{$pymtAmtCol} ELSE 0 END), 0)"
        : "COALESCE(SUM(p.{$pymtAmtCol}), 0)";

    // Detect course fee column name (some schemas use 'fee', some 'course_fee')
    $courseFeeCol = colExists($db, 'courses', 'fee') ? 'fee'
                  : (colExists($db, 'courses', 'course_fee') ? 'course_fee' : null);
    $feeExpr   = $courseFeeCol ? "COALESCE(c.{$courseFeeCol}, 0)" : '0';

    $selectExtra  = '';
    if ($hasFeePolicy)   $selectExtra .= ', t.fee_policy';
    if ($hasPriority)    $selectExtra .= ', t.priority';
    if ($hasCategory)    $selectExtra .= ', t.transfer_category';

    $sql = "
        SELECT t.id,
               t.transfer_id,
               u.name  AS student_name,
               s.student_id AS student_code,
               bo.name AS origin_branch,
               bd.name AS destination_branch,
               t.status,
               t.created_at,
               COALESCE(c.name, '') AS course_name,
               {$feeExpr}           AS course_fee,
               {$pymtSum}           AS total_paid,
               GREATEST(0, {$feeExpr} - {$pymtSum}) AS outstanding_balance
               {$selectExtra}
        FROM   transfer_requests t
        JOIN   students s   ON t.student_id            = s.id
        JOIN   users u      ON s.user_id               = u.id
        JOIN   branches bo  ON t.origin_branch_id      = bo.id
        JOIN   branches bd  ON t.destination_branch_id = bd.id
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = 'Active'
        LEFT JOIN courses c     ON c.id = e.course_id
        {$pymtJoin}
    ";

    $where  = [];
    $params = [];

    if (!$isSuperAdmin) {
        $where[]  = '(t.origin_branch_id = ? OR t.destination_branch_id = ?)';
        $params[] = $sessionBranch;
        $params[] = $sessionBranch;
    }
    if (!empty($_GET['branch_id'])) {
        $where[]  = '(t.origin_branch_id = ? OR t.destination_branch_id = ?)';
        $params[] = (int)$_GET['branch_id'];
        $params[] = (int)$_GET['branch_id'];
    }
    if (!empty($_GET['status'])) {
        $where[]  = 't.status LIKE ?';
        $params[] = '%' . $_GET['status'] . '%';
    }
    if (!empty($_GET['date_from'])) {
        $where[]  = 'DATE(t.created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[]  = 'DATE(t.created_at) <= ?';
        $params[] = $_GET['date_to'];
    }

    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY t.id ORDER BY t.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── GET SINGLE TRANSFER ───────────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Invalid transfer ID.');

    // Detect optional columns
    $hasFeePolicy   = colExists($db, 'transfer_requests', 'fee_policy');
    $hasTransferFee = colExists($db, 'transfer_requests', 'transfer_fee');
    $hasCategory    = colExists($db, 'transfer_requests', 'transfer_category');
    $hasPriority    = colExists($db, 'transfer_requests', 'priority');
    $hasTDate       = colExists($db, 'transfer_requests', 'transfer_date');
    $hasDocs        = colExists($db, 'transfer_requests', 'documents');

    $pymtHasStatus = colExists($db, 'payments', 'status');
    $pymtAmtCol    = colExists($db, 'payments', 'amount') ? 'amount' : 'amount_paid';
    $pymtJoin      = $pymtHasStatus
        ? "LEFT JOIN payments p ON p.student_id = s.id AND p.status = 'Active'"
        : "LEFT JOIN payments p ON p.student_id = s.id";
    $pymtSum       = $pymtHasStatus
        ? "COALESCE(SUM(CASE WHEN p.status='Active' THEN p.{$pymtAmtCol} ELSE 0 END), 0)"
        : "COALESCE(SUM(p.{$pymtAmtCol}), 0)";

    $courseFeeCol = colExists($db, 'courses', 'fee') ? 'fee'
                  : (colExists($db, 'courses', 'course_fee') ? 'course_fee' : null);
    $feeExpr      = $courseFeeCol ? "COALESCE(c.{$courseFeeCol}, 0)" : '0';

    $extraSel = '';
    if ($hasFeePolicy)   $extraSel .= ', t.fee_policy';
    if ($hasTransferFee) $extraSel .= ', t.transfer_fee';
    if ($hasCategory)    $extraSel .= ', t.transfer_category';
    if ($hasPriority)    $extraSel .= ', t.priority';
    if ($hasTDate)       $extraSel .= ', t.transfer_date';
    if ($hasDocs)        $extraSel .= ', t.documents';

    $stmt = $db->prepare("
        SELECT t.*,
               u.name  AS student_name,
               s.student_id AS student_code,
               bo.name AS origin_branch,
               bd.name AS destination_branch,
               COALESCE(c.name, '') AS course_name,
               {$feeExpr}           AS course_fee,
               {$pymtSum}           AS total_paid,
               GREATEST(0, {$feeExpr} - {$pymtSum}) AS outstanding_balance
               {$extraSel}
        FROM   transfer_requests t
        JOIN   students s   ON t.student_id            = s.id
        JOIN   users u      ON s.user_id               = u.id
        JOIN   branches bo  ON t.origin_branch_id      = bo.id
        JOIN   branches bd  ON t.destination_branch_id = bd.id
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = 'Active'
        LEFT JOIN courses c     ON c.id = e.course_id
        {$pymtJoin}
        WHERE  t.id = ?
        GROUP  BY t.id
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) fail('Transfer not found.', 404);

    // Documents
    $dStmt = $db->prepare(
        "SELECT * FROM transfer_documents WHERE transfer_request_id = ? ORDER BY uploaded_at DESC"
    );
    $dStmt->execute([$id]);
    $docs = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    // Audit log / timeline
    $lStmt = $db->prepare("
        SELECT l.action, l.previous_status, l.new_status, l.rationale,
               l.created_at, COALESCE(u.name, 'System') AS actor
        FROM   transfer_audit_logs l
        LEFT JOIN users u ON u.id = l.actor_id
        WHERE  l.transfer_request_id = ?
        ORDER  BY l.created_at ASC
    ");
    $lStmt->execute([$id]);
    $rawLogs = $lStmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalise for the timeline renderer
    $timeline = array_map(fn($l) => [
        'action'     => $l['action']      ?? $l['new_status'] ?? '—',
        'actor'      => $l['actor']       ?? 'System',
        'created_at' => $l['created_at']  ?? '',
        'note'       => $l['rationale']   ?? '',
    ], $rawLogs);

    ok([
        'data'      => $row,
        'documents' => $docs,
        'timeline'  => $timeline,
    ]);
}

// ── CREATE TRANSFER ───────────────────────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id   = (int)($_POST['student_id']            ?? 0);
    $origin       = (int)($_POST['origin_branch_id']      ?? 0);
    $dest         = (int)($_POST['destination_branch_id'] ?? 0);
    $reason       = trim($_POST['reason']                 ?? '');

    if (!$student_id || !$origin || !$dest)
        fail('Student, origin branch, and destination branch are required.');
    if ($origin === $dest)
        fail('Origin and destination branches cannot be the same.');
    if (!$isSuperAdmin && $origin !== $sessionBranch)
        fail('Unauthorized to initiate a transfer for this branch.');

    $result = $transferModel->createTransfer($student_id, $origin, $dest, $reason, $userId);

    // Persist extended fields if the columns exist
    $newId = $result['id'] ?? null;
    if (!$newId && !empty($result['transfer_id_str'])) {
        $f = $db->prepare("SELECT id FROM transfer_requests WHERE transfer_id = ? LIMIT 1");
        $f->execute([$result['transfer_id_str']]);
        $newId = (int)$f->fetchColumn() ?: null;
    }
    if ($newId) {
        $setClauses = [];
        $setParams  = [];
        $fieldMap = [
            'fee_policy'        => ['fee_policy',        'string'],
            'transfer_fee'      => ['transfer_fee',       'float'],
            'transfer_category' => ['transfer_category',  'string'],
            'priority'          => ['priority',           'string'],
            'transfer_date'     => ['transfer_date',      'string'],
            'documents'         => ['documents',          'string'],
        ];
        foreach ($fieldMap as $col => [$postKey, $type]) {
            if (isset($_POST[$postKey]) && colExists($db, 'transfer_requests', $col)) {
                $setClauses[] = "`{$col}` = ?";
                $val = $type === 'float' ? (float)$_POST[$postKey] : trim($_POST[$postKey]);
                $setParams[]  = ($val === '') ? null : $val;
            }
        }
        if ($setClauses) {
            $setParams[] = $newId;
            $db->prepare('UPDATE transfer_requests SET ' . implode(', ', $setClauses) . ' WHERE id = ?')
               ->execute($setParams);
        }
        $result['id'] = $newId;
    }

    ob_clean();
    echo json_encode(array_merge(['success' => true, 'status' => 'success'], $result));
    exit;
}

// ── UPLOAD DOCUMENT ───────────────────────────────────────────────────────────
if ($action === 'upload_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_id = (int)($_POST['transfer_request_id'] ?? 0);
    $docType     = $_POST['document_type'] ?? 'Other';

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK)
        fail('File upload failed or no file received.');

    $uploadDir = '../../../../assets/uploads/transfers/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true))
        fail('Could not create upload directory.');

    $ext      = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    $safeName = 'TRF_' . $transfer_id . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($_FILES['document']['tmp_name'], $destPath))
        fail('File move failed.');

    $checksum = hash_file('sha256', $destPath);
    $dbPath   = 'config/assets/uploads/transfers/' . $safeName;

    $db->prepare("INSERT INTO transfer_documents
        (transfer_request_id, document_type, file_path, checksum)
        VALUES (?, ?, ?, ?)")
       ->execute([$transfer_id, $docType, $dbPath, $checksum]);

    if (method_exists($transferModel, 'logAudit')) {
        $transferModel->logAudit($transfer_id, $userId, 'Document Uploaded', null, null,
            "Uploaded {$docType}. Checksum: {$checksum}");
    }

    ok(['file' => $dbPath, 'checksum' => $checksum]);
}

// ── UPDATE STATUS / APPROVAL SHORTCUTS ───────────────────────────────────────
$statusActions = ['update_status','approve_origin','approve_dest','reject','hold','fee_settled','complete'];
if (in_array($action, $statusActions) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $transfer_id = (int)($_POST['transfer_request_id'] ?? $_POST['id'] ?? 0);
    $rationale   = trim($_POST['rationale'] ?? $_POST['note'] ?? '');
    $cond_notes  = trim($_POST['conditional_notes'] ?? '');

    if (!$transfer_id) fail('Transfer ID is required.');

    $transfer = null;
    if (method_exists($transferModel, 'getById'))
        $transfer = $transferModel->getById($transfer_id);
    if (!$transfer) {
        $s = $db->prepare("SELECT id, status FROM transfer_requests WHERE id = ? LIMIT 1");
        $s->execute([$transfer_id]);
        $transfer = $s->fetch(PDO::FETCH_ASSOC);
    }
    if (!$transfer) fail('Transfer not found.', 404);

    // Map shortcut → ENUM status
    $cur = $transfer['status'] ?? '';
    $statusMap = [
        'approve_origin' => 'Pending Destination Approval',
        'approve_dest'   => 'Transfer Complete',
        'hold'           => 'Origin On Hold',
        'fee_settled'    => 'Pending Destination Approval',
        'complete'       => 'Transfer Complete',
        'reject'         => str_contains($cur, 'Destination') ? 'Destination Rejected' : 'Origin Rejected',
    ];

    $new_status = $_POST['status'] ?? $statusMap[$action] ?? null;
    if (!$new_status) fail('Cannot determine target status.');

    $destStatuses  = ['Destination Conditionally Approved', 'Destination Rejected', 'Transfer Complete'];
    $actor_role    = in_array($new_status, $destStatuses) ? 'destination' : 'origin';

    if (method_exists($transferModel, 'updateStatus')) {
        $result = $transferModel->updateStatus(
            $transfer_id, $new_status, $userId, $actor_role, $rationale, $cond_notes
        );
        ob_clean();
        echo json_encode(array_merge(['success' => true, 'status' => 'success'], $result));
    } else {
        // Fallback: direct update + audit log
        $db->prepare("UPDATE transfer_requests SET status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$new_status, $transfer_id]);

        $db->prepare("INSERT INTO transfer_audit_logs
            (transfer_request_id, actor_id, action, previous_status, new_status, rationale)
            VALUES (?, ?, ?, ?, ?, ?)")
           ->execute([$transfer_id, $userId, ucfirst($action), $cur, $new_status, $rationale]);

        ob_clean();
        echo json_encode([
            'success' => true,
            'status'  => 'success',
            'message' => 'Status updated to: ' . $new_status,
        ]);
    }
    exit;
}

fail('Unknown or unsupported action: ' . htmlspecialchars($action));