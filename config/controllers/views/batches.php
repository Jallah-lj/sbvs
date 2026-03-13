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
$branchId      = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    header("Location: dashboard.php");
    exit;
}

// Fetch branch name for header badge
$branchName = '';
if (!$isSuperAdmin && $branchId) {
    $bq = $db->prepare("SELECT name FROM branches WHERE id=?");
    $bq->execute([$branchId]);
    $branchName = $bq->fetchColumn() ?: '';
}

// Fetch all active branches for SA dropdown
$branches = [];
if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                   ->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch courses for the add modal (SA: need JS to filter by branch; BA/Admin: pre-load for their branch)
$initCourses = [];
if (!$isSuperAdmin && $branchId) {
    $cq = $db->prepare("SELECT id, name FROM courses WHERE branch_id = ? ORDER BY name");
    $cq->execute([$branchId]);
    $initCourses = $cq->fetchAll(PDO::FETCH_ASSOC);
}

$userName = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
$userRole = htmlspecialchars($role);
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Batches';
$activePage = 'batches.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><i class="bi bi-collection me-2 text-primary"></i>Batch Management</h2>
                <p class="text-muted small mb-0 mt-1">Organise students into course cohorts with defined start/end dates.</p>
                <?php if (!$isSuperAdmin && $branchName): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info mt-2 px-3 py-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($branchName) ?></span>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary shadow-sm rounded-pill px-4 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                <i class="bi bi-plus-circle-fill me-2"></i> New Batch
            </button>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="kpi-icon kpi-total"><i class="bi bi-collection"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-dark" style="letter-spacing: -0.02em;" id="statTotal">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Total Batches</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="kpi-icon kpi-active-icon"><i class="bi bi-play-circle"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-success" id="statActive">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Active</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="kpi-icon kpi-upcoming-icon"><i class="bi bi-clock"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-info" id="statUpcoming">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Upcoming</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-hover border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="kpi-icon kpi-completed-icon"><i class="bi bi-check-circle"></i></div>
                        <div>
                            <div class="fs-4 fw-bolder text-secondary" id="statCompleted">—</div>
                            <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1 fw-semibold text-muted">Filter by Branch</label>
                        <select id="fBranch" class="form-select border-0 shadow-sm bg-light">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1 fw-semibold text-muted">Status</label>
                        <select id="fStatus" class="form-select border-0 shadow-sm bg-light">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1 fw-semibold text-muted">Quick Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="fSearch" class="form-control border-0 bg-light shadow-sm" placeholder="Search batches...">
                            <button class="btn btn-primary shadow-sm" id="applyFilters" style="z-index: 0;">Search</button>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 text-md-end px-md-3">
                        <button class="btn btn-light shadow-sm w-100 w-md-auto text-muted" id="clearFilters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batches Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="batchesTable" class="table table-hover align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch Name</th>
                                <th>Course</th>
                                <?php if ($isSuperAdmin): ?><th>Branch</th><?php endif; ?>
                                <th>Timeline</th>
                                <th class="text-center">Enrolled</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batchesTableBody">
                            <tr><td colspan="<?= $isSuperAdmin ? 8 : 7 ?>" class="text-center text-muted py-5">
                                <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>Loading batches…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ══════════════════════ ADD BATCH MODAL ════════════════════════════════ -->
