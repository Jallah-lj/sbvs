<?php
/**
 * HELPER FUNCTIONS FOR DASHBOARD & APPLICATION
 * Centralized formatting, validation, and utility functions
 */

/* ═══════════════════════════════════════════════════════════════════════════ */
/* FORMATTING FUNCTIONS                                                        */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Format currency value with symbol
 * @param float $value Amount in currency
 * @param string $symbol Currency symbol (default: $)
 * @param int $decimals Decimal places
 * @return string Formatted currency
 */
function formatCurrency($value, $symbol = '$', $decimals = 0) {
    if (!is_numeric($value)) {
        return $symbol . '0';
    }
    return $symbol . number_format((float)$value, $decimals, '.', ',');
}

/**
 * Format number with thousands separator
 * @param int|float $value Number to format
 * @param int $decimals Decimal places
 * @return string Formatted number
 */
function formatNumber($value, $decimals = 0) {
    if (!is_numeric($value)) {
        return '0';
    }
    return number_format((float)$value, $decimals, '.', ',');
}

/**
 * Format date for display
 * @param string $date Date string (Y-m-d or timestamp)
 * @param string $format PHP date format (default: 'M j, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) {
        return '-';
    }
    try {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '-';
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Format date with time
 * @param string $date Date string
 * @return string Formatted date with time
 */
function formatDateTime($date, $format = 'M j, H:i') {
    return formatDate($date, $format);
}

/**
 * Format relative time (e.g., "2 hours ago")
 * @param string $date Date string
 * @return string Relative time
 */
