<?php
session_start();
header('Content-Type: application/json');
require_once '../../../../database.php';
require_once '../../../../helpers.php';
require_once '../../../../DashboardSecurity.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Communication store for Super Admin -> Branch Admin messaging
$db->exec("CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    branch_id INT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
    channel VARCHAR(50) NOT NULL DEFAULT 'in_app',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_user_id),
    INDEX idx_recipient (recipient_user_id),
    INDEX idx_created (created_at),
    CONSTRAINT fk_admin_msg_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_msg_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? '';

function adminAudit($db, $action, $details) {
    DashboardSecurity::auditLog('manage_admins', $action, $details, $db);
}

function normalizeAdminName($name) {
    $name = preg_replace('/\s+/', ' ', trim((string)$name));
    return $name;
}

function isStrongPassword($password) {
    $password = (string)$password;
    return (
        strlen($password) >= 8 &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/\d/', $password)
    );
}

function branchIsActive($db, $branchId) {
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([(int)$branchId]);
    return (bool)$stmt->fetchColumn();
}

function tableExists($db, $tableName) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$tableName]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function columnExists($db, $tableName, $columnName) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tableName, $columnName]);
    return ((int)$stmt->fetchColumn()) > 0;
}

// CSRF protection for state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'update', 'delete', 'send_message'], true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!DashboardSecurity::verifyToken($token)) {
        adminAudit($db, 'csrf_validation_failed', 'Blocked admin mutation due to invalid CSRF token');
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Security validation failed. Refresh and try again."]);
        exit;
    }
}

if ($action == 'list') {
    // Fetch only users with 'Branch Admin' role
    $query = "SELECT u.id, u.name, u.email, u.status, u.branch_id, b.name as branch_name 
              FROM users u 
              JOIN branches b ON u.branch_id = b.id 
              WHERE u.role = 'Branch Admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo json_encode(["data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action == 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid admin ID"]);
        exit;
    }

    $adminStmt = $db->prepare(
        "SELECT u.id, u.name, u.email, u.status, u.branch_id,
                b.name AS branch_name, b.address AS branch_address, b.phone AS branch_phone,
                b.email AS branch_email, b.status AS branch_status
         FROM users u
         LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.id = ? AND u.role = 'Branch Admin'
         LIMIT 1"
    );
    $adminStmt->execute([$id]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(["status" => "error", "message" => "Branch Admin not found"]);
        exit;
    }

    $branchId = (int)($admin['branch_id'] ?? 0);

    $metrics = [
        'students' => 0,
        'teachers' => 0,
        'courses' => 0,
        'active_enrollments' => 0,
        'outstanding_accounts' => 0,
        'pending_transfers' => 0,
        'last_activity' => null
    ];

    if ($branchId > 0 && tableExists($db, 'students') && columnExists($db, 'students', 'branch_id')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        $metrics['students'] = (int)$stmt->fetchColumn();
    }

    if ($branchId > 0 && tableExists($db, 'teachers') && columnExists($db, 'teachers', 'branch_id')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM teachers WHERE branch_id = ? AND status = 'Active'");
        $stmt->execute([$branchId]);
        $metrics['teachers'] = (int)$stmt->fetchColumn();
    }

    if ($branchId > 0 && tableExists($db, 'courses') && columnExists($db, 'courses', 'branch_id')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        $metrics['courses'] = (int)$stmt->fetchColumn();
    }

    if (
        $branchId > 0 &&
        tableExists($db, 'enrollments') && columnExists($db, 'enrollments', 'student_id') && columnExists($db, 'enrollments', 'status') &&
        tableExists($db, 'students') && columnExists($db, 'students', 'id') && columnExists($db, 'students', 'branch_id')
    ) {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM enrollments e
             JOIN students s ON s.id = e.student_id
             WHERE s.branch_id = ? AND e.status = 'Active'"
        );
        $stmt->execute([$branchId]);
        $metrics['active_enrollments'] = (int)$stmt->fetchColumn();
    }

    if ($branchId > 0 && tableExists($db, 'payments') && columnExists($db, 'payments', 'branch_id') && columnExists($db, 'payments', 'balance')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE branch_id = ? AND balance > 0");
        $stmt->execute([$branchId]);
        $metrics['outstanding_accounts'] = (int)$stmt->fetchColumn();
    }

    if ($branchId > 0 && tableExists($db, 'transfer_requests') && columnExists($db, 'transfer_requests', 'origin_branch_id') && columnExists($db, 'transfer_requests', 'status')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM transfer_requests WHERE origin_branch_id = ? AND status LIKE 'Pending%'");
        $stmt->execute([$branchId]);
        $metrics['pending_transfers'] = (int)$stmt->fetchColumn();
    }

    if (tableExists($db, 'audit_logs') && columnExists($db, 'audit_logs', 'user_id') && columnExists($db, 'audit_logs', 'created_at')) {
        $stmt = $db->prepare("SELECT MAX(created_at) FROM audit_logs WHERE user_id = ?");
        $stmt->execute([$id]);
        $metrics['last_activity'] = $stmt->fetchColumn() ?: null;
    }

    $recentMessages = [];
    if (tableExists($db, 'admin_messages')) {
        $msgStmt = $db->prepare(
            "SELECT subject, message, priority, channel, created_at
             FROM admin_messages
             WHERE recipient_user_id = ?
             ORDER BY created_at DESC
             LIMIT 5"
        );
        $msgStmt->execute([$id]);
        $recentMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'admin' => $admin,
        'metrics' => $metrics,
        'recent_messages' => $recentMessages
    ]);
    exit;
}

