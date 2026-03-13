<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

$pageTitle  = 'Global Programs';
$activePage = 'global_programs.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ──────────────────────────────────────── -->
    <div class="page-header fade-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
            <div>
                <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-mortarboard-fill me-2"></i>Global Programs &amp; Certificates</h4>
                <p class="mb-0 opacity-75" style="font-size:.9rem;">Master catalog of vocational programs used system-wide by all branches</p>
            </div>
            <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="bi bi-plus-circle-fill"></i> Add Program
            </button>
        </div>
    </div>

    <!-- ── Stats Row ────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-collection-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#6366f1;" id="totalPrograms">–</div>
                        <div class="kpi-label">Total Programs</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#10b981;" id="activePrograms">–</div>
                        <div class="kpi-label">Active</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#ef4444;" id="inactivePrograms">–</div>
                        <div class="kpi-label">Inactive</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 fade-up">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="bi bi-tags-fill"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#f59e0b;" id="totalCategories">–</div>
                        <div class="kpi-label">Categories</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Programs Table ───────────────────────────────────── -->
    <div class="card fade-up">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1" style="color:#6366f1;"></i> Programs Catalog</h6>
        </div>
        <div class="card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="programsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Program Name</th>
                            <th>Category</th>
                            <th>Duration (wks)</th>
                            <th>Min Hours</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="addProgramForm" class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-plus-circle-fill me-2"></i>Add Global Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Program Code <span class="text-danger">*</span></label>
                        <input type="text" name="program_code" class="form-control" placeholder="e.g. WELD-101" required>
                        <div class="form-text">Unique identifier used system-wide.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Program Name <span class="text-danger">*</span></label>
                        <input type="text" name="program_name" class="form-control" placeholder="e.g. Basic Welding Certification" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Technical Skills">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Duration (weeks)</label>
                        <input type="number" name="duration_weeks" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Minimum Hours</label>
                        <input type="number" name="min_hours" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the program..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;border-radius:8px;font-weight:600;" id="saveProgramBtn">
                    <i class="bi bi-check-lg me-1"></i> Add Program
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="editProgramForm" class="modal-content">
            <div class="modal-header modal-header-warning">
                <h5 class="modal-title fw-bold" style="font-size:1rem;"><i class="bi bi-pencil-square me-2"></i>Edit Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_p_id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Program Code</label>
                        <input type="text" id="edit_p_code" class="form-control" readonly style="background:#f8f9fa;">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Program Name <span class="text-danger">*</span></label>
                        <input type="text" name="program_name" id="edit_p_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Category</label>
                        <input type="text" name="category" id="edit_p_category" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Duration (weeks)</label>
                        <input type="number" name="duration_weeks" id="edit_p_weeks" class="form-control" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Min Hours</label>
                        <input type="number" name="min_hours" id="edit_p_hours" class="form-control" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" id="edit_p_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" id="edit_p_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm px-3" style="background:#f59e0b;color:#fff;border-radius:8px;font-weight:600;" id="updateProgramBtn">
                    <i class="bi bi-check-lg me-1"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API = 'models/api/global_programs_api.php';

$(document).ready(function () {

    const table = $('#programsTable').DataTable({
        processing: true,
        ajax: {
            url: API + '?action=list',
            dataSrc: function (res) {
                const data = res.data || [];
                $('#totalPrograms').text(data.length);
                $('#activePrograms').text(data.filter(r => r.status === 'Active').length);
                $('#inactivePrograms').text(data.filter(r => r.status === 'Inactive').length);
                const cats = new Set(data.map(r => r.category).filter(Boolean));
                $('#totalCategories').text(cats.size);
                return data;
            }
        },
        columns: [
            { data: null, render: (d,t,r,m) => m.row + 1 },
            { data: 'program_code', render: d => `<code style="font-size:.82rem;background:rgba(99,102,241,.08);padding:2px 6px;border-radius:4px;color:#6366f1;">${escHtml(d)}</code>` },
            { data: 'program_name', render: d => `<span class="fw-semibold">${escHtml(d)}</span>` },
            { data: 'category',     render: d => d ? `<span class="badge-branch">${escHtml(d)}</span>` : '—' },
            { data: 'duration_weeks' },
            { data: 'min_hours' },
            { data: 'status', render: d => d === 'Active'
                ? '<span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>'
                : '<span class="badge-inactive"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>' },
            { data: 'created_by_name', render: d => `<span class="text-muted" style="font-size:.82rem;">${escHtml(d)}</span>` },
            {
                data: null, orderable: false,
                render: function (data) {
                    return `<div class="d-flex gap-1">
                        <button class="btn-action edit" title="Edit"
                            onclick="openEditProgram(${data.id},'${escJs(data.program_code)}','${escJs(data.program_name)}','${escJs(data.category||'')}',${data.duration_weeks},${data.min_hours},'${data.status}','${escJs(data.description||'')}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn-action delete" title="Delete" onclick="deleteProgram(${data.id},'${escJs(data.program_name)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>`;
                }
            }
        ],
        responsive: true,
        language: { emptyTable: 'No programs in catalog yet.' }
    });

    // Add
    $('#addProgramForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#saveProgramBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({ url: API + '?action=save', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Added!', res.message, 'success');
                    $('#addProgramModal').modal('hide');
                    $('#addProgramForm')[0].reset();
                    table.ajax.reload();
                } else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Server error.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Add Program'); }
        });
    });

    // Edit
    $('#editProgramForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#updateProgramBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Updating...');
        $.ajax({ url: API + '?action=update', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Updated!', res.message, 'success');
                    $('#editProgramModal').modal('hide');
                    table.ajax.reload();
                } else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Server error.', 'error'); },
            complete: function () { btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Update'); }
        });
    });
});

function openEditProgram(id, code, name, cat, weeks, hours, status, desc) {
    document.getElementById('edit_p_id').value       = id;
    document.getElementById('edit_p_code').value     = code;
    document.getElementById('edit_p_name').value     = name;
    document.getElementById('edit_p_category').value = cat;
    document.getElementById('edit_p_weeks').value    = weeks;
    document.getElementById('edit_p_hours').value    = hours;
    document.getElementById('edit_p_status').value   = status;
    document.getElementById('edit_p_desc').value     = desc;
    new bootstrap.Modal(document.getElementById('editProgramModal')).show();
}

function deleteProgram(id, name) {
    Swal.fire({ title: 'Delete Program?', html: `Remove <strong>${escHtml(name)}</strong> from the global catalog?`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if (result.isConfirmed) {
            $.post(API + '?action=delete', { id }, function (res) {
                if (res.status === 'success') { Swal.fire('Deleted!', res.message, 'success'); $('#programsTable').DataTable().ajax.reload(); }
                else { Swal.fire('Error', res.message, 'error'); }
            }, 'json');
        }
    });
}

function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(str)   { return String(str||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
</script>
</body>
</html>
