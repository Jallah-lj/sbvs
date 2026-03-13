<?php
/**
 * Partial: sidebar.php
 *
 * Expected session variables (set at login):
 *   $_SESSION['logged_in'], $_SESSION['role'], $_SESSION['name'] or $_SESSION['user_name']
 *   $_SESSION['branch_id']   (for Branch Admin / Admin)
 *
 * Expected page variable (set by including page):
 *   $activePage  (string) – filename, e.g. 'dashboard.php', used to set .active class
 */

$_role       = $_SESSION['role']      ?? '';
$_userName   = $_SESSION['name']      ?? ($_SESSION['user_name'] ?? 'User');
$_isSA       = ($_role === 'Super Admin');
$_isBA       = ($_role === 'Branch Admin');
$_activePage = $activePage ?? '';

// Helper: mark nav link active
function _navActive(string $page, string $active): string {
    return basename($active, '.php') === basename($page, '.php') ? ' active' : '';
}
?>

<!-- ── Mobile top bar ─────────────────────────────────────────────── -->
<nav class="mobile-navbar">
    <button class="mobile-toggle" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="sidebar-brand-name" style="color:#fff; font-size:0.95rem; font-weight:800;">
        SBVS Portal
    </span>
</nav>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay"></div>

<!-- ── Sidebar ────────────────────────────────────────────────────── -->
<aside class="sidebar">

    <!-- Brand -->
    <a href="dashboard.php" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-shield-shaded"></i>
        </div>
        <div class="sidebar-brand-text">
            <div class="sidebar-brand-name">Shining Bright</div>
            <div class="sidebar-brand-sub">Vocational School</div>
        </div>
    </a>

    <!-- User info -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <?= strtoupper(substr(htmlspecialchars($_userName), 0, 1)) ?>
        </div>
        <div style="min-width:0;">
            <div class="sidebar-user-name"><?= htmlspecialchars($_userName) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($_role) ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- Main -->
        <div class="sidebar-section-label">Main</div>

        <a href="dashboard.php" class="sidebar-link<?= _navActive('dashboard.php', $_activePage) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <!-- Academic -->
        <div class="sidebar-section-label">Academic</div>

        <a href="students.php" class="sidebar-link<?= _navActive('students.php', $_activePage) ?>">
            <i class="bi bi-people-fill"></i> Students
        </a>

        <a href="student_registration.php" class="sidebar-link<?= _navActive('student_registration.php', $_activePage) ?>">
            <i class="bi bi-person-vcard"></i> Registration
        </a>

        <a href="teachers.php" class="sidebar-link<?= _navActive('teachers.php', $_activePage) ?>">
            <i class="bi bi-person-badge-fill"></i> Teachers
        </a>

        <a href="courses.php" class="sidebar-link<?= _navActive('courses.php', $_activePage) ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Courses
        </a>

        <a href="batches.php" class="sidebar-link<?= _navActive('batches.php', $_activePage) ?>">
            <i class="bi bi-collection-fill"></i> Batches
        </a>

        <!-- Finance -->
        <div class="sidebar-section-label">Finance</div>

        <a href="payments.php" class="sidebar-link<?= _navActive('payments.php', $_activePage) ?>">
            <i class="bi bi-cash-stack"></i> Payments
        </a>

        <a href="salary.php" class="sidebar-link<?= _navActive('salary.php', $_activePage) ?>">
            <i class="bi bi-wallet2"></i> Salary
        </a>

        <a href="reports.php" class="sidebar-link<?= _navActive('reports.php', $_activePage) ?>">
            <i class="bi bi-graph-up"></i> Reports
        </a>

        <!-- Operations -->
        <?php if ($_isSA || $_isBA): ?>
        <div class="sidebar-section-label">Operations</div>

        <a href="branches.php" class="sidebar-link<?= _navActive('branches.php', $_activePage) ?>">
            <i class="bi bi-buildings"></i> Branches
        </a>

        <a href="transfers.php" class="sidebar-link<?= _navActive('transfers.php', $_activePage) ?>">
            <i class="bi bi-arrow-left-right"></i> Transfers
        </a>

        <?php endif; ?>

        <!-- Administration -->
        <?php if ($_isSA): ?>
        <div class="sidebar-section-label">Administration</div>

        <a href="manage_admins.php" class="sidebar-link<?= _navActive('manage_admins.php', $_activePage) ?>">
            <i class="bi bi-shield-lock-fill"></i> Branch Admins
        </a>

        <a href="manage_super_admins.php" class="sidebar-link<?= _navActive('manage_super_admins.php', $_activePage) ?>">
            <i class="bi bi-person-gear"></i> Super Admins
        </a>
        <?php endif; ?>

    </nav>

    <!-- Footer / Logout -->
    <div class="sidebar-footer">
        <a href="models/api/LogoutController.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-left"></i> Sign Out
        </a>
    </div>

</aside>
