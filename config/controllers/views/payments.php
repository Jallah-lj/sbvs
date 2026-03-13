<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$branchId      = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    header("Location: dashboard.php");
    exit;
}

// Fetch branch name for header badge
$branchName = '';
if (!$isSuperAdmin && $branchId) {
    $bq = $db->prepare("SELECT name FROM branches WHERE id=?");
    $bq->execute([$branchId]);
    $branchName = $bq->fetchColumn() ?: '';
}

// Fetch branches for SA filter dropdown
$branches = [];
if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                   ->fetchAll(PDO::FETCH_ASSOC);
}

$userName = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
$userRole = htmlspecialchars($role);

$paymentMethods = [
    'Cash', 'Bank Transfer', 'Mobile Money - Orange', 'Mobile Money - MTN',
    'Check', 'Debit Card', 'Credit Card'
];
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Payments';
$activePage = 'payments.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 mt-2 gap-3 fade-in">
            <div>
                <h3 class="fw-800 mb-0" style="letter-spacing: -0.03em;">Payments & Receipts</h3>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <p class="text-muted small mb-0">Financial transaction history and receipt management</p>
                    <?php if (!$isSuperAdmin && $branchName): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.65rem;"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary d-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="bi bi-plus-circle-fill"></i> Record Payment
                </button>
                <a id="exportBtn" href="#" class="btn btn-light d-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600; border: 1px solid #e2e8f0;">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4 fade-in" style="animation-delay: 0.1s;">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-wallet2"></i></div>
                            <div>
                                <div class="stat-value" id="statTotal">—</div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-check"></i></div>
                            <div>
                                <div class="stat-value" id="statMonthly">—</div>
                                <div class="stat-label">This Month</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-value" id="statOutstanding">—</div>
                                <div class="stat-label">Outstanding</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-slash-circle"></i></div>
                            <div>
                                <div class="stat-value" id="statVoid">—</div>
                                <div class="stat-label">Voided Txns</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter System -->
        <div class="card filter-card mb-4 fade-in" style="animation-delay: 0.2s;">
            <div class="card-body py-4">
                <div class="row g-3">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <select id="fBranch" class="form-select">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" id="fDateFrom" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" id="fDateTo" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Method</label>
                        <select id="fMethod" class="form-select">
                            <option value="">All Methods</option>
                            <?php foreach ($paymentMethods as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="fStatus" class="form-select">
                            <option value="">All</option>
                            <option value="Active">Active</option>
                            <option value="Void">Voided</option>
                        </select>
                    </div>
                    <div class="col-md-<?= $isSuperAdmin ? '2' : '4' ?>">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" id="fSearch" class="form-control" placeholder="Receipt / Name…">
                            <button class="btn btn-primary px-3" id="applyFilters"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="table-container fade-in" style="animation-delay: 0.3s;">
            <div class="table-responsive">
                <table id="paymentsTable" class="table table-hover mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Course</th>
                            <?php if ($isSuperAdmin): ?><th>Branch</th><?php endif; ?>
                            <th class="text-end">Amount</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <tr><td colspan="<?= $isSuperAdmin ? 11 : 10 ?>" class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading payment records...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

    </main>
</div>

<!-- ═══════════════════════ RECORD PAYMENT MODAL ═══════════════════════════ -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>Record New Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="recordPaymentForm">
            <div class="modal-body">

                <!-- Step 1: Student -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                    <div class="student-autocomplete">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="studentSearch" class="form-control" placeholder="Type student name or ID…" autocomplete="off">
                        </div>
                        <div id="studentResults" class="student-results" style="display:none;"></div>
                    </div>
                    <input type="hidden" id="hiddenStudentId" name="student_id">
                </div>

                <!-- Step 2: Enrollment -->
                <div class="mb-3" id="enrollmentRow" style="display:none;">
                    <label class="form-label fw-semibold">Enrollment / Course <span class="text-danger">*</span></label>
                    <select id="enrollmentSelect" name="enrollment_id" class="form-select">
                        <option value="">— Select Enrollment —</option>
                    </select>
                </div>

                <!-- Balance Info Card -->
                <div id="balanceCard" class="alert alert-info d-none mb-3">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="small text-muted">Course Fee</div>
                            <div class="fw-bold" id="balFee">—</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Total Paid</div>
                            <div class="fw-bold text-success" id="balPaid">—</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Outstanding</div>
                            <div class="fw-bold text-danger fs-5" id="balOutstanding">—</div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="row g-3" id="paymentFields" style="display:none;">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Amount ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" id="payAmount" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <div class="form-text" id="payTypeLabel"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select id="payMethod" name="payment_method" class="form-select">
                            <option value="">— Select Method —</option>
                            <?php foreach ($paymentMethods as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" id="payDate" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Transaction / Reference ID</label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="Optional (bank ref, mobile txn ID…)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes…">
                    </div>
                </div>

            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,0.05);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:10px; font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3" style="border-radius:10px; font-weight:600;" id="submitPayBtn" disabled>
                    <i class="bi bi-check-circle-fill me-1"></i> Record Payment
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════ RECEIPT MODAL ═════════════════════════════════ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i>Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="receiptContent" class="p-4"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,0.05);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:10px; font-weight:600;" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary px-3" style="border-radius:10px; font-weight:600;" onclick="printReceipt()">
                    <i class="bi bi-printer-fill me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ VOID MODAL ════════════════════════════════════ -->
