<?php
/**
 * system_settings_api.php
 * CRUD for the system_settings table.
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

// ── Auto-create + seed table if missing ───────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS system_settings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    setting_key  VARCHAR(100) NOT NULL UNIQUE,
    setting_val  TEXT         NOT NULL,
    label        VARCHAR(200) NULL,
    category     VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_by   INT          NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = [
    ['max_discount_pct',           '15',                              'Maximum Discount % (without approval)',           'finance'],
    ['invoice_prefix',             'INV',                             'Invoice Number Prefix',                           'finance'],
    ['currency_symbol',            '$',                               'Currency Symbol',                                 'finance'],
    ['school_name',                'Shining Bright Vocational School','School / Institution Name',                       'general'],
    ['school_email',               '',                                'School Contact Email',                            'general'],
    ['school_phone',               '',                                'School Contact Phone',                            'general'],
    ['data_retention_days',        '1095',                            'Data Retention Period (days)',                    'governance'],
    ['allow_cross_branch_reports', '0',                               'Allow Branch Admins to view cross-branch reports','governance'],
    ['sms_provider',               '',                                'SMS Provider Name',                               'integrations'],
    ['smtp_host',                  '',                                'SMTP Host for Email',                             'integrations'],
];
$ins = $db->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_val, label, category) VALUES (?,?,?,?)");
foreach ($defaults as $d) {
    $ins->execute($d);
}

// ── LIST ──────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $cat  = $_GET['category'] ?? '';
    $sql  = "SELECT id, setting_key, setting_val, label, category, updated_at FROM system_settings";
    $args = [];
    if ($cat) {
        $sql  .= " WHERE category = ?";
        $args[] = $cat;
    }
    $sql .= " ORDER BY category, setting_key";
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── SAVE (bulk upsert) ────────────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    if (!is_array($settings) || empty($settings)) {
        echo json_encode(['status' => 'error', 'message' => 'No settings provided']);
        exit;
    }
    $upd = $db->prepare(
        "UPDATE system_settings SET setting_val = ?, updated_by = ? WHERE setting_key = ?"
    );
    $userId = (int)($_SESSION['user_id'] ?? 0);
    foreach ($settings as $key => $val) {
        $upd->execute([trim($val), $userId ?: null, $key]);
    }
    echo json_encode(['status' => 'success', 'message' => 'Settings saved successfully']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
