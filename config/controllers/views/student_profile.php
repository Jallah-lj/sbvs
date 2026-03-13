<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}

$isSuperAdmin = ($_SESSION['role'] === 'Super Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: students.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

// ── Core student info ────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT s.*, u.name, u.email, b.name AS branch_name
     FROM students s
     JOIN users u    ON s.user_id   = u.id
     JOIN branches b ON s.branch_id = b.id
     WHERE s.id = ?"
);
$stmt->execute([(int)$_GET['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: students.php");
    exit;
}

// ── Enrollments ──────────────────────────────────────────────────────────────
$eStmt = $db->prepare(
    "SELECT e.enrollment_date, e.status,
            c.name AS course_name, c.duration, c.fees,
            ba.name AS batch_name, ba.start_date, ba.end_date
     FROM enrollments e
     JOIN courses  c  ON e.course_id = c.id
     JOIN batches  ba ON e.batch_id  = ba.id
     WHERE e.student_id = ?
     ORDER BY e.enrollment_date DESC"
);
$eStmt->execute([(int)$_GET['id']]);
$enrollments = $eStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Payments ─────────────────────────────────────────────────────────────────
$pStmt = $db->prepare(
    "SELECT payment_date, amount, payment_method, transaction_id
     FROM payments
     WHERE student_id = ?
     ORDER BY payment_date DESC"
);
$pStmt->execute([(int)$_GET['id']]);
$payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary totals ────────────────────────────────────────────────────────────
$totalFees    = array_sum(array_column($enrollments, 'fees'));
$totalPaid    = array_sum(array_column($payments,    'amount'));
$totalBalance = max(0, $totalFees - $totalPaid);

$statusColors = ['Active' => 'success', 'Completed' => 'primary', 'Dropped' => 'danger'];

// ── Transfers ────────────────────────────────────────────────────────────────
$trStmt = $db->prepare(
    "SELECT t.*, bo.name as origin_branch, bd.name as destination_branch 
     FROM transfer_requests t
     JOIN branches bo ON t.origin_branch_id = bo.id
     JOIN branches bd ON t.destination_branch_id = bd.id
     WHERE t.student_id = ?
     ORDER BY t.created_at DESC"
);
$trStmt->execute([(int)$_GET['id']]);
$transfers = $trStmt->fetchAll(PDO::FETCH_ASSOC);

// Branches for the transfer modal
$branches = $db->query("SELECT id, name FROM branches WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Student Profile';
$activePage = 'student_profile.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="students.php"><i class="bi bi-people-fill me-1"></i> Students Directory</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($student['name']) ?> (<code><?= htmlspecialchars($student['student_id']) ?></code>)</li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- ── Left: Profile Card ───────────────────────────────────── -->
            <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 mb-3 text-center">
                    <div class="card-body py-5">
                        <div class="d-flex justify-content-center mb-4">
                            <?php
                            $photo    = $student['photo_url'] ?? '';
                            $photoAbs = $photo ? '/opt/lampp/htdocs/sbvs/' . $photo : '';
                            $photoUrl = $photo ? 'http://localhost/sbvs/' . $photo : '';
                            if ($photo && file_exists($photoAbs)):
                            ?>
                                <img src="<?= htmlspecialchars($photoUrl) ?>" class="avatar-circle" alt="Student Photo">
                            <?php else: ?>
                                <div class="avatar-initials">
                                    <?= strtoupper(substr($student['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h5 class="mb-1 fw-bold text-dark fs-4"><?= htmlspecialchars($student['name']) ?></h5>
                        <p class="text-muted small mb-3"><code><?= htmlspecialchars($student['student_id']) ?></code></p>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-semibold">Active Student</span>
                    </div>
                    <ul class="list-group list-group-flush text-start border-top">
                        <li class="list-group-item small d-flex align-items-center gap-3">
                            <i class="bi bi-envelope text-primary opacity-75 fs-5"></i>
                            <div>
                                <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase;">Email</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($student['email']) ?></div>
                            </div>
                        </li>
                        <li class="list-group-item small d-flex align-items-center gap-3">
                            <i class="bi bi-building text-primary opacity-75 fs-5"></i>
                            <div>
                                <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase;">Branch</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($student['branch_name']) ?></div>
                            </div>
                        </li>
                        <li class="list-group-item small d-flex align-items-center gap-3">
                            <i class="bi bi-telephone text-primary opacity-75 fs-5"></i>
                            <div>
                                <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase;">Phone</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($student['phone'] ?? '—') ?></div>
                            </div>
                        </li>
                        <li class="list-group-item small d-flex align-items-center gap-3">
                            <i class="bi bi-person-fill text-primary opacity-75 fs-5"></i>
                            <div>
                                <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase;">Gender</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($student['gender']) ?></div>
                            </div>
                        </li>
                        <li class="list-group-item small d-flex align-items-center gap-3">
                            <i class="bi bi-calendar3 text-primary opacity-75 fs-5"></i>
                            <div>
                                <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase;">Date of Birth</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($student['dob'] ?? '—') ?></div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Financial summary pills -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <span class="fw-bold small text-primary text-uppercase" style="letter-spacing: 0.05em;"><i class="bi bi-wallet2 me-2"></i>Financial Summary</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="stat-pill border-success d-flex justify-content-between align-items-center">
                            <div class="small fw-semibold text-muted">Total Fees</div>
                            <div class="fw-bold fs-6 text-success">$<?= number_format($totalFees, 2) ?></div>
                        </div>
                        <div class="stat-pill border-primary d-flex justify-content-between align-items-center">
                            <div class="small fw-semibold text-muted">Total Paid</div>
                            <div class="fw-bold fs-6 text-primary">$<?= number_format($totalPaid, 2) ?></div>
                        </div>
                        <div class="stat-pill <?= $totalBalance > 0 ? 'border-danger' : 'border-success' ?> d-flex justify-content-between align-items-center mb-0">
                            <div class="small fw-semibold text-muted">Outstanding</div>
                            <div class="fw-bold fs-6 <?= $totalBalance > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $totalBalance > 0 ? '$' . number_format($totalBalance, 2) : '<i class="bi bi-check2-circle"></i> Cleared' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Right: Tabs ─────────────────────────────────────────── -->
            <div class="col-md-8 col-lg-9">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview">
                                    <i class="bi bi-person-lines-fill me-1"></i>Overview
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-enrollments">
                                    <i class="bi bi-book me-1"></i>Enrollments
                                    <span class="badge bg-primary ms-1"><?= count($enrollments) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments">
                                    <i class="bi bi-cash-stack me-1"></i>Payments
                                    <span class="badge bg-success ms-1"><?= count($payments) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transfers">
                                    <i class="bi bi-arrow-left-right me-1"></i>Transfers
                                    <?php if (count($transfers) > 0): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?= count($transfers) ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body tab-content p-3 p-md-4">

                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="tab-overview">
                            <h6 class="fw-bold text-primary mb-4" style="letter-spacing: -0.01em;"><i class="bi bi-info-circle me-2"></i>Registration Details</h6>
                            <div class="table-responsive border rounded-3 p-1 bg-light bg-opacity-50">
                                <table class="table table-sm table-borderless align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2" style="width:35%">Student ID</th>
                                            <td class="fw-bold text-dark py-2"><code><?= htmlspecialchars($student['student_id']) ?></code></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Full Name</th>
                                            <td class="fw-semibold text-dark py-2"><?= htmlspecialchars($student['name']) ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Email</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars($student['email']) ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Gender</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars($student['gender']) ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Date of Birth</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars($student['dob'] ?? '—') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Phone</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars($student['phone'] ?? '—') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Address</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars($student['address'] ?? '—') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Assigned Branch</th>
                                            <td class="py-2"><span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-building me-1"></i><?= htmlspecialchars($student['branch_name']) ?></span></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted fw-medium ps-3 py-2">Registered On</th>
                                            <td class="text-dark py-2"><?= htmlspecialchars(date('M j, Y, g:i a', strtotime($student['registration_date']))) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Enrollments Tab -->
                        <div class="tab-pane fade" id="tab-enrollments">
                            <h6 class="fw-bold text-primary mb-4" style="letter-spacing: -0.01em;"><i class="bi bi-book me-2"></i>Course Enrollments</h6>
                            <?php if (empty($enrollments)): ?>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info fw-medium rounded-3 px-4 py-3">
                                    <i class="bi bi-info-circle-fill me-2"></i>No course enrollments on record.
                                </div>
                            <?php else: ?>
                            <div class="table-responsive border rounded-3">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                                        <tr>
                                            <th class="ps-3 fw-semibold">Course</th>
                                            <th class="fw-semibold">Batch</th>
                                            <th class="fw-semibold">Duration</th>
                                            <th class="fw-semibold">Fees</th>
                                            <th class="fw-semibold">Enrolled</th>
                                            <th class="fw-semibold">Start – End</th>
                                            <th class="fw-semibold pe-3 text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($enrollments as $e): $sc = $statusColors[$e['status']] ?? 'secondary'; ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($e['course_name']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($e['batch_name']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($e['duration']) ?></td>
                                            <td class="text-success fw-bold">$<?= number_format($e['fees'], 2) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars(date('M j, Y', strtotime($e['enrollment_date']))) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars(date('M j', strtotime($e['start_date']))) ?> &rarr; <?= htmlspecialchars(date('M j, Y', strtotime($e['end_date']))) ?></td>
                                            <td class="pe-3 text-end"><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25 px-2 py-1"><?= htmlspecialchars($e['status']) ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payments Tab -->
                        <div class="tab-pane fade" id="tab-payments">
                            <h6 class="fw-bold text-primary mb-4" style="letter-spacing: -0.01em;"><i class="bi bi-cash-stack me-2"></i>Payment History</h6>
                            <?php if (empty($payments)): ?>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info fw-medium rounded-3 px-4 py-3">
                                    <i class="bi bi-info-circle-fill me-2"></i>No payment records found.
                                </div>
                            <?php else: ?>
                            <div class="table-responsive border rounded-3">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                                        <tr>
                                            <th class="ps-3 fw-semibold">Date & Time</th>
                                            <th class="fw-semibold">Transaction ID</th>
                                            <th class="fw-semibold text-center">Method</th>
                                            <th class="fw-semibold pe-3 text-end">Amount Paid</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php
                                        $methodColors = ['Cash' => 'success', 'Bank Transfer' => 'primary', 'Mobile Money - MTN' => 'warning text-dark', 'Mobile Money - Orange' => 'warning text-dark', 'Check' => 'secondary' , 'Debit Card' => 'info' , 'Credit Card' => 'info'];
                                        foreach ($payments as $p):
                                            $mc = $methodColors[$p['payment_method']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td class="ps-3 text-dark fw-medium"><?= htmlspecialchars(date('M j, Y \a\t g:i a', strtotime($p['payment_date']))) ?></td>
                                            <td><code class="text-muted bg-light px-2 py-1 rounded-2"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></code></td>
                                            <td class="text-center"><span class="badge bg-<?= $mc ?> px-2 py-1 border opacity-75 border-dark-subtle"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                                            <td class="pe-3 fw-800 text-success text-end fs-6">+$<?= number_format($p['amount'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light border-top">
                                        <tr>
                                            <th colspan="3" class="text-end text-muted text-uppercase fw-semibold" style="letter-spacing: 0.05em;">Total Paid Successfully:</th>
                                            <th class="pe-3 text-success fw-bold fs-5 text-end">$<?= number_format($totalPaid, 2) ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Transfers Tab -->
                        <div class="tab-pane fade" id="tab-transfers">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold text-primary mb-0" style="letter-spacing: -0.01em;"><i class="bi bi-arrow-left-right me-2"></i>Inter-Branch Transfers</h6>
                                <button class="btn btn-sm btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#initiateTransferModal"><i class="bi bi-plus-lg me-1"></i> Initiate Transfer</button>
                            </div>
                            
                            <?php if (empty($transfers)): ?>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info fw-medium rounded-3 px-4 py-3">
                                    <i class="bi bi-info-circle-fill me-2"></i>No transfer requests on record for this student.
                                </div>
                            <?php else: ?>
                            <div class="table-responsive border rounded-3">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                                        <tr>
                                            <th class="ps-3 fw-semibold">Transfer ID</th>
                                            <th class="fw-semibold">Origin</th>
                                            <th class="fw-semibold">Destination</th>
                                            <th class="fw-semibold">Date Submitted</th>
                                            <th class="fw-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($transfers as $tr): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark"><a href="transfer_details.php?id=<?= $tr['id'] ?>" class="text-decoration-none"><code><?= htmlspecialchars($tr['transfer_id']) ?></code></a></td>
                                            <td class="text-muted"><?= htmlspecialchars($tr['origin_branch']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($tr['destination_branch']) ?></td>
                                            <td class="text-muted"><?= date('M j, Y', strtotime($tr['created_at'])) ?></td>
                                            <td>
                                                <?php
                                                $b = 'bg-secondary';
                                                if (str_contains($tr['status'], 'Pending')) $b = 'bg-warning text-dark';
                                                elseif (str_contains($tr['status'], 'Complete')) $b = 'bg-success';
                                                elseif (str_contains($tr['status'], 'Hold')) $b = 'bg-info text-dark';
                                                elseif (str_contains($tr['status'], 'Rejected')) $b = 'bg-danger';
                                                ?>
                                                <span class="badge <?= $b ?>"><?= htmlspecialchars($tr['status']) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- /tab-content -->
                </div><!-- /card -->

                <div class="mt-4 pb-4">
                    <a href="students.php" class="btn btn-light border fw-semibold d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; transition: all 0.2s;">
                        <i class="bi bi-arrow-left"></i> Back to Student Directory
                    </a>
                </div>
            </div>
</div><!-- /row -->
    </main>
</div>

<!-- Initiate Transfer Modal -->
<div class="modal fade" id="initiateTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="initiateTransferForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-down-up me-2"></i>Initiate Branch Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                <input type="hidden" name="origin_branch_id" value="<?= $student['branch_id'] ?>">
                
                <div class="alert alert-warning mb-3 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Student is currently at <strong><?= htmlspecialchars($student['branch_name']) ?></strong>.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Destination Branch <span class="text-danger">*</span></label>
                    <select name="destination_branch_id" class="form-select" required>
                        <option value="">Select branch to transfer to...</option>
                        <?php foreach ($branches as $b): if ($b['id'] != $student['branch_id']): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reason for Transfer <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Briefly explain why the student is requesting a transfer..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnTransferSubmit"><i class="bi bi-send me-1"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('#initiateTransferForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#btnTransferSubmit');
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Submitting...');
    $.post('models/api/transfer_api.php?action=create', $(this).serialize(), function(res) {
        if (res.status === 'success') {
            Swal.fire('Transfer Initiated', 'Transfer ID: ' + res.transfer_id_str, 'success').then(() => {
                window.location.href = 'transfer_details.php?id=' + res.id;
            });
        } else {
            Swal.fire('Error', res.message, 'error');
            btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Submit Request');
        }
    }, 'json').fail(function() {
        Swal.fire('Error', 'Server connection failed', 'error');
        btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Submit Request');
    });
});
</script>
</body>
</html>
