<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');
$isTeacher     = ($role === 'Teacher');
$canManageStudents = ($isSuperAdmin || $isBranchAdmin || $isAdmin);

$teacherSpecialization = '';
if ($isTeacher) {
    $tStmt = $db->prepare("SELECT specialization FROM teachers WHERE user_id = ? LIMIT 1");
    $tStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $teacherSpecialization = (string)($tStmt->fetchColumn() ?: '');
}

// Super Admin sees all branches in filters; Branch Admin sees only their own branch
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
$pageTitle  = 'Students';
$activePage = 'students.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 mt-2 gap-3 fade-in">
            <div>
                <h3 class="fw-800 mb-0" style="letter-spacing: -0.03em;">Student Directory</h3>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <p class="text-muted small mb-0"><?= $isTeacher ? 'Track learners in your specialization and review profiles' : 'Manage student records, enrollments, and profiles' ?></p>
                    <?php if (!$isSuperAdmin && $branchName): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.65rem;"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                    <?php endif; ?>
                    <?php if ($isTeacher && $teacherSpecialization !== ''): ?>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 0.65rem;"><i class="bi bi-journal-bookmark me-1"></i><?= htmlspecialchars($teacherSpecialization) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($canManageStudents): ?>
            <div class="d-flex gap-2">
                <a href="student_registration.php" class="btn btn-light d-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600; border: 1px solid #e2e8f0;">
                    <i class="bi bi-person-vcard"></i> Full Registration
                </a>
                <button class="btn btn-primary d-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-person-plus-fill"></i> Quick Add
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h6 class="mb-0 fw-bold" style="color:var(--text);"><i class="bi bi-list-ul text-primary me-2"></i>Enrolled Students</h6>
                <?php if ($isSuperAdmin): ?>
                <div style="min-width:200px;">
                    <select id="branchFilter" class="form-select text-secondary fw-semibold">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-container shadow-none border-top">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 w-100" id="studentTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Gender</th>
                                    <th>Branch</th>
                                    <th>Phone</th>
                                    <th>Reg. Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if ($canManageStudents): ?>
<!-- Register Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="studentForm" class="modal-content border-0" enctype="multipart/form-data">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>New Student Registration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Photo Upload -->
                <div class="text-center mb-4">
                    <img id="photoPreview" src="https://via.placeholder.com/100?text=Photo" class="rounded-circle mb-2">
                    <div>
                        <input type="file" name="photo" id="photoInput" class="form-control form-control-sm w-auto d-inline-block" accept="image/*">
                        <small class="text-muted d-block mt-1">Upload Student Photo (Optional)</small>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. john@email.com" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 0770000000">
                    </div>
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Home Address</label>
                        <input type="text" name="address" class="form-control" placeholder="Street, Community, City">
                    </div>
                </div>

                <!-- ── Enrollment (Optional) ───────────────────────────────── -->
                <hr class="my-3">
                <div class="d-flex align-items-center mb-3 gap-2">
                    <h6 class="text-primary mb-0"><i class="bi bi-collection me-1"></i>Enrollment</h6>
                    <span class="badge bg-secondary">Optional</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Course</label>
                        <select id="enroll_course" class="form-select">
                            <option value="">-- Select Course --</option>
                        </select>
                        <div id="courseFeeInfo" class="form-text" style="display:none;">
                            Course fee: <strong id="courseFeeAmount">$0.00</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Enrollment Date</label>
                        <input type="date" id="enroll_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Enrollment Status</label>
                        <select id="enroll_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>

                <!-- ── Initial Payment (Optional) ─────────────────────────── -->
                <div id="quickPaymentSection" style="display:none;" class="mt-2">
                    <hr class="my-3">
                    <div class="d-flex align-items-center mb-3 gap-2">
                        <h6 class="text-success mb-0"><i class="bi bi-receipt me-1"></i>Initial Payment</h6>
                        <span class="badge bg-secondary">Optional</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount ($)</label>
                            <input type="number" id="pay_amount" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                            <div class="form-text">Balance: <strong id="payBalanceInfo">--</strong></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Method</label>
                            <select id="pay_method" class="form-select">
                                <option value="">-- Select Method --</option>
                                <option value="Cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Date</label>
                            <input type="date" id="pay_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Transaction ID</label>
                            <input type="text" id="pay_trans_id" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Notes</label>
                            <input type="text" id="pay_notes" class="form-control" placeholder="Optional notes...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveStudentBtn">
                    <i class="bi bi-save me-1"></i> Save Student
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageStudents): ?>
<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="editStudentForm" class="modal-content border-0" enctype="multipart/form-data">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_s_id">
                <!-- Photo Upload -->
                <div class="text-center mb-4">
                    <img id="editPhotoPreview" src="https://via.placeholder.com/100?text=Photo" class="rounded-circle mb-2" style="width:100px;height:100px;object-fit:cover;border:3px solid #dee2e6;">
                    <div>
                        <input type="file" name="photo" id="editPhotoInput" class="form-control form-control-sm w-auto d-inline-block" accept="image/*">
                        <small class="text-muted d-block mt-1">Update Photo (Optional – leave blank to keep current)</small>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_s_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_s_email" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" id="edit_s_gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Date of Birth</label>
                        <input type="date" name="dob" id="edit_s_dob" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="phone" id="edit_s_phone" class="form-control" placeholder="e.g. 0770000000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Assigned Branch <span class="text-danger">*</span></label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" id="edit_s_branch_id" class="form-select" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Home Address</label>
                        <input type="text" name="address" id="edit_s_address" class="form-control" placeholder="Street, Community, City">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="updateStudentBtn">
                    <i class="bi bi-save me-1"></i> Update Student
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const IS_SA_OR_BA = <?= ($isSuperAdmin || $isBranchAdmin) ? 'true' : 'false' ?>;
const CAN_EDIT    = <?= $canManageStudents ? 'true' : 'false' ?>;
const CAN_REGISTER = <?= $canManageStudents ? 'true' : 'false' ?>;
const APP_BASE_URL = <?= json_encode(rtrim(defined('BASE_URL') ? BASE_URL : '/sbvs/sbvs/', '/') . '/') ?>;

