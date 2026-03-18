<?php
/**
 * DASHBOARD ACCESSIBILITY HELPERS
 * Provides utilities for WCAG compliance and accessibility features
 */

class DashboardAccessibility {
    
    /**
     * Generate ARIA label for card
     */
    public static function ariaLabel($title, $value = '', $unit = '') {
        return sprintf(
            '%s: %s %s',
            $title,
            $value,
            $unit
        );
    }
    
    /**
     * Generate role and aria attributes for icon button
     */
    public static function getIconButtonAttributes($label, $ariaExpanded = null) {
        $attrs = [
            'role="button"',
            'aria-label="' . escapeHtml($label) . '"',
            'tabindex="0"'
        ];
        
        if ($ariaExpanded !== null) {
            $attrs[] = 'aria-expanded="' . ($ariaExpanded ? 'true' : 'false') . '"';
        }
        
        return implode(' ', $attrs);
    }
    
    /**
     * Generate accessible chart title
     */
    public static function getChartTitle($title) {
        return sprintf(
            '<h3 id="chart-title" class="sr-only">%s</h3>',
            escapeHtml($title)
        );
    }
    
    /**
     * Generate accessible table headers
     */
    public static function getTableHeader($text, $scope = 'col', $sortable = false, $sortDir = null) {
        $attrs = [sprintf('scope="%s"', escapeHtml($scope))];
        
        if ($sortable) {
            $attrs[] = 'role="button"';
            $attrs[] = 'tabindex="0"';
            $attrs[] = 'aria-sort="' . ($sortDir === 'asc' ? 'ascending' : ($sortDir === 'desc' ? 'descending' : 'none')) . '"';
        }
        
        return sprintf(
            '<th %s>%s%s</th>',
            implode(' ', $attrs),
            escapeHtml($text),
            $sortable ? ' <i class="bi bi-arrow-down-up" aria-hidden="true"></i>' : ''
        );
    }
    
    /**
     * Generate accessible status badge
     */
    public static function getAccessibleBadge($status, $color = 'info') {
        $statusMap = [
            'Active' => 'Active - Record is currently in use',
            'Inactive' => 'Inactive - Record is not currently in use',
            'Pending' => 'Pending - Record is awaiting action',
            'Completed' => 'Completed - Task has finished',
            'Cancelled' => 'Cancelled - Record has been cancelled'
        ];
        
        $title = $statusMap[$status] ?? $status;
        
        return sprintf(
            '<span class="badge-status" data-status="%s" role="status" aria-label="%s">%s</span>',
            escapeHtml($status),
            escapeHtml($title),
            escapeHtml($status)
        );
    }
    
    /**
     * Generate accessible loading spinner
     */
    public static function getLoadingSpinner($label = 'Loading') {
        return sprintf(
            '<div class="spinner-border" role="status" aria-label="%s" aria-hidden="false">
                <span class="visually-hidden">%s...</span>
            </div>',
            escapeHtml($label),
            escapeHtml($label)
        );
    }
    
    /**
     * Generate accessible alert
     */
    public static function getAccessibleAlert($type, $title, $message, $dismissible = true) {
        $roleMap = [
            'error' => 'alert',
            'warning' => 'alert',
            'info' => 'region',
            'success' => 'status'
        ];
        
        $role = $roleMap[$type] ?? 'status';
        
        $dismissBtn = $dismissible ? '<button type="button" class="btn-close" aria-label="Close alert"></button>' : '';
        
        return sprintf(
            '<div class="alert-box alert-%s" role="%s" aria-live="polite" aria-atomic="true">
                <div class="alert-box-title">%s</div>
                <div class="alert-box-text">%s</div>
                %s
            </div>',
            escapeHtml($type),
            $role,
            escapeHtml($title),
            escapeHtml($message),
            $dismissBtn
        );
    }
    
    /**
     * Generate skip link for keyboard navigation
     */
    public static function getSkipLink() {
        return '<a href="#main-content" class="skip-link">Skip to main content</a>';
    }
    
