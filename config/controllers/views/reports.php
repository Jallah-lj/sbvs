<?php
session_start();
ob_start();
require_once '../../config.php';
require_once '../../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

$db = (new Database())->getConnection();

$role         = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin      = ($role === 'Admin');

$branches = $isSuperAdmin
    ? $db->query("SELECT id, name FROM branches WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$branchName = '';
if (!$isSuperAdmin && $sessionBranch) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$sessionBranch]);
    $branchName = $bStmt->fetchColumn() ?: '';
}

$pageTitle  = 'Financial Reports';
$activePage = 'reports.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<style>
    /* ── Reports-specific tokens ─────────────────────────────── */
    .kpi-revenue { background: linear-gradient(135deg,#198754 0%,#4facfe 100%); color:#fff; }
    .kpi-enrolled{ background: linear-gradient(135deg,#0dcaf0 0%,#009ef7 100%); color:#fff; }
    .kpi-pending  { background: linear-gradient(135deg,#f6c000 0%,#f39c12 100%); color:#fff; }

    /* ── Page header row ─────────────────────────────────────── */
    .report-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 1.25rem;
    }

    .report-header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    /* ── Filter card layout ──────────────────────────────────── */
    .filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    @media (max-width: 575.98px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr;
        }
        .filter-grid .filter-branch {
            grid-column: 1 / -1;
        }
        .filter-grid .filter-submit {
            grid-column: 1 / -1;
        }
    }

    /* ── Scrollable pill tabs on small screens ───────────────── */
    .tab-scroll-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 4px;
    }
    .tab-scroll-wrap::-webkit-scrollbar { display: none; }

    #reportTabs {
        flex-wrap: nowrap;
        min-width: max-content;
    }

    .nav-pills .nav-link {
        border-radius: 8px;
        padding: 9px 18px;
        font-weight: 600;
        color: #5e6278;
        white-space: nowrap;
        margin-right: 8px;
        transition: all .2s ease;
        font-size: .875rem;
    }
    .nav-pills .nav-link.active {
        background: #009ef7;
        color: #fff;
        box-shadow: 0 4px 10px rgba(0,158,247,.25);
    }
    .nav-pills .nav-link:hover:not(.active) {
        background: rgba(0,158,247,.06);
        color: #009ef7;
    }

    /* ── KPI widgets responsive grid ───────────────────────────── */
    #summaryWidgets .kpi-col {
        flex: 0 0 100%;
        max-width: 100%;
    }

    @media (min-width: 480px) {
        #summaryWidgets .kpi-col { flex: 0 0 50%; max-width: 50%; }
    }
    @media (min-width: 768px) {
        #summaryWidgets .kpi-col { flex: 0 0 33.333%; max-width: 33.333%; }
    }

    .kpi-card-report {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 20px;
    }

    .kpi-icon-lg {
        width: 52px; height: 52px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }

    /* ── Table wrapper ───────────────────────────────────────── */
    .table-responsive-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table.dataTable > thead > tr > th,
    .report-table > thead > tr > th {
        background: #f9f9f9;
        color: #5e6278;
        font-weight: 700;
        text-transform: uppercase;
        font-size: .7rem;
        letter-spacing: .5px;
        border-bottom: 1px dashed #e4e6ef;
        padding: 12px 10px;
        white-space: nowrap;
    }

    table.dataTable > tbody > tr > td,
    .report-table > tbody > tr > td {
        color: #3f4254;
        padding: 12px 10px;
        vertical-align: middle;
        border-bottom: 1px dashed #e4e6ef;
        font-size: .85rem;
    }

    .report-table > tbody > tr:last-child > td { border-bottom: 0; }

    /* ── Live toggle ─────────────────────────────────────────── */
    .live-toggle-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(0,158,247,.06);
        border: 1px solid rgba(0,158,247,.15);
        border-radius: 8px;
        padding: 6px 12px;
    }

    /* ── Badge ───────────────────────────────────────────────── */
    .badge-method {
        padding: .35em .7em;
        font-weight: 600;
        border-radius: 6px;
        font-size: .7rem;
    }

    /* ── Print ───────────────────────────────────────────────── */
    @media print {
        .sidebar, .mobile-navbar, #filterCard,
        .btn, nav, .dataTables_filter,
        .dataTables_info, .dataTables_paginate,
        .dataTables_length, .report-header-actions { display: none !important; }
        .sbvs-main { margin: 0 !important; padding: 0 !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }

    /* ── Utility ─────────────────────────────────────────────── */
    @media (max-width: 575.98px) {
        .sbvs-main { padding: 12px 10px 30px; }
        .report-header h2 { font-size: 1.3rem; }
        .kpi-icon-lg { width: 42px; height: 42px; font-size: 1.1rem; }
    }
</style>
</head>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ──────────────────────────────────────── -->
    <div class="report-header">
        <div>
            <h2 class="mb-0 fw-bold" style="letter-spacing:-.02em;">
                <i class="bi bi-graph-up me-2 text-primary"></i>System Reports
            </h2>
            <p class="text-muted small mb-0 mt-1">Analyse financial performance and enrollment statistics.</p>
            <?php if (!$isSuperAdmin && $branchName): ?>
            <span class="badge bg-info bg-opacity-10 text-info border border-info mt-2 px-3 py-2">
                <i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="report-header-actions">
            <!-- Live update toggle -->
            <div class="live-toggle-wrap">
                <input class="form-check-input mb-0" type="checkbox" role="switch"
                       id="liveDataSwitch" style="cursor:pointer; width:36px; height:20px;">
                <label class="form-check-label small fw-bold text-primary mb-0" for="liveDataSwitch"
                       style="white-space:nowrap; cursor:pointer;">
                    <i class="bi bi-broadcast me-1"></i><span class="d-none d-sm-inline">Live</span>
                </label>
            </div>
            <button onclick="window.print()" class="btn btn-light shadow-sm text-muted fw-semibold px-3">
                <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Print</span>
            </button>
            <button id="exportBtn" class="btn btn-success shadow-sm fw-semibold px-3 text-white">
                <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export CSV</span>
            </button>
        </div>
    </div>

    <!-- ── Filter Card ──────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4" id="filterCard">
        <div class="card-header bg-transparent py-3 border-bottom">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-funnel me-1 opacity-75"></i>Filter Parameters
            </h6>
        </div>
        <div class="card-body pt-3">
            <form id="reportFilterForm">
                <div class="filter-grid">
                    <div>
                        <label class="form-label small fw-semibold text-muted mb-1">Start Date</label>
                        <input type="date" name="start_date" id="start_date"
                               class="form-control bg-light border-0"
                               value="<?= date('Y-m-01') ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold text-muted mb-1">End Date</label>
                        <input type="date" name="end_date" id="end_date"
                               class="form-control bg-light border-0"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <?php if ($isSuperAdmin): ?>
                    <div class="filter-branch">
                        <label class="form-label small fw-semibold text-muted mb-1">Branch</label>
                        <select name="branch_id" id="branch_id" class="form-select bg-light border-0">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="filter-submit">
                        <button type="submit" class="btn btn-primary w-100 shadow-sm fw-semibold">
                            <i class="bi bi-search me-1"></i>Generate
                        </button>
                    </div>
                </div>
            </form>

            <!-- Tab pills inside a scrollable strip -->
            <div class="tab-scroll-wrap mt-4 mb-1">
                <ul class="nav nav-pills" id="reportTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-tab="payments">
                            <i class="bi bi-cash-stack me-2"></i>Payments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-tab="enrollments">
                            <i class="bi bi-person-check me-2"></i>Enrollments
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-tab="branch_summary">
                            <i class="bi bi-buildings me-2"></i>Branch Summary
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── KPI Widgets ───────────────────────────────────────── -->
    <div class="row g-3 mb-4" id="summaryWidgets">
        <div class="col kpi-col">
            <div class="card border-0 shadow-sm h-100">
                <div class="kpi-card-report">
                    <div class="kpi-icon-lg kpi-revenue">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bolder text-success" style="letter-spacing:-.02em;" id="total_revenue">—</div>
                        <div class="small fw-semibold text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.5px;">Total Revenue</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col kpi-col">
            <div class="card border-0 shadow-sm h-100">
                <div class="kpi-card-report">
                    <div class="kpi-icon-lg kpi-enrolled">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bolder text-info" style="letter-spacing:-.02em;" id="total_enrollments">—</div>
                        <div class="small fw-semibold text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.5px;">New Enrollments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col kpi-col">
            <div class="card border-0 shadow-sm h-100">
                <div class="kpi-card-report">
                    <div class="kpi-icon-lg kpi-pending">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bolder text-warning" style="letter-spacing:-.02em;" id="total_pending">—</div>
                        <div class="small fw-semibold text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.5px;">Outstanding</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Report Table ──────────────────────────────────────── -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent py-3 border-bottom-0">
            <h5 class="mb-0 fw-bold" id="tableTitle">
                <i class="bi bi-table me-2 opacity-75"></i>Transaction &amp; Payment Log
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-wrap">
                <table class="table table-hover align-middle w-100 mb-0 report-table" id="reportTable">
                    <thead id="reportTableHead"></thead>
                    <tbody id="reportTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
let activeTab  = 'payments';
let dtInstance = null;
let liveInterval = null;
let currentPage = 0;

$(document).ready(function () {
    fetchReport();

    $('#reportFilterForm').on('submit', function (e) {
        e.preventDefault();
        fetchReport();
    });

    $(document).on('click', '#reportTabs .nav-link', function () {
        $('#reportTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        activeTab = $(this).data('tab');
        fetchReport();
    });

    $('#exportBtn').on('click', exportCSV);

    $('#liveDataSwitch').on('change', function () {
        if ($(this).is(':checked')) {
            fetchReport(true);
            liveInterval = setInterval(function () { fetchReport(true); }, 10000);
        } else {
            clearInterval(liveInterval);
            liveInterval = null;
        }
    });
});

/* ── Fetch ───────────────────────────────────────────────────── */
function fetchReport(isLive = false) {
    const actionMap = { payments: 'summary', enrollments: 'enrollments', branch_summary: 'branch_summary' };
    const params = $('#reportFilterForm').serialize() + '&action=' + (actionMap[activeTab] || 'summary');

    if (!isLive) {
        currentPage = 0;
        destroyDT();
        $('#reportTableBody').html('<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>');
    } else {
        if (dtInstance) { currentPage = dtInstance.page(); destroyDT(); }
    }

    $.get('models/api/report_api.php', params, function (res) {
        if (res.status !== 'success') {
            if (!isLive) Swal.fire('Error', res.message || 'Failed to load report.', 'error');
            return;
        }
        if      (activeTab === 'payments')       renderPayments(res);
        else if (activeTab === 'enrollments')    renderEnrollments(res);
        else if (activeTab === 'branch_summary') renderBranchSummary(res);

        if (isLive && dtInstance && currentPage > 0) {
            dtInstance.page(currentPage).draw('page');
        }
    }, 'json').fail(function () {
        if (!isLive) Swal.fire('Error', 'Server connection failed.', 'error');
    });
}

function destroyDT() {
    if (dtInstance) { dtInstance.destroy(); dtInstance = null; }
}

/* ── DT config (shared) ─────────────────────────────────────── */
function dtConfig(extraOpts) {
    return $.extend({
        paging: true,
        order: [],
        responsive: true,
        pageLength: 25,
        destroy: true,
        retrieve: false,
        language: { emptyTable: 'No records found for this period.' },
        dom: '<"d-flex flex-wrap justify-content-between align-items-center pb-3 px-3 gap-2"<"small"l><"input-group input-group-sm w-auto"f>>t<"d-flex flex-wrap justify-content-between align-items-center pt-3 px-3 gap-2"<"small text-muted"i><"pagination-sm"p>>'
    }, extraOpts || {});
}

/* ── Render: Payments ───────────────────────────────────────── */
function renderPayments(res) {
    const isSA = <?= $isSuperAdmin ? 'true' : 'false' ?>;

    $('#total_revenue').text('$' + parseFloat(res.summary.revenue).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}));
    $('#total_enrollments').text(res.summary.enrollments);
    $('#total_pending').text('$' + parseFloat(res.summary.pending).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}));
    $('#summaryWidgets').show();
    $('#tableTitle').html('<i class="bi bi-cash-stack me-2 text-primary opacity-75"></i>Transaction &amp; Payment Log');

    const branchTh = isSA ? '<th>Branch</th>' : '';
    $('#reportTableHead').html('<tr><th>Date</th><th>Student</th><th>Transaction ID</th><th>Method</th><th>Amount</th>' + branchTh + '</tr>');

    let rows = '';
    (res.data || []).forEach(function (item) {
        const mc = item.method === 'Cash' ? 'success' : (item.method === 'Mobile Money' ? 'info' : 'primary');
        const brTd = isSA ? `<td><span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>${escHtml(item.branch_name??'')}</span></td>` : '';
        rows += `<tr>
            <td><span class="fw-semibold text-muted small">${item.date??''}</span></td>
            <td class="fw-semibold">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;">
                        <i class="bi bi-person text-primary" style="font-size:.85rem;"></i>
                    </div>
                    <span>${escHtml(item.student_name??'')}</span>
                </div>
            </td>
            <td><code class="bg-light px-2 py-1 rounded text-dark border small">${escHtml(item.tx_id??'')}</code></td>
            <td><span class="badge-method bg-${mc} bg-opacity-10 text-${mc} border border-${mc}">${escHtml(item.method??'')}</span></td>
            <td><span class="fw-bold text-success">$${parseFloat(item.amount||0).toFixed(2)}</span></td>
            ${brTd}
        </tr>`;
    });

    const colCount = isSA ? 6 : 5;
    $('#reportTableBody').html(rows || emptyRow(colCount));
    if (res.data && res.data.length) dtInstance = $('#reportTable').DataTable(dtConfig());
}

