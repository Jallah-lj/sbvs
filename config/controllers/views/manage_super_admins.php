<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Super Admins';
$activePage = 'manage_super_admins.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Page Header -->
        <div class="page-header fade-up">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
                <div>
                    <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-person-gear me-2"></i>Super Admin Management</h4>
                    <p class="mb-0 opacity-75" style="font-size:.9rem;">Manage system-level Super Administrator accounts</p>
                </div>
                <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#addSuperAdminModal">
                    <i class="bi bi-person-plus-fill"></i> Add Super Admin
                </button>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(99,102,241,.1);color:#6366f1;"><i class="bi bi-person-gear"></i></div>
                        <div>
                            <div class="stat-value" style="color:#6366f1;" id="totalSuperAdmins">–</div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="stat-value" style="color:#10b981;" id="activeSuperAdmins">–</div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
                        <div>
                            <div class="stat-value" style="color:#ef4444;" id="inactiveSuperAdmins">–</div>
                            <div class="stat-label">Inactive</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card fade-up">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1" style="color:#6366f1;"></i> Super Administrators</h6>
            </div>
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="superAdminTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Super Admin Modal -->
<div class="modal fade" id="addSuperAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="addSuperAdminForm" class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-person-plus-fill me-2"></i>Add Super Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. john@sbvs.com" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="addPassword" class="form-control" placeholder="Min. 6 characters" required>
                            <button type="button" class="btn btn-outline-secondary" style="border-radius:0 10px 10px 0;" onclick="togglePw('addPassword')">
                                <i class="bi bi-eye" id="addPasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="saveSuperAdminBtn">
                    <i class="bi bi-check-lg me-1"></i> Create
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Super Admin Modal -->
<div class="modal fade" id="editSuperAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="editSuperAdminForm" class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-pencil-square me-2"></i>Edit Super Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_sa_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_sa_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_sa_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_sa_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Password <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">(optional)</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="editPassword" class="form-control" placeholder="Leave blank to keep">
                            <button type="button" class="btn btn-outline-secondary" style="border-radius:0 10px 10px 0;" onclick="togglePw('editPassword')">
                                <i class="bi bi-eye" id="editPasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#f59e0b;color:#fff;border-radius:8px;font-weight:600;" id="updateSuperAdminBtn">
                    <i class="bi bi-check-lg me-1"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API = 'models/api/super_admin_api.php';

$(document).ready(function () {
    const table = $('#superAdminTable').DataTable({
        processing: true,
        ajax: {
            url: API + '?action=list',
            dataSrc: function (res) {
                const data = res.data || [];
                let total = data.length, active = 0, inactive = 0;
                data.forEach(function (r) { r.status === 'Active' ? active++ : inactive++; });
                $('#totalSuperAdmins').text(total);
                $('#activeSuperAdmins').text(active);
                $('#inactiveSuperAdmins').text(inactive);
                return data;
            }
        },
        columns: [
            { data: 'name', render: d => `<span class="fw-semibold">${d}</span>` },
            { data: 'email', render: d => `<span class="text-muted" style="font-size:.85rem;">${d}</span>` },
            {
                data: 'status',
                render: d => d === 'Active'
                    ? '<span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>'
                    : '<span class="badge-inactive"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>'
            },
            {
                data: 'created_at',
                render: d => d ? `<span class="text-muted" style="font-size:.82rem;">${d.substring(0, 10)}</span>` : '—'
            },
            {
                data: null, orderable: false,
                render: function (data) {
                    return '<div class="d-flex gap-1">'
                        + '<button class="btn-action edit" title="Edit" onclick="openEdit(' + data.id + ',\'' + escJs(data.name) + '\',\'' + escJs(data.email) + '\',\'' + data.status + '\')"><i class="bi bi-pencil"></i></button>'
                        + '<button class="btn-action delete" title="Delete" onclick="deleteSuperAdmin(' + data.id + ',\'' + escJs(data.name) + '\')"><i class="bi bi-trash"></i></button>'
                        + '</div>';
                }
            }
        ],
        responsive: true,
        language: { emptyTable: "No Super Admins registered yet." }
    });

    // Add
    $('#addSuperAdminForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#saveSuperAdminBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({
            url: API + '?action=save',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Added!', res.message, 'success');
                    $('#addSuperAdminModal').modal('hide');
                    $('#addSuperAdminForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Create'); }
        });
    });

    // Edit
    $('#editSuperAdminForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#updateSuperAdminBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({
            url: API + '?action=update',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Updated!', res.message, 'success');
                    $('#editSuperAdminModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Update'); }
        });
    });
});

function openEdit(id, name, email, status) {
    document.getElementById('edit_sa_id').value     = id;
    document.getElementById('edit_sa_name').value   = name;
    document.getElementById('edit_sa_email').value  = email;
    document.getElementById('edit_sa_status').value = status;
    document.getElementById('editPassword').value   = '';
    new bootstrap.Modal(document.getElementById('editSuperAdminModal')).show();
}

function deleteSuperAdmin(id, name) {
    Swal.fire({
        title: 'Delete Super Admin?',
        html: 'This will permanently delete <strong>' + name + '</strong>.<br><span class="text-danger small">This cannot be undone.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then(function (result) {
        if (result.isConfirmed) {
            $.post(API + '?action=delete', { id: id }, function (res) {
                if (res.status === 'success') {
                    Swal.fire('Deleted!', res.message, 'success');
                    $('#superAdminTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

function togglePw(fieldId) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById(fieldId + 'Icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function escJs(str) {
    return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
</script>
</body>
</html>
