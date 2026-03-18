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
$pageTitle = 'Competency Matrix';
$activePage = 'instructor_competency_matrix.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Competency Matrix</h3>
            <p class="text-muted mb-0">Track student trade skills using CBT statuses: NYC, Competent, Distinction.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" id="submitSignoffBtn"><i class="bi bi-shield-check me-1"></i>Request Admin Sign-off</button>
            <button class="btn btn-primary" id="saveMatrixBtn"><i class="bi bi-save me-1"></i>Save Matrix</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Course / Class</label>
                    <select id="batchSelect" class="form-select"></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Trade Module</label>
                    <input type="text" id="moduleName" class="form-control" value="Core Module" maxlength="150">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100" id="loadMatrixBtn"><i class="bi bi-arrow-repeat me-1"></i>Load Matrix</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white">
            <h6 class="mb-0 fw-bold">Student Skill Grid</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="matrixTable">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small mb-0">
        <strong>Status guide:</strong>
        <span class="badge bg-danger ms-1">NYC</span>
        <span class="badge bg-success ms-2">C</span>
        <span class="badge bg-primary ms-2">Distinction</span>
    </div>

    <div class="card shadow-sm border-0 mt-3">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Admin Sign-off Status</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="signoffTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Student</th>
                            <th>Requested Status</th>
                            <th>Approval Status</th>
                            <th>Reason</th>
                            <th>Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5" class="text-center text-muted py-3">Load a course to view sign-off status.</td></tr>
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
let currentSkills = [];
let currentStudents = [];

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

function renderMatrix(students, skills, matrix) {
    currentSkills = skills;
    currentStudents = students;

    const thead = $('#matrixTable thead');
    const tbody = $('#matrixTable tbody');
    thead.empty();
    tbody.empty();

    let h = '<tr><th class="ps-3">Student</th>';
    skills.forEach(s => h += `<th>${esc(s)}</th>`);
    h += '</tr>';
    thead.html(h);

    if (!students.length) {
        tbody.html('<tr><td colspan="99" class="text-center text-muted py-4">No students in this course.</td></tr>');
        return;
    }

    students.forEach(st => {
        let r = `<tr><td class="ps-3"><div class="fw-semibold">${esc(st.name)}</div><div class="small text-muted">${esc(st.student_id)}</div></td>`;
        skills.forEach(skill => {
            const val = (matrix?.[st.id]?.[skill]) || 'NYC';
            r += `<td>
                <select class="form-select form-select-sm comp-select" data-student="${st.id}" data-skill="${esc(skill)}">
                    <option value="NYC" ${val === 'NYC' ? 'selected' : ''}>NYC</option>
                    <option value="C" ${val === 'C' ? 'selected' : ''}>C</option>
                    <option value="Distinction" ${val === 'Distinction' ? 'selected' : ''}>Distinction</option>
                </select>
            </td>`;
        });
        r += '</tr>';
        tbody.append(r);
    });
}

function loadMatrix() {
    const courseId = $('#batchSelect').val();
    const moduleName = $('#moduleName').val().trim() || 'Core Module';
    if (!courseId) {
        Swal.fire('Select course', 'Please choose a course first.', 'warning');
        return;
    }

    $.getJSON('models/api/instructor_panel_api.php', {
        action: 'competency_matrix',
        course_id: courseId,
        module_name: moduleName
    }, function(res){
        if (!res.success) {
            Swal.fire('Error', res.message || 'Failed to load matrix.', 'error');
            return;
        }
        renderMatrix(res.data.students || [], res.data.skills || [], res.data.matrix || {});
        loadSignoffs();
    });
}

function signoffBadge(s) {
    const map = {
        Pending: ['bg-warning text-dark', 'hourglass-split'],
        Approved: ['bg-success', 'check-circle-fill'],
        Rejected: ['bg-danger', 'x-circle-fill']
    };
    const p = map[s] || ['bg-secondary', 'dash'];
    return `<span class="badge ${p[0]}"><i class="bi bi-${p[1]} me-1"></i>${esc(s)}</span>`;
}

function loadSignoffs() {
    const courseId = $('#batchSelect').val();
    const moduleName = $('#moduleName').val().trim() || 'Core Module';
    if (!courseId) return;

    $.getJSON('models/api/instructor_panel_api.php', {
        action: 'list_competency_signoffs',
        course_id: courseId,
        module_name: moduleName
    }, function(res){
        const tbody = $('#signoffTable tbody');
        tbody.empty();
        if (!res.success || !res.data.length) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No sign-off requests yet.</td></tr>');
            return;
        }
        res.data.forEach(r => {
            tbody.append(`<tr>
                <td class="ps-3"><div class="fw-semibold">${esc(r.student_name)}</div><div class="small text-muted">${esc(r.student_code)}</div></td>
                <td>${esc(r.requested_status)}</td>
                <td>${signoffBadge(r.status)}</td>
                <td class="small ${r.status === 'Rejected' ? 'text-danger' : 'text-muted'}">${esc(r.rejection_reason || '—')}</td>
                <td class="small text-muted">${esc(r.created_at)}</td>
            </tr>`);
        });
    });
}

function saveMatrix() {
    const courseId = $('#batchSelect').val();
    const moduleName = $('#moduleName').val().trim() || 'Core Module';
    if (!courseId) {
        Swal.fire('Select course', 'Please choose a course first.', 'warning');
        return;
    }

    const records = [];
    $('.comp-select').each(function(){
        records.push({
            student_id: Number($(this).data('student')),
            skill_name: String($(this).data('skill')),
            status: $(this).val()
        });
    });

    $.post('models/api/instructor_panel_api.php?action=save_competency', {
        csrf_token: csrfToken,
        course_id: courseId,
        module_name: moduleName,
        records: JSON.stringify(records)
    }, function(res){
        if (res.success) {
            Swal.fire('Saved', res.message || 'Competency matrix updated.', 'success');
        } else {
            Swal.fire('Error', res.message || 'Save failed.', 'error');
        }
    }, 'json');
}

function submitSignoff() {
    const courseId = $('#batchSelect').val();
    const moduleName = $('#moduleName').val().trim() || 'Core Module';
    if (!courseId) {
        Swal.fire('Select course', 'Please choose a course first.', 'warning');
        return;
    }

    $.post('models/api/instructor_panel_api.php?action=submit_competency_signoff', {
        csrf_token: csrfToken,
        course_id: courseId,
        module_name: moduleName
    }, function(res){
        if (res.success) {
            Swal.fire('Submitted', res.message || 'Sign-off request sent.', 'success');
            loadSignoffs();
        } else {
            Swal.fire('Error', res.message || 'Submit failed.', 'error');
        }
    }, 'json');
}

$(function(){
    loadCourses();
    $('#loadMatrixBtn').on('click', loadMatrix);
    $('#saveMatrixBtn').on('click', saveMatrix);
    $('#submitSignoffBtn').on('click', submitSignoff);
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
