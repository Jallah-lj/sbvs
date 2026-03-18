<?php
/**
 * Branches Management Interface
 * 
 * Provides CRUD operations for branch management with role-based access control.
 * - Super Admin: Full CRUD capabilities
 * - Branch Admin: Read-only access
 * - Other roles: No access (redirected)
 * 
 * @package SBVS
 * @subpackage Dashboard
 * @version 2.0
 */

declare(strict_types=1);

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

ob_start();
session_start();

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php", true, 302);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../../DashboardSecurity.php';
require_once __DIR__ . '/models/Branch.php';

// Initialize database and models
$db = (new Database())->getConnection();
$branchModel = new Branch($db);

// ============================================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================================

$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['username'] ?? 'Unknown';

$isSuperAdmin = ($userRole === 'Super Admin');
$isBranchAdmin = ($userRole === 'Branch Admin');
$canViewBranches = ($isSuperAdmin || $isBranchAdmin);
$canManageBranches = $isSuperAdmin;

// Enforce access policy
if (!$canViewBranches) {
    DashboardSecurity::auditLog(
        'branches',
        'access_denied',
        sprintf('User %s (ID: %d, Role: %s) attempted to access branches page', $userName, $userId, $userRole),
        $db
    );
    header("Location: dashboard.php", true, 302);
    exit;
}

// ============================================================================
// CONFIGURATION
// ============================================================================

const ALLOWED_STATUSES = ['Active', 'Inactive'];
const ALLOWED_PER_PAGE = [10, 25, 50, 100];
const DEFAULT_PER_PAGE = 10;
const DEFAULT_PAGE = 1;

const VALIDATION_RULES = [
    'name' => ['min' => 3, 'max' => 120],
    'address' => ['min' => 5, 'max' => 255],
    'phone' => ['min' => 7, 'max' => 20],
    'email' => ['max' => 255]
];

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Normalize branch input data
 * 
 * @param array $input Raw POST data
 * @return array Sanitized and normalized data
 */
function normalizeBranchPayload(array $input): array
{
    return [
        'name' => preg_replace('/\s+/', ' ', trim(sanitizeInput($input['name'] ?? ''))),
        'address' => preg_replace('/\s+/', ' ', trim(sanitizeInput($input['address'] ?? ''))),
        'phone' => trim($input['phone'] ?? ''),
        'email' => strtolower(trim($input['email'] ?? '')),
        'status' => $input['status'] ?? 'Active'
    ];
}

/**
 * Validate branch data against business rules
 * 
 * @param array $data Normalized branch data
 * @param Branch $branchModel Branch model instance
 * @param int|null $excludeId ID to exclude from uniqueness checks (for updates)
 * @return string Empty string if valid, error message otherwise
 */
function validateBranchPayload(array $data, Branch $branchModel, ?int $excludeId = null): string
{
    // Name validation
    $nameLength = mb_strlen($data['name']);
    if ($nameLength < VALIDATION_RULES['name']['min'] || $nameLength > VALIDATION_RULES['name']['max']) {
        return sprintf(
            'Branch name must be between %d and %d characters.',
            VALIDATION_RULES['name']['min'],
            VALIDATION_RULES['name']['max']
        );
    }

    // Address validation
    $addressLength = mb_strlen($data['address']);
    if ($addressLength < VALIDATION_RULES['address']['min'] || $addressLength > VALIDATION_RULES['address']['max']) {
        return sprintf(
            'Address must be between %d and %d characters.',
            VALIDATION_RULES['address']['min'],
            VALIDATION_RULES['address']['max']
        );
    }

    // Email validation
    if (!isValidEmail($data['email'])) {
        return 'Please provide a valid email address.';
    }

    if (mb_strlen($data['email']) > VALIDATION_RULES['email']['max']) {
        return sprintf('Email must not exceed %d characters.', VALIDATION_RULES['email']['max']);
    }

    // Phone validation
    $phonePattern = '/^[0-9+()\-\s]{' . VALIDATION_RULES['phone']['min'] . ',' . VALIDATION_RULES['phone']['max'] . '}$/';
    if (!preg_match($phonePattern, $data['phone'])) {
        return sprintf(
            'Phone must be between %d and %d characters and contain only numbers, spaces, +, -, or parentheses.',
            VALIDATION_RULES['phone']['min'],
            VALIDATION_RULES['phone']['max']
        );
    }

    // Status validation
    if (!in_array($data['status'], ALLOWED_STATUSES, true)) {
        return 'Invalid status selected. Must be either Active or Inactive.';
    }

    // Uniqueness checks
    if ($branchModel->nameExists($data['name'], $excludeId)) {
        return 'A branch with this name already exists. Please choose a different name.';
    }

    if ($branchModel->emailExists($data['email'], $excludeId)) {
        return 'A branch with this email already exists. Please use a different email address.';
    }

    return '';
}

