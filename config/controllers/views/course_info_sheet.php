<?php
ob_start();
session_start();

// Catch ALL PHP errors and show a readable message instead of blank 500
set_error_handler(function($no, $str, $file, $line) {
    ob_clean();
    echo '<div style="font-family:sans-serif;padding:40px;background:#fef2f2;border-left:4px solid #dc2626;margin:20px">';
    echo '<strong>PHP Error ['.$no.']:</strong> '.htmlspecialchars($str);
    echo '<br><small>'.basename($file).':'.$line.'</small></div>';
    exit;
});
set_exception_handler(function(Throwable $e) {
    ob_clean();
    echo '<div style="font-family:sans-serif;padding:40px;background:#fef2f2;border-left:4px solid #dc2626;margin:20px">';
    echo '<strong>Error:</strong> '.htmlspecialchars($e->getMessage());
    echo '<br><small>'.basename($e->getFile()).':'.$e->getLine().'</small></div>';
    exit;
});

function cis_findFile(string $filename): string {
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($dir . DIRECTORY_SEPARATOR . $filename)) return $dir . DIRECTORY_SEPARATOR . $filename;
        $parent = dirname($dir); if ($parent === $dir) break; $dir = $parent;
    }
    throw new RuntimeException("Cannot locate {$filename}");
}

require_once cis_findFile('config.php');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php"); exit;
}
require_once cis_findFile('database.php');

$db = (new Database())->getConnection();

// ── Mode detection ───────────────────────────────────────────
// ?id=N   → single course sheet
// ?all=1  → all-courses catalogue (optionally ?branch_id=N)
$courseId  = (int)($_GET['id']        ?? 0);
$printAll  = ($_GET['all']            ?? '') === '1';
$filterBranch = (int)($_GET['branch_id'] ?? 0);

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');

// Branch scope: SA can see all or filter; Branch Admin sees only their own
if (!$isSuperAdmin) $filterBranch = $sessionBranch;

// ── Load Course model if available ───────────────────────────
$coursePath = null;
foreach ([
    __DIR__ . '/../models/Course.php',
    __DIR__ . '/../../models/Course.php',
    dirname(cis_findFile('config.php')) . '/config/controllers/models/Course.php',
] as $p) {
    if (file_exists($p)) { $coursePath = $p; break; }
}
if ($coursePath) require_once $coursePath;

