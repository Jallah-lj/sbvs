<?php
/**
 * DASHBOARD QUERY OPTIMIZER
 * Handles batched queries, caching, and performance optimization
 */

class DashboardQueryOptimizer {
    private $db;
    private $cacheTTL = 3600; // 1 hour default
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all KPI data in single batch query
     */
    public function getKPIData($isSuperAdmin, $branchId = 0) {
        $cacheKey = cacheKey('kpi', $isSuperAdmin ? 'super' : 'branch', $branchId);
        
        // Check cache first
        $cached = getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            if ($isSuperAdmin) {
                $data = $this->getSuperAdminKPI();
            } else {
                $data = $this->getBranchAdminKPI($branchId);
            }
            
            // Cache the result
            setCache($cacheKey, $data, $this->cacheTTL);
            return $data;
        } catch (Exception $e) {
            logError('KPI batch query error: ' . $e->getMessage(), 'query_optimizer');
            return [
                'branches' => 0, 'students' => 0, 'teachers' => 0, 'courses' => 0,
                'monthly_rev' => 0, 'total_rev' => 0, 'enrollments' => 0
            ];
        }
    }
    
    /**
     * Super Admin KPI - Optimized single query
     */
    private function getSuperAdminKPI() {
        $result = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM branches WHERE status='Active') AS branches,
                (SELECT COUNT(*) FROM students) AS students,
                (SELECT COUNT(*) FROM teachers WHERE status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses) AS courses,
                (SELECT COALESCE(SUM(amount), 0) FROM payments
                    WHERE MONTH(payment_date) = MONTH(CURDATE())
                    AND YEAR(payment_date) = YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount), 0) FROM payments) AS total_rev"
        )->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: [
            'branches' => 0, 'students' => 0, 'teachers' => 0, 'courses' => 0,
            'monthly_rev' => 0, 'total_rev' => 0
        ];
    }
    
    /**
     * Branch Admin KPI - Optimized single query
     */
    private function getBranchAdminKPI($branchId) {
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM students WHERE branch_id = ?) AS students,
                (SELECT COUNT(*) FROM teachers WHERE branch_id = ? AND status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses WHERE branch_id = ?) AS courses,
                (SELECT COUNT(*) FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    WHERE s.branch_id = ? AND e.status = 'Active') AS enrollments,
                (SELECT COALESCE(SUM(amount), 0) FROM payments
                    WHERE branch_id = ?
                    AND MONTH(payment_date) = MONTH(CURDATE())
                    AND YEAR(payment_date) = YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE branch_id = ?) AS total_rev"
        );
        
        $stmt->execute([$branchId, $branchId, $branchId, $branchId, $branchId, $branchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: [
            'students' => 0, 'teachers' => 0, 'courses' => 0, 'enrollments' => 0,
            'monthly_rev' => 0, 'total_rev' => 0
        ];
    }
    
    /**
     * Get branch names in batch
     */
    public function getBranchNames($branchIds = []) {
        if (empty($branchIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, name FROM branches WHERE id IN ($placeholders) AND status='Active'"
        );
        $stmt->execute($branchIds);
        
        $names = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $names[$row['id']] = $row['name'];
        }
        return $names;
    }
    
    /**
     * Get recent activity (students + payments) in batches
     */
    public function getRecentActivity($isSuperAdmin, $branchId = 0, $limit = 5) {
        $cacheKey = cacheKey('activity', $isSuperAdmin ? 'super' : 'branch', $branchId, $limit);
        
        $cached = getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $students = $this->getRecentStudents($isSuperAdmin, $branchId, $limit);
            $payments = $this->getRecentPayments($isSuperAdmin, $branchId, $limit);
            
            $data = [
                'students' => $students,
                'payments' => $payments
            ];
            
            setCache($cacheKey, $data, 1800); // 30 mins cache
            return $data;
        } catch (Exception $e) {
            logError('Activity query error: ' . $e->getMessage(), 'query_optimizer');
            return ['students' => [], 'payments' => []];
        }
    }
    
    /**
     * Get recent students
     */
    private function getRecentStudents($isSuperAdmin, $branchId, $limit) {
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 JOIN branches b ON s.branch_id = b.id
                 ORDER BY s.registration_date DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 JOIN branches b ON s.branch_id = b.id
                 WHERE s.branch_id = ?
                 ORDER BY s.registration_date DESC LIMIT ?"
            );
            $stmt->execute([$branchId, $limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent payments
     */
    private function getRecentPayments($isSuperAdmin, $branchId, $limit) {
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT u.name, p.amount, p.payment_method, p.payment_date
                 FROM payments p
                 JOIN students s ON p.student_id = s.id
                 JOIN users u ON s.user_id = u.id
                 ORDER BY p.payment_date DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT u.name, p.amount, p.payment_method, p.payment_date
                 FROM payments p
                 JOIN students s ON p.student_id = s.id
                 JOIN users u ON s.user_id = u.id
                 WHERE p.branch_id = ?
                 ORDER BY p.payment_date DESC LIMIT ?"
            );
            $stmt->execute([$branchId, $limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get analytics data (growth rates, trends)
     */
    public function getAnalyticsData($isSuperAdmin, $branchId = 0) {
        $cacheKey = cacheKey('analytics', $isSuperAdmin ? 'super' : 'branch', $branchId);
        
        $cached = getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $data = [
                'current_month' => $this->getMonthlyMetrics($isSuperAdmin, $branchId, date('Y-m')),
                'previous_month' => $this->getMonthlyMetrics($isSuperAdmin, $branchId, date('Y-m', strtotime('-1 month'))),
                'year_to_date' => $this->getYearToDateMetrics($isSuperAdmin, $branchId),
            ];
            
            setCache($cacheKey, $data, 3600);
            return $data;
        } catch (Exception $e) {
            logError('Analytics query error: ' . $e->getMessage(), 'query_optimizer');
            return [
                'current_month' => [],
                'previous_month' => [],
                'year_to_date' => []
            ];
        }
    }
    
    /**
     * Get monthly metrics
     */
    private function getMonthlyMetrics($isSuperAdmin, $branchId, $yearMonth) {
        list($year, $month) = explode('-', $yearMonth);
        
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT student_id) AS new_students,
                    COALESCE(SUM(amount), 0) AS revenue,
                    COUNT(*) AS transaction_count
                 FROM payments
                 WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?"
            );
            $stmt->execute([$year, $month]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM students WHERE branch_id = ? 
                        AND YEAR(registration_date) = ? AND MONTH(registration_date) = ?) AS new_students,
                    COALESCE(SUM(amount), 0) AS revenue,
                    COUNT(*) AS transaction_count
                 FROM payments
                 WHERE branch_id = ? AND YEAR(payment_date) = ? AND MONTH(payment_date) = ?"
            );
            $stmt->execute([$branchId, $year, $month, $branchId, $year, $month]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'new_students' => 0,
            'revenue' => 0,
            'transaction_count' => 0
        ];
    }
    
    /**
     * Get year-to-date metrics
     */
    private function getYearToDateMetrics($isSuperAdmin, $branchId) {
        $year = date('Y');
        
        if ($isSuperAdmin) {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT student_id) AS new_students,
                    COALESCE(SUM(amount), 0) AS revenue,
                    COUNT(*) AS transaction_count
                 FROM payments
                 WHERE YEAR(payment_date) = ?"
            );
            $stmt->execute([$year]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM students WHERE branch_id = ? 
                        AND YEAR(registration_date) = ?) AS new_students,
                    COALESCE(SUM(amount), 0) AS revenue,
                    COUNT(*) AS transaction_count
                 FROM payments
                 WHERE branch_id = ? AND YEAR(payment_date) = ?"
            );
            $stmt->execute([$branchId, $year, $branchId, $year]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'new_students' => 0,
            'revenue' => 0,
            'transaction_count' => 0
        ];
    }
    
    /**
     * Clear all dashboard caches
     */
    public static function clearCache() {
        clearCache('kpi_');
        clearCache('activity_');
        clearCache('analytics_');
    }
}

?>
