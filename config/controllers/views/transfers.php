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

$pageTitle  = 'Inter-Branch Transfers';
$activePage = 'transfers.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;">
                    <i class="bi bi-arrow-left-right me-2 text-primary"></i>Transfer Requests
                </h2>
                <div class="text-muted mt-1" style="font-size: 0.95rem;">Manage student inter-branch transfers and approvals.</div>
            </div>
            <!-- Action to manually initiate a transfer on behalf of a student could go here -->
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 py-3 pb-0">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul me-2 text-muted"></i>Transfer Queue</h6>
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

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$(document).ready(function() {
    $('#transfersTable').DataTable({
        processing: true,
        ajax: { url: '../views/models/api/transfer_api.php?action=list' },
        columns: [
            { 
                data: 'transfer_id', 
                className: 'fw-bold text-dark ps-3',
                render: function(data) { return '<span style="font-family:monospace;">' + data + '</span>'; }
            },
            {
                data: null,
                render: function(row) {
                    return row.student_name + '<br><small class="text-muted">' + row.student_code + '</small>';
                }
            },
            { data: 'origin_branch' },
            { data: 'destination_branch' },
            { 
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
                }
            },
            {
                data: 'status',
                render: function(data) {
                    let badge = 'bg-secondary';
                    if (data.includes('Pending')) badge = 'bg-warning text-dark';
                    else if (data.includes('Complete')) badge = 'bg-success';
                    else if (data.includes('Rejected')) badge = 'bg-danger';
                    else if (data.includes('Hold')) badge = 'bg-info text-dark';
                    else if (data.includes('Conditionally')) badge = 'bg-primary';
                    return '<span class="badge ' + badge + '">' + data + '</span>';
                }
            },
            {
                data: 'id',
                className: 'text-end pe-3',
                orderable: false,
                render: function(id) {
                    return '<a href="transfer_details.php?id=' + id + '" class="btn btn-sm btn-outline-primary" style="font-weight:500;"><i class="bi bi-eye me-1"></i> Review</a>';
                }
            }
        ],
        order: [[4, 'desc']], // Sort by date descending
        language: { emptyTable: "No transfer requests found." }
    });
});
</script>
</body>
</html>
