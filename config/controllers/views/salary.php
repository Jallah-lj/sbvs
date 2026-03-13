<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}
require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    header("Location: dashboard.php"); exit;
}

$branches = [];
$branchName = '';
if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($sessionBranch) {
        $bStmt = $db->prepare("SELECT name FROM branches WHERE id=?");
        $bStmt->execute([$sessionBranch]);
        $branchName = $bStmt->fetchColumn() ?: '';
    }
}
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Salary';
$activePage = 'salary.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 no-print">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><i class="bi bi-cash-coin me-2 text-primary"></i>Salary Management</h2>
                <p class="text-muted small mb-0 mt-1">Configure employee salaries, grades, components, and process payrolls.</p>
                <?php if (!$isSuperAdmin && $branchName): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info mt-2 px-3 py-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isSuperAdmin || $isBranchAdmin): ?>
            <div>
                <button class="btn btn-primary shadow-sm fw-semibold px-3" onclick="openRunModal()">
                    <i class="bi bi-play-circle-fill me-1"></i> New Payroll Run
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4 no-print" id="kpiRow">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-4">
                        <div class="kpi-icon kpi-blue"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-dark" style="letter-spacing: -0.02em;" id="kpiProfiled">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Profiled Employees</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-4">
                        <div class="kpi-icon kpi-green"><i class="bi bi-safe2-fill"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-success" style="letter-spacing: -0.02em;" id="kpiBudget">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Salary Budget</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-4">
                        <div class="kpi-icon kpi-orange"><i class="bi bi-hourglass-bottom"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-warning" style="letter-spacing: -0.02em;" id="kpiPending">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Pending Payout</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-4">
                        <div class="kpi-icon kpi-purple"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder" style="color:#d63384; letter-spacing: -0.02em;" id="kpiPaidMonth">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Paid This Month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Nav -->
        <ul class="nav nav-tabs mb-4 no-print border-0" id="salaryTabs">
            <?php if ($isSuperAdmin): ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabGrades" style="border-radius:8px;">
                    <i class="bi bi-layers me-2"></i>Grades
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabComponents" style="border-radius:8px;">
                    <i class="bi bi-list-check me-2"></i>Components
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabProfiles" style="border-radius:8px;">
                    <i class="bi bi-person-badge me-2"></i>Employee Salaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tabRuns" style="border-radius:8px;">
                    <i class="bi bi-play-circle me-2"></i>Payroll Runs
                </a>
            </li>
        </ul>

        <div class="tab-content no-print">

            <!-- ── GRADES TAB ─────────────────────────────────────────────── -->
            <?php if ($isSuperAdmin): ?>
            <div class="tab-pane fade" id="tabGrades">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-4 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-layers opacity-75 me-2"></i>Salary Grades / Pay Bands</h5>
                        <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" onclick="openGradeModal()">
                            <i class="bi bi-plus-lg me-1"></i> Add Grade
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100 mb-0" id="gradesTable">
                                <thead>
                                    <tr><th>Grade</th><th>Level</th><th>Min Salary</th><th>Max Salary</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── COMPONENTS TAB ─────────────────────────────────────────── -->
            <div class="tab-pane fade" id="tabComponents">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-4 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-list-check opacity-75 me-2"></i>Salary Components</h5>
                        <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" onclick="openComponentModal()">
                            <i class="bi bi-plus-lg me-1"></i> Add Component
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100 mb-0" id="componentsTable">
                                <thead>
                                    <tr><th>Name</th><th>Code</th><th>Type</th><th>Calc</th><th>Value</th><th>Applies To</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── EMPLOYEE SALARIES TAB ───────────────────────────────────── -->
            <div class="tab-pane fade" id="tabProfiles">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-4 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge opacity-75 me-2"></i>Employee Salary Profiles</h5>
                        <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" onclick="openProfileModal()">
                            <i class="bi bi-person-plus-fill me-1"></i> Assign Salary
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100 mb-0" id="profilesTable">
                                <thead>
                                    <tr><th>Employee</th><th>Role</th><th>Branch</th><th>Grade</th><th>Basic</th><th>Payment Mode</th><th>Effective</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── PAYROLL RUNS TAB ───────────────────────────────────────── -->
            <div class="tab-pane fade show active" id="tabRuns">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-4 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-play-circle opacity-75 me-2"></i>Payroll Runs</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100 mb-0" id="runsTable">
                                <thead>
                                    <tr><th>Period</th><th>Branch</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /tab-content -->
    </main>
</div>

<!-- ══ MODALS ═══════════════════════════════════════════════════════════════ -->

<!-- Grade Modal -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="gradeForm" class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #0dcaf0 0%, #009ef7 100%);">
                <h5 class="modal-title fw-bold" id="gradeModalTitle"><i class="bi bi-layers me-2"></i>Add Salary Grade</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="grade_id">
                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Grade Name <span class="text-danger">*</span></label>
                        <input type="text" id="grade_name" class="form-control form-control-lg fs-6" placeholder="e.g. Senior Lecturer" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Level <span class="text-danger">*</span></label>
                        <input type="number" id="grade_level" class="form-control form-control-lg fs-6" min="1" value="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Min Salary ($)</label>
                        <input type="number" id="grade_min" class="form-control form-control-lg fs-6" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Max Salary ($)</label>
                        <input type="number" id="grade_max" class="form-control form-control-lg fs-6" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Description</label>
                        <textarea id="grade_desc" class="form-control form-control-lg fs-6" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Status</label>
                        <select id="grade_status" class="form-select form-select-lg fs-6">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light p-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" id="gradeSaveBtn"><i class="bi bi-save me-1"></i>Save Grade</button>
            </div>
        </form>
    </div>
</div>