// ── Shared query helper ───────────────────────────────────────
function fetchCourses(PDO $db, int $branchId = 0, bool $activeOnly = true): array {
    $where  = $activeOnly ? ["c.status = 'Active'"] : [];
    $params = [];
    if ($branchId > 0) { $where[] = 'c.branch_id = ?'; $params[] = $branchId; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $stmt = $db->prepare("
        SELECT c.id, c.branch_id, c.name, c.code, c.duration, c.duration_weeks,
               c.registration_fee, c.tuition_fee, c.currency_code,
               c.description, c.capacity, c.status,
               b.name    AS branch_name,
               b.address AS branch_address,
               b.phone   AS branch_phone,
               b.email   AS branch_email
        FROM   courses c
        LEFT JOIN branches b ON b.id = c.branch_id
        {$whereSql}
        ORDER BY b.name ASC, c.name ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Single course ─────────────────────────────────────────────
$course = null;
if (!$printAll) {
    if (!$courseId) {
        echo '<p style="font-family:sans-serif;padding:40px;color:#dc2626">No course ID provided. Add <code>?id=N</code> or <code>?all=1</code>.</p>'; exit;
    }
    if ($coursePath && class_exists('Course')) {
        $model  = new Course($db);
        $course = $model->getById($courseId);
    } else {
        $stmt = $db->prepare("
            SELECT c.id, c.branch_id, c.name, c.code, c.duration, c.duration_weeks,
                   c.registration_fee, c.tuition_fee, c.currency_code,
                   c.description, c.capacity, c.status,
                   b.name AS branch_name, b.address AS branch_address,
                   b.phone AS branch_phone, b.email AS branch_email
            FROM   courses c
            LEFT JOIN branches b ON b.id = c.branch_id
            WHERE  c.id = ?
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$course) {
        echo '<p style="font-family:sans-serif;padding:40px;color:#dc2626">Course not found.</p>'; exit;
    }
}

// ── Pull institution settings ────────────────────────────────
function getSetting(PDO $db, string $key, string $default = ''): string {
    try {
        $s = $db->prepare("SELECT setting_val FROM system_settings WHERE setting_key=? LIMIT 1");
        $s->execute([$key]);
        return $s->fetchColumn() ?: $default;
    } catch (\Throwable $e) { return $default; }
}

$institutionName    = getSetting($db, 'institution_name',    'Shining Bright Vocational School');
$institutionTagline = getSetting($db, 'institution_tagline', 'Empowering Futures');
$hqAddress          = getSetting($db, 'hq_address',          '');
$contactPhone       = getSetting($db, 'contact_phone',       '');
$contactEmail       = getSetting($db, 'contact_email',       '');
$defaultCurrency    = getSetting($db, 'default_currency',    'USD');
$logoPath           = getSetting($db, 'logo_path',           '');

// Currency symbol map
$currencySymbols = [
    'USD'=>'$','LRD'=>'L$','GHS'=>'₵','NGN'=>'₦','KES'=>'Ksh',
    'ZAR'=>'R','EUR'=>'€','GBP'=>'£','XOF'=>'CFA','RWF'=>'Fr','ETB'=>'Br','TZS'=>'TSh',
];
$courseCurrency = $course['currency_code'] ?? $defaultCurrency;
$sym = $currencySymbols[$courseCurrency] ?? $courseCurrency;
$regFee  = (float)($course['registration_fee'] ?? 0);
$tuiFee  = (float)($course['tuition_fee']      ?? 0);
$totalFee= $regFee + $tuiFee;

// Enrolled count (may come from Course model or need a direct query)
// Enrollment count not shown on public sheet

// Branch info — prefer branch columns if joined, else fall through
$branchName    = $course['branch_name']    ?? '';
$branchAddress = $course['branch_address'] ?? $hqAddress;
$branchPhone   = $course['branch_phone']   ?? $contactPhone;
$branchEmail   = $course['branch_email']   ?? $contactEmail;

// If branch has no address/phone/email, pull from branches table directly
if (empty($branchAddress) || empty($branchPhone)) {
    try {
        $bq = $db->prepare("SELECT address, phone, email FROM branches WHERE id=? LIMIT 1");
        $bq->execute([$course['branch_id'] ?? 0]);
        $bRow = $bq->fetch(PDO::FETCH_ASSOC) ?: [];
        if (empty($branchAddress)) $branchAddress = $bRow['address'] ?? '';
        if (empty($branchPhone))   $branchPhone   = $bRow['phone']   ?? '';
        if (empty($branchEmail))   $branchEmail   = $bRow['email']   ?? '';
    } catch (\Throwable $e) {}
}

// Derive display address: prefer branch, else HQ
$displayAddress = $branchAddress ?: $hqAddress;
$displayPhone   = $branchPhone   ?: $contactPhone;
$displayEmail   = $branchEmail   ?: $contactEmail;

$printDate = date('d F Y');
$sheetRef  = 'CIS-' . strtoupper(substr(md5($courseId . $printDate), 0, 8));

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$fmtFee = fn(float $v): string => $sym . ' ' . number_format($v, 2);

// ════════════════════════════════════════════════════════════════
// ALL-COURSES CATALOGUE MODE  (?all=1)
// ════════════════════════════════════════════════════════════════
if ($printAll) {
    $courses     = fetchCourses($db, $filterBranch, true); // active courses only — public prospectus
    $totalCourses= count($courses);
    $activeCnt   = count(array_filter($courses, fn($c) => ($c['status']??'Active') === 'Active'));

    // Group by branch
    $byBranch = [];
    foreach ($courses as $c) {
        $bn = $c['branch_name'] ?? 'Unknown Branch';
        $byBranch[$bn][] = $c;
    }



    $pageTitle    = 'All Courses — ' . $institutionName;
    $sheetRefAll  = 'CAT-' . strtoupper(substr(md5(date('Y-m-d') . $filterBranch), 0, 8));
    $scopeLabel   = $filterBranch
        ? (($byBranch ? array_key_first($byBranch) : 'Selected Branch') . ' Branch')
        : 'All Branches';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --navy:#0B1D35;--navy-md:#152B4E;--gold:#C9973A;--gold-lt:#F0D9A8;
    --gold-pale:#FBF5E8;--cream:#FDFAF4;--cream-dk:#F5EFE0;
    --ink:#1A1A2E;--muted:#5A6478;--subtle:#8A95A3;
    --border:#D8C99A;--border-lt:#EDE3C6;--green:#166534;--red:#B91C1C;
    --fd:'Cormorant Garamond',Georgia,serif;
    --fb:'Jost',system-ui,sans-serif;
    --fm:'DM Mono',monospace;
}
body{font-family:var(--fb);background:#E8E0D0;color:var(--ink);padding:30px 20px 60px}

/* Toolbar */
.screen-toolbar{max-width:1100px;margin:0 auto 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.tb-btn{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 16px;border:none;border-radius:8px;font-family:var(--fb);font-size:.84rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s}
.tb-btn-print{background:var(--navy);color:#fff}.tb-btn-print:hover{background:var(--navy-md)}
.tb-btn-back{background:rgba(255,255,255,.7);color:var(--navy);border:1.5px solid var(--border)}.tb-btn-back:hover{background:#fff}
.tb-label{font-family:var(--fm);font-size:.72rem;color:var(--muted);background:rgba(255,255,255,.6);border:1px solid var(--border-lt);border-radius:6px;padding:4px 10px}

/* Catalogue wrapper */
.catalogue{max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:0;background:var(--cream);border:1px solid var(--border);box-shadow:0 20px 60px rgba(0,0,0,.18)}

/* Accent bars */
.cat-accent-top{height:6px;background:linear-gradient(90deg,var(--navy) 0%,var(--gold) 50%,var(--navy) 100%)}
.cat-accent-bot{height:4px;background:linear-gradient(90deg,var(--gold) 0%,var(--navy) 50%,var(--gold) 100%)}

/* Header */
.cat-header{display:grid;grid-template-columns:auto 1fr auto;gap:24px;align-items:center;padding:28px 44px 24px;border-bottom:1px solid var(--border-lt)}
.logo-wrap{width:72px;height:72px;border-radius:12px;background:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 16px rgba(11,29,53,.25)}
.logo-initials{font-family:var(--fd);font-size:1.4rem;font-weight:700;color:var(--gold)}
.logo-wrap img{width:100%;height:100%;object-fit:cover;border-radius:12px}
.cat-inst-name{font-family:var(--fd);font-size:2rem;font-weight:700;color:var(--navy);letter-spacing:-.01em;line-height:1.1;margin-bottom:3px}
.cat-inst-tagline{font-size:.72rem;font-weight:600;color:var(--gold);text-transform:uppercase;letter-spacing:.18em;margin-bottom:10px}
.cat-contacts{display:flex;flex-wrap:wrap;gap:5px 14px}
.cat-contact{display:inline-flex;align-items:center;gap:5px;font-size:.75rem;color:var(--muted)}
.cat-contact i{color:var(--gold);font-size:.78rem}
.cat-doc-meta{text-align:right}
.cat-doc-type{font-family:var(--fd);font-size:.95rem;font-weight:600;color:var(--navy);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.cat-ref{font-family:var(--fm);font-size:.68rem;color:var(--subtle);margin-bottom:2px}
.cat-date{font-size:.72rem;color:var(--muted);margin-bottom:8px}
.scope-badge{display:inline-block;background:var(--navy);color:var(--gold-lt);font-family:var(--fb);font-size:.68rem;font-weight:600;border-radius:4px;padding:4px 10px}

/* Gold rule */
.gold-rule{height:1px;background:linear-gradient(90deg,transparent,var(--gold) 20%,var(--gold) 80%,transparent);margin:0 44px;opacity:.5}

/* KPI strip */
.kpi-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border-lt);border-bottom:1px solid var(--border-lt)}
.kpi-cell{background:var(--cream);padding:18px 24px;text-align:center}
.kpi-val{font-family:var(--fd);font-size:2rem;font-weight:700;color:var(--navy);line-height:1}
.kpi-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.2em;color:var(--subtle);margin-top:4px}
.kpi-sub{font-size:.7rem;color:var(--gold);margin-top:2px;font-family:var(--fm)}

/* Branch section */
.branch-section{padding:28px 44px;page-break-inside:avoid}
.branch-title{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.branch-title-text{font-family:var(--fd);font-size:1.4rem;font-weight:600;color:var(--navy);letter-spacing:.02em}
.branch-title-line{flex:1;height:1px;background:var(--border)}
.branch-title-count{font-family:var(--fm);font-size:.72rem;color:var(--subtle);background:var(--cream-dk);border:1px solid var(--border-lt);border-radius:20px;padding:2px 10px;white-space:nowrap}

/* Course table */
.course-tbl{width:100%;border-collapse:collapse;margin-bottom:8px}
.course-tbl thead th{background:var(--navy);color:rgba(255,255,255,.75);font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;padding:10px 12px;white-space:nowrap;border:none}
.course-tbl thead th:first-child{border-radius:6px 0 0 0}
.course-tbl thead th:last-child{border-radius:0 6px 0 0}
.course-tbl tbody tr{border-bottom:1px solid var(--border-lt)}
.course-tbl tbody tr:last-child{border-bottom:none}
.course-tbl tbody tr:nth-child(even) td{background:#FDFBF6}
.course-tbl tbody tr:hover td{background:var(--gold-pale)}
.course-tbl tbody td{padding:11px 12px;font-size:.84rem;color:var(--ink);vertical-align:middle}
.course-name-cell{font-family:var(--fb);font-weight:600;color:var(--navy)}
.course-code-badge{display:inline-block;font-family:var(--fm);font-size:.68rem;color:var(--muted);background:var(--cream-dk);border:1px solid var(--border-lt);border-radius:4px;padding:1px 6px;margin-top:2px}
.course-desc-cell{font-size:.76rem;color:var(--muted);max-width:200px;line-height:1.4}
.fee-cell{font-family:var(--fm);font-size:.84rem;color:var(--navy);white-space:nowrap;text-align:right}
.fee-total-cell{font-family:var(--fm);font-size:.88rem;font-weight:700;color:var(--green);white-space:nowrap;text-align:right}
.curr-badge{display:inline-flex;align-items:center;background:var(--gold-pale);color:var(--gold);border:1px solid var(--gold-lt);border-radius:20px;padding:2px 7px;font-size:.65rem;font-weight:700;white-space:nowrap}


.duration-cell{white-space:nowrap;font-size:.82rem}
.row-num{font-family:var(--fm);font-size:.72rem;color:var(--subtle);text-align:right;padding-right:4px}

/* (financial summary styles removed — public prospectus) */

/* Notes */
.notes-section{padding:18px 44px;background:var(--gold-pale);border-top:1px solid var(--border-lt);border-bottom:1px solid var(--border-lt)}
.notes-title{font-family:var(--fd);font-size:.88rem;font-weight:600;color:var(--navy);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;display:flex;align-items:center;gap:7px}
.notes-title i{color:var(--gold)}
.notes-list{list-style:none;display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 20px}
.notes-list li{font-size:.75rem;color:var(--muted);display:flex;align-items:flex-start;gap:6px;line-height:1.45}
.notes-list li::before{content:'◆';color:var(--gold);font-size:.5rem;margin-top:4px;flex-shrink:0}

/* Footer */
.cat-footer{background:var(--navy);padding:14px 44px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.cat-footer-inst{font-family:var(--fd);font-size:.84rem;font-weight:600;color:var(--gold-lt)}
.cat-footer-meta{font-family:var(--fm);font-size:.64rem;color:rgba(255,255,255,.3)}
.cat-footer-tagline{font-family:var(--fd);font-style:italic;font-size:.78rem;color:rgba(201,151,58,.55)}
.footer-div{width:1px;height:18px;background:rgba(255,255,255,.15)}

/* Ornament */
.ornament-divider{display:flex;align-items:center;gap:12px;padding:10px 44px}
.ornament-divider::before,.ornament-divider::after{content:'';flex:1;height:1px;background:var(--border-lt)}
.ornament-diamond{width:7px;height:7px;background:var(--gold);transform:rotate(45deg);flex-shrink:0;opacity:.5}

/* Print */
@media print{
    body{background:#fff;padding:0}
    .screen-toolbar{display:none!important}
    .catalogue{max-width:100%;box-shadow:none;border:none}
    .branch-section{page-break-inside:avoid}
    @page{size:A4 landscape;margin:8mm}
}
@media(max-width:800px){
    .cat-header{grid-template-columns:auto 1fr}
    .cat-doc-meta{display:none}
    .kpi-strip{grid-template-columns:1fr 1fr}
    .branch-section,.cat-footer,.gold-rule{padding-left:20px;padding-right:20px}
    .notes-section{padding-left:20px;padding-right:20px}
    .notes-list{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div class="screen-toolbar">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="courses.php" class="tb-btn tb-btn-back"><i class="bi bi-arrow-left"></i> Back</a>
        <span class="tb-label"><?= $h($sheetRefAll) ?></span>
    </div>
    <div style="display:flex;gap:8px">
        <?php if ($isSuperAdmin && $filterBranch): ?>
        <a href="?all=1" class="tb-btn" style="background:rgba(255,255,255,.7);color:var(--navy);border:1.5px solid var(--border)">All Branches</a>
        <?php endif; ?>
        <button class="tb-btn tb-btn-print" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Print / Save PDF
        </button>
    </div>
</div>

<div class="catalogue">
    <div class="cat-accent-top"></div>

    <!-- HEADER -->
    <div class="cat-header">
        <div class="logo-wrap">
            <?php if ($logoPath && file_exists($logoPath)): ?>
                <img src="<?= $h($logoPath) ?>" alt="Logo">
            <?php else:
                $wds = preg_split('/\s+/', trim($institutionName));
                $ini = implode('', array_map(fn($w) => strtoupper($w[0]??''), array_slice($wds,0,3)));
            ?>
                <div class="logo-initials"><?= $h($ini) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <div class="cat-inst-name"><?= $h($institutionName) ?></div>
            <?php if ($institutionTagline): ?>
            <div class="cat-inst-tagline"><?= $h($institutionTagline) ?></div>
            <?php endif; ?>
            <div class="cat-contacts">
                <?php if ($hqAddress): ?>
                <span class="cat-contact"><i class="bi bi-geo-alt-fill"></i><?= $h($hqAddress) ?></span>
                <?php endif; ?>
                <?php if ($contactPhone): ?>
                <span class="cat-contact"><i class="bi bi-telephone-fill"></i><?= $h($contactPhone) ?></span>
                <?php endif; ?>
                <?php if ($contactEmail): ?>
                <span class="cat-contact"><i class="bi bi-envelope-fill"></i><?= $h($contactEmail) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="cat-doc-meta">
            <div class="cat-doc-type">Course Catalogue</div>
            <div class="cat-ref"><?= $h($sheetRefAll) ?></div>
            <div class="cat-date">Printed: <?= $h($printDate) ?></div>
            <span class="scope-badge"><i class="bi bi-building me-1"></i><?= $h($scopeLabel) ?></span>
        </div>
    </div>

    <div class="gold-rule"></div>

    <!-- SUMMARY STRIP — public-safe info only -->
    <div class="kpi-strip">
        <div class="kpi-cell">
            <div class="kpi-val"><?= $activeCnt ?></div>
            <div class="kpi-lbl">Courses Available</div>
            <div class="kpi-sub">Currently enrolling</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-val"><?= count($byBranch) ?></div>
            <div class="kpi-lbl">Branch<?= count($byBranch)!=1?'es':'' ?></div>
            <div class="kpi-sub"><?= $h($scopeLabel) ?></div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-val"><?= $totalCourses ?></div>
            <div class="kpi-lbl">Total Programmes</div>
            <div class="kpi-sub"><?= $activeCnt ?> active · <?= $totalCourses - $activeCnt ?> inactive</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-val" style="font-size:1.05rem;font-family:var(--fb)">Flexible</div>
            <div class="kpi-lbl">Payment Plans</div>
            <div class="kpi-sub">Ask at admissions</div>
        </div>
    </div>

    <!-- BRANCH SECTIONS -->
    <?php
    $globalRow = 0;
    foreach ($byBranch as $bName => $bCourses):

    ?>
    <div class="branch-section">
        <div class="branch-title">
            <i class="bi bi-building-fill" style="color:var(--gold);font-size:1rem"></i>
            <div class="branch-title-text"><?= $h($bName) ?></div>
            <div class="branch-title-line"></div>
            <div class="branch-title-count"><?= count($bCourses) ?> course<?= count($bCourses)!=1?'s':'' ?></div>
        </div>

        <table class="course-tbl">
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th>Course</th>
                    <th>Duration</th>
                    <th style="text-align:right">Reg. Fee</th>
                    <th style="text-align:right">Tuition</th>
                    <th style="text-align:right">Total Fee</th>
                    <th style="text-align:center">Currency</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bCourses as $bc):
                $globalRow++;
                $cur    = $bc['currency_code'] ?? $defaultCurrency;
                $bSym   = $currencySymbols[$cur] ?? $cur;
                $reg    = (float)($bc['registration_fee']??0);
                $tui    = (float)($bc['tuition_fee']??0);
                $tot    = $reg + $tui;

            ?>
            <tr>
                <td class="row-num"><?= $globalRow ?></td>
                <td>
                    <div class="course-name-cell"><?= $h($bc['name']??'') ?></div>
                    <?php if (!empty($bc['code'])): ?>
                    <span class="course-code-badge"><?= $h($bc['code']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="duration-cell"><?= $h($bc['duration']??'—') ?></td>
                <td class="fee-cell"><?= $h($bSym.' '.number_format($reg,2)) ?></td>
                <td class="fee-cell"><?= $h($bSym.' '.number_format($tui,2)) ?></td>
                <td class="fee-total-cell"><?= $h($bSym.' '.number_format($tot,2)) ?></td>
                <td style="text-align:center"><span class="curr-badge"><?= $h($cur) ?></span></td>

                <td class="course-desc-cell"><?= $h(mb_strimwidth($bc['description']??'', 0, 80, '…')) ?></td>
            </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
    <?php if (array_key_last($byBranch) !== $bName): ?>
    <div class="ornament-divider"><div class="ornament-diamond"></div></div>
    <?php endif; ?>
    <?php endforeach; ?>


    <!-- HOW TO APPLY CTA -->
    <div style="background:var(--navy);padding:18px 44px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
            <div style="font-family:var(--fd);font-size:1.1rem;font-weight:600;color:var(--gold-lt);margin-bottom:4px">
                <i class="bi bi-person-plus-fill" style="margin-right:8px;color:var(--gold)"></i>Ready to Enroll?
            </div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.55)">
                Visit any branch with your documents and registration fee to secure your place.
                <?php if ($contactPhone): ?>Call us: <strong style="color:var(--gold-lt)"><?= $h($contactPhone) ?></strong><?php endif; ?>
            </div>
        </div>
        <div style="font-family:var(--fm);font-size:.7rem;color:rgba(255,255,255,.3);text-align:right">
            <?= $activeCnt ?> courses available &nbsp;·&nbsp; <?= $h($scopeLabel) ?><br><?= $h($printDate) ?>
        </div>
    </div>

    <!-- NOTES -->
    <div class="notes-section">
        <div class="notes-title"><i class="bi bi-info-circle-fill"></i> Important Notes</div>
        <ul class="notes-list">
            <li>All listed courses are currently open for enrollment — visit any branch to apply.</li>
            <li>Registration fees are non-refundable once enrollment is confirmed.</li>
            <li>Flexible payment plans may be available — enquire at the admissions office.</li>
            <li>A minimum attendance of 75% is required to receive your completion certificate.</li>
            <li>Fees shown reflect each course's billing currency; ask the branch about conversions.</li>
            <li>This prospectus is valid for the current academic period only and subject to change.</li>
        </ul>
    </div>

    <!-- FOOTER -->
    <div class="cat-footer">
        <div class="cat-footer-inst"><?= $h($institutionName) ?></div>
        <div class="footer-div"></div>
        <div class="cat-footer-tagline"><?= $h($institutionTagline) ?></div>
        <div class="footer-div"></div>
        <div class="cat-footer-meta">
            <?= $h($sheetRefAll) ?> &nbsp;·&nbsp; Printed <?= $h($printDate) ?> &nbsp;·&nbsp;
            <?= $activeCnt ?> programmes available &nbsp;·&nbsp; <?= $h($scopeLabel) ?>
        </div>
    </div>
    <div class="cat-accent-bot"></div>
</div>
</body>
</html>
    <?php
    exit;
}

// ════════════════════════════════════════════════════════════════
// SINGLE COURSE SHEET (original mode continues below)
// ════════════════════════════════════════════════════════════════
$pageTitle = 'Course Information Sheet — ' . ($course['name'] ?? 'Course');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Jost:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ════════════════════════════════════════════════════════════════
   COURSE INFORMATION SHEET
   Aesthetic: Refined Academic — editorial letterpress feel
   Fonts: Cormorant Garamond (titles) + Jost (body) + DM Mono (data)
   Palette: Deep navy with gold accents on cream
════════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --navy:      #0B1D35;
    --navy-md:   #152B4E;
    --navy-lt:   #1E3A66;
    --gold:      #C9973A;
    --gold-lt:   #F0D9A8;
    --gold-pale: #FBF5E8;
    --cream:     #FDFAF4;
    --cream-dk:  #F5EFE0;
    --ink:       #1A1A2E;
    --muted:     #5A6478;
    --subtle:    #8A95A3;
    --border:    #D8C99A;
    --border-lt: #EDE3C6;
    --white:     #FFFFFF;
    --red:       #B91C1C;
    --green:     #166534;

    --fd: 'Cormorant Garamond', Georgia, serif;
    --fb: 'Jost', system-ui, sans-serif;
    --fm: 'DM Mono', monospace;
}

body {
    font-family: var(--fb);
    background: #E8E0D0;
    color: var(--ink);
    min-height: 100vh;
    padding: 30px 20px 60px;
}

/* ── Screen toolbar ── */
.screen-toolbar {
    max-width: 900px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.tb-left { display: flex; align-items: center; gap: 10px; }
.tb-right { display: flex; gap: 10px; }

.tb-btn {
    display: inline-flex; align-items: center; gap: 7px;
    height: 38px; padding: 0 16px; border: none; border-radius: 8px;
    font-family: var(--fb); font-size: .84rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all .15s;
}
.tb-btn-print { background: var(--navy); color: #fff; }
.tb-btn-print:hover { background: var(--navy-lt); }
.tb-btn-back { background: rgba(255,255,255,.7); color: var(--navy); border: 1.5px solid var(--border); }
.tb-btn-back:hover { background: #fff; }
.tb-label {
    font-family: var(--fm); font-size: .72rem; color: var(--muted);
    background: rgba(255,255,255,.6); border: 1px solid var(--border-lt);
    border-radius: 6px; padding: 4px 10px;
}

/* ══════════════════════════════════════════════════════════════
   THE SHEET — print-ready A4-proportioned card
══════════════════════════════════════════════════════════════ */
.sheet {
    max-width: 900px;
    margin: 0 auto;
    background: var(--cream);
    border: 1px solid var(--border);
    box-shadow: 0 20px 60px rgba(0,0,0,.18), 0 4px 16px rgba(0,0,0,.10);
    position: relative;
    overflow: hidden;
}

/* ── Decorative corner ornament ── */
.sheet::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 220px; height: 220px;
    background: radial-gradient(ellipse at top right, rgba(201,151,58,.12) 0%, transparent 70%);
    pointer-events: none;
}
.sheet::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 180px; height: 180px;
    background: radial-gradient(ellipse at bottom left, rgba(11,29,53,.07) 0%, transparent 70%);
    pointer-events: none;
}

/* ── Top gold accent bar ── */
.sheet-accent-top {
    height: 6px;
    background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 50%, var(--navy) 100%);
}

/* ── Header ── */
.sheet-header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 24px;
    padding: 32px 44px 24px;
    border-bottom: 1px solid var(--border-lt);
    position: relative;
}

.logo-wrap {
    width: 80px; height: 80px;
    border-radius: 14px;
    background: var(--navy);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(11,29,53,.25);
}
.logo-wrap img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
.logo-initials {
    font-family: var(--fd);
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: .05em;
    line-height: 1;
}

.inst-info { min-width: 0; }
.inst-name {
    font-family: var(--fd);
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -.01em;
    line-height: 1.1;
    margin-bottom: 4px;
}
.inst-tagline {
    font-family: var(--fb);
    font-size: .78rem;
    font-weight: 500;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: .18em;
    margin-bottom: 12px;
}
.inst-contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 16px;
}
.inst-contact-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    color: var(--muted);
    font-family: var(--fb);
}
.inst-contact-item i { color: var(--gold); font-size: .8rem; }

.doc-meta {
    text-align: right;
    flex-shrink: 0;
}
.doc-type-label {
    font-family: var(--fd);
    font-size: 1rem;
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: 8px;
}
.doc-ref {
    font-family: var(--fm);
    font-size: .7rem;
    color: var(--subtle);
    margin-bottom: 4px;
}
.doc-date {
    font-size: .74rem;
    color: var(--muted);
    margin-bottom: 10px;
}
.doc-stamp {
    display: inline-block;
    background: var(--navy);
    color: var(--gold);
    font-family: var(--fd);
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 4px;
}
.doc-stamp.inactive { background: #78350F; color: #FDE68A; }
.doc-stamp.archived { background: #374151; color: #D1D5DB; }

/* ── Gold rule ── */
.gold-rule {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold) 20%, var(--gold) 80%, transparent);
    margin: 0 44px;
    opacity: .5;
}

/* ── Course Hero ── */
.course-hero {
    padding: 28px 44px 24px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 24px;
    align-items: start;
}
.course-hero-left {}
.course-eyebrow {
    font-family: var(--fb);
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .2em;
    color: var(--gold);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.course-eyebrow::before, .course-eyebrow::after {
    content: '';
    flex: 0 0 24px;
    height: 1px;
    background: var(--gold);
    opacity: .5;
}
.course-title {
    font-family: var(--fd);
    font-size: 2.6rem;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -.02em;
    line-height: 1.1;
    margin-bottom: 10px;
}
.course-code-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.course-code {
    font-family: var(--fm);
    font-size: .8rem;
    font-weight: 500;
    color: var(--navy);
    background: var(--cream-dk);
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 3px 10px;
}
.branch-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--navy);
    color: var(--gold-lt);
    border-radius: 20px;
    padding: 3px 12px;
    font-size: .72rem;
    font-weight: 600;
    font-family: var(--fb);
}
.course-desc {
    font-family: var(--fd);
    font-size: 1.05rem;
    font-style: italic;
    color: var(--muted);
    line-height: 1.7;
    max-width: 560px;
}

/* Fee hero box */
.fee-hero {
    background: var(--navy);
    border-radius: 16px;
    padding: 20px 24px;
    min-width: 200px;
    text-align: center;
    box-shadow: 0 8px 24px rgba(11,29,53,.2);
    position: relative;
    overflow: hidden;
}
.fee-hero::before {
    content: '';
    position: absolute;
    top: -20px; right: -20px;
    width: 100px; height: 100px;
    background: radial-gradient(circle, rgba(201,151,58,.2) 0%, transparent 70%);
}
.fee-hero-label {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .2em;
    color: rgba(201,151,58,.7);
    margin-bottom: 4px;
}
.fee-hero-currency {
    font-family: var(--fm);
    font-size: .72rem;
    color: var(--gold-lt);
    margin-bottom: 2px;
}
.fee-hero-amount {
    font-family: var(--fd);
    font-size: 2.4rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: -.02em;
    line-height: 1;
    margin-bottom: 16px;
}
.fee-hero-breakdown {
    border-top: 1px solid rgba(201,151,58,.25);
    padding-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.fee-hero-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .72rem;
    color: rgba(255,255,255,.6);
}
.fee-hero-row .val {
    font-family: var(--fm);
    color: rgba(255,255,255,.85);
}

/* ── Divider ornament ── */
.ornament-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 44px;
    margin: 4px 0;
}
.ornament-divider::before, .ornament-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-lt);
}
.ornament-diamond {
    width: 8px; height: 8px;
    background: var(--gold);
    transform: rotate(45deg);
    flex-shrink: 0;
    opacity: .6;
}

/* ── Details grid ── */
.details-section {
    padding: 24px 44px;
}
.details-section-title {
    font-family: var(--fd);
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.details-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-lt);
}
.details-section-title i {
    color: var(--gold);
    font-size: 1rem;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: var(--border-lt);
    border: 1px solid var(--border-lt);
    border-radius: 10px;
    overflow: hidden;
}
.detail-cell {
    background: var(--cream);
    padding: 16px 18px;
}
.detail-cell.wide { grid-column: span 2; }
.detail-cell.full { grid-column: span 3; }
.detail-label {
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .2em;
    color: var(--subtle);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.detail-label i { color: var(--gold); font-size: .72rem; }
.detail-value {
    font-family: var(--fm);
    font-size: .88rem;
    font-weight: 500;
    color: var(--navy);
}
.detail-value.large {
    font-family: var(--fd);
    font-size: 1.15rem;
    font-weight: 600;
    font-family: var(--fb);
}
.detail-empty { color: var(--subtle); font-style: italic; font-family: var(--fb); font-size: .82rem; }

/* ── Fee breakdown table ── */
.fee-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
}
.fee-table tr td {
    padding: 10px 14px;
    font-size: .875rem;
    border-bottom: 1px solid var(--border-lt);
}
.fee-table tr:last-child td { border-bottom: none; }
.fee-table .fee-label { color: var(--muted); font-family: var(--fb); }
.fee-table .fee-amount {
    text-align: right;
    font-family: var(--fm);
    font-weight: 500;
    color: var(--navy);
}
.fee-table .fee-total-row td {
    background: var(--navy);
    color: #fff;
    font-weight: 700;
    border-bottom: none;
}
.fee-table .fee-total-row .fee-amount {
    color: var(--gold);
    font-size: 1rem;
}

/* ── Enrollment bar ── */
.enrollment-visual {
    margin-top: 8px;
}
.enroll-track {
    height: 8px;
    background: var(--cream-dk);
    border-radius: 10px;
    overflow: hidden;
    margin-top: 6px;
    border: 1px solid var(--border-lt);
}
.enroll-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--navy), var(--gold));
    border-radius: 10px;
    transition: width .6s ease;
}