/**
 * Build URL with query parameters for pagination and filtering
 * 
 * @param array $overrides Parameters to override
 * @return string Complete URL with query string
 */
function buildBranchesUrl(array $overrides = []): string
{
    $baseParams = [
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? '',
        'per_page' => $_GET['per_page'] ?? (string)DEFAULT_PER_PAGE,
        'page' => $_GET['page'] ?? (string)DEFAULT_PAGE
    ];

    $params = array_merge($baseParams, $overrides);

    // Remove empty parameters
    $params = array_filter($params, fn($value) => $value !== '' && $value !== null);

    $queryString = http_build_query($params);
    return 'branches.php' . ($queryString ? '?' . $queryString : '');
}

/**
 * Filter branches based on search and status criteria
 * 
 * @param array $branches All branches
 * @param string $searchQuery Search term
 * @param string $statusFilter Status filter
 * @return array Filtered branches
 */
function filterBranches(array $branches, string $searchQuery, string $statusFilter): array
{
    return array_values(array_filter($branches, function ($branch) use ($searchQuery, $statusFilter) {
        // Search filter
        if ($searchQuery !== '') {
            $searchableText = strtolower(implode(' ', [
                $branch['name'] ?? '',
                $branch['address'] ?? '',
                $branch['phone'] ?? '',
                $branch['email'] ?? ''
            ]));

            if (strpos($searchableText, strtolower($searchQuery)) === false) {
                return false;
            }
        }

        // Status filter
        if ($statusFilter !== '' && ($branch['status'] ?? '') !== $statusFilter) {
            return false;
        }

        return true;
    }));
}

/**
 * Export branches to CSV format
 * 
 * @param array $branches Branches to export
 * @return void
 */