function formatRelativeTime($date) {
    if (empty($date)) {
        return '-';
    }
    
    try {
        $timestamp = strtotime($date);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return formatDate($date);
        }
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Truncate text with ellipsis
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix (default: '...')
 * @return string Truncated text
 */
function truncateText($text, $length = 30, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Convert boolean to display text
 * @param bool $value Boolean value
 * @param string $trueText Text for true
 * @param string $falseText Text for false
 * @return string
 */
function formatBool($value, $trueText = 'Yes', $falseText = 'No') {
    return $value ? $trueText : $falseText;
}

/**
 * Calculate percentage
 * @param float $value Current value
 * @param float $total Total value
 * @return int Percentage (0-100)
 */
function calculatePercentage($value, $total) {
    if ($total == 0 || !is_numeric($value) || !is_numeric($total)) {
        return 0;
    }
    return (int)round(($value / $total) * 100);
}

/**
 * Calculate growth rate (current vs previous)
 * @param float $current Current value
 * @param float $previous Previous value
 * @return array ['value' => percentage, 'trend' => 'up'|'down'|'same', 'icon' => icon class]
 */
function calculateGrowth($current, $previous) {
    if (!is_numeric($current) || !is_numeric($previous)) {
        return ['value' => 0, 'trend' => 'same', 'icon' => 'bi-dash'];
    }
    
    if ($previous == 0) {
        return ['value' => $current > 0 ? 100 : 0, 'trend' => $current > 0 ? 'up' : 'same', 'icon' => $current > 0 ? 'bi-arrow-up' : 'bi-dash'];
    }
    
    $growth = (($current - $previous) / $previous) * 100;
    $trend = $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'same');
    $icon = $growth > 0 ? 'bi-arrow-up' : ($growth < 0 ? 'bi-arrow-down' : 'bi-dash');
    
    return [
        'value' => (int)round($growth),
        'trend' => $trend,
        'icon' => $icon,
        'color' => $growth > 0 ? '#10b981' : ($growth < 0 ? '#ef4444' : '#64748b')
    ];
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* ESCAPING & SECURITY FUNCTIONS                                               */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Escape HTML for safe display
 * @param string $text Text to escape
 * @return string Escaped text
 */
function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Escape text while preserving line breaks
 * @param string $text Text to escape
 * @return string Escaped text with nl2br
 */
function escapeHtmlNl($text) {
    return nl2br(escapeHtml($text));
}

/**
 * Sanitize input for database
 * @param string $value Input value
 * @return string Sanitized value
 */
function sanitizeInput($value) {
    return trim(strip_tags($value ?? ''));
}

/**
 * Validate email
 * @param string $email Email address
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 * @param string $url URL
 * @return bool
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* ARRAY & DATA FUNCTIONS                                                      */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Get value from array with default fallback
 * @param array $array Array to search
 * @param string|int $key Key to find
 * @param mixed $default Default value if key not found
 * @return mixed Value or default
 */
function arrayGet($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Get multiple columns from array of arrays
 * @param array $array Array of records
 * @param string $key Column name
 * @return array Array of values
 */
function arrayColumn($array, $key) {
    return array_map(function($item) use ($key) {
        return $item[$key] ?? null;
    }, $array);
}

/**
 * Convert array to JSON safely
 * @param array $array Array to convert
 * @return string JSON string
 */
function toJson($array) {
    return json_encode($array ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* BADGE & STATUS FUNCTIONS                                                    */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Get badge HTML for status
 * @param string $status Status value
 * @param array $mapping Custom status mapping
 * @return string HTML badge
 */
function statusBadge($status, $mapping = []) {
    $defaultMapping = [
        'Active' => ['class' => 'badge-active', 'text' => 'Active'],
        'Inactive' => ['class' => 'badge-danger', 'text' => 'Inactive'],
        'Pending' => ['class' => 'badge-pending', 'text' => 'Pending'],
        'Completed' => ['class' => 'badge-active', 'text' => 'Completed'],
        'Cancelled' => ['class' => 'badge-danger', 'text' => 'Cancelled'],
        'On Hold' => ['class' => 'badge-pending', 'text' => 'On Hold'],
    ];
    
    $config = $mapping[$status] ?? $defaultMapping[$status] ?? ['class' => 'badge-info', 'text' => $status];
    
    return sprintf(
        '<span class="%s" title="%s">%s</span>',
        escapeHtml($config['class']),
        escapeHtml($status),
        escapeHtml($config['text'])
    );
}

/**
 * Get color class based on value range
 * @param float $value Value to evaluate
 * @param float $good Threshold for good (green)
 * @param float $warning Threshold for warning (amber)
 * @return string Color class name
 */
function getColorByRange($value, $good = 70, $warning = 40) {
    if ($value >= $good) {
        return 'green';
    } elseif ($value >= $warning) {
        return 'amber';
    } else {
        return 'red';
    }
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* PERMISSION & AUTHORIZATION FUNCTIONS                                        */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Check if user has permission
 * @param string $permission Permission name
 * @param array $userPermissions Array of user permissions
 * @return bool
 */
function hasPermission($permission, $userPermissions = []) {
    if (empty($userPermissions)) {
        $userPermissions = $_SESSION['permissions'] ?? [];
    }
    return in_array($permission, $userPermissions);
}

/**
 * Check if user role matches
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function hasRole($roles) {
    $userRole = $_SESSION['role'] ?? '';
    $rolesArray = is_array($roles) ? $roles : [$roles];
    return in_array($userRole, $rolesArray);
}

/**
 * Check if user is Super Admin
 * @return bool
 */
function isSuperAdmin() {
    return hasRole('Super Admin');
}

/**
 * Check if user is Branch Admin
 * @return bool
 */
function isBranchAdmin() {
    return hasRole('Branch Admin');
}

/**
 * Get user's branch ID from session
 * @return int|null
 */
function getUserBranchId() {
    return (int)($_SESSION['branch_id'] ?? 0) ?: null;
}

/**
 * Verify user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* ERROR HANDLING & MESSAGING FUNCTIONS                                        */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Get user-friendly error message
 * @param string $code Error code or message
 * @return string User-friendly message
 */
function getUserErrorMessage($code) {
    $messages = [
        'DB_CONNECT' => 'Unable to connect to database. Please try again later.',
        'DB_QUERY' => 'Unable to fetch data. Please try again.',
        'PERMISSION_DENIED' => 'You do not have permission to access this resource.',
        'INVALID_INPUT' => 'Please provide valid input.',
        'NOT_FOUND' => 'The requested resource was not found.',
        'UNKNOWN' => 'An unexpected error occurred. Please try again.',
    ];
    
    return $messages[$code] ?? $messages['UNKNOWN'];
}

/**
 * Log error for debugging
 * @param string $message Error message
 * @param string $context Context/file
 * @return void
 */
function logError($message, $context = '') {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../logs/error.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($logFile))) {
        @mkdir(dirname($logFile), 0755, true);
    }
    
    $logEntry = "[$timestamp] [Error in $context] $message\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* CACHE FUNCTIONS                                                              */
/* ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Generate cache key
 * @param string $prefix Prefix
 * @param mixed ...$parts Cache key parts
 * @return string Cache key
 */
function cacheKey($prefix, ...$parts) {
    $key = array_merge([$prefix], $parts);
    return implode('_', array_map(function($p) {
        return str_replace([' ', '-'], '_', (string)$p);
    }, $key));
}

/**
 * Get cached value
 * @param string $key Cache key
 * @return mixed Cached value or null
 */
function getCache($key) {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
        // Check if cache expired (24 hours default)
        if ($data['expires'] > time()) {
            return $data['value'];
        }
        @unlink($file);
    }
    return null;
}

/**
 * Set cached value
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds (default: 24 hours)
 * @return void
 */
function setCache($key, $value, $ttl = 86400) {
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $data = [
        'value' => $value,
        'expires' => time() + $ttl
    ];
    
    $file = $cacheDir . md5($key) . '.cache';
    @file_put_contents($file, serialize($data));
}

/**
 * Clear cache by key pattern
 * @param string $pattern Pattern to match
 * @return void
 */
function clearCache($pattern = '') {
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        return;
    }
    
    $files = glob($cacheDir . '*.cache');
    foreach ($files as $file) {
        if (empty($pattern) || strpos(basename($file), $pattern) !== false) {
            @unlink($file);
        }
    }
}

?>