if ($action == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name      = normalizeAdminName($_POST['name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $password  = $_POST['password'] ?? '';

    if (!$name || !$email || !$branch_id || !$password) {
        adminAudit($db, 'create_validation_failed', 'Missing required fields while creating Branch Admin');
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || !preg_match('/^[\p{L}\s\.\'\-]+$/u', $name)) {
        adminAudit($db, 'create_validation_failed', 'Invalid name format while creating Branch Admin');
        echo json_encode(["status" => "error", "message" => "Name must be 3-120 characters and contain only letters, spaces, dot, apostrophe, or hyphen"]);
        exit;
    }

    if (!isValidEmail($email)) {
        adminAudit($db, 'create_validation_failed', 'Invalid email format on create: ' . $email);
        echo json_encode(["status" => "error", "message" => "Invalid email format"]);
        exit;
    }

    if (!branchIsActive($db, $branch_id)) {
        adminAudit($db, 'create_validation_failed', 'Inactive/invalid branch selected on create: branch_id=' . $branch_id);
        echo json_encode(["status" => "error", "message" => "Selected branch is invalid or inactive"]);
        exit;
    }

    if (!isStrongPassword($password)) {
        adminAudit($db, 'create_validation_failed', 'Weak password rejected on Branch Admin create');
        echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters and include uppercase, lowercase, and number"]);
        exit;
    }

    // Check for duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        adminAudit($db, 'create_validation_failed', 'Duplicate email on create: ' . $email);
        echo json_encode(["status" => "error", "message" => "Email already in use"]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $query  = "INSERT INTO users (branch_id, name, email, password_hash, role, status) 
               VALUES (?, ?, ?, ?, 'Branch Admin', 'Active')";
    $stmt   = $db->prepare($query);

    if ($stmt->execute([$branch_id, $name, $email, $hashed])) {
        $newId = (int)$db->lastInsertId();
        adminAudit(
            $db,
            'create',
            'Created Branch Admin ID ' . $newId . ' (' . $name . ', ' . $email . ') for branch_id=' . $branch_id
        );
        echo json_encode(["status" => "success", "message" => "Branch Admin created successfully"]);
    } else {
        adminAudit($db, 'create_failed', 'Database error while creating Branch Admin: ' . $email);
        echo json_encode(["status" => "error", "message" => "Failed to create Admin"]);
    }
}

if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = normalizeAdminName($_POST['name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $status    = $_POST['status'] ?? 'Active';
    $password  = $_POST['password'] ?? '';

    $beforeStmt = $db->prepare("SELECT id, name, email, branch_id, status FROM users WHERE id = ? AND role = 'Branch Admin' LIMIT 1");
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$id || !$name || !$email || !$branch_id) {
        adminAudit($db, 'update_validation_failed', 'Missing required fields while updating Branch Admin ID ' . $id);
        echo json_encode(["status" => "error", "message" => "Required fields missing"]);
        exit;
    }

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || !preg_match('/^[\p{L}\s\.\'\-]+$/u', $name)) {
        adminAudit($db, 'update_validation_failed', 'Invalid name format on update for Branch Admin ID ' . $id);
        echo json_encode(["status" => "error", "message" => "Name must be 3-120 characters and contain only letters, spaces, dot, apostrophe, or hyphen"]);
        exit;
    }

    if (!isValidEmail($email)) {
        adminAudit($db, 'update_validation_failed', 'Invalid email format on update for Branch Admin ID ' . $id . ': ' . $email);
        echo json_encode(["status" => "error", "message" => "Invalid email format"]);
        exit;
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        adminAudit($db, 'update_validation_failed', 'Invalid status on update for Branch Admin ID ' . $id . ': ' . $status);
        echo json_encode(["status" => "error", "message" => "Invalid status value"]);
        exit;
    }

    if (!branchIsActive($db, $branch_id)) {
        adminAudit($db, 'update_validation_failed', 'Inactive/invalid branch selected on update for Branch Admin ID ' . $id . ': branch_id=' . $branch_id);
        echo json_encode(["status" => "error", "message" => "Selected branch is invalid or inactive"]);
        exit;
    }

    if ($password !== '' && !isStrongPassword($password)) {
        adminAudit($db, 'update_validation_failed', 'Weak password rejected on update for Branch Admin ID ' . $id);
        echo json_encode(["status" => "error", "message" => "New password must be at least 8 characters and include uppercase, lowercase, and number"]);
        exit;
    }

    // Check for duplicate email (exclude current user)
    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        adminAudit($db, 'update_validation_failed', 'Duplicate email on update for Branch Admin ID ' . $id . ': ' . $email);
        echo json_encode(["status" => "error", "message" => "Email already in use by another account"]);
        exit;
    }

    $ok = false;
    if ($password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $db->prepare("UPDATE users SET name=?, email=?, branch_id=?, status=?, password_hash=? WHERE id=? AND role='Branch Admin'");
        $ok = $stmt->execute([$name, $email, $branch_id, $status, $hashed, $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, branch_id=?, status=? WHERE id=? AND role='Branch Admin'");
        $ok = $stmt->execute([$name, $email, $branch_id, $status, $id]);
    }

    if ($ok) {
        adminAudit(
            $db,
            'update',
            'Updated Branch Admin ID ' . $id .
            ' from [' . (($before['name'] ?? 'N/A')) . ', ' . (($before['email'] ?? 'N/A')) .
            ', branch_id=' . (($before['branch_id'] ?? 'N/A')) . ', status=' . (($before['status'] ?? 'N/A')) .
            '] to [' . $name . ', ' . $email . ', branch_id=' . $branch_id . ', status=' . $status .
            '], password_changed=' . ($password ? 'yes' : 'no')
        );
        echo json_encode(["status" => "success", "message" => "Admin updated successfully"]);
    } else {
        adminAudit($db, 'update_failed', 'Database error while updating Branch Admin ID ' . $id);
        echo json_encode(["status" => "error", "message" => "Failed to update Admin"]);
    }
}

if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    $beforeStmt = $db->prepare("SELECT id, name, email, branch_id, status FROM users WHERE id = ? AND role = 'Branch Admin' LIMIT 1");
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$id) {
        adminAudit($db, 'deactivate_validation_failed', 'Invalid Branch Admin ID supplied for deactivation');
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }
    $stmt = $db->prepare("UPDATE users SET status = 'Inactive' WHERE id = ? AND role = 'Branch Admin'");
    if ($stmt->execute([$id])) {
        if ($stmt->rowCount() > 0) {
            adminAudit(
                $db,
                'deactivate',
                'Deactivated Branch Admin ID ' . $id . ' (' . (($before['name'] ?? 'N/A')) . ', ' . (($before['email'] ?? 'N/A')) . ')'
            );
            echo json_encode(["status" => "success", "message" => "Admin deactivated successfully"]);
        } else {
            adminAudit($db, 'deactivate_failed', 'No matching active Branch Admin found for ID ' . $id);
            echo json_encode(["status" => "error", "message" => "No matching active Branch Admin found"]);
        }
    } else {
        adminAudit($db, 'deactivate_failed', 'Database error while deactivating Branch Admin ID ' . $id);
        echo json_encode(["status" => "error", "message" => "Failed to deactivate Admin"]);
    }
}

if ($action == 'insights') {
    $adminsStmt = $db->prepare(
        "SELECT u.id, u.name, u.email, u.status, u.branch_id, COALESCE(b.name, '—') AS branch_name
         FROM users u
         LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.role = 'Branch Admin'
         ORDER BY u.status = 'Active' DESC, u.name ASC"
    );
    $adminsStmt->execute();
    $admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);

    $activeAdmins = array_values(array_filter($admins, function ($a) {
        return ($a['status'] ?? '') === 'Active';
    }));

    $summary = [
        'active_admins' => count($activeAdmins),
        'covered_branches' => 0,
        'total_students' => 0,
        'pending_transfers' => 0,
        'outstanding_accounts' => 0
    ];

    $summary['covered_branches'] = count(array_unique(array_map(function ($a) {
        return (int)($a['branch_id'] ?? 0);
    }, array_filter($activeAdmins, function ($a) {
        return (int)($a['branch_id'] ?? 0) > 0;
    }))));

    $hasStudents = tableExists($db, 'students') && columnExists($db, 'students', 'branch_id');
    $hasTransfers = tableExists($db, 'transfer_requests') && columnExists($db, 'transfer_requests', 'origin_branch_id') && columnExists($db, 'transfer_requests', 'status');
    $hasPayments = tableExists($db, 'payments') && columnExists($db, 'payments', 'branch_id') && columnExists($db, 'payments', 'balance');
    $hasAudit = tableExists($db, 'audit_logs') && columnExists($db, 'audit_logs', 'user_id') && columnExists($db, 'audit_logs', 'created_at');

    $studentsStmt = $hasStudents ? $db->prepare("SELECT COUNT(*) FROM students WHERE branch_id = ?") : null;
    $transfersStmt = $hasTransfers ? $db->prepare("SELECT COUNT(*) FROM transfer_requests WHERE origin_branch_id = ? AND status LIKE 'Pending%'") : null;
    $paymentsStmt = $hasPayments ? $db->prepare("SELECT COUNT(*) FROM payments WHERE branch_id = ? AND balance > 0") : null;
    $activityStmt = $hasAudit ? $db->prepare("SELECT MAX(created_at) FROM audit_logs WHERE user_id = ?") : null;

    $top = [];
    foreach ($activeAdmins as $a) {
        $branchId = (int)($a['branch_id'] ?? 0);

        $students = 0;
        if ($studentsStmt && $branchId > 0) {
            $studentsStmt->execute([$branchId]);
            $students = (int)$studentsStmt->fetchColumn();
        }

        $pendingTransfers = 0;
        if ($transfersStmt && $branchId > 0) {
            $transfersStmt->execute([$branchId]);
            $pendingTransfers = (int)$transfersStmt->fetchColumn();
        }

        $outstanding = 0;
        if ($paymentsStmt && $branchId > 0) {
            $paymentsStmt->execute([$branchId]);
            $outstanding = (int)$paymentsStmt->fetchColumn();
        }

        $lastActivity = null;
        if ($activityStmt) {
            $activityStmt->execute([(int)$a['id']]);
            $lastActivity = $activityStmt->fetchColumn() ?: null;
        }

        $summary['total_students'] += $students;
        $summary['pending_transfers'] += $pendingTransfers;
        $summary['outstanding_accounts'] += $outstanding;

        $top[] = [
            'id' => (int)$a['id'],
            'name' => $a['name'],
            'email' => $a['email'],
            'branch_id' => $branchId,
            'branch_name' => $a['branch_name'],
            'students' => $students,
            'pending_transfers' => $pendingTransfers,
            'outstanding_accounts' => $outstanding,
            'last_activity' => $lastActivity
        ];
    }

    usort($top, function ($x, $y) {
        $scoreX = ($x['pending_transfers'] * 1000) + ($x['outstanding_accounts'] * 10) + $x['students'];
        $scoreY = ($y['pending_transfers'] * 1000) + ($y['outstanding_accounts'] * 10) + $y['students'];
        return $scoreY <=> $scoreX;
    });

    echo json_encode([
        'status' => 'success',
        'summary' => $summary,
        'top' => array_slice($top, 0, 10)
    ]);
    exit;
}

