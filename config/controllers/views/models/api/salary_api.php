<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once '../../../../database.php';
$db = (new Database())->getConnection();

header('Content-Type: application/json');

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$userId        = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function branchScope(bool $isSuperAdmin, int $sessionBranch, string $col = 'branch_id'): array
{
    if ($isSuperAdmin) return ['', []];
    return [" AND {$col} = ?", [$sessionBranch]];
}

/**
 * Calculate a single employee's payslip lines given their basic_salary,
 * an optional array of override values [component_id => value],
 * and the role they hold.
 * Returns [gross, total_deductions, net, lines[]]
 */
function calculatePayslip(PDO $db, float $basic, string $empRole, array $overrides = []): array
{
    $stmt = $db->prepare(
        "SELECT * FROM salary_components
         WHERE status = 'Active'
           AND (applies_to = 'All' OR applies_to = ?)
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute([$empRole]);
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $earnings   = ['BASIC' => $basic];  // code => resolved amount
    $lines      = [];
    $gross      = $basic;
    $deductions = 0.0;

    // First pass: earnings
    foreach ($components as $c) {
        if ($c['type'] !== 'Earning') continue;
        $val = isset($overrides[$c['id']]) ? (float)$overrides[$c['id']] : (float)$c['value'];
        if ($c['calc_type'] === 'Percentage') {
            $base = ($c['percentage_of'] === 'basic_salary')
                  ? $basic
                  : ($earnings[$c['percentage_of']] ?? $basic);
            $val = round($base * $val / 100, 2);
        }
        $earnings[$c['code']] = $val;
        $gross += $val;
        $lines[] = ['component_id' => (int)$c['id'], 'component_name' => $c['name'],
                    'component_code' => $c['code'], 'component_type' => 'Earning', 'amount' => $val];
    }

    // Second pass: deductions & tax
    foreach ($components as $c) {
        if ($c['type'] === 'Earning') continue;
        $val = isset($overrides[$c['id']]) ? (float)$overrides[$c['id']] : (float)$c['value'];
        if ($c['calc_type'] === 'Percentage') {
            $base = ($c['percentage_of'] === 'basic_salary')
                  ? $basic
                  : (($c['percentage_of'] === 'gross_salary') ? $gross : ($earnings[$c['percentage_of']] ?? $basic));
            $val = round($base * $val / 100, 2);
        }
        $deductions += $val;
        $lines[] = ['component_id' => (int)$c['id'], 'component_name' => $c['name'],
                    'component_code' => $c['code'], 'component_type' => $c['type'], 'amount' => $val];
    }

    $net = round($gross - $deductions, 2);
    return [round($gross, 2), round($deductions, 2), $net, $lines];
}

// ─────────────────────────────────────────────────────────────────────────────
// STATS / KPI
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'stats') {
    [$w, $p] = branchScope($isSuperAdmin, $sessionBranch, 'esp.branch_id');
    $stmt = $db->prepare(
        "SELECT
            COUNT(DISTINCT esp.user_id)                              AS profiled_employees,
            COALESCE(SUM(esp.basic_salary), 0)                      AS total_basic_budget,
            (SELECT COUNT(*) FROM payroll_runs pr WHERE pr.status = 'Processed' {$w}) AS pending_payment_runs,
            (SELECT COALESCE(SUM(ps.net_salary),0)
             FROM payroll_slips ps WHERE ps.status = 'Pending'
             AND ps.branch_id = " . ($isSuperAdmin ? 'ps.branch_id' : '?') . ") AS pending_net_payout,
            (SELECT COALESCE(SUM(ps.net_salary),0)
             FROM payroll_slips ps WHERE ps.status = 'Paid'
             AND MONTH(ps.created_at)=MONTH(CURDATE())
             AND YEAR(ps.created_at)=YEAR(CURDATE())
             AND ps.branch_id = " . ($isSuperAdmin ? 'ps.branch_id' : '?') . ") AS paid_this_month
         FROM employee_salary_profiles esp
         WHERE esp.status = 'Active' {$w}"
    );
    $params = $isSuperAdmin ? [] : [$sessionBranch, $sessionBranch, $sessionBranch];
    // Rebuild with proper param count
    if ($isSuperAdmin) {
        $stmt = $db->query(
            "SELECT
                COUNT(DISTINCT esp.user_id) AS profiled_employees,
                COALESCE(SUM(esp.basic_salary),0) AS total_basic_budget,
                (SELECT COUNT(*) FROM payroll_runs pr WHERE pr.status='Processed') AS pending_payment_runs,
                (SELECT COALESCE(SUM(ps.net_salary),0) FROM payroll_slips ps WHERE ps.status='Pending') AS pending_net_payout,
                (SELECT COALESCE(SUM(ps.net_salary),0) FROM payroll_slips ps
                 WHERE ps.status='Paid' AND MONTH(ps.created_at)=MONTH(CURDATE()) AND YEAR(ps.created_at)=YEAR(CURDATE())) AS paid_this_month
             FROM employee_salary_profiles esp WHERE esp.status='Active'"
        );
    } else {
        $stmt = $db->prepare(
            "SELECT
                COUNT(DISTINCT esp.user_id) AS profiled_employees,
                COALESCE(SUM(esp.basic_salary),0) AS total_basic_budget,
                (SELECT COUNT(*) FROM payroll_runs pr WHERE pr.status='Processed' AND pr.branch_id=?) AS pending_payment_runs,
                (SELECT COALESCE(SUM(ps.net_salary),0) FROM payroll_slips ps WHERE ps.status='Pending' AND ps.branch_id=?) AS pending_net_payout,
                (SELECT COALESCE(SUM(ps.net_salary),0) FROM payroll_slips ps
                 WHERE ps.status='Paid' AND ps.branch_id=? AND MONTH(ps.created_at)=MONTH(CURDATE()) AND YEAR(ps.created_at)=YEAR(CURDATE())) AS paid_this_month
             FROM employee_salary_profiles esp WHERE esp.status='Active' AND esp.branch_id=?"
        );
        $stmt->execute([$sessionBranch, $sessionBranch, $sessionBranch, $sessionBranch]);
    }
    if ($isSuperAdmin) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// SALARY GRADES  (SA only for write; all can read)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'grades_list') {
    $stmt = $db->query("SELECT * FROM salary_grades ORDER BY level ASC, name ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
if ($action === 'grade_save') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $name    = trim($_POST['name'] ?? '');
    $level   = (int)($_POST['level'] ?? 1);
    $minSal  = (float)($_POST['min_salary'] ?? 0);
    $maxSal  = (float)($_POST['max_salary'] ?? 0);
    $desc    = trim($_POST['description'] ?? '');
    $status  = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
    if (!$name || $minSal < 0 || $maxSal < $minSal) {
        echo json_encode(['success'=>false,'message'=>'Valid name and salary range required.']); exit;
    }
    $db->prepare("INSERT INTO salary_grades (name,level,min_salary,max_salary,description,status) VALUES (?,?,?,?,?,?)")
       ->execute([$name, $level, $minSal, $maxSal, $desc ?: null, $status]);
    echo json_encode(['success'=>true,'message'=>"Grade '{$name}' created.",'id'=>(int)$db->lastInsertId()]);
    exit;
}
if ($action === 'grade_update') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $level   = (int)($_POST['level'] ?? 1);
    $minSal  = (float)($_POST['min_salary'] ?? 0);
    $maxSal  = (float)($_POST['max_salary'] ?? 0);
    $desc    = trim($_POST['description'] ?? '');
    $status  = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
    if (!$id || !$name || $maxSal < $minSal) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }
    $db->prepare("UPDATE salary_grades SET name=?,level=?,min_salary=?,max_salary=?,description=?,status=? WHERE id=?")
       ->execute([$name, $level, $minSal, $maxSal, $desc ?: null, $status, $id]);
    echo json_encode(['success'=>true,'message'=>"Grade updated."]);
    exit;
}
if ($action === 'grade_delete') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    $cnt = (int)$db->prepare("SELECT COUNT(*) FROM employee_salary_profiles WHERE grade_id=?")->execute([$id]) ? (int)$db->query("SELECT COUNT(*) FROM employee_salary_profiles WHERE grade_id={$id}")->fetchColumn() : 0;
    $chk = $db->prepare("SELECT COUNT(*) FROM employee_salary_profiles WHERE grade_id=?"); $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0) { echo json_encode(['success'=>false,'message'=>'Cannot delete — employees are assigned to this grade.']); exit; }
    $db->prepare("DELETE FROM salary_grades WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true,'message'=>'Grade deleted.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// SALARY COMPONENTS  (SA only for write; all can read)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'components_list') {
    $stmt = $db->query("SELECT * FROM salary_components ORDER BY sort_order ASC, type ASC, name ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
if ($action === 'component_save') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $name       = trim($_POST['name'] ?? '');
    $code       = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', strtoupper(trim($_POST['code'] ?? ''))));
    $type       = in_array($_POST['type'] ?? '', ['Earning','Deduction','Tax']) ? $_POST['type'] : 'Earning';
    $calcType   = in_array($_POST['calc_type'] ?? '', ['Fixed','Percentage']) ? $_POST['calc_type'] : 'Fixed';
    $value      = (float)($_POST['value'] ?? 0);
    $pctOf      = trim($_POST['percentage_of'] ?? '');
    $taxable    = isset($_POST['taxable']) ? 1 : 0;
    $mandatory  = isset($_POST['is_mandatory']) ? 1 : 0;
    $appliesTo  = in_array($_POST['applies_to'] ?? '', ['All','Teacher','Admin','Branch Admin','Super Admin']) ? $_POST['applies_to'] : 'All';
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $status     = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
    if (!$name || !$code) { echo json_encode(['success'=>false,'message'=>'Name and code are required.']); exit; }
    try {
        $db->prepare("INSERT INTO salary_components (name,code,type,calc_type,value,percentage_of,taxable,is_mandatory,applies_to,sort_order,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name,$code,$type,$calcType,$value,$pctOf ?: null,$taxable,$mandatory,$appliesTo,$sortOrder,$status]);
        echo json_encode(['success'=>true,'message'=>"Component '{$name}' created.",'id'=>(int)$db->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Code already exists or DB error: '.$e->getMessage()]);
    }
    exit;
}
if ($action === 'component_update') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $id         = (int)($_POST['id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $code       = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', strtoupper(trim($_POST['code'] ?? ''))));
    $type       = in_array($_POST['type'] ?? '', ['Earning','Deduction','Tax']) ? $_POST['type'] : 'Earning';
    $calcType   = in_array($_POST['calc_type'] ?? '', ['Fixed','Percentage']) ? $_POST['calc_type'] : 'Fixed';
    $value      = (float)($_POST['value'] ?? 0);
    $pctOf      = trim($_POST['percentage_of'] ?? '');
    $taxable    = isset($_POST['taxable']) ? 1 : 0;
    $mandatory  = isset($_POST['is_mandatory']) ? 1 : 0;
    $appliesTo  = in_array($_POST['applies_to'] ?? '', ['All','Teacher','Admin','Branch Admin','Super Admin']) ? $_POST['applies_to'] : 'All';
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $status     = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
    if (!$id || !$name) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }
    $db->prepare("UPDATE salary_components SET name=?,code=?,type=?,calc_type=?,value=?,percentage_of=?,taxable=?,is_mandatory=?,applies_to=?,sort_order=?,status=? WHERE id=?")
       ->execute([$name,$code,$type,$calcType,$value,$pctOf ?: null,$taxable,$mandatory,$appliesTo,$sortOrder,$status,$id]);
    echo json_encode(['success'=>true,'message'=>'Component updated.']);
    exit;
}
if ($action === 'component_delete') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM salary_components WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true,'message'=>'Component deleted.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// EMPLOYEE SALARY PROFILES
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'profiles_list') {
    [$w, $p] = branchScope($isSuperAdmin, $sessionBranch, 'esp.branch_id');
    $stmt = $db->prepare(
        "SELECT esp.id, esp.user_id, esp.branch_id, esp.grade_id, esp.basic_salary,
                esp.effective_date, esp.bank_name, esp.account_number,
                esp.payment_mode, esp.status, esp.notes,
                u.name AS employee_name, u.email, u.role AS employee_role,
                b.name AS branch_name,
                sg.name AS grade_name, sg.level AS grade_level
         FROM employee_salary_profiles esp
         JOIN users u ON esp.user_id = u.id
         JOIN branches b ON esp.branch_id = b.id
         LEFT JOIN salary_grades sg ON esp.grade_id = sg.id
         WHERE 1=1 {$w}
         ORDER BY b.name, u.name"
    );
    $stmt->execute($p);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
if ($action === 'profile_get') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare(
        "SELECT esp.*, u.name AS employee_name, u.role AS employee_role,
                sg.name AS grade_name
         FROM employee_salary_profiles esp
         JOIN users u ON esp.user_id = u.id
         LEFT JOIN salary_grades sg ON esp.grade_id = sg.id
         WHERE esp.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Profile not found']); exit; }
    if (!$isSuperAdmin && (int)$row['branch_id'] !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
    // Load overrides
    $ovStmt = $db->prepare("SELECT component_id, override_value FROM employee_salary_overrides WHERE profile_id=?");
    $ovStmt->execute([$id]);
    $row['overrides'] = $ovStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    // Compute preview
    $overrides = array_map('floatval', $row['overrides']);
    [$gross, $ded, $net, $lines] = calculatePayslip($db, (float)$row['basic_salary'], $row['employee_role'], $overrides);
    $row['preview'] = ['gross'=>$gross,'deductions'=>$ded,'net'=>$net,'lines'=>$lines];
    echo json_encode(['success'=>true,'data'=>$row]);
    exit;
}
if ($action === 'profile_save' || $action === 'profile_update') {
    $isUpdate   = ($action === 'profile_update');
    $profileId  = $isUpdate ? (int)($_POST['id'] ?? 0) : 0;
    $empUserId  = (int)($_POST['user_id'] ?? 0);
    $gradeId    = (int)($_POST['grade_id'] ?? 0) ?: null;
    $basic      = (float)($_POST['basic_salary'] ?? 0);
    $effDate    = trim($_POST['effective_date'] ?? date('Y-m-d'));
    $bankName   = trim($_POST['bank_name'] ?? '');
    $acctNo     = trim($_POST['account_number'] ?? '');
    $payMode    = in_array($_POST['payment_mode'] ?? '', ['Bank Transfer','Cash','Mobile Money','Cheque'])
                  ? $_POST['payment_mode'] : 'Cash';
    $status     = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
    $notes      = trim($_POST['notes'] ?? '');
    $overridesRaw = json_decode($_POST['overrides'] ?? '{}', true) ?: [];

    if (!$empUserId || $basic <= 0) {
        echo json_encode(['success'=>false,'message'=>'Employee and basic salary are required.']); exit;
    }
    // Determine branch
    $uStmt = $db->prepare("SELECT branch_id, role FROM users WHERE id=?"); $uStmt->execute([$empUserId]);
    $uRow  = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$uRow) { echo json_encode(['success'=>false,'message'=>'Employee not found.']); exit; }
    $branchId = (int)($uRow['branch_id'] ?? ($isSuperAdmin ? (int)($_POST['branch_id'] ?? 0) : $sessionBranch));
    if (!$branchId) { echo json_encode(['success'=>false,'message'=>'Branch could not be determined.']); exit; }
    if (!$isSuperAdmin && $branchId !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }

    $db->beginTransaction();
    try {
        if ($isUpdate) {
            if (!$profileId) throw new Exception('Profile ID required.');
            $db->prepare("UPDATE employee_salary_profiles SET grade_id=?,basic_salary=?,effective_date=?,bank_name=?,account_number=?,payment_mode=?,status=?,notes=? WHERE id=?")
               ->execute([$gradeId,$basic,$effDate,$bankName ?: null,$acctNo ?: null,$payMode,$status,$notes ?: null,$profileId]);
        } else {
            // Deactivate previous active profile for this user
            $db->prepare("UPDATE employee_salary_profiles SET status='Inactive' WHERE user_id=? AND status='Active'")->execute([$empUserId]);
            $db->prepare("INSERT INTO employee_salary_profiles (user_id,branch_id,grade_id,basic_salary,effective_date,bank_name,account_number,payment_mode,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$empUserId,$branchId,$gradeId,$basic,$effDate,$bankName ?: null,$acctNo ?: null,$payMode,$status,$notes ?: null,$userId]);
            $profileId = (int)$db->lastInsertId();
        }
        // Save overrides
        $db->prepare("DELETE FROM employee_salary_overrides WHERE profile_id=?")->execute([$profileId]);
        foreach ($overridesRaw as $compId => $val) {
            if ((float)$val >= 0) {
                $db->prepare("INSERT INTO employee_salary_overrides (profile_id,component_id,override_value) VALUES (?,?,?)")
                   ->execute([$profileId,(int)$compId,(float)$val]);
            }
        }
        $db->commit();
        echo json_encode(['success'=>true,'message'=>'Salary profile saved.','id'=>$profileId]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
    }
    exit;
}
if ($action === 'profile_delete') {
    $id = (int)($_POST['id'] ?? 0);
    $chk = $db->prepare("SELECT branch_id FROM employee_salary_profiles WHERE id=?"); $chk->execute([$id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Profile not found']); exit; }
    if (!$isSuperAdmin && (int)$row['branch_id'] !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
    // Block if used in any payslip
    $usedStmt = $db->prepare("SELECT COUNT(*) FROM payroll_slips WHERE user_id=(SELECT user_id FROM employee_salary_profiles WHERE id=?) AND status='Paid'");
    $usedStmt->execute([$id]);
    if ((int)$usedStmt->fetchColumn() > 0) {
        echo json_encode(['success'=>false,'message'=>'Cannot delete — employee has paid payslips on record.']); exit;
    }
    $db->prepare("DELETE FROM employee_salary_profiles WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true,'message'=>'Profile deleted.']);
    exit;
}

// List employees (users with roles Teacher/Admin/Branch Admin) for profile assignment
if ($action === 'employees_list') {
    [$w, $p] = branchScope($isSuperAdmin, $sessionBranch, 'u.branch_id');
    $stmt = $db->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.branch_id, u.status,
                b.name AS branch_name,
                (SELECT esp.id FROM employee_salary_profiles esp
                 WHERE esp.user_id = u.id AND esp.status='Active' LIMIT 1) AS profile_id,
                (SELECT esp.basic_salary FROM employee_salary_profiles esp
                 WHERE esp.user_id = u.id AND esp.status='Active' LIMIT 1) AS basic_salary
         FROM users u
         LEFT JOIN branches b ON u.branch_id = b.id
         WHERE u.role IN ('Teacher','Admin','Branch Admin','Super Admin')
           AND u.status = 'Active' {$w}
         ORDER BY b.name, u.role, u.name"
    );
    $stmt->execute($p);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Preview salary calculation (AJAX before saving)
if ($action === 'preview_salary') {
    $basic     = (float)($_POST['basic_salary'] ?? 0);
    $empUserId = (int)($_POST['user_id'] ?? 0);
    $overrides = json_decode($_POST['overrides'] ?? '{}', true) ?: [];
    $uStmt = $db->prepare("SELECT role FROM users WHERE id=?"); $uStmt->execute([$empUserId]);
    $uRow  = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$uRow || $basic <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid input']); exit; }
    [$gross, $ded, $net, $lines] = calculatePayslip($db, $basic, $uRow['role'], array_map('floatval', $overrides));
    echo json_encode(['success'=>true,'basic'=>$basic,'gross'=>$gross,'deductions'=>$ded,'net'=>$net,'lines'=>$lines]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PAYROLL RUNS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'runs_list') {
    [$w, $p] = branchScope($isSuperAdmin, $sessionBranch, 'pr.branch_id');
    $stmt = $db->prepare(
        "SELECT pr.*, b.name AS branch_name,
                u.name AS processed_by_name,
                (SELECT COUNT(*) FROM payroll_slips ps WHERE ps.run_id = pr.id) AS slip_count
         FROM payroll_runs pr
         LEFT JOIN branches b ON pr.branch_id = b.id
         LEFT JOIN users u ON pr.processed_by = u.id
         WHERE 1=1 {$w}
         ORDER BY pr.pay_period_year DESC, pr.pay_period_month DESC, pr.id DESC"
    );
    $stmt->execute($p);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'run_create') {
    $month   = (int)($_POST['month'] ?? 0);
    $year    = (int)($_POST['year'] ?? 0);
    $payDate = trim($_POST['pay_date'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    $brId    = $isSuperAdmin ? ((int)($_POST['branch_id'] ?? 0) ?: null) : $sessionBranch;
    if ($month < 1 || $month > 12 || $year < 2000 || !$payDate) {
        echo json_encode(['success'=>false,'message'=>'Valid month, year, and pay date are required.']); exit;
    }
    // Duplicate check
    $dupStmt = $db->prepare("SELECT id FROM payroll_runs WHERE branch_id<=>? AND pay_period_month=? AND pay_period_year=?");
    $dupStmt->execute([$brId, $month, $year]);
    if ($dupStmt->fetch()) {
        echo json_encode(['success'=>false,'message'=>'A payroll run for this period already exists.']); exit;
    }
    $db->prepare("INSERT INTO payroll_runs (branch_id,pay_period_month,pay_period_year,pay_date,status,notes,created_by) VALUES (?,?,?,?,'Draft',?,?)")
       ->execute([$brId,$month,$year,$payDate,$notes ?: null,$userId]);
    echo json_encode(['success'=>true,'message'=>'Payroll run created (Draft).','id'=>(int)$db->lastInsertId()]);
    exit;
}

if ($action === 'run_process') {
    $runId = (int)($_POST['run_id'] ?? 0);
    $runStmt = $db->prepare("SELECT * FROM payroll_runs WHERE id=?"); $runStmt->execute([$runId]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC);
    if (!$run) { echo json_encode(['success'=>false,'message'=>'Run not found']); exit; }
    if (!$isSuperAdmin && $run['branch_id'] && (int)$run['branch_id'] !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
    if ($run['status'] !== 'Draft') {
        echo json_encode(['success'=>false,'message'=>"Run is already {$run['status']} and cannot be re-processed."]); exit;
    }

    // Fetch all active profiles for this branch (or all if SA global run)
    $profWhere = $run['branch_id'] ? "AND esp.branch_id = {$run['branch_id']}" : '';
    $profStmt = $db->query(
        "SELECT esp.*, u.role AS employee_role, u.name AS employee_name,
                u.branch_id AS u_branch_id
         FROM employee_salary_profiles esp
         JOIN users u ON esp.user_id = u.id
         WHERE esp.status='Active' {$profWhere}"
    );
    $profiles = $profStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($profiles)) {
        echo json_encode(['success'=>false,'message'=>'No active salary profiles found for this run scope.']); exit;
    }

    $db->beginTransaction();
    try {
        // Remove any previous slips for this run (re-process guard)
        $db->prepare("DELETE FROM payroll_slips WHERE run_id=?")->execute([$runId]);

        $totalGross = $totalDed = $totalNet = 0.0;

        foreach ($profiles as $prof) {
            $ovStmt = $db->prepare("SELECT component_id, override_value FROM employee_salary_overrides WHERE profile_id=?");
            $ovStmt->execute([$prof['id']]);
            $overrides = array_map('floatval', $ovStmt->fetchAll(PDO::FETCH_KEY_PAIR));
            [$gross, $ded, $net, $lines] = calculatePayslip($db, (float)$prof['basic_salary'], $prof['employee_role'], $overrides);

            $branchId = (int)($prof['branch_id'] ?? $prof['u_branch_id']);
            $db->prepare(
                "INSERT INTO payroll_slips (run_id,user_id,branch_id,grade_id,basic_salary,gross_salary,total_deductions,net_salary,payment_mode,bank_name,account_number,status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,'Pending')"
            )->execute([$runId,$prof['user_id'],$branchId,$prof['grade_id'],$prof['basic_salary'],$gross,$ded,$net,$prof['payment_mode'],$prof['bank_name'],$prof['account_number']]);
            $slipId = (int)$db->lastInsertId();

            foreach ($lines as $line) {
                $db->prepare("INSERT INTO payroll_slip_lines (slip_id,component_id,component_name,component_code,component_type,amount) VALUES (?,?,?,?,?,?)")
                   ->execute([$slipId,$line['component_id'],$line['component_name'],$line['component_code'],$line['component_type'],$line['amount']]);
            }
            $totalGross += $gross;
            $totalDed   += $ded;
            $totalNet   += $net;
        }

        $db->prepare("UPDATE payroll_runs SET status='Processed',total_gross=?,total_deductions=?,total_net=?,processed_by=?,processed_at=NOW() WHERE id=?")
           ->execute([$totalGross,$totalDed,$totalNet,$userId,$runId]);

        $db->commit();
        echo json_encode(['success'=>true,'message'=>'Payroll processed successfully.',
            'slip_count'=>count($profiles), 'total_net'=>round($totalNet,2)]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success'=>false,'message'=>'Processing failed: '.$e->getMessage()]);
    }
    exit;
}

if ($action === 'run_mark_paid') {
    if (!$isSuperAdmin && !$isBranchAdmin) { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
    $runId = (int)($_POST['run_id'] ?? 0);
    $runStmt = $db->prepare("SELECT * FROM payroll_runs WHERE id=?"); $runStmt->execute([$runId]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC);
    if (!$run || $run['status'] !== 'Processed') {
        echo json_encode(['success'=>false,'message'=>'Run must be in Processed status to mark as Paid.']); exit;
    }
    if (!$isSuperAdmin && $run['branch_id'] && (int)$run['branch_id'] !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
    $db->prepare("UPDATE payroll_runs SET status='Paid' WHERE id=?")->execute([$runId]);
    $db->prepare("UPDATE payroll_slips SET status='Paid' WHERE run_id=? AND status='Pending'")->execute([$runId]);
    echo json_encode(['success'=>true,'message'=>'Payroll run marked as Paid. All slips updated.']);
    exit;
}

if ($action === 'run_void') {
    if (!$isSuperAdmin) { echo json_encode(['success'=>false,'message'=>'Super Admin only']); exit; }
    $runId  = (int)($_POST['run_id'] ?? 0);
    $reason = trim($_POST['void_reason'] ?? '');
    if (!$reason) { echo json_encode(['success'=>false,'message'=>'Void reason is required.']); exit; }
    $runStmt = $db->prepare("SELECT * FROM payroll_runs WHERE id=?"); $runStmt->execute([$runId]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC);
    if (!$run || $run['status'] === 'Voided') {
        echo json_encode(['success'=>false,'message'=>'Run not found or already voided.']); exit;
    }
    $db->prepare("UPDATE payroll_runs SET status='Voided',voided_by=?,void_reason=?,voided_at=NOW() WHERE id=?")->execute([$userId,$reason,$runId]);
    $db->prepare("UPDATE payroll_slips SET status='Voided' WHERE run_id=?")->execute([$runId]);
    echo json_encode(['success'=>true,'message'=>'Payroll run voided.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PAYSLIPS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'slips_list') {
    $runId = (int)($_GET['run_id'] ?? 0);
    if (!$runId) { echo json_encode(['success'=>false,'message'=>'run_id required']); exit; }
    [$w, $p] = branchScope($isSuperAdmin, $sessionBranch, 'ps.branch_id');
    $stmt = $db->prepare(
        "SELECT ps.*, u.name AS employee_name, u.email, u.role AS employee_role,
                b.name AS branch_name, sg.name AS grade_name
         FROM payroll_slips ps
         JOIN users u ON ps.user_id = u.id
         JOIN branches b ON ps.branch_id = b.id
         LEFT JOIN salary_grades sg ON ps.grade_id = sg.id
         WHERE ps.run_id = ? {$w}
         ORDER BY b.name, u.name"
    );
    array_unshift($p, $runId);
    $stmt->execute($p);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'slip_detail') {
    $slipId = (int)($_GET['slip_id'] ?? 0);
    $stmt = $db->prepare(
        "SELECT ps.*, u.name AS employee_name, u.email, u.role AS employee_role,
                b.name AS branch_name, b.address AS branch_address, b.phone AS branch_phone, b.email AS branch_email,
                sg.name AS grade_name, sg.level AS grade_level,
                pr.pay_period_month, pr.pay_period_year, pr.pay_date,
                COALESCE(t.teacher_id, '') AS teacher_id
         FROM payroll_slips ps
         JOIN payroll_runs pr ON ps.run_id = pr.id
         JOIN users u ON ps.user_id = u.id
         JOIN branches b ON ps.branch_id = b.id
         LEFT JOIN salary_grades sg ON ps.grade_id = sg.id
         LEFT JOIN teachers t ON t.user_id = ps.user_id
         WHERE ps.id = ?"
    );
    $stmt->execute([$slipId]);
    $slip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$slip) { echo json_encode(['success'=>false,'message'=>'Slip not found']); exit; }
    if (!$isSuperAdmin && (int)$slip['branch_id'] !== $sessionBranch) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
    $lStmt = $db->prepare("SELECT * FROM payroll_slip_lines WHERE slip_id=? ORDER BY component_type DESC, id ASC");
    $lStmt->execute([$slipId]);
    $slip['lines'] = $lStmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'data'=>$slip]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
