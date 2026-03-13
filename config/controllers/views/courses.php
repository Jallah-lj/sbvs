<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

// Load branches (Super Admin sees all)
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
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Courses';
$activePage = 'courses.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><i class="bi bi-book text-primary me-2"></i>Vocational Courses</h2>
                <p class="text-muted small mb-0 mt-1">Manage and organize all academic programs.</p>
                <?php if (!$isSuperAdmin && $branchName): ?>
                <span class="badge bg-info mt-2 px-2 py-1"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isSuperAdmin || $isBranchAdmin || $isAdmin): ?>
            <button class="btn btn-primary shadow-sm rounded-pill px-4 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="bi bi-plus-circle-fill me-2"></i> Add New Course
            </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-ul me-1"></i>Available Programs</h6>
                <?php if ($isSuperAdmin): ?>
                <div style="min-width:180px;">
                    <select id="branchFilter" class="form-select form-select-sm">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="courseTable">
                        <thead>
                            <tr>
                            <th>Course Name</th>
                                <th>Duration</th>
                                <th>Fees (USD)</th>
                                <th>Branch</th>
                                <th>Description</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="courseForm" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header modal-header-accent border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-book-fill me-2 opacity-75"></i>Add New Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative pt-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Course Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Advanced Tailoring" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Duration <span class="text-danger">*</span></label>
                        <select name="duration" class="form-select" required>
                            <option value="3 Months">3 Months</option>
                            <option value="6 Months">6 Months</option>
                            <option value="1 Year">1 Year</option>
                            <option value="2 Years">2 Years</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Registration Fee (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-check"></i></span>
                            <input type="number" step="0.01" min="0" name="registration_fee" class="form-control fee-input" placeholder="0.00" value="0" required>
                        </div>
                        <div class="form-text">One-time enrolment fee</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tuition Fee (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                            <input type="number" step="0.01" min="0" name="tuition_fee" class="form-control fee-input" placeholder="0.00" required>
                        </div>
                        <div class="form-text">Course tuition amount</div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 mb-0 d-flex justify-content-between align-items-center" style="border-radius:10px;">
                            <span class="small fw-semibold"><i class="bi bi-calculator me-1"></i>Total Fee:</span>
                            <span class="fw-bold text-primary" id="addTotalFeePreview">$0.00</span>
                        </div>
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
                    <div class="col-12">
                        <label class="form-label fw-bold">Course Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the course..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveCourseBtn">
                    <i class="bi bi-save me-1"></i> Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="editCourseForm" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header modal-header-warning border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 opacity-75"></i>Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative pt-4">
                <input type="hidden" name="id" id="edit_c_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Course Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_c_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Duration <span class="text-danger">*</span></label>
                        <select name="duration" id="edit_c_duration" class="form-select" required>
                            <option value="3 Months">3 Months</option>
                            <option value="6 Months">6 Months</option>
                            <option value="1 Year">1 Year</option>
                            <option value="2 Years">2 Years</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Registration Fee (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-check"></i></span>
                            <input type="number" step="0.01" min="0" name="registration_fee" id="edit_c_reg_fee" class="form-control fee-input" placeholder="0.00" required>
                        </div>
                        <div class="form-text">One-time enrolment fee</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tuition Fee (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                            <input type="number" step="0.01" min="0" name="tuition_fee" id="edit_c_fees" class="form-control fee-input" placeholder="0.00" required>
                        </div>
                        <div class="form-text">Course tuition amount</div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 mb-0 d-flex justify-content-between align-items-center" style="border-radius:10px;">
                            <span class="small fw-semibold"><i class="bi bi-calculator me-1"></i>Total Fee:</span>
                            <span class="fw-bold text-primary" id="editTotalFeePreview">$0.00</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Assigned Branch</label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" id="edit_c_branch_id" class="form-select" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Course Description</label>
                        <textarea name="description" id="edit_c_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="updateCourseBtn">
                    <i class="bi bi-save me-1"></i> Update Course
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$(document).ready(function () {
    const canEdit   = <?= json_encode($isSuperAdmin || $isBranchAdmin || $isAdmin) ?>;
    const canDelete = <?= json_encode($isSuperAdmin || $isBranchAdmin) ?>;

    const table = $('#courseTable').DataTable({
        processing: true,
        ajax: {
            url: '../views/models/api/course_api.php?action=list',
            data: function (d) { d.branch_id = $('#branchFilter').val() || ''; }
        },
        columns: [
            { 
                data: 'name', 
                className: 'fw-bold text-dark pe-0',
                render: function(data) {
                    return `<div class="d-flex align-items-center"><div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;"><i class="bi bi-book text-primary fs-5"></i></div><span class="fs-6">${data}</span></div>`;
                }
            },
            { 
                data: 'duration',
                render: function(data) {
                    return `<span class="badge badge-custom badge-info"><i class="bi bi-clock me-1"></i>${data}</span>`;
                }
            },
            {
                data: null,
                render: function(row) {
                    const reg  = parseFloat(row.registration_fee || 0);
                    const tut  = parseFloat(row.tuition_fee || 0);
                    const tot  = parseFloat(row.fees || 0);
                    const fmt  = v => '$' + v.toFixed(2);
                    return `<div class="d-flex flex-column" style="min-width:140px;">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted"><i class="bi bi-person-check me-1"></i>Reg:</span>
                            <span>${fmt(reg)}</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted"><i class="bi bi-mortarboard me-1"></i>Tuition:</span>
                            <span>${fmt(tut)}</span>
                        </div>
                        <div class="d-flex justify-content-between border-top mt-1 pt-1">
                            <span class="fw-bold small">Total:</span>
                            <span class="fw-bold text-success">${fmt(tot)}</span>
                        </div>
                    </div>`;
                }
            },
            { data: 'branch_name' },
            {
                data: 'description',
                render: function (data) {
                    return data ? '<span class="text-muted" title="' + $('<div>').text(data).html() + '">' + (data.length > 50 ? data.substring(0, 50) + '…' : data) + '</span>' : '<em class="text-muted small">—</em>';
                }
            },
            {
                data: null, orderable: false, className: 'text-end text-center pe-4',
                render: function (data) {
                    let btns = '<div class="d-flex gap-2 justify-content-end">';
                    if (canEdit) {
                        btns += `<button class="btn btn-action btn-edit" title="Edit" onclick="editCourse(${data.id})"><i class="bi bi-pencil-fill"></i></button>`;
                    }
                    if (canDelete) {
                        btns += `<button class="btn btn-action btn-delete" title="Delete" onclick="deleteCourse(${data.id}, '${$('<div>').text(data.name).html()}')"><i class="bi bi-trash-fill"></i></button>`;
                    }
                    if (!canEdit && !canDelete) {
                        btns += '<span class="badge bg-light text-muted">View only</span>';
                    }
                    btns += '</div>';
                    return btns;
                }
            }
        ],
        responsive: true,
        language: { emptyTable: "No courses registered yet." }
    });

    $('#branchFilter').on('change', function () { table.ajax.reload(); });

    // Add Course
    $('#courseForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#saveCourseBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({
            url: '../views/models/api/course_api.php?action=save',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Saved!', res.message, 'success');
                    $('#addCourseModal').modal('hide');
                    $('#courseForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Course'); }
        });
    });

    // Edit Course
    $('#editCourseForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#updateCourseBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({
            url: '../views/models/api/course_api.php?action=update',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Updated!', res.message, 'success');
                    $('#editCourseModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Server connection failed.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Update Course'); }
        });
    });
});