if ($action == 'send_message' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $targetType = trim($_POST['target_type'] ?? 'all');
    $branchId   = (int)($_POST['branch_id'] ?? 0);
    $adminId    = (int)($_POST['admin_id'] ?? 0);
    $subject    = trim($_POST['subject'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $priority   = trim($_POST['priority'] ?? 'normal');
    $sendEmail  = isset($_POST['send_email']) && (string)$_POST['send_email'] === '1';

    if (!in_array($targetType, ['all', 'branch', 'admin'], true)) {
        adminAudit($db, 'message_validation_failed', 'Invalid target type for message dispatch');
        echo json_encode(["status" => "error", "message" => "Invalid audience selection"]);
        exit;
    }

    if (!in_array($priority, ['normal', 'high', 'urgent'], true)) {
        $priority = 'normal';
    }

    if ($subject === '' || mb_strlen($subject) > 150 || $message === '' || mb_strlen($message) > 2000) {
        adminAudit($db, 'message_validation_failed', 'Subject/message validation failed for dispatch');
        echo json_encode(["status" => "error", "message" => "Subject or message is invalid"]);
        exit;
    }

    if ($targetType === 'branch' && !$branchId) {
        adminAudit($db, 'message_validation_failed', 'Branch target selected without branch ID');
        echo json_encode(["status" => "error", "message" => "Please select a branch"]);
        exit;
    }

    if ($targetType === 'admin' && !$adminId) {
        adminAudit($db, 'message_validation_failed', 'Admin target selected without admin ID');
        echo json_encode(["status" => "error", "message" => "Please select a branch admin"]);
        exit;
    }

    $sql = "SELECT u.id, u.name, u.email, u.branch_id
            FROM users u
            WHERE u.role='Branch Admin' AND u.status='Active'";
    $params = [];

    if ($targetType === 'branch') {
        $sql .= " AND u.branch_id = ?";
        $params[] = $branchId;
    } elseif ($targetType === 'admin') {
        $sql .= " AND u.id = ?";
        $params[] = $adminId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recipients)) {
        adminAudit($db, 'message_dispatch_failed', 'No eligible recipients found for target=' . $targetType);
        echo json_encode(["status" => "error", "message" => "No eligible active branch admins found for this audience"]);
        exit;
    }

    $insert = $db->prepare(
        "INSERT INTO admin_messages (sender_user_id, recipient_user_id, branch_id, subject, message, priority, channel)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $senderId = (int)($_SESSION['user_id'] ?? 0);
    $savedCount = 0;
    $emailCount = 0;

    foreach ($recipients as $rcp) {
        $channel = $sendEmail ? 'in_app,email' : 'in_app';
        if ($insert->execute([
            $senderId,
            (int)$rcp['id'],
            (int)($rcp['branch_id'] ?? 0),
            $subject,
            $message,
            $priority,
            $channel
        ])) {
            $savedCount++;
        }

        if ($sendEmail && !empty($rcp['email'])) {
            $mailSubject = '[SBVS Admin Notice] ' . $subject;
            $mailBody = "Hello " . ($rcp['name'] ?? 'Branch Admin') . ",\n\n" .
                        $message . "\n\n" .
                        "Priority: " . strtoupper($priority) . "\n\n" .
                        "— Sent by Super Admin, SBVS";
            $headers = "From: no-reply@sbvs.example.com\r\n";
            if (@mail($rcp['email'], $mailSubject, $mailBody, $headers)) {
                $emailCount++;
            }
        }
    }

    adminAudit(
        $db,
        'message_dispatch',
        'Sent message to ' . $savedCount . ' branch admin(s), target=' . $targetType .
        ', priority=' . $priority . ', email_sent=' . $emailCount
    );

    echo json_encode([
        "status" => "success",
        "message" => "Message sent to {$savedCount} branch admin(s)." . ($sendEmail ? " Email delivered to {$emailCount}." : '')
    ]);
    exit;
}

if ($action == 'messages') {
    $stmt = $db->prepare(
        "SELECT m.id, m.subject, m.message, m.priority, m.channel, m.created_at,
                r.name AS recipient_name,
                COALESCE(b.name, '—') AS branch_name,
                s.name AS sender_name
         FROM admin_messages m
         JOIN users r ON r.id = m.recipient_user_id
         LEFT JOIN branches b ON b.id = m.branch_id
         LEFT JOIN users s ON s.id = m.sender_user_id
         ORDER BY m.created_at DESC
         LIMIT 80"
    );
    $stmt->execute();
    echo json_encode(["data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
