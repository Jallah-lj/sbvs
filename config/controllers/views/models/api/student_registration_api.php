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
require_once '../../../../EmailService.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$actorId       = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isAllowed     = in_array($role, ['Super Admin', 'Branch Admin', 'Admin'], true);

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'bootstrap';

function ensureLockedFeeColumn(PDO $db): void {
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

ensureLockedFeeColumn($db);

function genStudentCode(PDO $db): string {
    $year = date('Y');
    do {
        $code = 'VS-' . $year . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $s = $db->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $s->execute([$code]);
        $exists = (bool)$s->fetchColumn();
    } while ($exists);
    return $code;
}

function genReceiptNo(PDO $db): string {
    do {
        $no = 'RCP-' . date('Ymd') . '-' . random_int(1000, 9999);
        $s = $db->prepare("SELECT id FROM payments WHERE receipt_no = ? LIMIT 1");
        $s->execute([$no]);
        $exists = (bool)$s->fetchColumn();
    } while ($exists);
    return $no;
}

switch ($action) {
    case 'bootstrap':
        $out = [
            'student_id' => genStudentCode($db),
            'branches' => [],
            'is_super_admin' => $isSuperAdmin,
            'session_branch' => $sessionBranch
        ];

        if ($isSuperAdmin) {
            $out['branches'] = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                                 ->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($sessionBranch) {
            $s = $db->prepare("SELECT id, name FROM branches WHERE id = ? LIMIT 1");
            $s->execute([$sessionBranch]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) $out['branches'][] = $row;
        }

        echo json_encode(['success' => true, 'data' => $out]);
        break;

    case 'courses':
        $branchId = (int)($_GET['branch_id'] ?? 0);
        if (!$isSuperAdmin) $branchId = $sessionBranch;
        if (!$branchId) {
            echo json_encode(['success' => false, 'message' => 'Branch is required', 'data' => []]);
            break;
        }

        $stmt = $db->prepare("SELECT id, name AS course_name, duration, fees AS fee, branch_id
                             FROM courses WHERE branch_id = ? ORDER BY name");
        $stmt->execute([$branchId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'batches':
        $courseId = (int)($_GET['course_id'] ?? 0);
        if (!$courseId) {
            echo json_encode(['success' => false, 'message' => 'Course is required', 'data' => []]);
            break;
        }

        $c = $db->prepare("SELECT id, branch_id FROM courses WHERE id = ? LIMIT 1");
        $c->execute([$courseId]);
        $cRow = $c->fetch(PDO::FETCH_ASSOC);
        if (!$cRow) {
            echo json_encode(['success' => false, 'message' => 'Course not found', 'data' => []]);
            break;
        }
        if (!$isSuperAdmin && (int)$cRow['branch_id'] !== $sessionBranch) {
            echo json_encode(['success' => false, 'message' => 'Access denied', 'data' => []]);
            break;
        }

        $stmt = $db->prepare("SELECT id, name, start_date, end_date FROM batches
                             WHERE course_id = ? ORDER BY start_date DESC, id DESC");
        $stmt->execute([$courseId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }

        $studentCode    = trim($_POST['student_id'] ?? '');
        $firstName      = trim($_POST['first_name'] ?? '');
        $lastName       = trim($_POST['last_name'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $dob            = trim($_POST['dob'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $branchId       = (int)($_POST['branch_id'] ?? 0);
        $courseId       = (int)($_POST['course_id'] ?? 0);
        $batchId        = (int)($_POST['batch_id'] ?? 0);
        $amountPaidRaw  = (float)($_POST['amount_paid'] ?? 0);
        $paymentMethod  = trim($_POST['payment_method'] ?? 'Cash');
        $enrollDate     = trim($_POST['enrollment_date'] ?? date('Y-m-d'));

        if (!$isSuperAdmin) $branchId = $sessionBranch;

        if ($studentCode === '' || $firstName === '' || $lastName === '' || $gender === '' || $dob === '' || !$branchId || !$courseId) {
            echo json_encode(['success' => false, 'message' => 'Please complete all required fields']);
            break;
        }

        $hasEmail = ($email !== '');
        if ($hasEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            break;
        }

        if (!in_array($gender, ['Male', 'Female'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid gender']);
            break;
        }

        $fullName = trim($firstName . ' ' . $lastName);

        try {
            $db->beginTransaction();

            // Uniqueness checks
            if ($hasEmail) {
                $eChk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $eChk->execute([$email]);
                if ($eChk->fetch()) {
                    throw new Exception('Email already exists');
                }
            }

            $sidChk = $db->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
            $sidChk->execute([$studentCode]);
            if ($sidChk->fetch()) {
                $studentCode = genStudentCode($db);
            }

            $emailForAccount = $hasEmail
                ? strtolower($email)
                : strtolower($studentCode) . '@no-email.local';

            // Validate course + branch
            $c = $db->prepare("SELECT id, branch_id, fees, duration, name FROM courses WHERE id = ? LIMIT 1");
            $c->execute([$courseId]);
            $course = $c->fetch(PDO::FETCH_ASSOC);
            if (!$course) throw new Exception('Course not found');
            if ((int)$course['branch_id'] !== $branchId) throw new Exception('Course does not belong to selected branch');

            // Batch is optional from UI; auto-select latest batch if not provided
            if ($batchId > 0) {
                $b = $db->prepare("SELECT id, course_id FROM batches WHERE id = ? LIMIT 1");
                $b->execute([$batchId]);
                $batch = $b->fetch(PDO::FETCH_ASSOC);
                if (!$batch || (int)$batch['course_id'] !== $courseId) {
                    throw new Exception('Invalid batch selected');
                }
            } else {
                $b = $db->prepare("SELECT id FROM batches WHERE course_id = ? ORDER BY start_date DESC, id DESC LIMIT 1");
                $b->execute([$courseId]);
                $batchId = (int)$b->fetchColumn();
                if (!$batchId) {
                    // Auto-create default batch if course has none
                    $autoBatchName = 'Auto Batch ' . date('Ymd-His');
                    $autoStart     = $enrollDate ?: date('Y-m-d');
                    $autoEnd       = date('Y-m-d', strtotime($autoStart . ' +6 months'));
                    $bCreate = $db->prepare(
                        "INSERT INTO batches (branch_id, course_id, name, start_date, end_date)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    $bCreate->execute([(int)$course['branch_id'], $courseId, $autoBatchName, $autoStart, $autoEnd]);
                    $batchId = (int)$db->lastInsertId();
                }
            }

            $totalFee = (float)$course['fees'];
            $amountPaid = max(0, round($amountPaidRaw, 2));
            if ($amountPaid > $totalFee) {
                throw new Exception('Amount paid cannot exceed total fee');
            }
            $balance = max(0, round($totalFee - $amountPaid, 2));

            // Create user
            $u = $db->prepare("INSERT INTO users (branch_id, name, email, password_hash, role, status)
                               VALUES (?, ?, ?, ?, 'Student', 'Active')");
            $u->execute([$branchId, $fullName, $emailForAccount, password_hash($dob, PASSWORD_DEFAULT)]);
            $userId = (int)$db->lastInsertId();

            // Create student
            $s = $db->prepare("INSERT INTO students
                               (user_id, branch_id, student_id, gender, dob, phone, address, registration_date)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $s->execute([$userId, $branchId, $studentCode, $gender, $dob, $phone ?: null, $address ?: null, date('Y-m-d')]);
            $studentId = (int)$db->lastInsertId();

            // Enrollment
            $en = $db->prepare("INSERT INTO enrollments (student_id, course_id, batch_id, locked_fee, enrollment_date, status)
                                VALUES (?, ?, ?, ?, ?, 'Active')");
            $en->execute([$studentId, $courseId, $batchId, $totalFee, $enrollDate]);
            $enrollmentId = (int)$db->lastInsertId();

            $paymentId = null;
            $receiptNo = null;

            // Optional payment at registration
            if ($amountPaid > 0) {
                $receiptNo = genReceiptNo($db);
                $payType = ($balance <= 0.009) ? 'Full' : 'Partial';
                $payNotes = 'Registration payment | total_fee=' . number_format($totalFee, 2, '.', '')
                          . ' | balance=' . number_format($balance, 2, '.', '')
                          . ' | recorded_by=' . $actorId;

                $p = $db->prepare("INSERT INTO payments
                                  (branch_id, student_id, enrollment_id, amount, payment_method, payment_type,
                                   receipt_no, notes, payment_date, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Active')");
                $p->execute([$branchId, $studentId, $enrollmentId, $amountPaid, $paymentMethod, $payType, $receiptNo, $payNotes]);
                $paymentId = (int)$db->lastInsertId();
            }

            $db->commit();

            // Fire off background emails silently (don't fail registration if mail() fails)
            try {
                if ($hasEmail) {
                    $emailService = new EmailService();
                    $emailService->sendWelcomeEmail($email, $fullName, $studentCode, $course['name'], $email, $dob);

                    if ($amountPaid > 0 && $receiptNo) {
                        $emailService->sendPaymentReceipt($email, $fullName, $receiptNo, $amountPaid, $paymentMethod, $balance, $course['name']);
                    }
                }
            } catch (Exception $emailEx) {
                // Ignore email failure to ensure registration completes
            }

            echo json_encode([
                'success' => true,
                'message' => 'Student registered successfully',
                'data' => [
                    'student_id' => $studentId,
                    'student_code' => $studentCode,
                    'enrollment_id' => $enrollmentId,
                    'payment_id' => $paymentId,
                    'receipt_no' => $receiptNo,
                    'total_fee' => $totalFee,
                    'amount_paid' => $amountPaid,
                    'balance' => $balance,
                    'course_name' => $course['name'],
                    'course_duration' => $course['duration']
                ]
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
