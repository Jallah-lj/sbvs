<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isAllowed     = in_array($role, ['Super Admin', 'Branch Admin', 'Admin'], true);

if (!$isAllowed) {
    header("Location: dashboard.php");
    exit;
}
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Register Student';
$activePage = 'student_registration.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 mt-2 gap-3 fade-in">
            <div>
                <h3 class="fw-800 mb-0" style="letter-spacing: -0.03em;">Student Registration</h3>
                <p class="text-muted small mb-0 mt-1">Register student, enroll in course, and record first payment in one process.</p>
            </div>
            <a href="students.php" class="btn btn-light d-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600; border: 1px solid #e2e8f0;">
                <i class="bi bi-arrow-left"></i> Back to Students
            </a>
        </div>

        <form id="registrationForm" class="card shadow-sm border-0 fade-in" style="animation-delay: 0.1s;" novalidate>
            <div class="card-body p-4 p-lg-5">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="section-title"><i class="bi bi-person-lines-fill"></i> Student Information</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Student ID</label>
                        <input type="text" class="form-control bg-light" id="student_id" name="student_id" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                        <select id="branch_id" name="branch_id" class="form-select" required></select>
                    </div>

                    <div class="col-12"><hr></div>
                    <div class="col-12">
                        <div class="section-title"><i class="bi bi-book"></i> Course Enrollment</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Select Course <span class="text-danger">*</span></label>
                        <select id="course_id" name="course_id" class="form-select" required>
                            <option value="">Select branch first</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Course Duration</label>
                        <input type="text" id="course_duration" class="form-control bg-light" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Course Fee</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="text" id="course_fee" class="form-control bg-light border-start-0" readonly>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Enrollment Date <span class="text-danger">*</span></label>
                        <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-12"><hr></div>
                    <div class="col-12">
                        <div class="section-title text-success"><i class="bi bi-cash-stack"></i> Initial Payment Section</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Total Course Fee</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="number" id="total_fee" name="total_fee_display" class="form-control bg-light border-start-0" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Amount Paid</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-success fw-bold">$</span>
                            <input type="number" id="amount_paid" name="amount_paid" class="form-control border-start-0 fw-bold text-success" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Balance Remaining</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="number" id="balance" name="balance_display" class="form-control bg-light border-start-0" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Mobile Money - Orange">Mobile Money - Orange</option>
                            <option value="Mobile Money - MTN">Mobile Money - MTN</option>
                            <option value="Check">Check</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <div class="alert alert-success summary-box mb-0 p-3 h-100 d-flex flex-column justify-content-center">
                            <div class="fw-semibold mb-1 text-success d-flex align-items-center gap-2"><i class="bi bi-calculator"></i> Payment Summary</div>
                            <small class="text-muted d-block opacity-75">Formula: <strong>balance = total_fee - amount_paid</strong></small>
                            <div id="summaryLine" class="mt-2 fs-5 fw-bold text-success">$0.00 - $0.00 = $0.00</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light border-opacity-10 py-3 px-4 d-flex justify-content-end gap-3 rounded-bottom-4">
                <button type="reset" class="btn btn-light border px-4" id="resetBtn" style="border-radius:10px; font-weight: 500;">Reset Form</button>
                <button type="submit" class="btn btn-primary px-4 shadow-sm" id="submitBtn" style="border-radius:10px; font-weight: 600;">
                    <i class="bi bi-save me-2"></i> Register Student
                </button>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API = 'models/api/student_registration_api.php';
const IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const SESSION_BRANCH = <?= (int)$sessionBranch ?>;

function money(v){ return parseFloat(v || 0).toFixed(2); }

function recalcBalance(){
    const total = parseFloat($('#total_fee').val() || 0);
    let paid = parseFloat($('#amount_paid').val() || 0);
    if (paid < 0) paid = 0;
    if (paid > total) paid = total;
    $('#amount_paid').val(money(paid));
    const bal = Math.max(0, total - paid);
    $('#balance').val(money(bal));
    $('#summaryLine').text(`$${money(total)} - $${money(paid)} = $${money(bal)}`);
}

function loadBootstrap(){
    $.getJSON(API, {action:'bootstrap'}, function(res){
        if (!res.success) return;
        const d = res.data;
        $('#student_id').val(d.student_id || '');

        let bOpts = '<option value="">Select Branch...</option>';
        (d.branches || []).forEach(b => bOpts += `<option value="${b.id}">${b.name}</option>`);
        $('#branch_id').html(bOpts);

        if (!IS_SUPER_ADMIN && SESSION_BRANCH) {
            $('#branch_id').val(SESSION_BRANCH).prop('disabled', true);
            loadCourses(SESSION_BRANCH);
        }
    });
}

function loadCourses(branchId){
    $('#course_id').html('<option value="">Loading courses...</option>');
    $.getJSON(API, {action:'courses', branch_id: branchId}, function(res){
        if (!res.success) {
            $('#course_id').html('<option value="">No courses</option>');
            return;
        }
        let opts = '<option value="">Select Course...</option>';
        res.data.forEach(c => {
            opts += `<option value="${c.id}" data-duration="${c.duration}" data-fee="${c.fee}">${c.course_name}</option>`;
        });
        $('#course_id').html(opts);
        $('#course_duration').val('');
        $('#course_fee').val('');
        $('#total_fee').val('0.00');
        recalcBalance();
    });
}

$('#branch_id').on('change', function(){
    const branchId = $(this).val();
    if (branchId) loadCourses(branchId);
});

$('#course_id').on('change', function(){
    const sel = $(this).find(':selected');
    const courseId = $(this).val();
    const duration = sel.data('duration') || '';
    const fee = parseFloat(sel.data('fee') || 0);

    $('#course_duration').val(duration);
    $('#course_fee').val(money(fee));
    $('#total_fee').val(money(fee));
    recalcBalance();

});

$('#amount_paid').on('input', recalcBalance);

$('#resetBtn').on('click', function(){
    setTimeout(() => {
        $('#course_duration').val('');
        $('#course_fee').val('');
        $('#total_fee').val('0.00');
        $('#balance').val('0.00');
        $('#summaryLine').text('$0.00 - $0.00 = $0.00');
        if (!IS_SUPER_ADMIN && SESSION_BRANCH) loadCourses(SESSION_BRANCH);
        loadBootstrap();
    }, 50);
});

$('#registrationForm').on('submit', function(e){
    e.preventDefault();

    const form = this;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    if (parseFloat($('#amount_paid').val() || 0) > parseFloat($('#total_fee').val() || 0)) {
        Swal.fire('Invalid Payment', 'Amount paid cannot exceed total fee.', 'error');
        return;
    }

    const btn = $('#submitBtn');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    const data = $(form).serialize() + '&action=register' + (IS_SUPER_ADMIN ? '' : `&branch_id=${SESSION_BRANCH}`);
    $.post(API, data, function(res){
        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Register Student');

        if (!res.success) {
            Swal.fire('Registration Failed', res.message || 'Please check data and try again.', 'error');
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Registration Successful',
            html: `Student <strong>${res.data.student_code}</strong> has been registered.<br>Balance: <strong>$${money(res.data.balance)}</strong>`,
            confirmButtonText: 'View Receipt'
        }).then(() => {
            const pid = res.data.payment_id || 0;
            window.location.href = `student_registration_receipt.php?student_id=${res.data.student_id}&enrollment_id=${res.data.enrollment_id}&payment_id=${pid}`;
        });
    }, 'json').fail(function(){
        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Register Student');
        Swal.fire('Error', 'Could not complete request.', 'error');
    });
});

loadBootstrap();
recalcBalance();
</script>
</body>
</html>