/* ── Render: Enrollments ────────────────────────────────────── */
function renderEnrollments(res) {
    const isSA = <?= $isSuperAdmin ? 'true' : 'false' ?>;

    $('#summaryWidgets').show();
    $('#tableTitle').html('<i class="bi bi-person-check me-2 text-primary opacity-75"></i>Enrollment Log');

    const branchTh = isSA ? '<th>Branch</th>' : '';
    $('#reportTableHead').html('<tr><th>Date</th><th>Student</th><th>Course</th><th>Duration</th><th>Fees</th><th>Status</th>' + branchTh + '</tr>');

    let rows = '';
    (res.data || []).forEach(function (item) {
        let sc = 'secondary';
        if (item.status === 'Active')     sc = 'success';
        else if (item.status === 'Completed') sc = 'primary';
        else if (item.status === 'Dropped')   sc = 'danger';

        const brTd = isSA ? `<td><span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>${escHtml(item.branch_name??'')}</span></td>` : '';
        rows += `<tr>
            <td><span class="fw-semibold text-muted small">${item.date??''}</span></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;">
                        <i class="bi bi-person text-info" style="font-size:.85rem;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:.875rem;">${escHtml(item.student_name??'')}</div>
                        <div class="text-muted" style="font-size:.73rem;">${escHtml(item.student_code??'')}</div>
                    </div>
                </div>
            </td>
            <td class="fw-semibold text-muted small">${escHtml(item.course_name??'')}</td>
            <td><span class="badge bg-light text-dark border small"><i class="bi bi-clock me-1 text-muted"></i>${escHtml(item.duration??'')}</span></td>
            <td class="fw-bold">$${parseFloat(item.fees||0).toFixed(2)}</td>
            <td><span class="badge-method bg-${sc} bg-opacity-10 text-${sc} border border-${sc}">${item.status??'Unknown'}</span></td>
            ${brTd}
        </tr>`;
    });

    const colCount = isSA ? 7 : 6;
    $('#reportTableBody').html(rows || emptyRow(colCount));
    if (res.data && res.data.length) dtInstance = $('#reportTable').DataTable(dtConfig());
}