<div class="modal fade" id="voidModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-slash-circle me-2"></i>Void Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="voidForm">
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Voiding cannot be undone.</strong> The payment record will be retained for audit purposes.
                </div>
                <p class="mb-2">Receipt: <strong id="voidReceiptNo" class="text-primary"></strong></p>
                <input type="hidden" id="voidPaymentId" name="payment_id">
                <label class="form-label fw-semibold">Reason for Voiding <span class="text-danger">*</span></label>
                <textarea id="voidReason" name="void_reason" class="form-control" rows="3" placeholder="Enter reason (duplicate entry, wrong amount, error, etc.)…" required></textarea>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,0.05);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:10px; font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger px-3" style="border-radius:10px; font-weight:600;">
                    <i class="bi bi-slash-circle-fill me-1"></i> Confirm Void
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden print area -->
<div id="printReceiptArea"></div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const isSuperAdmin  = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const printedByUser = <?= json_encode($userName) ?>;
const API           = 'models/api/payment_api.php';
let dtTable         = null;
let currentFilters  = {};

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(v) {
    return '$' + parseFloat(v || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function fmtDate(s) {
    if (!s) return '—';
    return new Date(s).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
}
function methodIcon(m) {
    const icons = {
        'Cash':'bi-cash-stack','Bank Transfer':'bi-bank2',
        'Mobile Money - Orange':'bi-phone','Mobile Money - MTN':'bi-phone-fill',
        'Check':'bi-file-earmark-text','Debit Card':'bi-credit-card',
        'Credit Card':'bi-credit-card-2-front'
    };
    const ic = icons[m] || 'bi-cash';
    return `<i class="bi ${ic} me-1 text-secondary"></i>${m}`;
}
function buildExportUrl() {
    const p = new URLSearchParams({action:'export',...currentFilters});
    return API + '?' + p.toString();
}

// ── Load Stats ────────────────────────────────────────────────────────────────
function loadStats() {
    const bf = isSuperAdmin ? ($('#fBranch').val() || '') : '';
    $.getJSON(API, {action:'stats', branch_id:bf}, function(res){
        if (!res.success) return;
        const d = res.data;
        $('#statTotal').text(fmtMoney(d.total_rev));
        $('#statMonthly').text(fmtMoney(d.monthly_rev));
        $('#statOutstanding').text(fmtMoney(d.outstanding));
        $('#statVoid').text(d.void_count);
    });
}

// ── Load Table ────────────────────────────────────────────────────────────────
function loadTable() {
    currentFilters = {
        branch_id : isSuperAdmin ? ($('#fBranch').val() || '') : '',
        date_from : $('#fDateFrom').val(),
        date_to   : $('#fDateTo').val(),
        method    : $('#fMethod').val(),
        status    : $('#fStatus').val(),
        search    : $('#fSearch').val(),
    };
    $('#exportBtn').attr('href', buildExportUrl());

    $.getJSON(API, {action:'list', ...currentFilters}, function(res){
        if (!res.success) {
            Swal.fire('Error', res.message || 'Failed to load data', 'error'); return;
        }
        renderTable(res.data);
    }).fail(function(){
        Swal.fire('Error', 'Could not connect to the server.', 'error');
    });
}

function renderTable(data) {
    if (dtTable) { dtTable.destroy(); }
    $('#paymentsTableBody').empty();

    const colDefs = [
        { // Receipt No
            data: 'receipt_no',
            render: (d,t,r) => `<code class="text-primary small">${d||'—'}</code>`
        },
        { // Date
            data: 'payment_date',
            render: d => `<span class="text-nowrap small">${fmtDate(d)}</span>`
        },
        { // Student
            data: 'student_name',
            render: (d,t,r) => `<div class="fw-semibold small">${d}</div><div class="text-muted" style="font-size:.72rem">${r.student_code}</div>`
        },
        { // Course
            data: 'course_name',
            render: d => d ? `<span class="small">${d}</span>` : '<em class="text-muted small">N/A</em>'
        },
    ];
    if (isSuperAdmin) {
        colDefs.push({ data:'branch_name', render: d => `<span class="small">${d||'—'}</span>` });
    }
    colDefs.push(
        { // Amount
            data: 'amount',
            className: 'text-end',
            render: d => `<span class="fw-bold text-success">${fmtMoney(d)}</span>`
        },
        { // Type
            data: 'payment_type',
            render: d => d === 'Full'
                ? '<span class="badge-custom badge-success">Full</span>'
                : '<span class="badge-custom badge-warning">Partial</span>'
        },
        { // Method
            data: 'payment_method',
            render: d => `<span class="small text-nowrap">${methodIcon(d)}</span>`
        },
        { // Balance
            data: 'balance',
            className: 'text-end',
            render: (d,t,r) => {
                if (d === null || d === undefined) return '<span class="text-muted small">—</span>';
                return d > 0
                    ? `<span class="fw-bold text-danger small">${fmtMoney(d)}</span>`
                    : '<span class="badge-custom badge-success"><i class="bi bi-check-circle-fill me-1"></i>Cleared</span>';
            }
        },
        { // Status
            data: 'status',
            render: (d,t,r) => {
                if (d === 'Void') {
                    const tip = r.void_reason ? ` title="${r.void_reason.replace(/"/g,'&quot;')}"` : '';
                    return `<span class="badge-custom badge-danger" data-bs-toggle="tooltip"${tip}>VOID</span>`;
                }
                return '<span class="badge-custom badge-success">Active</span>';
            }
        },
        { // Actions
            data: null,
            orderable: false,
            className: 'text-center text-nowrap',
            render: (d,t,r) => {
                let btns = `<div class="d-flex justify-content-center gap-1">
                    <button class="btn-action btn-view" onclick="showReceipt(${r.id})" title="View Receipt">
                    <i class="bi bi-receipt"></i></button>`;
                if (r.status === 'Active') {
                    btns += `<button class="btn-action btn-void" onclick="openVoid(${r.id},'${(r.receipt_no||'').replace(/'/g,"\\'")}')\" title="Void Payment">
                        <i class="bi bi-slash-circle"></i></button>`;
                }
                btns += '</div>';
                return btns;
            }
        }
    );

    dtTable = $('#paymentsTable').DataTable({
        data: data,
        columns: colDefs,
        order: [[1, 'desc']],
        responsive: true,
        pageLength: 25,
        language: {
            emptyTable: '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No payments found</div>',
            zeroRecords: '<div class="text-center text-muted py-4"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching records</div>',
        },
        rowCallback: function(row, data) {
            if (data.status === 'Void') $(row).addClass('void-row table-secondary');
        }
    });

    // init tooltips
    $('[data-bs-toggle="tooltip"]').tooltip({html:true});
}

// ── Filters ───────────────────────────────────────────────────────────────────
$(document).ready(function(){
    loadStats();
    loadTable();

    $('#applyFilters').on('click', function(){ loadStats(); loadTable(); });
    $('#fSearch').on('keydown', function(e){ if(e.key==='Enter'){ loadStats(); loadTable(); } });
    if (isSuperAdmin) {
        $('#fBranch').on('change', function(){ loadStats(); loadTable(); });
    }
});

// ── Student Autocomplete ──────────────────────────────────────────────────────
let searchTimer;
$('#studentSearch').on('input', function(){
    clearTimeout(searchTimer);
    const q = $(this).val().trim();
    $('#hiddenStudentId').val('');
    resetEnrollmentSection();
    if (q.length < 2) { $('#studentResults').hide(); return; }
    searchTimer = setTimeout(function(){
        $.getJSON(API, {action:'search_students', q:q}, function(rows){
            let html = '';
            rows.forEach(s => {
                html += `<div class="sr-item" data-id="${s.id}" data-name="${s.name}" data-code="${s.code}">
                    <i class="bi bi-person-fill me-2 text-primary"></i>
                    <strong>${s.name}</strong>
                    <span class="text-muted ms-2">${s.code}</span>
                    <small class="float-end text-muted">${s.branch}</small>
                </div>`;
            });
            if (!html) html = '<div class="sr-item text-muted">No students found</div>';
            $('#studentResults').html(html).show();
        });
    }, 300);
});

$(document).on('click', '.sr-item[data-id]', function(){
    const id   = $(this).data('id');
    const name = $(this).data('name');
    const code = $(this).data('code');
    $('#studentSearch').val(name + '  (' + code + ')');
    $('#hiddenStudentId').val(id);
    $('#studentResults').hide();
    loadEnrollments(id);
});

$(document).on('click', function(e){
    if (!$(e.target).closest('.student-autocomplete').length) $('#studentResults').hide();
});

function resetEnrollmentSection(){
    $('#enrollmentRow').hide();
    $('#balanceCard').addClass('d-none');
    $('#paymentFields').hide();
    $('#submitPayBtn').prop('disabled', true);
    $('#enrollmentSelect').html('<option value="">— Select Enrollment —</option>');
    $('#payTypeLabel').text('');
}

function loadEnrollments(studentId){
    $.getJSON(API, {action:'get_enrollments', student_id:studentId}, function(res){
        if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
        let opts = '<option value="">— Select Enrollment —</option>';
        res.data.forEach(e => {
            const bal = parseFloat(e.balance).toFixed(2);
            const cleared = e.balance <= 0;
            opts += `<option value="${e.enrollment_id}"
                data-fee="${e.fees}" data-paid="${e.total_paid}" data-balance="${e.balance}"
                ${cleared ? 'disabled' : ''}>
                ${e.course_name} | Fee: $${parseFloat(e.fees).toFixed(2)} | Outstanding: $${bal}${cleared ? ' ✓ Cleared' : ''}
            </option>`;
        });
        $('#enrollmentSelect').html(opts);
        $('#enrollmentRow').show();
        updateBalanceCard();
    });
}

$('#enrollmentSelect').on('change', updateBalanceCard);

function updateBalanceCard(){
    const sel = $('#enrollmentSelect option:selected');
    const fee  = parseFloat(sel.data('fee')     || 0);
    const paid = parseFloat(sel.data('paid')    || 0);
    const bal  = parseFloat(sel.data('balance') || 0);

    if (sel.val()) {
        $('#balFee').text(fmtMoney(fee));
        $('#balPaid').text(fmtMoney(paid));
        $('#balOutstanding').text(fmtMoney(bal));
        $('#balanceCard').removeClass('d-none');

        if (bal > 0) {
            $('#paymentFields').show();
            $('#payAmount').attr('max', bal).val(bal.toFixed(2));
            checkPayType();
            $('#submitPayBtn').prop('disabled', false);
        } else {
            $('#paymentFields').hide();
            $('#submitPayBtn').prop('disabled', true);
            $('#balanceCard').removeClass('alert-info').addClass('alert-success');
        }
    } else {
        $('#balanceCard').addClass('d-none').removeClass('alert-success').addClass('alert-info');
        $('#paymentFields').hide();
        $('#submitPayBtn').prop('disabled', true);
    }
}

$('#payAmount').on('input', checkPayType);

function checkPayType(){
    const amount = parseFloat($('#payAmount').val() || 0);
    const bal    = parseFloat($('#enrollmentSelect option:selected').data('balance') || 0);
    if (amount <= 0) { $('#payTypeLabel').text(''); return; }
    if (amount >= bal - 0.01) {
        $('#payTypeLabel').html('<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>This will fully clear the balance.</span>');
    } else {
        const remaining = (bal - amount).toFixed(2);
        $('#payTypeLabel').html(`<span class="text-warning"><i class="bi bi-info-circle-fill me-1"></i>Partial payment — $${remaining} will remain outstanding.</span>`);
    }
}

// ── Submit Record Payment ─────────────────────────────────────────────────────
$('#recordPaymentForm').on('submit', function(e){
    e.preventDefault();
    if (!$('#hiddenStudentId').val()) { Swal.fire('Required','Please select a student.','warning'); return; }
    if (!$('#enrollmentSelect').val()) { Swal.fire('Required','Please select an enrollment.','warning'); return; }

    const btn = $('#submitPayBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Recording…');
    $.post(API, $(this).serialize() + '&action=record', function(res){
        $('#submitPayBtn').prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Record Payment');
        if (res.success) {
            bootstrap.Modal.getInstance('#recordPaymentModal').hide();
            Swal.fire({
                title: 'Payment Recorded!',
                html: `Receipt No: <strong class="text-primary">${res.receipt_no}</strong>`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-receipt me-1"></i> View Receipt',
                cancelButtonText: 'Close',
                confirmButtonColor: '#0d6efd',
            }).then(r => {
                if (r.isConfirmed) showReceipt(res.payment_id);
                loadTable(); loadStats();
                resetRecordForm();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function(){ Swal.fire('Error','Server error.','error'); });
});

$('#recordPaymentModal').on('hidden.bs.modal', resetRecordForm);
function resetRecordForm(){
    $('#recordPaymentForm')[0].reset();
    $('#studentSearch').val('');
    $('#hiddenStudentId').val('');
    resetEnrollmentSection();
}

// ── Security hash helper ──────────────────────────────────────────────────────
async function makeHash(str) {
    const buf  = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,'0')).join('').substring(0,16).toUpperCase();
}

// ── View Receipt ──────────────────────────────────────────────────────────────
function showReceipt(paymentId){
    $.getJSON(API, {action:'get_receipt', payment_id:paymentId}, async function(res){
        if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
        const d = res.data;
        const isVoid   = d.status === 'Void';
        const today    = new Date();
        const printed  = today.toLocaleString('en-US',{dateStyle:'medium',timeStyle:'short'});
        const secHash  = await makeHash((d.receipt_no||d.id) + d.student_code + (d.amount||'') + today.toISOString().slice(0,10));
        const verifyUrl = window.location.origin + '/sbvs/config/controllers/views/payments.php?verify=' + paymentId;

        const microText = `✦ SHINING BRIGHT VOCATIONAL SCHOOL · OFFICIAL RECEIPT · ${secHash} · NOT VALID IF PHOTOCOPIED · `.repeat(10);

        const voidBanner = isVoid ? `
            <div style="text-align:center;margin:12px 0;">
                <span style="display:inline-block;background:#dc2626;color:#fff;font-size:1.2rem;font-weight:900;padding:8px 24px;border-radius:30px;letter-spacing:3px;">
                    ⊘ VOID
                </span>
                <p style="color:#6b7280;font-size:.72rem;margin-top:6px;">Reason: ${d.void_reason || '—'}</p>
            </div>` : '';

        const html = `
<div id="receiptPrintable" style="font-family:'Inter',sans-serif;position:relative;background:#fff;${isVoid?'opacity:.75':''}">

    <!-- ① Void pantograph -->
    <div style="position:absolute;inset:0;pointer-events:none;z-index:1;
        background-image:radial-gradient(circle,rgba(0,0,0,0.04) 1px,transparent 1px),radial-gradient(circle,rgba(0,0,0,0.012) 1px,transparent 1px);
        background-size:3px 3px,5px 5px;background-position:0 0,1.5px 1.5px;
        mix-blend-mode:multiply;" aria-hidden="true"></div>

    <!-- ② Top guilloche SVG -->
    <svg width="100%" height="14" viewBox="0 0 500 14" preserveAspectRatio="none" style="display:block;" aria-hidden="true">
        <defs><pattern id="gw" x="0" y="0" width="30" height="14" patternUnits="userSpaceOnUse">
            <path d="M0 7Q4 0 8 7Q12 14 16 7Q20 0 24 7Q28 14 32 7" fill="none" stroke="#6366f1" stroke-width="0.5" opacity="0.28"/>
            <path d="M0 7Q4 2 8 7Q12 12 16 7Q20 2 24 7Q28 12 32 7" fill="none" stroke="#8b5cf6" stroke-width="0.3" opacity="0.15"/>
        </pattern></defs>
        <rect width="500" height="14" fill="url(#gw)"/>
    </svg>

    <!-- ③ Top microtext -->
    <div style="font-size:6px;font-weight:700;letter-spacing:1.5px;color:rgba(79,70,229,0.5);
        text-transform:uppercase;white-space:nowrap;overflow:hidden;line-height:1;
        background:#ede9fe;padding:2px 0;text-align:center;user-select:none;">${microText}</div>

    <!-- ④ Yellow security band -->
    <div style="background:linear-gradient(90deg,#fde68a,#fcd34d,#fde68a);padding:4px 14px;
        font-size:6.5px;font-weight:800;letter-spacing:2px;color:rgba(120,53,15,0.55);
        text-transform:uppercase;display:flex;justify-content:space-between;
        -webkit-print-color-adjust:exact;print-color-adjust:exact;user-select:none;">
        <span>⬛ SECURITY INK · VOID IF BAND MISSING</span>
        <span>${secHash}</span>
        <span>SBVS PORTAL ⬛</span>
    </div>

    <!-- ⑤ Diagonal watermark (shows on copies) -->
    <div style="position:relative;z-index:2;padding:20px 24px 16px;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-35deg);
        font-size:11px;font-weight:900;letter-spacing:4px;color:rgba(79,70,229,0.05);
        white-space:nowrap;pointer-events:none;text-transform:uppercase;user-select:none;
        -webkit-print-color-adjust:exact;print-color-adjust:exact;">
        SHINING BRIGHT VOCATIONAL SCHOOL · ORIGINAL RECEIPT · ${secHash}
    </div>

    <div style="position:relative;z-index:3;">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1.5px dashed #e2e8f0;padding-bottom:14px;margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="../../assets/img/logo.svg" alt="Logo" width="40" height="50" onerror="this.style.display='none'">
                <div>
                    <div style="font-size:1rem;font-weight:800;letter-spacing:-.02em;color:#0f172a;">Shining Bright</div>
                    <div style="font-size:.6rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#64748b;">Vocational School</div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:2px;">${d.branch_name || ''}</div>
                    ${d.branch_phone ? `<div style="font-size:.7rem;color:#94a3b8;">☎ ${d.branch_phone}</div>` : ''}
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                <div id="payQR_${paymentId}"></div>
                <div style="font-size:5.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Scan to Verify</div>
                <div style="text-align:right;font-size:.72rem;">
                    <div style="color:#94a3b8;font-size:.62rem;">Printed</div>
                    <div style="font-weight:600;">${printed}</div>
                    <div style="color:#94a3b8;font-size:.62rem;margin-top:3px;">By</div>
                    <div style="font-weight:600;">${printedByUser}</div>
                </div>
            </div>
        </div>

        <!-- Receipt title -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div>
                <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#4f46e5;">Payment Receipt</div>
                <div style="font-size:1rem;font-weight:800;letter-spacing:-.02em;color:#0f172a;">${d.receipt_no || '—'}</div>
            </div>
            <span style="background:${isVoid?'#dc2626':'#4f46e5'};color:#fff;font-size:.68rem;padding:5px 12px;border-radius:20px;font-weight:700;letter-spacing:.5px;">
                ${isVoid ? '⊘ VOIDED' : (parseFloat(d.balance)>0 ? 'PARTIAL' : 'PAID IN FULL')}
            </span>
        </div>

        <!-- Details table -->
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:12px;">
            ${[['Date', fmtDate(d.payment_date)],
               ['Student', `<strong>${d.student_name}</strong>`],
               ['Student ID', `<span style="font-family:monospace">${d.student_code}</span>`],
               ['Course', d.course_name || '—'],
               ['Method', methodIcon(d.payment_method)],
               d.transaction_id ? ['Ref / Txn ID', d.transaction_id] : null,
               d.notes          ? ['Notes', `<em>${d.notes}</em>`] : null
              ].filter(Boolean).map(([label, val]) =>
                `<tr><td style="color:#64748b;font-weight:500;padding:5px 0;width:38%;">${label}</td><td style="color:#0f172a;padding:5px 0;">${val}</td></tr>`
            ).join('')}
        </table>

        <!-- Financial summary -->
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:14px;">
            <thead><tr>
                <th style="background:#f8fafc;color:#64748b;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 10px;border:1px solid #e2e8f0;">Description</th>
                <th style="background:#f8fafc;color:#64748b;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 10px;border:1px solid #e2e8f0;text-align:right;">Amount</th>
            </tr></thead>
            <tbody>
                <tr><td style="padding:8px 10px;border:1px solid #e2e8f0;">Registration Fee</td><td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:right;color:#475569;">${fmtMoney(d.registration_fee || 0)}</td></tr>
                <tr><td style="padding:8px 10px;border:1px solid #e2e8f0;">Tuition Fee</td><td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:right;color:#475569;">${fmtMoney(d.tuition_fee || 0)}</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:8px 10px;border:1px solid #e2e8f0;font-weight:600;">Total Course Fee</td><td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:700;">${fmtMoney(d.fees)}</td></tr>
                <tr><td style="padding:8px 10px;border:1px solid #e2e8f0;">Previously Paid</td><td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:right;">${fmtMoney(d.prev_paid)}</td></tr>
                <tr style="background:#f0fdf4;"><td style="padding:8px 10px;border:1px solid #e2e8f0;"><strong>This Payment</strong></td><td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:800;color:#059669;font-size:.95rem;">${fmtMoney(d.amount)}</td></tr>
                <tr style="background:${parseFloat(d.balance)>0?'#fffbeb':'#f0fdf4'};"><td style="padding:8px 10px;border:2px solid #4f46e5;">Remaining Balance</td><td style="padding:8px 10px;border:2px solid #4f46e5;text-align:right;font-weight:800;color:${parseFloat(d.balance)>0?'#dc2626':'#059669'}">${parseFloat(d.balance)>0?fmtMoney(d.balance):'✔ CLEARED'}</td></tr>
            </tbody>
        </table>

        ${voidBanner}

        <!-- Security footer -->
        <div style="border-top:1px dashed #e2e8f0;padding-top:10px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:10px;">
            <div>
                <div style="display:inline-block;background:#4f46e5;color:#fff;font-size:6px;font-weight:800;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;border-radius:20px;">Official Receipt</div>
                <div style="font-family:monospace;font-size:7px;color:rgba(79,70,229,0.5);letter-spacing:1.5px;background:#ede9fe;padding:3px 8px;border-radius:4px;border:1px solid rgba(79,70,229,0.15);margin-top:5px;">SEC: ${secHash}</div>
                <div style="font-size:.6rem;color:#94a3b8;margin-top:4px;max-width:260px;line-height:1.5;">Only valid in original printed form. Any photocopy or scan is invalid and constitutes fraud.</div>
            </div>
            <div style="text-align:center;border:1.5px dashed #cbd5e1;border-radius:8px;padding:6px 20px;">
                <div style="font-size:.62rem;color:#94a3b8;margin-bottom:18px;">Authorised Signature</div>
                <div style="font-size:.62rem;color:#94a3b8;">Office Stamp</div>
            </div>
        </div>

    </div>
    </div>

    <!-- ③ Bottom microtext -->
    <div style="font-size:6px;font-weight:700;letter-spacing:1.5px;color:rgba(79,70,229,0.5);
        text-transform:uppercase;white-space:nowrap;overflow:hidden;line-height:1;
        background:#ede9fe;padding:2px 0;text-align:center;user-select:none;transform:scaleY(-1);">${microText}</div>

    <!-- ② Bottom guilloche SVG -->
    <svg width="100%" height="14" viewBox="0 0 500 14" preserveAspectRatio="none" style="display:block;" aria-hidden="true">
        <rect width="500" height="14" fill="url(#gw)"/>
    </svg>

</div>`;

        $('#receiptContent').html(html);
        new bootstrap.Modal('#receiptModal').show();

        // Generate QR code after DOM insertion
        setTimeout(() => {
            const qrEl = document.getElementById('payQR_' + paymentId);
            if (qrEl && typeof QRCode !== 'undefined') {
                new QRCode(qrEl, {
                    text: verifyUrl,
                    width: 70, height: 70,
                    colorDark: '#4f46e5', colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        }, 50);
    });
}

function printReceipt(){
    const content = document.getElementById('receiptPrintable');
    if (!content) return;
    const printArea = document.getElementById('printReceiptArea');
    printArea.innerHTML = content.outerHTML;
    // Inject print styles for security elements
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            body > *:not(#printReceiptArea) { display:none !important; }
            #printReceiptArea { display:block !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        }`;
    printArea.appendChild(style);
    printArea.style.display = 'block';
    window.print();
    setTimeout(() => { printArea.style.display = 'none'; printArea.innerHTML = ''; }, 500);
}

// ── Void ──────────────────────────────────────────────────────────────────────
function openVoid(paymentId, receiptNo){
    $('#voidPaymentId').val(paymentId);
    $('#voidReceiptNo').text(receiptNo || '#' + paymentId);
    $('#voidReason').val('');
    new bootstrap.Modal('#voidModal').show();
}

$('#voidForm').on('submit', function(e){
    e.preventDefault();
    const reason = $('#voidReason').val().trim();
    if (!reason) { Swal.fire('Required','Please provide a reason for voiding.','warning'); return; }

    $.post(API, {action:'void', payment_id:$('#voidPaymentId').val(), void_reason:reason}, function(res){
        bootstrap.Modal.getInstance('#voidModal').hide();
        if (res.success) {
            Swal.fire({title:'Voided',text:res.message,icon:'success',timer:2000,showConfirmButton:false});
            loadTable(); loadStats();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json');
});
</script>
</body>
</html>