<!-- Component Modal -->
<div class="modal fade" id="componentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="componentForm" class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #198754 0%, #4facfe 100%);">
                <h5 class="modal-title fw-bold" id="componentModalTitle"><i class="bi bi-list-check me-2"></i>Add Salary Component</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="comp_id">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Name <span class="text-danger">*</span></label>
                        <input type="text" id="comp_name" class="form-control form-control-lg fs-6" placeholder="e.g. Housing Allowance" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Code <span class="text-danger">*</span></label>
                        <input type="text" id="comp_code" class="form-control form-control-lg fs-6" placeholder="e.g. HRA" style="text-transform:uppercase" required>
                        <div class="form-text mt-1">Unique, uppercase, no spaces</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Type</label>
                        <select id="comp_type" class="form-select form-select-lg fs-6">
                            <option value="Earning">Earning</option>
                            <option value="Deduction">Deduction</option>
                            <option value="Tax">Tax</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Calculation</label>
                        <select id="comp_calc" class="form-select form-select-lg fs-6" onchange="togglePctOf()">
                            <option value="Fixed">Fixed Amount</option>
                            <option value="Percentage">Percentage</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Value <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" id="comp_value" class="form-control fs-6" min="0" step="0.0001" value="0" required>
                            <span class="input-group-text fs-6" id="compValueSuffix">$</span>
                        </div>
                    </div>
                    <div class="col-md-6" id="pctOfWrapper" style="display:none;">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Percentage Of</label>
                        <select id="comp_pct_of" class="form-select form-select-lg fs-6">
                            <option value="basic_salary">Basic Salary</option>
                            <option value="gross_salary">Gross Salary</option>
                        </select>
                        <div class="form-text mt-1">Base for percentage calculation</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Applies To</label>
                        <select id="comp_applies_to" class="form-select form-select-lg fs-6">
                            <option value="All">All Roles</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Admin">Admin</option>
                            <option value="Branch Admin">Branch Admin</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Sort Order</label>
                        <input type="number" id="comp_sort" class="form-control form-control-lg fs-6" value="0" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Status</label>
                        <select id="comp_status" class="form-select form-select-lg fs-6">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex flex-column justify-content-center gap-2 pt-2">
                        <div class="form-check form-switch bg-light p-2 rounded w-100 d-flex justify-content-between m-0">
                            <label class="form-check-label fw-semibold" for="comp_taxable">Taxable</label>
                            <input class="form-check-input m-0 float-none ms-2" type="checkbox" id="comp_taxable" checked>
                        </div>
                        <div class="form-check form-switch bg-light p-2 rounded w-100 d-flex justify-content-between m-0">
                            <label class="form-check-label fw-semibold" for="comp_mandatory">Mandatory</label>
                            <input class="form-check-input m-0 float-none ms-2" type="checkbox" id="comp_mandatory">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light p-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-bold" id="compSaveBtn"><i class="bi bi-save me-1"></i>Save Component</button>
            </div>
        </form>
    </div>
</div>

<!-- Employee Salary Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="profileForm" class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #1e1e2d 0%, #151521 100%);">
                <h5 class="modal-title fw-bold" id="profileModalTitle"><i class="bi bi-person-badge me-2 text-info"></i>Assign Salary Profile</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" id="prof_id">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold mb-3 text-uppercase small" style="letter-spacing:1px;">General Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Employee <span class="text-danger">*</span></label>
                                <select id="prof_user_id" class="form-select form-select-lg fs-6 bg-light" required onchange="onEmployeeChange()">
                                    <option value="">-- Select Employee --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Salary Grade <span class="text-primary">(Optional)</span></label>
                                <select id="prof_grade_id" class="form-select form-select-lg fs-6 bg-light" onchange="applyGradeRange()">
                                    <option value="">-- Custom Grade --</option>
                                </select>
                                <div class="form-text mt-1 text-primary fw-medium" id="gradeRangeHint"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Basic Salary ($) <span class="text-danger">*</span></label>
                                <input type="number" id="prof_basic" class="form-control form-control-lg fs-6 bg-light" min="0.01" step="0.01" placeholder="0.00" required oninput="triggerPreview()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Effective Date <span class="text-danger">*</span></label>
                                <input type="date" id="prof_eff_date" class="form-control form-control-lg fs-6 bg-light" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Status</label>
                                <select id="prof_status" class="form-select form-select-lg fs-6 bg-light">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold mb-3 text-uppercase small" style="letter-spacing:1px;">Payment & Bank Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Payment Mode</label>
                                <select id="prof_pay_mode" class="form-select form-select-lg fs-6 bg-light">
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Bank Name</label>
                                <input type="text" id="prof_bank_name" class="form-control form-control-lg fs-6 bg-light" placeholder="e.g. LBS Bank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Account Number</label>
                                <input type="text" id="prof_acct_no" class="form-control form-control-lg fs-6 bg-light" placeholder="e.g. 1234567890">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Component Overrides -->
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
                    <div class="card-body p-4">
                        <h6 class="text-info mb-3 fw-bold text-uppercase small" style="letter-spacing:1px;">
                            <i class="bi bi-sliders me-1"></i>Component Overrides <span class="text-muted fw-normal ms-2 text-capitalize" style="letter-spacing:0;">(leave blank to use global defaults)</span>
                        </h6>
                        <div id="overridesContainer">
                            <p class="text-muted small mb-0"><i class="bi bi-arrow-return-right me-1"></i>Select an employee to load relevant salary components…</p>
                        </div>
                    </div>
                </div>

                <!-- Live Salary Preview -->
                <div class="card border-0 shadow-sm border-start border-4 border-success" id="previewSection" style="display:none;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-success mb-0 fw-bold text-uppercase small" style="letter-spacing:1px;">
                                <i class="bi bi-calculator me-1"></i>Live Salary Preview
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-semibold px-3" onclick="triggerPreview()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-4">
                                <div class="bg-success bg-opacity-10 rounded p-3 text-center h-100" style="border:1px dashed rgba(25,135,84,0.3)">
                                    <div class="small text-muted fw-semibold text-uppercase" style="letter-spacing:0.5px">Gross Pay</div>
                                    <div class="fs-4 fw-bolder text-success mt-1" id="prevGross">$0.00</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-danger bg-opacity-10 rounded p-3 text-center h-100" style="border:1px dashed rgba(220,53,69,0.3)">
                                    <div class="small text-muted fw-semibold text-uppercase" style="letter-spacing:0.5px">Total Deductions</div>
                                    <div class="fs-4 fw-bolder text-danger mt-1" id="prevDed">$0.00</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-primary bg-opacity-10 rounded p-3 text-center h-100" style="border:1px dashed rgba(13,110,253,0.3)">
                                    <div class="small text-muted fw-semibold text-uppercase" style="letter-spacing:0.5px">Net Salary</div>
                                    <div class="fs-4 fw-bolder text-primary mt-1" id="prevNet">$0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive rounded border border-light">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-uppercase text-muted" style="font-size:0.7rem; letter-spacing:1px;">Component</th>
                                        <th class="text-uppercase text-muted" style="font-size:0.7rem; letter-spacing:1px;">Classification</th>
                                        <th class="text-end pe-3 text-uppercase text-muted" style="font-size:0.7rem; letter-spacing:1px;">Amount ($)</th>
                                    </tr>
                                </thead>
                                <tbody id="prevLines"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0 p-4">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" id="profSaveBtn"><i class="bi bi-save me-1"></i>Save Salary Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Payroll Run Modal -->
