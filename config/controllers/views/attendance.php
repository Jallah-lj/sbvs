<?php
ob_start();
session_start();
require_once '../../config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db   = (new Database())->getConnection();
$role = $_SESSION['role'] ?? '';
$isSA = ($role === 'Super Admin');
$isBA = ($role === 'Branch Admin');
$isAdmin = ($role === 'Admin');

if (!$isSA && !$isBA && !$isAdmin) {
    header("Location: dashboard.php");
    exit;
}

$branchId   = (int)($_SESSION['branch_id'] ?? 0);
$branchName = '';

if (!$isSA && $branchId) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$branchId]);
    $branchName = $bStmt->fetchColumn() ?: '';
}

$branches = $isSA
    ? $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle  = 'Attendance';
$activePage = 'attendance.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ─────────────────────────────────────────────────────────────
   ATTENDANCE PAGE — Bootstrap-enhanced overrides
   All classes are prefixed att- or use scoped selectors so
   nothing leaks into the rest of the app.
───────────────────────────────────────────────────────────── */

/* ── Status pills (keep originals + add excused) ── */
.att-present { background: #d1fae5; color: #065f46; font-weight: 600; border-radius: 20px; padding: 3px 12px; font-size: .72rem; letter-spacing: .3px; white-space: nowrap; }
.att-absent  { background: #fee2e2; color: #991b1b; font-weight: 600; border-radius: 20px; padding: 3px 12px; font-size: .72rem; letter-spacing: .3px; white-space: nowrap; }
.att-late    { background: #fef3c7; color: #92400e; font-weight: 600; border-radius: 20px; padding: 3px 12px; font-size: .72rem; letter-spacing: .3px; white-space: nowrap; }
.att-excused { background: #e0f2fe; color: #075985; font-weight: 600; border-radius: 20px; padding: 3px 12px; font-size: .72rem; letter-spacing: .3px; white-space: nowrap; }

/* ── Summary cards — tighter, accent left-border ── */
.att-stat-card {
    border: 1px solid rgba(0,0,0,.07) !important;
    border-radius: 12px !important;
    transition: box-shadow .15s, transform .15s;
}
.att-stat-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08) !important; transform: translateY(-1px); }
.att-stat-card .card-body { padding: 1.1rem 1.25rem; }
.att-stat-icon {
    width: 46px; height: 46px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── Filter bar ── */
.att-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
}
.att-filter-bar .form-select,
.att-filter-bar .form-control {
    height: 36px;
    font-size: .825rem;
    border-color: #d1d5db;
    border-radius: 8px;
    box-shadow: none !important;
    transition: border-color .15s;
}
.att-filter-bar .form-select:focus,
.att-filter-bar .form-control:focus { border-color: #0d6efd; }
.att-filter-bar .btn {
    height: 36px;
    padding: 0 16px;
    font-size: .825rem;
    border-radius: 8px;
    display: inline-flex; align-items: center; gap: 5px;
}

/* ── Attendance history table ── */
#attendanceTable thead th {
    background: #f8f9fb;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .55px;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    padding: 11px 14px;
    white-space: nowrap;
}
#attendanceTable tbody td {
    padding: 12px 14px;
    font-size: .875rem;
    vertical-align: middle;
    border-bottom: 1px solid #f3f4f6;
}
#attendanceTable tbody tr:last-child td { border-bottom: none; }
#attendanceTable tbody tr:hover td { background: #f0f7ff; }
.att-student-name { font-weight: 600; color: #111827; }
.att-id-badge {
    background: #f1f5f9; color: #475569;
    border-radius: 5px; padding: 2px 8px;
    font-size: .75rem; font-family: monospace; font-weight: 500;
}
.att-date-col { color: #6b7280; font-size: .825rem; }

/* DataTables cosmetic fixes */
div.dataTables_wrapper div.dataTables_filter input { border-radius: 8px !important; height: 34px !important; font-size: .825rem !important; border: 1px solid #d1d5db !important; box-shadow: none !important; }
div.dataTables_wrapper div.dataTables_length select { border-radius: 8px !important; border: 1px solid #d1d5db !important; }
div.dataTables_wrapper div.dataTables_info { font-size: .8rem; color: #6b7280; }

/* ── Take Attendance Modal ── */
#takeAttModal .modal-content { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.18); }
#takeAttModal .modal-header { padding: 1.1rem 1.5rem; border-bottom: none; }
#takeAttModal .modal-body { background: #f8f9fb; padding: 1.5rem; }
#takeAttModal .modal-footer { background: #fff; border-top: 1px solid #f0f0f0; padding: .9rem 1.5rem; }

/* Modal — selection section card */
.att-modal-select-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem 1.25rem 1rem;
    margin-bottom: 1.25rem;
}
.att-modal-select-card label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .55px;
    color: #6b7280;
    margin-bottom: 5px;
    display: block;
}
.att-modal-select-card .form-select,
.att-modal-select-card .form-control {
    height: 40px;
    font-size: .875rem;
    border-color: #d1d5db;
    border-radius: 8px;
    box-shadow: none !important;
    background-color: #f9fafb;
    transition: border-color .15s, background-color .15s;
}
.att-modal-select-card .form-select:focus,
.att-modal-select-card .form-control:focus {
    border-color: #0d6efd;
    background-color: #fff;
}
#loadStudentsBtn {
    height: 40px; width: 40px;
    padding: 0;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
}

/* Modal — roster toolbar */
.att-roster-toolbar {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
}
.att-roster-toolbar h6 { font-weight: 700; font-size: .9rem; margin-bottom: 0; }
.att-roster-toolbar .badge { font-size: .72rem; font-weight: 600; border-radius: 20px; padding: 3px 10px; }

/* Modal — roster table */
.att-roster-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.att-roster-card table { margin-bottom: 0; }
.att-roster-card thead th {
    background: #f8f9fb;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .55px;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    padding: 10px 14px;
}
.att-roster-card tbody td {
    padding: 10px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f3f4f6;
    font-size: .875rem;
}
.att-roster-card tbody tr:last-child td { border-bottom: none; }
.att-roster-card tbody tr:hover td { background: #f8fbff; }

/* Avatar initials */
.att-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: #eff6ff; color: #1d4ed8;
    font-size: .7rem; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0; text-transform: uppercase;
}

/* Status select in roster */
.att-status-select {
    height: 34px;
    font-size: .825rem;
    border-color: #d1d5db;
    border-radius: 7px;
    min-width: 120px;
    box-shadow: none !important;
    cursor: pointer;
}
.att-status-select:focus { border-color: #0d6efd; }

/* Notes input in roster */
.att-notes-input {
    height: 34px;
    font-size: .825rem;
    border-color: #d1d5db;
    border-radius: 7px;
    box-shadow: none !important;
}
.att-notes-input:focus { border-color: #0d6efd; }
.att-notes-input::placeholder { color: #9ca3af; font-size: .8rem; }

/* Modal empty / placeholder state */
.att-placeholder {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #9ca3af;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}
.att-placeholder i { font-size: 2rem; margin-bottom: .5rem; display: block; opacity: .4; }

/* Mark-all buttons */
.att-bulk-btn { height: 32px; padding: 0 12px; font-size: .78rem; border-radius: 7px; display: inline-flex; align-items: center; gap: 4px; }

/* Save button */
#saveAttBtn { border-radius: 8px; padding: 0 22px; height: 38px; font-size: .875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }

/* Page header button */
#takeAttBtn { border-radius: 8px; padding: 0 18px; height: 38px; font-size: .875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 2px 8px rgba(13,110,253,.3); }
#takeAttBtn:hover { box-shadow: 0 4px 14px rgba(13,110,253,.35); }

/* Card headers */
.att-card-header {
    background: #fff !important;
    border-bottom: 1px solid #f0f0f0;
    padding: 1rem 1.25rem;
}
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ─────────────────────────────────── -->
    <div class="page-header fade-up mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-calendar-check-fill me-2 text-primary"></i>Attendance Management
                </h4>
                <p class="mb-0 text-muted" style="font-size:.875rem;">
                    Record and track daily attendance by module / course
                    <?= (!$isSA && $branchName) ? ' &mdash; <strong>' . htmlspecialchars($branchName) . '</strong>' : '' ?>
                </p>
            </div>
            <button class="btn btn-primary" id="takeAttBtn">
                <i class="bi bi-plus-circle-fill"></i> Take Attendance
            </button>
        </div>
    </div>

    <!-- ── Summary Cards ───────────────────────────────── -->
    <div class="row g-3 mb-4" id="summaryRow">
        <div class="col-6 col-md-3 fade-up">
            <div class="card border-0 shadow-sm h-100 att-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="att-stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-person-check-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold text-success lh-1 mb-1" id="cntPresent">0</div>
                        <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Present Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card border-0 shadow-sm h-100 att-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="att-stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-person-x-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold text-danger lh-1 mb-1" id="cntAbsent">0</div>
                        <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Absent Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card border-0 shadow-sm h-100 att-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="att-stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold text-warning lh-1 mb-1" id="cntLate">0</div>
                        <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Late Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card border-0 shadow-sm h-100 att-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="att-stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold text-info lh-1 mb-1" id="cntTotal">0</div>
                        <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Total Records</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Attendance History Card ─────────────────────── -->
    <div class="card border-0 shadow-sm fade-up">
        <div class="att-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-table me-2 text-primary"></i>Attendance History
            </h6>

            <!-- Filter Bar -->
            <div class="att-filter-bar">
                <?php if ($isSA): ?>
                <select id="filterBranchHist" class="form-select" style="width:155px;">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <select id="filterCourseHist" class="form-select" style="width:195px;">
                    <option value="">All Courses</option>
                </select>

                <div class="d-flex align-items-center gap-1" style="background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:2px 10px;height:36px;">
                    <i class="bi bi-calendar3 text-muted" style="font-size:.8rem;"></i>
                    <input type="date" id="filterFrom" class="form-control form-control-sm border-0 p-0 shadow-none" style="width:115px;font-size:.825rem;" value="<?= date('Y-m-01') ?>">
                    <span class="text-muted mx-1" style="font-size:.75rem;">–</span>
                    <input type="date" id="filterTo" class="form-control form-control-sm border-0 p-0 shadow-none" style="width:115px;font-size:.825rem;" value="<?= date('Y-m-d') ?>">
                </div>

                <button class="btn btn-primary" id="loadHistBtn">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100 mb-0" id="attendanceTable">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:110px;">Date</th>
                            <th>Student</th>
                            <th style="width:130px;">Student ID</th>
                            <th style="width:110px;">Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<!-- ════════════════════════════════════════════════════════
     TAKE ATTENDANCE MODAL
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="takeAttModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-plus-fill"></i> Take Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <!-- Selection row -->
                <div class="att-modal-select-card">
                    <div class="row g-3 align-items-end">
                        <?php if ($isSA): ?>
                        <div class="col-md-3">
                            <label>Branch <span class="text-danger">*</span></label>
                            <select id="attBranch" class="form-select">
                                <option value="">Select Branch…</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="<?= $isSA ? 'col-md-4' : 'col-md-6' ?>">
                            <label>Course / Module <span class="text-danger">*</span></label>
                            <select id="attCourse" class="form-select">
                                <option value="">Select Course…</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>Date <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="date" id="attDate" class="form-control"
                                       value="<?= date('Y-m-d') ?>"
                                       max="<?= date('Y-m-d') ?>">
                                <button class="btn btn-primary flex-shrink-0" id="loadStudentsBtn" title="Load students">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roster toolbar (hidden until students load) -->
                <div id="attendanceToolbar" class="att-roster-toolbar" style="display:none!important;">
                    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-people-fill text-primary"></i>
                        Student Roster
                    </h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-success att-bulk-btn" onclick="markAll('Present')">
                            <i class="bi bi-check2-all"></i> All Present
                        </button>
                        <button class="btn btn-sm btn-danger att-bulk-btn" onclick="markAll('Absent')">
                            <i class="bi bi-x-lg"></i> All Absent
                        </button>
                    </div>
                </div>

                <!-- Roster / placeholder -->
                <div id="studentAttRows" class="att-placeholder">
                    <i class="bi bi-search"></i>
                    <p class="mb-0" style="font-size:.875rem;">
                        Select a course and click the
                        <strong class="text-primary">search button</strong>
                        to load the student roster.
                    </p>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAttBtn" disabled>
                    <i class="bi bi-floppy-fill"></i> Save Attendance
                </button>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>

<script>
const ATT_API  = 'models/api/attendance_api.php';
const isSA     = <?= $isSA ? 'true' : 'false' ?>;
const todayISO = <?= json_encode(date('Y-m-d')) ?>;
let attTable;

/* ── Helper: initials avatar ───────────────────────── */
function attInitials(name) {
    return String(name || '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
}

$(document).ready(function () {

    // ── 1. Summary & DataTable ──────────────────────────
    loadSummary();

    attTable = $('#attendanceTable').DataTable({
        data: [],
        columns: [
            { data: null,         render: (d, t, r, m) => `<span class="text-muted">${m.row + 1}</span>` },
            { data: 'attend_date',render: d => `<span class="att-date-col">${d || '—'}</span>` },
            { data: 'student_name',render: d => `<span class="att-student-name">${escHtml(d)}</span>` },
            { data: 'student_code',render: d => `<span class="att-id-badge">${escHtml(d)}</span>` },
            { data: 'status',      render: statusBadge },
            { data: 'notes',       render: d => `<span class="text-muted">${escHtml(d || '—')}</span>` }
        ],
        responsive: true,
        language: { emptyTable: 'No records found. Adjust your filters and click Filter.' }
    });

    // ── 2. Dropdowns ────────────────────────────────────
    if (isSA) {
        $('#attBranch').on('change', function () { loadCourses('attCourse', $(this).val(), false); resetRoster(); });
        $('#filterBranchHist').on('change', function () { loadCourses('filterCourseHist', $(this).val(), true); });
        loadCourses('attCourse', '', false);
        loadCourses('filterCourseHist', '', true);
    } else {
        loadCourses('attCourse', '', false);
        loadCourses('filterCourseHist', '', true);
    }

    $('#attCourse, #attDate').on('change', resetRoster);

    // ── 3. Open modal ────────────────────────────────────
    $('#takeAttBtn').on('click', function () {
        resetRoster();
        const modalEl = document.getElementById('takeAttModal');
        let m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        m.show();
    });

    // ── 4. Load students ─────────────────────────────────
    $('#loadStudentsBtn').on('click', function () {
        const courseId = $('#attCourse').val();
        const date     = $('#attDate').val();

        if (!courseId || !date) {
            Swal.fire('Missing Information', 'Please select a course and date first.', 'warning'); return;
        }
        if (date > todayISO) {
            Swal.fire('Invalid Date', 'You cannot take attendance for a future date.', 'error'); return;
        }

        const btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.getJSON(ATT_API + `?action=course_students&course_id=${courseId}&date=${date}`, function (res) {
            const rows = res.data || [];
            if (!rows.length) {
                $('#studentAttRows').html(`
                    <div class="att-placeholder">
                        <i class="bi bi-exclamation-triangle"></i>
                        <p class="mb-0" style="font-size:.875rem;">No active enrollments found for this course.</p>
                    </div>`);
                return;
            }

            const statusOpts = ['Present','Absent','Late','Excused'].map(s =>
                `<option value="${s}">${s}</option>`
            ).join('');

            let html = `<div class="att-roster-card">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID Number</th>
                            <th style="width:150px;">Status</th>
                            <th>Instructor Notes</th>
                        </tr>
                    </thead>
                    <tbody>`;

            rows.forEach(function (r) {
                const initials = attInitials(r.student_name);
                const selOpts  = ['Present','Absent','Late','Excused'].map(s =>
                    `<option value="${s}" ${r.att_status === s ? 'selected' : ''}>${s}</option>`
                ).join('');

                html += `<tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="att-avatar">${initials}</div>
                            <span class="fw-semibold">${escHtml(r.student_name)}</span>
                        </div>
                    </td>
                    <td><span class="att-id-badge">${escHtml(r.student_code)}</span></td>
                    <td>
                        <input type="hidden" class="att-student-id" value="${r.student_id}">
                        <select class="form-select att-status att-status-select">${selOpts}</select>
                    </td>
                    <td>
                        <input type="text" class="form-control att-notes att-notes-input"
                               placeholder="Add a note…"
                               value="${escHtml(r.notes || '')}">
                    </td>
                </tr>`;
            });

            html += `</tbody></table></div>`;

            $('#studentAttRows').html(html);
            $('#attendanceToolbar').show();
            $('#saveAttBtn').prop('disabled', false);

        }).fail(function (xhr) {
            let msg = 'Failed to communicate with the API.';
            try { const p = JSON.parse(xhr.responseText || '{}'); if (p.message) msg = p.message; } catch(e){}
            Swal.fire('Server Error', msg, 'error');
            resetRoster();
        }).always(function () {
            btn.prop('disabled', false).html('<i class="bi bi-search"></i>');
        });
    });

    // ── 5. Save attendance ───────────────────────────────
    $('#saveAttBtn').on('click', function () {
        const courseId = $('#attCourse').val();
        const date     = $('#attDate').val();
        const branchId = isSA ? $('#attBranch').val() : '';

        let formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('attend_date', date);
        if (branchId) formData.append('branch_id', branchId);

        let count = 0;
        $('.att-student-id').each(function (i) {
            formData.append(`records[${i}][student_id]`, $(this).val());
            formData.append(`records[${i}][status]`, $('.att-status').eq(i).val());
            formData.append(`records[${i}][notes]`, $('.att-notes').eq(i).val());
            count++;
        });

        if (!count) { Swal.fire('No Data', 'Please load the students first.', 'warning'); return; }

        const btn = $(this);
        const orig = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving…');

        $.ajax({
            url: ATT_API + '?action=save',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Saved!', res.message || 'Attendance saved successfully.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('takeAttModal'))?.hide();
                    loadSummary();
                    loadHistory();
                } else {
                    Swal.fire('Save Failed', res.message || 'The server rejected the data.', 'error');
                }
            },
            error: function (xhr) {
                let msg = 'Check the browser console for details.';
                try { const p = JSON.parse(xhr.responseText || '{}'); if (p.message) msg = p.message; } catch(e){}
                Swal.fire('Server Error', msg, 'error');
            },
            complete: function () { btn.prop('disabled', false).html(orig); }
        });
    });

    // ── 6. Filter history ────────────────────────────────
    $('#loadHistBtn').on('click', loadHistory);

    function loadHistory() {
        const courseId = $('#filterCourseHist').val();
        const from     = $('#filterFrom').val();
        const to       = $('#filterTo').val();
        const bid      = isSA ? ($('#filterBranchHist').val() || '') : '';
        const btn      = $('#loadHistBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.getJSON(ATT_API + `?action=list&course_id=${courseId}&date_from=${from}&date_to=${to}&branch_id=${bid}`,
            function (res) { attTable.clear().rows.add(res.data || []).draw(); }
        ).always(function () {
            btn.prop('disabled', false).html('<i class="bi bi-funnel-fill"></i> Filter');
        });
    }

    // ── Utilities ─────────────────────────────────────────
    function resetRoster() {
        $('#studentAttRows').html(`
            <div class="att-placeholder">
                <i class="bi bi-search"></i>
                <p class="mb-0" style="font-size:.875rem;">
                    Select a course and click the
                    <strong class="text-primary">search button</strong>
                    to load the student roster.
                </p>
            </div>`);
        $('#attendanceToolbar').hide();
        $('#saveAttBtn').prop('disabled', true);
    }

    function loadCourses(selectId, branchOverride, includeAll) {
        const bid = branchOverride !== undefined ? branchOverride : '';
        $.getJSON(`${ATT_API}?action=courses_with_students&branch_id=${encodeURIComponent(bid)}`, function (res) {
            const placeholder = includeAll ? 'All Courses' : 'Select Course…';
            const sel = $(`#${selectId}`).empty().append(`<option value="">${placeholder}</option>`);
            const rows = res.data || [];
            if (!rows.length && !includeAll) {
                sel.empty().append('<option value="">No active courses</option>');
                return;
            }
            rows.forEach(c => sel.append(`<option value="${c.id}">${escHtml(c.course_name)}</option>`));
        }).fail(function () {
            $(`#${selectId}`).empty().append('<option value="">Error loading courses</option>');
        });
    }

    function loadSummary() {
        $.getJSON(ATT_API + '?action=summary', function (res) {
            $('#cntPresent').text(res.present || 0);
            $('#cntAbsent').text(res.absent   || 0);
            $('#cntLate').text(res.late       || 0);
            $('#cntTotal').text(res.total     || 0);
        });
    }

    function statusBadge(s) {
        if (!s) return '—';
        return `<span class="att-${s.toLowerCase()}">${escHtml(s)}</span>`;
    }
});

function markAll(status) { $('.att-status').val(status); }

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>