function exportBranchesToCsv(array $branches): void
{
    ob_clean();

    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="branches_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // CSV headers
    fputcsv($output, ['ID', 'Name', 'Address', 'Phone', 'Email', 'Status', 'Created']);

    // Data rows
    foreach ($branches as $branch) {
        fputcsv($output, [
            $branch['id'] ?? '',
            $branch['name'] ?? '',
            $branch['address'] ?? '',
            $branch['phone'] ?? '',
            $branch['email'] ?? '',
            $branch['status'] ?? '',
            $branch['created_at'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Calculate statistics from branches
 * 
 * @param array $branches Branches to analyze
 * @return array Statistics array
 */
function calculateBranchStatistics(array $branches): array
{
    $stats = [
        'total' => count($branches),
        'active' => 0,
        'inactive' => 0
    ];

    foreach ($branches as $branch) {
        if (($branch['status'] ?? '') === 'Active') {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }

    return $stats;
}

// ============================================================================
// REQUEST HANDLING
// ============================================================================

$successMessage = '';
$errorMessage = '';

// Handle POST requests (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Permission check
    if (!$canManageBranches) {
        $errorMessage = "You do not have permission to modify branches.";
        DashboardSecurity::auditLog(
            'branches',
            'unauthorized_modification_attempt',
            sprintf('User %s (ID: %d, Role: %s) attempted unauthorized branch modification', $userName, $userId, $userRole),
            $db
        );
    }
    // CSRF validation
    elseif (!DashboardSecurity::verifyToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = "Security validation failed. Please refresh the page and try again.";
        DashboardSecurity::auditLog(
            'branches',
            'csrf_validation_failed',
            sprintf('CSRF token validation failed for user %s (ID: %d)', $userName, $userId),
            $db
        );
    }
    // Process valid requests
    else {
        $action = $_POST['action'];

        switch ($action) {
            case 'create':
                $branchData = normalizeBranchPayload($_POST);
                $validationError = validateBranchPayload($branchData, $branchModel);

                if ($validationError !== '') {
                    $errorMessage = $validationError;
                    DashboardSecurity::auditLog(
                        'branches',
                        'create_validation_failed',
                        sprintf('Validation failed for branch "%s": %s', $branchData['name'], $validationError),
                        $db
                    );
                } elseif ($branchModel->create($branchData)) {
                    $successMessage = "Branch created successfully!";
                    DashboardSecurity::auditLog(
                        'branches',
                        'create_success',
                        sprintf('Created branch: %s (%s) - Status: %s', $branchData['name'], $branchData['email'], $branchData['status']),
                        $db
                    );
                } else {
                    $errorMessage = "Failed to create branch. Please try again.";
                    DashboardSecurity::auditLog(
                        'branches',
                        'create_database_error',
                        sprintf('Database error while creating branch: %s', $branchData['name']),
                        $db
                    );
                }
                break;

            case 'update':
                if (empty($_POST['id'])) {
                    $errorMessage = "Invalid branch ID.";
                    break;
                }

                $branchId = (int)$_POST['id'];
                $branchData = normalizeBranchPayload($_POST);
                $existingBranch = $branchModel->getById($branchId);
                
                if (!$existingBranch) {
                    $errorMessage = "Branch not found.";
                    break;
                }

                $validationError = validateBranchPayload($branchData, $branchModel, $branchId);

                if ($validationError !== '') {
                    $errorMessage = $validationError;
                    DashboardSecurity::auditLog(
                        'branches',
                        'update_validation_failed',
                        sprintf('Validation failed for branch ID %d: %s', $branchId, $validationError),
                        $db
                    );
                } elseif ($branchModel->update($branchId, $branchData)) {
                    $successMessage = "Branch updated successfully!";
                    DashboardSecurity::auditLog(
                        'branches',
                        'update_success',
                        sprintf('Updated branch ID %d: "%s" → "%s", Status: %s', 
                            $branchId, 
                            $existingBranch['name'], 
                            $branchData['name'], 
                            $branchData['status']
                        ),
                        $db
                    );
                } else {
                    $errorMessage = "Failed to update branch. Please try again.";
                    DashboardSecurity::auditLog(
                        'branches',
                        'update_database_error',
                        sprintf('Database error while updating branch ID %d', $branchId),
                        $db
                    );
                }
                break;

            case 'delete':
                if (empty($_POST['id'])) {
                    $errorMessage = "Invalid branch ID.";
                    break;
                }

                $branchId = (int)$_POST['id'];
                $existingBranch = $branchModel->getById($branchId);

                if (!$existingBranch) {
                    $errorMessage = "Branch not found.";
                    break;
                }

                if ($branchModel->delete($branchId)) {
                    $successMessage = "Branch deactivated successfully.";
                    DashboardSecurity::auditLog(
                        'branches',
                        'deactivate_success',
                        sprintf('Deactivated branch ID %d (%s)', $branchId, $existingBranch['name']),
                        $db
                    );
                } else {
                    $errorMessage = "Failed to deactivate branch. Please try again.";
                    DashboardSecurity::auditLog(
                        'branches',
                        'deactivate_database_error',
                        sprintf('Database error while deactivating branch ID %d', $branchId),
                        $db
                    );
                }
                break;

            default:
                $errorMessage = "Invalid action specified.";
                DashboardSecurity::auditLog(
                    'branches',
                    'invalid_action',
                    sprintf('Invalid action "%s" attempted by user %s (ID: %d)', $action, $userName, $userId),
                    $db
                );
        }
    }

    // Redirect to prevent form resubmission (PRG pattern)
    if ($successMessage || $errorMessage) {
        $_SESSION['branch_success'] = $successMessage;
        $_SESSION['branch_error'] = $errorMessage;
        header("Location: " . buildBranchesUrl(), true, 303);
        exit;
    }
}

// Retrieve flash messages
if (isset($_SESSION['branch_success'])) {
    $successMessage = $_SESSION['branch_success'];
    unset($_SESSION['branch_success']);
}
if (isset($_SESSION['branch_error'])) {
    $errorMessage = $_SESSION['branch_error'];
    unset($_SESSION['branch_error']);
}

// ============================================================================
// DATA RETRIEVAL & FILTERING
// ============================================================================

// Fetch all branches
$allBranches = $branchModel->getAll();

// Get filter parameters
$searchQuery = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// Validate status filter
if (!in_array($statusFilter, array_merge([''], ALLOWED_STATUSES), true)) {
    $statusFilter = '';
}

// Apply filters
$filteredBranches = filterBranches($allBranches, $searchQuery, $statusFilter);

// Handle CSV export
if (($_GET['export'] ?? '') === 'csv') {
    DashboardSecurity::auditLog(
        'branches',
        'export_csv',
        sprintf('User %s (ID: %d) exported %d branches to CSV', $userName, $userId, count($filteredBranches)),
        $db
    );
    exportBranchesToCsv($filteredBranches);
}

// Calculate statistics
$statistics = calculateBranchStatistics($filteredBranches);

// ============================================================================
// PAGINATION
// ============================================================================

$perPage = (int)($_GET['per_page'] ?? DEFAULT_PER_PAGE);
if (!in_array($perPage, ALLOWED_PER_PAGE, true)) {
    $perPage = DEFAULT_PER_PAGE;
}

$totalRecords = count($filteredBranches);
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

$currentPage = max(1, min((int)($_GET['page'] ?? DEFAULT_PAGE), $totalPages));

$offset = ($currentPage - 1) * $perPage;
$visibleBranches = array_slice($filteredBranches, $offset, $perPage);

// ============================================================================
// VIEW PREPARATION
// ============================================================================

$pageTitle = 'Branches';
$activePage = 'branches.php';
$extraCss = '
<style>
    .branches-page-title {
        letter-spacing: -0.02em;
        font-size: 1.75rem;
    }

    .branch-avatar-icon {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
    }

    .branch-modal-card {
        border-radius: 16px;
        overflow: hidden;
    }

    .modal-header-accent {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .modal-header-warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .badge-custom {
        font-weight: 500;
        border-radius: 20px;
    }

    .btn-action {
        padding: 0.375rem 0.625rem;
        border-radius: 6px;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-action.btn-edit {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .btn-action.btn-edit:hover {
        background-color: #bbdefb;
        transform: translateY(-1px);
    }

    .btn-action.btn-delete {
        background-color: #ffebee;
        color: #c62828;
    }

    .btn-action.btn-delete:hover {
        background-color: #ffcdd2;
        transform: translateY(-1px);
    }

    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s ease;
    }

    @media (max-width: 768px) {
        .branches-page-title {
            font-size: 1.5rem;
        }
    }
</style>
';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main">

        <!-- ============================================================ -->
        <!-- PAGE HEADER -->
        <!-- ============================================================ -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="mb-0 fw-bold branches-page-title">
                    <i class="bi bi-building me-2 text-primary"></i>Branches
                </h2>
                <p class="text-muted small mb-0 mt-1">
                    <?php if ($isSuperAdmin): ?>
                        Create and manage all vocational training branches.
                    <?php else: ?>
                        View registered branches. Contact the Super Admin to make changes.
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($isSuperAdmin): ?>
                <button class="btn btn-primary shadow-sm rounded-pill px-4 d-flex align-items-center" 
                        data-bs-toggle="modal" 
                        data-bs-target="#addBranchModal">
                    <i class="bi bi-plus-circle-fill me-2"></i> Add New Branch
                </button>
            <?php else: ?>
                <span class="badge bg-secondary badge-custom fs-6 px-3 py-2">
                    <i class="bi bi-eye-fill me-2"></i>View Only Mode
                </span>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- ALERT MESSAGES -->
        <!-- ============================================================ -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$isSuperAdmin): ?>
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-3 mb-3">
                <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
                <div>
                    <strong>Read-Only Mode</strong><br>
                    <span class="small">You are viewing branches in read-only mode. Only the Super Admin can add, edit, or delete branches.</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- FILTERS & SEARCH -->
        <!-- ============================================================ -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form method="GET" action="branches.php" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="searchInput" class="form-label fw-semibold mb-1">
                            <i class="bi bi-search me-1"></i>Search
                        </label>
                        <input type="text" 
                               id="searchInput"
                               name="q" 
                               class="form-control" 
                               placeholder="Search by name, address, phone, or email" 
                               value="<?= escapeHtml($searchQuery) ?>"
                               aria-label="Search branches">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="statusFilter" class="form-label fw-semibold mb-1">
                            <i class="bi bi-filter me-1"></i>Status
                        </label>
                        <select id="statusFilter" name="status" class="form-select" aria-label="Filter by status">
                            <option value="">All Statuses</option>
                            <?php foreach (ALLOWED_STATUSES as $status): ?>
                                <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="perPageSelect" class="form-label fw-semibold mb-1">
                            <i class="bi bi-list-ol me-1"></i>Rows
                        </label>
                        <select id="perPageSelect" name="per_page" class="form-select" aria-label="Items per page">
                            <?php foreach (ALLOWED_PER_PAGE as $option): ?>
                                <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>>
                                    <?= $option ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel-fill me-1"></i>Apply
                        </button>
                    </div>
                </form>

                <!-- Statistics & Actions -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3 pt-3 border-top">
                    <div class="text-muted small">
                        Showing <strong><?= $totalRecords > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalRecords) ?></strong> 
                        of <strong><?= number_format($totalRecords) ?></strong> branches
                    </div>
                    
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge rounded-pill text-bg-success" 
                              title="Active branches available for operations">
                            <i class="bi bi-check-circle-fill me-1"></i>Active: <?= $statistics['active'] ?>
                        </span>
                        <span class="badge rounded-pill text-bg-secondary" 
                              title="Inactive branches not accepting new activity">
                            <i class="bi bi-pause-circle-fill me-1"></i>Inactive: <?= $statistics['inactive'] ?>
                        </span>
                        <a href="branches.php" class="btn btn-outline-secondary btn-sm" title="Clear all filters">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </a>
                        <a href="<?= escapeHtml(buildBranchesUrl(['export' => 'csv'])) ?>" 
                           class="btn btn-outline-success btn-sm"
                           title="Export filtered results to CSV">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- BRANCHES TABLE -->
        <!-- ============================================================ -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-list-ul me-2"></i>Registered Branches
                </h6>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-center" style="width: 60px;">#</th>
                                <th scope="col">Branch Name</th>
                                <th scope="col">Address</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Email</th>
                                <th scope="col" style="width: 140px;">Status</th>
                                <?php if ($isSuperAdmin): ?>
                                    <th scope="col" class="text-end pe-4" style="width: 120px;">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visibleBranches)): ?>
                                <tr>
                                    <td colspan="<?= $isSuperAdmin ? 7 : 6 ?>" class="text-center text-muted py-5">
                                        <i class="bi bi-buildings opacity-50 display-4 d-block mb-3"></i>
                                        <p class="mb-0">No branches found matching your criteria.</p>
                                        <?php if ($searchQuery || $statusFilter): ?>
                                            <a href="branches.php" class="btn btn-sm btn-outline-secondary mt-2">
                                                Clear Filters
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($visibleBranches as $index => $branch): ?>
                                    <tr>
                                        <td class="text-center text-muted">
                                            <?= $offset + $index + 1 ?>
                                        </td>
                                        
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3 branch-avatar-icon">
                                                    <i class="bi bi-building text-primary fs-5"></i>
                                                </div>
                                                <span class="fw-semibold text-dark">
                                                    <?= escapeHtml($branch['name']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <span class="text-muted">
                                                <i class="bi bi-geo-alt me-1 text-secondary"></i>
                                                <?= escapeHtml($branch['address']) ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <a href="tel:<?= escapeHtml($branch['phone']) ?>" 
                                               class="text-decoration-none text-dark">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?= escapeHtml($branch['phone']) ?>
                                            </a>
                                        </td>
                                        
                                        <td>
                                            <a href="mailto:<?= escapeHtml($branch['email']) ?>" 
                                               class="text-decoration-none text-primary">
                                                <i class="bi bi-envelope me-1"></i>
                                                <?= escapeHtml($branch['email']) ?>
                                            </a>
                                        </td>
                                        
                                        <td>
                                            <?php if (($branch['status'] ?? '') === 'Active'): ?>
                                                <span class="badge rounded-pill text-bg-success" 
                                                      title="Operational and accepting new activity">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill text-bg-secondary" 
                                                      title="Deactivated from new activity">
                                                    <i class="bi bi-pause-circle-fill me-1"></i>Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <?php if ($isSuperAdmin): ?>
                                            <td>
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button class="btn btn-action btn-edit"
                                                            title="Edit branch details"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editBranchModal"
                                                            data-id="<?= (int)$branch['id'] ?>"
                                                            data-name="<?= escapeHtml($branch['name']) ?>"
                                                            data-address="<?= escapeHtml($branch['address']) ?>"
                                                            data-phone="<?= escapeHtml($branch['phone']) ?>"
                                                            data-email="<?= escapeHtml($branch['email']) ?>"
                                                            data-status="<?= escapeHtml($branch['status']) ?>"
                                                            aria-label="Edit <?= escapeHtml($branch['name']) ?>">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </button>
                                                    
                                                    <form method="POST" 
                                                          action="branches.php" 
                                                          class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to deactivate this branch? It will no longer accept new activity.');">
                                                        <?= DashboardSecurity::getTokenField() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$branch['id'] ?>">
                                                        <button type="submit" 
                                                                class="btn btn-action btn-delete" 
                                                                title="Deactivate branch"
                                                                aria-label="Deactivate <?= escapeHtml($branch['name']) ?>">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ============================================================ -->
                <!-- PAGINATION -->
                <!-- ============================================================ -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-top">
                        <small class="text-muted">
                            Page <?= $currentPage ?> of <?= $totalPages ?>
                        </small>
                        
                        <nav aria-label="Branches pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Button -->
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="<?= $currentPage > 1 ? escapeHtml(buildBranchesUrl(['page' => $currentPage - 1])) : '#' ?>"
                                       aria-label="Previous page"
                                       <?= $currentPage <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                </li>

                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);

                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= escapeHtml(buildBranchesUrl(['page' => 1])) ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">…</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" 
                                           href="<?= escapeHtml(buildBranchesUrl(['page' => $p])) ?>"
                                           <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">…</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= escapeHtml(buildBranchesUrl(['page' => $totalPages])) ?>">
                                            <?= $totalPages ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Button -->
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="<?= $currentPage < $totalPages ? escapeHtml(buildBranchesUrl(['page' => $currentPage + 1])) : '#' ?>"
                                       aria-label="Next page"
                                       <?= $currentPage >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<?php if ($isSuperAdmin): ?>
    <!-- ============================================================ -->
    <!-- ADD BRANCH MODAL -->
    <!-- ============================================================ -->
    <div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="branches.php" class="modal-content border-0 shadow-lg branch-modal-card">
                <?= DashboardSecurity::getTokenField() ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header modal-header-accent border-bottom-0 pb-4">
                    <h5 class="modal-title fw-bold" id="addBranchModalLabel">
                        <i class="bi bi-plus-circle-fill me-2 opacity-75"></i>Add New Branch
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body pt-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="addBranchName" class="form-label fw-semibold">
                                Branch Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="addBranchName"
                                   name="name" 
                                   class="form-control" 
                                   placeholder="e.g., Main Campus, Downtown Branch" 
                                   required 
                                   minlength="<?= VALIDATION_RULES['name']['min'] ?>"
                                   maxlength="<?= VALIDATION_RULES['name']['max'] ?>"
                                   aria-required="true">
                            <div class="form-text">
                                <?= VALIDATION_RULES['name']['min'] ?>-<?= VALIDATION_RULES['name']['max'] ?> characters
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label for="addBranchAddress" class="form-label fw-semibold">
                                Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="addBranchAddress"
                                   name="address" 
                                   class="form-control" 
                                   placeholder="e.g., 123 Main Street, City, Country" 
                                   required 
                                   minlength="<?= VALIDATION_RULES['address']['min'] ?>"
                                   maxlength="<?= VALIDATION_RULES['address']['max'] ?>"
                                   aria-required="true">
                            <div class="form-text">
                                Full physical address
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="addBranchPhone" class="form-label fw-semibold">
                                Phone <span class="text-danger">*</span>
                            </label>
                            <input type="tel" 
                                   id="addBranchPhone"
                                   name="phone" 
                                   class="form-control" 
                                   placeholder="e.g., +123 456 7890" 
                                   required 
                                   pattern="[0-9+()\-\s]{<?= VALIDATION_RULES['phone']['min'] ?>,<?= VALIDATION_RULES['phone']['max'] ?>}"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="addBranchEmail" class="form-label fw-semibold">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   id="addBranchEmail"
                                   name="email" 
                                   class="form-control" 
                                   placeholder="e.g., branch@company.com" 
                                   required 
                                   maxlength="<?= VALIDATION_RULES['email']['max'] ?>"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-12">
                            <label for="addBranchStatus" class="form-label fw-semibold">
                                Status
                            </label>
                            <select id="addBranchStatus" name="status" class="form-select">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <div class="form-text">
                                Set to Active to allow operations, or Inactive to disable
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Branch
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- EDIT BRANCH MODAL -->
    <!-- ============================================================ -->
    <div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="branches.php" class="modal-content border-0 shadow-lg branch-modal-card">
                <?= DashboardSecurity::getTokenField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header modal-header-warning border-bottom-0 pb-4">
                    <h5 class="modal-title fw-bold" id="editBranchModalLabel">
                        <i class="bi bi-pencil-square me-2 opacity-75"></i>Edit Branch
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body pt-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="edit_name" class="form-label fw-semibold">
                                Branch Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="edit_name"
                                   name="name" 
                                   class="form-control" 
                                   required 
                                   minlength="<?= VALIDATION_RULES['name']['min'] ?>"
                                   maxlength="<?= VALIDATION_RULES['name']['max'] ?>"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-12">
                            <label for="edit_address" class="form-label fw-semibold">
                                Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="edit_address"
                                   name="address" 
                                   class="form-control" 
                                   required 
                                   minlength="<?= VALIDATION_RULES['address']['min'] ?>"
                                   maxlength="<?= VALIDATION_RULES['address']['max'] ?>"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_phone" class="form-label fw-semibold">
                                Phone <span class="text-danger">*</span>
                            </label>
                            <input type="tel" 
                                   id="edit_phone"
                                   name="phone" 
                                   class="form-control" 
                                   required 
                                   pattern="[0-9+()\-\s]{<?= VALIDATION_RULES['phone']['min'] ?>,<?= VALIDATION_RULES['phone']['max'] ?>}"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label fw-semibold">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   id="edit_email"
                                   name="email" 
                                   class="form-control" 
                                   required 
                                   maxlength="<?= VALIDATION_RULES['email']['max'] ?>"
                                   aria-required="true">
                        </div>
                        
                        <div class="col-12">
                            <label for="edit_status" class="form-label fw-semibold">
                                Status
                            </label>
                            <select id="edit_status" name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save me-1"></i>Update Branch
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Edit Modal Population Script -->
    <script>
    (function() {
        'use strict';
        
        const editModal = document.getElementById('editBranchModal');
        
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                if (!button) return;
                
                // Populate form fields
                const fields = {
                    'edit_id': button.dataset.id,
                    'edit_name': button.dataset.name,
                    'edit_address': button.dataset.address,
                    'edit_phone': button.dataset.phone,
                    'edit_email': button.dataset.email,
                    'edit_status': button.dataset.status
                };
                
                for (const [fieldId, value] of Object.entries(fields)) {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.value = value || '';
                    }
                }
            });
        }
    })();
    </script>
<?php else: ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

</body>
</html>