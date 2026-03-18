<?php
/**
 * system_settings_api.php
 * Full CRUD for system settings with audit log, cache, and defaults.
 * Super Admin only.
 */
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function(Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success'=>false,'status'=>'error','message'=>$e->getMessage()]);
    exit;
});

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success'=>false,'status'=>'error','message'=>'Unauthorized']);
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'status'=>'error','message'=>'Forbidden: Super Admin only']);
    exit;
}

require_once '../../../../config.php';
require_once '../../../../database.php';
$db     = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ── Helpers ──────────────────────────────────────────────────────────────────
function ok(array $p=[]): void {
    ob_clean();
    echo json_encode(array_merge(['success'=>true,'status'=>'success'],$p)); exit;
}
function fail(string $msg, int $code=400): void {
    ob_clean(); http_response_code($code);
    echo json_encode(['success'=>false,'status'=>'error','message'=>$msg]); exit;
}

// ── Ensure tables exist ───────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS system_settings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80)   NOT NULL UNIQUE,
    setting_val TEXT          NULL,
    category    VARCHAR(40)   NOT NULL DEFAULT 'general',
    label       VARCHAR(120)  NOT NULL DEFAULT '',
    description TEXT          NULL,
    input_type  VARCHAR(20)   NOT NULL DEFAULT 'text',
    options     TEXT          NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migrate: Add missing columns if they don't exist
$columnsToAdd = [
    'label'       => "VARCHAR(120) NOT NULL DEFAULT ''",
    'description' => "TEXT NULL",
    'input_type'  => "VARCHAR(20) NOT NULL DEFAULT 'text'",
    'options'     => "TEXT NULL",
];
foreach ($columnsToAdd as $col => $def) {
    $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_settings' AND COLUMN_NAME=?");
    $chk->execute([$col]);
    if (!(int)$chk->fetchColumn()) {
        $db->exec("ALTER TABLE system_settings ADD COLUMN `{$col}` {$def}");
    }
}

$db->exec("CREATE TABLE IF NOT EXISTS system_settings_audit (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80)  NOT NULL,
    old_value   TEXT         NULL,
    new_value   TEXT         NULL,
    changed_by  INT          NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Default settings definition ───────────────────────────────────────────────
// These are seeded into the DB if the key doesn't exist yet.
// They map directly to the data-key attributes in the HTML.
$DEFAULTS = [
    // General
    ['institution_name',         'Shining Bright Vocational School','general',    'Institution Name',              'text'],
    ['institution_tagline',      'Empowering Futures',               'general',    'Institution Tagline',           'text'],
    ['hq_address',               '',                                  'general',    'Headquarters Address',          'text'],
    ['contact_phone',            '',                                  'general',    'Contact Phone',                 'text'],
    ['contact_email',            '',                                  'general',    'Contact Email',                 'email'],
    ['default_currency',         'USD',                               'general',    'Default Currency',              'select'],
    ['academic_year_format',     'YYYY',                              'general',    'Academic Year Format',          'select'],
    ['date_format',              'd M Y',                             'general',    'Date Display Format',           'select'],
    // Finance
    ['max_discount_percent',     '20',                                'finance',    'Max Discount % (Branch Admin)', 'number'],
    ['transfer_admin_fee',       '0.00',                              'finance',    'Transfer Admin Fee',            'number'],
    ['receipt_prefix',           'RCP',                               'finance',    'Receipt Number Prefix',         'text'],
    ['allow_partial_payments',   '1',                                 'finance',    'Allow Partial Payments',        'select'],
    ['late_payment_penalty_pct', '0',                                 'finance',    'Late Payment Penalty (%)',      'number'],
    ['payment_grace_days',       '7',                                 'finance',    'Grace Period (Days)',           'number'],
    ['receipt_security_hash',    '1',                                 'finance',    'Receipt Security Hash',         'select'],
    // Governance
    ['allow_cross_branch_reports','0',                                'governance', 'Cross-Branch Report Access',    'select'],
    ['transfer_approval_chain',  'both',                              'governance', 'Transfer Approval Chain',       'select'],
    ['void_payment_auth',        'sa_only',                           'governance', 'Void Payment Authorization',    'select'],
    ['payroll_run_auth',         'sa_only',                           'governance', 'Payroll Run Authorization',     'select'],
    ['student_self_enrollment',  '0',                                 'governance', 'Student Self-Enrollment',       'select'],
    // Academic
    ['student_id_prefix',        'VS',                                'academic',   'Student ID Prefix',             'text'],
    ['max_enrollments_per_student','1',                               'academic',   'Max Enrollments per Student',   'number'],
    ['enrollment_requires_payment','0',                               'academic',   'Enrollment Requires Payment',   'select'],
    ['certificate_issue_policy', 'full_payment',                      'academic',   'Certificate Issuing Policy',    'select'],
    // Attendance
    ['min_attendance_pct',       '75',                                'attendance', 'Min Attendance % for Cert',     'number'],
    ['allow_past_attendance',    '0',                                 'attendance', 'Allow Past-Date Attendance',    'select'],
    ['late_threshold_minutes',   '15',                                'attendance', 'Late Threshold (Minutes)',      'number'],
    // Notifications
    ['email_receipt_on_payment', '0',                                 'notifications','Payment Receipt Email',       'select'],
    ['low_attendance_alert_pct', '60',                                'notifications','Low Attendance Alert %',      'number'],
    ['notify_transfer_status',   '1',                                 'notifications','Transfer Status Notifications','select'],
    ['notify_payroll_complete',  '1',                                 'notifications','Payroll Completion Notify',   'select'],
    // Integrations
    ['smtp_host',                '',                                  'integrations','SMTP Host',                    'text'],
    ['smtp_port',                '587',                               'integrations','SMTP Port',                    'number'],
    ['smtp_user',                '',                                  'integrations','SMTP Username',                'email'],
    ['smtp_password',            '',                                  'integrations','SMTP Password',                'password'],
    ['sms_api_key',              '',                                  'integrations','SMS Gateway API Key',          'password'],
    ['sms_sender_id',            'SBVS',                              'integrations','SMS Sender ID',                'text'],
    // Security
    ['session_timeout_minutes',  '30',                                'security',   'Session Timeout (Minutes)',     'number'],
    ['max_login_attempts',       '5',                                 'security',   'Max Login Attempts',            'number'],
    ['password_min_length',      '8',                                 'security',   'Password Min Length',           'number'],
    ['require_2fa_superadmin',   '0',                                 'security',   'Two-Factor Authentication',     'select'],
];

// Seed defaults (INSERT IGNORE — never overwrites existing values)
$seedStmt = $db->prepare(
    "INSERT IGNORE INTO system_settings (setting_key, setting_val, category, label, input_type)
     VALUES (?, ?, ?, ?, ?)"
);
foreach ($DEFAULTS as [$key, $val, $cat, $label, $type]) {
    $seedStmt->execute([$key, $val, $cat, $label, $type]);
}

// ── LIST ─────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $stmt = $db->query("SELECT setting_key, setting_val, category, label, input_type, updated_at
                        FROM system_settings ORDER BY category, id");
    ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── HEALTH ───────────────────────────────────────────────────────────────────
if ($action === 'health') {
    $branches = (int)$db->query("SELECT COUNT(*) FROM branches WHERE status='Active'")->fetchColumn();
    ok([
        'branch_count' => $branches,
        'version'      => 'SBVS v2.0',
        'db_ok'        => true,
    ]);
}

// ── SAVE ─────────────────────────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    if (!is_array($settings) || empty($settings)) fail('No settings data received.');

    $updateStmt = $db->prepare(
        "INSERT INTO system_settings (setting_key, setting_val, category, label, input_type)
         VALUES (?, ?, 'general', ?, 'text')
         ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)"
    );
    $auditStmt = $db->prepare(
        "INSERT INTO system_settings_audit (setting_key, old_value, new_value, changed_by)
         VALUES (?, ?, ?, ?)"
    );
    $getOldStmt = $db->prepare("SELECT setting_val FROM system_settings WHERE setting_key = ?");

    $saved = 0;
    foreach ($settings as $key => $val) {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($key)));
        if (!$key) continue;

        // Skip blank passwords — means "no change"
        $getOldStmt->execute([$key]);
        $old = $getOldStmt->fetchColumn();

        // Don't overwrite with blank for password fields
        $isPasswordKey = in_array($key, ['smtp_password','sms_api_key']);
        if ($isPasswordKey && trim($val) === '') continue;

        if ($old !== false && $old === $val) continue; // no change

        $updateStmt->execute([$key, $val, ucwords(str_replace('_',' ',$key))]);
        $auditStmt->execute([$key, $old ?: null, $val, $userId]);
        $saved++;
    }

    // Clear the settings cache file if it exists
    $cacheFile = sys_get_temp_dir() . '/sbvs_settings_cache.json';
    if (file_exists($cacheFile)) @unlink($cacheFile);

    ok(['message' => "{$saved} setting(s) saved successfully."]);
}

