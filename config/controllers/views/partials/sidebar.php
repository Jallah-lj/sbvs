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
$_isAdmin    = ($_role === 'Admin');
$_isTeacher  = ($_role === 'Teacher');
$_isOpsAdmin = ($_isBA || $_isAdmin);
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

        <!-- ────────────────────────────────────────────────────── -->
        <!-- SUPER ADMIN MENU  (executive / control-plane view)    -->
        <!-- ────────────────────────────────────────────────────── -->
        <?php if ($_isSA): ?>

        <div class="sidebar-section-label">Overview</div>

        <a href="dashboard.php" class="sidebar-link<?= _navActive('dashboard.php', $_activePage) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-label">Branch Management</div>

        <a href="branches.php" class="sidebar-link<?= _navActive('branches.php', $_activePage) ?>">
            <i class="bi bi-buildings"></i> Branches
        </a>

        <a href="manage_admins.php" class="sidebar-link<?= _navActive('manage_admins.php', $_activePage) ?>">
            <i class="bi bi-person-badge-fill"></i> Branch Admins
        </a>

        <a href="manage_super_admins.php" class="sidebar-link<?= _navActive('manage_super_admins.php', $_activePage) ?>">
            <i class="bi bi-person-gear"></i> Super Admins
        </a>

        <div class="sidebar-section-label">Academic Catalog</div>

        <a href="courses.php" class="sidebar-link<?= _navActive('courses.php', $_activePage) ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Courses
        </a>

        <a href="teachers.php" class="sidebar-link<?= _navActive('teachers.php', $_activePage) ?>">
            <i class="bi bi-person-video3"></i> Instructors
        </a>

        <div class="sidebar-section-label">Operations (Full Access)</div>

        <a href="student_registration.php" class="sidebar-link<?= _navActive('student_registration.php', $_activePage) ?>">
            <i class="bi bi-person-plus-fill"></i> New Enrollment
        </a>

        <a href="students.php" class="sidebar-link<?= _navActive('students.php', $_activePage) ?>">
            <i class="bi bi-people-fill"></i> Students
        </a>

        <a href="attendance.php" class="sidebar-link<?= _navActive('attendance.php', $_activePage) ?>">
            <i class="bi bi-calendar-check-fill"></i> Attendance
        </a>

        <a href="payments.php" class="sidebar-link<?= _navActive('payments.php', $_activePage) ?>">
            <i class="bi bi-cash-stack"></i> Payments &amp; Receipts
        </a>

        <a href="salary.php" class="sidebar-link<?= _navActive('salary.php', $_activePage) ?>">
            <i class="bi bi-wallet2"></i> Salary
        </a>

        <div class="sidebar-section-label">Global Reports</div>

        <a href="reports.php" class="sidebar-link<?= _navActive('reports.php', $_activePage) ?>">
            <i class="bi bi-graph-up-arrow"></i> Reports &amp; Analytics
        </a>

        <div class="sidebar-section-label">Workflows &amp; Governance</div>

        <a href="instructor_approval_dashboard.php" class="sidebar-link<?= _navActive('instructor_approval_dashboard.php', $_activePage) ?>">
            <i class="bi bi-kanban-fill"></i> Instructor Approvals
        </a>

        <a href="transfers.php" class="sidebar-link<?= _navActive('transfers.php', $_activePage) ?>">
            <i class="bi bi-arrow-left-right"></i> Transfers
        </a>

        <div class="sidebar-section-label">System</div>

        <a href="system_settings.php" class="sidebar-link<?= _navActive('system_settings.php', $_activePage) ?>">
            <i class="bi bi-gear-wide-connected"></i> System Settings
        </a>

        <!-- ────────────────────────────────────────────────────── -->
        <!-- BRANCH ADMIN MENU  (operations / execution view)      -->
        <!-- ────────────────────────────────────────────────────── -->
        <?php elseif ($_isOpsAdmin): ?>

        <div class="sidebar-section-label">Overview</div>

        <a href="dashboard.php" class="sidebar-link<?= _navActive('dashboard.php', $_activePage) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-label">Admissions</div>

        <a href="student_registration.php" class="sidebar-link<?= _navActive('student_registration.php', $_activePage) ?>">
            <i class="bi bi-person-plus-fill"></i> New Enrollment
        </a>

        <a href="students.php" class="sidebar-link<?= _navActive('students.php', $_activePage) ?>">
            <i class="bi bi-people-fill"></i> Trainees
        </a>

        <div class="sidebar-section-label">Classes &amp; Attendance</div>

        <a href="attendance.php" class="sidebar-link<?= _navActive('attendance.php', $_activePage) ?>">
            <i class="bi bi-calendar-check-fill"></i> Attendance
        </a>

        <a href="teachers.php" class="sidebar-link<?= _navActive('teachers.php', $_activePage) ?>">
            <i class="bi bi-person-video3"></i> Instructors
        </a>

        <a href="courses.php" class="sidebar-link<?= _navActive('courses.php', $_activePage) ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Courses
        </a>

        <div class="sidebar-section-label">Finance</div>

        <a href="payments.php" class="sidebar-link<?= _navActive('payments.php', $_activePage) ?>">
            <i class="bi bi-cash-stack"></i> Payments &amp; Receipts
        </a>

        <a href="salary.php" class="sidebar-link<?= _navActive('salary.php', $_activePage) ?>">
            <i class="bi bi-wallet2"></i> Salary
        </a>

        <div class="sidebar-section-label">Reports</div>

        <a href="reports.php" class="sidebar-link<?= _navActive('reports.php', $_activePage) ?>">
            <i class="bi bi-graph-up"></i> Local Reports
        </a>

        <div class="sidebar-section-label">Support &amp; Requests</div>

        <a href="instructor_approval_dashboard.php" class="sidebar-link<?= _navActive('instructor_approval_dashboard.php', $_activePage) ?>">
            <i class="bi bi-clipboard2-check-fill"></i> Instructor Approvals
        </a>

        <a href="transfers.php" class="sidebar-link<?= _navActive('transfers.php', $_activePage) ?>">
            <i class="bi bi-arrow-left-right"></i> Transfer Requests
        </a>

        <?php elseif ($_isTeacher): ?>
        <!-- TEACHER MENU (classroom / learner-centric view) -->

        <div class="sidebar-section-label">My Workspace</div>

        <a href="dashboard.php" class="sidebar-link<?= _navActive('dashboard.php', $_activePage) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <a href="students.php" class="sidebar-link<?= _navActive('students.php', $_activePage) ?>">
            <i class="bi bi-people-fill"></i> My Learners
        </a>

        <a href="instructor_competency_matrix.php" class="sidebar-link<?= _navActive('instructor_competency_matrix.php', $_activePage) ?>">
            <i class="bi bi-grid-3x3-gap-fill"></i> Competency Matrix
        </a>

        <a href="instructor_attendance_logger.php" class="sidebar-link<?= _navActive('instructor_attendance_logger.php', $_activePage) ?>">
            <i class="bi bi-stopwatch-fill"></i> Attendance Logger
        </a>

        <a href="courses.php" class="sidebar-link<?= _navActive('courses.php', $_activePage) ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Course Library
        </a>

        <a href="material_requisition.php" class="sidebar-link<?= _navActive('material_requisition.php', $_activePage) ?>">
            <i class="bi bi-box-seam"></i> Material Requisition
        </a>

        <a href="instructor_resource_links.php" class="sidebar-link<?= _navActive('instructor_resource_links.php', $_activePage) ?>">
            <i class="bi bi-link-45deg"></i> Resource Submissions
        </a>

        <a href="equipment_fault_reporter.php" class="sidebar-link<?= _navActive('equipment_fault_reporter.php', $_activePage) ?>">
            <i class="bi bi-tools"></i> Equipment Faults
        </a>

        <div class="sidebar-section-label">Professional Profile</div>

        <a href="teachers.php" class="sidebar-link<?= _navActive('teachers.php', $_activePage) ?>">
            <i class="bi bi-person-video3"></i> Instructor Directory
        </a>

        <a href="reports.php" class="sidebar-link<?= _navActive('reports.php', $_activePage) ?>">
            <i class="bi bi-graph-up"></i> Progress Reports
        </a>

        <?php else: ?>
        <!-- Fallback for other roles -->
        <div class="sidebar-section-label">Main</div>
        <a href="dashboard.php" class="sidebar-link<?= _navActive('dashboard.php', $_activePage) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
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