<div class="modal fade" id="addBatchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-primary border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2 opacity-75"></i>Add New Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4 position-relative">
                <div id="addAlert" class="alert d-none shadow-sm border-0"></div>
                <?php if ($isSuperAdmin): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-building text-muted"></i></span>
                        <select id="addBranch" class="form-select bg-light border-0 shadow-sm">
                            <option value="">— Select Branch —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Course <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-book text-muted"></i></span>
                        <select id="addCourse" class="form-select bg-light border-0 shadow-sm">
                            <option value="">— Select Course —</option>
                            <?php if (!$isSuperAdmin): ?>
                            <?php foreach ($initCourses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-text text-muted" id="addCourseHint"><?= $isSuperAdmin ? 'Select a branch first to load courses.' : '' ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Batch Name <span class="text-danger">*</span></label>
                    <input type="text" id="addName" class="form-control bg-light border-0 shadow-sm" placeholder="e.g. Batch A – Jan 2025">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" id="addStart" class="form-control bg-light border-0 shadow-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
                        <input type="date" id="addEnd" class="form-control bg-light border-0 shadow-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary shadow-sm" id="addBatchBtn">
                    <span id="addSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    <i class="bi bi-save me-1" id="addSaveIcon"></i>Save Batch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════ EDIT BATCH MODAL ═══════════════════════════════ -->
<div class="modal fade" id="editBatchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-warning border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 opacity-75"></i>Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4 position-relative">
                <div id="editAlert" class="alert d-none shadow-sm border-0"></div>
                <input type="hidden" id="editId">
                <?php if ($isSuperAdmin): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-building text-muted"></i></span>
                        <select id="editBranch" class="form-select bg-light border-0 shadow-sm">
                            <option value="">— Select Branch —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Course <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-book text-muted"></i></span>
                        <select id="editCourse" class="form-select bg-light border-0 shadow-sm">
                            <option value="">— Select Course —</option>
                            <?php if (!$isSuperAdmin): ?>
                            <?php foreach ($initCourses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Batch Name <span class="text-danger">*</span></label>
                    <input type="text" id="editName" class="form-control bg-light border-0 shadow-sm">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" id="editStart" class="form-control bg-light border-0 shadow-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
                        <input type="date" id="editEnd" class="form-control bg-light border-0 shadow-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning shadow-sm" id="editBatchBtn">
                    <span id="editSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    <i class="bi bi-save me-1" id="editSaveIcon"></i>Update Batch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
const API       = 'models/api/batch_api.php';
const IS_SA     = <?= $isSuperAdmin  ? 'true' : 'false' ?>;
const IS_BA     = <?= $isBranchAdmin ? 'true' : 'false' ?>;
const SESSION_BRANCH = <?= (int)$branchId ?>;

// ── Load batches and update KPI cards ──────────────────────────────────────
function loadBatches() {
    const params = new URLSearchParams({ action: 'list' });
    const bfVal  = IS_SA ? ($('#fBranch').val() || '') : '';
    if (IS_SA && bfVal)       params.set('branch_id',  bfVal);

    $.getJSON(API + '?' + params.toString(), function(res) {
        if (!res.success) return;
        const rows = res.data;

        // KPI counts
        const statuses = rows.reduce((acc, r) => {
            acc[r.status] = (acc[r.status] || 0) + 1; return acc;
        }, {});
        $('#statTotal').text(rows.length);
        $('#statActive').text(statuses['Active'] || 0);
        $('#statUpcoming').text(statuses['Upcoming'] || 0);
        $('#statCompleted').text(statuses['Completed'] || 0);

        // Apply client-side status filter
        const sf = $('#fStatus').val();
        const tf = ($('#fSearch').val() || '').toLowerCase();
        const filtered = rows.filter(r => {
            if (sf && r.status !== sf) return false;
            if (tf) {
                const hay = (r.name + r.course_name + (r.branch_name || '')).toLowerCase();
                if (!hay.includes(tf)) return false;
            }
            return true;
        });

        renderTable(filtered);
    });
}

function renderTable(rows) {
    const statusBadge = s => {
        const cls = { Active: 'badge-success d-inline-flex align-items-center', Upcoming: 'badge-info d-inline-flex align-items-center', Completed: 'badge-secondary d-inline-flex align-items-center' };
        const icons = { Active: '<i class="bi bi-check-circle-fill me-1"></i>', Upcoming: '<i class="bi bi-clock-fill me-1"></i>', Completed: '<i class="bi bi-archive-fill me-1"></i>'};
        return `<span class="badge badge-custom ${cls[s] || 'badge-secondary'}">${icons[s] || ''}${s}</span>`;
    };

    let html = '';
    rows.forEach((r, i) => {
        const branchCol = IS_SA ? `<td><span class="text-muted"><i class="bi bi-geo-alt me-1 text-secondary"></i>${escH(r.branch_name)}</span></td>` : '';
        html += `<tr>
            <td class="text-muted">${i+1}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-collection text-primary fs-5"></i>
                    </div>
                    <span class="fw-bold text-dark fs-6 text-nowrap">${escH(r.name)}</span>
                </div>
            </td>
            <td><span class="fw-semibold text-muted text-nowrap">${escH(r.course_name)}</span></td>
            ${branchCol}
            <td>
                <div class="d-flex flex-column">
                    <span class="small fw-semibold text-dark"><i class="bi bi-calendar2-minus me-1 text-muted"></i>${r.start_date || 'N/A'}</span>
                    <span class="small text-muted"><i class="bi bi-calendar2-check me-1"></i>${r.end_date || 'N/A'}</span>
                </div>
            </td>
            <td class="text-center">
                <div class="rounded bg-light text-dark fw-bold py-1 px-2 d-inline-block border">
                    <i class="bi bi-people-fill text-primary me-1"></i>${r.enrollment_count}
                </div>
            </td>
            <td class="text-center">${statusBadge(r.status)}</td>
            <td>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-action btn-edit"
                            onclick="openEdit(${r.id})" title="Edit">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-action btn-delete"
                            onclick="deleteBatch(${r.id}, '${escJs(r.name)}')" title="Delete">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    });

    if (!html) html = `<tr><td colspan="${IS_SA ? 8 : 7}" class="text-center text-muted py-5">
        <i class="bi bi-inbox opacity-50 display-4 d-block mb-3"></i>No batches match your filters.</td></tr>`;
    $('#batchesTableBody').html(html);
}

// ── Add batch ──────────────────────────────────────────────────────────────
function showAddAlert(msg, type='danger') {
    $('#addAlert').removeClass('d-none alert-danger alert-success').addClass('alert-'+type).html(msg);
}
function hideAddAlert() { $('#addAlert').addClass('d-none'); }

$('#addBatchModal').on('show.bs.modal', function() {
    hideAddAlert();
    $('#addName, #addStart, #addEnd').val('');
    if (IS_SA) { $('#addBranch').val(''); $('#addCourse').html('<option value="">— Select Course —</option>'); }
    else        { $('#addCourse').val(''); }
});

// SA: Load courses when branch changes in Add modal
$('#addBranch').on('change', function() {
    const bid = $(this).val();
    $('#addCourse').html('<option value="">— Loading… —</option>');
    if (!bid) { $('#addCourse').html('<option value="">— Select Course —</option>'); return; }
    $.getJSON(API + '?action=courses_by_branch&branch_id=' + bid, function(res) {
        let opts = '<option value="">— Select Course —</option>';
        (res.data || []).forEach(c => opts += `<option value="${c.id}">${escH(c.name)}</option>`);
        $('#addCourse').html(opts);
    });
});

$('#addBatchBtn').on('click', function() {
    hideAddAlert();
    const data = {
        action:     'save',
        name:       $('#addName').val().trim(),
        course_id:  $('#addCourse').val(),
        start_date: $('#addStart').val(),
        end_date:   $('#addEnd').val()
    };
    if (IS_SA) data.branch_id = $('#addBranch').val();

    if (!data.name || !data.course_id || !data.start_date || !data.end_date ||
        (IS_SA && !data.branch_id)) {
        return showAddAlert('Please fill in all required fields.');
    }

    $('#addSpinner').removeClass('d-none');
    $(this).prop('disabled', true);
    $.post(API, data, function(res) {
        $('#addSpinner').addClass('d-none');
        $('#addBatchBtn').prop('disabled', false);
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('addBatchModal')).hide();
            Swal.fire({ icon: 'success', title: 'Batch Created', text: res.message, timer: 2000, showConfirmButton: false });
            loadBatches();
        } else {
            showAddAlert(res.message || 'Failed to save batch.');
        }
    }, 'json').fail(() => {
        $('#addSpinner').addClass('d-none');
        $('#addBatchBtn').prop('disabled', false);
        showAddAlert('Server error. Please try again.');
    });
});

// ── Edit batch ─────────────────────────────────────────────────────────────
function showEditAlert(msg, type='danger') {
    $('#editAlert').removeClass('d-none alert-danger alert-success').addClass('alert-'+type).html(msg);
}
function hideEditAlert() { $('#editAlert').addClass('d-none'); }

function openEdit(id) {
    hideEditAlert();
    $.getJSON(API + '?action=get&id=' + id, function(res) {
        if (!res.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not load batch.' });
            return;
        }
        const d = res.data;
        $('#editId').val(d.id);
        $('#editName').val(d.name);
        $('#editStart').val(d.start_date);
        $('#editEnd').val(d.end_date);

        if (IS_SA) {
            $('#editBranch').val(d.branch_id);
            // Load courses for that branch then set course
            $.getJSON(API + '?action=courses_by_branch&branch_id=' + d.branch_id, function(cr) {
                let opts = '<option value="">— Select Course —</option>';
                (cr.data || []).forEach(c => opts += `<option value="${c.id}">${escH(c.name)}</option>`);
                $('#editCourse').html(opts).val(d.course_id);
            });
        } else {
            $('#editCourse').val(d.course_id);
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editBatchModal')).show();
    });
}

// SA: Load courses when branch changes in Edit modal
$('#editBranch').on('change', function() {
    const bid = $(this).val();
    $('#editCourse').html('<option value="">— Loading… —</option>');
    if (!bid) { $('#editCourse').html('<option value="">— Select Course —</option>'); return; }
    $.getJSON(API + '?action=courses_by_branch&branch_id=' + bid, function(res) {
        let opts = '<option value="">— Select Course —</option>';
        (res.data || []).forEach(c => opts += `<option value="${c.id}">${escH(c.name)}</option>`);
        $('#editCourse').html(opts);
    });
});

$('#editBatchBtn').on('click', function() {
    hideEditAlert();
    const data = {
        action:     'update',
        id:         $('#editId').val(),
        name:       $('#editName').val().trim(),
        course_id:  $('#editCourse').val(),
        start_date: $('#editStart').val(),
        end_date:   $('#editEnd').val()
    };
    if (IS_SA) data.branch_id = $('#editBranch').val();

    if (!data.name || !data.course_id || !data.start_date || !data.end_date ||
        (IS_SA && !data.branch_id)) {
        return showEditAlert('Please fill in all required fields.');
    }

    $('#editSpinner').removeClass('d-none');
    $(this).prop('disabled', true);
    $.post(API, data, function(res) {
        $('#editSpinner').addClass('d-none');
        $('#editBatchBtn').prop('disabled', false);
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('editBatchModal')).hide();
            Swal.fire({ icon: 'success', title: 'Updated', text: res.message, timer: 2000, showConfirmButton: false });
            loadBatches();
        } else {
            showEditAlert(res.message || 'Failed to update batch.');
        }
    }, 'json').fail(() => {
        $('#editSpinner').addClass('d-none');
        $('#editBatchBtn').prop('disabled', false);
        showEditAlert('Server error. Please try again.');
    });
});

// ── Delete batch ───────────────────────────────────────────────────────────
function deleteBatch(id, name) {
    Swal.fire({
        title: 'Delete Batch?',
        html: `Are you sure you want to delete <strong>${escH(name)}</strong>?<br>
               <small class="text-muted">This cannot be undone. Batches with enrolled students cannot be deleted.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post(API, { action: 'delete', id: id }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1800, showConfirmButton: false });
                loadBatches();
            } else {
                Swal.fire({ icon: 'error', title: 'Cannot Delete', text: res.message });
            }
        }, 'json');
    });
}

// ── Filters ────────────────────────────────────────────────────────────────
$('#applyFilters').on('click', loadBatches);
$('#fSearch').on('keydown', e => { if (e.key === 'Enter') loadBatches(); });
$('#fStatus').on('change', loadBatches);
if (IS_SA) { $('#fBranch').on('change', loadBatches); }

$('#clearFilters').on('click', function() {
    $('#fStatus, #fSearch').val('');
    if (IS_SA) $('#fBranch').val('');
    loadBatches();
});

// ── Utilities ──────────────────────────────────────────────────────────────
function escH(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) {
    if (!s) return '';
    return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"');
}

// ── Init ───────────────────────────────────────────────────────────────────
$(function() { loadBatches(); });
</script>
</body>
</html>