    /**
     * Generate accessible form group
     */
    public static function getFormGroup($name, $label, $type = 'text', $value = '', $required = false, $error = null) {
        $attrs = [
            'type="' . escapeHtml($type) . '"',
            'name="' . escapeHtml($name) . '"',
            'id="' . escapeHtml($name) . '"',
            'value="' . escapeHtml($value) . '"'
        ];
        
        if ($required) {
            $attrs[] = 'required';
            $attrs[] = 'aria-required="true"';
        }
        
        if (!empty($error)) {
            $attrs[] = 'aria-invalid="true"';
            $attrs[] = 'aria-describedby="' . escapeHtml($name) . '-error"';
        }
        
        return sprintf(
            '<div class="form-group">
                <label for="%s">%s%s</label>
                <input %s>
                %s
            </div>',
            escapeHtml($name),
            escapeHtml($label),
            $required ? ' <span aria-label="required">*</span>' : '',
            implode(' ', $attrs),
            !empty($error) ? '<small id="' . escapeHtml($name) . '-error" class="form-error">' . escapeHtml($error) . '</small>' : ''
        );
    }
    
    /**
     * Generate color-blind friendly status indicator
     */
    public static function getColorBlindIndicator($status, $value = null) {
        $indicators = [
            'good' => ['icon' => 'bi-check-circle-fill', 'text' => '✓ Good', 'aria' => 'Status is good'],
            'warning' => ['icon' => 'bi-exclamation-triangle-fill', 'text' => '⚠ Warning', 'aria' => 'Status needs attention'],
            'critical' => ['icon' => 'bi-x-circle-fill', 'text' => '✗ Critical', 'aria' => 'Status is critical']
        ];
        
        $indicator = $indicators[$status] ?? $indicators['warning'];
        
        return sprintf(
            '<span class="status-indicator" aria-label="%s">
                <i class="bi %s" aria-hidden="true"></i>
                <span class="status-text">%s</span>
                %s
            </span>',
            escapeHtml($indicator['aria']),
            escapeHtml($indicator['icon']),
            escapeHtml($indicator['text']),
            $value ? '<span class="status-value">' . escapeHtml($value) . '</span>' : ''
        );
    }
    
    /**
     * Generate keyboard shortcut help
     */
    public static function getKeyboardHelp() {
        return '
            <div class="keyboard-help" role="complementary" aria-label="Keyboard shortcuts">
                <h4>Keyboard Shortcuts</h4>
                <ul>
                    <li><kbd>Tab</kbd> - Navigate between elements</li>
                    <li><kbd>Enter</kbd> - Activate button or link</li>
                    <li><kbd>Space</kbd> - Toggle checkbox or button</li>
                    <li><kbd>Esc</kbd> - Close modals or menus</li>
                    <li><kbd>?</kbd> - Show this help (when focused on page)</li>
                </ul>
            </div>
        ';
    }
    
    /**
     * Generate language tag for content
     */
    public static function getLanguageTag($content, $lang = 'en') {
        return sprintf(
            '<span lang="%s">%s</span>',
            escapeHtml($lang),
            escapeHtml($content)
        );
    }
    
    /**
     * Validate accessibility score
     */
    public static function validateAccessibility($html) {
        $issues = [];
        
        // Check for img without alt
        if (preg_match('/<img[^>]*(?!alt=)[^>]*>/i', $html)) {
            $issues[] = 'Images missing alt attributes';
        }
        
        // Check for empty links
        if (preg_match('/<a[^>]*href="[^"]*"[^>]*>\s*<\/a>/i', $html)) {
            $issues[] = 'Empty links detected';
        }
        
        // Check for missing form labels
        if (preg_match('/<input[^>]*(?!id=)[^>]*>/i', $html)) {
            $issues[] = 'Form inputs missing IDs';
        }
        
        return [
            'accessible' => empty($issues),
            'issues' => $issues
        ];
    }
}

?>
