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
$pageTitle = 'Resource Submissions';
$activePage = 'instructor_resource_links.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-link-45deg text-primary me-2"></i>Instructor Resource Submissions</h3>
            <p class="text-muted mb-0">Upload study links/PDFs for admin verification before student visibility.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form id="resourceForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Module Name *</label>
                        <input type="text" name="module_name" class="form-control" value="Core Module" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Resource Title *</label>
                        <input type="text" name="title" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Type *</label>
                        <select name="resource_type" id="resType" class="form-select" required>
                            <option value="Link">External Link</option>
                            <option value="PDF">PDF</option>
                        </select>
                    </div>
                    <div class="col-md-8" id="resUrlWrap">
                        <label class="form-label fw-semibold">Resource URL *</label>
                        <input type="url" name="resource_url" id="resUrl" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-md-8 d-none" id="resFileWrap">
                        <label class="form-label fw-semibold">PDF File *</label>
                        <input type="file" name="resource_file" id="resFile" class="form-control" accept="application/pdf">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Submit for Approval</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Current Status Tracker</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="resTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Title</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Rejection Reason</th>
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
function statusBadge(s){
    const map = {
        Pending: ['bg-warning text-dark', 'hourglass-split'],
        Approved: ['bg-success', 'check-circle-fill'],
        Rejected: ['bg-danger', 'x-circle-fill']
    };
    const p = map[s] || ['bg-secondary','dash'];
    return `<span class="badge ${p[0]}"><i class="bi bi-${p[1]} me-1"></i>${esc(s)}</span>`;
}

function toggleResourceType(){
    const type = $('#resType').val();
    if (type === 'PDF') {
        $('#resUrlWrap').addClass('d-none');
        $('#resFileWrap').removeClass('d-none');
        $('#resUrl').prop('required', false).val('');
        $('#resFile').prop('required', true);
    } else {
        $('#resFileWrap').addClass('d-none');
        $('#resUrlWrap').removeClass('d-none');
        $('#resFile').prop('required', false).val('');
        $('#resUrl').prop('required', true);
    }
}

function loadResources(){
    $.getJSON('models/api/instructor_panel_api.php?action=list_resources', function(res){
        const tbody = $('#resTable tbody');
        tbody.empty();
        if (!res.success || !res.data.length) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No submissions yet.</td></tr>');
            return;
        }
        res.data.forEach(r => {
            tbody.append(`<tr>
                <td class="ps-3 fw-semibold">${esc(r.title)}</td>
                <td>${esc(r.module_name)}</td>
                <td>${esc(r.resource_type)}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="small text-danger">${esc(r.rejection_reason || '—')}</td>
                <td class="small text-muted">${esc(r.created_at)}</td>
            </tr>`);
        });
    });
}

$('#resourceForm').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({
        url: 'models/api/instructor_panel_api.php?action=submit_resource',
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            if (res.success) {
                Swal.fire('Submitted', res.message || 'Resource sent for approval.', 'success');
                $('#resourceForm')[0].reset();
                toggleResourceType();
                loadResources();
            } else {
                Swal.fire('Error', res.message || 'Submit failed.', 'error');
            }
        },
        error: function(){ Swal.fire('Error', 'Request failed.', 'error'); }
    });
});

$(function(){
    $('#resType').on('change', toggleResourceType);
    toggleResourceType();
    loadResources();
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