<div class="modal fade" id="runModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="runForm" class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #1e1e2d 0%, #3f4254 100%);">
                <h5 class="modal-title fw-bold"><i class="bi bi-play-circle-fill me-2 text-info"></i>Create Payroll Run</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted font-monospace small">Select Branch</label>
                        <select id="run_branch_id" class="form-select form-select-lg fs-6 bg-light">
                            <option value="">All Branches (Global)</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted font-monospace small">Pay Month <span class="text-danger">*</span></label>
                        <select id="run_month" class="form-select form-select-lg fs-6 bg-light" required>
                            <?php foreach ($months as $i => $m): ?>
                            <option value="<?= $i+1 ?>" <?= ($i+1)===$currentMonth?'selected':'' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted font-monospace small">Pay Year <span class="text-danger">*</span></label>
                        <input type="number" id="run_year" class="form-control form-control-lg fs-6 bg-light" value="<?= $currentYear ?>" min="2000" max="2099" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted font-monospace small">Generation Date <span class="text-danger">*</span></label>
                        <input type="date" id="run_pay_date" class="form-control form-control-lg fs-6 bg-light" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted font-monospace small">Description / Notes</label>
                        <textarea id="run_notes" class="form-control bg-light fs-6" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 p-3 bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold shadow-sm" id="runSaveBtn"><i class="bi bi-play-fill me-1"></i>Generate Payroll</button>
            </div>
        </form>
    </div>
</div>

<!-- Void Run Modal -->
<div class="modal fade" id="voidRunModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="voidRunForm" class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #dc3545 0%, #f87171 100%);">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-octagon-fill me-2"></i>Void Payroll Run</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <input type="hidden" id="void_run_id">
                <div class="my-3 text-danger"><i class="bi bi-exclamation-triangle-fill" style="font-size:3rem"></i></div>
                <p class="text-danger fw-bold fs-5 mb-1">Are you absolutely sure?</p>
                <p class="text-muted small mb-4">This will permanently void all payslips generated in this run. This action cannot be undone.</p>
                <div class="text-start">
                    <label class="form-label fw-semibold text-muted small text-uppercase">Void Reason <span class="text-danger">*</span></label>
                    <textarea id="void_reason" class="form-control form-control-lg fs-6 bg-light border-danger text-danger" rows="3" placeholder="Provide a detailed reason..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-top-0 p-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="bi bi-x-octagon me-1"></i>Confirm Void</button>
            </div>
        </form>
    </div>
</div>

<!-- Slips Modal (list per run) -->
<div class="modal fade" id="slipsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 text-white" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Payslips — <span id="slipsModalTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100 border-0" id="slipsTable">
                        <thead class="table-light text-muted small text-uppercase" style="letter-spacing:0.5px">
                            <tr><th>Employee</th><th>Role</th><th>Branch</th><th>Grade</th><th>Basic</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Mode</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light p-3">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payslip Detail / Print Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" id="payslipPrintArea">
            <div class="modal-header payslip-header no-print border-bottom-0 text-white" style="background: linear-gradient(135deg, #1e1e2d 0%, #151521 100%);">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2 text-info"></i>Pay Slip</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="payslipBody">
                <!-- Injected by JS -->
            </div>
            <div class="modal-footer no-print border-top-0 p-3 bg-white">
                <button class="btn btn-light fw-bold" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary fw-bold px-4 shadow-sm" onclick="window.print()"><i class="bi bi-printer-fill me-2"></i>Print Payslip</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const IS_SA  = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const IS_BA  = <?= $isBranchAdmin ? 'true' : 'false' ?>;
const API    = '../views/models/api/salary_api.php';
const fmt    = n => '$' + parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

let gradesData = [], componentsData = [], employeesData = [], previewTimer = null;

// ── DataTables ──────────────────────────────────────────────────────────────
let gradesTable, componentsTable, profilesTable, runsTable, slipsTable;

$(function() {
    loadKPI();
    <?php if ($isSuperAdmin): ?>
    loadGrades();
    loadComponents();
    <?php endif; ?>
    loadProfiles();
    loadRuns();
    loadEmployeeOptions();
    loadGradeOptions();
    $('[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        const t = $(e.target).attr('href');
        if (t === '#tabGrades' && gradesTable)    gradesTable.columns.adjust();
        if (t === '#tabComponents' && componentsTable) componentsTable.columns.adjust();
        if (t === '#tabProfiles' && profilesTable)   profilesTable.columns.adjust();
        if (t === '#tabRuns' && runsTable)           runsTable.columns.adjust();
    });
    $('#comp_name').on('input', function() {
        if (!$('#comp_id').val()) {
            $('#comp_code').val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,''));
        }
    });
});

// ── KPI ────────────────────────────────────────────────────────────────────
function loadKPI() {
    $.getJSON(API + '?action=stats', function(res) {
        if (!res.success) return;
        const d = res.data;
        $('#kpiProfiled').text(d.profiled_employees || 0);
        $('#kpiBudget').text(fmt(d.total_basic_budget));
        $('#kpiPending').text(fmt(d.pending_net_payout));
        $('#kpiPaidMonth').text(fmt(d.paid_this_month));
    });
}

