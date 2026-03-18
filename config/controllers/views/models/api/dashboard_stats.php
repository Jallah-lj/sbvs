<?php
session_start();
require_once '../config/database.php';

// Access Control
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$stats = [];

// 1. Get Counters
$stats['total_students'] = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$stats['total_branches'] = $db->query("SELECT COUNT(*) FROM branches WHERE status = 'Active'")->fetchColumn();
$stats['total_revenue'] = $db->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?? 0;
$stats['total_teachers'] = $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

// 2. Revenue Trend (Last 6 Months)
$revenue_query = "SELECT DATE_FORMAT(payment_date, '%b') as month, SUM(amount) as total 
                  FROM payments 
                  GROUP BY MONTH(payment_date) 
                  ORDER BY payment_date ASC LIMIT 6";
$stats['revenue_trend'] = $db->query($revenue_query)->fetchAll();

// 3. Branch Performance (Student distribution)
$branch_query = "SELECT b.name, COUNT(s.id) as student_count 
                 FROM branches b 
                 LEFT JOIN students s ON b.id = s.branch_id 
                 GROUP BY b.id";
$stats['branch_performance'] = $db->query($branch_query)->fetchAll();

header('Content-Type: application/json');
echo json_encode($stats);