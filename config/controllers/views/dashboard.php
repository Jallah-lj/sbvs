<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$role      = $_SESSION['role'] ?? '';
$branchId  = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin   = ($role === 'Super Admin');
$isBranchAdmin  = ($role === 'Branch Admin');

// Fetch current branch name for Branch Admin header
$branchName = '';
if (!$isSuperAdmin && $branchId) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$branchId]);
    $branchName = $bStmt->fetchColumn() ?: '';
}

// ── KPI counters ──────────────────────────────────────────────────────────────
if ($isSuperAdmin) {
    $kpi = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM branches  WHERE status='Active')   AS branches,
            (SELECT COUNT(*) FROM students)                           AS students,
            (SELECT COUNT(*) FROM teachers  WHERE status='Active')    AS teachers,
            (SELECT COUNT(*) FROM courses)                            AS courses,
            (SELECT COALESCE(SUM(amount),0) FROM payments
                 WHERE MONTH(payment_date)=MONTH(CURDATE())
                   AND YEAR(payment_date)=YEAR(CURDATE()))            AS monthly_rev,
            (SELECT COALESCE(SUM(amount),0) FROM payments)            AS total_rev"
    )->fetch(PDO::FETCH_ASSOC);
} else {
    $kpiStmt = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM students   WHERE branch_id = ?)     AS students,
            (SELECT COUNT(*) FROM teachers   WHERE branch_id = ? AND status='Active') AS teachers,
            (SELECT COUNT(*) FROM courses    WHERE branch_id = ?)     AS courses,
            (SELECT COUNT(*) FROM enrollments e
                JOIN students s ON e.student_id = s.id
                WHERE s.branch_id = ? AND e.status = 'Active')        AS enrollments,
            (SELECT COALESCE(SUM(amount),0) FROM payments
                 WHERE branch_id = ?
                   AND MONTH(payment_date)=MONTH(CURDATE())
                   AND YEAR(payment_date)=YEAR(CURDATE()))             AS monthly_rev,
            (SELECT COALESCE(SUM(amount),0) FROM payments
                 WHERE branch_id = ?)                                  AS total_rev"
    );
    $kpiStmt->execute([$branchId, $branchId, $branchId, $branchId, $branchId, $branchId]);
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);
}