// ── GRADES ──────────────────────────────────────────────────────────────────
function loadGrades() {
    $.getJSON(API + '?action=grades_list', function(res) {
        if (!res.success) return;
        gradesData = res.data;
        if (gradesTable) gradesTable.destroy();
        const tbody = res.data.map(g => `<tr style="transition:all 0.2s hover:bg-light">
            <td>
                <div class="d-flex flex-column">
                    <span class="fw-bold text-dark mb-1">${g.name}</span>
                    ${g.description ? `<span class="text-muted small">${g.description}</span>` : ''}
                </div>
            </td>
            <td><span class="badge bg-light text-dark border px-2 py-1 shadow-sm">Grade <strong>${g.level}</strong></span></td>
            <td class="fw-semibold text-secondary">${fmt(g.min_salary)}</td>
            <td class="fw-semibold text-secondary">${fmt(g.max_salary)}</td>
            <td><span class="badge ${g.status==='Active'?'bg-success':'bg-secondary'} bg-opacity-75 px-2 py-1">${g.status}</span></td>
            <td>
                <div class="btn-group shadow-sm">
                    <button class="btn btn-sm btn-light border-end text-warning hover-elevate" onclick="editGrade(${g.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-light text-danger hover-elevate" onclick="deleteGrade(${g.id},'${g.name.replace(/'/g,"\\'")}')" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </td></tr>`).join('');
        $('#gradesTable tbody').html(tbody);
        gradesTable = $('#gradesTable').DataTable({retrieve:true, responsive:true, pageLength:10, language:{emptyTable:'No grades defined yet.'}});
    });
}
function loadGradeOptions() {
    $.getJSON(API + '?action=grades_list', function(res) {
        if (!res.success) return;
        let opts = '<option value="">-- None --</option>';
        res.data.filter(g=>g.status==='Active').forEach(g => {
            opts += `<option value="${g.id}" data-min="${g.min_salary}" data-max="${g.max_salary}">${g.name} (L${g.level}) — ${fmt(g.min_salary)}–${fmt(g.max_salary)}</option>`;
        });
        $('#prof_grade_id').html(opts);
    });
}
function openGradeModal(id) {
    $('#grade_id').val(''); $('#gradeForm')[0].reset();
    $('#gradeModalTitle').text(id ? 'Edit Salary Grade' : 'Add Salary Grade');
    if (id) {
        const g = gradesData.find(x=>x.id==id);
        if (!g) return;
        $('#grade_id').val(g.id); $('#grade_name').val(g.name); $('#grade_level').val(g.level);
        $('#grade_min').val(g.min_salary); $('#grade_max').val(g.max_salary);
        $('#grade_desc').val(g.description||''); $('#grade_status').val(g.status);
    }
    new bootstrap.Modal(document.getElementById('gradeModal')).show();
}
function editGrade(id) { openGradeModal(id); }
function deleteGrade(id, name) {
    Swal.fire({title:'Delete Grade?',text:`Delete "${name}"?`,icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',confirmButtonText:'Delete'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=grade_delete',{id},function(res){ res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadGrades(), loadGradeOptions()) : Swal.fire('Error',res.message,'error'); },'json'); });
}
$('#gradeForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#grade_id').val();
    const data = {name:$('#grade_name').val(),level:$('#grade_level').val(),min_salary:$('#grade_min').val(),max_salary:$('#grade_max').val(),description:$('#grade_desc').val(),status:$('#grade_status').val()};
    if (id) data.id = id;
    $.post(API+'?action='+(id?'grade_update':'grade_save'), data, function(res) {
        if (res.success) { bootstrap.Modal.getInstance(document.getElementById('gradeModal'))?.hide(); loadGrades(); loadGradeOptions(); Swal.fire({icon:'success',title:'Saved',timer:1500,showConfirmButton:false}); }
        else Swal.fire('Error', res.message, 'error');
    }, 'json');
});

// ── COMPONENTS ──────────────────────────────────────────────────────────────
function loadComponents() {
    $.getJSON(API + '?action=components_list', function(res) {
        if (!res.success) return;
        componentsData = res.data;
        if (componentsTable) componentsTable.destroy();
        const typeColor = {Earning:'success',Deduction:'danger',Tax:'warning'};
        const tbody = res.data.map(c => `<tr>
            <td class="fw-bold text-dark">${c.name}</td>
            <td><code class="bg-light px-2 py-1 rounded border text-muted small">${c.code}</code></td>
            <td><span class="badge bg-${typeColor[c.type]} bg-opacity-10 text-${typeColor[c.type]} border border-${typeColor[c.type]} border-opacity-25 px-2 py-1">${c.type}</span></td>
            <td><span class="badge bg-light text-dark border"><i class="bi bi-${c.calc_type==='Percentage'?'percent':'currency-dollar'} me-1"></i>${c.calc_type}</span></td>
            <td class="fw-semibold">${c.calc_type==='Percentage' ? c.value+'% <span class="text-muted fw-normal small">of '+c.percentage_of.replace('_',' ')+'</span>' : fmt(c.value)}</td>
            <td><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">${c.applies_to}</span></td>
            <td><span class="badge ${c.status==='Active'?'bg-success':'bg-secondary'} bg-opacity-75 px-2 py-1">${c.status}</span></td>
            <td>
                <div class="btn-group shadow-sm">
                    <button class="btn btn-sm btn-light border-end text-warning" onclick="editComponent(${c.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-light text-danger" onclick="deleteComponent(${c.id},'${c.name.replace(/'/g,"\\'")}')" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </td></tr>`).join('');

        $('#componentsTable tbody').html(tbody);
        componentsTable = $('#componentsTable').DataTable({retrieve:true, responsive:true, pageLength:15, language:{emptyTable:'No components defined yet.'}});
    });
}
function openComponentModal(id) {
    $('#comp_id').val(''); $('#componentForm')[0].reset(); $('#comp_taxable').prop('checked',true);
    $('#componentModalTitle').text(id ? 'Edit Component' : 'Add Salary Component');
    togglePctOf();
    if (id) {
        const c = componentsData.find(x=>x.id==id);
        if (!c) return;
        $('#comp_id').val(c.id); $('#comp_name').val(c.name); $('#comp_code').val(c.code);
        $('#comp_type').val(c.type); $('#comp_calc').val(c.calc_type); $('#comp_value').val(c.value);
        $('#comp_pct_of').val(c.percentage_of||'basic_salary'); $('#comp_taxable').prop('checked',!!c.taxable);
        $('#comp_mandatory').prop('checked',!!c.is_mandatory); $('#comp_applies_to').val(c.applies_to);
        $('#comp_sort').val(c.sort_order); $('#comp_status').val(c.status);
        togglePctOf();
    }
    new bootstrap.Modal(document.getElementById('componentModal')).show();
}
function editComponent(id) { openComponentModal(id); }
function deleteComponent(id, name) {
    Swal.fire({title:'Delete Component?',text:`Delete "${name}"?`,icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',confirmButtonText:'Delete'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=component_delete',{id},function(res){ res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadComponents(), loadComponentOverrideInputs()) : Swal.fire('Error',res.message,'error'); },'json'); });
}
function togglePctOf() {
    const isPct = $('#comp_calc').val() === 'Percentage';
    $('#pctOfWrapper').toggle(isPct);
    $('#compValueSuffix').text(isPct ? '%' : '$');
}
$('#componentForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#comp_id').val();
    const data = {name:$('#comp_name').val(),code:$('#comp_code').val(),type:$('#comp_type').val(),
        calc_type:$('#comp_calc').val(),value:$('#comp_value').val(),percentage_of:$('#comp_pct_of').val(),
        applies_to:$('#comp_applies_to').val(),sort_order:$('#comp_sort').val(),status:$('#comp_status').val()};
    if ($('#comp_taxable').is(':checked'))   data.taxable = 1;
    if ($('#comp_mandatory').is(':checked')) data.is_mandatory = 1;
    if (id) data.id = id;
    $.post(API+'?action='+(id?'component_update':'component_save'), data, function(res) {
        if (res.success) { bootstrap.Modal.getInstance(document.getElementById('componentModal'))?.hide(); loadComponents(); Swal.fire({icon:'success',title:'Saved',timer:1500,showConfirmButton:false}); }
        else Swal.fire('Error', res.message, 'error');
    }, 'json');
});

