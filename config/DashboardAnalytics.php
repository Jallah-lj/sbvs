<?php
/**
 * DASHBOARD ANALYTICS & INSIGHTS
 * Provides trend analysis, growth calculations, and predictive insights
 */

class DashboardAnalytics {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Calculate growth metrics for KPI
     */
    public function calculateGrowthMetrics($isSuperAdmin, $branchId = 0) {
        try {
            $current = $this->getCurrentMonthMetrics($isSuperAdmin, $branchId);
            $previous = $this->getPreviousMonthMetrics($isSuperAdmin, $branchId);
            
            return [
                'students_growth' => calculateGrowth($current['students'] ?? 0, $previous['students'] ?? 0),
                'teachers_growth' => calculateGrowth($current['teachers'] ?? 0, $previous['teachers'] ?? 0),
                'revenue_growth' => calculateGrowth($current['revenue'] ?? 0, $previous['revenue'] ?? 0),
                'enrollments_growth' => calculateGrowth($current['enrollments'] ?? 0, $previous['enrollments'] ?? 0),
            ];
        } catch (Exception $e) {
            logError('Growth calculation error: ' . $e->getMessage(), 'analytics');
            return [];
        }
    }
    
    /**
     * Get current month metrics
     */
    private function getCurrentMonthMetrics($isSuperAdmin, $branchId) {
        $year = date('Y');
        $month = date('m');
        
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) AS students,
                    COUNT(DISTINCT t.id) AS teachers,
                    COALESCE(SUM(p.amount), 0) AS revenue,
                    COUNT(DISTINCT e.id) AS enrollments
                 FROM (
                    SELECT 1 WHERE MONTH(registration_date) = ? AND YEAR(registration_date) = ?
                 ) AS registration_period
                 LEFT JOIN students s ON MONTH(s.registration_date) = ? AND YEAR(s.registration_date) = ?
                 LEFT JOIN teachers t ON MONTH(t.created_at) = ? AND YEAR(t.created_at) = ? AND t.status = 'Active'
                 LEFT JOIN payments p ON MONTH(p.payment_date) = ? AND YEAR(p.payment_date) = ?
                 LEFT JOIN enrollments e ON MONTH(e.enrolled_date) = ? AND YEAR(e.enrolled_date) = ? AND e.status = 'Active'"
            );
            $stmt->execute([$month, $year, $month, $year, $month, $year, $month, $year, $month, $year]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) AS students,
                    COUNT(DISTINCT t.id) AS teachers,
                    COALESCE(SUM(p.amount), 0) AS revenue,
                    COUNT(DISTINCT e.id) AS enrollments
                 FROM students s
                 LEFT JOIN teachers t ON t.branch_id = ? AND MONTH(t.created_at) = ? AND YEAR(t.created_at) = ? AND t.status = 'Active'
                 LEFT JOIN payments p ON p.branch_id = ? AND MONTH(p.payment_date) = ? AND YEAR(p.payment_date) = ?
                 LEFT JOIN enrollments e ON e.status = 'Active' AND MONTH(e.enrolled_date) = ? AND YEAR(e.enrolled_date) = ?
                 WHERE s.branch_id = ? AND MONTH(s.registration_date) = ? AND YEAR(s.registration_date) = ?"
            );
            $stmt->execute([$branchId, $month, $year, $branchId, $month, $year, $month, $year, $branchId, $month, $year]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'students' => 0,
            'teachers' => 0,
            'revenue' => 0,
            'enrollments' => 0
        ];
    }
    
    /**
     * Get previous month metrics
     */
    private function getPreviousMonthMetrics($isSuperAdmin, $branchId) {
        $date = new DateTime('first day of previous month');
        $year = $date->format('Y');
        $month = $date->format('m');
        
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) AS students,
                    COUNT(DISTINCT t.id) AS teachers,
                    COALESCE(SUM(p.amount), 0) AS revenue,
                    COUNT(DISTINCT e.id) AS enrollments
                 FROM students s
                 LEFT JOIN teachers t ON MONTH(t.created_at) = ? AND YEAR(t.created_at) = ? AND t.status = 'Active'
                 LEFT JOIN payments p ON MONTH(p.payment_date) = ? AND YEAR(p.payment_date) = ?
                 LEFT JOIN enrollments e ON e.status = 'Active' AND MONTH(e.enrolled_date) = ? AND YEAR(e.enrolled_date) = ?
                 WHERE MONTH(s.registration_date) = ? AND YEAR(s.registration_date) = ?"
            );
            $stmt->execute([$month, $year, $month, $year, $month, $year, $month, $year]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) AS students,
                    COUNT(DISTINCT t.id) AS teachers,
                    COALESCE(SUM(p.amount), 0) AS revenue,
                    COUNT(DISTINCT e.id) AS enrollments
                 FROM students s
                 LEFT JOIN teachers t ON t.branch_id = ? AND MONTH(t.created_at) = ? AND YEAR(t.created_at) = ? AND t.status = 'Active'
                 LEFT JOIN payments p ON p.branch_id = ? AND MONTH(p.payment_date) = ? AND YEAR(p.payment_date) = ?
                 LEFT JOIN enrollments e ON e.status = 'Active' AND MONTH(e.enrolled_date) = ? AND YEAR(e.enrolled_date) = ?
                 WHERE s.branch_id = ? AND MONTH(s.registration_date) = ? AND YEAR(s.registration_date) = ?"
            );
            $stmt->execute([$branchId, $month, $year, $branchId, $month, $year, $month, $year, $branchId, $month, $year]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'students' => 0,
            'teachers' => 0,
            'revenue' => 0,
            'enrollments' => 0
        ];
    }
    
    /**
     * Get performance alerts/insights
     */
    public function getPerformanceAlerts($isSuperAdmin, $branchId = 0) {
        $alerts = [];
        
        try {
            // Alert: Low revenue branches
            if ($isSuperAdmin) {
                $lowRevenue = $this->db->query(
                    "SELECT b.id, b.name, COALESCE(SUM(p.amount), 0) AS monthly_rev
                     FROM branches b
                     LEFT JOIN payments p ON b.id = p.branch_id 
                        AND MONTH(p.payment_date) = MONTH(CURDATE())
                        AND YEAR(p.payment_date) = YEAR(CURDATE())
                     WHERE b.status = 'Active'
                     GROUP BY b.id, b.name
                     HAVING monthly_rev < 1000
                     ORDER BY monthly_rev ASC
                     LIMIT 3"
                )->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($lowRevenue)) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => count($lowRevenue) . ' Branch' . (count($lowRevenue) > 1 ? 'es' : '') . ' Below Revenue Threshold',
                        'icon' => 'bi-exclamation-triangle-fill',
                        'color' => '#f59e0b',
                        'branches' => $lowRevenue
                    ];
                }
            }
            
            // Alert: Inactive teachers
            $inactiveTeachers = $this->db->prepare(
                "SELECT COUNT(*) as count FROM teachers 
                 WHERE " . ($isSuperAdmin ? "1=1" : "branch_id = ?") . "
                 AND status = 'Inactive'"
            );
            
            if ($isSuperAdmin) {
                $inactiveTeachers->execute();
            } else {
                $inactiveTeachers->execute([$branchId]);
            }
            
            $count = $inactiveTeachers->fetchColumn();
            if ($count > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => $count . ' Teacher' . ($count > 1 ? 's' : '') . ' Marked Inactive',
                    'icon' => 'bi-person-dash-fill',
                    'color' => '#0ea5e9'
                ];
            }
            
            // Alert: High completion rate (positive alert)
            if ($isSuperAdmin) {
                $stmt = $this->db->prepare(
                    "SELECT b.name, 
                            COUNT(CASE WHEN e.status = 'Completed' THEN 1 END) as completions,
                            COUNT(e.id) as total
                     FROM branches b
                     LEFT JOIN students s ON b.id = s.branch_id
                     LEFT JOIN enrollments e ON s.id = e.student_id
                     WHERE b.status = 'Active'
                     GROUP BY b.id, b.name
                     HAVING total > 0 AND (completions / total * 100) >= 80"
                );
                $stmt->execute();
            } else {
                $stmt = $this->db->prepare(
                    "SELECT 'This Branch' as name, 
                            COUNT(CASE WHEN e.status = 'Completed' THEN 1 END) as completions,
                            COUNT(e.id) as total
                     FROM students s
                     LEFT JOIN enrollments e ON s.id = e.student_id
                     WHERE s.branch_id = ? AND COUNT(e.id) > 0"
                );
                $stmt->execute([$branchId]);
            }
            
            $highCompletion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($highCompletion && $highCompletion['total'] > 0) {
                $rate = round(($highCompletion['completions'] / $highCompletion['total']) * 100);
                if ($rate >= 80) {
                    $alerts[] = [
                        'type' => 'success',
                        'title' => 'Excellent Completion Rate: ' . $rate . '%',
                        'icon' => 'bi-check-circle-fill',
                        'color' => '#10b981'
                    ];
                }
            }
        } catch (Exception $e) {
            logError('Alert calculation error: ' . $e->getMessage(), 'analytics');
        }
        
        return $alerts;
    }
    
    /**
     * Get trend indicators for display
     */
    public function getTrendIndicator($growth) {
        if (empty($growth) || !isset($growth['value'])) {
            return [
                'icon' => 'bi-dash',
                'color' => '#64748b',
                'class' => 'neutral',
                'text' => 'No change'
            ];
        }
        
        $value = $growth['value'];
        $trend = $growth['trend'] ?? 'same';
        
        if ($trend === 'up') {
            return [
                'icon' => 'bi-arrow-up-right',
                'color' => '#10b981',
                'class' => 'positive',
                'text' => '+' . $value . '%'
            ];
        } elseif ($trend === 'down') {
            return [
                'icon' => 'bi-arrow-down-left',
                'color' => '#ef4444',
                'class' => 'negative',
                'text' => $value . '%'
            ];
        } else {
            return [
                'icon' => 'bi-dash',
                'color' => '#64748b',
                'class' => 'neutral',
                'text' => 'No change'
            ];
        }
    }
    
    /**
     * Generate insight message
     */
    public function generateInsightMessage($metric, $growth, $value) {
        if (empty($growth)) {
            return '';
        }
        
        $trend = $growth['trend'] ?? 'same';
        $trendText = $trend === 'up' ? 'increased' : ($trend === 'down' ? 'decreased' : 'remained');
        
        return sprintf(
            '%s has %s by %d%% compared to last month (current: %s)',
            ucfirst($metric),
            $trendText,
            abs($growth['value'] ?? 0),
            formatNumber($value)
        );
    }
}

?>