<!-- ── Mobile bottom navigation bar (shown only ≤991px) ──────────────── -->
<nav class="mobile-bottom-nav">
    <a href="dashboard.php"  class="bnav-item<?= _navActive('dashboard.php',  $_activePage) ?>"><i class="bi bi-speedometer2"></i>Home</a>

    <?php if ($_isOpsAdmin): ?>
    <a href="students.php"   class="bnav-item<?= _navActive('students.php',   $_activePage) ?>"><i class="bi bi-people-fill"></i>Students</a>
    <a href="attendance.php" class="bnav-item<?= _navActive('attendance.php', $_activePage) ?>"><i class="bi bi-calendar-check-fill"></i>Attend.</a>
    <a href="payments.php"   class="bnav-item<?= _navActive('payments.php',   $_activePage) ?>"><i class="bi bi-cash-stack"></i>Finance</a>

    <?php elseif ($_isSA): ?>
    <a href="students.php"   class="bnav-item<?= _navActive('students.php',   $_activePage) ?>"><i class="bi bi-people-fill"></i>Students</a>
    <a href="branches.php"   class="bnav-item<?= _navActive('branches.php',   $_activePage) ?>"><i class="bi bi-buildings"></i>Branches</a>
    <a href="reports.php"    class="bnav-item<?= _navActive('reports.php',    $_activePage) ?>"><i class="bi bi-graph-up-arrow"></i>Reports</a>

    <?php elseif ($_isTeacher): ?>
    <a href="students.php"   class="bnav-item<?= _navActive('students.php',   $_activePage) ?>"><i class="bi bi-people-fill"></i>Learners</a>
    <a href="instructor_competency_matrix.php" class="bnav-item<?= _navActive('instructor_competency_matrix.php', $_activePage) ?>"><i class="bi bi-grid-3x3-gap-fill"></i>CBT</a>
    <a href="instructor_attendance_logger.php" class="bnav-item<?= _navActive('instructor_attendance_logger.php', $_activePage) ?>"><i class="bi bi-stopwatch-fill"></i>Hours</a>

    <?php else: ?>
    <a href="students.php"   class="bnav-item<?= _navActive('students.php',   $_activePage) ?>"><i class="bi bi-people-fill"></i>Students</a>
    <a href="courses.php"    class="bnav-item<?= _navActive('courses.php',    $_activePage) ?>"><i class="bi bi-journal-bookmark-fill"></i>Courses</a>
    <?php endif; ?>

    <a href="#" class="bnav-item" onclick="event.preventDefault(); document.querySelector('.sidebar').classList.add('open'); document.querySelector('.sidebar-overlay').classList.add('show');">
        <i class="bi bi-grid-3x3-gap-fill"></i>Menu
    </a>
</nav>

