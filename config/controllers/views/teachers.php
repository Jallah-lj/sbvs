<?php
declare(strict_types=1);

ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../../database.php';
require_once '../../DashboardSecurity.php';
$db = (new Database())->getConnection();

$csrfToken = DashboardSecurity::generateToken();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');
$isTeacher     = ($role === 'Teacher');
$canManageTeachers = ($isSuperAdmin || $isBranchAdmin || $isAdmin);
$canViewTeachers = ($canManageTeachers || $isTeacher);

if (!$canViewTeachers) {
    header("Location: dashboard.php");
    exit;
}

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
$pageTitle  = 'Instructors Directory';
$activePage = 'teachers.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
    /* ── Academic & Faculty Theme ── */
    :root {
        --faculty-primary: #4f46e5;
        --faculty-light: #e0e7ff;
        --faculty-accent: #0ea5e9;
        --faculty-surface: #ffffff;
    }

    .faculty-header {
        background: linear-gradient(120deg, var(--faculty-primary) 0%, #3730a3 100%);
        color: #fff;
        border-radius: 16px;
        padding: 2.5rem 2rem;
        box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.3);
        position: relative;
        overflow: hidden;
    }
    .faculty-header::after {
        content: '\F3C4'; /* bi-mortarboard */
        font-family: 'bootstrap-icons';
        position: absolute;
        right: -20px;
        bottom: -40px;
        font-size: 12rem;
        color: rgba(255,255,255,0.05);
        transform: rotate(-15deg);
        pointer-events: none;
    }

    .stat-card.academic {
        background: var(--faculty-surface);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .stat-card.academic:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px -5px rgba(0,0,0,0.08);
        border-color: var(--faculty-light);
    }
    .academic-icon-ring {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        position: relative;
    }
    .academic-icon-ring::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 1px dashed currentColor;
        opacity: 0.3;
        animation: spin-slow 10s linear infinite;
    }
    @keyframes spin-slow { 100% { transform: rotate(360deg); } }

    .table-faculty {
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    .table-faculty thead th {
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding-bottom: 0.5rem;
    }
    .table-faculty tbody tr {
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .table-faculty tbody tr:hover {
        transform: scale(1.002);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        z-index: 10;
        position: relative;
    }
    .table-faculty tbody td {
        border: none;
        padding: 1.25rem 1rem;
        vertical-align: middle;
    }
    .table-faculty tbody td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .table-faculty tbody td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

    .modal-faculty .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
    }
    .modal-faculty .modal-header {
        background: var(--faculty-primary);
        color: white;
        border: none;
        padding: 1.5rem 2rem;
    }
    .modal-faculty .modal-header .btn-close { filter: invert(1) brightness(100%); }

    /* Clean form inputs */
    .form-control-academic, .form-select-academic {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s;
    }
    .form-control-academic:focus, .form-select-academic:focus {
        background: #fff;
        border-color: var(--faculty-primary);
        box-shadow: 0 0 0 4px var(--faculty-light);
    }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main p-4">
        
        <!-- ── Top Header Section ── -->
        <div class="faculty-header d-flex flex-wrap justify-content-between align-items-center gap-4 mb-5 fade-up">
            <div style="z-index:1;">
                <h2 class="fw-bolder mb-1 text-white" style="letter-spacing: -1px;"><i class="bi bi-mortarboard-fill me-2"></i>Faculty Directory</h2>
                <p class="mb-0 text-white-50 fs-6">
                    <?= $canManageTeachers ? 'Manage pedagogical experts, course assignments, and faculty credentials.' : 'Browse active faculty members and specializations.' ?>
                </p>
                <?php if (!$isSuperAdmin && $branchName): ?>
                    <div class="mt-3">
                        <span class="badge bg-white text-primary px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($branchName) ?> Campus</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($canManageTeachers): ?>
            <div style="z-index:1;">
                <button class="btn btn-light text-primary fw-bold px-4 py-2 rounded-pill shadow" onclick="InstructorManager.initiateAdd()">
                    <i class="bi bi-person-plus-fill me-2"></i> Onboard Instructor
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isTeacher): ?>
        <div class="alert alert-primary bg-primary bg-opacity-10 border-0 rounded-4 mb-4 d-flex align-items-center shadow-sm fade-up">
            <i class="bi bi-journal-text fs-4 text-primary me-3"></i>
            <div>
                <span class="fw-bold text-primary d-block">Faculty Read-Only Access</span>
                <span class="small text-muted">You are viewing the peer directory for your designated branch.</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManageTeachers): ?>
        <!-- ── Faculty Analytics ── -->
        <div class="row g-4 mb-5">
            <div class="col-6 col-xl-3 fade-up" style="animation-delay: 50ms;">
                <div class="stat-card academic h-100 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="academic-icon-ring bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Total Faculty</div>
                            <div class="fs-3 fw-bolder text-dark lh-1"><?= (int)$stats['total'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 fade-up" style="animation-delay: 100ms;">
                <div class="stat-card academic h-100 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="academic-icon-ring bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Active Roster</div>
                            <div class="fs-3 fw-bolder text-dark lh-1"><?= (int)$stats['active'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 fade-up" style="animation-delay: 150ms;">
                <div class="stat-card academic h-100 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="academic-icon-ring bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">On Leave / Inactive</div>
                            <div class="fs-3 fw-bolder text-dark lh-1"><?= (int)$stats['inactive'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 fade-up" style="animation-delay: 200ms;">
                <div class="stat-card academic h-100 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="academic-icon-ring bg-info bg-opacity-10 text-info">
                            <i class="bi bi-book-half"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Course Disciplines</div>
                            <div class="fs-3 fw-bolder text-dark lh-1"><?= (int)$stats['specializations'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Top Specialization Banner ── -->
        <?php if ($canManageTeachers && $topSpec): ?>
        <div class="card border-0 bg-white rounded-4 shadow-sm mb-4 fade-up" style="animation-delay: 250ms;">
            <div class="card-body p-3 px-4 d-flex align-items-center flex-wrap gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px; height:45px;">
                    <i class="bi bi-star-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">Dominant Pedagogy: <span class="text-primary ms-1"><?= htmlspecialchars($topSpec['specialization']) ?></span></h6>
                    <small class="text-muted">This specialization carries the highest volume of registered instructors (<span class="fw-bold"><?= (int)$topSpec['cnt'] ?></span>).</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Master Roster Table ── -->
        <div class="fade-up" style="animation-delay: 300ms;">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-collection-fill text-muted me-2"></i>Active Roster</h5>
                <?php if ($isSuperAdmin): ?>
                <div style="width: 250px;">
                    <div class="input-group input-group-sm shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-building"></i></span>
                        <select id="branchFilter" class="form-select border-start-0 bg-white">
                            <option value="">Global Network (All Branches)</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive" style="min-height: 400px;">
                <table class="table table-faculty w-100 mt-2" id="teacherTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Identity</th>
                            <th>Contact Vector</th>
                            <th>Specialization</th>
                            <th>Campus/Branch</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Controls</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php if ($canManageTeachers): ?>
<!-- ── Add Instructor Modal ── -->
<div class="modal fade modal-faculty" id="addTeacherModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="addTeacherForm" class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-person-fill-add me-2 text-white-50"></i>Faculty Onboarding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-md-5 bg-white">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">Personal Details</h6>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Legal Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-academic" placeholder="e.g. Dr. Jane Smith" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Institute Photo <span class="text-muted fw-normal">(Optional)</span></label>
                        <input type="file" name="photo" id="addPhoto" class="form-control form-control-academic" accept="image/jpeg,image/png,image/webp">
                        <div class="mt-2 text-center d-none" id="addPhotoPreviewWrap">
                            <img id="addPhotoPreview" src="" alt="Preview" class="rounded-circle shadow-sm mt-1" style="width:60px; height:60px; object-fit:cover; border: 2px solid var(--faculty-light);">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Official Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-academic" placeholder="faculty@institute.edu" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control form-control-academic" placeholder="e.g. 0770000000" required>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">Academic Placement</h6>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Core Discipline <span class="text-danger">*</span></label>
                        <select name="specialization" class="form-select form-select-academic" required>
                            <option value="">-- Choose Module --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Campus Assignment <span class="text-danger">*</span></label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" class="form-select form-select-academic" required>
                            <option value="">-- Select Campus --</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control form-control-academic fw-medium text-primary" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 border-top-0 d-flex justify-content-between">
                <button type="button" class="btn btn-light border text-muted fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill shadow" id="saveBtn">
                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> Deploy Faculty
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Instructor Modal ── -->
<div class="modal fade modal-faculty" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="editTeacherForm" class="modal-content shadow-lg">
            <div class="modal-header" style="background:#0284c7;">
                <h5 class="modal-title fw-bold mb-0 text-white"><i class="bi bi-pencil-square me-2 text-white-50"></i>Update Faculty Matrix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-md-5 bg-white">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-academic" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="w-100">
                            <label class="form-label fw-bold text-dark small">Update Photo</label>
                            <input type="file" name="photo" id="editPhoto" class="form-control form-control-academic" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="ms-3 rounded-circle shadow-sm border border-2 border-white" style="width:48px;height:48px;overflow:hidden;flex-shrink:0;">
                            <img id="editPhotoPreview" src="" class="d-none w-100 h-100" style="object-fit:cover;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control form-control-academic" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark small">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control form-control-academic" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark small">Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-academic fw-bold">
                            <option value="Active" class="text-success">Active</option>
                            <option value="Inactive" class="text-danger">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark small">Specialization</label>
                        <select name="specialization" id="edit_specialization" class="form-select form-select-academic">
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark small">Campus</label>
                        <?php if ($isSuperAdmin): ?>
                        <select name="branch_id" id="edit_branch_id" class="form-select form-select-academic">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control form-control-academic text-muted" value="<?= htmlspecialchars($branchName) ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $sessionBranch ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 border-top-0 d-flex justify-content-between">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none" data-bs-dismiss="modal">Discard</button>
                <button type="submit" class="btn btn-info text-white fw-bold px-4 rounded-pill shadow" id="updateBtn">
                    <i class="bi bi-check2-circle me-2"></i> Save Matrix
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const InstructorManager = (() => {
    const API = 'models/api/teacher_api.php';
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const baseAppUrl = <?= json_encode(rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/') ?>;
    
    // Permissions
    const canManage = <?= json_encode($canManageTeachers) ?>;
    const isSuper   = <?= json_encode($isSuperAdmin) ?>;
    
    let table = null;

    const sanitize = (str) => {
        if (!str) return '';
        const t = document.createElement('div');
        t.textContent = String(str);
        return t.innerHTML;
    };

    const getPhotoUrl = (path) => path ? baseAppUrl + String(path).replace(/^\/+/, '') : null;
    
    const initTable = () => {
        if(table) table.destroy();
        table = $('#teacherTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: API + '?action=list',
                data: (d) => { d.branch_id = $('#branchFilter').val() || ''; },
                dataSrc: (res) => res.data || []
            },
            columns: [
                {
                    data: null, className: 'ps-3',
                    render: (data) => {
                        const sName = sanitize(data.name);
                        const sId   = sanitize(data.teacher_id || 'PENDING');
                        const pUrl  = getPhotoUrl(data.photo_url);
                        
                        let imgHtml = pUrl 
                            ? `<img src="${pUrl}" class="rounded-circle shadow-sm" style="width:45px;height:45px;object-fit:cover;">`
                            : `<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:45px;height:45px;font-size:1.1rem;">${sName.charAt(0).toUpperCase()}</div>`;
                            
                        return `
                            <div class="d-flex align-items-center gap-3">
                                ${imgHtml}
                                <div>
                                    <div class="fw-bolder text-dark mb-0" style="font-size:1.05rem;">${sName}</div>
                                    <div class="text-muted small fw-medium" style="letter-spacing:0.5px;">${sId}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                {
                    data: null,
                    render: (data) => `
                        <div class="d-flex flex-column gap-1">
                            <span class="text-secondary fw-medium"><i class="bi bi-envelope-fill me-2 opacity-50"></i>${sanitize(data.email)}</span>
                            <span class="text-secondary small"><i class="bi bi-telephone-fill me-2 opacity-50"></i>${sanitize(data.phone || 'N/A')}</span>
                        </div>
                    `
                },
                { 
                    data: 'specialization', 
                    render: (d) => `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-journal-check me-1"></i> ${sanitize(d)}</span>`
                },
                {
                    data: 'branch_name',
                    render: (d) => `<div class="text-dark fw-semibold"><i class="bi bi-geo-alt-fill text-muted me-1"></i> ${sanitize(d)}</div>`
                },
                {
                    data: 'status',
                    render: (d) => d === 'Active' 
                        ? `<span class="text-success fw-bold"><i class="bi bi-circle-fill me-1 small"></i>Active</span>`
                        : `<span class="text-danger fw-bold"><i class="bi bi-dash-circle-fill me-1 small"></i>Inactive</span>`
                },
                {
                    data: null, orderable: false, className: 'text-end pe-3',
                    render: (data) => {
                        const safePayload = btoa(encodeURIComponent(JSON.stringify(data)));
                        let html = `<div class="d-flex justify-content-end gap-2">`;
                        
                        // Always allow view profile / ID
                        html += `
                            <a href="teacher_profile.php?id=${data.id}" class="btn btn-sm btn-light border shadow-sm text-primary" title="Profile">
                                <i class="bi bi-person-bounding-box"></i>
                            </a>
                            <a href="generate_id.php?type=teacher&id=${data.id}" target="_blank" class="btn btn-sm btn-light border shadow-sm text-secondary" title="ID Card">
                                <i class="bi bi-upc-scan"></i>
                            </a>
                        `;
                        
                        if(canManage) {
                            html += `
                                <button class="btn btn-sm btn-light border shadow-sm text-warning" onclick="InstructorManager.editPrompt('${safePayload}')" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-light border shadow-sm text-danger" onclick="InstructorManager.deactivateTarget(${data.id}, '${sanitize(data.name).replace(/'/g,"\\'")}')" title="Toggle Access">
                                    <i class="bi bi-shield-x"></i>
                                </button>
                            `;
                        }
                        html += `</div>`;
                        return html;
                    }
                }
            ],
            language: { emptyTable: "<div class='text-center py-5'><i class='bi bi-person-x text-muted fs-1 d-block mb-3'></i><h5 class='text-muted'>No faculty enrolled.</h5></div>" }
        });
    };

    const attachEvents = () => {
        $('#branchFilter').on('change', () => table.ajax.reload());

        // Add Photo Preview
        document.getElementById('addPhoto')?.addEventListener('change', function() {
            const wrap = document.getElementById('addPhotoPreviewWrap');
            const img = document.getElementById('addPhotoPreview');
            if(this.files && this.files[0]) {
                img.src = URL.createObjectURL(this.files[0]);
                wrap.classList.remove('d-none');
            } else {
                wrap.classList.add('d-none');
            }
        });

        // Edit Photo Preview
        document.getElementById('editPhoto')?.addEventListener('change', function() {
            const img = document.getElementById('editPhotoPreview');
            if(this.files && this.files[0]) {
                img.src = URL.createObjectURL(this.files[0]);
                img.classList.remove('d-none');
            }
        });

        // Add Form Submit (Fetch API)
        const addForm = document.getElementById('addTeacherForm');
        if(addForm) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('saveBtn');
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Processing...`;
                
                try {
                    const req = await fetch(API + '?action=create', { method: 'POST', body: new FormData(addForm) });
                    const res = await req.json();
                    
                    if(res.status === 'success') {
                        Swal.fire({
                            title: 'Faculty Deployed!',
                            html: `System assigned temporary cipher:<br> <code class="fs-4 d-block mt-2 p-2 bg-light border rounded">${res.default_password || 'Use Phone #'}</code>`,
                            icon: 'success'
                        });
                        bootstrap.Modal.getInstance(document.getElementById('addTeacherModal')).hide();
                        addForm.reset();
                        document.getElementById('addPhotoPreviewWrap').classList.add('d-none');
                        table.ajax.reload(null, false);
                    } else throw new Error(res.message);
                } catch(err) {
                    Swal.fire('Operation Halted', err.message || 'Network disruption during dispatch.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-cloud-arrow-up-fill me-2"></i> Deploy Faculty`;
                }
            });
        }

        // Edit Form Submit (Fetch API)
        const editForm = document.getElementById('editTeacherForm');
        if(editForm) {
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('updateBtn');
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Syncing...`;
                
                try {
                    const req = await fetch(API + '?action=update', { method: 'POST', body: new FormData(editForm) });
                    const res = await req.json();
                    
                    if(res.status === 'success') {
                        Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 3000})
                            .fire({icon: 'success', title: 'Faculty Matrix Updated'});
                        bootstrap.Modal.getInstance(document.getElementById('editTeacherModal')).hide();
                        table.ajax.reload(null, false);
                    } else throw new Error(res.message);
                } catch(err) {
                    Swal.fire('Sync Failed', err.message || 'Data rejection from core logic.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-check2-circle me-2"></i> Save Matrix`;
                }
            });
        }
    };

    return {
        init: () => {
            initTable();
            attachEvents();
        },
        initiateAdd: () => {
            document.getElementById('addTeacherForm')?.reset();
            document.getElementById('addPhotoPreviewWrap')?.classList.add('d-none');
            new bootstrap.Modal(document.getElementById('addTeacherModal')).show();
        },
        editPrompt: (base64Data) => {
            const data = JSON.parse(decodeURIComponent(atob(base64Data)));
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_specialization').value = data.specialization;
            if(isSuper) document.getElementById('edit_branch_id').value = data.branch_id;
            
            document.getElementById('editPhoto').value = '';
            const img = document.getElementById('editPhotoPreview');
            const pUrl = getPhotoUrl(data.photo_url);
            if(pUrl) {
                img.src = pUrl;
                img.classList.remove('d-none');
            } else {
                img.src = '';
                img.classList.add('d-none');
            }
            
            new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
        },
        deactivateTarget: async (id, name) => {
            const conf = await Swal.fire({
                title: 'Suspend Faculty Access?',
                html: `Are you sure you want to deactivate <strong>${name}</strong>? They will lose access to instructor portals immediately.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                confirmButtonText: 'Yes, Suspend'
            });
            if(!conf.isConfirmed) return;
            
            try {
                const fd = new URLSearchParams();
                fd.append('id', id);
                fd.append('csrf_token', csrfToken);
                
                const req = await fetch(API + '?action=delete', {
                    method: 'POST', 
                    body: fd,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                const res = await req.json();
                
                if(res.status === 'success') {
                    Swal.fire('Suspended', `Faculty member has been disconnected.`, 'success');
                    table.ajax.reload(null, false);
                } else throw new Error(res.message);
            } catch(e) {
                Swal.fire('Core Error', e.message || 'Failed to execute suspension protocol.', 'error');
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', InstructorManager.init);
</script>
</body>
</html>
