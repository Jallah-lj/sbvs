<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../../DashboardSecurity.php';
$role = $_SESSION['role'] ?? '';
$isAllowed = in_array($role, ['Teacher', 'Super Admin', 'Branch Admin', 'Admin'], true);
if (!$isAllowed) {
    header("Location: dashboard.php");
    exit;
}

$csrfToken = DashboardSecurity::generateToken();
$pageTitle = 'Material Requisition';
$activePage = 'material_requisition.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-box-seam text-primary me-2"></i>Material Requisition</h3>
            <p class="text-muted mb-0">Request workshop consumables from the central store.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form id="reqForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Item *</label>
                        <input type="text" name="item_name" class="form-control" placeholder="Timber, cables, solder..." required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Quantity *</label>
                        <input type="number" name="quantity" min="0.01" step="0.01" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Unit</label>
                        <input type="text" name="unit" class="form-control" value="pcs">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Urgency *</label>
                        <select name="urgency" class="form-select" required>
                            <option>Low</option><option selected>Medium</option><option>High</option><option>Critical</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Justification *</label>
                        <textarea name="justification" class="form-control" rows="3" required placeholder="Why is this material needed for training?"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Submit Requisition</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Recent Requisitions</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reqTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Item</th>
                            <th>Qty</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Created</th>
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
function esc(v){ return $('<div>').text(v ?? '').html(); }

function urgencyBadge(u) {
    const map = { Low: 'bg-success', Medium: 'bg-warning text-dark', High: 'bg-danger', Critical: 'bg-dark' };
    const c = map[u] || 'bg-secondary';
    return `<span class="badge ${c}">${esc(u)}</span>`;
}

function statusBadge(s) {
    const map = {
        Pending: ['bg-warning text-dark', 'hourglass-split'],
        Approved: ['bg-success', 'check-circle-fill'],
        Rejected: ['bg-danger', 'x-circle-fill'],
        Issued: ['bg-primary', 'box-seam-fill']
    };
    const p = map[s] || ['bg-secondary', 'dash'];
    return `<span class="badge ${p[0]}"><i class="bi bi-${p[1]} me-1"></i>${esc(s)}</span>`;
}

function loadReqs() {
    $.getJSON('models/api/instructor_panel_api.php?action=list_requisitions', function(res){
        const tbody = $('#reqTable tbody');
        tbody.empty();
        if (!res.success || !res.data.length) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No requisitions yet.</td></tr>');
            return;
        }
        res.data.forEach(r => {
            tbody.append(`<tr>
                <td class="ps-3"><div class="fw-semibold">${esc(r.item_name)}</div></td>
                <td>${Number(r.quantity)} ${esc(r.unit)}</td>
                <td>${urgencyBadge(r.urgency)}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="small ${r.status === 'Rejected' ? 'text-danger' : 'text-muted'}">${esc(r.rejection_reason || '—')}</td>
                <td class="small text-muted">${esc(r.created_at)}</td>
            </tr>`);
        });
    });
}

$('#reqForm').on('submit', function(e){
    e.preventDefault();
    $.post('models/api/instructor_panel_api.php?action=submit_requisition', $(this).serialize(), function(res){
        if (res.success) {
            Swal.fire('Submitted', res.message || 'Requisition submitted.', 'success');
            $('#reqForm')[0].reset();
            loadReqs();
        } else {
            Swal.fire('Error', res.message || 'Submit failed.', 'error');
        }
    }, 'json').fail(function(){
        Swal.fire('Error', 'Failed to submit requisition.', 'error');
    });
});

$(function(){ loadReqs(); });
</script>
</body>
</html>
<?php ob_end_flush(); ?>
