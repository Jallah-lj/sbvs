<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
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

$pageTitle  = 'Register Student';
$activePage = 'student_registration.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ══════════════════════════════════════════════════════════════
   STUDENT REGISTRATION — Modern Stepped Form
   Uses: DM Sans (body) + existing Bootstrap 5 base
══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap');

:root {
    --sr-blue:      #2563EB;
    --sr-blue-lt:   #EFF6FF;
    --sr-blue-mid:  #BFDBFE;
    --sr-green:     #059669;
    --sr-green-lt:  #ECFDF5;
    --sr-amber:     #D97706;
    --sr-amber-lt:  #FFFBEB;
    --sr-red:       #DC2626;
    --sr-surface:   #FFFFFF;
    --sr-page:      #F1F5F9;
    --sr-border:    #E2E8F0;
    --sr-border2:   #CBD5E1;
    --sr-text:      #0F172A;
    --sr-muted:     #64748B;
    --sr-subtle:    #94A3B8;
    --sr-radius:    12px;
    --sr-radius-lg: 18px;
    --sr-font:      'DM Sans', system-ui, sans-serif;
    --sr-shadow:    0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.06);
    --sr-shadow-lg: 0 4px 6px rgba(0,0,0,.05), 0 10px 40px rgba(0,0,0,.10);
}

.sr-wrap * { font-family: var(--sr-font); box-sizing: border-box; }

/* ── Page header ── */
.sr-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 32px;
}
.sr-page-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--sr-text);
    letter-spacing: -.03em;
    margin: 0 0 4px;
}
.sr-page-header p { font-size: .875rem; color: var(--sr-muted); margin: 0; }
.sr-back-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    background: var(--sr-surface);
    border: 1px solid var(--sr-border2);
    border-radius: 10px;
    font-size: .85rem; font-weight: 600;
    color: var(--sr-muted);
    text-decoration: none;
    transition: all .15s;
    white-space: nowrap;
}
.sr-back-btn:hover { border-color: var(--sr-blue); color: var(--sr-blue); background: var(--sr-blue-lt); }

