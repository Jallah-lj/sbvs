<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../config.php';
require_once '../../database.php';
require_once 'models/TransferRequest.php';

$db = (new Database())->getConnection();
$transferModel = new TransferRequest($db);

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');

$transfer_id = (int)($_GET['id'] ?? 0);
$t = $transferModel->getById($transfer_id);

if (!$t) {
    die("Transfer Request Not Found.");
}

// Access Control
if (!$isSuperAdmin && $t['origin_branch_id'] != $sessionBranch && $t['destination_branch_id'] != $sessionBranch) {
    die("Unauthorized.");
}

$pageTitle  = 'Transfer Details - ' . htmlspecialchars($t['transfer_id']);
$activePage = 'transfers.php';

// Fetch Docs & Logs manually for PHP inclusion (instead of API call on load)
$dStmt = $db->prepare("SELECT * FROM transfer_documents WHERE transfer_request_id = ?");
$dStmt->execute([$transfer_id]);
$docs = $dStmt->fetchAll(PDO::FETCH_ASSOC);

$logs = $transferModel->getAuditLog($transfer_id);

// Determine valid actions based on role: All logged-in admins can now act
$isOriginAdmin = ($isSuperAdmin || $isBranchAdmin);
$isDestAdmin   = ($isSuperAdmin || $isBranchAdmin);

$canOriginApprove = $isOriginAdmin && in_array($t['status'], ['Pending Origin Approval', 'Origin On Hold']);
$canDestApprove = $isDestAdmin && $t['status'] === 'Pending Destination Approval';