function toPhotoUrl(path) {
    const p = String(path || '').trim();
    if (!p) return 'https://via.placeholder.com/100?text=Photo';
    if (/^(https?:)?\/\//i.test(p) || p.startsWith('/')) return p;
    return APP_BASE_URL + p.replace(/^\/+/, '');
}

$(document).ready(function () {

    // Initialize DataTable
    const table = $('#studentTable').DataTable({
        processing: true,
        ajax: {
            url: 'models/api/student_api.php?action=list',
            data: function (d) { d.branch_id = $('#branchFilter').val(); }
        },
        columns: [
            { data: 'student_id', className: 'fw-bold text-primary align-middle' },
            { data: 'name', className: 'align-middle fw-semibold text-dark' },
            { data: 'gender', className: 'align-middle text-muted small' },
            { 
                data: 'branch_name', className: 'align-middle',
                render: function(data) { return `<span class="badge-custom badge-branch"><i class="bi bi-building me-1"></i>${data}</span>`; }
            },
            { data: 'phone', className: 'align-middle text-muted' },
            { data: 'registration_date', className: 'align-middle text-muted small' },
            {
                data: null, orderable: false, className: 'align-middle text-center',
                render: function (data) {
                    const editBtn = CAN_EDIT
                        ? `<button class="btn-action btn-edit" title="Edit" onclick="openEditStudent(${data.id})"><i class="bi bi-pencil-fill"></i></button>`
                        : '';
                    const delBtn = IS_SA_OR_BA
                        ? `<button class="btn-action btn-delete" title="Delete" onclick="deleteStudent(${data.id}, '${(data.name||'').replace(/'/g,"\\'")}')"><i class="bi bi-trash-fill"></i></button>`
                        : '';
                    const idBtn = `<a class="btn-action btn-secondary" style="background:#f1f5f9; color:#475569; border: 1px solid #e2e8f0;" title="Print ID Card" href="generate_id.php?type=student&id=${data.id}" target="_blank"><i class="bi bi-person-badge"></i></a>`;
                    return `<button class="btn-action btn-view" title="View Profile" onclick="viewProfile(${data.id})"><i class="bi bi-eye-fill"></i></button>` + idBtn + editBtn + delBtn;
                }
            }
        ],
        responsive: true,
        language: { emptyTable: "No students registered yet." }
    });

    // Branch filter
    $('#branchFilter').on('change', function () { table.ajax.reload(); });

    // Photo preview
    if (CAN_REGISTER) {
        $('#photoInput').on('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => $('#photoPreview').attr('src', e.target.result);
                reader.readAsDataURL(file);
            }
        });

        $('#editPhotoInput').on('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => $('#editPhotoPreview').attr('src', e.target.result);
                reader.readAsDataURL(file);
            }
        });
    }

    // ── Quick-Add enrollment & payment helpers ─────────────────────────────
    function loadQuickAddCourses(branchId) {
        const $c = $('#enroll_course');
        $c.html('<option value="">Loading...</option>').prop('disabled', true);
        $('#quickPaymentSection').hide();
        $('#courseFeeInfo').hide();
        if (!branchId) {
            $c.html('<option value="">-- Select Course --</option>').prop('disabled', false);
            return;
        }
        $.getJSON('../views/models/api/batch_api.php?action=courses_by_branch&branch_id=' + branchId, function(res) {
            if (res.success && res.data.length) {
                let opts = '<option value="">-- Select Course --</option>';
                res.data.forEach(c => {
                    opts += `<option value="${c.id}" data-fee="${c.fees}">${c.name} — $${parseFloat(c.fees).toFixed(2)}</option>`;
                });
                $c.html(opts).prop('disabled', false);
            } else {
                $c.html('<option value="">No courses for this branch</option>').prop('disabled', false);
            }
        }).fail(() => $c.html('<option value="">-- Select Course --</option>').prop('disabled', false));
    }

    function resetQuickAddModal() {
        $('#studentForm')[0].reset();
        $('#photoPreview').attr('src', 'https://via.placeholder.com/100?text=Photo');
        $('#enroll_course').val('').html('<option value="">-- Select Course --</option>').prop('disabled', false);
        $('#enroll_date').val('<?= date('Y-m-d') ?>');
        $('#enroll_status').val('Active');
        $('#pay_amount, #pay_trans_id, #pay_notes').val('');
        $('#pay_method').val('');
        $('#pay_date').val('<?= date('Y-m-d') ?>');
        $('#quickPaymentSection, #courseFeeInfo').hide();
        $('#addStudentModal').modal('hide');
    }

    // Course change → show fee + payment section
    $('#enroll_course').on('change', function() {
        const cid  = $(this).val();
        const fee  = parseFloat($(this).find(':selected').data('fee') || 0);
        if (!cid) {
            $('#quickPaymentSection').hide();
            $('#courseFeeInfo').hide();
            return;
        }
        $('#courseFeeAmount').text('$' + fee.toFixed(2));
        $('#courseFeeInfo').show();
        $('#pay_amount').val(fee.toFixed(2));
        $('#payBalanceInfo').text('$' + fee.toFixed(2));
        $('#quickPaymentSection').slideDown(200);
    });

    // SA: branch change inside Add modal → reload courses
    $('#addStudentModal').on('change', 'select[name="branch_id"]', function() {
        loadQuickAddCourses($(this).val());
    });

    // Modal open → reset enrollment fields & pre-load courses for non-SA
    $('#addStudentModal').on('show.bs.modal', function() {
        <?php if (!$isSuperAdmin && $sessionBranch): ?>
        loadQuickAddCourses(<?= $sessionBranch ?>);
        <?php else: ?>
        loadQuickAddCourses(0);
        <?php endif; ?>
        $('#enroll_date').val('<?= date('Y-m-d') ?>');
        $('#pay_date').val('<?= date('Y-m-d') ?>');
    });

    // Form submission — student → enroll (optional) → payment (optional)
    $('#studentForm').on('submit', function (e) {
        if (!CAN_REGISTER) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const enrollCourseId = $('#enroll_course').val();
        const enrollDate     = $('#enroll_date').val() || '<?= date('Y-m-d') ?>';
        const enrollStatus   = $('#enroll_status').val() || 'Active';
        const payAmount      = parseFloat($('#pay_amount').val()) || 0;
        const payMethod      = $('#pay_method').val();
        const payDate        = $('#pay_date').val() || '<?= date('Y-m-d') ?>';
        const payTransId     = $('#pay_trans_id').val();
        const payNotes       = $('#pay_notes').val();
        const doEnroll       = !!enrollCourseId;
        const doPay          = doEnroll && payAmount > 0 && !!payMethod;

        const formData = new FormData(this);
        const btn = $('#saveStudentBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');

        $.ajax({
            url: 'models/api/student_api.php?action=register',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message, 'error');
                    btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Student');
                    return;
                }
                const studentId   = res.id;
                const studentCode = res.student_id;

                if (!doEnroll) {
                    Swal.fire({ icon: 'success', title: 'Registered!', text: 'Student ID: ' + studentCode + ' created.' });
                    resetQuickAddModal();
                    table.ajax.reload();
                    return;
                }

                // Step 2: Enroll
                $.post('models/api/enrollment_api.php?action=enroll', {
                    student_id: studentId, course_id: enrollCourseId,
                    enrollment_date: enrollDate, status: enrollStatus
                }, function(eRes) {
                    if (!eRes.success) {
                        Swal.fire({ icon: 'warning', title: 'Registered — Enrollment Failed',
                            html: `Student <strong>${studentCode}</strong> created.<br><small class="text-danger">${eRes.message}</small>` });
                        resetQuickAddModal(); table.ajax.reload(); return;
                    }
                    
                    const enrollmentId = eRes.id;
                    if (!doPay) {
                        Swal.fire({ icon: 'success', title: 'Registered & Enrolled!',
                            html: `Student <strong>${studentCode}</strong> created and enrolled successfully.` });
                        resetQuickAddModal(); table.ajax.reload(); return;
                    }

                    // Step 3: Payment
                    $.post('models/api/payment_api.php?action=record', {
                        student_id: studentId, enrollment_id: enrollmentId,
                        amount: payAmount, payment_method: payMethod,
                        payment_date: payDate, transaction_id: payTransId, notes: payNotes
                    }, function(pRes) {
                        if (!pRes.success) {
                            Swal.fire({ icon: 'warning', title: 'Registered & Enrolled — Payment Failed',
                                html: `Student created and enrolled.<br><small class="text-danger">${pRes.message}</small>` });
                            resetQuickAddModal(); table.ajax.reload(); return;
                        }
                        Swal.fire({ icon: 'success', title: 'Success!',
                            html: `Student <strong>${studentCode}</strong> registered, enrolled, and initial payment recorded.` });
                        resetQuickAddModal(); table.ajax.reload();
                    }, 'json').fail(function() {
                        Swal.fire('Error', 'Payment request failed, but student was enrolled.', 'warning');
                        resetQuickAddModal(); table.ajax.reload();
                    });

                }, 'json').fail(function() {
                    Swal.fire('Error', 'Enrollment request failed, but student was created.', 'warning');
                    resetQuickAddModal(); table.ajax.reload();
                });
            }
        });
    });

    // Populate Edit Modal
    window.openEditStudent = function(id) {
        if (!CAN_EDIT) return;
        $.getJSON('../views/models/api/student_api.php?action=get&id=' + id, function(res) {
            if (res.status !== 'success') return Swal.fire('Error', res.message || 'Error fetching student', 'error');
            const s = res.data;
            $('#edit_s_id').val(s.id);
            $('#edit_s_name').val(s.name);
            $('#edit_s_email').val(s.email);
            $('#edit_s_gender').val(s.gender);
            $('#edit_s_dob').val(s.dob);
            $('#edit_s_phone').val(s.phone);
            $('#edit_s_branch_id').val(s.branch_id);
            $('#edit_s_address').val(s.address);
            $('#editPhotoPreview').attr('src', toPhotoUrl(s.photo_url));
            $('#editStudentModal').modal('show');
        });
    };

    // Update Student
    $('#editStudentForm').on('submit', function(e) {
        if (!CAN_EDIT) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const btn = $('#updateStudentBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({
            url: 'models/api/student_api.php?action=update',
            type: 'POST', data: new FormData(this), contentType: false, processData: false,
            success: function(res) {
                btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Update Student');
                if (res.status !== 'success') return Swal.fire('Error', res.message || 'Error updating student', 'error');
                $('#editStudentModal').modal('hide');
                Swal.fire('Updated!', 'Student details updated successfully.', 'success');
                table.ajax.reload(null, false);
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Update Student');
                Swal.fire('Error', 'Failed to update student.', 'error');
            }
        });
    });

    // Delete Student
    window.deleteStudent = function(id, name) {
        Swal.fire({
            title: 'Delete Student?',
            html: `Are you sure you want to delete <strong>${name}</strong>?<br>This will also remove all enrollments and payments.`,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('models/api/student_api.php?action=delete', { id: id }, function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', 'Student has been deleted.', 'success');
                        table.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json').fail(() => Swal.fire('Error', 'Failed to delete student.', 'error'));
            }
        });
    };

    // View Profile Setup
    window.viewProfile = function(id) {
        window.location.href = `student_profile.php?id=${id}`;
    };

});
</script>
</body>
</html>