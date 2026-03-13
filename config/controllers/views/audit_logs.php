<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();
$branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Audit Logs';
$activePage = 'audit_logs.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ──────────────────────────────────────── -->
    <div class="page-header fade-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
            <div>
                <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-journal-text me-2"></i>Audit Logs</h4>
                <p class="mb-0 opacity-75" style="font-size:.9rem;">Complete trail of changes across all branches and modules</p>
            </div>
        </div>
    </div>

    <!-- ── Filters ──────────────────────────────────────────── -->
    <div class="card mb-4 fade-up">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Module</label>
                    <select id="filterModule" class="form-select form-select-sm">
                        <option value="">All Modules</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Branch</label>
                    <select id="filterBranch" class="form-select form-select-sm">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">From</label>
                    <input type="date" id="filterFrom" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-sm-2">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">To</label>
                    <input type="date" id="filterTo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-sm w-100" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="applyFilters">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Log Table ────────────────────────────────────────── -->
    <div class="card fade-up">
        <div class="card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-columns-reverse me-1" style="color:#6366f1;"></i> Activity Log</h6>
        </div>
        <div class="card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="auditTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                            <th>Date / Time</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
let auditTable;

$(document).ready(function () {

    // Load modules dropdown
    $.getJSON('models/api/audit_log_api.php?action=modules', function (res) {
        (res.data || []).forEach(function (m) {
            $('#filterModule').append(`<option value="${escHtml(m)}">${escHtml(m)}</option>`);
        });
    });

    // Init DataTable
    auditTable = $('#auditTable').DataTable({
        processing: true,
        ajax: {
            url: buildUrl(),
            dataSrc: 'data'
        },
        columns: [
            { data: null,         render: (d,t,r,m) => m.row + 1 },
            { data: 'user_name',  render: d => `<span class="fw-semibold">${escHtml(d)}</span>` },
            { data: 'user_role',  render: d => roleBadge(d) },
            { data: 'branch_name',render: d => `<span class="badge-branch">${escHtml(d)}</span>` },
            { data: 'module',     render: d => `<span class="badge bg-secondary bg-opacity-10 text-secondary" style="border-radius:6px;font-size:.75rem;">${escHtml(d)}</span>` },
            { data: 'action',     render: d => `<span style="font-size:.85rem;">${escHtml(d)}</span>` },
            { data: 'record_id',  render: d => d ? `<code style="font-size:.78rem;">#${d}</code>` : '—' },
            { data: 'ip_address', render: d => `<span class="text-muted" style="font-size:.78rem;">${escHtml(d||'—')}</span>` },
            { data: 'created_at', render: d => `<span class="text-muted" style="font-size:.78rem;">${d ? d.substring(0,19).replace('T',' ') : '—'}</span>` }
        ],
        order: [[8, 'desc']],
        responsive: true,
        pageLength: 25,
        language: { emptyTable: 'No audit log entries found.' }
    });

    // Apply filters
    $('#applyFilters').on('click', function () {
        auditTable.ajax.url(buildUrl()).load();
    });

    function buildUrl() {
        return 'models/api/audit_log_api.php?action=list'
            + '&module='    + encodeURIComponent($('#filterModule').val())
            + '&branch_id=' + encodeURIComponent($('#filterBranch').val())
            + '&date_from=' + encodeURIComponent($('#filterFrom').val())
            + '&date_to='   + encodeURIComponent($('#filterTo').val());
    }

    function roleBadge(role) {
        const map = {
            'Super Admin':  ['rgba(99,102,241,.15)','#6366f1'],
            'Branch Admin': ['rgba(16,185,129,.15)','#10b981'],
        };
        const [bg, color] = map[role] || ['rgba(100,116,139,.15)','#64748b'];
        return `<span style="background:${bg};color:${color};border-radius:6px;padding:3px 8px;font-size:.75rem;font-weight:600;">${escHtml(role)}</span>`;
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
</script>
</body>
</html>