/* ── Important notes ── */
.notes-section {
    padding: 20px 44px;
    background: var(--gold-pale);
    border-top: 1px solid var(--border-lt);
    border-bottom: 1px solid var(--border-lt);
}
.notes-title {
    font-family: var(--fd);
    font-size: .9rem;
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .1em;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.notes-title i { color: var(--gold); }
.notes-list {
    list-style: none;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 24px;
}
.notes-list li {
    font-size: .78rem;
    color: var(--muted);
    display: flex;
    align-items: flex-start;
    gap: 7px;
    line-height: 1.5;
}
.notes-list li::before {
    content: '◆';
    color: var(--gold);
    font-size: .55rem;
    margin-top: 4px;
    flex-shrink: 0;
}

/* ── Contact / location block ── */
.location-section {
    padding: 24px 44px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    border-bottom: 1px solid var(--border-lt);
}
.loc-card {
    background: var(--cream-dk);
    border: 1px solid var(--border-lt);
    border-radius: 10px;
    padding: 18px 20px;
}
.loc-card-title {
    font-family: var(--fd);
    font-size: .95rem;
    font-weight: 600;
    color: var(--navy);
    letter-spacing: .05em;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.loc-card-title i { color: var(--gold); font-size: .9rem; }
.loc-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: .8rem;
    color: var(--muted);
    margin-bottom: 8px;
    line-height: 1.5;
}
.loc-row:last-child { margin-bottom: 0; }
.loc-row i {
    color: var(--gold);
    font-size: .82rem;
    margin-top: 2px;
    flex-shrink: 0;
    width: 14px;
}
.loc-row a { color: var(--navy); text-decoration: none; font-weight: 500; }
.loc-row a:hover { text-decoration: underline; }

/* ── QR / Registration CTA ── */
.cta-section {
    padding: 20px 44px 28px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 20px;
    align-items: center;
}
.cta-text h3 {
    font-family: var(--fd);
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 6px;
}
.cta-text p {
    font-size: .82rem;
    color: var(--muted);
    line-height: 1.6;
}
.cta-register {
    background: var(--navy);
    color: var(--gold);
    border: none;
    border-radius: 10px;
    padding: 12px 24px;
    font-family: var(--fd);
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: .05em;
    cursor: pointer;
    text-transform: uppercase;
    white-space: nowrap;
    box-shadow: 0 4px 16px rgba(11,29,53,.2);
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

/* ── Footer ── */
.sheet-footer {
    background: var(--navy);
    padding: 16px 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.footer-inst {
    font-family: var(--fd);
    font-size: .85rem;
    font-weight: 600;
    color: var(--gold-lt);
    letter-spacing: .05em;
}
.footer-meta {
    font-family: var(--fm);
    font-size: .65rem;
    color: rgba(255,255,255,.35);
}
.footer-divider {
    width: 1px;
    height: 20px;
    background: rgba(255,255,255,.15);
}
.footer-tagline {
    font-family: var(--fd);
    font-style: italic;
    font-size: .8rem;
    color: rgba(201,151,58,.6);
}

/* ── Bottom accent bar ── */
.sheet-accent-bot {
    height: 4px;
    background: linear-gradient(90deg, var(--gold) 0%, var(--navy) 50%, var(--gold) 100%);
}

/* ════════════════════════════════════════════════════════════
   PRINT
════════════════════════════════════════════════════════════ */
@media print {
    body { background: #fff; padding: 0; }
    .screen-toolbar { display: none !important; }
    .sheet {
        max-width: 100%;
        box-shadow: none;
        border: none;
    }
    .cta-register { display: none; }
    a { color: inherit !important; }
    @page { size: A4; margin: 0; }
}

@media (max-width: 700px) {
    .sheet-header { grid-template-columns: auto 1fr; }
    .doc-meta { display: none; }
    .course-hero { grid-template-columns: 1fr; }
    .fee-hero { min-width: auto; }
    .details-grid { grid-template-columns: 1fr 1fr; }
    .detail-cell.wide, .detail-cell.full { grid-column: span 2; }
    .location-section { grid-template-columns: 1fr; }
    .notes-list { grid-template-columns: 1fr; }
    .cta-section { grid-template-columns: 1fr; }
    .sheet-header, .course-hero, .details-section, .location-section,
    .notes-section, .cta-section, .sheet-footer { padding-left: 20px; padding-right: 20px; }
    .gold-rule { margin: 0 20px; }
    .ornament-divider { padding: 0 20px; }
    .inst-name { font-size: 1.5rem; }
    .course-title { font-size: 1.8rem; }
}
</style>
</head>
<body>

<!-- ── Screen toolbar ── -->
<div class="screen-toolbar">
    <div class="tb-left">
        <a href="courses.php" class="tb-btn tb-btn-back">
            <i class="bi bi-arrow-left"></i> Back to Courses
        </a>
        <span class="tb-label"><?= $h($sheetRef) ?></span>
    </div>
    <div class="tb-right">
        <button class="tb-btn tb-btn-print" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Print / Save PDF
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     THE INFORMATION SHEET
══════════════════════════════════════════════════════════════ -->
<div class="sheet">

    <!-- Top accent -->
    <div class="sheet-accent-top"></div>

    <!-- ── HEADER ── -->
    <div class="sheet-header">

        <!-- Logo -->
        <div class="logo-wrap">
            <?php if ($logoPath && file_exists($logoPath)): ?>
                <img src="<?= $h($logoPath) ?>" alt="Logo">
            <?php else:
                // Generate initials from institution name
                $words   = preg_split('/\s+/', trim($institutionName));
                $initials= implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice($words, 0, 3)));
                $initials= substr($initials, 0, 3);
            ?>
                <div class="logo-initials"><?= $h($initials) ?></div>
            <?php endif; ?>
        </div>

        <!-- Institution info -->
        <div class="inst-info">
            <div class="inst-name"><?= $h($institutionName) ?></div>
            <?php if ($institutionTagline): ?>
            <div class="inst-tagline"><?= $h($institutionTagline) ?></div>
            <?php endif; ?>
            <div class="inst-contacts">
                <?php if ($displayAddress): ?>
                <span class="inst-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <?= $h($displayAddress) ?>
                </span>
                <?php endif; ?>
                <?php if ($displayPhone): ?>
                <span class="inst-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <?= $h($displayPhone) ?>
                </span>
                <?php endif; ?>
                <?php if ($displayEmail): ?>
                <span class="inst-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <?= $h($displayEmail) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Document meta -->
        <div class="doc-meta">
            <div class="doc-type-label">Course<br>Information Sheet</div>
            <div class="doc-ref">Ref: <?= $h($sheetRef) ?></div>
            <div class="doc-date">Issued: <?= $h($printDate) ?></div>
            <?php
            $statusClass = strtolower($course['status'] ?? 'active');
            $statusLabel = $course['status'] ?? 'Active';
            ?>
            <span class="doc-stamp <?= $h($statusClass) ?>"><?= $h($statusLabel) ?></span>
        </div>

    </div>

    <div class="gold-rule"></div>

    <!-- ── COURSE HERO ── -->
    <div class="course-hero">
        <div class="course-hero-left">
            <div class="course-eyebrow">Official Course Document</div>
            <h1 class="course-title"><?= $h($course['name'] ?? 'Untitled Course') ?></h1>
            <div class="course-code-wrap">
                <?php if (!empty($course['code'])): ?>
                <span class="course-code"><?= $h($course['code']) ?></span>
                <?php endif; ?>
                <?php if ($branchName): ?>
                <span class="branch-pill">
                    <i class="bi bi-building-fill" style="font-size:.7rem"></i>
                    <?= $h($branchName) ?> Branch
                </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($course['description'])): ?>
            <p class="course-desc"><?= $h($course['description']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Fee hero -->
        <div class="fee-hero">
            <div class="fee-hero-label">Total Course Fee</div>
            <div class="fee-hero-currency"><?= $h($courseCurrency) ?></div>
            <div class="fee-hero-amount"><?= $h(number_format($totalFee, 2)) ?></div>
            <div class="fee-hero-breakdown">
                <div class="fee-hero-row">
                    <span>Registration</span>
                    <span class="val"><?= $h($sym . ' ' . number_format($regFee, 2)) ?></span>
                </div>
                <div class="fee-hero-row">
                    <span>Tuition</span>
                    <span class="val"><?= $h($sym . ' ' . number_format($tuiFee, 2)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Ornament -->
    <div class="ornament-divider"><div class="ornament-diamond"></div></div>

    <!-- ── COURSE DETAILS GRID ── -->
    <div class="details-section">
        <div class="details-section-title">
            <i class="bi bi-list-check"></i> Course Details
        </div>
        <div class="details-grid">

            <div class="detail-cell">
                <div class="detail-label"><i class="bi bi-clock-fill"></i> Duration</div>
                <div class="detail-value large"><?= $h($course['duration'] ?? '—') ?></div>
            </div>

            <div class="detail-cell">
                <div class="detail-label"><i class="bi bi-calendar-week-fill"></i> Duration (Weeks)</div>
                <div class="detail-value large">
                    <?= !empty($course['duration_weeks']) ? $h($course['duration_weeks'] . ' weeks') : '<span class="detail-empty">Not specified</span>' ?>
                </div>
            </div>

            <div class="detail-cell">
                <div class="detail-label"><i class="bi bi-people-fill"></i> Capacity</div>
                <div class="detail-value large">
                    <?= !empty($course['capacity']) ? $h($course['capacity'] . ' students') : '<span class="detail-empty">Unlimited</span>' ?>
                </div>
            </div>

            
            <div class="detail-cell">
                <div class="detail-label"><i class="bi bi-building-fill"></i> Branch</div>
                <div class="detail-value large"><?= $branchName ? $h($branchName) : '<span class="detail-empty">All Branches</span>' ?></div>
            </div>

            <div class="detail-cell">
                <div class="detail-label"><i class="bi bi-coin"></i> Currency</div>
                <div class="detail-value large"><?= $h($courseCurrency) ?> (<?= $h($sym) ?>)</div>
            </div>

        </div>
    </div>

    <!-- Ornament -->
    <div class="ornament-divider"><div class="ornament-diamond"></div></div>

    <!-- ── FEE STRUCTURE ── -->
    <div class="details-section" style="padding-top:16px">
        <div class="details-section-title">
            <i class="bi bi-cash-coin"></i> Fee Structure
        </div>
        <table class="fee-table">
            <tr>
                <td class="fee-label">
                    <i class="bi bi-receipt" style="color:var(--gold);margin-right:6px"></i>
                    Registration Fee
                    <div style="font-size:.72rem;color:var(--subtle);margin-top:2px">One-time payment upon enrollment</div>
                </td>
                <td class="fee-amount"><?= $h($fmtFee($regFee)) ?></td>
            </tr>
            <tr>
                <td class="fee-label">
                    <i class="bi bi-mortarboard-fill" style="color:var(--gold);margin-right:6px"></i>
                    Tuition Fee
                    <div style="font-size:.72rem;color:var(--subtle);margin-top:2px">Course instruction and materials</div>
                </td>
                <td class="fee-amount"><?= $h($fmtFee($tuiFee)) ?></td>
            </tr>
            <tr class="fee-total-row">
                <td class="fee-label" style="font-weight:700;font-size:.95rem">
                    <i class="bi bi-check-circle-fill" style="color:var(--gold);margin-right:6px"></i>
                    Total Fee Payable
                </td>
                <td class="fee-amount"><?= $h($fmtFee($totalFee)) ?></td>
            </tr>
        </table>
    </div>

    <!-- ── IMPORTANT NOTES ── -->
    <div class="notes-section">
        <div class="notes-title">
            <i class="bi bi-info-circle-fill"></i>
            Important Information
        </div>
        <ul class="notes-list">
            <li>Registration fee is non-refundable once enrollment is confirmed.</li>
            <li>Partial payment plans may be available — enquire at the branch office.</li>
            <li>A minimum attendance of 75% is required for course completion.</li>
            <li>Certificates are issued upon full payment clearance and course completion.</li>
            <li>Course fees are quoted in <?= $h($courseCurrency) ?> and subject to annual review.</li>
            <li>This document is valid for the current academic period only.</li>
        </ul>
    </div>

    <!-- ── CONTACT & LOCATION ── -->
    <div class="location-section">

        <!-- Branch / Campus info -->
        <div class="loc-card">
            <div class="loc-card-title">
                <i class="bi bi-building-fill"></i>
                <?= $branchName ? $h($branchName) . ' Branch' : 'Campus' ?>
            </div>
            <?php if ($displayAddress): ?>
            <div class="loc-row">
                <i class="bi bi-geo-alt-fill"></i>
                <span><?= $h($displayAddress) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($displayPhone): ?>
            <div class="loc-row">
                <i class="bi bi-telephone-fill"></i>
                <a href="tel:<?= $h($displayPhone) ?>"><?= $h($displayPhone) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($displayEmail): ?>
            <div class="loc-row">
                <i class="bi bi-envelope-fill"></i>
                <a href="mailto:<?= $h($displayEmail) ?>"><?= $h($displayEmail) ?></a>
            </div>
            <?php endif; ?>
            <?php if (!$displayAddress && !$displayPhone && !$displayEmail): ?>
            <div class="loc-row" style="color:var(--subtle);font-style:italic">
                <i class="bi bi-info-circle"></i>
                Contact details not configured. Update branch settings.
            </div>
            <?php endif; ?>
        </div>

        <!-- Institution head office -->
        <div class="loc-card">
            <div class="loc-card-title">
                <i class="bi bi-bank"></i>
                Head Office
            </div>
            <div class="loc-row">
                <i class="bi bi-journal-text"></i>
                <strong style="color:var(--navy)"><?= $h($institutionName) ?></strong>
            </div>
            <?php if ($hqAddress): ?>
            <div class="loc-row">
                <i class="bi bi-geo-alt-fill"></i>
                <span><?= $h($hqAddress) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($contactPhone): ?>
            <div class="loc-row">
                <i class="bi bi-telephone-fill"></i>
                <a href="tel:<?= $h($contactPhone) ?>"><?= $h($contactPhone) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($contactEmail): ?>
            <div class="loc-row">
                <i class="bi bi-envelope-fill"></i>
                <a href="mailto:<?= $h($contactEmail) ?>"><?= $h($contactEmail) ?></a>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── CTA ── -->
    <div class="cta-section">
        <div class="cta-text">
            <h3>Ready to Enroll?</h3>
            <p>
                Visit the <?= $branchName ? $h($branchName) . ' branch' : 'nearest branch' ?> with a copy of this sheet,
                your identification, and the registration fee of <strong><?= $h($fmtFee($regFee)) ?></strong>.
                <?php if ($displayPhone): ?>
                Call us at <strong><?= $h($displayPhone) ?></strong> to reserve your place.
                <?php endif; ?>
            </p>
        </div>
        <a href="student_registration.php<?= $courseId ? '?course_id=' . $courseId : '' ?>"
           class="cta-register">
            <i class="bi bi-person-plus-fill"></i>
            Enroll Now
        </a>
    </div>

    <!-- ── FOOTER ── -->
    <div class="sheet-footer">
        <div class="footer-inst"><?= $h($institutionName) ?></div>
        <div class="footer-divider"></div>
        <div class="footer-tagline"><?= $h($institutionTagline) ?></div>
        <div class="footer-divider"></div>
        <div class="footer-meta">
            <?= $h($sheetRef) ?> &nbsp;·&nbsp; Printed <?= $h($printDate) ?> &nbsp;·&nbsp; Page 1 of 1
        </div>
    </div>

    <!-- Bottom accent -->
    <div class="sheet-accent-bot"></div>

</div><!-- /sheet -->

</body>
</html>