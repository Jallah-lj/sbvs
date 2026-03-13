<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db   = (new Database())->getConnection();
$role = $_SESSION['role'] ?? '';
$isSA = ($role === 'Super Admin');
$isBA = ($role === 'Branch Admin');

if (!$isSA && !$isBA) {
    header("Location: dashboard.php");
    exit;
}

$branchId   = (int)($_SESSION['branch_id'] ?? 0);
$branchName = '';
if (!$isSA && $branchId) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$branchId]);
    $branchName = $bStmt->fetchColumn() ?: '';
}
$branches = $isSA
    ? $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle  = 'Attendance';
$activePage = 'attendance.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<style>
.att-present { background:rgba(16,185,129,.1);  color:#10b981; font-weight:600; border-radius:6px; padding:2px 8px; font-size:.75rem; }
.att-absent  { background:rgba(239,68,68,.1);   color:#ef4444; font-weight:600; border-radius:6px; padding:2px 8px; font-size:.75rem; }
.att-late    { background:rgba(245,158,11,.1);  color:#f59e0b; font-weight:600; border-radius:6px; padding:2px 8px; font-size:.75rem; }
.att-excused { background:rgba(14,165,233,.1);  color:#0ea5e9; font-weight:600; border-radius:6px; padding:2px 8px; font-size:.75rem; }
</style>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ──────────────────────────────────────── -->
    <div class="page-header fade-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
            <div>
                <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-calendar-check-fill me-2"></i>Attendance</h4>
                <p class="mb-0 opacity-75" style="font-size:.9rem;">
                    Record and track daily attendance per class batch
                    <?= (!$isSA && $branchName) ? ' — ' . htmlspecialchars($branchName) . ' Branch' : '' ?>
                </p>
            </div>
            <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" id="takeAttBtn">
                <i class="bi bi-plus-circle-fill"></i> Take Attendance
            </button>
        </div>
    </div>

    <!-- ── Summary Stats ────────────────────────────────────── -->
    <div class="row g-3 mb-4" id="summaryRow">
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#10b981;" id="cntPresent">–</div>
                        <div class="kpi-label">Present Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="bi bi-person-x-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#ef4444;" id="cntAbsent">–</div>
                        <div class="kpi-label">Absent Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(245,158,11,.1);color:#f59e0b;"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#f59e0b;" id="cntLate">–</div>
                        <div class="kpi-label">Late Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(14,165,233,.1);color:#0ea5e9;"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#0ea5e9;" id="cntTotal">–</div>
                        <div class="kpi-label">Total Records</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Attendance History ────────────────────────────────── -->
    <div class="card fade-up">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1" style="color:#6366f1;"></i> Attendance Records</h6>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($isSA): ?>
                <select id="filterBranchHist" class="form-select form-select-sm" style="width:150px;border-radius:8px;">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select id="filterBatchHist" class="form-select form-select-sm" style="width:170px;border-radius:8px;">
                    <option value="">All Batches</option>
                </select>
                <input type="date" id="filterFrom" class="form-control form-control-sm" style="width:140px;border-radius:8px;" value="<?= date('Y-m-01') ?>">
                <input type="date" id="filterTo"   class="form-control form-control-sm" style="width:140px;border-radius:8px;" value="<?= date('Y-m-d') ?>">
                <button class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="loadHistBtn">
                    <i class="bi bi-funnel me-1"></i> Load
                </button>
            </div>
        </div>
        <div class="card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<!-- Take Attendance Modal -->
<div class="modal fade" id="takeAttModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-calendar-plus-fill me-2"></i>Take Attendance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Session selector -->
                <div class="row g-3 mb-4">
                    <?php if ($isSA): ?>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                        <select id="attBranch" class="form-select">
                            <option value="">Select Branch…</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Batch / Class <span class="text-danger">*</span></label>
                        <select id="attBatch" class="form-select">
                            <option value="">Select Batch…</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" id="attDate" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn w-100" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="loadStudentsBtn">
                            <i class="bi bi-search me-1"></i> Load
                        </button>
                    </div>
                </div>

                <!-- Mark all buttons -->
                <div class="d-flex gap-2 mb-3" id="markAllBtns" style="display:none!important;">
                    <button class="btn btn-sm btn-outline-success" onclick="markAll('Present')"><i class="bi bi-check-all me-1"></i>All Present</button>
                    <button class="btn btn-sm btn-outline-danger"  onclick="markAll('Absent')"><i class="bi bi-x-lg me-1"></i>All Absent</button>
                </div>

                <!-- Student rows -->
                <div id="studentAttRows"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="saveAttBtn" disabled>
                    <i class="bi bi-floppy-fill me-1"></i> Save Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const ATT_API = 'models/api/attendance_api.php';
const isSA    = <?= $isSA ? 'true' : 'false' ?>;
let attTable;

$(document).ready(function () {

    // ── Load today's summary ─────────────────────────────────
    loadSummary();

    // ── Init history table ───────────────────────────────────
    attTable = $('#attendanceTable').DataTable({
        data: [],
        columns: [
            { data: null, render: (d,t,r,m) => m.row + 1 },
            { data: 'attend_date', render: d => `<span class="text-muted" style="font-size:.82rem;">${d||'—'}</span>` },
            { data: 'student_name', render: d => `<span class="fw-semibold" style="font-size:.85rem;">${escHtml(d)}</span>` },
            { data: 'student_code', render: d => `<code style="font-size:.78rem;">${escHtml(d)}</code>` },
            { data: 'status', render: statusBadge },
            { data: 'notes',  render: d => escHtml(d||'—') }
        ],
        responsive: true,
        language: { emptyTable: 'No records yet. Use Load button to fetch records.' }
    });

    // ── Load batches on branch change (SA mode) ──────────────
    if (isSA) {
        $('#attBranch').on('change', function () { loadBatches('attBatch', $(this).val()); });
        $('#filterBranchHist').on('change', function () { loadBatches('filterBatchHist', $(this).val()); });
    } else {
        loadBatches('attBatch');
        loadBatches('filterBatchHist');
    }

    // ── Open take-attendance modal ───────────────────────────
    $('#takeAttBtn').on('click', function () {
        $('#studentAttRows').empty();
        $('#saveAttBtn').prop('disabled', true);
        $('#markAllBtns').hide();
        new bootstrap.Modal(document.getElementById('takeAttModal')).show();
    });

    // ── Load students for selected batch ─────────────────────
    $('#loadStudentsBtn').on('click', function () {
        const batchId = $('#attBatch').val();
        const date    = $('#attDate').val();
        if (!batchId || !date) { Swal.fire('Missing', 'Select a batch and date first.', 'warning'); return; }

        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>');
        $.getJSON(ATT_API + `?action=batch_students&batch_id=${batchId}&date=${date}`, function (res) {
            const rows = res.data || [];
            if (!rows.length) { $('#studentAttRows').html('<p class="text-muted text-center py-3">No active enrollments in this batch.</p>'); return; }

            let html = '<table class="table table-sm align-middle"><thead><tr><th>Student</th><th>ID</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
            rows.forEach(function (r, i) {
                const sel = ['Present','Absent','Late','Excused'].map(s =>
                    `<option value="${s}" ${r.att_status===s?'selected':''}>${s}</option>`
                ).join('');
                html += `<tr>
                    <td class="fw-semibold" style="font-size:.85rem;">${escHtml(r.student_name)}</td>
                    <td><code style="font-size:.78rem;">${escHtml(r.student_code)}</code></td>
                    <td>
                        <input type="hidden" class="att-student-id" value="${r.student_id}">
                        <select class="form-select form-select-sm att-status" style="width:120px;border-radius:6px;">
                            ${sel}
                        </select>
                    </td>
                    <td><input type="text" class="form-control form-control-sm att-notes" placeholder="optional" value="${escHtml(r.notes)}" style="border-radius:6px;font-size:.8rem;"></td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#studentAttRows').html(html);
            $('#saveAttBtn').prop('disabled', false);
            $('#markAllBtns').show();
        }).always(function () {
            $('#loadStudentsBtn').prop('disabled', false).html('<i class="bi bi-search me-1"></i> Load');
        });
    });

    // ── Save attendance ──────────────────────────────────────
    $('#saveAttBtn').on('click', function () {
        const batchId  = $('#attBatch').val();
        const date     = $('#attDate').val();
        const branchId = isSA ? $('#attBranch').val() : '';
        const records  = [];

        $('.att-student-id').each(function (i) {
            records.push({
                student_id: $(this).val(),
                status:     $('.att-status').eq(i).val(),
                notes:      $('.att-notes').eq(i).val()
            });
        });

        if (!batchId || !date || !records.length) { Swal.fire('Missing', 'Please load students first.', 'warning'); return; }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');

        // Build POST body manually for nested array
        let formData = `batch_id=${encodeURIComponent(batchId)}&attend_date=${encodeURIComponent(date)}`;
        if (branchId) formData += `&branch_id=${encodeURIComponent(branchId)}`;
        records.forEach(function (r, i) {
            formData += `&records[${i}][student_id]=${encodeURIComponent(r.student_id)}`;
            formData += `&records[${i}][status]=${encodeURIComponent(r.status)}`;
            formData += `&records[${i}][notes]=${encodeURIComponent(r.notes)}`;
        });

        $.ajax({
            url: ATT_API + '?action=save', type: 'POST',
            data: formData,
            contentType: 'application/x-www-form-urlencoded',
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Saved!', res.message, 'success');
                    $('#takeAttModal').modal('hide');
                    loadSummary();
                } else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Server error. Please try again.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-floppy-fill me-1"></i> Save Attendance'); }
        });
    });

    // ── Load history ─────────────────────────────────────────
    $('#loadHistBtn').on('click', loadHistory);

    function loadHistory() {
        const batchId = $('#filterBatchHist').val();
        const from    = $('#filterFrom').val();
        const to      = $('#filterTo').val();
        const bid     = isSA ? ($('#filterBranchHist').val() || '') : '';

        $.getJSON(ATT_API + `?action=list&batch_id=${batchId}&date_from=${from}&date_to=${to}&branch_id=${bid}`, function (res) {
            attTable.clear().rows.add(res.data || []).draw();
        });
    }

    function loadBatches(selectId, branchOverride) {
        const bid = branchOverride !== undefined ? branchOverride : '';
        $.getJSON(ATT_API + `?action=batches&branch_id=${bid}`, function (res) {
            const sel = $(`#${selectId}`).empty().append('<option value="">All Batches</option>');
            (res.data || []).forEach(b => sel.append(`<option value="${b.id}">${escHtml(b.batch_name)} — ${escHtml(b.course_name)}</option>`));
        });
    }

    function loadSummary() {
        $.getJSON(ATT_API + '?action=summary', function (res) {
            $('#cntPresent').text(res.present  || 0);
            $('#cntAbsent').text(res.absent    || 0);
            $('#cntLate').text(res.late        || 0);
            $('#cntTotal').text(res.total      || 0);
        });
    }

    function statusBadge(s) {
        return `<span class="att-${(s||'').toLowerCase()}">${escHtml(s||'—')}</span>`;
    }

    function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
});

function markAll(status) {
    $('.att-status').val(status);
}

function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