/* ── Step indicator ── */
.sr-stepper {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 32px;
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    border-radius: var(--sr-radius-lg);
    padding: 20px 28px;
    box-shadow: var(--sr-shadow);
    overflow-x: auto;
}
.sr-step {
    display: flex; align-items: center; gap: 10px;
    flex: 1; min-width: 130px;
    cursor: pointer;
    position: relative;
}
.sr-step:not(:last-child)::after {
    content: '';
    position: absolute;
    right: -8px; top: 50%;
    transform: translateY(-50%);
    width: 16px; height: 2px;
    background: var(--sr-border2);
    transition: background .3s;
    z-index: 1;
}
.sr-step.done:not(:last-child)::after { background: var(--sr-blue); }
.sr-step-icon {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700;
    flex-shrink: 0;
    background: var(--sr-page);
    color: var(--sr-subtle);
    border: 2px solid var(--sr-border2);
    transition: all .25s;
}
.sr-step.active .sr-step-icon {
    background: var(--sr-blue);
    color: #fff;
    border-color: var(--sr-blue);
    box-shadow: 0 0 0 4px var(--sr-blue-mid);
}
.sr-step.done .sr-step-icon {
    background: var(--sr-green-lt);
    color: var(--sr-green);
    border-color: var(--sr-green);
}
.sr-step-info { flex: 1; min-width: 0; }
.sr-step-label {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sr-subtle);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    transition: color .2s;
}
.sr-step.active .sr-step-label { color: var(--sr-blue); }
.sr-step.done  .sr-step-label  { color: var(--sr-green); }
.sr-step-desc {
    font-size: .78rem; color: var(--sr-muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 1px;
}
.sr-step-sep { width: 32px; flex-shrink: 0; }

/* ── Panel card ── */
.sr-panel {
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    border-radius: var(--sr-radius-lg);
    box-shadow: var(--sr-shadow);
    margin-bottom: 20px;
    display: none;
    animation: srFadeUp .25s ease;
}
.sr-panel.active { display: block; }

@keyframes srFadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.sr-panel-head {
    padding: 22px 28px 18px;
    border-bottom: 1px solid var(--sr-border);
    display: flex; align-items: center; gap: 12px;
}
.sr-panel-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.sr-panel-icon.blue  { background: var(--sr-blue-lt);  color: var(--sr-blue);  }
.sr-panel-icon.green { background: var(--sr-green-lt); color: var(--sr-green); }
.sr-panel-icon.amber { background: var(--sr-amber-lt); color: var(--sr-amber); }
.sr-panel-head h5 {
    font-size: 1rem; font-weight: 700;
    color: var(--sr-text); margin: 0 0 2px;
}
.sr-panel-head p { font-size: .8rem; color: var(--sr-muted); margin: 0; }
.sr-panel-body { padding: 24px 28px; }

/* ── Form elements ── */
.sr-label {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sr-muted); display: block;
    margin-bottom: 6px;
}
.sr-label span { color: var(--sr-red); }
.sr-input, .sr-select {
    width: 100%; height: 42px;
    padding: 0 14px;
    border: 1.5px solid var(--sr-border2);
    border-radius: 9px;
    font-family: var(--sr-font);
    font-size: .9rem;
    color: var(--sr-text);
    background: #fff;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.sr-input:focus, .sr-select:focus {
    border-color: var(--sr-blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
.sr-input.readonly, .sr-input[readonly] {
    background: var(--sr-page);
    color: var(--sr-muted);
    cursor: default;
}
.sr-input.valid   { border-color: var(--sr-green); }
.sr-input.invalid { border-color: var(--sr-red); }

.sr-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
    cursor: pointer;
}
.sr-select:focus { border-color: var(--sr-blue); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }

.sr-input-group {
    display: flex; align-items: center;
    border: 1.5px solid var(--sr-border2);
    border-radius: 9px;
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
    background: #fff;
}
.sr-input-group:focus-within {
    border-color: var(--sr-blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
.sr-input-group.readonly { background: var(--sr-page); }
.sr-input-pfx {
    padding: 0 12px; height: 42px;
    display: flex; align-items: center;
    font-size: .85rem; font-weight: 600;
    color: var(--sr-muted);
    background: transparent;
    border-right: 1.5px solid var(--sr-border);
    flex-shrink: 0;
}
.sr-input-group .sr-input {
    border: none; box-shadow: none; border-radius: 0;
    padding-left: 10px; height: 42px;
    flex: 1;
}
.sr-input-group.readonly .sr-input { background: var(--sr-page); }
.sr-input-group:focus-within .sr-input { box-shadow: none; }

/* ── ID badge ── */
.sr-id-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--sr-blue-lt);
    border: 1.5px dashed var(--sr-blue-mid);
    border-radius: 9px;
    padding: 8px 14px;
    font-size: .88rem; font-weight: 600;
    color: var(--sr-blue);
}
.sr-id-badge i { font-size: .9rem; }

/* ── Course info card ── */
.sr-course-info {
    background: var(--sr-page);
    border: 1px solid var(--sr-border);
    border-radius: 10px;
    padding: 14px 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 20px;
}
.sr-course-info-item label {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sr-subtle); display: block; margin-bottom: 2px;
}
.sr-course-info-item span {
    font-size: .9rem; font-weight: 600;
    color: var(--sr-text);
}
.sr-course-info-item span.empty { color: var(--sr-subtle); font-weight: 400; }