// ── EMPLOYEE PROFILES ───────────────────────────────────────────────────────
function loadEmployeeOptions() {
    $.getJSON(API + '?action=employees_list', function(res) {
        if (!res.success) return;
        employeesData = res.data;
        let opts = '<option value="">-- Select Employee --</option>';
        res.data.forEach(e => {
            opts += `<option value="${e.id}" data-role="${e.employee_role||e.role}" data-branch="${e.branch_id}">${e.name} (${e.role}) — ${e.branch_name||''}</option>`;
        });
        $('#prof_user_id').html(opts);
    });
}
function loadProfiles() {
    $.getJSON(API + '?action=profiles_list', function(res) {
        if (!res.success) return;
        if (profilesTable) profilesTable.destroy();
        const tbody = res.data.map(p => `<tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold shadow-sm" style="width:40px;height:40px;min-width:40px;">${p.employee_name.charAt(0)}</div>
                    <div>
                        <div class="fw-bold text-dark" style="white-space:nowrap;">${p.employee_name}</div>
                        <div class="text-muted small">${p.email}</div>
                    </div>
                </div>
            </td>
            <td><span class="badge bg-light text-dark border px-2 py-1 shadow-sm"><i class="bi bi-briefcase text-muted me-1"></i>${p.employee_role}</span></td>
            <td><span class="text-muted small"><i class="bi bi-shop me-1"></i>${p.branch_name}</span></td>
            <td>${p.grade_name ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1" style="white-space:nowrap;">${p.grade_name} (L${p.grade_level})</span>` : '<span class="text-muted small fst-italic">Custom</span>'}</td>
            <td class="fw-bolder fs-6 text-dark">${fmt(p.basic_salary)}</td>
            <td><span class="small fw-semibold text-secondary"><i class="bi bi-${p.payment_mode==='Cash'?'cash-coin':'bank'} text-muted me-1"></i>${p.payment_mode}</span></td>
            <td class="text-muted small">${p.effective_date}</td>
            <td><span class="badge ${p.status==='Active'?'bg-success':'bg-secondary'} bg-opacity-75 px-2 py-1">${p.status}</span></td>
            <td>
                <div class="btn-group shadow-sm">
                    <button class="btn btn-sm btn-light border-end text-primary" onclick="viewProfileCalc(${p.id})" title="View Preview"><i class="bi bi-calculator-fill"></i></button>
                    <button class="btn btn-sm btn-light border-end text-warning" onclick="editProfile(${p.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-light text-danger" onclick="deleteProfile(${p.id},'${p.employee_name.replace(/'/g,"\\'")}')" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </td></tr>`).join('');

        $('#profilesTable tbody').html(tbody);
        profilesTable = $('#profilesTable').DataTable({retrieve:true, responsive:true, pageLength:15, language:{emptyTable:'No salary profiles assigned yet.'}});
    });
}
function openProfileModal(id) {
    $('#prof_id').val(''); $('#profileForm')[0].reset();
    $('#previewSection').hide(); $('#overridesContainer').html('<p class="text-muted small">Select an employee to load components…</p>');
    $('#profileModalTitle').text(id ? 'Edit Salary Profile' : 'Assign Salary Profile');
    $('#prof_eff_date').val('<?= date('Y-m-d') ?>');
    if (!id) { new bootstrap.Modal(document.getElementById('profileModal')).show(); return; }
    $.getJSON(API + '?action=profile_get&id='+id, function(res) {
        if (!res.success) { Swal.fire('Error',res.message,'error'); return; }
        const d = res.data;
        $('#prof_id').val(d.id); $('#prof_user_id').val(d.user_id).prop('disabled',true);
        $('#prof_grade_id').val(d.grade_id||''); $('#prof_basic').val(d.basic_salary);
        $('#prof_eff_date').val(d.effective_date); $('#prof_pay_mode').val(d.payment_mode);
        $('#prof_bank_name').val(d.bank_name||''); $('#prof_acct_no').val(d.account_number||'');
        $('#prof_status').val(d.status); $('#prof_notes').val(d.notes||'');
        if (d.grade_id) applyGradeRange();
        loadComponentOverrideInputs(d.user_id, d.overrides, function() {
            showPreviewResult(d.preview);
        });
        new bootstrap.Modal(document.getElementById('profileModal')).show();
    });
}
function editProfile(id) { openProfileModal(id); }
function deleteProfile(id, name) {
    Swal.fire({title:'Delete Profile?',text:`Delete salary profile for "${name}"?`,icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',confirmButtonText:'Delete'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=profile_delete',{id},function(res){ res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadProfiles(), loadKPI()) : Swal.fire('Error',res.message,'error'); },'json'); });
}
function viewProfileCalc(id) { openProfileModal(id); }
function onEmployeeChange() {
    const uid = $('#prof_user_id').val();
    loadComponentOverrideInputs(uid, {}, function(){});
    triggerPreview();
}
function loadComponentOverrideInputs(uid, existingOverrides, cb) {
    if (!uid) { $('#overridesContainer').html('<p class="text-muted small">Select an employee to load components…</p>'); return; }
    const empRow = employeesData.find(e=>e.id==uid);
    const empRole = empRow ? (empRow.role||empRow.employee_role) : 'All';
    $.getJSON(API + '?action=components_list', function(res) {
        if (!res.success || !res.data.length) { $('#overridesContainer').html('<p class="text-muted">No active components defined.</p>'); if(cb) cb(); return; }
        const active = res.data.filter(c=>c.status==='Active');
        if (!active.length) { $('#overridesContainer').html('<p class="text-muted">No active components.</p>'); if(cb) cb(); return; }
        const overrides = existingOverrides || {};
        let html = '<div class="row g-2">';
        active.forEach(c => {
            const ov = overrides[c.id] !== undefined ? overrides[c.id] : '';
            const suffix = c.calc_type === 'Percentage' ? '%' : '$';
            const placeholder = c.calc_type === 'Percentage' ? c.value+'% (global)' : fmt(c.value)+' (global)';
            html += `<div class="col-md-4">
                <label class="form-label small fw-bold mb-1">
                    <span class="badge badge-${c.type.toLowerCase()} text-white me-1" style="font-size:0.65rem">${c.type}</span>${c.name}
                </label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control override-input" data-comp-id="${c.id}"
                           placeholder="${placeholder}" value="${ov}" min="0" step="0.01" oninput="triggerPreview()">
                    <span class="input-group-text">${suffix}</span>
                </div>
            </div>`;
        });
        html += '</div>';
        $('#overridesContainer').html(html);
        if(cb) cb();
    });
}
function applyGradeRange() {
    const sel = $('#prof_grade_id option:selected');
    const min = sel.data('min'), max = sel.data('max');
    if (min !== undefined && max !== undefined) {
        $('#gradeRangeHint').text('Range: ' + fmt(min) + ' – ' + fmt(max));
    } else {
        $('#gradeRangeHint').text('');
    }
}
function triggerPreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(doPreview, 500);
}
function doPreview() {
    const uid = $('#prof_user_id').val();
    const basic = parseFloat($('#prof_basic').val())||0;
    if (!uid || basic <= 0) { $('#previewSection').hide(); return; }
    const overrides = {};
    $('.override-input').each(function() {
        const val = $(this).val();
        if (val !== '') overrides[$(this).data('comp-id')] = val;
    });
    $.post(API+'?action=preview_salary', {user_id:uid, basic_salary:basic, overrides:JSON.stringify(overrides)}, function(res) {
        if (res.success) showPreviewResult(res);
    }, 'json');
}
function showPreviewResult(res) {
    if (!res) return;
    $('#prevGross').text(fmt(res.gross)); $('#prevDed').text(fmt(res.deductions)); $('#prevNet').text(fmt(res.net));
    const lines = (res.lines||[]).map(l => {
        const cls = l.component_type==='Earning' ? 'text-success' : 'text-danger';
        const sign = l.component_type==='Earning' ? '+' : '-';
        return `<tr><td>${l.component_name} <code class="small">${l.component_code}</code></td>
            <td><span class="badge badge-${l.component_type.toLowerCase()} text-white">${l.component_type}</span></td>
            <td class="text-end fw-bold ${cls}">${sign}${fmt(l.amount)}</td></tr>`;
    }).join('');
    $('#prevLines').html(lines || '<tr><td colspan="3" class="text-muted text-center">No components</td></tr>');
    $('#previewSection').show();
}
$('#profileForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#prof_id').val();
    const overrides = {};
    $('.override-input').each(function() {
        const val = $(this).val();
        if (val !== '') overrides[$(this).data('comp-id')] = val;
    });
    const data = {
        user_id: $('#prof_user_id').val(), grade_id: $('#prof_grade_id').val()||'',
        basic_salary: $('#prof_basic').val(), effective_date: $('#prof_eff_date').val(),
        payment_mode: $('#prof_pay_mode').val(), bank_name: $('#prof_bank_name').val(),
        account_number: $('#prof_acct_no').val(), status: $('#prof_status').val(),
        notes: $('#prof_notes').val(), overrides: JSON.stringify(overrides)
    };
    if (id) data.id = id;
    const action = id ? 'profile_update' : 'profile_save';
    const btn = $('#profSaveBtn').prop('disabled',true).html('<i class="bi bi-hourglass-split me-1"></i>Saving…');
    $.post(API+'?action='+action, data, function(res) {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('profileModal'))?.hide();
            $('#prof_user_id').prop('disabled',false);
            loadProfiles(); loadKPI(); loadEmployeeOptions();
            Swal.fire({icon:'success',title:'Saved',timer:1500,showConfirmButton:false});
        } else Swal.fire('Error', res.message, 'error');
    }, 'json').always(()=>$('#profSaveBtn').prop('disabled',false).html('<i class="bi bi-save me-1"></i>Save Profile'));
});
$('#profileModal').on('hidden.bs.modal', function() {
    $('#prof_user_id').prop('disabled', false);
});

