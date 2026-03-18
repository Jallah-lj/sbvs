<?php
ob_start();
session_start();
require_once '../../config.php';
require_once '../../database.php';
require_once '../../helpers.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php"); exit;
}

try {
    $db = (new Database())->getConnection();
    if (!$db) throw new Exception('DB failed');
} catch (Exception $e) {
    logError($e->getMessage(), 'dashboard_init');
    $dbError = getUserErrorMessage('DB_CONNECT');
}
$dbError = $dbError ?? '';

$role          = $_SESSION['role'] ?? '';
$branchId      = (int)($_SESSION['branch_id'] ?? 0);
$userId        = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');
$isOpsAdmin    = ($isBranchAdmin || $isAdmin);
$isTeacher     = ($role === 'Teacher');
$firstName     = explode(' ', trim($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin'))[0];

$branchName = '';
if (!$isSuperAdmin && $branchId && !$dbError) {
    try {
        $bq = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $bq->execute([$branchId]);
        $branchName = $bq->fetchColumn() ?: '';
    } catch (Exception $e) {}
}

// ── KPIs ──────────────────────────────────────────────────────
$kpi = ['branches'=>0,'students'=>0,'teachers'=>0,'courses'=>0,'monthly_rev'=>0,'total_rev'=>0,'enrollments'=>0];
if (!$dbError) {
    try {
        if ($isSuperAdmin) {
            $kpi = $db->query("SELECT
                (SELECT COUNT(*) FROM branches WHERE status='Active') AS branches,
                (SELECT COUNT(*) FROM students) AS students,
                (SELECT COUNT(*) FROM teachers WHERE status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses) AS courses,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount),0) FROM payments) AS total_rev"
            )->fetch(PDO::FETCH_ASSOC) ?: $kpi;
        } else {
            $ks = $db->prepare("SELECT
                (SELECT COUNT(*) FROM students WHERE branch_id=?) AS students,
                (SELECT COUNT(*) FROM teachers WHERE branch_id=? AND status='Active') AS teachers,
                (SELECT COUNT(*) FROM courses WHERE branch_id=?) AS courses,
                (SELECT COUNT(*) FROM enrollments e JOIN students s ON e.student_id=s.id WHERE s.branch_id=? AND e.status='Active') AS enrollments,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE branch_id=? AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())) AS monthly_rev,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE branch_id=?) AS total_rev");
            $ks->execute([$branchId,$branchId,$branchId,$branchId,$branchId,$branchId]);
            $kpi = $ks->fetch(PDO::FETCH_ASSOC) ?: $kpi;
        }
    } catch (Exception $e) { logError($e->getMessage(),'dashboard_kpi'); }
}

