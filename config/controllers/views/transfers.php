<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../config.php';
require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');

if (!$isSuperAdmin && !$isBranchAdmin) {
    die("Unauthorized access.");
}

// Fetch active branches for destination/origin selectors
$allBranches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

// For Branch Admin, get their own branch name
$sessionBranchName = '';
if ($isBranchAdmin && $sessionBranch) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$sessionBranch]);
    $sessionBranchName = $bStmt->fetchColumn() ?: '';
}

$pageTitle  = 'Inter-Branch Transfers';
$activePage = 'transfers.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main">

        <!-- Page Header -->
        <div class="page-header fade-up">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
                <div>
                    <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;">
                        <i class="bi bi-arrow-left-right me-2"></i>Inter-Branch Transfers
                    </h4>
                    <p class="mb-0 opacity-75" style="font-size:.9rem;">
                        <?= $isSuperAdmin ? 'View and action all inter-branch student transfer requests.' : 'Submit and manage student transfer requests for your branch.' ?>
                        <?= ($isBranchAdmin && $sessionBranchName) ? ' — ' . htmlspecialchars($sessionBranchName) . ' Branch' : '' ?>
                    </p>
                </div>
                <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2"
                        style="font-weight:600;border-radius:10px;"
                        data-bs-toggle="modal" data-bs-target="#newTransferModal">
                    <i class="bi bi-plus-circle-fill"></i> New Transfer Request
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(245,158,11,.1);color:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
                        <div><div class="kpi-value" style="color:#f59e0b;" id="statPending">–</div><div class="kpi-label">Pending</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                        <div><div class="kpi-value" style="color:#10b981;" id="statComplete">–</div><div class="kpi-label">Completed</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
                        <div><div class="kpi-value" style="color:#ef4444;" id="statRejected">–</div><div class="kpi-label">Rejected</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(99,102,241,.1);color:#6366f1;"><i class="bi bi-list-check"></i></div>
                        <div><div class="kpi-value" style="color:#6366f1;" id="statTotal">–</div><div class="kpi-label">Total</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Queue Table -->
        <div class="card shadow-sm border-0 fade-up">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-muted"></i>Transfer Queue</h6>
            </div>
            <div class="card-body p-0 p-md-3 pt-md-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="transfersTable">
                        <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                            <tr>
                                <th class="ps-3 fw-semibold">Transfer ID</th>
                                <th class="fw-semibold">Student</th>
                                <th class="fw-semibold">Origin</th>
                                <th class="fw-semibold">Destination</th>
                                <th class="fw-semibold">Date Submitted</th>
                                <th class="fw-semibold">Status</th>
                                <th class="pe-3 fw-semibold text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ── New Transfer Request Modal ───────────────────────────────────────── -->
