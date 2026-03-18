<?php
/**
 * SECURITY MODULE FOR DASHBOARD
 * Handles CSRF tokens, permission validation, and security checks
 */

class DashboardSecurity {
    
    /**
     * Generate CSRF token
     */
    public static function generateToken($sessionKey = '_csrf_token') {
        if (empty($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$sessionKey];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyToken($token, $sessionKey = '_csrf_token') {
        if (empty($_SESSION[$sessionKey])) {
            return false;
        }
        return hash_equals($_SESSION[$sessionKey], $token ?? '');
    }
    
    /**
     * Get CSRF token for forms/AJAX
     */
    public static function getTokenField($sessionKey = '_csrf_token') {
        $token = self::generateToken($sessionKey);
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            escapeHtml($token)
        );
    }
    
    /**
     * Get CSRF token as HTML attribute for AJAX
     */
    public static function getTokenAttribute($sessionKey = '_csrf_token') {
        $token = self::generateToken($sessionKey);
        return sprintf('data-csrf-token="%s"', escapeHtml($token));
    }
    
    /**
     * Validate user permissions for resource
     */
    public static function validatePermission($permission, $userPermissions = []) {
        if (empty($userPermissions)) {
            $userPermissions = $_SESSION['permissions'] ?? [];
        }
        
        if (!in_array($permission, $userPermissions)) {
            logError(
                sprintf(
                    'Permission denied for user %s trying to access %s',
                    $_SESSION['name'] ?? 'Unknown',
                    $permission
                ),
                'security_audit'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate user role
     */
    public static function validateRole($roles) {
        $userRole = $_SESSION['role'] ?? '';
        $rolesArray = is_array($roles) ? $roles : [$roles];
        
        if (!in_array($userRole, $rolesArray)) {
            logError(
                sprintf(
                    'Invalid role %s for user %s',
                    $userRole,
                    $_SESSION['name'] ?? 'Unknown'
                ),
                'security_audit'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate branch access
     */
    public static function validateBranchAccess($branchId) {
        $role = $_SESSION['role'] ?? '';
        $userBranchId = (int)($_SESSION['branch_id'] ?? 0);
        
        // Super Admin can access all branches
        if ($role === 'Super Admin') {
            return true;
        }
        
        // Branch Admin can only access their own branch
        if ($role === 'Branch Admin' && $userBranchId === (int)$branchId) {
            return true;
        }
        
        logError(
            sprintf(
                'Unauthorized branch access attempt by %s for branch %d',
                $_SESSION['name'] ?? 'Unknown',
                $branchId
            ),
            'security_audit'
        );
        
        return false;
    }
    
    /**
     * Rate limit check (prevent abuse)
     */
    public static function checkRateLimit($action, $maxAttempts = 10, $windowSeconds = 60) {
        $key = '_rate_limit_' . $action . '_' . $_SESSION['user_id'] ?? 'unknown';
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Remove old attempts outside window
        $now = time();
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$key]) >= $maxAttempts) {
            logError(
                sprintf(
                    'Rate limit exceeded for action %s by user %s',
                    $action,
                    $_SESSION['name'] ?? 'Unknown'
                ),
                'security_audit'
            );
            return false;
        }
        
        // Record this attempt
        $_SESSION[$key][] = $now;
        
        return true;
    }
    
    /**
     * Log security event to audit trail
     */
    public static function auditLog($module, $action, $details = '', $db = null) {
        if ($db === null) {
            return;
        }
        
        try {
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (user_id, user_name, user_role, module, action, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([
                $_SESSION['user_id'] ?? 0,
                $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'System',
                $_SESSION['role'] ?? 'Unknown',
                $module,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            logError('Audit log error: ' . $e->getMessage(), 'security_audit');
        }
    }
    
    /**
     * Validate data input
     */
    public static function validateInput($data, $rules = []) {
        $errors = [];
        
        foreach ($rules as $field => $constraints) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($constraints['required']) && $constraints['required'] && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required';
                continue;
            }
            
            // Email validation
            if (isset($constraints['email']) && $constraints['email'] && !isValidEmail($value)) {
                $errors[$field] = ucfirst($field) . ' must be a valid email';
                continue;
            }
            
            // URL validation
            if (isset($constraints['url']) && $constraints['url'] && !isValidUrl($value)) {
                $errors[$field] = ucfirst($field) . ' must be a valid URL';
                continue;
            }
            
            // Min length
            if (isset($constraints['min']) && strlen($value) < $constraints['min']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $constraints['min'] . ' characters';
                continue;
            }
            
            // Max length
            if (isset($constraints['max']) && strlen($value) > $constraints['max']) {
                $errors[$field] = ucfirst($field) . ' must be at most ' . $constraints['max'] . ' characters';
                continue;
            }
            
            // Type check
            if (isset($constraints['type'])) {
                $type = $constraints['type'];
                $valid = false;
                
                switch ($type) {
                    case 'numeric':
                        $valid = is_numeric($value);
                        break;
                    case 'int':
                        $valid = is_int($value) || (is_numeric($value) && strpos($value, '.') === false);
                        break;
                    case 'float':
                        $valid = is_float($value) || is_numeric($value);
                        break;
                    case 'boolean':
                        $valid = is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0]);
                        break;
                }
                
                if (!$valid) {
                    $errors[$field] = ucfirst($field) . ' must be a valid ' . $type;
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize output for safe display
     */
    public static function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        
        return escapeHtml($data);
    }
    
    /**
     * Check session validity and security
     */
    public static function validateSession() {
        // Check required session values
        $required = ['logged_in', 'user_id', 'name', 'role'];
        
        foreach ($required as $key) {
            if (!isset($_SESSION[$key])) {
                logError('Invalid session: missing ' . $key, 'security_audit');
                session_destroy();
                return false;
            }
        }
        
        // Check session age (max 24 hours)
        $maxAge = 86400; // 24 hours
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > $maxAge) {
            logError('Session expired for user ' . $_SESSION['name'], 'security_audit');
            session_destroy();
            return false;
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regenerated'])) {
            $_SESSION['last_regenerated'] = time();
        } elseif ((time() - $_SESSION['last_regenerated']) > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regenerated'] = time();
        }
        
        return true;
    }
}

?>