// ── Charts data ────────────────────────────────────────────────
try {
    if ($isSuperAdmin) {
        $branchRows = $db->query("SELECT b.name, COUNT(s.id) AS cnt FROM branches b LEFT JOIN students s ON s.branch_id=b.id GROUP BY b.id,b.name ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $brs = $db->prepare("SELECT c.name, COUNT(e.id) AS cnt FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id JOIN students s ON e.student_id=s.id AND s.branch_id=? WHERE c.branch_id=? GROUP BY c.id,c.name ORDER BY cnt DESC");
        $brs->execute([$branchId,$branchId]);
        $branchRows = $brs->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $branchRows = []; }

try {
    if ($isSuperAdmin) {
        $revRows = $db->query("SELECT DATE_FORMAT(payment_date,'%b') AS lbl, SUM(amount) AS rev FROM payments WHERE payment_date>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY YEAR(payment_date),MONTH(payment_date) ORDER BY YEAR(payment_date),MONTH(payment_date)")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rs = $db->prepare("SELECT DATE_FORMAT(payment_date,'%b') AS lbl, SUM(amount) AS rev FROM payments WHERE branch_id=? AND payment_date>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY YEAR(payment_date),MONTH(payment_date) ORDER BY YEAR(payment_date),MONTH(payment_date)");
        $rs->execute([$branchId]);
        $revRows = $rs->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $revRows = []; }

try {
    if ($isSuperAdmin) {
        $courseRows = $db->query("SELECT c.name, COUNT(e.id) AS cnt FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id GROUP BY c.id,c.name ORDER BY cnt DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $crs = $db->prepare("SELECT c.name, COUNT(e.id) AS cnt FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id WHERE c.branch_id=? GROUP BY c.id,c.name ORDER BY cnt DESC LIMIT 6");
        $crs->execute([$branchId]);
        $courseRows = $crs->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $courseRows = []; }

$branchLabels = json_encode(array_column($branchRows,'name'));
$branchCounts = json_encode(array_column($branchRows,'cnt'));
$revLabels    = json_encode(array_column($revRows,'lbl'));
$revData      = json_encode(array_column($revRows,'rev'));
$courseLabels = json_encode(array_column($courseRows,'name'));
$courseCounts = json_encode(array_column($courseRows,'cnt'));

// ── SA extras ─────────────────────────────────────────────────
$branchPerformance = []; $pendingApprovals = 0; $pendingTransfers = 0;
$topPrograms = []; $needsAttention = []; $recentAudit = [];
if ($isSuperAdmin && !$dbError) {
    try { $branchPerformance = $db->query("SELECT b.id, b.name, (SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id) AS total_students, (SELECT COUNT(*) FROM enrollments e JOIN students s ON e.student_id=s.id WHERE s.branch_id=b.id AND e.status='Active') AS active_enrollments, (SELECT COUNT(*) FROM enrollments e JOIN students s ON e.student_id=s.id WHERE s.branch_id=b.id AND e.status='Completed') AS completions, (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.branch_id=b.id AND MONTH(p.payment_date)=MONTH(CURDATE()) AND YEAR(p.payment_date)=YEAR(CURDATE())) AS monthly_rev FROM branches b WHERE b.status='Active' ORDER BY monthly_rev DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $pendingApprovals = (int)$db->query("SELECT COUNT(*) FROM discount_approvals WHERE status='Pending'")->fetchColumn(); } catch (Exception $e) {}
    try { $pendingTransfers = (int)$db->query("SELECT COUNT(*) FROM transfer_requests WHERE status='Pending Origin Approval'")->fetchColumn(); } catch (Exception $e) {}
    try { $topPrograms = $db->query("SELECT c.name, COUNT(e.id) AS total_enrollments, COUNT(DISTINCT s.branch_id) AS branches_offering FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id LEFT JOIN students s ON e.student_id=s.id GROUP BY c.id,c.name ORDER BY total_enrollments DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $needsAttention = $db->query("SELECT b.name, (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.branch_id=b.id AND MONTH(p.payment_date)=MONTH(CURDATE()) AND YEAR(p.payment_date)=YEAR(CURDATE())) AS monthly_rev, (SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id) AS total_students, (SELECT COUNT(*) FROM enrollments e JOIN students s ON e.student_id=s.id WHERE s.branch_id=b.id AND e.status='Active') AS active_enrollments FROM branches b WHERE b.status='Active' HAVING monthly_rev=0 OR (total_students>0 AND active_enrollments=0) ORDER BY b.name")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $recentAudit = $db->query("SELECT user_name, user_role, module, action, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
}

// ── Branch admin ops ───────────────────────────────────────────
$todayBatches=[]; $overduePayments=0; $pendingTransfersBA=0;
$todayAttSummary=['present'=>0,'absent'=>0,'late'=>0,'total'=>0];
$lowAttendanceCount=0; $newAdmissionsThisWeek=0;
if ($isOpsAdmin && !$dbError) {
    try { $tb=$db->prepare("SELECT b.batch_name, c.name AS course_name FROM batches b JOIN courses c ON b.course_id=c.id WHERE b.branch_id=? AND b.status='Active' ORDER BY b.batch_name LIMIT 6"); $tb->execute([$branchId]); $todayBatches=$tb->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $ov=$db->prepare("SELECT COUNT(*) FROM payments WHERE branch_id=? AND balance>0"); $ov->execute([$branchId]); $overduePayments=(int)$ov->fetchColumn(); } catch (Exception $e) {}
    try { $pt=$db->prepare("SELECT COUNT(*) FROM transfer_requests WHERE origin_branch_id=? AND status='Pending Origin Approval'"); $pt->execute([$branchId]); $pendingTransfersBA=(int)$pt->fetchColumn(); } catch (Exception $e) {}
    try { $at=$db->prepare("SELECT SUM(status='Present') AS present, SUM(status='Absent') AS absent, SUM(status='Late') AS late, COUNT(*) AS total FROM attendance WHERE branch_id=? AND attend_date=CURDATE()"); $at->execute([$branchId]); $todayAttSummary=$at->fetch(PDO::FETCH_ASSOC)?:$todayAttSummary; } catch (Exception $e) {}
    try { $la=$db->prepare("SELECT COUNT(DISTINCT student_id) FROM (SELECT student_id, SUM(status='Present')/COUNT(*)*100 AS att_rate FROM attendance WHERE branch_id=? GROUP BY student_id HAVING COUNT(*)>=5 AND att_rate<60) x"); $la->execute([$branchId]); $lowAttendanceCount=(int)$la->fetchColumn(); } catch (Exception $e) {}
    try { $na=$db->prepare("SELECT COUNT(*) FROM students WHERE branch_id=? AND registration_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)"); $na->execute([$branchId]); $newAdmissionsThisWeek=(int)$na->fetchColumn(); } catch (Exception $e) {}
}

// ── Recent activity ────────────────────────────────────────────
try {
    if ($isSuperAdmin) {
        $recentStudents = $db->query("SELECT u.name, s.student_id, b.name AS branch, s.registration_date FROM students s JOIN users u ON s.user_id=u.id JOIN branches b ON s.branch_id=b.id ORDER BY s.registration_date DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rss=$db->prepare("SELECT u.name, s.student_id, b.name AS branch, s.registration_date FROM students s JOIN users u ON s.user_id=u.id JOIN branches b ON s.branch_id=b.id WHERE s.branch_id=? ORDER BY s.registration_date DESC LIMIT 6"); $rss->execute([$branchId]); $recentStudents=$rss->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $recentStudents = []; }

try {
    if ($isSuperAdmin) {
        $recentPayments = $db->query("SELECT u.name, p.amount, p.payment_method, p.payment_date FROM payments p JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id ORDER BY p.payment_date DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rps=$db->prepare("SELECT u.name, p.amount, p.payment_method, p.payment_date FROM payments p JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.branch_id=? ORDER BY p.payment_date DESC LIMIT 6"); $rps->execute([$branchId]); $recentPayments=$rps->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $recentPayments = []; }

// ── Teacher profile ────────────────────────────────────────────
$teacherProfile = null;
$teacherKpi = ['active_batches'=>0,'learners_in_track'=>0,'attendance_today'=>0,'new_learners_30d'=>0];
$teacherBatches = []; $teacherRecentLearners = [];
$teacherAttendance7d = []; $teacherTrackMix = ['Active'=>0,'Completed'=>0,'Dropped'=>0];
if ($isTeacher && !$dbError) {
    try {
        $tp=$db->prepare("SELECT t.id, t.teacher_id, t.specialization, t.phone, t.status, t.photo_url, b.name AS branch_name, t.branch_id FROM teachers t LEFT JOIN branches b ON b.id=t.branch_id WHERE t.user_id=? LIMIT 1");
        $tp->execute([$userId]); $teacherProfile=$tp->fetch(PDO::FETCH_ASSOC)?:null;
    } catch (Exception $e) {}
    $teacherBranchId=(int)($teacherProfile['branch_id']??$branchId);
    $teacherSpec=trim((string)($teacherProfile['specialization']??''));
    try { $tb=$db->prepare("SELECT b.id, b.batch_name, b.start_date, b.end_date, b.status, c.name AS course_name FROM batches b JOIN courses c ON c.id=b.course_id WHERE b.branch_id=? AND (?='' OR c.name=?) ORDER BY CASE b.status WHEN 'Active' THEN 1 WHEN 'Upcoming' THEN 2 ELSE 3 END, b.start_date ASC LIMIT 6"); $tb->execute([$teacherBranchId,$teacherSpec,$teacherSpec]); $teacherBatches=$tb->fetchAll(PDO::FETCH_ASSOC); $teacherKpi['active_batches']=(int)array_sum(array_map(fn($b)=>($b['status']??'')==='Active'?1:0,$teacherBatches)); } catch (Exception $e) {}
    try { $ls=$db->prepare("SELECT COUNT(DISTINCT s.id) FROM students s JOIN enrollments e ON e.student_id=s.id JOIN courses c ON c.id=e.course_id WHERE s.branch_id=? AND e.status='Active' AND (?='' OR c.name=?)"); $ls->execute([$teacherBranchId,$teacherSpec,$teacherSpec]); $teacherKpi['learners_in_track']=(int)$ls->fetchColumn(); } catch (Exception $e) {}
    try { $at=$db->prepare("SELECT COUNT(*) FROM attendance WHERE branch_id=? AND attend_date=CURDATE()"); $at->execute([$teacherBranchId]); $teacherKpi['attendance_today']=(int)$at->fetchColumn(); } catch (Exception $e) {}
    try { $nl=$db->prepare("SELECT COUNT(*) FROM students WHERE branch_id=? AND registration_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)"); $nl->execute([$teacherBranchId]); $teacherKpi['new_learners_30d']=(int)$nl->fetchColumn(); } catch (Exception $e) {}
    try { $rl=$db->prepare("SELECT u.name, s.student_id, c.name AS course_name, e.enrollment_date FROM enrollments e JOIN students s ON s.id=e.student_id JOIN users u ON u.id=s.user_id JOIN courses c ON c.id=e.course_id WHERE s.branch_id=? AND (?='' OR c.name=?) ORDER BY e.enrollment_date DESC LIMIT 6"); $rl->execute([$teacherBranchId,$teacherSpec,$teacherSpec]); $teacherRecentLearners=$rl->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $ta=$db->prepare("SELECT attend_date, SUM(status='Present') AS present, SUM(status='Absent') AS absent, SUM(status='Late') AS late FROM attendance WHERE branch_id=? AND attend_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY attend_date ORDER BY attend_date ASC"); $ta->execute([$teacherBranchId]); $teacherAttendance7d=$ta->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $mx=$db->prepare("SELECT e.status, COUNT(*) AS cnt FROM enrollments e JOIN students s ON s.id=e.student_id JOIN courses c ON c.id=e.course_id WHERE s.branch_id=? AND (?='' OR c.name=?) GROUP BY e.status"); $mx->execute([$teacherBranchId,$teacherSpec,$teacherSpec]); foreach($mx->fetchAll(PDO::FETCH_ASSOC) as $m){ if(isset($teacherTrackMix[$m['status']])) $teacherTrackMix[$m['status']]=(int)$m['cnt']; } } catch (Exception $e) {}
}
$teacherAttLabels  = json_encode(array_map(fn($r)=>date('D',strtotime($r['attend_date']??'now')),$teacherAttendance7d));
$teacherAttPresent = json_encode(array_map(fn($r)=>(int)($r['present']??0),$teacherAttendance7d));
$teacherAttAbsent  = json_encode(array_map(fn($r)=>(int)($r['absent']??0),$teacherAttendance7d));
$teacherAttLate    = json_encode(array_map(fn($r)=>(int)($r['late']??0),$teacherAttendance7d));
$teacherMixLabels  = json_encode(array_keys($teacherTrackMix));
$teacherMixCounts  = json_encode(array_values($teacherTrackMix));

$pageTitle  = 'Dashboard';
$activePage = 'dashboard.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<style>
/* ═══════════════════════════════════════════════════════════════
   SBVS DASHBOARD — Dark Command Centre
   Fonts: Syne (display) + Nunito (body) + IBM Plex Mono (data)
   Palette: Deep slate + electric teal + amber signal
═══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Nunito:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap');

:root {
    --d-bg:       #0D1117;
    --d-surface:  #161B22;
    --d-surface2: #1C2333;
    --d-border:   #30363D;
    --d-border2:  #21262D;
    --d-teal:     #00D4A8;
    --d-teal-dk:  #00A884;
    --d-teal-glow:rgba(0,212,168,.15);
    --d-amber:    #F5A623;
    --d-amber-glow:rgba(245,166,35,.12);
    --d-blue:     #58A6FF;
    --d-blue-glow:rgba(88,166,255,.12);
    --d-violet:   #BC8CFF;
    --d-vi-glow:  rgba(188,140,255,.12);
    --d-rose:     #FF7B72;
    --d-rose-glow:rgba(255,123,114,.12);
    --d-green:    #3FB950;
    --d-green-glow:rgba(63,185,80,.12);
    --d-ink:      #F0F6FC;
    --d-muted:    #8B949E;
    --d-subtle:   #484F58;
    --d-fd:       'Syne', system-ui, sans-serif;
    --d-fb:       'Nunito', system-ui, sans-serif;
    --d-fm:       'IBM Plex Mono', monospace;
    --d-r:        12px;
    --d-rlg:      16px;
    --d-rxl:      20px;
    --d-shadow:   0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3);
    --d-glow-teal:0 0 20px rgba(0,212,168,.2), 0 0 40px rgba(0,212,168,.05);
}

/* ── Reset & base ── */
.dash-wrap, .dash-wrap * { font-family: var(--d-fb); box-sizing: border-box; }
.dash-wrap { background: var(--d-bg); min-height: 100vh; color: var(--d-ink); }

/* ── Animated grain overlay ── */
.dash-wrap::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
    opacity: .4;
}

/* ── Hero welcome bar ── */
.d-hero {
    position: relative;
    padding: 28px 32px 24px;
    margin-bottom: 28px;
    background: linear-gradient(135deg, var(--d-surface) 0%, var(--d-surface2) 100%);
    border: 1px solid var(--d-border);
    border-radius: var(--d-rxl);
    overflow: hidden;
    box-shadow: var(--d-shadow);
}
.d-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, var(--d-teal-glow) 0%, transparent 70%);
    pointer-events: none;
}
.d-hero::after {
    content: '';
    position: absolute;
    bottom: -40px; left: 20%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, var(--d-vi-glow) 0%, transparent 70%);
    pointer-events: none;
}
.d-hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.d-hero-greeting { font-family: var(--d-fd); font-size: 1.85rem; font-weight: 800; color: var(--d-ink); letter-spacing: -.02em; line-height: 1.1; margin-bottom: 6px; }
.d-hero-greeting span { color: var(--d-teal); }
.d-hero-meta { font-size: .8rem; color: var(--d-muted); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.d-hero-meta-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--d-border); }
.d-hero-role-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--d-teal-glow); border: 1px solid rgba(0,212,168,.3);
    border-radius: 20px; padding: 4px 12px;
    font-size: .72rem; font-weight: 700; color: var(--d-teal);
    text-transform: uppercase; letter-spacing: .1em;
}
.d-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.d-btn {
    display: inline-flex; align-items: center; gap: 7px;
    height: 38px; padding: 0 16px; border: none; border-radius: var(--d-r);
    font-family: var(--d-fb); font-size: .84rem; font-weight: 700;
    cursor: pointer; text-decoration: none; transition: all .15s; white-space: nowrap;
}
.d-btn-teal { background: var(--d-teal); color: #0D1117; box-shadow: var(--d-glow-teal); }
.d-btn-teal:hover { background: var(--d-teal-dk); color: #0D1117; }
.d-btn-ghost { background: var(--d-surface2); color: var(--d-muted); border: 1px solid var(--d-border); }
.d-btn-ghost:hover { background: var(--d-surface); color: var(--d-ink); border-color: var(--d-border2); }
.d-btn-sm { height: 32px; padding: 0 12px; font-size: .78rem; }

/* ── KPI grid ── */
.d-kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 24px; }
.d-kpi-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
.d-kpi {
    background: var(--d-surface);
    border: 1px solid var(--d-border);
    border-radius: var(--d-rlg);
    padding: 20px 18px;
    box-shadow: var(--d-shadow);
    position: relative;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.d-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
.d-kpi::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    border-radius: var(--d-rlg) var(--d-rlg) 0 0;
}
.kpi-teal::before   { background: var(--d-teal); }
.kpi-amber::before  { background: var(--d-amber); }
.kpi-blue::before   { background: var(--d-blue); }
.kpi-violet::before { background: var(--d-violet); }
.kpi-rose::before   { background: var(--d-rose); }
.kpi-green::before  { background: var(--d-green); }

.d-kpi-glow {
    position: absolute; top: -30px; right: -30px;
    width: 100px; height: 100px; border-radius: 50%;
    pointer-events: none;
}
.kpi-teal .d-kpi-glow   { background: radial-gradient(circle, var(--d-teal-glow) 0%, transparent 70%); }
.kpi-amber .d-kpi-glow  { background: radial-gradient(circle, var(--d-amber-glow) 0%, transparent 70%); }
.kpi-blue .d-kpi-glow   { background: radial-gradient(circle, var(--d-blue-glow) 0%, transparent 70%); }
.kpi-violet .d-kpi-glow { background: radial-gradient(circle, var(--d-vi-glow) 0%, transparent 70%); }
.kpi-rose .d-kpi-glow   { background: radial-gradient(circle, var(--d-rose-glow) 0%, transparent 70%); }
.kpi-green .d-kpi-glow  { background: radial-gradient(circle, var(--d-green-glow) 0%, transparent 70%); }

.d-kpi-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; margin-bottom: 14px; flex-shrink: 0;
}
.kpi-teal .d-kpi-icon   { background: var(--d-teal-glow); color: var(--d-teal); }
.kpi-amber .d-kpi-icon  { background: var(--d-amber-glow); color: var(--d-amber); }
.kpi-blue .d-kpi-icon   { background: var(--d-blue-glow); color: var(--d-blue); }
.kpi-violet .d-kpi-icon { background: var(--d-vi-glow); color: var(--d-violet); }
.kpi-rose .d-kpi-icon   { background: var(--d-rose-glow); color: var(--d-rose); }
.kpi-green .d-kpi-icon  { background: var(--d-green-glow); color: var(--d-green); }

.d-kpi-val {
    font-family: var(--d-fd); font-size: 1.65rem; font-weight: 800;
    color: var(--d-ink); letter-spacing: -.02em; line-height: 1; margin-bottom: 4px;
}
.d-kpi-lbl { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .15em; color: var(--d-muted); }

/* ── Cards ── */
.d-card {
    background: var(--d-surface);
    border: 1px solid var(--d-border);
    border-radius: var(--d-rlg);
    box-shadow: var(--d-shadow);
    overflow: hidden;
    margin-bottom: 0;
}
.d-card-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--d-border2);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.d-card-title {
    font-family: var(--d-fd); font-size: .88rem; font-weight: 700;
    color: var(--d-ink); display: flex; align-items: center; gap: 8px;
}
.d-card-title i { font-size: .9rem; }
.d-card-body { padding: 20px; }
.d-card-body-0 { padding: 0; }

/* ── Activity list ── */
.d-activity-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 20px; border-bottom: 1px solid var(--d-border2);
    transition: background .12s;
}
.d-activity-item:last-child { border-bottom: none; }
.d-activity-item:hover { background: var(--d-surface2); }
.d-avatar {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--d-fd); font-size: .75rem; font-weight: 700;
    flex-shrink: 0;
}
.d-activity-name { font-size: .875rem; font-weight: 600; color: var(--d-ink); }
.d-activity-sub  { font-size: .75rem; color: var(--d-muted); margin-top: 1px; }

/* ── Badges ── */
.d-badge {
    display: inline-flex; align-items: center; gap: 4px;
    border-radius: 20px; padding: 3px 9px;
    font-size: .68rem; font-weight: 700; white-space: nowrap;
    font-family: var(--d-fb);
}
.db-teal   { background: var(--d-teal-glow); color: var(--d-teal); border: 1px solid rgba(0,212,168,.2); }
.db-amber  { background: var(--d-amber-glow); color: var(--d-amber); border: 1px solid rgba(245,166,35,.2); }
.db-rose   { background: var(--d-rose-glow); color: var(--d-rose); border: 1px solid rgba(255,123,114,.2); }
.db-violet { background: var(--d-vi-glow); color: var(--d-violet); border: 1px solid rgba(188,140,255,.2); }
.db-green  { background: var(--d-green-glow); color: var(--d-green); border: 1px solid rgba(63,185,80,.2); }
.db-muted  { background: var(--d-surface2); color: var(--d-muted); border: 1px solid var(--d-border); }

/* ── Action bar ── */
.d-action-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    padding: 16px 20px; background: var(--d-surface); border: 1px solid var(--d-border);
    border-radius: var(--d-rlg); box-shadow: var(--d-shadow); margin-bottom: 24px;
}
.d-action-label { font-family: var(--d-fd); font-size: .78rem; font-weight: 700; color: var(--d-muted); text-transform: uppercase; letter-spacing: .1em; margin-right: 4px; }
.d-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    height: 34px; padding: 0 14px; border: 1px solid var(--d-border);
    border-radius: 9px; background: var(--d-surface2); color: var(--d-muted);
    font-family: var(--d-fb); font-size: .8rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all .15s; white-space: nowrap;
}
.d-action-btn:hover { background: var(--d-border2); color: var(--d-ink); border-color: var(--d-border); }
.d-action-btn.primary { background: var(--d-teal); color: #0D1117; border-color: var(--d-teal); }
.d-action-btn.primary:hover { background: var(--d-teal-dk); }
.d-action-btn .badge-count {
    background: var(--d-rose); color: #fff;
    border-radius: 20px; padding: 1px 6px; font-size: .62rem; font-weight: 800;
}

/* ── Alert banner ── */
.d-alert-bar {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 18px; border-radius: var(--d-r);
    margin-bottom: 14px; font-size: .84rem;
}
.d-alert-rose   { background: var(--d-rose-glow); border: 1px solid rgba(255,123,114,.25); color: var(--d-rose); }
.d-alert-amber  { background: var(--d-amber-glow); border: 1px solid rgba(245,166,35,.25); color: var(--d-amber); }
.d-alert-teal   { background: var(--d-teal-glow); border: 1px solid rgba(0,212,168,.25); color: var(--d-teal); }
.d-alert-bar a  { color: inherit; font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }

/* ── Branch scorecard table ── */
.d-table { width: 100%; border-collapse: collapse; }
.d-table th {
    padding: 11px 16px; font-size: .62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .12em; color: var(--d-muted);
    background: var(--d-surface2); border-bottom: 1px solid var(--d-border2); text-align: left;
}
.d-table td { padding: 12px 16px; font-size: .855rem; border-bottom: 1px solid var(--d-border2); color: var(--d-ink); }
.d-table tbody tr:last-child td { border-bottom: none; }
.d-table tbody tr:hover td { background: var(--d-surface2); }
.d-mono { font-family: var(--d-fm); font-size: .82rem; }

/* ── Progress bar ── */
.d-progress { height: 5px; background: var(--d-surface2); border-radius: 10px; overflow: hidden; }
.d-progress-fill { height: 100%; border-radius: 10px; transition: width .6s ease; }

/* ── Stat number animation ── */
@keyframes countUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.d-kpi-val { animation: countUp .5s ease both; }
.d-kpi:nth-child(1) .d-kpi-val { animation-delay: .05s; }
.d-kpi:nth-child(2) .d-kpi-val { animation-delay: .10s; }
.d-kpi:nth-child(3) .d-kpi-val { animation-delay: .15s; }
.d-kpi:nth-child(4) .d-kpi-val { animation-delay: .20s; }
.d-kpi:nth-child(5) .d-kpi-val { animation-delay: .25s; }
.d-kpi:nth-child(6) .d-kpi-val { animation-delay: .30s; }

/* ── Section label ── */
.d-section-label {
    font-family: var(--d-fd); font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .2em; color: var(--d-muted);
    margin-bottom: 14px; display: flex; align-items: center; gap: 10px;
}
.d-section-label::after { content: ''; flex: 1; height: 1px; background: var(--d-border2); }

/* ── Attendance summary ── */
.d-att-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid var(--d-border2); font-size: .84rem; }
.d-att-row:last-child { border-bottom: none; }
.d-att-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.d-att-label { display: flex; align-items: center; gap: 8px; color: var(--d-muted); }
.d-att-val { font-family: var(--d-fm); font-weight: 600; color: var(--d-ink); }

/* ── Ops widget card ── */
.d-ops-alert {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; border-radius: var(--d-r);
    border: 1px solid var(--d-border2); background: var(--d-surface2);
    margin-bottom: 10px; text-decoration: none; transition: all .15s;
    cursor: pointer;
}
.d-ops-alert:last-child { margin-bottom: 0; }
.d-ops-alert:hover { border-color: var(--d-border); background: var(--d-border2); }
.d-ops-alert-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; }
.d-ops-alert-title { font-size: .84rem; font-weight: 700; }
.d-ops-alert-sub   { font-size: .72rem; color: var(--d-muted); margin-top: 2px; }

/* ── Chart wrap ── */
.d-chart-wrap { position: relative; height: 200px; }

/* ── Responsive ── */
@media (max-width: 1200px) {
    .d-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    .d-kpi-grid.cols-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .d-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .d-hero-greeting { font-size: 1.4rem; }
}
@media (max-width: 480px) {
    .d-kpi-grid { grid-template-columns: 1fr 1fr; }
}

/* ── Scrollbar ── */
.dash-wrap ::-webkit-scrollbar { width: 5px; height: 5px; }
.dash-wrap ::-webkit-scrollbar-track { background: var(--d-bg); }
.dash-wrap ::-webkit-scrollbar-thumb { background: var(--d-border); border-radius: 10px; }
</style>

<body class="dash-wrap">
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main" style="background:var(--d-bg);padding:24px 28px;position:relative;z-index:1">

<?php if ($dbError): ?>
<div class="d-alert-bar d-alert-rose mb-4">
    <i class="bi bi-database-x-fill" style="font-size:1.2rem;flex-shrink:0"></i>
    <div><strong>Database Error</strong> — <?= escapeHtml($dbError) ?></div>
</div>
<?php endif; ?>

<!-- ── HERO ── -->
<div class="d-hero fade-in">
    <div class="d-hero-inner">
        <div>
            <div class="d-hero-greeting">
                Good <?= (date('H')<12)?'Morning':(date('H')<17?'Afternoon':'Evening') ?>,
                <span><?= escapeHtml($firstName) ?></span> 👋
            </div>
            <div class="d-hero-meta">
                <span><?= formatDate('now','l, F j, Y') ?></span>
                <span class="d-hero-meta-dot"></span>
                <span class="d-hero-role-badge">
                    <i class="bi bi-shield-fill-check" style="font-size:.7rem"></i>
                    <?= escapeHtml($role) ?>
                </span>
                <?php if ($branchName): ?>
                <span class="d-hero-meta-dot"></span>
                <span style="color:var(--d-teal);font-weight:600"><?= escapeHtml($branchName) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-hero-actions">
            <?php if ($isSuperAdmin): ?>
            <a href="system_settings.php" class="d-btn d-btn-ghost">
                <i class="bi bi-gear-wide-connected"></i> Settings
            </a>
            <?php endif; ?>
            <?php if (!$isTeacher): ?>
            <div class="dropdown">
                <button class="d-btn d-btn-ghost" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="background:var(--d-surface);border:1px solid var(--d-border);border-radius:var(--d-r)">
                    <li><a class="dropdown-item" href="models/api/export_dashboard.php?type=kpi" style="color:var(--d-muted);font-size:.84rem"><i class="bi bi-graph-up me-2" style="color:var(--d-teal)"></i>KPI Report</a></li>
                    <li><a class="dropdown-item" href="models/api/export_dashboard.php?type=students" style="color:var(--d-muted);font-size:.84rem"><i class="bi bi-people me-2" style="color:var(--d-blue)"></i>Students</a></li>
                    <li><a class="dropdown-item" href="models/api/export_dashboard.php?type=payments" style="color:var(--d-muted);font-size:.84rem"><i class="bi bi-cash-coin me-2" style="color:var(--d-green)"></i>Payments</a></li>
                    <?php if ($isSuperAdmin): ?>
                    <li><hr class="dropdown-divider" style="border-color:var(--d-border2)"></li>
                    <li><a class="dropdown-item" href="models/api/export_dashboard.php?type=branch_performance" style="color:var(--d-muted);font-size:.84rem"><i class="bi bi-trophy me-2" style="color:var(--d-amber)"></i>Branch Performance</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($isSuperAdmin && ($pendingApprovals > 0 || $pendingTransfers > 0)): ?>
            <a href="instructor_approval_dashboard.php" class="d-btn d-btn-teal">
                <i class="bi bi-bell-fill"></i>
                <?= ($pendingApprovals + $pendingTransfers) ?> Pending
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TEACHER DASHBOARD
═══════════════════════════════════════════════════════════ -->
<?php if ($isTeacher): ?>

<!-- Teacher action bar -->
<div class="d-action-bar fade-in" style="animation-delay:.05s">
    <span class="d-action-label">Instructor Workspace</span>
    <a href="students.php" class="d-action-btn primary"><i class="bi bi-people-fill"></i> Learners</a>
    <a href="attendance.php" class="d-action-btn"><i class="bi bi-calendar-check-fill"></i> Attendance</a>
    <a href="courses.php" class="d-action-btn"><i class="bi bi-journal-bookmark-fill"></i> Courses</a>
    <a href="instructor_competency_matrix.php" class="d-action-btn"><i class="bi bi-grid-3x3-gap-fill"></i> Competency</a>
    <a href="instructor_attendance_logger.php" class="d-action-btn"><i class="bi bi-stopwatch-fill"></i> Hours</a>
    <a href="equipment_fault_reporter.php" class="d-action-btn"><i class="bi bi-tools"></i> Faults</a>
    <?php if (!empty($teacherProfile['id'])): ?>
    <a href="teacher_profile.php?id=<?= (int)$teacherProfile['id'] ?>" class="d-action-btn"><i class="bi bi-person-vcard"></i> Profile</a>
    <?php endif; ?>
</div>

<!-- Teacher KPIs -->
<div class="d-kpi-grid cols-4 fade-in" style="animation-delay:.1s">
    <div class="d-kpi kpi-blue">
        <div class="d-kpi-glow"></div>
        <div class="d-kpi-icon"><i class="bi bi-collection-fill"></i></div>
        <div class="d-kpi-val"><?= formatNumber($teacherKpi['active_batches']) ?></div>
        <div class="d-kpi-lbl">Active Classes</div>
    </div>
    <div class="d-kpi kpi-teal">
        <div class="d-kpi-glow"></div>
        <div class="d-kpi-icon"><i class="bi bi-people-fill"></i></div>
        <div class="d-kpi-val"><?= formatNumber($teacherKpi['learners_in_track']) ?></div>
        <div class="d-kpi-lbl">Learners in Track</div>
    </div>
    <div class="d-kpi kpi-amber">
        <div class="d-kpi-glow"></div>
        <div class="d-kpi-icon"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="d-kpi-val"><?= formatNumber($teacherKpi['attendance_today']) ?></div>
        <div class="d-kpi-lbl">Attendance Today</div>
    </div>
    <div class="d-kpi kpi-green">
        <div class="d-kpi-glow"></div>
        <div class="d-kpi-icon"><i class="bi bi-person-plus-fill"></i></div>
        <div class="d-kpi-val"><?= formatNumber($teacherKpi['new_learners_30d']) ?></div>
        <div class="d-kpi-lbl">New Learners (30d)</div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- My Classes -->
    <div class="col-lg-5">
        <div class="d-card h-100 fade-in" style="animation-delay:.15s">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-calendar-range" style="color:var(--d-blue)"></i> My Classes</span>
                <a href="courses.php" class="d-btn d-btn-ghost d-btn-sm">Catalogue</a>
            </div>
            <div class="d-card-body-0">
                <?php if (empty($teacherBatches)): ?>
                <div style="text-align:center;padding:40px;color:var(--d-muted)"><i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No classes assigned yet.</div>
                <?php else: foreach ($teacherBatches as $tb): ?>
                <div class="d-activity-item">
                    <div class="d-avatar" style="background:var(--d-blue-glow);color:var(--d-blue)"><i class="bi bi-collection"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="d-activity-name text-truncate"><?= escapeHtml($tb['batch_name']) ?></div>
                        <div class="d-activity-sub"><?= escapeHtml($tb['course_name']) ?> · <?= formatDate($tb['start_date']) ?> → <?= formatDate($tb['end_date']) ?></div>
                    </div>
                    <span class="d-badge <?= ($tb['status']??'')=='Active'?'db-teal':(($tb['status']??'')=='Upcoming'?'db-amber':'db-muted') ?>"><?= escapeHtml($tb['status']??'N/A') ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <!-- Instructor snapshot -->
    <div class="col-lg-4">
        <div class="d-card h-100 fade-in" style="animation-delay:.2s">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-person-vcard-fill" style="color:var(--d-violet)"></i> Instructor Snapshot</span>
                <?php if (!empty($teacherProfile['id'])): ?>
                <a href="teacher_profile.php?id=<?= (int)$teacherProfile['id'] ?>" class="d-btn d-btn-ghost d-btn-sm">Profile</a>
                <?php endif; ?>
            </div>
            <div class="d-card-body">
                <?php if (empty($teacherProfile)): ?>
                <div style="text-align:center;padding:20px;color:var(--d-muted)"><i class="bi bi-person-exclamation" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>Profile not linked.</div>
                <?php else: ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                    <div class="d-avatar" style="width:52px;height:52px;border-radius:14px;background:var(--d-vi-glow);color:var(--d-violet);font-size:1.1rem"><?= strtoupper(substr($_SESSION['name']??'T',0,1)) ?></div>
                    <div>
                        <div style="font-family:var(--d-fd);font-weight:700;color:var(--d-ink)"><?= escapeHtml($_SESSION['name']??$_SESSION['user_name']??'Instructor') ?></div>
                        <div style="font-family:var(--d-fm);font-size:.72rem;color:var(--d-muted)"><?= escapeHtml($teacherProfile['teacher_id']??'N/A') ?></div>
                    </div>
                </div>
                <?php foreach (['Specialization'=>$teacherProfile['specialization']??'—','Branch'=>$teacherProfile['branch_name']??($branchName?:'—'),'Phone'=>$teacherProfile['phone']??'—','Status'=>$teacherProfile['status']??'Active'] as $lbl=>$val): ?>
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--d-border2);font-size:.84rem">
                    <span style="color:var(--d-muted)"><?= $lbl ?></span>
                    <strong style="color:var(--d-ink)"><?= escapeHtml($val) ?></strong>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Recent learners -->
    <div class="col-lg-3">
        <div class="d-card h-100 fade-in" style="animation-delay:.25s">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-person-plus-fill" style="color:var(--d-teal)"></i> Recent Learners</span>
                <a href="students.php" class="d-btn d-btn-ghost d-btn-sm">View All</a>
            </div>
            <div class="d-card-body-0">
                <?php if (empty($teacherRecentLearners)): ?>
                <div style="text-align:center;padding:40px;color:var(--d-muted)"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No learners yet.</div>
                <?php else: foreach ($teacherRecentLearners as $lr): ?>
                <div class="d-activity-item">
                    <div class="d-avatar" style="background:var(--d-teal-glow);color:var(--d-teal)"><?= strtoupper(substr(escapeHtml($lr['name']),0,1)) ?></div>
                    <div style="flex:1;min-width:0">
                        <div class="d-activity-name text-truncate"><?= escapeHtml($lr['name']) ?></div>
                        <div class="d-activity-sub"><?= escapeHtml($lr['course_name']) ?></div>
                    </div>
                    <span class="d-badge db-teal" style="font-family:var(--d-fm);font-size:.64rem"><?= formatDate($lr['enrollment_date']) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Teacher charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="d-card fade-in" style="animation-delay:.3s">
            <div class="d-card-head"><span class="d-card-title"><i class="bi bi-bar-chart-fill" style="color:var(--d-teal)"></i> Attendance Activity — Last 7 Days</span></div>
            <div class="d-card-body"><div class="d-chart-wrap"><canvas id="teacherAttChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="d-card fade-in" style="animation-delay:.35s">
            <div class="d-card-head"><span class="d-card-title"><i class="bi bi-pie-chart-fill" style="color:var(--d-amber)"></i> Learner Mix</span></div>
            <div class="d-card-body d-flex align-items-center justify-content-center"><div class="d-chart-wrap" style="width:100%"><canvas id="teacherMixChart"></canvas></div></div>
        </div>
    </div>
</div>

<?php else: /* ── ADMIN / BRANCH ADMIN / SUPER ADMIN ── */ ?>

<!-- ═══════════════════════════════════════════════════════
     SUPER ADMIN & BRANCH ADMIN SHARED DASHBOARD
═══════════════════════════════════════════════════════════ -->

<!-- Pending alerts -->
<?php if ($isSuperAdmin && $pendingApprovals > 0): ?>
<div class="d-alert-bar d-alert-rose fade-in" style="animation-delay:.05s">
    <i class="bi bi-percent" style="font-size:1rem;flex-shrink:0"></i>
    <div style="flex:1"><strong><?= $pendingApprovals ?></strong> discount approval<?= $pendingApprovals>1?'s':'' ?> awaiting your review.</div>
    <a href="instructor_approval_dashboard.php" class="d-btn d-btn-sm" style="background:var(--d-rose);color:#fff;border:none;flex-shrink:0">Review</a>
</div>
<?php endif; ?>
<?php if ($isSuperAdmin && $pendingTransfers > 0): ?>
<div class="d-alert-bar d-alert-amber fade-in" style="animation-delay:.08s">
    <i class="bi bi-arrow-left-right" style="font-size:1rem;flex-shrink:0"></i>
    <div style="flex:1"><strong><?= $pendingTransfers ?></strong> inter-branch transfer<?= $pendingTransfers>1?'s':'' ?> pending origin approval.</div>
    <a href="transfers.php" class="d-btn d-btn-sm" style="background:var(--d-amber);color:#0D1117;border:none;flex-shrink:0">View</a>
</div>
<?php endif; ?>
<?php if ($isOpsAdmin && $overduePayments > 0): ?>
<div class="d-alert-bar d-alert-rose fade-in" style="animation-delay:.05s">
    <i class="bi bi-cash-coin" style="font-size:1rem;flex-shrink:0"></i>
    <div style="flex:1"><strong><?= $overduePayments ?></strong> student<?= $overduePayments>1?'s':'' ?> with outstanding payment balance.</div>
    <a href="payments.php" class="d-btn d-btn-sm" style="background:var(--d-rose);color:#fff;border:none;flex-shrink:0">Manage</a>
</div>
<?php endif; ?>
<?php if ($isOpsAdmin && $lowAttendanceCount > 0): ?>
<div class="d-alert-bar d-alert-amber fade-in" style="animation-delay:.08s">
    <i class="bi bi-person-exclamation" style="font-size:1rem;flex-shrink:0"></i>
    <div style="flex:1"><strong><?= $lowAttendanceCount ?></strong> student<?= $lowAttendanceCount>1?'s':'' ?> below 60% attendance — follow-up required.</div>
    <a href="attendance.php" class="d-btn d-btn-sm" style="background:var(--d-amber);color:#0D1117;border:none;flex-shrink:0">Review</a>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="d-action-bar fade-in" style="animation-delay:.1s">
    <?php if ($isSuperAdmin): ?>
    <span class="d-action-label">Super Admin</span>
    <a href="branches.php" class="d-action-btn primary"><i class="bi bi-buildings-fill"></i> Branches</a>
    <a href="system_settings.php" class="d-action-btn"><i class="bi bi-gear-wide-connected"></i> Settings</a>
    <a href="instructor_approval_dashboard.php" class="d-action-btn">
        <i class="bi bi-clipboard2-check"></i> Approvals
        <?php if ($pendingApprovals > 0): ?><span class="badge-count"><?= $pendingApprovals ?></span><?php endif; ?>
    </a>
    <a href="reports.php" class="d-action-btn"><i class="bi bi-graph-up-arrow"></i> Reports</a>
    <?php else: ?>
    <span class="d-action-label">Quick Actions</span>
    <a href="attendance.php" class="d-action-btn primary"><i class="bi bi-calendar-check-fill"></i> Attendance</a>
    <a href="student_registration.php" class="d-action-btn"><i class="bi bi-person-plus-fill"></i> Enroll</a>
    <a href="payments.php" class="d-action-btn"><i class="bi bi-cash-coin"></i> Payments</a>
    <a href="transfers.php" class="d-action-btn"><i class="bi bi-arrow-left-right"></i> Transfers</a>
    <?php endif; ?>
    <a href="students.php" class="d-action-btn"><i class="bi bi-people-fill"></i> Students</a>
    <a href="courses.php" class="d-action-btn"><i class="bi bi-journals"></i> Courses</a>
</div>

<!-- KPIs -->
<div class="d-kpi-grid fade-in" style="animation-delay:.12s">
    <?php if ($isSuperAdmin): ?>
    <div class="d-kpi kpi-blue"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-buildings-fill"></i></div><div class="d-kpi-val"><?= formatNumber($kpi['branches']) ?></div><div class="d-kpi-lbl">Branches</div></div>
    <?php else: ?>
    <div class="d-kpi kpi-violet"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-person-check-fill"></i></div><div class="d-kpi-val"><?= formatNumber($kpi['enrollments']) ?></div><div class="d-kpi-lbl">Active Enrollments</div></div>
    <?php endif; ?>
    <div class="d-kpi kpi-teal"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-people-fill"></i></div><div class="d-kpi-val"><?= formatNumber($kpi['students']) ?></div><div class="d-kpi-lbl">Students</div></div>
    <div class="d-kpi kpi-amber"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-person-badge-fill"></i></div><div class="d-kpi-val"><?= formatNumber($kpi['teachers']) ?></div><div class="d-kpi-lbl">Teachers</div></div>
    <div class="d-kpi kpi-violet"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-journal-bookmark-fill"></i></div><div class="d-kpi-val"><?= formatNumber($kpi['courses']) ?></div><div class="d-kpi-lbl">Courses</div></div>
    <div class="d-kpi kpi-green"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-cash-coin"></i></div><div class="d-kpi-val"><?= formatCurrency($kpi['monthly_rev']) ?></div><div class="d-kpi-lbl">This Month</div></div>
    <div class="d-kpi kpi-rose"><div class="d-kpi-glow"></div><div class="d-kpi-icon"><i class="bi bi-wallet2"></i></div><div class="d-kpi-val"><?= formatCurrency($kpi['total_rev']) ?></div><div class="d-kpi-lbl">Total Revenue</div></div>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4 fade-in" style="animation-delay:.15s">
    <div class="col-lg-5">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-bar-chart-fill" style="color:var(--d-violet)"></i> <?= $isSuperAdmin ? 'Students per Branch' : 'Enrollments per Course' ?></span>
            </div>
            <div class="d-card-body"><div class="d-chart-wrap"><canvas id="branchChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-graph-up" style="color:var(--d-teal)"></i> Revenue Trend (6mo)</span>
            </div>
            <div class="d-card-body"><div class="d-chart-wrap"><canvas id="revenueChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-pie-chart-fill" style="color:var(--d-amber)"></i> Course Share</span>
            </div>
            <div class="d-card-body d-flex align-items-center justify-content-center"><div class="d-chart-wrap" style="width:100%"><canvas id="courseChart"></canvas></div></div>
        </div>
    </div>
</div>

<!-- SA: Branch scorecards -->
<?php if ($isSuperAdmin && !empty($branchPerformance)): ?>
<div class="d-card mb-4 fade-in" style="animation-delay:.2s">
    <div class="d-card-head">
        <span class="d-card-title"><i class="bi bi-trophy-fill" style="color:var(--d-amber)"></i> Branch Performance Scorecards</span>
        <a href="reports.php" class="d-btn d-btn-ghost d-btn-sm">Full Report</a>
    </div>
    <div class="d-card-body-0">
        <div class="table-responsive">
            <table class="d-table">
                <thead>
                    <tr>
                        <th class="ps-4">Branch</th>
                        <th class="text-center">Students</th>
                        <th class="text-center">Active Enrollments</th>
                        <th class="text-center">Completions</th>
                        <th class="text-end pe-4">Month Revenue</th>
                        <th style="width:160px">Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($branchPerformance as $bp):
                    $cr = ($bp['active_enrollments']+$bp['completions'])>0 ? round($bp['completions']/($bp['active_enrollments']+$bp['completions'])*100) : 0;
                    $cc = $cr>=70?'var(--d-teal)':($cr>=40?'var(--d-amber)':'var(--d-rose)');
                ?>
                <tr>
                    <td class="ps-4" style="font-weight:700"><?= escapeHtml($bp['name']) ?></td>
                    <td class="text-center d-mono"><?= (int)$bp['total_students'] ?></td>
                    <td class="text-center"><span class="d-badge db-teal"><?= (int)$bp['active_enrollments'] ?></span></td>
                    <td class="text-center d-mono"><?= (int)$bp['completions'] ?></td>
                    <td class="text-end pe-4 d-mono" style="color:var(--d-green)"><?= formatCurrency($bp['monthly_rev']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;padding-right:16px">
                            <div class="d-progress" style="flex:1"><div class="d-progress-fill" style="width:<?= $cr ?>%;background:<?= $cc ?>"></div></div>
                            <span style="font-family:var(--d-fm);font-size:.75rem;font-weight:600;color:<?= $cc ?>;min-width:32px"><?= $cr ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- SA: Top programs + Needs attention -->
<?php if ($isSuperAdmin && (!empty($topPrograms) || !empty($needsAttention))): ?>
<div class="row g-4 mb-4 fade-in" style="animation-delay:.25s">
    <?php if (!empty($needsAttention)): ?>
    <div class="col-lg-4">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title" style="color:var(--d-rose)"><i class="bi bi-exclamation-triangle-fill" style="color:var(--d-rose)"></i> Needs Attention</span>
                <span class="d-badge db-rose"><?= count($needsAttention) ?></span>
            </div>
            <div class="d-card-body-0">
            <?php foreach ($needsAttention as $na): ?>
            <div class="d-activity-item">
                <div class="d-avatar" style="background:var(--d-rose-glow);color:var(--d-rose)"><i class="bi bi-building-exclamation"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="d-activity-name"><?= escapeHtml($na['name']) ?></div>
                    <div class="d-activity-sub"><?= (int)$na['active_enrollments']===0&&(int)$na['total_students']>0?'No active enrollments':'No revenue this month' ?></div>
                </div>
                <a href="branches.php" class="d-badge db-rose">Review</a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($topPrograms)): ?>
    <div class="col-lg-<?= !empty($needsAttention)?'8':'12' ?>">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-star-fill" style="color:var(--d-amber)"></i> Top Programs</span>
                <a href="reports.php" class="d-btn d-btn-ghost d-btn-sm">Report</a>
            </div>
            <div class="d-card-body-0">
            <?php
            $maxE = max(1, (int)($topPrograms[0]['total_enrollments']??1));
            $pColors = ['var(--d-violet)','var(--d-teal)','var(--d-amber)','var(--d-blue)','var(--d-rose)'];
            foreach ($topPrograms as $i => $tp):
                $pct = round((int)$tp['total_enrollments']/$maxE*100);
                $c = $pColors[$i%count($pColors)];
            ?>
            <div class="d-activity-item">
                <div style="font-family:var(--d-fm);font-size:.8rem;font-weight:700;color:<?= $c ?>;width:24px;text-align:center">#<?= $i+1 ?></div>
                <div style="flex:1;min-width:0">
                    <div class="d-activity-name text-truncate mb-1"><?= escapeHtml($tp['name']) ?></div>
                    <div class="d-progress"><div class="d-progress-fill" style="width:<?= $pct ?>%;background:<?= $c ?>"></div></div>
                </div>
                <div style="text-align:right;min-width:64px">
                    <div style="font-family:var(--d-fm);font-weight:700;color:<?= $c ?>"><?= (int)$tp['total_enrollments'] ?></div>
                    <div style="font-size:.68rem;color:var(--d-muted)"><?= (int)$tp['branches_offering'] ?> branch<?= (int)$tp['branches_offering']!==1?'es':'' ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Branch admin ops widgets -->
<?php if ($isOpsAdmin): ?>
<div class="row g-4 mb-4 fade-in" style="animation-delay:.2s">
    <!-- Active classes -->
    <div class="col-lg-4">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-collection-fill" style="color:var(--d-violet)"></i> Active Classes</span>
                <a href="attendance.php" class="d-btn d-btn-ghost d-btn-sm">Attendance</a>
            </div>
            <div class="d-card-body-0">
                <?php if (empty($todayBatches)): ?>
                <div style="text-align:center;padding:40px;color:var(--d-muted)"><i class="bi bi-calendar" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No active classes.</div>
                <?php else: foreach ($todayBatches as $tb): ?>
                <div class="d-activity-item">
                    <div class="d-avatar" style="background:var(--d-vi-glow);color:var(--d-violet)"><i class="bi bi-collection"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="d-activity-name text-truncate"><?= escapeHtml($tb['batch_name']) ?></div>
                        <div class="d-activity-sub"><?= escapeHtml($tb['course_name']) ?></div>
                    </div>
                    <a href="attendance.php" class="d-badge db-violet">Attend</a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <!-- Attendance today -->
    <div class="col-lg-3">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-calendar-check-fill" style="color:var(--d-teal)"></i> Today's Attendance</span>
                <a href="attendance.php" class="d-btn d-btn-ghost d-btn-sm">View</a>
            </div>
            <div class="d-card-body">
                <?php if ((int)$todayAttSummary['total']===0): ?>
                <div style="text-align:center;padding:20px;color:var(--d-muted)"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No attendance recorded.</div>
                <?php else: ?>
                <div class="d-att-row"><span class="d-att-label"><span class="d-att-dot" style="background:var(--d-teal)"></span>Present</span><span class="d-att-val" style="color:var(--d-teal)"><?= formatNumber($todayAttSummary['present']) ?></span></div>
                <div class="d-att-row"><span class="d-att-label"><span class="d-att-dot" style="background:var(--d-rose)"></span>Absent</span><span class="d-att-val" style="color:var(--d-rose)"><?= formatNumber($todayAttSummary['absent']) ?></span></div>
                <div class="d-att-row"><span class="d-att-label"><span class="d-att-dot" style="background:var(--d-amber)"></span>Late</span><span class="d-att-val" style="color:var(--d-amber)"><?= formatNumber($todayAttSummary['late']) ?></span></div>
                <div class="d-att-row" style="border:none;margin-top:4px"><span class="d-att-label" style="color:var(--d-muted)">Total</span><span class="d-att-val"><?= formatNumber($todayAttSummary['total']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Action required -->
    <div class="col-lg-5">
        <div class="d-card h-100">
            <div class="d-card-head"><span class="d-card-title"><i class="bi bi-exclamation-triangle-fill" style="color:var(--d-rose)"></i> Action Required</span></div>
            <div class="d-card-body">
                <a href="payments.php" class="d-ops-alert">
                    <div class="d-ops-alert-icon" style="background:var(--d-rose-glow);color:var(--d-rose)"><i class="bi bi-cash-coin"></i></div>
                    <div><div class="d-ops-alert-title" style="color:var(--d-rose)"><?= $overduePayments ?> Outstanding Balance<?= $overduePayments!==1?'s':'' ?></div><div class="d-ops-alert-sub">Payments with remaining balance</div></div>
                </a>
                <a href="transfers.php" class="d-ops-alert">
                    <div class="d-ops-alert-icon" style="background:var(--d-amber-glow);color:var(--d-amber)"><i class="bi bi-arrow-left-right"></i></div>
                    <div><div class="d-ops-alert-title" style="color:var(--d-amber)"><?= $pendingTransfersBA ?> Pending Transfer<?= $pendingTransfersBA!==1?'s':'' ?></div><div class="d-ops-alert-sub">Awaiting branch approval</div></div>
                </a>
                <?php if ($lowAttendanceCount > 0): ?>
                <a href="attendance.php" class="d-ops-alert">
                    <div class="d-ops-alert-icon" style="background:var(--d-vi-glow);color:var(--d-violet)"><i class="bi bi-person-exclamation"></i></div>
                    <div><div class="d-ops-alert-title" style="color:var(--d-violet)"><?= $lowAttendanceCount ?> Low-Attendance Student<?= $lowAttendanceCount!==1?'s':'' ?></div><div class="d-ops-alert-sub">Below 60% — review needed</div></div>
                </a>
                <?php endif; ?>
                <?php if ($newAdmissionsThisWeek > 0): ?>
                <a href="students.php" class="d-ops-alert">
                    <div class="d-ops-alert-icon" style="background:var(--d-teal-glow);color:var(--d-teal)"><i class="bi bi-person-plus-fill"></i></div>
                    <div><div class="d-ops-alert-title" style="color:var(--d-teal)"><?= $newAdmissionsThisWeek ?> New Admission<?= $newAdmissionsThisWeek!==1?'s':'' ?> This Week</div><div class="d-ops-alert-sub">Registered in last 7 days</div></div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent activity -->
<div class="row g-4 mb-4 fade-in" style="animation-delay:.3s">
    <div class="col-lg-6">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-person-plus-fill" style="color:var(--d-violet)"></i> Recent Registrations</span>
                <a href="students.php" class="d-btn d-btn-ghost d-btn-sm">View All</a>
            </div>
            <div class="d-card-body-0">
                <?php if (empty($recentStudents)): ?>
                <div style="text-align:center;padding:40px;color:var(--d-muted)"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No students yet.</div>
                <?php else: foreach ($recentStudents as $s): ?>
                <div class="d-activity-item">
                    <div class="d-avatar" style="background:var(--d-vi-glow);color:var(--d-violet)"><?= strtoupper(substr(escapeHtml($s['name']),0,1)) ?></div>
                    <div style="flex:1;min-width:0">
                        <div class="d-activity-name text-truncate"><?= escapeHtml($s['name']) ?></div>
                        <div class="d-activity-sub"><?= escapeHtml($s['branch']) ?> · <span style="font-family:var(--d-fm);font-size:.72rem"><?= escapeHtml($s['student_id']) ?></span></div>
                    </div>
                    <span class="d-badge db-violet" style="font-family:var(--d-fm);font-size:.64rem"><?= formatDate($s['registration_date']) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="d-card h-100">
            <div class="d-card-head">
                <span class="d-card-title"><i class="bi bi-cash-stack" style="color:var(--d-green)"></i> Recent Payments</span>
                <a href="payments.php" class="d-btn d-btn-ghost d-btn-sm">View All</a>
            </div>
            <div class="d-card-body-0">
                <?php if (empty($recentPayments)): ?>
                <div style="text-align:center;padding:40px;color:var(--d-muted)"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No payments yet.</div>
                <?php else: foreach ($recentPayments as $p): ?>
                <div class="d-activity-item">
                    <div class="d-avatar" style="background:var(--d-green-glow);color:var(--d-green)"><?= strtoupper(substr(escapeHtml($p['name']),0,1)) ?></div>
                    <div style="flex:1;min-width:0">
                        <div class="d-activity-name text-truncate"><?= escapeHtml($p['name']) ?></div>
                        <div class="d-activity-sub"><?= escapeHtml($p['payment_method']) ?> · <?= formatDate($p['payment_date']) ?></div>
                    </div>
                    <span style="font-family:var(--d-fm);font-weight:700;color:var(--d-green);font-size:.875rem"><?= formatCurrency($p['amount']) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SA: Audit trail -->
<?php if ($isSuperAdmin && !empty($recentAudit)): ?>
<div class="d-card mb-4 fade-in" style="animation-delay:.35s">
    <div class="d-card-head">
        <span class="d-card-title"><i class="bi bi-journal-text" style="color:var(--d-blue)"></i> Audit Trail Highlights</span>
        <a href="reports.php" class="d-btn d-btn-ghost d-btn-sm">Full Log</a>
    </div>
    <div class="d-card-body-0">
    <?php foreach ($recentAudit as $al): ?>
    <div class="d-activity-item">
        <div class="d-avatar" style="background:var(--d-blue-glow);color:var(--d-blue)"><i class="bi bi-person-fill"></i></div>
        <div style="flex:1;min-width:0">
            <div class="d-activity-name text-truncate"><?= escapeHtml($al['user_name']??'System') ?> <span class="d-badge db-muted ms-1"><?= escapeHtml($al['module']??'') ?></span></div>
            <div class="d-activity-sub text-truncate"><?= escapeHtml($al['action']??'') ?></div>
        </div>
        <span style="font-family:var(--d-fm);font-size:.68rem;color:var(--d-muted);white-space:nowrap"><?= formatDateTime($al['created_at']??'','M j, H:i') ?></span>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; /* end teacher/admin split */ ?>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
(function() {
const isTeacher = <?= $isTeacher?'true':'false' ?>;

// Chart defaults — dark theme
Chart.defaults.color = '#8B949E';
Chart.defaults.borderColor = '#21262D';
Chart.defaults.font.family = "'Nunito', system-ui, sans-serif";

const palette = ['#00D4A8','#BC8CFF','#F5A623','#58A6FF','#FF7B72','#3FB950'];
const darkTip = { backgroundColor:'#1C2333', titleColor:'#F0F6FC', bodyColor:'#8B949E', borderColor:'#30363D', borderWidth:1, padding:12, cornerRadius:10 };

if (isTeacher) {
    // Teacher attendance chart
    const attEl = document.getElementById('teacherAttChart');
    if (attEl) {
        new Chart(attEl, {
            type:'bar',
            data:{
                labels:<?= $teacherAttLabels ?>,
                datasets:[
                    {label:'Present', data:<?= $teacherAttPresent ?>, backgroundColor:'rgba(0,212,168,.75)', borderRadius:6, barThickness:18},
                    {label:'Absent',  data:<?= $teacherAttAbsent ?>,  backgroundColor:'rgba(255,123,114,.75)', borderRadius:6, barThickness:18},
                    {label:'Late',    data:<?= $teacherAttLate ?>,    backgroundColor:'rgba(245,166,35,.75)',  borderRadius:6, barThickness:18}
                ]
            },
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:14,color:'#8B949E'}},tooltip:darkTip},
                scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.04)'},border:{display:false},ticks:{stepSize:1}}}}
        });
    }
    const mixEl = document.getElementById('teacherMixChart');
    if (mixEl) {
        new Chart(mixEl, {
            type:'doughnut',
            data:{labels:<?= $teacherMixLabels ?>,datasets:[{data:<?= $teacherMixCounts ?>,backgroundColor:['#00D4A8','#BC8CFF','#FF7B72'],borderWidth:0,hoverOffset:6}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:12,color:'#8B949E'}},tooltip:darkTip}}
        });
    }
    return;
}