// ── RESET DEFAULTS ────────────────────────────────────────────────────────────
if ($action === 'reset_defaults' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $resetStmt = $db->prepare(
        "UPDATE system_settings SET setting_val = ? WHERE setting_key = ?"
    );
    $auditStmt = $db->prepare(
        "INSERT INTO system_settings_audit (setting_key, old_value, new_value, changed_by)
         VALUES (?, ?, ?, ?)"
    );
    $getOldStmt = $db->prepare("SELECT setting_val FROM system_settings WHERE setting_key = ?");

    foreach ($DEFAULTS as [$key, $defaultVal]) {
        $getOldStmt->execute([$key]);
        $old = $getOldStmt->fetchColumn();
        $resetStmt->execute([$defaultVal, $key]);
        $auditStmt->execute([$key, $old ?: null, $defaultVal, $userId]);
    }
    ok(['message' => 'All settings reset to factory defaults.']);
}

// ── CLEAR CACHE ───────────────────────────────────────────────────────────────
if ($action === 'clear_cache' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cacheFile = sys_get_temp_dir() . '/sbvs_settings_cache.json';
    if (file_exists($cacheFile)) @unlink($cacheFile);
    // Also clear PHP opcache for good measure
    if (function_exists('opcache_reset')) opcache_reset();
    ok(['message' => 'System cache cleared.']);
}

// ── AUDIT LOG ─────────────────────────────────────────────────────────────────
if ($action === 'audit_log') {
    $stmt = $db->prepare("
        SELECT a.setting_key, a.old_value, a.new_value, a.created_at,
               COALESCE(u.name, 'System') AS changed_by
        FROM   system_settings_audit a
        LEFT JOIN users u ON u.id = a.changed_by
        ORDER  BY a.created_at DESC
        LIMIT  200
    ");
    $stmt->execute();
    ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── GET SINGLE ────────────────────────────────────────────────────────────────
if ($action === 'get') {
    $key  = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['key'] ?? ''));
    if (!$key) fail('Key is required.');
    $stmt = $db->prepare("SELECT setting_val FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    if ($val === false) fail('Setting not found.', 404);
    ok(['key' => $key, 'value' => $val]);
}

fail('Unknown action: ' . htmlspecialchars($action ?? ''));