$statusBadge = 'bg-secondary';
if (str_contains($t['status'], 'Pending')) $statusBadge = 'bg-warning text-dark';
else if (str_contains($t['status'], 'Complete')) $statusBadge = 'bg-success';
else if (str_contains($t['status'], 'Rejected')) $statusBadge = 'bg-danger';
else if (str_contains($t['status'], 'Hold')) $statusBadge = 'bg-info text-dark';
else if (str_contains($t['status'], 'Conditionally')) $statusBadge = 'bg-primary';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main">
        <div class="d-flex align-items-center mb-4">
            <a href="transfers.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Back</a>
            <div>
                <h2 class="mb-0 fw-bold">Transfer Request: <?= htmlspecialchars($t['transfer_id']) ?></h2>
                <div class="mt-1">
                    <span class="badge <?= $statusBadge ?> fs-6"><?= htmlspecialchars($t['status']) ?></span>
                    <span class="text-muted ms-2 small">Submitted: <?= date('M j, Y h:i A', strtotime($t['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Details & Docs -->
            <div class="col-lg-8">
                
                <!-- Request Info Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Transfer Information</div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Student</div>
                            <div class="col-sm-8 fw-bold"><?= htmlspecialchars($t['student_name']) ?> <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars($t['student_code']) ?></span></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Route</div>
                            <div class="col-sm-8">
                                <span class="fw-medium text-danger"><?= htmlspecialchars($t['origin_branch_name']) ?></span> 
                                <i class="bi bi-arrow-right mx-2 text-muted"></i> 
                                <span class="fw-medium text-success"><?= htmlspecialchars($t['destination_branch_name']) ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Student's Reason</div>
                            <div class="col-sm-8 p-3 bg-light rounded" style="font-size:0.9rem;">
                                <?= nl2br(htmlspecialchars($t['reason'] ?: 'No reason provided.')) ?>
                            </div>
                        </div>
                        <?php if ($t['conditional_notes']): ?>
                        <div class="row mb-0">
                            <div class="col-sm-4 text-primary fw-bold">Destination Conditions</div>
                            <div class="col-sm-8 p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded text-primary" style="font-size:0.9rem;">
                                <?= nl2br(htmlspecialchars($t['conditional_notes'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Documents Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="bi bi-file-earmark-pdf me-2 text-primary"></i>Uploaded Documents</span>
                        <!-- Document upload strictly for students/origin admins could go here -->
                        <?php if ($canOriginApprove || $t['status'] === 'Origin On Hold' || $t['status'] === 'Pending Origin Approval'): ?>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal"><i class="bi bi-upload"></i> Upload</button>
                        <?php endif; ?>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($docs)): ?>
                            <li class="list-group-item text-muted p-4 text-center">No documents uploaded yet.</li>
                        <?php else: ?>
                            <?php foreach ($docs as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <h6 class="mb-1"><i class="bi bi-file-text me-2 text-secondary"></i><?= htmlspecialchars($d['document_type']) ?></h6>
                                    <div class="small text-muted" style="font-family:monospace; font-size:0.75rem;">
                                        Checksum (SHA256): <?= htmlspecialchars($d['checksum']) ?> &bull; <?= date('M j, Y Hi', strtotime($d['uploaded_at'])) ?>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL . $d['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary border-0"><i class="bi bi-download fs-5"></i></a>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

            </div>

            <!-- Right Column: Actions & Log -->
            <div class="col-lg-4">
                
                <!-- Action Panel -->
                <?php if ($canOriginApprove || $canDestApprove): ?>
                <div class="card shadow-sm border-0 border-top border-4 border-warning mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-shield-check me-2"></i>Required Actions</h6>
                        
                        <?php if ($canOriginApprove): ?>
                            <p class="small text-muted mb-3">As Origin Admin, review documents and clear the student for transfer.</p>
                            <button class="btn btn-success w-100 mb-2 fw-bold" onclick="updateStatus('Pending Destination Approval', 'origin')"><i class="bi bi-check-circle me-1"></i> Approve & Forward</button>
                            <button class="btn btn-warning text-dark w-100 mb-2 fw-medium" onclick="updateStatus('Origin On Hold', 'origin')"><i class="bi bi-pause-circle me-1"></i> Request Clarification</button>
                            <button class="btn btn-outline-danger w-100 fw-medium" onclick="updateStatus('Origin Rejected', 'origin')"><i class="bi bi-x-circle me-1"></i> Reject Transfer</button>
                        <?php endif; ?>

                        <?php if ($canDestApprove): ?>
                            <p class="small text-muted mb-3">As Destination Admin, verify capacity and academic standing.</p>
                            <button class="btn btn-success w-100 mb-2 fw-bold" onclick="updateStatus('Transfer Complete', 'destination')"><i class="bi bi-check-circle me-1"></i> Final Approval (Complete Transfer)</button>
                            <button class="btn btn-primary w-100 mb-2 fw-medium" onclick="updateStatus('Destination Conditionally Approved', 'destination')"><i class="bi bi-exclamation-circle me-1"></i> Conditional Approval</button>
                            <button class="btn btn-outline-danger w-100 fw-medium" onclick="updateStatus('Destination Rejected', 'destination')"><i class="bi bi-x-circle me-1"></i> Reject Transfer</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Audit Log -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Immutable Audit Trail</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($logs as $log): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong class="text-dark small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($log['actor_name']) ?> (<?= htmlspecialchars($log['actor_role']) ?>)</strong>
                                    <span class="text-muted" style="font-size:0.7rem;"><?= date('M j, Y H:i', strtotime($log['created_at'])) ?></span>
                                </div>
                                <div class="badge bg-light text-dark border mb-2"><?= htmlspecialchars($log['action']) ?></div>
                                <?php if ($log['rationale']): ?>
                                <div class="bg-light p-2 rounded small text-muted border border-secondary border-opacity-10">
                                    "<?= nl2br(htmlspecialchars($log['rationale'])) ?>"
                                </div>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- Upload Doc Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="uploadDocForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title">Upload Transfer Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="transfer_request_id" value="<?= $transfer_id ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Document Type</label>
                    <select name="document_type" class="form-select" required>
                        <option value="Application Form">Application Form</option>
                        <option value="Academic Record">Academic Record</option>
                        <option value="Clearance Certificate">Clearance Certificate</option>
                        <option value="ID Document">ID Document</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">File</label>
                    <input type="file" name="document" class="form-control" required>
                    <div class="form-text">PDF or images only. File will be checksummed.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="uploadBtn">Upload Document</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$('#uploadDocForm').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    $('#uploadBtn').prop('disabled', true).text('Uploading...');
    
    $.ajax({
        url: 'models/api/transfer_api.php?action=upload_document',
        type: 'POST',
        data: fd,
        processData: false, contentType: false,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') location.reload();
            else Swal.fire('Error', res.message, 'error');
        },
        error: function() { Swal.fire('Error', 'Server failed to upload document', 'error'); },
        complete: function() { $('#uploadBtn').prop('disabled', false).text('Upload Document'); }
    });
});

function updateStatus(new_status, roleContext) {
    let html = '<textarea id="swalRationale" class="swal2-textarea" placeholder="Enter rationale/notes for this decision..."></textarea>';
    if (new_status === 'Destination Conditionally Approved') {
         html += '<textarea id="swalConditions" class="swal2-textarea" placeholder="List specific prerequisites/conditions for this student..."></textarea>';
    }

    Swal.fire({
        title: 'Confirm Action',
        html: html,
        showCancelButton: true,
        confirmButtonText: 'Submit Decision',
        preConfirm: () => {
            return {
                rationale: document.getElementById('swalRationale').value,
                conditions: document.getElementById('swalConditions') ? document.getElementById('swalConditions').value : ''
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('models/api/transfer_api.php?action=update_status', {
                transfer_request_id: <?= $transfer_id ?>,
                status: new_status,
                rationale: result.value.rationale,
                conditional_notes: result.value.conditions
            }, function(res) {
                if (res.status === 'success') {
                    if (new_status === 'Transfer Complete') {
                        Swal.fire('Transfer Complete!', 'The student has been successfully migrated to the new branch.', 'success').then(() => location.reload());
                    } else {
                        location.reload();
                    }
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>
</body>
</html>