// Branch bar
const bCtx = document.getElementById('branchChart');
if (bCtx) {
    const g = bCtx.getContext('2d').createLinearGradient(0,0,0,220);
    g.addColorStop(0,'rgba(188,140,255,.8)'); g.addColorStop(1,'rgba(188,140,255,.15)');
    new Chart(bCtx, {
        type:'bar',
        data:{labels:<?= $branchLabels ?>,datasets:[{label:'<?= $isSuperAdmin?'Students':'Enrollments' ?>',data:<?= $branchCounts ?>,backgroundColor:g,borderColor:'#BC8CFF',borderWidth:0,borderRadius:7,barThickness:24}]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:darkTip},
            scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.04)'},border:{display:false},ticks:{stepSize:1}}}}
    });
}

// Revenue line
const rCtx = document.getElementById('revenueChart');
if (rCtx) {
    const rg = rCtx.getContext('2d').createLinearGradient(0,0,0,220);
    rg.addColorStop(0,'rgba(0,212,168,.2)'); rg.addColorStop(1,'rgba(0,212,168,.01)');
    new Chart(rCtx, {
        type:'line',
        data:{labels:<?= $revLabels ?>,datasets:[{label:'Revenue',data:<?= $revData ?>,borderColor:'#00D4A8',backgroundColor:rg,borderWidth:2.5,fill:true,tension:.4,pointBackgroundColor:'#00D4A8',pointBorderColor:'#161B22',pointBorderWidth:2,pointRadius:5,pointHoverRadius:7}]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{...darkTip,callbacks:{label:c=>' $'+c.parsed.y.toLocaleString()}}},
            scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.04)'},border:{display:false},ticks:{callback:v=>'$'+v.toLocaleString()}}}}
    });
}

// Course doughnut
const cCtx = document.getElementById('courseChart');
if (cCtx) {
    new Chart(cCtx, {
        type:'doughnut',
        data:{labels:<?= $courseLabels ?>,datasets:[{data:<?= $courseCounts ?>,backgroundColor:palette.slice(0,<?= count($courseRows) ?>),borderWidth:0,hoverOffset:8}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:10,color:'#8B949E',font:{size:11}}},tooltip:darkTip}}
    });
}
})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>