<div class="modal fade" id="newTransferModal" tabindex="-1" aria-labelledby="newTransferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="newTransferForm" class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;">
                <h5 class="modal-title fw-bold" style="font-size:1rem;" id="newTransferModalLabel">
                    <i class="bi bi-arrow-left-right me-2"></i>New Transfer Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">

                    <?php if ($isSuperAdmin): ?>
                    <!-- Super Admin: choose origin branch, then student auto-filters -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Origin Branch <span class="text-danger">*</span></label>
                        <select name="origin_branch_id" id="originBranchSel" class="form-select" required>
                            <option value="">— Select origin branch —</option>
                            <?php foreach ($allBranches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <!-- Branch Admin: origin is fixed to their branch -->
                    <input type="hidden" name="origin_branch_id" value="<?= $sessionBranch ?>">
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <select name="student_id" id="studentSel" class="form-select" required>
                            <option value=""><?= $isSuperAdmin ? '— Select origin branch first —' : '— Select student —' ?></option>
                        </select>
                        <div class="form-text" id="studentLoadNote"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Destination Branch <span class="text-danger">*</span></label>
                        <select name="destination_branch_id" id="destBranchSel" class="form-select" required>
                            <option value="">— Select destination branch —</option>
                            <?php foreach ($allBranches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-warning" id="sameBranchWarning" style="display:none;">
                            <i class="bi bi-exclamation-triangle me-1"></i>Origin and destination cannot be the same branch.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Reason for Transfer <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3"
                                  placeholder="Briefly explain the reason for this transfer request…" required
                                  style="resize:vertical;"></textarea>
                    </div>

                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.04);">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-4" id="submitTransferBtn" style="border-radius:8px;font-weight:600;">
                    <i class="bi bi-send me-1"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API    = 'models/api/transfer_api.php';
const isSA   = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranch = <?= $sessionBranch ?>;
let table;

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function statusBadge(s) {
    let bg = 'rgba(100,116,139,.15)', color = '#64748b';
    if (s.includes('Pending'))       { bg = 'rgba(245,158,11,.15)';  color = '#f59e0b'; }
    else if (s.includes('Complete')) { bg = 'rgba(16,185,129,.15)';  color = '#10b981'; }
    else if (s.includes('Rejected')) { bg = 'rgba(239,68,68,.15)';   color = '#ef4444'; }
    else if (s.includes('Hold'))     { bg = 'rgba(14,165,233,.15)';  color = '#0ea5e9'; }
    else if (s.includes('Conditional'))  { bg = 'rgba(99,102,241,.15)'; color = '#6366f1'; }
    return `<span style="background:${bg};color:${color};border-radius:6px;padding:3px 8px;font-size:.75rem;font-weight:600;white-space:nowrap;">${escHtml(s)}</span>`;
}

function loadStudents(branchId) {
    const sel = $('#studentSel').empty().append('<option value="">Loading…</option>');
    $('#studentLoadNote').text('');
    const url = 'models/api/student_api.php?action=list_simple' + (branchId ? '&branch_id=' + branchId : '');
    $.getJSON(url, function(res) {
        sel.empty().append('<option value="">— Select student —</option>');
        const students = res.data || [];
        if (students.length === 0) {
            sel.append('<option value="" disabled>No students found for this branch</option>');
            $('#studentLoadNote').text('No enrolled students found for this branch.');
        } else {
            students.forEach(function(s) {
                sel.append('<option value="' + s.id + '">' + escHtml(s.name) + ' (' + escHtml(s.student_id) + ')</option>');
            });
        }
    });
}

$(document).ready(function() {

    // For Branch Admin, load students immediately
    if (!isSA && myBranch) {
        loadStudents(myBranch);
    }

    // For Super Admin, load students when origin branch is selected
    $('#originBranchSel').on('change', function() {
        const bid = $(this).val();
        if (bid) {
            loadStudents(bid);
        } else {
            $('#studentSel').empty().append('<option value="">— Select origin branch first —</option>');
        }
        // Refresh dest branch options (exclude origin)
        updateDestOptions();
    });

    // Warn if origin == destination
    $('#destBranchSel').on('change', function() { updateDestOptions(); });

    function getOriginBranch() {
        return isSA ? parseInt($('#originBranchSel').val() || 0) : myBranch;
    }

    function updateDestOptions() {
        const origin = getOriginBranch();
        const dest   = parseInt($('#destBranchSel').val() || 0);
        if (origin && dest && origin === dest) {
            $('#sameBranchWarning').show();
            $('#submitTransferBtn').prop('disabled', true);
        } else {
            $('#sameBranchWarning').hide();
            $('#submitTransferBtn').prop('disabled', false);
        }
    }

    // Submit new transfer
    $('#newTransferForm').on('submit', function(e) {
        e.preventDefault();

        // Validate origin != destination
        const origin = parseInt($('[name="origin_branch_id"]').first().val() || 0);
        const dest   = parseInt($('#destBranchSel').val() || 0);
        if (origin && dest && origin === dest) {
            Swal.fire('Validation Error', 'Origin and destination branches cannot be the same.', 'warning');
            return;
        }

        const btn = $('#submitTransferBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Submitting…');

        $.ajax({
            url: API + '?action=create',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Transfer Submitted',
                        html: 'Transfer ID: <strong style="font-family:monospace;">' + escHtml(res.transfer_id_str) + '</strong><br>It is now pending origin branch approval.',
                        confirmButtonText: 'View Details'
                    }).then(function(r) {
                        if (r.isConfirmed) {
                            window.location.href = 'transfer_details.php?id=' + res.id;
                        } else {
                            $('#newTransferModal').modal('hide');
                            $('#newTransferForm')[0].reset();
                            if (!isSA) loadStudents(myBranch);
                            table.ajax.reload();
                        }
                    });
                } else {
                    Swal.fire('Error', res.message || 'Could not submit transfer.', 'error');
                }
            },
            error: function() { Swal.fire('Error', 'Server error. Please try again.', 'error'); },
            complete: function() { btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Submit Request'); }
        });
    });

    // Reset form on modal close
    $('#newTransferModal').on('hidden.bs.modal', function() {
        $('#newTransferForm')[0].reset();
        $('#sameBranchWarning').hide();
        if (isSA) {
            $('#studentSel').empty().append('<option value="">— Select origin branch first —</option>');
        }
    });

    // DataTable
    table = $('#transfersTable').DataTable({
        processing: true,
        ajax: {
            url: API + '?action=list',
            dataSrc: function(res) {
                const rows = res.data || [];
                $('#statPending').text(rows.filter(function(r) { return r.status.includes('Pending') || r.status.includes('Hold'); }).length);
                $('#statComplete').text(rows.filter(function(r) { return r.status === 'Transfer Complete'; }).length);
                $('#statRejected').text(rows.filter(function(r) { return r.status.includes('Rejected'); }).length);
                $('#statTotal').text(rows.length);
                return rows;
            }
        },
        columns: [
            {
                data: 'transfer_id',
                className: 'fw-bold ps-3',
                render: function(d) { return '<span style="font-family:monospace;font-size:.85rem;">' + escHtml(d) + '</span>'; }
            },
            {
                data: null,
                render: function(row) {
                    return '<div class="fw-semibold" style="font-size:.85rem;">' + escHtml(row.student_name) + '</div>'
                         + '<div class="text-muted" style="font-size:.75rem;">' + escHtml(row.student_code) + '</div>';
                }
            },
            { data: 'origin_branch',      render: function(d) { return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fw-semibold" style="font-size:.78rem;">' + escHtml(d) + '</span>'; } },
            { data: 'destination_branch', render: function(d) { return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-semibold" style="font-size:.78rem;">' + escHtml(d) + '</span>'; } },
            {
                data: 'created_at',
                render: function(d) {
                    return '<span class="text-muted" style="font-size:.8rem;">' + new Date(d).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }) + '</span>';
                }
            },
            { data: 'status', render: statusBadge },
            {
                data: 'id',
                className: 'text-end pe-3',
                orderable: false,
                render: function(id) {
                    return '<a href="transfer_details.php?id=' + id + '" class="btn btn-sm btn-outline-primary" style="font-weight:600;border-radius:8px;font-size:.8rem;"><i class="bi bi-eye me-1"></i>Review</a>';
                }
            }
        ],
        order: [[4, 'desc']],
        responsive: true,
        language: { emptyTable: 'No transfer requests found.' }
    });
});
</script>
</body>
</html>