// ── PAYROLL RUNS ────────────────────────────────────────────────────────────
function loadRuns() {
    $.getJSON(API + '?action=runs_list', function(res) {
        if (!res.success) return;
        if (runsTable) runsTable.destroy();
        const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        const statusColors = {Draft:'secondary',Processed:'warning',Paid:'success',Voided:'danger'};
        const tbody = res.data.map(r => {
            const period = months[r.pay_period_month]+' '+r.pay_period_year;
            const branch = r.branch_name ? `<span class="badge bg-light text-dark border px-2 py-1 shadow-sm"><i class="bi bi-shop text-muted me-1"></i>${r.branch_name}</span>` : '<span class="badge bg-dark px-2 py-1 shadow-sm"><i class="bi bi-globe me-1"></i>All Branches</span>';
            let actions = `<button class="btn btn-sm btn-light border-end text-primary hover-elevate" onclick="viewSlips(${r.id},'${period}')" title="View Payslips"><i class="bi bi-file-earmark-text-fill"></i></button>`;
            if (r.status==='Draft') actions += `<button class="btn btn-sm btn-light border-end text-success hover-elevate" onclick="processRun(${r.id})" title="Generate Slips"><i class="bi bi-play-circle-fill"></i></button>`;
            if (r.status==='Processed' && (IS_SA||IS_BA)) actions += `<button class="btn btn-sm btn-light border-end text-info hover-elevate" onclick="markPaid(${r.id})" title="Mark as Paid"><i class="bi bi-check-circle-fill"></i></button>`;
            if (r.status!=='Voided' && IS_SA) actions += `<button class="btn btn-sm btn-light text-danger hover-elevate" onclick="voidRun(${r.id})" title="Void Run"><i class="bi bi-x-octagon-fill"></i></button>`;
            return `<tr>
                <td>
                    <div class="d-flex flex-column" style="white-space: nowrap;">
                        <strong class="text-dark mb-1">${period}</strong>
                        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>${r.pay_date}</span>
                    </div>
                </td>
                <td>${branch}</td>
                <td><span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="bi bi-people-fill me-1"></i>${r.slip_count} Slips</span></td>
                <td class="fw-bold text-secondary">${fmt(r.total_gross)}</td>
                <td class="fw-bold text-danger">(${fmt(r.total_deductions)})</td>
                <td class="fw-bolder text-success fs-6">${fmt(r.total_net)}</td>
                <td><span class="badge bg-${statusColors[r.status]||'secondary'} bg-opacity-75 shadow-sm px-2 py-1 rounded-pill">${r.status}</span></td>
                <td><div class="btn-group shadow-sm rounded">${actions}</div></td></tr>`;
        }).join('');
        $('#runsTable tbody').html(tbody);
        runsTable = $('#runsTable').DataTable({retrieve:true, responsive:true, pageLength:12, order:[[0,'desc']], language:{emptyTable:'No payroll runs yet.'}});
    });
}
function openRunModal() {
    $('#runForm')[0].reset();
    $('#run_month').val('<?= $currentMonth ?>');
    $('#run_year').val('<?= $currentYear ?>');
    $('#run_pay_date').val('<?= date('Y-m-d') ?>');
    new bootstrap.Modal(document.getElementById('runModal')).show();
}
$('#runForm').on('submit', function(e) {
    e.preventDefault();
    const data = {month:$('#run_month').val(),year:$('#run_year').val(),pay_date:$('#run_pay_date').val(),notes:$('#run_notes').val()};
    <?php if ($isSuperAdmin): ?>data.branch_id = $('#run_branch_id').val();<?php endif; ?>
    $.post(API+'?action=run_create', data, function(res) {
        if (res.success) { bootstrap.Modal.getInstance(document.getElementById('runModal'))?.hide(); loadRuns(); Swal.fire({icon:'success',title:'Draft Created',timer:1500,showConfirmButton:false}); }
        else Swal.fire('Error', res.message, 'error');
    }, 'json');
});
function processRun(runId) {
    Swal.fire({title:'Process Payroll?',text:'This will generate payslips for all active salary profiles in this run scope.',icon:'question',showCancelButton:true,confirmButtonColor:'#198754',confirmButtonText:'Process'})
    .then(r => { if (!r.isConfirmed) return;
        Swal.fire({title:'Processing…',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        $.post(API+'?action=run_process',{run_id:runId},function(res){
            if(res.success){Swal.fire({icon:'success',title:'Done!',html:`Generated <strong>${res.slip_count}</strong> payslips.<br>Total Net: <strong>${fmt(res.total_net)}</strong>`,timer:3000}); loadRuns(); loadKPI();}
            else Swal.fire('Error',res.message,'error');
        },'json');
    });
}
function markPaid(runId) {
    Swal.fire({title:'Mark as Paid?',text:'All slips in this run will be marked as Paid.',icon:'question',showCancelButton:true,confirmButtonColor:'#0d6efd',confirmButtonText:'Mark Paid'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=run_mark_paid',{run_id:runId},function(res){
            res.success ? (Swal.fire({icon:'success',title:'Marked Paid',timer:1500,showConfirmButton:false}), loadRuns(), loadKPI()) : Swal.fire('Error',res.message,'error');
        },'json');
    });
}
function voidRun(runId) {
    $('#void_run_id').val(runId); $('#void_reason').val('');
    new bootstrap.Modal(document.getElementById('voidRunModal')).show();
}
$('#voidRunForm').on('submit', function(e) {
    e.preventDefault();
    $.post(API+'?action=run_void',{run_id:$('#void_run_id').val(),void_reason:$('#void_reason').val()},function(res){
        if(res.success){ bootstrap.Modal.getInstance(document.getElementById('voidRunModal'))?.hide(); loadRuns(); loadKPI(); Swal.fire({icon:'success',title:'Voided',timer:1500,showConfirmButton:false}); }
        else Swal.fire('Error',res.message,'error');
    },'json');
});

