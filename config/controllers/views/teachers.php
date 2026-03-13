<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
    $branchName = '';
} else {
    $branches = [];
    $branchName = '';
    if ($sessionBranch) {
        $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $bStmt->execute([$sessionBranch]);
        $branchName = $bStmt->fetchColumn() ?: '';
    }
}

// Courses for specialization dropdown: Super Admin sees all; Branch Admin sees own branch
if ($isSuperAdmin) {
    $courses = $db->query("SELECT id, name FROM courses ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cStmt = $db->prepare("SELECT id, name FROM courses WHERE branch_id = ? ORDER BY name ASC");
    $cStmt->execute([$sessionBranch]);
    $courses = $cStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Stats (scoped to branch for non-Super Admin) ───────────────────────────
if ($isSuperAdmin) {
    $stats = $db->query(
        "SELECT
            COUNT(*)                         AS total,
            SUM(t.status = 'Active')         AS active,
            SUM(t.status = 'Inactive')       AS inactive,
            COUNT(DISTINCT t.branch_id)      AS branches_covered,
            COUNT(DISTINCT t.specialization) AS specializations
         FROM teachers t"
    )->fetch(PDO::FETCH_ASSOC);

    $topSpec = $db->query(
        "SELECT specialization, COUNT(*) AS cnt
         FROM teachers
         GROUP BY specialization
         ORDER BY cnt DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
} else {
    $statsStmt = $db->prepare(
        "SELECT
            COUNT(*)                         AS total,
            SUM(t.status = 'Active')         AS active,
            SUM(t.status = 'Inactive')       AS inactive,
            COUNT(DISTINCT t.branch_id)      AS branches_covered,
            COUNT(DISTINCT t.specialization) AS specializations
         FROM teachers t
         WHERE t.branch_id = ?"
    );
    $statsStmt->execute([$sessionBranch]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $tsStmt = $db->prepare(
        "SELECT specialization, COUNT(*) AS cnt
         FROM teachers
         WHERE branch_id = ?
         GROUP BY specialization
         ORDER BY cnt DESC
         LIMIT 1"
    );
    $tsStmt->execute([$sessionBranch]);
    $topSpec = $tsStmt->fetch(PDO::FETCH_ASSOC);
}
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Instructors';
$activePage = 'teachers.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Instructor Directory</h2>
                <div class="text-muted mt-1" style="font-size: 0.95rem;">Manage teacher profiles, specializations, and access.</div>
                <?php if (!$isSuperAdmin && $branchName): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary mt-2 border border-primary border-opacity-25 px-2 py-1"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary px-4 py-2 shadow-sm" style="border-radius: 12px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-person-plus-fill me-2"></i> Add Instructor
            </button>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card-hover h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-people-fill fs-4 text-primary"></i>
                            </div>
                        </div>
                        <div class="fs-2 fw-bold text-dark mb-1" style="letter-spacing: -0.03em;"><?= (int)$stats['total'] ?></div>
                        <div class="text-muted fw-medium" style="font-size: 0.85rem; letter-spacing: 0.02em;">Total Instructors</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-hover h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-person-check-fill fs-4 text-success"></i>
                            </div>
                        </div>
                        <div class="fs-2 fw-bold text-dark mb-1" style="letter-spacing: -0.03em;"><?= (int)$stats['active'] ?></div>
                        <div class="text-muted fw-medium" style="font-size: 0.85rem; letter-spacing: 0.02em;">Active Records</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-hover h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-person-x-fill fs-4 text-danger"></i>
                            </div>
                        </div>
                        <div class="fs-2 fw-bold text-dark mb-1" style="letter-spacing: -0.03em;"><?= (int)$stats['inactive'] ?></div>
                        <div class="text-muted fw-medium" style="font-size: 0.85rem; letter-spacing: 0.02em;">Inactive</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-hover h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-buildings-fill fs-4 text-warning"></i>
                            </div>
                        </div>
                        <div class="fs-2 fw-bold text-dark mb-1" style="letter-spacing: -0.03em;"><?= (int)$stats['branches_covered'] ?></div>
                        <div class="text-muted fw-medium" style="font-size: 0.85rem; letter-spacing: 0.02em;">Branches Covered</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Specialization Banner -->
        <?php if ($topSpec): ?>
        <div class="alert alert-primary d-flex align-items-center gap-3 py-3 mb-4 rounded-3 border-0 bg-primary bg-opacity-10 text-primary" role="alert" style="box-shadow: inset 0 0 0 1px rgba(0,158,247,0.2);">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px;">
                <i class="bi bi-award-fill"></i>
            </div>
            <div>
                <strong>Most-Registered Specialization:</strong>
                <span class="ms-1"><?= htmlspecialchars($topSpec['specialization']) ?></span>
                <span class="badge bg-primary ms-2 rounded-pill px-3 py-1"><?= (int)$topSpec['cnt'] ?> instructor<?= $topSpec['cnt'] > 1 ? 's' : '' ?></span>
            </div>
            <span class="ms-auto small fw-medium opacity-75 d-none d-sm-inline"><?= (int)$stats['specializations'] ?> skill area<?= $stats['specializations'] > 1 ? 's' : '' ?> covered</span>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 py-3 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.01em;"><i class="bi bi-list-ul me-2 text-muted"></i>Registered Instructors</h6>
                <?php if ($isSuperAdmin): ?>
                <div style="min-width:200px;">
                    <select id="branchFilter" class="form-select form-select-sm shadow-none border-secondary bg-light">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0 p-md-3 pt-md-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="teacherTable">
                        <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                            <tr>
                                <th class="ps-3 fw-semibold">Full Name</th>
                                <th class="fw-semibold">Email</th>
                                <th class="fw-semibold">Phone</th>
                                <th class="fw-semibold">Specialization</th>
                                <th class="fw-semibold">Branch</th>
                                <th class="fw-semibold">Status</th>
                                <th class="pe-3 fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="teacherForm" class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Register Instructor</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Jane Smith" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. jane@sbvs.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 0770000000" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Vocational Specialization <span class="text-danger">*</span></label>
                        <select name="specialization" class="form-select" required>
                            <option value="">Select Course / Skill Area...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                            <?php if (empty($courses)): ?>
                                <option disabled>No courses registered yet</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Assigned Branch <span class="text-danger">*</span></label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Branch...</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveTeacherBtn">
                    <i class="bi bi-save me-1"></i> Save Instructor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="editTeacherForm" class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Instructor</h5>
                <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Specialization</label>
                        <select name="specialization" id="edit_specialization" class="form-select">
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                            <?php if (empty($courses)): ?>
                                <option disabled>No courses registered yet</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Branch</label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" id="edit_branch_id" class="form-select">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="updateTeacherBtn">
                    <i class="bi bi-save me-1"></i> Update Instructor
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$(document).ready(function () {
    const userRole = <?= json_encode($role) ?>;
    const canDelete = <?= json_encode($isSuperAdmin || $isBranchAdmin) ?>;
    const canEdit   = <?= json_encode($isSuperAdmin || $isBranchAdmin || $isAdmin) ?>;

    const table = $('#teacherTable').DataTable({
        processing: true,
        ajax: {
            url: '../views/models/api/teacher_api.php?action=list',
            data: function (d) { d.branch_id = $('#branchFilter').val(); }
        },
        columns: [
            { data: 'name', className: 'fw-bold text-dark ps-3 align-middle' },
            { data: 'email', className: 'text-muted align-middle' },
            { data: 'phone', className: 'text-muted align-middle' },
            { data: 'specialization', className: 'text-dark fw-medium align-middle' },
            { 
                data: 'branch_name', className: 'align-middle',
                render: function (data) {
                    return '<span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-building me-1 text-muted"></i>' + data + '</span>';
                }
            },
            {
                data: 'status', className: 'align-middle',
                render: function (data) {
                    return '<span class="badge-custom badge-' + (data === 'Active' ? 'success' : 'danger') + '">' + data + '</span>';
                }
            },
            {
                data: null, orderable: false, className: 'text-end pe-3 align-middle',
                render: function (data) {
                    let btns = '<div class="d-flex justify-content-end gap-2">';
                    btns += '<a class="btn-action btn-view" title="View Profile" href="teacher_profile.php?id=' + data.id + '"><i class="bi bi-person-vcard"></i></a>';
                    btns += '<a class="btn-action btn-secondary" style="background:#f1f5f9; color:#475569; border: 1px solid #e2e8f0;" title="Print ID Card" href="generate_id.php?type=teacher&id=' + data.id + '" target="_blank"><i class="bi bi-person-badge"></i></a>';
                    if (canEdit) {
                        btns += '<button type="button" class="btn-action btn-edit border-0" title="Edit" onclick="openEdit(' + data.id + ')"><i class="bi bi-pencil-fill"></i></button>';
                    }
                    if (canDelete) {
                        btns += '<button type="button" class="btn-action btn-delete border-0" title="Delete" onclick="deleteTeacher(' + data.id + ')"><i class="bi bi-trash3-fill"></i></button>';
                    }
                    btns += '</div>';
                    return btns;
                }
            }
        ],
        responsive: true,
        language: { emptyTable: "No instructors registered yet." }
    });

    $('#branchFilter').on('change', function () { table.ajax.reload(); });

    $('#teacherForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#saveTeacherBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({
            url: '../views/models/api/teacher_api.php?action=create',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Registered!', 'Instructor has been added.', 'success');
                    $('#addTeacherModal').modal('hide');
                    $('#teacherForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Instructor'); }
        });
    });

    $('#editTeacherForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#updateTeacherBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({
            url: '../views/models/api/teacher_api.php?action=update',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Updated!', 'Instructor record updated.', 'success');
                    $('#editTeacherModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Update Instructor'); }
        });
    });
});

function openEdit(id) {
    $.getJSON('../views/models/api/teacher_api.php?action=get&id=' + id, function (res) {
        if (res.status !== 'success') { Swal.fire('Error', res.message, 'error'); return; }
        const d = res.data;
        document.getElementById('edit_id').value = d.id;
        document.getElementById('edit_name').value = d.name;
        document.getElementById('edit_email').value = d.email;
        document.getElementById('edit_phone').value = d.phone;
        document.getElementById('edit_status').value = d.status;
        document.getElementById('edit_specialization').value = d.specialization;
        const branchEl = document.getElementById('edit_branch_id');
        if (branchEl) branchEl.value = d.branch_id;
        new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
    });
}

function deleteTeacher(id) {
    Swal.fire({
        title: 'Delete Instructor?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Delete'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.get('../views/models/api/teacher_api.php?action=delete&id=' + id, function (res) {
                if (res.status === 'success') {
                    Swal.fire('Deleted!', 'Instructor removed.', 'success');
                    $('#teacherTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>
</body>
</html>
