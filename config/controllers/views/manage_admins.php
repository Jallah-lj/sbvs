<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();
$branches = $db->query("SELECT id, name FROM branches WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Manage Admins';
$activePage = 'manage_admins.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- ── Page Header ────────────────────────────── -->
        <div class="page-header fade-up">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
                <div>
                    <h4 class="fw-bold mb-1" style="letter-spacing:-0.02em;"><i class="bi bi-shield-lock-fill me-2"></i>Branch Admin Management</h4>
                    <p class="mb-0 opacity-75" style="font-size:.9rem;">Create, edit, and manage branch administrator accounts</p>
                </div>
                <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-plus-fill"></i> Add Branch Admin
                </button>
            </div>
        </div>

        <!-- ── Stat Cards ─────────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="stat-value" style="color:#6366f1;" id="totalAdmins">–</div>
                            <div class="stat-label">Total Admins</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="stat-value" style="color:#10b981;" id="activeAdmins">–</div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
                        <div>
                            <div class="stat-value" style="color:#ef4444;" id="inactiveAdmins">–</div>
                            <div class="stat-label">Inactive</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:rgba(14,165,233,0.1);color:#0ea5e9;"><i class="bi bi-buildings"></i></div>
                        <div>
                            <div class="stat-value" style="color:#0ea5e9;" id="branchCount">–</div>
                            <div class="stat-label">Branches</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Admin Table ────────────────────────────── -->
        <div class="card fade-up">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1" style="color:#6366f1;"></i> Branch Administrators</h6>
            </div>
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="adminTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>Status</th>
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

<!-- Add Branch Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form id="addAdminForm" class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-person-plus-fill me-2"></i>Add Branch Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. admin@branch.com" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Assign Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Branch...</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="passwordField" class="form-control" placeholder="Minimum 6 characters" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="confirmPasswordField" class="form-control" placeholder="Re-enter password" required minlength="6">
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,0.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="saveAdminBtn">
                    <i class="bi bi-check-lg me-1"></i> Create Admin
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form id="editAdminForm" class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-pencil-square me-2"></i>Edit Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_a_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_a_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_a_email" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Assign Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="edit_a_branch_id" class="form-select" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_a_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">New Password <small class="text-muted fw-normal">(leave blank to keep current)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password" minlength="6">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,0.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#f59e0b;color:#fff;border-radius:8px;font-weight:600;" id="updateAdminBtn">
                    <i class="bi bi-check-lg me-1"></i> Update Admin
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$(document).ready(function () {

    // Initialize DataTable
    const table = $('#adminTable').DataTable({
        processing: true,
        ajax: {
            url: 'models/api/admin_api.php?action=list',
            dataSrc: function (json) {
                const data = json.data || [];
                $('#totalAdmins').text(data.length);
                $('#activeAdmins').text(data.filter(r => r.status === 'Active').length);
                $('#inactiveAdmins').text(data.filter(r => r.status === 'Inactive').length);
                const uniqueBranches = [...new Set(data.map(r => r.branch_name))];
                $('#branchCount').text(uniqueBranches.length);
                return data;
            }
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'name', render: d => `<span class="fw-semibold">${d}</span>` },
            { data: 'email', render: d => `<span class="text-muted" style="font-size:.85rem;">${d}</span>` },
            { data: 'branch_name', render: d => `<span class="badge-branch">${d}</span>` },
            {
                data: 'status',
                render: d => d === 'Active'
                    ? `<span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>`
                    : `<span class="badge-inactive"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>`
            },
            {
                data: null, orderable: false,
                render: function (data) {
                    return `
                        <button class="btn btn-sm btn-warning me-1" title="Edit"
                            onclick="openEditAdmin(${data.id}, '${data.name.replace(/'/g,"\\'")}', '${data.email}', ${JSON.stringify(data.branch_id)}, '${data.status}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" title="Delete" onclick="deleteAdmin(${data.id}, '${data.name.replace(/'/g,"\\'")}')">
                            <i class="bi bi-trash"></i>
                        </button>`;
                }
            }
        ],
        responsive: true,
        language: { emptyTable: "No branch admins found." }
    });

    // Toggle password visibility
    $('#togglePassword').on('click', function () {
        const field = $('#passwordField');
        const icon  = $('#eyeIcon');
        if (field.attr('type') === 'password') {
            field.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            field.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // Add Admin form submission
    $('#addAdminForm').on('submit', function (e) {
        e.preventDefault();
        const password = $('#passwordField').val();
        const confirm  = $('#confirmPasswordField').val();
        const confirmField = document.getElementById('confirmPasswordField');
        if (password !== confirm) {
            confirmField.classList.add('is-invalid');
            return;
        }
        confirmField.classList.remove('is-invalid');

        const btn = $('#saveAdminBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({
            url: 'models/api/admin_api.php?action=save',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Created!', res.message, 'success');
                    $('#addAdminModal').modal('hide');
                    $('#addAdminForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Create Admin'); }
        });
    });

    // Edit Admin form submission
    $('#editAdminForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#updateAdminBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({
            url: 'models/api/admin_api.php?action=update',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Updated!', res.message, 'success');
                    $('#editAdminModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Update Admin'); }
        });
    });
});

function openEditAdmin(id, name, email, branchId, status) {
    document.getElementById('edit_a_id').value        = id;
    document.getElementById('edit_a_name').value      = name;
    document.getElementById('edit_a_email').value     = email;
    document.getElementById('edit_a_branch_id').value = branchId;
    document.getElementById('edit_a_status').value    = status;
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
}

function deleteAdmin(id, name) {
    Swal.fire({
        title: 'Delete Admin?',
        text: `Remove "${name}" as Branch Admin? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('models/api/admin_api.php?action=delete', { id }, function (res) {
                if (res.status === 'success') {
                    Swal.fire('Deleted!', res.message, 'success');
                    $('#adminTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(() => Swal.fire('Error', 'Server connection failed.', 'error'));
        }
    });
}
</script>
</body>
</html>
