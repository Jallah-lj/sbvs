<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: teachers.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

// ── Core teacher info (via teachers.id) ──────────────────────────────────────
$stmt = $db->prepare(
    "SELECT t.id AS teacher_id, t.phone, t.specialization, t.status,
            u.id AS user_id, u.name, u.email, u.role, u.created_at,
            b.name AS branch_name
     FROM teachers t
     JOIN users    u ON t.user_id   = u.id
     JOIN branches b ON t.branch_id = b.id
     WHERE t.id = ?"
);
$stmt->execute([(int)$_GET['id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header("Location: teachers.php");
    exit;
}

// ── Assigned batches (via branch + teacher's specialization linkage) ──────────
// Batches in the same branch as the teacher
$bStmt = $db->prepare(
    "SELECT ba.id, ba.name AS batch_name, ba.start_date, ba.end_date,
            c.name AS course_name, c.duration, c.fees
     FROM batches ba
     JOIN courses c ON ba.course_id = c.id
     JOIN teachers t ON ba.branch_id = t.branch_id
     WHERE t.id = ?
     ORDER BY ba.start_date DESC"
);
$bStmt->execute([(int)$_GET['id']]);
$batches = $bStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Student count for this teacher's branch ───────────────────────────────────
$scStmt = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM students s
     JOIN teachers t ON s.branch_id = t.branch_id
     WHERE t.id = ?"
);
$scStmt->execute([(int)$_GET['id']]);
$studentCount = (int)$scStmt->fetchColumn();
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Instructor Profile';
$activePage = 'teacher_profile.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="teachers.php" class="text-decoration-none"><i class="bi bi-person-badge-fill me-1"></i> Instructors</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($teacher['name']) ?> (<code>INS-<?= str_pad($teacher['teacher_id'], 4, '0', STR_PAD_LEFT) ?></code>)</li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- ── Left: Profile Card ─────────────────────────────────── -->
            <div class="col-md-4 col-lg-3">
                <!-- Identity card -->
                <div class="card shadow-sm border-0 mb-4 pb-2">
                    <div class="card-body text-center pt-5 pb-4 px-4">
                        <div class="avatar-initials mx-auto mb-3 position-relative">
                            <?= strtoupper(substr($teacher['name'], 0, 1)) ?>
                            <span class="position-absolute bottom-0 end-0 p-2 bg-white border border-white rounded-circle">
                                <span class="d-inline-block bg-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?> rounded-circle" style="width: 12px; height: 12px;"></span>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark fs-4" style="letter-spacing: -0.02em;"><?= htmlspecialchars($teacher['name']) ?></h5>
                        <p class="text-primary fw-medium small mb-3"><?= htmlspecialchars($teacher['specialization']) ?></p>
                        <span class="badge <?= $teacher['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 text-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?> border border-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?> border-opacity-25 px-3 py-2 rounded-pill shadow-sm mb-4">
                            <?= htmlspecialchars($teacher['status']) ?> Instructor
                        </span>

                        <div class="text-start mt-4 pt-3 border-top border-light">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light text-muted rounded p-2 me-3"><i class="bi bi-building"></i></div>
                                <div>
                                    <div class="small text-muted fw-semibold">Branch</div>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($teacher['branch_name']) ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light text-muted rounded p-2 me-3"><i class="bi bi-envelope"></i></div>
                                <div>
                                    <div class="small text-muted fw-semibold">Email</div>
                                    <div class="fw-medium text-dark"><a href="mailto:<?= htmlspecialchars($teacher['email']) ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($teacher['email']) ?></a></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-light text-muted rounded p-2 me-3"><i class="bi bi-telephone"></i></div>
                                <div>
                                    <div class="small text-muted fw-semibold">Phone</div>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($teacher['phone'] ?? '—') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats pills -->
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="stat-pill shadow-sm">
                        <div class="title">Batches in Branch</div>
                        <div class="value text-primary"><?= count($batches) ?></div>
                    </div>
                    <div class="stat-pill shadow-sm">
                        <div class="title">Students in Branch</div>
                        <div class="value text-success"><?= $studentCount ?></div>
                    </div>
                </div>
            </div>

            <!-- ── Right: Tabs ────────────────────────────────────────── -->
            <div class="col-md-8 col-lg-9">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pt-0 pb-0">
                        <ul class="nav nav-tabs card-header-tabs" style="transform: translateY(1px);">
                            <li class="nav-item">
                                <button class="nav-link active py-3" data-bs-toggle="tab" data-bs-target="#tab-overview">
                                    <i class="bi bi-person-lines-fill me-2"></i>Overview
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link py-3" data-bs-toggle="tab" data-bs-target="#tab-batches">
                                    <i class="bi bi-collection-fill me-2"></i>Assigned Batches
                                    <span class="badge bg-primary text-white ms-2 rounded-pill"><?= count($batches) ?></span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body tab-content p-3 p-md-4">

                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="tab-overview">
                            <h6 class="fw-bold text-dark mb-4" style="letter-spacing: -0.01em;"><i class="bi bi-person-vcard me-2 text-primary"></i>Professional Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-0">
                                    <tbody class="text-muted">
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark w-25">Full Name</th>
                                            <td class="py-3"><?= htmlspecialchars($teacher['name']) ?></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Employee ID</th>
                                            <td class="py-3"><code class="fs-6 bg-light text-primary px-2 py-1 rounded">INS-<?= str_pad($teacher['teacher_id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Email Address</th>
                                            <td class="py-3"><?= htmlspecialchars($teacher['email']) ?></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Phone</th>
                                            <td class="py-3"><?= htmlspecialchars($teacher['phone'] ?? '—') ?></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Specialization</th>
                                            <td class="py-3"><?= htmlspecialchars($teacher['specialization']) ?></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Assigned Branch</th>
                                            <td class="py-3"><span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($teacher['branch_name']) ?></span></td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">Employment Status</th>
                                            <td class="py-3">
                                                <span class="badge <?= $teacher['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 text-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?> border border-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?> border-opacity-25 px-2 py-1">
                                                    <?= htmlspecialchars($teacher['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="border-bottom border-light">
                                            <th class="ps-0 py-3 fw-medium text-dark">System Role</th>
                                            <td class="py-3"><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><?= htmlspecialchars($teacher['role']) ?> Portal</span></td>
                                        </tr>
                                        <tr>
                                            <th class="ps-0 py-3 fw-medium text-dark">Account Created</th>
                                            <td class="py-3"><?= date('d M Y', strtotime($teacher['created_at'])) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Batches Tab -->
                        <div class="tab-pane fade" id="tab-batches">
                            <h6 class="fw-bold text-dark mb-4" style="letter-spacing: -0.01em;"><i class="bi bi-collection me-2 text-primary"></i>Branch Batches</h6>
                            <?php if (empty($batches)): ?>
                                <div class="alert alert-primary bg-primary bg-opacity-10 border-0 text-primary py-3 rounded-3 d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                                    <div>No batches currently in this instructor's branch.</div>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle w-100 mb-0">
                                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.05em;">
                                        <tr>
                                            <th class="ps-3 fw-semibold py-3 border-bottom-0">Batch Name</th>
                                            <th class="fw-semibold py-3 border-bottom-0">Course</th>
                                            <th class="fw-semibold py-3 border-bottom-0">Duration</th>
                                            <th class="fw-semibold py-3 border-bottom-0">Fees</th>
                                            <th class="fw-semibold py-3 border-bottom-0">Start Date</th>
                                            <th class="pe-3 fw-semibold py-3 border-bottom-0">End Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($batches as $b): ?>
                                        <tr>
                                            <td class="ps-3 py-3 fw-bold text-dark"><?= htmlspecialchars($b['batch_name']) ?></td>
                                            <td class="py-3 text-muted"><?= htmlspecialchars($b['course_name']) ?></td>
                                            <td class="py-3 text-muted"><?= htmlspecialchars($b['duration']) ?></td>
                                            <td class="py-3"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-bold fs-6">$<?= number_format($b['fees'], 2) ?></span></td>
                                            <td class="py-3 text-muted"><?= date('M d, Y', strtotime($b['start_date'])) ?></td>
                                            <td class="pe-3 py-3 text-muted"><?= date('M d, Y', strtotime($b['end_date'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- /tab-content -->
                </div><!-- /card -->

                <div class="mt-4">
                    <a href="teachers.php" class="btn btn-light text-muted fw-medium px-4 py-2 border shadow-sm rounded-pill" style="font-size: 0.95rem;">
                        <i class="bi bi-arrow-left me-2"></i> Back to Instructors Directory
                    </a>
                </div>
            </div>
        </div><!-- /row -->
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