/* ── Render: Branch Summary ─────────────────────────────────── */
function renderBranchSummary(res) {
    $('#summaryWidgets').hide();
    $('#tableTitle').html('<i class="bi bi-buildings me-2 text-primary opacity-75"></i>Branch Summary Report');

    $('#reportTableHead').html('<tr><th>Branch</th><th>Students</th><th>Staff</th><th>Courses</th><th>Revenue (USD)</th></tr>');

    let rows = '';
    (res.data || []).forEach(function (item) {
        rows += `<tr>
            <td class="fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>${escHtml(item.branch_name??'')}</td>
            <td>${parseInt(item.total_students||0)}</td>
            <td>${parseInt(item.total_staff||0)}</td>
            <td>${parseInt(item.total_courses||0)}</td>
            <td><span class="fw-bold text-success">$${parseFloat(item.total_revenue||0).toFixed(2)}</span></td>
        </tr>`;
    });

    $('#reportTableBody').html(rows || emptyRow(5));
    if (res.data && res.data.length) dtInstance = $('#reportTable').DataTable(dtConfig());
}

/* ── Helpers ────────────────────────────────────────────────── */
function emptyRow(cols) {
    return `<tr><td colspan="${cols}" class="text-center text-muted py-5">
        <i class="bi bi-inbox opacity-50 d-block mb-3" style="font-size:2.5rem;"></i>
        No records found for this period.
    </td></tr>`;
}

function exportCSV() {
    const rows = [], headers = [];
    $('#reportTableHead th').each(function () { headers.push('"' + $(this).text().trim() + '"'); });
    rows.push(headers.join(','));
    $('#reportTableBody tr').each(function () {
        const cells = [];
        $(this).find('td').each(function () { cells.push('"' + $(this).text().trim().replace(/"/g,'""') + '"'); });
        if (cells.length) rows.push(cells.join(','));
    });
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'sbvs_report_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>