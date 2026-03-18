<?php
/**
 * Dashboard Export API
 * Handles exporting KPI data as CSV
 */

ob_start();
session_start();

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once __DIR__ . '/../../../../database.php';
require_once __DIR__ . '/../../../../helpers.php';

$db = (new Database())->getConnection();
$role = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin = ($role === 'Super Admin');

// Get export type
$exportType = sanitizeInput($_GET['type'] ?? 'kpi');
$filename = date('Y-m-d_His');

try {
    if ($exportType === 'kpi') {
        exportKPI($db, $isSuperAdmin, $branchId, $filename);
    } elseif ($exportType === 'branch_performance') {
        exportBranchPerformance($db, $isSuperAdmin, $filename);
    } elseif ($exportType === 'students') {
        exportRecentStudents($db, $isSuperAdmin, $branchId, $filename);
    } elseif ($exportType === 'payments') {
        exportRecentPayments($db, $isSuperAdmin, $branchId, $filename);
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid export type']));
    }
} catch (Exception $e) {
    logError('Export error: ' . $e->getMessage(), 'export_api');
    http_response_code(500);
    die(json_encode(['error' => getUserErrorMessage('DB_QUERY')]));
}

/**
 * Export KPI data
 */
function exportKPI($db, $isSuperAdmin, $branchId, $filename) {
    if ($isSuperAdmin) {
        $kpi = $db->query(
            "SELECT
                (SELECT COUNT(*) FROM branches WHERE status='Active') AS branches,
                (SELECT COUNT(*) FROM students) AS students,
                (SELECT COUNT(*) FROM teachers WHERE status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses) AS courses,
                (SELECT COALESCE(SUM(amount),0) FROM payments
                     WHERE MONTH(payment_date)=MONTH(CURDATE())
                       AND YEAR(payment_date)=YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount),0) FROM payments) AS total_rev,
                NOW() AS export_date"
        )->fetch(PDO::FETCH_ASSOC);
    } else {
        $kpiStmt = $db->prepare(
            "SELECT
                ? AS branch_id,
                (SELECT COUNT(*) FROM students WHERE branch_id = ?) AS students,
                (SELECT COUNT(*) FROM teachers WHERE branch_id = ? AND status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses WHERE branch_id = ?) AS courses,
                (SELECT COUNT(*) FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    WHERE s.branch_id = ? AND e.status = 'Active') AS active_enrollments,
                (SELECT COALESCE(SUM(amount),0) FROM payments
                     WHERE branch_id = ?
                       AND MONTH(payment_date)=MONTH(CURDATE())
                       AND YEAR(payment_date)=YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE branch_id = ?) AS total_rev,
                NOW() AS export_date"
        );
        $kpiStmt->execute([$branchId, $branchId, $branchId, $branchId, $branchId, $branchId, $branchId]);
        $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $csv = "KPI Report - " . formatDate('now', 'Y-m-d H:i:s') . "\n\n";
    $csv .= "Metric,Value\n";
    
    foreach ($kpi as $key => $value) {
        if ($key === 'export_date') continue;
        $label = ucwords(str_replace('_', ' ', $key));
        $csv .= "\"$label\",\"$value\"\n";
    }
    
    sendCSV($csv, $filename . '_kpi');
}

/**
 * Export branch performance data
 */
function exportBranchPerformance($db, $isSuperAdmin, $filename) {
    if (!$isSuperAdmin) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }
    
    $branches = $db->query(
        "SELECT b.id, b.name,
                (SELECT COUNT(*) FROM students s WHERE s.branch_id = b.id) AS total_students,
                (SELECT COUNT(*) FROM enrollments e 
                    JOIN students s ON e.student_id = s.id 
                    WHERE s.branch_id = b.id AND e.status='Active') AS active_enrollments,
                (SELECT COUNT(*) FROM enrollments e 
                    JOIN students s ON e.student_id = s.id 
                    WHERE s.branch_id = b.id AND e.status='Completed') AS completions,
                (SELECT COALESCE(SUM(amount),0) FROM payments p 
                    WHERE p.branch_id = b.id 
                    AND MONTH(p.payment_date)=MONTH(CURDATE()) 
                    AND YEAR(p.payment_date)=YEAR(CURDATE())) AS monthly_rev
         FROM branches b WHERE b.status='Active'
         ORDER BY monthly_rev DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    
    $csv = "Branch Performance Report - " . formatDate('now', 'Y-m-d H:i:s') . "\n\n";
    $csv .= "Branch,Total Students,Active Enrollments,Completions,Monthly Revenue,Completion Rate %\n";
    
    foreach ($branches as $bp) {
        $compRate = ($bp['active_enrollments'] + $bp['completions']) > 0
            ? round($bp['completions'] / ($bp['active_enrollments'] + $bp['completions']) * 100)
            : 0;
        
        $csv .= sprintf(
            '"%s",%d,%d,%d,%s,%d%%' . "\n",
            $bp['name'],
            $bp['total_students'],
            $bp['active_enrollments'],
            $bp['completions'],
            formatCurrency($bp['monthly_rev'], '', 2),
            $compRate
        );
    }
    
    sendCSV($csv, $filename . '_branch_performance');
}

/**
 * Export recent students
 */
function exportRecentStudents($db, $isSuperAdmin, $branchId, $filename) {
    if ($isSuperAdmin) {
        $students = $db->query(
            "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
             FROM students s
             JOIN users u ON s.user_id = u.id
             JOIN branches b ON s.branch_id = b.id
             ORDER BY s.registration_date DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare(
            "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
             FROM students s
             JOIN users u ON s.user_id = u.id
             JOIN branches b ON s.branch_id = b.id
             WHERE s.branch_id = ?
             ORDER BY s.registration_date DESC LIMIT 100"
        );
        $stmt->execute([$branchId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $csv = "Student Registrations Report - " . formatDate('now', 'Y-m-d H:i:s') . "\n\n";
    $csv .= "Name,Student ID,Branch,Registration Date\n";
    
    foreach ($students as $s) {
        $csv .= sprintf(
            '"%s","%s","%s","%s"' . "\n",
            $s['name'],
            $s['student_id'],
            $s['branch'],
            formatDate($s['registration_date'])
        );
    }
    
    sendCSV($csv, $filename . '_students');
}

/**
 * Export recent payments
 */
function exportRecentPayments($db, $isSuperAdmin, $branchId, $filename) {
    if ($isSuperAdmin) {
        $payments = $db->query(
            "SELECT u.name, p.amount, p.payment_method, p.payment_date
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN users u ON s.user_id = u.id
             ORDER BY p.payment_date DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare(
            "SELECT u.name, p.amount, p.payment_method, p.payment_date
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN users u ON s.user_id = u.id
             WHERE p.branch_id = ?
             ORDER BY p.payment_date DESC LIMIT 100"
        );
        $stmt->execute([$branchId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $csv = "Payments Report - " . formatDate('now', 'Y-m-d H:i:s') . "\n\n";
    $csv .= "Student Name,Amount,Payment Method,Payment Date\n";
    
    foreach ($payments as $p) {
        $csv .= sprintf(
            '"%s",%s,"%s","%s"' . "\n",
            $p['name'],
            formatCurrency($p['amount'], '', 2),
            $p['payment_method'],
            formatDate($p['payment_date'])
        );
    }
    
    sendCSV($csv, $filename . '_payments');
}

/**
 * Send CSV file to client
 */
function sendCSV($csv, $filename) {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csv;
    exit;
}

?>
