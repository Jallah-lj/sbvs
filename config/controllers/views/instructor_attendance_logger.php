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
$pageTitle = 'Attendance Logger';
$activePage = 'instructor_attendance_logger.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-stopwatch-fill text-primary me-2"></i>Attendance Logger</h3>
            <p class="text-muted mb-0">Fast entry for classroom and workshop/lab hours.</p>
        </div>
        <button class="btn btn-primary" id="saveAttendanceBtn"><i class="bi bi-save me-1"></i>Save Hours</button>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Course / Class</label>
                    <select id="batchSelect" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" id="sessionDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Session Type</label>
                    <select id="sessionType" class="form-select">
                        <option value="Classroom">Classroom</option>
                        <option value="Workshop">Workshop</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" id="loadAttendanceBtn"><i class="bi bi-arrow-repeat me-1"></i>Load</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Hours Entry Table</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="attendanceTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Student</th>
                            <th>Classroom Hours</th>
                            <th>Workshop/Lab Hours</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="text-center text-muted py-4">Load a course to start logging hours.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const csrfToken = <?= json_encode($csrfToken) ?>;

function esc(v){ return $('<div>').text(v ?? '').html(); }

function loadCourses() {
    $.getJSON('models/api/instructor_panel_api.php?action=courses', function(res){
        const sel = $('#batchSelect');
        sel.empty();
        if (!res.success || !res.data.length) {
            sel.append('<option value="">No courses available</option>');
            return;
        }
        sel.append('<option value="">Select course...</option>');
        res.data.forEach(c => {
            sel.append(`<option value="${c.id}">${esc(c.course_name)} • ${esc(c.batch_name)} (${esc(c.status)})</option>`);
        });
    });
}

function renderRows(students, logs) {
    const tbody = $('#attendanceTable tbody');
    tbody.empty();

    if (!students.length) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">No students in this cohort.</td></tr>');
        return;
    }

    students.forEach(s => {
        const log = logs?.[s.id] || {};
        tbody.append(`
            <tr>
                <td class="ps-3">
                    <div class="fw-semibold">${esc(s.name)}</div>
                    <div class="small text-muted">${esc(s.student_id)}</div>
                </td>
                <td><input type="number" min="0" step="0.25" class="form-control form-control-sm att-ch" data-student="${s.id}" value="${Number(log.classroom_hours || 0)}"></td>
                <td><input type="number" min="0" step="0.25" class="form-control form-control-sm att-wh" data-student="${s.id}" value="${Number(log.workshop_hours || 0)}"></td>
                <td><input type="text" class="form-control form-control-sm att-notes" data-student="${s.id}" value="${esc(log.notes || '')}" maxlength="255"></td>
            </tr>
        `);
    });
}

function loadAttendance() {
    const courseId = $('#batchSelect').val();
    if (!courseId) {
        Swal.fire('Select course', 'Please choose a course first.', 'warning');
        return;
    }

    $.getJSON('models/api/instructor_panel_api.php', {
        action: 'attendance_load',
        course_id: courseId,
        session_date: $('#sessionDate').val(),
        session_type: $('#sessionType').val()
    }, function(res){
        if (!res.success) {
            Swal.fire('Error', res.message || 'Failed to load attendance.', 'error');
            return;
        }
        renderRows(res.data.students || [], res.data.logs || {});
    });
}

function saveAttendance() {
    const courseId = $('#batchSelect').val();
    if (!courseId) {
        Swal.fire('Select course', 'Please choose a course first.', 'warning');
        return;
    }

    const entries = [];
    $('#attendanceTable tbody tr').each(function(){
        const s = Number($(this).find('.att-ch').data('student') || 0);
        if (!s) return;
        entries.push({
            student_id: s,
            classroom_hours: Number($(this).find('.att-ch').val() || 0),
            workshop_hours: Number($(this).find('.att-wh').val() || 0),
            notes: String($(this).find('.att-notes').val() || '')
        });
    });

    $.post('models/api/instructor_panel_api.php?action=attendance_save', {
        csrf_token: csrfToken,
        course_id: courseId,
        session_date: $('#sessionDate').val(),
        session_type: $('#sessionType').val(),
        entries: JSON.stringify(entries)
    }, function(res){
        if (res.success) {
            Swal.fire('Saved', res.message || 'Attendance logged successfully.', 'success');
        } else {
            Swal.fire('Error', res.message || 'Save failed.', 'error');
        }
    }, 'json');
}

$(function(){
    loadCourses();
    $('#loadAttendanceBtn').on('click', loadAttendance);
    $('#saveAttendanceBtn').on('click', saveAttendance);
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
