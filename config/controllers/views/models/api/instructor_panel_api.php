<?php
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../../../database.php';
require_once '../../../../DashboardSecurity.php';

$db = (new Database())->getConnection();
$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';
$isSuperAdmin = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin = ($role === 'Admin');
$isTeacher = ($role === 'Teacher');
$isStudent = ($role === 'Student');
$canAccessInstructorPanel = ($isTeacher || $isSuperAdmin || $isBranchAdmin || $isAdmin || ($isStudent && $action === 'student_resources'));

if (!$canAccessInstructorPanel) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function jsonOut(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function ensureInstructorSchema(PDO $db): void {
    static $ready = false;
    if ($ready) return;
    $ready = true;

    $db->exec("CREATE TABLE IF NOT EXISTS competency_checklists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        batch_id INT NOT NULL,
        student_id INT NOT NULL,
        module_name VARCHAR(150) NOT NULL,
        skill_name VARCHAR(150) NOT NULL,
        status ENUM('NYC','C','Distinction') NOT NULL DEFAULT 'NYC',
        assessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_comp (student_id, batch_id, module_name, skill_name),
        INDEX idx_comp_batch (batch_id),
        INDEX idx_comp_teacher (teacher_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS attendance_hour_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        batch_id INT NOT NULL,
        student_id INT NOT NULL,
        session_date DATE NOT NULL,
        session_type ENUM('Classroom','Workshop') NOT NULL,
        classroom_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
        workshop_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
        notes VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_att (teacher_user_id, student_id, batch_id, session_date, session_type),
        INDEX idx_att_batch_date (batch_id, session_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS trade_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        course_id INT NULL,
        module_name VARCHAR(150) NOT NULL,
        sequence_no INT NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tm_course (course_id),
        INDEX idx_tm_branch (branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS student_module_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('Not Started','In Progress','Completed') NOT NULL DEFAULT 'Not Started',
        completed_at DATETIME NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_smp (module_id, student_id),
        INDEX idx_smp_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS material_requisitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
        urgency ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
        justification TEXT NOT NULL,
        status ENUM('Pending','Approved','Rejected','Issued') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_req_teacher (teacher_user_id),
        INDEX idx_req_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS equipment_fault_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        equipment_name VARCHAR(150) NOT NULL,
        severity ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
        location VARCHAR(150) NULL,
        issue_notes TEXT NOT NULL,
        photo_url VARCHAR(255) NULL,
        status ENUM('Open','In Progress','Resolved') NOT NULL DEFAULT 'Open',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fault_teacher (teacher_user_id),
        INDEX idx_fault_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS safety_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        severity ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_alert_branch (branch_id),
        INDEX idx_alert_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS practical_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NULL,
        batch_id INT NULL,
        title VARCHAR(150) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NULL,
        status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ps_branch_start (branch_id, start_at),
        INDEX idx_ps_teacher (teacher_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS instructor_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        module_name VARCHAR(150) NOT NULL,
        title VARCHAR(150) NOT NULL,
        resource_type ENUM('Link','PDF') NOT NULL DEFAULT 'Link',
        resource_url VARCHAR(500) NULL,
        file_url VARCHAR(255) NULL,
        notes TEXT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        is_approved TINYINT(1) NOT NULL DEFAULT 0,
        rejection_reason VARCHAR(255) NULL,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ir_branch (branch_id),
        INDEX idx_ir_teacher (teacher_user_id),
        INDEX idx_ir_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS competency_signoffs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        teacher_user_id INT NOT NULL,
        batch_id INT NOT NULL,
        student_id INT NOT NULL,
        module_name VARCHAR(150) NOT NULL,
        requested_status ENUM('Competent','Distinction') NOT NULL DEFAULT 'Competent',
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        rejection_reason VARCHAR(255) NULL,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_comp_signoff (batch_id, student_id, module_name),
        INDEX idx_cs_branch (branch_id),
        INDEX idx_cs_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS instructor_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        request_type ENUM('ResourceLink','MaterialRequisition','CompetencySignoff') NOT NULL,
        entity_table VARCHAR(64) NOT NULL,
        entity_id INT NOT NULL,
        submitted_by INT NOT NULL,
        approver_id INT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        rejection_reason VARCHAR(255) NULL,
        payload_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_irq_branch (branch_id),
        INDEX idx_irq_status (status),
        INDEX idx_irq_submitted (submitted_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS approval_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_user_id INT NOT NULL,
        actor_user_id INT NULL,
        branch_id INT NULL,
        title VARCHAR(150) NOT NULL,
        message VARCHAR(255) NOT NULL,
        link_url VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_an_recipient (recipient_user_id),
        INDEX idx_an_read (is_read),
        INDEX idx_an_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $colExists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'material_requisitions' AND COLUMN_NAME = 'reviewed_by'")->fetchColumn();
    if ((int)$colExists === 0) {
        $db->exec("ALTER TABLE material_requisitions ADD COLUMN reviewed_by INT NULL, ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN rejection_reason VARCHAR(255) NULL");
    }
}

function getTeacherScope(PDO $db, int $userId, int $sessionBranch): array {
    $stmt = $db->prepare("SELECT branch_id, specialization FROM teachers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'branch_id' => (int)($row['branch_id'] ?? $sessionBranch),
        'specialization' => trim((string)($row['specialization'] ?? '')),
    ];
}

function batchInScope(PDO $db, int $batchId, int $branchId, string $specialization = ''): bool {
    $stmt = $db->prepare(
        "SELECT b.id
         FROM batches b
         JOIN courses c ON c.id = b.course_id
         WHERE b.id = ?
           AND b.branch_id = ?
           AND (? = '' OR c.name = ?)
         LIMIT 1"
    );
    $stmt->execute([$batchId, $branchId, $specialization, $specialization]);
    return (bool)$stmt->fetchColumn();
}

function courseInScope(PDO $db, int $courseId, int $branchId, string $specialization = ''): bool {
    $stmt = $db->prepare(
        "SELECT c.id
         FROM courses c
         WHERE c.id = ?
           AND c.branch_id = ?
           AND (? = '' OR c.name = ?)
         LIMIT 1"
    );
    $stmt->execute([$courseId, $branchId, $specialization, $specialization]);
    return (bool)$stmt->fetchColumn();
}

function ensureBatchForCourse(PDO $db, int $courseId, int $branchId): int {
    $stmt = $db->prepare("SELECT id FROM batches WHERE course_id = ? AND branch_id = ? ORDER BY start_date DESC, id DESC LIMIT 1");
    $stmt->execute([$courseId, $branchId]);
    $batchId = (int)$stmt->fetchColumn();
    if ($batchId > 0) {
        return $batchId;
    }

    $name = 'Course Class - ' . date('M Y');
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime($startDate . ' +6 months'));
    $ins = $db->prepare("INSERT INTO batches (branch_id, course_id, name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    $ins->execute([$branchId, $courseId, $name, $startDate, $endDate]);
    return (int)$db->lastInsertId();
}

function isApprovalRole(string $role): bool {
    return in_array($role, ['Super Admin', 'Branch Admin', 'Admin'], true);
}

function createApprovalRequest(PDO $db, int $branchId, string $type, string $entityTable, int $entityId, int $submittedBy, ?array $payload = null): int {
    $stmt = $db->prepare(
        "INSERT INTO instructor_requests
         (branch_id, request_type, entity_table, entity_id, submitted_by, status, payload_json)
         VALUES (?, ?, ?, ?, ?, 'Pending', ?)"
    );
    $payloadJson = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
    $stmt->execute([$branchId, $type, $entityTable, $entityId, $submittedBy, $payloadJson]);
    return (int)$db->lastInsertId();
}

function notifyApprovers(PDO $db, int $branchId, int $actorUserId, string $title, string $message, string $linkUrl = ''): void {
    $stmt = $db->prepare(
        "SELECT id FROM users
         WHERE status = 'Active'
           AND role IN ('Super Admin','Branch Admin','Admin')
           AND (role = 'Super Admin' OR branch_id = ?)"
    );
    $stmt->execute([$branchId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) return;

    $ins = $db->prepare(
        "INSERT INTO approval_notifications
         (recipient_user_id, actor_user_id, branch_id, title, message, link_url, is_read)
         VALUES (?, ?, ?, ?, ?, ?, 0)"
    );
    foreach ($ids as $rid) {
        if ((int)$rid === $actorUserId) continue;
        $ins->execute([(int)$rid, $actorUserId, $branchId, $title, $message, $linkUrl]);
    }
}

function notifyRequester(PDO $db, int $recipientUserId, int $actorUserId, int $branchId, string $title, string $message, string $linkUrl = ''): void {
    $stmt = $db->prepare(
        "INSERT INTO approval_notifications
         (recipient_user_id, actor_user_id, branch_id, title, message, link_url, is_read)
         VALUES (?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->execute([$recipientUserId, $actorUserId, $branchId, $title, $message, $linkUrl]);
}

ensureInstructorSchema($db);

$teacherScope = $isTeacher
    ? getTeacherScope($db, $userId, $sessionBranch)
    : ['branch_id' => $sessionBranch, 'specialization' => ''];

$scopeBranch = $isSuperAdmin
    ? (int)($_GET['branch_id'] ?? $_POST['branch_id'] ?? $sessionBranch)
    : (int)$teacherScope['branch_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if ($token && !DashboardSecurity::verifyToken($token)) {
        jsonOut(['success' => false, 'message' => 'Security validation failed. Refresh and retry.'], 403);
    }
}

try {
    if ($action === 'dashboard') {
        $cohortStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM batches b
             JOIN courses c ON c.id = b.course_id
             WHERE b.branch_id = ?
               AND b.status = 'Active'
               AND (? = '' OR c.name = ?)"
        );
        $cohortStmt->execute([$scopeBranch, $teacherScope['specialization'], $teacherScope['specialization']]);
        $activeCohorts = (int)$cohortStmt->fetchColumn();

        $alertStmt = $db->prepare(
            "SELECT id, title, message, severity, created_at
             FROM safety_alerts
             WHERE is_active = 1
               AND (branch_id IS NULL OR branch_id = ?)
             ORDER BY created_at DESC
             LIMIT 5"
        );
        $alertStmt->execute([$scopeBranch]);
        $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

        $nextStmt = $db->prepare(
            "SELECT id, title, start_at, end_at
             FROM practical_sessions
             WHERE branch_id = ?
               AND status = 'Scheduled'
               AND start_at >= NOW()
               AND (? = 0 OR teacher_user_id = ? OR teacher_user_id IS NULL)
             ORDER BY start_at ASC
             LIMIT 1"
        );
        $nextStmt->execute([$scopeBranch, $isTeacher ? 1 : 0, $userId]);
        $next = $nextStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pendingReqStmt = $db->prepare(
            "SELECT COUNT(*) FROM material_requisitions
             WHERE branch_id = ?
               AND status = 'Pending'
               AND (? = 0 OR teacher_user_id = ?)"
        );
        $pendingReqStmt->execute([$scopeBranch, $isTeacher ? 1 : 0, $userId]);
        $pendingReq = (int)$pendingReqStmt->fetchColumn();

        jsonOut([
            'success' => true,
            'data' => [
                'active_cohorts' => $activeCohorts,
                'safety_alert_count' => count($alerts),
                'safety_alerts' => $alerts,
                'next_practical_session' => $next,
                'pending_requisitions' => $pendingReq,
            ]
        ]);
    }

        if ($action === 'cohorts' || $action === 'courses') {
        $stmt = $db->prepare(
                        "SELECT c.id,
                                        c.id AS course_id,
                                        c.name AS course_name,
                                        c.name AS class_name,
                                        c.name AS batch_name,
                                        COALESCE(c.status, 'Active') AS status,
                                        c.duration,
                                        c.fees
                         FROM courses c
                         WHERE c.branch_id = ?
                             AND (? = '' OR c.name = ?)
             ORDER BY
                                 CASE COALESCE(c.status, 'Active')
                    WHEN 'Active' THEN 1
                    WHEN 'Upcoming' THEN 2
                    ELSE 3
                 END,
                                 c.name ASC"
        );
        $stmt->execute([$scopeBranch, $teacherScope['specialization'], $teacherScope['specialization']]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'competency_matrix') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $moduleName = trim((string)($_GET['module_name'] ?? 'Core Module'));

        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if ($courseId <= 0) {
            if ($isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
        }

        $studentsStmt = $db->prepare(
            "SELECT s.id, s.student_id, u.name
             FROM enrollments e
             JOIN students s ON s.id = e.student_id
             JOIN users u ON u.id = s.user_id
             WHERE e.batch_id = ?
             GROUP BY s.id, s.student_id, u.name
             ORDER BY u.name ASC"
        );
        $studentsStmt->execute([$batchId]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $skillsStmt = $db->prepare(
            "SELECT DISTINCT skill_name
             FROM competency_checklists
             WHERE batch_id = ? AND module_name = ?
             ORDER BY skill_name ASC"
        );
        $skillsStmt->execute([$batchId, $moduleName]);
        $skills = $skillsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($skills)) {
            $skills = ['Safety Procedure', 'Tool Handling', 'Task Accuracy', 'Time Management'];
        }

        $statusStmt = $db->prepare(
            "SELECT student_id, skill_name, status
             FROM competency_checklists
             WHERE batch_id = ? AND module_name = ?"
        );
        $statusStmt->execute([$batchId, $moduleName]);
        $matrix = [];
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $matrix[(int)$row['student_id']][$row['skill_name']] = $row['status'];
        }

        jsonOut([
            'success' => true,
            'data' => [
                'students' => $students,
                'skills' => $skills,
                'matrix' => $matrix,
            ]
        ]);
    }

    if ($action === 'save_competency' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $moduleName = trim((string)($_POST['module_name'] ?? 'Core Module'));
        $records = json_decode((string)($_POST['records'] ?? '[]'), true);

        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if (!is_array($records) || empty($records)) jsonOut(['success' => false, 'message' => 'No competency records provided'], 422);
        if ($courseId <= 0 && $isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
            jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
        }

        $allowed = ['NYC', 'C', 'Distinction'];
        $stmt = $db->prepare(
            "INSERT INTO competency_checklists
             (branch_id, teacher_user_id, batch_id, student_id, module_name, skill_name, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               teacher_user_id = VALUES(teacher_user_id),
               status = VALUES(status),
               assessed_at = CURRENT_TIMESTAMP"
        );

        foreach ($records as $r) {
            $studentId = (int)($r['student_id'] ?? 0);
            $skill = trim((string)($r['skill_name'] ?? ''));
            $status = trim((string)($r['status'] ?? 'NYC'));
            if ($studentId <= 0 || $skill === '' || !in_array($status, $allowed, true)) continue;
            $stmt->execute([$scopeBranch, $userId, $batchId, $studentId, $moduleName, $skill, $status]);
        }

        jsonOut(['success' => true, 'message' => 'Competency matrix saved']);
    }

    if ($action === 'attendance_load') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $sessionDate = trim((string)($_GET['session_date'] ?? date('Y-m-d')));
        $sessionType = trim((string)($_GET['session_type'] ?? 'Classroom'));

        if (!in_array($sessionType, ['Classroom', 'Workshop'], true)) {
            jsonOut(['success' => false, 'message' => 'Invalid session type'], 422);
        }
        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if ($courseId <= 0 && $isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
            jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
        }

        $studentsStmt = $db->prepare(
            "SELECT s.id, s.student_id, u.name
             FROM enrollments e
             JOIN students s ON s.id = e.student_id
             JOIN users u ON u.id = s.user_id
             WHERE e.batch_id = ?
             GROUP BY s.id, s.student_id, u.name
             ORDER BY u.name ASC"
        );
        $studentsStmt->execute([$batchId]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $logStmt = $db->prepare(
            "SELECT student_id, classroom_hours, workshop_hours, notes
             FROM attendance_hour_logs
             WHERE batch_id = ?
               AND session_date = ?
               AND session_type = ?
               AND (? = 0 OR teacher_user_id = ?)"
        );
        $logStmt->execute([$batchId, $sessionDate, $sessionType, $isTeacher ? 1 : 0, $userId]);
        $logs = [];
        foreach ($logStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $logs[(int)$r['student_id']] = $r;
        }

        jsonOut(['success' => true, 'data' => ['students' => $students, 'logs' => $logs]]);
    }

    if ($action === 'attendance_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $sessionDate = trim((string)($_POST['session_date'] ?? date('Y-m-d')));
        $sessionType = trim((string)($_POST['session_type'] ?? 'Classroom'));
        $entries = json_decode((string)($_POST['entries'] ?? '[]'), true);

        if (!in_array($sessionType, ['Classroom', 'Workshop'], true)) {
            jsonOut(['success' => false, 'message' => 'Invalid session type'], 422);
        }
        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if (!is_array($entries) || empty($entries)) jsonOut(['success' => false, 'message' => 'No attendance entries provided'], 422);
        if ($courseId <= 0 && $isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
            jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
        }

        $stmt = $db->prepare(
            "INSERT INTO attendance_hour_logs
             (branch_id, teacher_user_id, batch_id, student_id, session_date, session_type, classroom_hours, workshop_hours, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                classroom_hours = VALUES(classroom_hours),
                workshop_hours = VALUES(workshop_hours),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP"
        );

        foreach ($entries as $e) {
            $studentId = (int)($e['student_id'] ?? 0);
            if ($studentId <= 0) continue;
            $ch = max(0, (float)($e['classroom_hours'] ?? 0));
            $wh = max(0, (float)($e['workshop_hours'] ?? 0));
            $notes = trim((string)($e['notes'] ?? ''));
            $stmt->execute([$scopeBranch, $userId, $batchId, $studentId, $sessionDate, $sessionType, $ch, $wh, $notes]);
        }

        jsonOut(['success' => true, 'message' => 'Attendance hours saved']);
    }

    if ($action === 'module_progress') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if ($courseId <= 0 && $isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
            jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
        }

        $courseStmt = $db->prepare("SELECT course_id FROM batches WHERE id = ? LIMIT 1");
        $courseStmt->execute([$batchId]);
        $courseId = (int)$courseStmt->fetchColumn();

        $enrolledStmt = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE batch_id = ?");
        $enrolledStmt->execute([$batchId]);
        $studentTotal = max(1, (int)$enrolledStmt->fetchColumn());

        $modsStmt = $db->prepare(
            "SELECT id, module_name, sequence_no
             FROM trade_modules
             WHERE branch_id = ?
               AND is_active = 1
               AND (course_id = ? OR course_id IS NULL)
             ORDER BY sequence_no ASC, id ASC"
        );
        $modsStmt->execute([$scopeBranch, $courseId]);
        $modules = $modsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($modules)) {
            $seedStmt = $db->prepare(
                "INSERT INTO trade_modules (branch_id, course_id, module_name, sequence_no)
                 VALUES (?, ?, ?, ?), (?, ?, ?, ?), (?, ?, ?, ?)"
            );
            $seedStmt->execute([
                $scopeBranch, $courseId, 'Basic Safety & Tools', 1,
                $scopeBranch, $courseId, 'Core Practical Operation', 2,
                $scopeBranch, $courseId, 'Advanced Task Execution', 3
            ]);
            $modsStmt->execute([$scopeBranch, $courseId]);
            $modules = $modsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $out = [];
        $progStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM student_module_progress smp
             JOIN enrollments e ON e.student_id = smp.student_id
             WHERE smp.module_id = ?
               AND e.batch_id = ?
               AND smp.status = 'Completed'"
        );

        foreach ($modules as $m) {
            $progStmt->execute([(int)$m['id'], $batchId]);
            $completed = (int)$progStmt->fetchColumn();
            $percent = (int)round(($completed / $studentTotal) * 100);
            $out[] = [
                'module_id' => (int)$m['id'],
                'module_name' => $m['module_name'],
                'completed' => $completed,
                'total' => $studentTotal,
                'percent' => $percent,
            ];
        }

        jsonOut(['success' => true, 'data' => $out]);
    }

    if ($action === 'submit_requisition' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $item = trim((string)($_POST['item_name'] ?? ''));
        $qty = (float)($_POST['quantity'] ?? 0);
        $unit = trim((string)($_POST['unit'] ?? 'pcs'));
        $urgency = trim((string)($_POST['urgency'] ?? 'Medium'));
        $justification = trim((string)($_POST['justification'] ?? ''));

        if ($item === '' || $qty <= 0 || $justification === '') {
            jsonOut(['success' => false, 'message' => 'Item, quantity, and justification are required'], 422);
        }
        if (!in_array($urgency, ['Low', 'Medium', 'High', 'Critical'], true)) {
            jsonOut(['success' => false, 'message' => 'Invalid urgency'], 422);
        }

        $stmt = $db->prepare(
            "INSERT INTO material_requisitions
             (branch_id, teacher_user_id, item_name, quantity, unit, urgency, justification, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->execute([$scopeBranch, $userId, $item, $qty, $unit, $urgency, $justification]);

        $entityId = (int)$db->lastInsertId();
        createApprovalRequest(
            $db,
            $scopeBranch,
            'MaterialRequisition',
            'material_requisitions',
            $entityId,
            $userId,
            ['item_name' => $item, 'quantity' => $qty, 'unit' => $unit, 'urgency' => $urgency]
        );
        notifyApprovers(
            $db,
            $scopeBranch,
            $userId,
            'New Material Requisition',
            'Instructor submitted a material requisition pending approval.',
            'instructor_approval_dashboard.php'
        );

        jsonOut(['success' => true, 'message' => 'Material requisition submitted']);
    }

    if ($action === 'list_requisitions') {
        $stmt = $db->prepare(
            "SELECT id, item_name, quantity, unit, urgency, status, rejection_reason, created_at
             FROM material_requisitions
             WHERE branch_id = ?
               AND (? = 0 OR teacher_user_id = ?)
             ORDER BY created_at DESC
             LIMIT 30"
        );
        $stmt->execute([$scopeBranch, $isTeacher ? 1 : 0, $userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'submit_fault' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $equipment = trim((string)($_POST['equipment_name'] ?? ''));
        $severity = trim((string)($_POST['severity'] ?? 'Medium'));
        $location = trim((string)($_POST['location'] ?? ''));
        $notes = trim((string)($_POST['issue_notes'] ?? ''));

        if ($equipment === '' || $notes === '') {
            jsonOut(['success' => false, 'message' => 'Equipment name and issue notes are required'], 422);
        }
        if (!in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true)) {
            jsonOut(['success' => false, 'message' => 'Invalid severity'], 422);
        }

        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                jsonOut(['success' => false, 'message' => 'Invalid image format'], 422);
            }
            $projectRoot = realpath(dirname(__DIR__, 5)) ?: dirname(__DIR__, 5);
            $relDir = 'uploads/faults';
            $absDir = $projectRoot . '/' . $relDir;
            if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                jsonOut(['success' => false, 'message' => 'Failed to prepare upload directory'], 500);
            }
            $name = 'fault_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $absPath = $absDir . '/' . $name;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $absPath)) {
                jsonOut(['success' => false, 'message' => 'Failed to upload image'], 500);
            }
            $photoUrl = $relDir . '/' . $name;
        }

        $stmt = $db->prepare(
            "INSERT INTO equipment_fault_reports
             (branch_id, teacher_user_id, equipment_name, severity, location, issue_notes, photo_url, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Open')"
        );
        $stmt->execute([$scopeBranch, $userId, $equipment, $severity, $location, $notes, $photoUrl]);

        jsonOut(['success' => true, 'message' => 'Fault report submitted']);
    }

    if ($action === 'list_faults') {
        $stmt = $db->prepare(
            "SELECT id, equipment_name, severity, location, issue_notes, photo_url, status, created_at
             FROM equipment_fault_reports
             WHERE branch_id = ?
               AND (? = 0 OR teacher_user_id = ?)
             ORDER BY created_at DESC
             LIMIT 30"
        );
        $stmt->execute([$scopeBranch, $isTeacher ? 1 : 0, $userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'submit_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $moduleName = trim((string)($_POST['module_name'] ?? 'Core Module'));
        $title = trim((string)($_POST['title'] ?? ''));
        $resourceType = trim((string)($_POST['resource_type'] ?? 'Link'));
        $resourceUrl = trim((string)($_POST['resource_url'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($title === '' || !in_array($resourceType, ['Link', 'PDF'], true)) {
            jsonOut(['success' => false, 'message' => 'Title and valid resource type are required'], 422);
        }

        $fileUrl = null;
        if ($resourceType === 'Link') {
            if (!filter_var($resourceUrl, FILTER_VALIDATE_URL)) {
                jsonOut(['success' => false, 'message' => 'Please provide a valid external URL'], 422);
            }
        } else {
            if (empty($_FILES['resource_file']['tmp_name'])) {
                jsonOut(['success' => false, 'message' => 'Please upload a PDF file'], 422);
            }
            $ext = strtolower(pathinfo((string)$_FILES['resource_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                jsonOut(['success' => false, 'message' => 'Only PDF files are allowed'], 422);
            }
            $projectRoot = realpath(dirname(__DIR__, 5)) ?: dirname(__DIR__, 5);
            $relDir = 'uploads/resources';
            $absDir = $projectRoot . '/' . $relDir;
            if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                jsonOut(['success' => false, 'message' => 'Failed to prepare resource upload directory'], 500);
            }
            $name = 'res_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.pdf';
            $absPath = $absDir . '/' . $name;
            if (!move_uploaded_file($_FILES['resource_file']['tmp_name'], $absPath)) {
                jsonOut(['success' => false, 'message' => 'Failed to upload resource file'], 500);
            }
            $fileUrl = $relDir . '/' . $name;
            $resourceUrl = '';
        }

        $stmt = $db->prepare(
            "INSERT INTO instructor_resources
             (branch_id, teacher_user_id, module_name, title, resource_type, resource_url, file_url, notes, status, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 0)"
        );
        $stmt->execute([$scopeBranch, $userId, $moduleName, $title, $resourceType, $resourceUrl ?: null, $fileUrl, $notes ?: null]);
        $entityId = (int)$db->lastInsertId();

        createApprovalRequest(
            $db,
            $scopeBranch,
            'ResourceLink',
            'instructor_resources',
            $entityId,
            $userId,
            ['module_name' => $moduleName, 'title' => $title, 'resource_type' => $resourceType]
        );
        notifyApprovers(
            $db,
            $scopeBranch,
            $userId,
            'New Resource Submission',
            'Instructor submitted a study resource pending verification.',
            'instructor_approval_dashboard.php'
        );

        jsonOut(['success' => true, 'message' => 'Resource submitted for admin verification']);
    }

    if ($action === 'list_resources') {
        $stmt = $db->prepare(
            "SELECT id, module_name, title, resource_type, resource_url, file_url, status, rejection_reason, created_at
             FROM instructor_resources
             WHERE branch_id = ?
               AND (? = 0 OR teacher_user_id = ?)
             ORDER BY created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$scopeBranch, $isTeacher ? 1 : 0, $userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'student_resources') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $moduleName = trim((string)($_GET['module_name'] ?? ''));
        if ($courseId <= 0 && $batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);

        $stmt = $db->prepare(
            "SELECT id, module_name, title, resource_type, resource_url, file_url, notes, created_at
             FROM instructor_resources
             WHERE branch_id = ?
               AND status = 'Approved'
               AND is_approved = 1
               AND (? = '' OR module_name = ?)
             ORDER BY created_at DESC"
        );
        $stmt->execute([$scopeBranch, $moduleName, $moduleName]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'submit_competency_signoff' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $moduleName = trim((string)($_POST['module_name'] ?? 'Core Module'));
        if ($courseId > 0) {
            if ($isTeacher && !courseInScope($db, $courseId, $scopeBranch, $teacherScope['specialization'])) {
                jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
            }
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }

        if ($batchId <= 0) jsonOut(['success' => false, 'message' => 'Course is required'], 422);
        if ($courseId <= 0 && $isTeacher && !batchInScope($db, $batchId, $scopeBranch, $teacherScope['specialization'])) {
            jsonOut(['success' => false, 'message' => 'Course out of scope'], 403);
        }

        $eligibleStmt = $db->prepare(
            "SELECT cc.student_id,
                    CASE WHEN SUM(cc.status = 'Distinction') > 0 THEN 'Distinction' ELSE 'Competent' END AS req_status
             FROM competency_checklists cc
             WHERE cc.batch_id = ?
               AND cc.module_name = ?
               AND cc.status IN ('C','Distinction')
             GROUP BY cc.student_id"
        );
        $eligibleStmt->execute([$batchId, $moduleName]);
        $rows = $eligibleStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            jsonOut(['success' => false, 'message' => 'No competency records eligible for sign-off'], 422);
        }

        $upsert = $db->prepare(
            "INSERT INTO competency_signoffs
             (branch_id, teacher_user_id, batch_id, student_id, module_name, requested_status, status, rejection_reason, reviewed_by, reviewed_at)
             VALUES (?, ?, ?, ?, ?, ?, 'Pending', NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE
               requested_status = VALUES(requested_status),
               status = 'Pending',
               rejection_reason = NULL,
               reviewed_by = NULL,
               reviewed_at = NULL,
               updated_at = CURRENT_TIMESTAMP"
        );

        $count = 0;
        foreach ($rows as $r) {
            $upsert->execute([$scopeBranch, $userId, $batchId, (int)$r['student_id'], $moduleName, $r['req_status']]);
            $entityId = (int)$db->lastInsertId();
            if ($entityId <= 0) {
                $idStmt = $db->prepare("SELECT id FROM competency_signoffs WHERE batch_id = ? AND student_id = ? AND module_name = ? LIMIT 1");
                $idStmt->execute([$batchId, (int)$r['student_id'], $moduleName]);
                $entityId = (int)$idStmt->fetchColumn();
            }
            if ($entityId > 0) {
                createApprovalRequest(
                    $db,
                    $scopeBranch,
                    'CompetencySignoff',
                    'competency_signoffs',
                    $entityId,
                    $userId,
                    ['course_id' => $courseId, 'module_name' => $moduleName, 'student_id' => (int)$r['student_id']]
                );
                $count++;
            }
        }

        notifyApprovers(
            $db,
            $scopeBranch,
            $userId,
            'Competency Sign-off Requested',
            'Instructor requested admin verification for competency marking.',
            'instructor_approval_dashboard.php'
        );

        jsonOut(['success' => true, 'message' => "Submitted {$count} competency sign-off request(s)"]);
    }

    if ($action === 'list_competency_signoffs') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $moduleName = trim((string)($_GET['module_name'] ?? ''));
        if ($courseId > 0 && $batchId <= 0) {
            $batchId = ensureBatchForCourse($db, $courseId, $scopeBranch);
        }
        $stmt = $db->prepare(
            "SELECT cs.id, cs.student_id, u.name AS student_name, s.student_id AS student_code,
                    cs.module_name, cs.requested_status, cs.status, cs.rejection_reason, cs.created_at, cs.reviewed_at
             FROM competency_signoffs cs
             JOIN students s ON s.id = cs.student_id
             JOIN users u ON u.id = s.user_id
             WHERE cs.branch_id = ?
               AND (? = 0 OR cs.batch_id = ?)
               AND (? = '' OR cs.module_name = ?)
               AND (? = 0 OR cs.teacher_user_id = ?)
             ORDER BY cs.created_at DESC"
        );
        $stmt->execute([$scopeBranch, $batchId, $batchId, $moduleName, $moduleName, $isTeacher ? 1 : 0, $userId]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'can_generate_certificate') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        $moduleName = trim((string)($_GET['module_name'] ?? 'Core Module'));
        if ($studentId <= 0) jsonOut(['success' => false, 'message' => 'Student is required'], 422);

        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM competency_signoffs
             WHERE branch_id = ?
               AND student_id = ?
               AND module_name = ?
               AND status = 'Approved'"
        );
        $stmt->execute([$scopeBranch, $studentId, $moduleName]);
        $ok = ((int)$stmt->fetchColumn()) > 0;
        jsonOut(['success' => true, 'can_generate' => $ok]);
    }

    if ($action === 'admin_pending_queue') {
        if (!isApprovalRole($role)) jsonOut(['success' => false, 'message' => 'Access denied'], 403);

        $status = trim((string)($_GET['status'] ?? 'Pending'));
        $query =
            "SELECT ir.id, ir.request_type, ir.entity_table, ir.entity_id, ir.status, ir.rejection_reason,
                    ir.created_at, ir.reviewed_at, ir.branch_id, ir.payload_json,
                    su.name AS submitted_by_name, su.role AS submitted_by_role,
                    b.name AS branch_name
             FROM instructor_requests ir
             JOIN users su ON su.id = ir.submitted_by
             LEFT JOIN branches b ON b.id = ir.branch_id
             WHERE (? = 'Super Admin' OR ir.branch_id = ?)
               AND (? = '' OR ir.status = ?)
             ORDER BY
               CASE ir.status WHEN 'Pending' THEN 1 WHEN 'Rejected' THEN 2 ELSE 3 END,
               ir.created_at DESC
             LIMIT 200";
        $stmt = $db->prepare($query);
        $stmt->execute([$role, $scopeBranch, $status, $status]);
        jsonOut(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'review_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isApprovalRole($role)) jsonOut(['success' => false, 'message' => 'Access denied'], 403);

        $requestId = (int)($_POST['request_id'] ?? 0);
        $decision = trim((string)($_POST['status'] ?? ''));
        $reason = trim((string)($_POST['rejection_reason'] ?? ''));
        if ($requestId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) {
            jsonOut(['success' => false, 'message' => 'Invalid review payload'], 422);
        }
        if ($decision === 'Rejected' && $reason === '') {
            jsonOut(['success' => false, 'message' => 'Reason for rejection is required'], 422);
        }

        $rqStmt = $db->prepare("SELECT * FROM instructor_requests WHERE id = ? LIMIT 1");
        $rqStmt->execute([$requestId]);
        $rq = $rqStmt->fetch(PDO::FETCH_ASSOC);
        if (!$rq) jsonOut(['success' => false, 'message' => 'Request not found'], 404);
        if ($role !== 'Super Admin' && (int)$rq['branch_id'] !== $scopeBranch) {
            jsonOut(['success' => false, 'message' => 'Request out of scope'], 403);
        }

        $db->beginTransaction();
        try {
            $upd = $db->prepare(
                "UPDATE instructor_requests
                 SET status = ?, approver_id = ?, rejection_reason = ?, reviewed_at = NOW()
                 WHERE id = ?"
            );
            $upd->execute([$decision, $userId, $decision === 'Rejected' ? $reason : null, $requestId]);

            $entityTable = (string)$rq['entity_table'];
            $entityId = (int)$rq['entity_id'];
            if ($entityTable === 'instructor_resources') {
                $eu = $db->prepare(
                    "UPDATE instructor_resources
                     SET status = ?, is_approved = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                     WHERE id = ?"
                );
                $eu->execute([$decision, $decision === 'Approved' ? 1 : 0, $decision === 'Rejected' ? $reason : null, $userId, $entityId]);
            } elseif ($entityTable === 'material_requisitions') {
                $eu = $db->prepare(
                    "UPDATE material_requisitions
                     SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                     WHERE id = ?"
                );
                $eu->execute([$decision, $decision === 'Rejected' ? $reason : null, $userId, $entityId]);
            } elseif ($entityTable === 'competency_signoffs') {
                $eu = $db->prepare(
                    "UPDATE competency_signoffs
                     SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                     WHERE id = ?"
                );
                $eu->execute([$decision, $decision === 'Rejected' ? $reason : null, $userId, $entityId]);
            }

            notifyRequester(
                $db,
                (int)$rq['submitted_by'],
                $userId,
                (int)$rq['branch_id'],
                'Request ' . $decision,
                $decision === 'Rejected'
                    ? ('Your ' . $rq['request_type'] . ' request was rejected: ' . $reason)
                    : ('Your ' . $rq['request_type'] . ' request was approved.'),
                'instructor_approval_dashboard.php'
            );

            $db->commit();
        } catch (Throwable $ex) {
            $db->rollBack();
            throw $ex;
        }

        jsonOut(['success' => true, 'message' => 'Request reviewed successfully']);
    }

    if ($action === 'notifications') {
        $stmt = $db->prepare(
            "SELECT id, title, message, link_url, is_read, created_at
             FROM approval_notifications
             WHERE recipient_user_id = ?
             ORDER BY created_at DESC
             LIMIT 30"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unread = 0;
        foreach ($rows as $r) {
            if ((int)$r['is_read'] === 0) $unread++;
        }
        jsonOut(['success' => true, 'unread_count' => $unread, 'data' => $rows]);
    }

    if ($action === 'mark_notification_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Notification ID required'], 422);
        $stmt = $db->prepare("UPDATE approval_notifications SET is_read = 1 WHERE id = ? AND recipient_user_id = ?");
        $stmt->execute([$id, $userId]);
        jsonOut(['success' => true]);
    }

    jsonOut(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