// ── SLIPS ───────────────────────────────────────────────────────────────────
function viewSlips(runId, period) {
    $('#slipsModalTitle').text(period);
    $.getJSON(API + '?action=slips_list&run_id='+runId, function(res) {
        if (!res.success) return;
        const statusColors = {Pending:'warning',Paid:'success',Voided:'danger'};
        const tbody = res.data.map(s => `<tr>
            <td>
                <div class="d-flex flex-column" style="white-space: nowrap;">
                    <span class="fw-bold text-dark d-block mb-1">${s.employee_name}</span>
                    <span class="text-muted small">${s.email}</span>
                </div>
            </td>
            <td><span class="badge bg-light text-dark border px-2 py-1 shadow-sm">${s.employee_role}</span></td>
            <td><span class="text-muted small" style="white-space: nowrap;">${s.branch_name}</span></td>
            <td>${s.grade_name ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1" style="white-space: nowrap;">${s.grade_name}</span>` : '<span class="text-muted">—</span>'}</td>
            <td class="text-secondary fw-semibold">${fmt(s.basic_salary)}</td>
            <td class="text-dark fw-semibold">${fmt(s.gross_salary)}</td>
            <td class="text-danger fw-semibold">(${fmt(s.total_deductions)})</td>
            <td class="fw-bolder text-success fs-6">${fmt(s.net_salary)}</td>
            <td><span class="small fw-semibold text-secondary" style="white-space: nowrap;"><i class="bi bi-${s.payment_mode==='Cash'?'cash-coin':'bank'} text-muted me-1"></i>${s.payment_mode}</span></td>
            <td><span class="badge bg-${statusColors[s.status]||'secondary'} bg-opacity-75 px-2 py-1 rounded-pill">${s.status}</span></td>
            <td><button class="btn btn-sm btn-light text-primary border shadow-sm hover-elevate rounded" onclick="viewPayslip(${s.id})" title="View Payslip"><i class="bi bi-receipt"></i></button></td>
        </tr>`).join('');
        if (slipsTable) slipsTable.destroy();
        $('#slipsTable tbody').html(tbody);
        slipsTable = $('#slipsTable').DataTable({retrieve:true, responsive:true, pageLength:25, language:{emptyTable:'No payslips in this run.'}});
        new bootstrap.Modal(document.getElementById('slipsModal')).show();
    });
}
function viewPayslip(slipId) {
    $.getJSON(API + '?action=slip_detail&slip_id='+slipId, function(res) {
        if (!res.success) { Swal.fire('Error',res.message,'error'); return; }
        const d = res.data;
        const mNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        const earnings = d.lines.filter(l=>l.component_type==='Earning');
        const deductions = d.lines.filter(l=>l.component_type!=='Earning');
        const earningsRows = earnings.map(l=>`<tr><td>${l.component_name}</td><td class="text-end fw-bold text-success">${fmt(l.amount)}</td></tr>`).join('');
        const deductionRows = deductions.map(l=>`<tr><td>${l.component_name}</td><td class="text-end fw-bold text-danger">(${fmt(l.amount)})</td></tr>`).join('');
        const html = `
        <div class="card border-0 shadow-sm mb-0">
            <div class="card-header border-bottom-0 text-white p-4" style="background: linear-gradient(135deg, #1e1e2d 0%, #151521 100%); border-radius: 0.5rem 0.5rem 0 0;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <img src="../../assets/img/logo.svg" alt="Logo" width="48" height="60">
                        <div>
                            <h3 class="mb-1 fw-bolder text-white" style="font-size: 1.5rem; letter-spacing: -0.5px;">Shining Bright</h3>
                            <div class="small text-white-50 text-uppercase tracking-wider" style="font-size: 0.7rem;">Vocational School</div>
                            <div class="small text-white-50 mt-1">${d.branch_name||'Headquarters'}</div>
                            ${d.branch_address ? `<div class="small text-white-50">${d.branch_address}</div>` : ''}
                        </div>
                    </div>
                    <div class="text-end">
                        <h4 class="mb-2 fw-bold text-uppercase tracking-wider text-light">PAY SLIP</h4>
                        <div class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-3 py-2 mb-2 fs-6 shadow-sm"><i class="bi bi-calendar3 me-2"></i>${mNames[d.pay_period_month]} ${d.pay_period_year}</div>
                        <div class="small text-white-50"><i class="bi bi-clock-history me-1"></i>Pay Date: <strong>${d.pay_date}</strong></div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 bg-white">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border border-light h-100">
                            <h6 class="text-muted fw-bold mb-3 text-uppercase small" style="letter-spacing:1px;"><i class="bi bi-person-badge me-2"></i>Employee Details</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted w-25 small">Name</td><td class="fw-bolder fs-6">${d.employee_name}</td></tr>
                                <tr><td class="text-muted small">Email</td><td class="fw-semibold text-secondary">${d.email}</td></tr>
                                <tr><td class="text-muted small">Role</td><td><span class="badge bg-secondary bg-opacity-10 text-secondary border px-2">${d.employee_role}</span></td></tr>
                                <tr><td class="text-muted small">Grade</td><td>${d.grade_name ? `<span class="badge bg-primary bg-opacity-10 text-primary border">${d.grade_name} (L${d.grade_level})</span>` : '<span class="text-muted">—</span>'}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border border-light h-100">
                            <h6 class="text-muted fw-bold mb-3 text-uppercase small" style="letter-spacing:1px;"><i class="bi bi-wallet2 me-2"></i>Payment Details</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted w-25 small">Mode</td><td class="fw-semibold"><i class="bi bi-${d.payment_mode==='Cash'?'cash-coin text-success':'bank text-primary'} me-2"></i>${d.payment_mode}</td></tr>
                                <tr><td class="text-muted small">Bank</td><td class="fw-semibold">${d.bank_name||'<span class="text-muted fst-italic">—</span>'}</td></tr>
                                <tr><td class="text-muted small">Account</td><td class="fw-semibold font-monospace tracking-wider">${d.account_number||'<span class="text-muted fst-italic">—</span>'}</td></tr>
                                <tr><td class="text-muted small">Status</td><td><span class="badge bg-${d.status==='Paid'?'success':d.status==='Pending'?'warning':'danger'} bg-opacity-75 shadow-sm rounded-pill px-3 py-1">${d.status}</span></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border border-success h-100 shadow-sm rounded-3">
                            <div class="card-header bg-success bg-opacity-10 border-success border-bottom py-2">
                                <h6 class="fw-bold text-success mb-0 text-uppercase small"><i class="bi bi-graph-up-arrow me-2"></i>Earnings</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-sm mb-0 align-middle">
                                    <tbody>
                                        <tr><td class="ps-3 py-2 fw-semibold text-secondary">Basic Salary</td><td class="text-end pe-3 py-2 fw-bold text-success">${fmt(d.basic_salary)}</td></tr>
                                        ${earningsRows}
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light border-top border-success mt-auto py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted fw-bold small text-uppercase" style="letter-spacing: 0.5px">Gross Earnings</span>
                                    <span class="fs-5 fw-bolder text-success">${fmt(d.gross_salary)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border border-danger h-100 shadow-sm rounded-3">
                            <div class="card-header bg-danger bg-opacity-10 border-danger border-bottom py-2">
                                <h6 class="fw-bold text-danger mb-0 text-uppercase small"><i class="bi bi-graph-down-arrow me-2"></i>Deductions</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-sm mb-0 align-middle">
                                    <tbody>
                                        ${deductionRows || '<tr><td colspan="2" class="text-muted text-center py-3 fst-italic small">No deductions</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light border-top border-danger mt-auto py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted fw-bold small text-uppercase" style="letter-spacing: 0.5px">Total Deductions</span>
                                    <span class="fs-5 fw-bolder text-danger">(${fmt(d.total_deductions)})</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded p-4 text-center mt-2 mx-auto" style="max-width: 500px">
                    <div class="text-primary fw-bold text-uppercase tracking-wider small mb-1">Net Pay Received</div>
                    <div class="display-5 fw-bolder text-primary lh-1">${fmt(d.net_salary)}</div>
                </div>
            </div>
            
            <div class="card-footer border-top-0 bg-light p-3 text-center text-muted small" style="border-radius: 0 0 0.5rem 0.5rem;">
                <i class="bi bi-shield-check me-1 text-success"></i>This is a system generated payslip and does not require a physical signature.<br>
                <span class="opacity-50">Generated by SBVS &bull; ${new Date().toLocaleDateString()}</span>
            </div>
        </div>`;
        $('#payslipBody').html(html);
        new bootstrap.Modal(document.getElementById('payslipModal')).show();
    });
}
</script>
</body>
</html>
