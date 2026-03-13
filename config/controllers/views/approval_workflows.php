<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db       = (new Database())->getConnection();
$role     = $_SESSION['role'] ?? '';
$isSA     = ($role === 'Super Admin');
$isBA     = ($role === 'Branch Admin');

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

// Branches list for Super Admin filter
$branches = $isSA
    ? $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle  = 'Approval Workflows';
$activePage = 'approval_workflows.php';
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
                <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-clipboard2-check-fill me-2"></i>Approval Workflows</h4>
                <p class="mb-0 opacity-75" style="font-size:.9rem;">
                    <?= $isSA ? 'Review and action all pending discount approval requests' : 'Submit and track discount approval requests for your branch' ?>
                    <?= (!$isSA && $branchName) ? ' — ' . htmlspecialchars($branchName) . ' Branch' : '' ?>
                </p>
            </div>
            <?php if ($isBA): ?>
            <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#requestModal">
                <i class="bi bi-plus-circle-fill"></i> Request Discount
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stats Row ────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#f59e0b;" id="countPending">–</div>
                        <div class="kpi-label">Pending</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#10b981;" id="countApproved">–</div>
                        <div class="kpi-label">Approved</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#ef4444;" id="countRejected">–</div>
                        <div class="kpi-label">Rejected</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-list-check"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#6366f1;" id="countTotal">–</div>
                        <div class="kpi-label">Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter / Table ───────────────────────────────────── -->
    <div class="card fade-up">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1" style="color:#6366f1;"></i> Discount Approval Requests</h6>
            <div class="d-flex gap-2">
                <select id="filterStatus" class="form-select form-select-sm" style="width:160px;border-radius:8px;">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="approvalTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Branch</th>
                            <th>Discount %</th>
                            <th>Requested By</th>
                            <th>Justification</th>
                            <th>Status</th>
                            <th>Date</th>
                            <?php if ($isSA): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<?php if ($isBA): ?>
<!-- Request Discount Modal (Branch Admin) -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="requestForm" class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-percent me-2"></i>Request Discount Approval</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex gap-2" style="border-radius:8px;font-size:.85rem;">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <span>Discounts above the system policy maximum require Super Admin approval.</span>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Student <span class="text-danger">*</span></label>
                        <select name="student_id" id="req_student" class="form-select" required>
                            <option value="">Loading students...</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Course <span class="text-danger">*</span></label>
                        <select name="course_id" id="req_course" class="form-select" required>
                            <option value="">Loading courses...</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Discount % <span class="text-danger">*</span> <span class="text-muted fw-normal" style="font-size:.8rem;" id="maxPctHint"></span></label>
                        <input type="number" name="discount_pct" class="form-control" min="1" max="100" step="0.1" placeholder="e.g. 20" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Justification <span class="text-danger">*</span></label>
                        <textarea name="justification" class="form-control" rows="3" placeholder="Explain the reason for this discount request..." required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="submitReqBtn">
                    <i class="bi bi-send me-1"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($isSA): ?>
<!-- Review Modal (Super Admin) -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="reviewForm" class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-clipboard2-check me-2"></i>Review Discount Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="review_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Decision <span class="text-danger">*</span></label>
                        <select name="status" id="review_status" class="form-select" required>
                            <option value="Approved">Approve</option>
                            <option value="Rejected">Reject</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Review Notes</label>
                        <textarea name="review_notes" class="form-control" rows="3" placeholder="Optional notes to the Branch Admin..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#f59e0b;color:#fff;border-radius:8px;font-weight:600;" id="submitReviewBtn">
                    <i class="bi bi-check-lg me-1"></i> Submit Review
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API   = 'models/api/approval_workflow_api.php';
const isSA  = <?= $isSA ? 'true' : 'false' ?>;
let table;

