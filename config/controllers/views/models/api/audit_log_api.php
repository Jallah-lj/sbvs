<?php
/**
 * audit_log_api.php
 * Provides read-only access to the audit_logs table.
 * Super Admin only.
 */
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$db     = (new Database())->getConnection();
$action = $_GET['action'] ?? 'list';

// ── Auto-create table if missing ───────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    user_name   VARCHAR(150) NOT NULL,
    user_role   VARCHAR(50)  NOT NULL,
    branch_id   INT          NULL,
    action      VARCHAR(100) NOT NULL,
    module      VARCHAR(100) NOT NULL,
    record_id   INT          NULL,
    old_value   LONGTEXT     NULL,
    new_value   LONGTEXT     NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id  (user_id),
    INDEX idx_module   (module),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($action === 'list') {
    $module    = $_GET['module']    ?? '';
    $branchId  = (int)($_GET['branch_id'] ?? 0);
    $dateFrom  = $_GET['date_from'] ?? '';
    $dateTo    = $_GET['date_to']   ?? '';
    $search    = trim($_GET['search'] ?? '');

    $where  = [];
    $params = [];

    if ($module) {
        $where[]  = 'a.module = ?';
        $params[] = $module;
    }
    if ($branchId) {
        $where[]  = 'a.branch_id = ?';
        $params[] = $branchId;
    }
    if ($dateFrom) {
        $where[]  = 'DATE(a.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[]  = 'DATE(a.created_at) <= ?';
        $params[] = $dateTo;
    }
    if ($search) {
        $like     = '%' . $search . '%';
        $where[]  = '(a.user_name LIKE ? OR a.action LIKE ? OR a.module LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT a.id, a.user_name, a.user_role,
                   COALESCE(b.name,'—') AS branch_name,
                   a.action, a.module, a.record_id,
                   a.old_value, a.new_value, a.ip_address, a.created_at
            FROM audit_logs a
            LEFT JOIN branches b ON a.branch_id = b.id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.created_at DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'modules') {
    $rows = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['data' => $rows]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
