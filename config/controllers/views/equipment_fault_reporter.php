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
$pageTitle = 'Equipment Fault Reporter';
$activePage = 'equipment_fault_reporter.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-tools text-danger me-2"></i>Equipment Fault Reporter</h3>
            <p class="text-muted mb-0">Flag faulty machines/tools for maintenance follow-up.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form id="faultForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Equipment Name *</label>
                        <input type="text" name="equipment_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Severity *</label>
                        <select name="severity" class="form-select" required>
                            <option>Low</option><option selected>Medium</option><option>High</option><option>Critical</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Workshop bay / lab room">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Issue Description *</label>
                        <textarea name="issue_notes" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Photo (optional)</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-danger" type="submit"><i class="bi bi-send me-1"></i>Submit Fault Report</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Recent Fault Reports</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="faultTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Equipment</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Reported</th>
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

function severityBadge(s) {
    const map = { Low: 'bg-success', Medium: 'bg-warning text-dark', High: 'bg-danger', Critical: 'bg-dark' };
    const c = map[s] || 'bg-secondary';
    return `<span class="badge ${c}">${esc(s)}</span>`;
}

function loadFaults() {
    $.getJSON('models/api/instructor_panel_api.php?action=list_faults', function(res){
        const tbody = $('#faultTable tbody');
        tbody.empty();
        if (!res.success || !res.data.length) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">No fault reports yet.</td></tr>');
            return;
        }
        res.data.forEach(r => {
            tbody.append(`<tr>
                <td class="ps-3"><div class="fw-semibold">${esc(r.equipment_name)}</div><div class="small text-muted">${esc(r.issue_notes || '')}</div></td>
                <td>${severityBadge(r.severity)}</td>
                <td><span class="badge bg-light text-dark border">${esc(r.status)}</span></td>
                <td>${esc(r.location || '—')}</td>
                <td class="small text-muted">${esc(r.created_at)}</td>
            </tr>`);
        });
    });
}

$('#faultForm').on('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: 'models/api/instructor_panel_api.php?action=submit_fault',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            if (res.success) {
                Swal.fire('Submitted', res.message || 'Fault report submitted.', 'success');
                $('#faultForm')[0].reset();
                loadFaults();
            } else {
                Swal.fire('Error', res.message || 'Submit failed.', 'error');
            }
        },
        error: function(){ Swal.fire('Error', 'Failed to submit fault report.', 'error'); }
    });
});

$(function(){ loadFaults(); });
</script>
</body>
</html>
<?php ob_end_flush(); ?>