/* ── Payment summary card ── */
.sr-pay-summary {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1.5px solid #a7f3d0;
    border-radius: 12px;
    padding: 20px;
}
.sr-pay-summary-title {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sr-green); margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
}
.sr-pay-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0;
    font-size: .875rem;
}
.sr-pay-row.divider { border-top: 1px solid #a7f3d0; margin-top: 6px; padding-top: 12px; }
.sr-pay-row .lbl { color: var(--sr-muted); }
.sr-pay-row .val { font-weight: 600; color: var(--sr-text); }
.sr-pay-row.total .val { font-size: 1.15rem; color: var(--sr-green); }
.sr-pay-row.balance .val { color: var(--sr-amber); }

/* ── Footer actions ── */
.sr-footer {
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    border-radius: var(--sr-radius-lg);
    box-shadow: var(--sr-shadow);
    padding: 18px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.sr-footer-nav { display: flex; gap: 10px; }
.sr-btn {
    height: 42px; padding: 0 22px;
    border: none; border-radius: 9px;
    font-family: var(--sr-font); font-size: .875rem; font-weight: 600;
    cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    transition: all .15s; white-space: nowrap;
}
.sr-btn-ghost {
    background: var(--sr-page);
    color: var(--sr-muted);
    border: 1.5px solid var(--sr-border2);
}
.sr-btn-ghost:hover { background: #E2E8F0; color: var(--sr-text); }
.sr-btn-primary {
    background: var(--sr-blue);
    color: #fff;
    box-shadow: 0 2px 8px rgba(37,99,235,.3);
}
.sr-btn-primary:hover { background: #1D4ED8; box-shadow: 0 4px 14px rgba(37,99,235,.35); }
.sr-btn-primary:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
.sr-btn-success {
    background: var(--sr-green);
    color: #fff;
    box-shadow: 0 2px 8px rgba(5,150,105,.3);
}
.sr-btn-success:hover { background: #047857; }
.sr-btn-success:disabled { opacity: .55; cursor: not-allowed; }

/* ── Validation feedback ── */
.sr-error-msg {
    font-size: .75rem; color: var(--sr-red);
    margin-top: 4px; display: none;
}
.sr-field-wrap.has-error .sr-input,
.sr-field-wrap.has-error .sr-select { border-color: var(--sr-red); }
.sr-field-wrap.has-error .sr-error-msg { display: block; }
.sr-field-wrap.has-error .sr-input-group { border-color: var(--sr-red); }

/* ── Progress dots ── */
.sr-progress {
    display: flex; gap: 6px; align-items: center;
}
.sr-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--sr-border2);
    transition: all .25s;
}
.sr-dot.active { background: var(--sr-blue); width: 20px; border-radius: 4px; }
.sr-dot.done   { background: var(--sr-green); }

/* ── Review panel ── */
.sr-review-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 1px; background: var(--sr-border);
    border: 1px solid var(--sr-border);
    border-radius: 10px; overflow: hidden;
}
.sr-review-cell {
    background: var(--sr-surface);
    padding: 12px 16px;
}
.sr-review-cell:nth-child(odd) { background: #FAFBFC; }
.sr-review-cell label {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sr-subtle); display: block; margin-bottom: 3px;
}
.sr-review-cell span {
    font-size: .9rem; font-weight: 500; color: var(--sr-text);
}
.sr-review-cell span.empty { color: var(--sr-subtle); font-style: italic; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .sr-panel-body  { padding: 18px 16px; }
    .sr-panel-head  { padding: 16px; }
    .sr-footer      { padding: 14px 16px; }
    .sr-stepper     { padding: 14px 16px; gap: 0; }
    .sr-step-desc   { display: none; }
    .sr-review-grid { grid-template-columns: 1fr; }
    .sr-course-info { grid-template-columns: 1fr; }
}
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main sr-wrap">

    <!-- ── Page Header ───────────────────────────────────── -->
    <div class="sr-page-header fade-up">
        <div>
            <h2>Student Registration</h2>
            <p>Register a new student, enroll them in a course, and record the first payment — all in one flow.</p>
        </div>
        <a href="students.php" class="sr-back-btn">
            <i class="bi bi-arrow-left"></i> Back to Students
        </a>
    </div>

    <!-- ── Step Indicator ────────────────────────────────── -->
    <div class="sr-stepper fade-up" id="srStepper">
        <div class="sr-step active" data-step="1" onclick="goToStep(1)">
            <div class="sr-step-icon" id="stepIcon1"><i class="bi bi-person-fill"></i></div>
            <div class="sr-step-info">
                <div class="sr-step-label">Step 1</div>
                <div class="sr-step-desc">Student Info</div>
            </div>
        </div>
        <div class="sr-step-sep"></div>
        <div class="sr-step" data-step="2" onclick="goToStep(2)">
            <div class="sr-step-icon" id="stepIcon2"><i class="bi bi-book-fill"></i></div>
            <div class="sr-step-info">
                <div class="sr-step-label">Step 2</div>
                <div class="sr-step-desc">Enrollment</div>
            </div>
        </div>
        <div class="sr-step-sep"></div>
        <div class="sr-step" data-step="3" onclick="goToStep(3)">
            <div class="sr-step-icon" id="stepIcon3"><i class="bi bi-cash-stack"></i></div>
            <div class="sr-step-info">
                <div class="sr-step-label">Step 3</div>
                <div class="sr-step-desc">Payment</div>
            </div>
        </div>
        <div class="sr-step-sep"></div>
        <div class="sr-step" data-step="4" onclick="goToStep(4)">
            <div class="sr-step-icon" id="stepIcon4"><i class="bi bi-check2-all"></i></div>
            <div class="sr-step-info">
                <div class="sr-step-label">Step 4</div>
                <div class="sr-step-desc">Review</div>
            </div>
        </div>
    </div>

    <form id="registrationForm" novalidate>

        <!-- ══ PANEL 1: Student Info ══════════════════════ -->
        <div class="sr-panel active" id="panel1">
            <div class="sr-panel-head">
                <div class="sr-panel-icon blue"><i class="bi bi-person-fill"></i></div>
                <div>
                    <h5>Student Information</h5>
                    <p>Fill in the student's personal details. Fields marked <span style="color:var(--sr-red)">*</span> are required.</p>
                </div>
            </div>
            <div class="sr-panel-body">
                <div class="row g-3">

                    <!-- Auto ID + Branch -->
                    <div class="col-md-6">
                        <label class="sr-label">Auto-Generated Student ID</label>
                        <div class="sr-id-badge" id="idBadge">
                            <i class="bi bi-hash"></i>
                            <span id="student_id_display">Loading…</span>
                        </div>
                        <input type="hidden" name="student_id" id="student_id">
                    </div>
                    <div class="col-md-6">
                        <label class="sr-label">Branch <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_branch">
                            <select class="sr-select" id="branch_id" name="branch_id" required></select>
                            <div class="sr-error-msg">Please select a branch.</div>
                        </div>
                    </div>

                    <!-- Name row -->
                    <div class="col-md-6">
                        <label class="sr-label" for="first_name">First Name <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_first_name">
                            <input class="sr-input" type="text" id="first_name" name="first_name"
                                   placeholder="e.g. Alice" required autocomplete="given-name">
                            <div class="sr-error-msg">First name is required.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="sr-label" for="last_name">Last Name <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_last_name">
                            <input class="sr-input" type="text" id="last_name" name="last_name"
                                   placeholder="e.g. Mensah" required autocomplete="family-name">
                            <div class="sr-error-msg">Last name is required.</div>
                        </div>
                    </div>

                    <!-- Details row -->
                    <div class="col-md-3">
                        <label class="sr-label" for="gender">Gender <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_gender">
                            <select class="sr-select" id="gender" name="gender" required>
                                <option value="">Select…</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <div class="sr-error-msg">Please select gender.</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="sr-label" for="dob">Date of Birth <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_dob">
                            <input class="sr-input" type="date" id="dob" name="dob"
                                   max="<?= date('Y-m-d', strtotime('-5 years')) ?>" required>
                            <div class="sr-error-msg">Date of birth is required.</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="sr-label" for="phone">Phone Number</label>
                        <input class="sr-input" type="tel" id="phone" name="phone"
                               placeholder="+231 XXX XXX" maxlength="30">
                    </div>
                    <div class="col-md-3">
                        <label class="sr-label" for="email">Email Address</label>
                        <input class="sr-input" type="email" id="email" name="email"
                               placeholder="Optional">
                    </div>

                    <!-- Address -->
                    <div class="col-12">
                        <label class="sr-label" for="address">Home Address</label>
                        <input class="sr-input" type="text" id="address" name="address"
                               placeholder="Street, City, Country">
                    </div>

                </div>
            </div>
        </div>

        <!-- ══ PANEL 2: Enrollment ════════════════════════ -->
        <div class="sr-panel" id="panel2">
            <div class="sr-panel-head">
                <div class="sr-panel-icon blue"><i class="bi bi-book-fill"></i></div>
                <div>
                    <h5>Course Enrollment</h5>
                    <p>Select the course this student will be enrolled in.</p>
                </div>
            </div>
            <div class="sr-panel-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="sr-label" for="course_id">Select Course <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_course">
                            <select class="sr-select" id="course_id" name="course_id" required>
                                <option value="">Select branch first…</option>
                            </select>
                            <div class="sr-error-msg">Please select a course.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="sr-label" for="enrollment_date">Enrollment Date <span>*</span></label>
                        <div class="sr-field-wrap" id="fw_enrollment_date">
                            <input class="sr-input" type="date" id="enrollment_date"
                                   name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                            <div class="sr-error-msg">Enrollment date is required.</div>
                        </div>
                    </div>

                    <!-- Course detail card -->
                    <div class="col-12">
                        <label class="sr-label">Course Details</label>
                        <div class="sr-course-info" id="courseInfoCard">
                            <div class="sr-course-info-item">
                                <label>Duration</label>
                                <span id="ci_duration" class="empty">— select a course —</span>
                            </div>
                            <div class="sr-course-info-item">
                                <label>Course Fee</label>
                                <span id="ci_fee" class="empty">— select a course —</span>
                            </div>
                            <div class="sr-course-info-item">
                                <label>Branch</label>
                                <span id="ci_branch" class="empty">—</span>
                            </div>
                            <div class="sr-course-info-item">
                                <label>Status</label>
                                <span id="ci_status" class="empty">—</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══ PANEL 3: Payment ═══════════════════════════ -->
        <div class="sr-panel" id="panel3">
            <div class="sr-panel-head">
                <div class="sr-panel-icon green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h5>Initial Payment</h5>
                    <p>Record the first payment. Leave at $0.00 if no payment is made today.</p>
                </div>
            </div>
            <div class="sr-panel-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="sr-label">Total Course Fee</label>
                        <div class="sr-input-group readonly">
                            <span class="sr-input-pfx">$</span>
                            <input class="sr-input" type="number" id="total_fee"
                                   name="total_fee_display" readonly tabindex="-1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="sr-label" for="amount_paid">Amount Paid Today</label>
                        <div class="sr-input-group" id="ig_amount_paid">
                            <span class="sr-input-pfx" style="color:var(--sr-green);">$</span>
                            <input class="sr-input" type="number" id="amount_paid"
                                   name="amount_paid" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="sr-label">Balance Remaining</label>
                        <div class="sr-input-group readonly">
                            <span class="sr-input-pfx">$</span>
                            <input class="sr-input" type="number" id="balance"
                                   name="balance_display" readonly tabindex="-1">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="sr-label" for="payment_method">Payment Method</label>
                        <select class="sr-select" id="payment_method" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Mobile Money - Orange">Mobile Money - Orange</option>
                            <option value="Mobile Money - MTN">Mobile Money - MTN</option>
                            <option value="Check">Check</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>

                    <div class="col-md-7">
                        <label class="sr-label" for="payment_notes">Payment Notes</label>
                        <input class="sr-input" type="text" id="payment_notes"
                               name="payment_notes" placeholder="Reference number, receipt note, etc.">
                    </div>

                    <!-- Live payment summary -->
                    <div class="col-12">
                        <div class="sr-pay-summary">
                            <div class="sr-pay-summary-title">
                                <i class="bi bi-calculator-fill"></i> Live Payment Summary
                            </div>
                            <div class="sr-pay-row">
                                <span class="lbl">Total Course Fee</span>
                                <span class="val" id="ps_total">$0.00</span>
                            </div>
                            <div class="sr-pay-row">
                                <span class="lbl">Amount Paid Today</span>
                                <span class="val" id="ps_paid">$0.00</span>
                            </div>
                            <div class="sr-pay-row divider total">
                                <span class="lbl" style="font-weight:600;color:var(--sr-green);">Balance Due</span>
                                <span class="val" id="ps_balance">$0.00</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══ PANEL 4: Review & Submit ═══════════════════ -->
        <div class="sr-panel" id="panel4">
            <div class="sr-panel-head">
                <div class="sr-panel-icon amber"><i class="bi bi-clipboard2-check-fill"></i></div>
                <div>
                    <h5>Review &amp; Confirm</h5>
                    <p>Review all details before submitting. Click any step to go back and edit.</p>
                </div>
            </div>
            <div class="sr-panel-body">
                <div class="row g-3">

                    <div class="col-12">
                        <div class="sr-label" style="margin-bottom:10px;">
                            <i class="bi bi-person me-1"></i> Student Details
                        </div>
                        <div class="sr-review-grid" id="reviewStudent"></div>
                    </div>

                    <div class="col-12">
                        <div class="sr-label" style="margin-bottom:10px;margin-top:8px;">
                            <i class="bi bi-book me-1"></i> Enrollment Details
                        </div>
                        <div class="sr-review-grid" id="reviewEnrollment"></div>
                    </div>

                    <div class="col-12">
                        <div class="sr-label" style="margin-bottom:10px;margin-top:8px;">
                            <i class="bi bi-cash me-1"></i> Payment Details
                        </div>
                        <div class="sr-review-grid" id="reviewPayment"></div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══ Footer Navigation ══════════════════════════ -->
        <div class="sr-footer fade-up">
            <div class="sr-progress" id="srProgress">
                <div class="sr-dot active" id="dot1"></div>
                <div class="sr-dot" id="dot2"></div>
                <div class="sr-dot" id="dot3"></div>
                <div class="sr-dot" id="dot4"></div>
            </div>

            <div class="sr-footer-nav">
                <button type="button" class="sr-btn sr-btn-ghost" id="btnBack" style="display:none;"
                        onclick="stepBack()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <button type="button" class="sr-btn sr-btn-ghost" id="btnReset"
                        onclick="resetAll()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <button type="button" class="sr-btn sr-btn-primary" id="btnNext"
                        onclick="stepNext()">
                    Next <i class="bi bi-arrow-right"></i>
                </button>
                <button type="submit" class="sr-btn sr-btn-success" id="btnSubmit"
                        style="display:none;">
                    <i class="bi bi-save-fill"></i> Register Student
                </button>
            </div>
        </div>

    </form><!-- /registrationForm -->

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
/* ════════════════════════════════════════════════════════════
   STUDENT REGISTRATION — Multi-step JS
════════════════════════════════════════════════════════════ */
const API          = 'models/api/student_registration_api.php';
const IS_SA        = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const SES_BRANCH   = <?= (int)$sessionBranch ?>;

let currentStep    = 1;
let totalSteps     = 4;
let courseFee      = 0;
let selectedCourse = {};
let selectedBranch = {};

// ── Formatters ──────────────────────────────────────────────
const fmt = v => parseFloat(v || 0).toFixed(2);
const money = v => `$${fmt(v)}`;
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

// ── Step UI ─────────────────────────────────────────────────
function updateStepUI(step) {
    for (let i = 1; i <= totalSteps; i++) {
        const stepEl  = $(`.sr-step[data-step="${i}"]`);
        const iconEl  = $(`#stepIcon${i}`);
        const dotEl   = $(`#dot${i}`);
        const panelEl = $(`#panel${i}`);

        stepEl.removeClass('active done');
        dotEl.removeClass('active done');
        panelEl.removeClass('active');

        if (i < step)  { stepEl.addClass('done');   dotEl.addClass('done'); }
        if (i === step){ stepEl.addClass('active');  dotEl.addClass('active'); panelEl.addClass('active'); }
    }

    // Back / Next / Submit visibility
    $('#btnBack').toggle(step > 1);
    $('#btnNext').toggle(step < totalSteps);
    $('#btnSubmit').toggle(step === totalSteps);

    // On review step, rebuild review panels
    if (step === totalSteps) buildReview();
}

function goToStep(step) {
    // Only allow going back (forward requires validation)
    if (step >= currentStep) return;
    currentStep = step;
    updateStepUI(currentStep);
    window.scrollTo({top: 0, behavior: 'smooth'});
}

// ── Field validation ─────────────────────────────────────────
const required1 = ['branch_id','first_name','last_name','gender','dob'];
const required2 = ['course_id','enrollment_date'];

function validateStep(step) {
    let ok = true;
    const fields = step === 1 ? required1 : step === 2 ? required2 : [];

    fields.forEach(name => {
        const el = $(`#${name}, [name="${name}"]`).first();
        const fw = el.closest('.sr-field-wrap');
        if (!fw.length) return;

        const val = el.val()?.trim();
        if (!val) {
            fw.addClass('has-error');
            ok = false;
        } else {
            fw.removeClass('has-error');
        }
    });

    // Step 2 extra: course must be selected
    if (step === 2) {
        const cv = $('#course_id').val();
        const fw = $('#fw_course');
        if (!cv) { fw.addClass('has-error'); ok = false; }
        else fw.removeClass('has-error');
    }

    return ok;
}

function stepNext() {
    if (!validateStep(currentStep)) {
        // Shake the active panel
        const panel = $(`#panel${currentStep}`);
        panel.css('animation','none');
        requestAnimationFrame(() => {
            panel.css('animation','');
        });
        return;
    }
    if (currentStep < totalSteps) {
        currentStep++;
        updateStepUI(currentStep);
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
}

function stepBack() {
    if (currentStep > 1) {
        currentStep--;
        updateStepUI(currentStep);
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
}

// ── Bootstrap load ───────────────────────────────────────────
function loadBootstrap() {
    $.getJSON(API, {action:'bootstrap'}, function(res){
        if (!res.success) return;
        const d = res.data;

        // Student ID
        $('#student_id').val(d.student_id || '');
        $('#student_id_display').text(d.student_id || 'Generating…');

        // Branches
        let bOpts = '<option value="">Select Branch…</option>';
        (d.branches || []).forEach(b => {
            bOpts += `<option value="${b.id}" data-name="${esc(b.name)}">${esc(b.name)}</option>`;
        });
        $('#branch_id').html(bOpts);

        if (!IS_SA && SES_BRANCH) {
            $('#branch_id').val(SES_BRANCH).prop('disabled', true);
            const bname = $('#branch_id option:selected').text();
            selectedBranch = { id: SES_BRANCH, name: bname };
            loadCourses(SES_BRANCH);
        }
    });
}

// ── Load courses ─────────────────────────────────────────────
function loadCourses(branchId) {
    $('#course_id').html('<option value="">Loading courses…</option>');
    $.getJSON(API, {action:'courses', branch_id: branchId}, function(res){
        if (!res.success) {
            $('#course_id').html('<option value="">No active courses found</option>');
            return;
        }
        let opts = '<option value="">Select Course…</option>';
        res.data.forEach(c => {
            opts += `<option value="${c.id}"
                              data-duration="${esc(c.duration)}"
                              data-fee="${c.fee}"
                              data-name="${esc(c.course_name)}"
                              data-status="${esc(c.status||'Active')}">${esc(c.course_name)}</option>`;
        });
        $('#course_id').html(opts);
        resetCourseInfo();
    });
}

// ── Course info card ─────────────────────────────────────────
function resetCourseInfo() {
    ['#ci_duration','#ci_fee','#ci_branch','#ci_status'].forEach(s => {
        $(s).text('— select a course —').addClass('empty');
    });
    courseFee = 0;
    selectedCourse = {};
    $('#total_fee').val('0.00');
    recalcBalance();
}

function updateCourseInfo(sel) {
    const duration = sel.data('duration') || '—';
    const fee      = parseFloat(sel.data('fee') || 0);
    const bname    = $('#branch_id option:selected').text() || '—';
    const status   = sel.data('status') || 'Active';

    courseFee = fee;
    selectedCourse = {
        id: sel.val(),
        name: sel.text(),
        duration, fee, status
    };

    $('#ci_duration').text(duration).removeClass('empty');
    $('#ci_fee').text(money(fee)).removeClass('empty');
    $('#ci_branch').text(bname).removeClass('empty');
    $('#ci_status').text(status).removeClass('empty');
    $('#total_fee').val(fmt(fee));
    recalcBalance();
}

// ── Balance calc ─────────────────────────────────────────────
function recalcBalance() {
    const total = parseFloat($('#total_fee').val() || 0);
    let paid    = parseFloat($('#amount_paid').val() || 0);
    if (paid < 0)     paid = 0;
    if (paid > total) paid = total;
    $('#amount_paid').val(fmt(paid));
    const bal = Math.max(0, total - paid);
    $('#balance').val(fmt(bal));

    // Live summary
    $('#ps_total').text(money(total));
    $('#ps_paid').text(money(paid));
    $('#ps_balance').text(money(bal));

    // Color balance
    $('#ps_balance').css('color', bal > 0 ? 'var(--sr-amber)' : 'var(--sr-green)');
}

// ── Review panel builder ─────────────────────────────────────
function reviewRow(label, value) {
    const val = value?.toString().trim();
    return `<div class="sr-review-cell">
                <label>${esc(label)}</label>
                <span class="${val ? '' : 'empty'}">${val ? esc(val) : '—'}</span>
            </div>`;
}

function buildReview() {
    // Student
    $('#reviewStudent').html(
        reviewRow('Student ID',   $('#student_id').val()) +
        reviewRow('First Name',   $('#first_name').val()) +
        reviewRow('Last Name',    $('#last_name').val()) +
        reviewRow('Gender',       $('#gender').val()) +
        reviewRow('Date of Birth',($('#dob').val())) +
        reviewRow('Phone',        $('#phone').val()) +
        reviewRow('Email',        $('#email').val()) +
        reviewRow('Address',      $('#address').val()) +
        reviewRow('Branch',       $('#branch_id option:selected').text())
    );

    // Enrollment
    $('#reviewEnrollment').html(
        reviewRow('Course',          $('#course_id option:selected').text()) +
        reviewRow('Duration',        selectedCourse.duration || '—') +
        reviewRow('Course Fee',      money(selectedCourse.fee || 0)) +
        reviewRow('Enrollment Date', $('#enrollment_date').val())
    );

    // Payment
    const total  = parseFloat($('#total_fee').val() || 0);
    const paid   = parseFloat($('#amount_paid').val() || 0);
    const bal    = Math.max(0, total - paid);
    $('#reviewPayment').html(
        reviewRow('Total Fee',      money(total)) +
        reviewRow('Amount Paid',    money(paid)) +
        reviewRow('Balance',        money(bal)) +
        reviewRow('Method',         $('#payment_method').val()) +
        reviewRow('Notes',          $('#payment_notes').val())
    );
}

// ── Event Bindings ───────────────────────────────────────────
$('#branch_id').on('change', function(){
    const bid = $(this).val();
    const bname = $(this).find('option:selected').text();
    selectedBranch = bid ? {id:bid, name:bname} : {};
    if (bid) loadCourses(bid);
    else $('#course_id').html('<option value="">Select branch first…</option>');
});

$('#course_id').on('change', function(){
    const sel = $(this).find('option:selected');
    if ($(this).val()) updateCourseInfo(sel);
    else resetCourseInfo();
});

$('#amount_paid').on('input', recalcBalance);

// Clear error state on change
$(document).on('change input', '.sr-field-wrap .sr-input, .sr-field-wrap .sr-select', function(){
    if ($(this).val()?.trim()) {
        $(this).closest('.sr-field-wrap').removeClass('has-error');
    }
});

// ── Reset ────────────────────────────────────────────────────
function resetAll() {
    Swal.fire({
        title: 'Reset Form?',
        text: 'All entered data will be cleared.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reset',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#DC2626'
    }).then(r => {
        if (!r.isConfirmed) return;
        document.getElementById('registrationForm').reset();
        resetCourseInfo();
        currentStep = 1;
        updateStepUI(1);
        loadBootstrap();
        recalcBalance();
        $('.sr-field-wrap').removeClass('has-error');
    });
}

// ── Submit ────────────────────────────────────────────────────
$('#registrationForm').on('submit', function(e){
    e.preventDefault();

    if (parseFloat($('#amount_paid').val()||0) > parseFloat($('#total_fee').val()||0)) {
        Swal.fire('Invalid Payment', 'Amount paid cannot exceed total fee.', 'error');
        return;
    }

    const btn = $('#btnSubmit');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving…');

    const data = $(this).serialize()
               + '&action=register'
               + (IS_SA ? '' : `&branch_id=${SES_BRANCH}`);

    $.post(API, data, function(res){
        btn.prop('disabled', false).html('<i class="bi bi-save-fill me-1"></i> Register Student');

        if (!res.success) {
            Swal.fire('Registration Failed', res.message || 'Please check your data and try again.', 'error');
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Registered!',
            html: `Student <strong>${res.data.student_code}</strong> successfully registered.<br>
                   Balance: <strong>$${fmt(res.data.balance)}</strong>`,
            confirmButtonText: '<i class="bi bi-receipt me-1"></i> View Receipt'
        }).then(() => {
            const pid = res.data.payment_id || 0;
            window.location.href = `student_registration_receipt.php`
                + `?student_id=${res.data.student_id}`
                + `&enrollment_id=${res.data.enrollment_id}`
                + `&payment_id=${pid}`;
        });
    }, 'json').fail(function(){
        btn.prop('disabled', false).html('<i class="bi bi-save-fill me-1"></i> Register Student');
        Swal.fire('Error', 'Could not complete registration. Please try again.', 'error');
    });
});

// ── Init ─────────────────────────────────────────────────────
loadBootstrap();
recalcBalance();
updateStepUI(1);
</script>
</body>
</html>