function editCourse(id) {
    $.getJSON('../views/models/api/course_api.php?action=get&id=' + id, function (res) {
        if (!res || res.status === 'error') { Swal.fire('Error', (res && res.message) || 'Course not found.', 'error'); return; }
        document.getElementById('edit_c_id').value          = res.id;
        document.getElementById('edit_c_name').value        = res.name;
        document.getElementById('edit_c_duration').value    = res.duration;
        document.getElementById('edit_c_reg_fee').value     = parseFloat(res.registration_fee || 0).toFixed(2);
        document.getElementById('edit_c_fees').value        = parseFloat(res.tuition_fee || 0).toFixed(2);
        document.getElementById('edit_c_description').value = res.description || '';
        const branchEl = document.getElementById('edit_c_branch_id');
        if (branchEl) branchEl.value = res.branch_id;
        // Update total preview
        const tot = parseFloat(res.registration_fee||0) + parseFloat(res.tuition_fee||0);
        document.getElementById('editTotalFeePreview').textContent = '$' + tot.toFixed(2);
        new bootstrap.Modal(document.getElementById('editCourseModal')).show();
    });
}

// Live total fee preview for both modals
document.querySelectorAll('#addCourseModal .fee-input').forEach(el => {
    el.addEventListener('input', function() {
        const inputs = document.querySelectorAll('#addCourseModal .fee-input');
        const tot = Array.from(inputs).reduce((s,i) => s + parseFloat(i.value||0), 0);
        document.getElementById('addTotalFeePreview').textContent = '$' + tot.toFixed(2);
    });
});
document.querySelectorAll('#editCourseModal .fee-input').forEach(el => {
    el.addEventListener('input', function() {
        const inputs = document.querySelectorAll('#editCourseModal .fee-input');
        const tot = Array.from(inputs).reduce((s,i) => s + parseFloat(i.value||0), 0);
        document.getElementById('editTotalFeePreview').textContent = '$' + tot.toFixed(2);
    });
});

function deleteCourse(id, name) {
    Swal.fire({
        title: 'Delete Course?',
        html: 'This will permanently delete <strong>' + name + '</strong>.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Delete'
    }).then(function (result) {
        if (result.isConfirmed) {
            $.getJSON('../views/models/api/course_api.php?action=delete&id=' + id, function (res) {
                if (res.status === 'success') {
                    Swal.fire('Deleted!', res.message, 'success');
                    $('#courseTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}
</script>
</body>
</html>