// ── Enrollment per branch / gender split (bar chart) ─────────────────────────
if ($isSuperAdmin) {
    $branchRows = $db->query(
        "SELECT b.name, COUNT(s.id) AS cnt
         FROM branches b
         LEFT JOIN students s ON s.branch_id = b.id
         GROUP BY b.id, b.name
         ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $branchStmt = $db->prepare(
        "SELECT c.name, COUNT(e.id) AS cnt
         FROM courses c
         LEFT JOIN enrollments e ON e.course_id = c.id
            JOIN students s ON e.student_id = s.id AND s.branch_id = ?
         WHERE c.branch_id = ?
         GROUP BY c.id, c.name
         ORDER BY cnt DESC"
    );
    $branchStmt->execute([$branchId, $branchId]);
    $branchRows = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
}
$branchLabels = json_encode(array_column($branchRows, 'name'));
$branchCounts = json_encode(array_column($branchRows, 'cnt'));

// ── 6-month revenue trend (line chart) ───────────────────────────────────────
if ($isSuperAdmin) {
    $revRows = $db->query(
        "SELECT DATE_FORMAT(payment_date,'%b %Y') AS lbl, SUM(amount) AS rev
         FROM payments
         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY YEAR(payment_date), MONTH(payment_date)
         ORDER BY YEAR(payment_date), MONTH(payment_date)"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $revStmt = $db->prepare(
        "SELECT DATE_FORMAT(payment_date,'%b %Y') AS lbl, SUM(amount) AS rev
         FROM payments
         WHERE branch_id = ?
           AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY YEAR(payment_date), MONTH(payment_date)
         ORDER BY YEAR(payment_date), MONTH(payment_date)"
    );
    $revStmt->execute([$branchId]);
    $revRows = $revStmt->fetchAll(PDO::FETCH_ASSOC);
}
$revLabels = json_encode(array_column($revRows, 'lbl'));
$revData   = json_encode(array_column($revRows, 'rev'));

// ── Course enrollment share (doughnut) ───────────────────────────────────────
if ($isSuperAdmin) {
    $courseRows = $db->query(
        "SELECT c.name, COUNT(e.id) AS cnt
         FROM courses c
         LEFT JOIN enrollments e ON e.course_id = c.id
         GROUP BY c.id, c.name
         ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $courseStmt = $db->prepare(
        "SELECT c.name, COUNT(e.id) AS cnt
         FROM courses c
         LEFT JOIN enrollments e ON e.course_id = c.id
         WHERE c.branch_id = ?
         GROUP BY c.id, c.name
         ORDER BY cnt DESC"
    );
    $courseStmt->execute([$branchId]);
    $courseRows = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
}
$courseLabels = json_encode(array_column($courseRows, 'name'));
$courseCounts = json_encode(array_column($courseRows, 'cnt'));

// ── Recent students (5) ───────────────────────────────────────────────────────
if ($isSuperAdmin) {
    $recentStudents = $db->query(
        "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
         FROM students s
         JOIN users u    ON s.user_id   = u.id
         JOIN branches b ON s.branch_id = b.id
         ORDER BY s.registration_date DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rsStmt = $db->prepare(
        "SELECT u.name, s.student_id, b.name AS branch, s.registration_date
         FROM students s
         JOIN users u    ON s.user_id   = u.id
         JOIN branches b ON s.branch_id = b.id
         WHERE s.branch_id = ?
         ORDER BY s.registration_date DESC LIMIT 5"
    );
    $rsStmt->execute([$branchId]);
    $recentStudents = $rsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Recent payments (5) ───────────────────────────────────────────────────────
if ($isSuperAdmin) {
    $recentPayments = $db->query(
        "SELECT u.name, p.amount, p.payment_method, p.payment_date
         FROM payments p
         JOIN students s ON p.student_id = s.id
         JOIN users    u ON s.user_id    = u.id
         ORDER BY p.payment_date DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rpStmt = $db->prepare(
        "SELECT u.name, p.amount, p.payment_method, p.payment_date
         FROM payments p
         JOIN students s ON p.student_id = s.id
         JOIN users    u ON s.user_id    = u.id
         WHERE p.branch_id = ?
         ORDER BY p.payment_date DESC LIMIT 5"
    );
    $rpStmt->execute([$branchId]);
    $recentPayments = $rpStmt->fetchAll(PDO::FETCH_ASSOC);
}

$userName = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
$userRole = htmlspecialchars($role);
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Dashboard';
$activePage = 'dashboard.php';
// KPI icon color helpers (dashboard-specific)
$extraCss = <<<CSS
<style>
    .kpi-blue   { background: rgba(99,102,241,0.1);  color: #6366f1; }
    .kpi-green  { background: rgba(16,185,129,0.1);  color: #10b981; }
    .kpi-orange { background: rgba(245,158,11,0.1);  color: #f59e0b; }
    .kpi-purple { background: rgba(216,55,132,0.1);  color: #d63384; }
</style>
CSS;
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- ── Gradient Hero Banner ────────────────────── -->
        <div class="page-header fade-up">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
                <div>
                    <h4 class="fw-bold mb-1" style="letter-spacing:-0.02em;">Welcome back, <?= explode(' ', trim($userName))[0] ?>! 👋</h4>
                    <p class="mb-0 opacity-75" style="font-size:.9rem;"><?= date('l, F j, Y') ?> — <?= $userRole ?>
                        <?= $isBranchAdmin && $branchName ? ' • ' . htmlspecialchars($branchName) . ' Branch' : '' ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm text-white" style="background:rgba(255,255,255,0.18);width:42px;height:42px;font-size:.95rem;">
                        <?= strtoupper(substr($userName, 0, 1)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── KPI Cards ───────────────────────────────── -->
        <div class="row g-3 mb-4">
            <?php if ($isSuperAdmin): ?>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-buildings"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#6366f1;"><?= (int)$kpi['branches'] ?></div>
                            <div class="kpi-label">Branches</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-person-check"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#10b981;"><?= (int)$kpi['enrollments'] ?></div>
                            <div class="kpi-label">Active Enroll</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#10b981;"><?= (int)$kpi['students'] ?></div>
                            <div class="kpi-label">Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(14,165,233,0.1);color:#0ea5e9;"><i class="bi bi-person-badge-fill"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#0ea5e9;"><?= (int)$kpi['teachers'] ?></div>
                            <div class="kpi-label">Teachers</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="bi bi-journal-bookmark-fill"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#f59e0b;"><?= (int)$kpi['courses'] ?></div>
                            <div class="kpi-label">Courses</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(34,197,94,0.1);color:#22c55e;"><i class="bi bi-cash-coin"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#22c55e;">$<?= number_format($kpi['monthly_rev'], 0) ?></div>
                            <div class="kpi-label">This Month</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-2 fade-up">
                <div class="card kpi-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="kpi-value" style="color:#6366f1;">$<?= number_format($kpi['total_rev'], 0) ?></div>
                            <div class="kpi-label">Total Revenue</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Super Admin Quick Actions ───────────────── -->
        <?php if ($isSuperAdmin): ?>
        <div class="card quick-actions mb-4 fade-up">
            <div class="card-body py-3 d-flex flex-wrap align-items-center gap-3">
                <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold" style="font-size:.9rem;">Super Admin Controls</div>
                    <div class="text-muted" style="font-size:.78rem;">Manage administrators and system-level settings</div>
                </div>
                <a href="manage_admins.php" class="btn btn-sm px-3" style="background:#6366f1;color:#fff;font-weight:600;border-radius:8px;">
                    <i class="bi bi-shield-lock me-1"></i> Branch Admins
                </a>
                <a href="manage_super_admins.php" class="btn btn-sm btn-outline-secondary px-3" style="border-radius:8px;font-weight:600;">
                    <i class="bi bi-person-gear me-1"></i> Super Admins
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Charts ──────────────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-lg-5 fade-up">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-1" style="color:#6366f1;"></i> <?= $isSuperAdmin ? 'Students per Branch' : 'Enrollments per Course' ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap"><canvas id="branchChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 fade-up">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-1" style="color:#10b981;"></i> Revenue Trend</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 fade-up">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart-fill me-1" style="color:#f59e0b;"></i> Course Share</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="chart-wrap"><canvas id="courseChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent Activity ─────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6 fade-up">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill me-1" style="color:#6366f1;"></i> Recent Registrations</h6>
                        <a href="students.php" class="btn btn-sm" style="background:var(--accent-light);color:#6366f1;font-weight:600;border-radius:8px;">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentStudents)): ?>
                            <p class="text-muted small p-4 mb-0 text-center">No students registered yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentStudents as $s): ?>
                            <div class="activity-item">
                                <div class="avatar-sm" style="background:rgba(99,102,241,0.1);color:#6366f1;"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-semibold text-truncate" style="font-size:.875rem;"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($s['branch']) ?> · <?= htmlspecialchars($s['student_id']) ?></div>
                                </div>
                                <span class="badge" style="background:var(--accent-light);color:#6366f1;font-weight:600;font-size:.72rem;"><?= date('M j', strtotime($s['registration_date'])) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 fade-up">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-1" style="color:#10b981;"></i> Recent Payments</h6>
                        <a href="payments.php" class="btn btn-sm" style="background:rgba(16,185,129,0.1);color:#10b981;font-weight:600;border-radius:8px;">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentPayments)): ?>
                            <p class="text-muted small p-4 mb-0 text-center">No payments recorded yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $p): ?>
                            <div class="activity-item">
                                <div class="avatar-sm" style="background:rgba(16,185,129,0.1);color:#10b981;"><?= strtoupper(substr($p['name'],0,1)) ?></div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-semibold text-truncate" style="font-size:.875rem;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($p['payment_method']) ?> · <?= date('M j', strtotime($p['payment_date'])) ?></div>
                                </div>
                                <span class="fw-bold" style="color:#10b981;font-size:.9rem;">$<?= number_format($p['amount'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const font = "'Inter', sans-serif";
    Chart.defaults.font.family = font;
    Chart.defaults.color = '#64748b';

    // ── Branch / Enrollment Bar Chart ─────────────────
    const branchCtx = document.getElementById('branchChart').getContext('2d');
    const branchGrad = branchCtx.createLinearGradient(0, 0, 0, 260);
    branchGrad.addColorStop(0, 'rgba(99,102,241,0.8)');
    branchGrad.addColorStop(1, 'rgba(99,102,241,0.2)');

    new Chart(branchCtx, {
        type: 'bar',
        data: {
            labels: <?= $branchLabels ?>,
            datasets: [{
                label: '<?= $isSuperAdmin ? "Students" : "Enrollments" ?>',
                data: <?= $branchCounts ?>,
                backgroundColor: branchGrad,
                borderColor: '#6366f1',
                borderWidth: 0,
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 28,
                hoverBackgroundColor: '#6366f1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { weight: '600' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    border: { display: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });

    // ── Revenue Line Chart ────────────────────────────
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    const revGrad = revCtx.createLinearGradient(0, 0, 0, 260);
    revGrad.addColorStop(0, 'rgba(16,185,129,0.25)');
    revGrad.addColorStop(1, 'rgba(16,185,129,0.01)');

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: <?= $revLabels ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?= $revData ?>,
                borderColor: '#10b981',
                backgroundColor: revGrad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { weight: '600' },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: ctx => ' $' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    border: { display: false },
                    ticks: {
                        callback: v => '$' + v.toLocaleString()
                    }
                }
            }
        }
    });

    // ── Course Doughnut ───────────────────────────────
    const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#0ea5e9','#ec4899','#8b5cf6','#14b8a6'];

    new Chart(document.getElementById('courseChart'), {
        type: 'doughnut',
        data: {
            labels: <?= $courseLabels ?>,
            datasets: [{
                data: <?= $courseCounts ?>,
                backgroundColor: palette.slice(0, <?= count($courseRows) ?>),
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: { size: 11, weight: '500' }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    cornerRadius: 8
                }
            }
        }
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>