$(document).ready(function () {

    // Load policy hint
    if (!isSA) {
        $.getJSON(API + '?action=policy', function (res) {
            $('#maxPctHint').text(`(policy max without approval: ${res.max_discount_pct}%)`);
        });
        // Load students & courses for branch
        $.getJSON('models/api/student_api.php?action=list_simple', function (res) {
            const sel = $('#req_student').empty().append('<option value="">Select Student…</option>');
            (res.data || []).forEach(s => sel.append(`<option value="${s.id}">${escHtml(s.name)} (${escHtml(s.student_id)})</option>`));
        });
        $.getJSON('models/api/course_api.php?action=list_simple', function (res) {
            const sel = $('#req_course').empty().append('<option value="">Select Course…</option>');
            (res.data || []).forEach(c => sel.append(`<option value="${c.id}">${escHtml(c.name)}</option>`));
        });
    }

    const columns = [
        { data: null, render: (d,t,r,m) => m.row + 1 },
        { data: null, render: d => `<div class="fw-semibold" style="font-size:.85rem;">${escHtml(d.student_name)}</div><div class="text-muted" style="font-size:.75rem;">${escHtml(d.student_code)}</div>` },
        { data: 'course_name',      render: d => escHtml(d) },
        { data: 'branch_name',      render: d => `<span class="badge-branch">${escHtml(d)}</span>` },
        { data: 'discount_pct',     render: d => `<strong style="color:#ef4444;">${d}%</strong>` },
        { data: 'requested_by_name',render: d => `<span class="text-muted" style="font-size:.82rem;">${escHtml(d)}</span>` },
        { data: 'justification',    render: d => d ? `<span style="font-size:.82rem;" title="${escHtml(d)}">${escHtml(d.substring(0,60))}${d.length>60?'…':''}</span>` : '—' },
        { data: 'status', render: statusBadge },
        { data: 'created_at', render: d => `<span class="text-muted" style="font-size:.78rem;">${d ? d.substring(0,10) : '—'}</span>` },
    ];

    if (isSA) {
        columns.push({ data: null, orderable: false,
            render: d => d.status === 'Pending'
                ? `<button class="btn-action edit" title="Review" onclick="openReview(${d.id})"><i class="bi bi-clipboard2-check"></i></button>`
                : '—'
        });
    }

    table = $('#approvalTable').DataTable({
        processing: true,
        ajax: {
            url: buildUrl(),
            dataSrc: function (res) {
                const data = res.data || [];
                $('#countPending').text(data.filter(r => r.status === 'Pending').length);
                $('#countApproved').text(data.filter(r => r.status === 'Approved').length);
                $('#countRejected').text(data.filter(r => r.status === 'Rejected').length);
                $('#countTotal').text(data.length);
                return data;
            }
        },
        columns: columns,
        responsive: true,
        order: [[8, 'desc']],
        language: { emptyTable: 'No discount approval requests found.' }
    });

    // Status filter
    $('#filterStatus').on('change', function () {
        table.ajax.url(buildUrl()).load();
    });

    // Submit request (Branch Admin)
    $('#requestForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#submitReqBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Submitting...');
        $.ajax({ url: API + '?action=submit', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') { Swal.fire('Submitted!', res.message, 'success'); $('#requestModal').modal('hide'); $('#requestForm')[0].reset(); table.ajax.reload(); }
                else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Server error.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Submit Request'); }
        });
    });

    // Review (Super Admin)
    $('#reviewForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#submitReviewBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({ url: API + '?action=review', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') { Swal.fire('Done!', res.message, 'success'); $('#reviewModal').modal('hide'); table.ajax.reload(); }
                else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Server error.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Submit Review'); }
        });
    });

    function buildUrl() {
        return API + '?action=list&status=' + encodeURIComponent($('#filterStatus').val());
    }

    function statusBadge(s) {
        const map = {
            Pending:  ['rgba(245,158,11,.15)','#f59e0b','hourglass-split'],
            Approved: ['rgba(16,185,129,.15)','#10b981','check-circle-fill'],
            Rejected: ['rgba(239,68,68,.15)', '#ef4444','x-circle-fill'],
        };
        const [bg, color, icon] = map[s] || ['rgba(100,116,139,.15)','#64748b','dash'];
        return `<span style="background:${bg};color:${color};border-radius:6px;padding:3px 8px;font-size:.75rem;font-weight:600;white-space:nowrap;"><i class="bi bi-${icon} me-1"></i>${escHtml(s)}</span>`;
    }

    function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    // ── end of $(document).ready ─────────────────────────────────────────────
});

function openReview(id) {
    document.getElementById('review_id').value = id;
    document.getElementById('review_status').value = 'Approved';
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>
</body>
</html>
