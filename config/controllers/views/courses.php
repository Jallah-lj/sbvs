<?php
ob_start();
session_start();

// Path-adaptive loader — works regardless of deployment depth
function cr_findFile(string $filename): string {
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($dir . DIRECTORY_SEPARATOR . $filename)) {
            return $dir . DIRECTORY_SEPARATOR . $filename;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    // Fallback: try relative paths that match common SBVS structures
    $fallbacks = [
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
    ];
    foreach ($fallbacks as $fb) {
        if (file_exists($fb) && basename($fb) === $filename) return $fb;
    }
    throw new RuntimeException("Cannot locate {$filename} from " . __DIR__);
}

require_once cr_findFile('config.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php"); exit;
}

require_once cr_findFile('database.php');

// Load Course model — check standard locations
$_courseModelPath = null;
$_courseModelCandidates = [
    __DIR__ . '/../models/Course.php',          // views/../models/
    __DIR__ . '/../../models/Course.php',        // deeper nesting
    __DIR__ . '/../../../models/Course.php',
    dirname(cr_findFile('config.php')) . '/config/controllers/models/Course.php',
    dirname(cr_findFile('config.php')) . '/controllers/models/Course.php',
];
foreach ($_courseModelCandidates as $_p) {
    if (file_exists($_p)) { $_courseModelPath = $_p; break; }
}
if (!$_courseModelPath) {
    // Last resort: search upward for Course.php
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $candidate = $dir . '/models/Course.php';
        if (file_exists($candidate)) { $_courseModelPath = $candidate; break; }
        $dir = dirname($dir);
    }
}
if ($_courseModelPath) {
    require_once $_courseModelPath;
} else {
    // Define a minimal stub so the page doesn't 500 — shows an error instead
    die('<h3 style="font-family:sans-serif;color:#dc2626;padding:40px">Course model not found. Please ensure Course.php is at config/controllers/models/Course.php</h3>');
}

$db   = (new Database())->getConnection();
$role = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin','Branch Admin','Admin'])) {
    header("Location: dashboard.php"); exit;
}

$courseModel     = new Course($db);
$defaultCurrency = $courseModel->getDefaultCurrency();
$currencySymbol  = Course::currencySymbol($defaultCurrency);
$allCurrencies   = Course::availableCurrencies();

$branchName = '';
if (!$isSuperAdmin && $sessionBranch) {
    $bq = $db->prepare("SELECT name FROM branches WHERE id=?");
    $bq->execute([$sessionBranch]);
    $branchName = $bq->fetchColumn() ?: '';
}
$branches = $isSuperAdmin
    ? $db->query("SELECT id,name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle  = 'Courses';
$activePage = 'courses.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

:root {
    --cr-blue:    #1D4ED8;
    --cr-blue-md: #2563EB;
    --cr-blue-lt: #EFF6FF;
    --cr-blue-bd: #BFDBFE;
    --cr-green:   #059669;
    --cr-green-lt:#ECFDF5;
    --cr-green-bd:#A7F3D0;
    --cr-amber:   #D97706;
    --cr-amber-lt:#FFFBEB;
    --cr-amber-bd:#FDE68A;
    --cr-red:     #DC2626;
    --cr-red-lt:  #FEF2F2;
    --cr-slate:   #0F172A;
    --cr-muted:   #64748B;
    --cr-subtle:  #94A3B8;
    --cr-surface: #FFFFFF;
    --cr-page:    #F8FAFC;
    --cr-border:  #E2E8F0;
    --cr-border2: #CBD5E1;
    --cr-shadow:  0 1px 3px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.07);
    --cr-shadow-md:0 4px 8px rgba(0,0,0,.06),0 12px 32px rgba(0,0,0,.10);
    --cr-r:       10px;
    --cr-rlg:     16px;
    --cr-font:    'Plus Jakarta Sans',system-ui,sans-serif;
}
.cr-wrap,  .cr-wrap * { font-family: var(--cr-font); box-sizing: border-box; }

/* ── Page header ── */
.cr-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
.cr-header h2 { font-size:1.55rem; font-weight:800; color:var(--cr-slate); letter-spacing:-.03em; margin:0 0 5px; }
.cr-header p  { font-size:.875rem; color:var(--cr-muted); margin:0; }
.cr-branch-tag { display:inline-flex; align-items:center; gap:5px; background:var(--cr-blue-lt); color:var(--cr-blue-md); border:1px solid var(--cr-blue-bd); border-radius:20px; padding:3px 11px; font-size:.72rem; font-weight:700; margin-top:6px; }
.cr-currency-tag { display:inline-flex; align-items:center; gap:5px; background:var(--cr-green-lt); color:var(--cr-green); border:1px solid var(--cr-green-bd); border-radius:20px; padding:3px 11px; font-size:.72rem; font-weight:700; margin-top:6px; margin-left:4px; }

/* ── Buttons ── */
.cr-btn { display:inline-flex; align-items:center; gap:7px; height:40px; padding:0 18px; border:none; border-radius:var(--cr-r); font-family:var(--cr-font); font-size:.855rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap; }
.cr-btn-primary { background:var(--cr-blue-md); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.3); }
.cr-btn-primary:hover { background:var(--cr-blue); color:#fff; }
.cr-btn-ghost { background:var(--cr-surface); color:var(--cr-muted); border:1.5px solid var(--cr-border2); }
.cr-btn-ghost:hover { background:var(--cr-page); color:var(--cr-slate); }
.cr-btn-sm { height:34px; padding:0 14px; font-size:.8rem; }

/* ── KPI strip ── */
.cr-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.cr-kpi { background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:var(--cr-rlg); padding:20px 20px 16px; box-shadow:var(--cr-shadow); position:relative; overflow:hidden; transition:box-shadow .2s,transform .2s; }
.cr-kpi:hover { box-shadow:var(--cr-shadow-md); transform:translateY(-1px); }
.cr-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--cr-rlg) var(--cr-rlg) 0 0; }
.cr-kpi.kb::before { background:var(--cr-blue-md); }
.cr-kpi.kg::before { background:var(--cr-green); }
.cr-kpi.ka::before { background:var(--cr-amber); }
.cr-kpi.kr::before { background:var(--cr-red); }
.cr-kpi-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:12px; }
.kb .cr-kpi-icon { background:var(--cr-blue-lt); color:var(--cr-blue-md); }
.kg .cr-kpi-icon { background:var(--cr-green-lt); color:var(--cr-green); }
.ka .cr-kpi-icon { background:var(--cr-amber-lt); color:var(--cr-amber); }
.kr .cr-kpi-icon { background:var(--cr-red-lt); color:var(--cr-red); }
.cr-kpi-val { font-size:1.6rem; font-weight:800; color:var(--cr-slate); letter-spacing:-.02em; line-height:1; margin-bottom:4px; }
.cr-kpi-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--cr-subtle); }

/* ── View toggle ── */
.cr-view-toggle { display:flex; gap:4px; background:var(--cr-page); border:1px solid var(--cr-border); border-radius:9px; padding:3px; }
.cr-view-btn { width:32px; height:28px; border:none; border-radius:6px; background:transparent; color:var(--cr-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.88rem; transition:all .15s; }
.cr-view-btn.active { background:var(--cr-surface); color:var(--cr-blue-md); box-shadow:0 1px 3px rgba(0,0,0,.08); }

/* ── Filters ── */
.cr-filters { background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:var(--cr-rlg); padding:16px 20px; box-shadow:var(--cr-shadow); margin-bottom:18px; display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.cr-filter-field label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--cr-muted); display:block; margin-bottom:5px; }
.cr-fi, .cr-fs { height:36px; padding:0 11px; border:1.5px solid var(--cr-border2); border-radius:8px; font-family:var(--cr-font); font-size:.84rem; color:var(--cr-slate); background:#fff; outline:none; transition:border-color .15s; }
.cr-fi:focus, .cr-fs:focus { border-color:var(--cr-blue-md); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.cr-fs { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; padding-right:26px; cursor:pointer; }
.cr-filter-btn { height:36px; padding:0 14px; background:var(--cr-blue-md); color:#fff; border:none; border-radius:8px; font-family:var(--cr-font); font-size:.84rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; }

/* ── Card grid view ── */
.cr-card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
.cr-course-card { background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:var(--cr-rlg); box-shadow:var(--cr-shadow); overflow:hidden; transition:box-shadow .2s,transform .2s; display:flex; flex-direction:column; }
.cr-course-card:hover { box-shadow:var(--cr-shadow-md); transform:translateY(-2px); }
.cr-card-top { padding:18px 18px 14px; flex:1; }
.cr-card-status-bar { height:4px; }
.cr-card-status-bar.active   { background:var(--cr-green); }
.cr-card-status-bar.inactive { background:var(--cr-amber); }
.cr-card-status-bar.archived { background:var(--cr-subtle); }
.cr-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:10px; }
.cr-course-icon { width:44px; height:44px; border-radius:11px; background:var(--cr-blue-lt); color:var(--cr-blue-md); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.cr-course-name { font-size:.95rem; font-weight:700; color:var(--cr-slate); margin:0 0 3px; line-height:1.3; }
.cr-course-code { font-family:monospace; font-size:.72rem; color:var(--cr-muted); background:var(--cr-page); border:1px solid var(--cr-border); border-radius:4px; padding:1px 6px; }
.cr-course-branch { font-size:.75rem; color:var(--cr-muted); margin-top:4px; display:flex; align-items:center; gap:4px; }
.cr-card-meta { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:12px 0; }
.cr-meta-item label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--cr-subtle); display:block; margin-bottom:2px; }
.cr-meta-item span  { font-size:.855rem; font-weight:600; color:var(--cr-slate); }
.cr-fee-display { background:var(--cr-green-lt); border:1px solid var(--cr-green-bd); border-radius:8px; padding:10px 14px; margin-top:10px; }
.cr-fee-row { display:flex; justify-content:space-between; align-items:center; font-size:.82rem; }
.cr-fee-row .lbl { color:var(--cr-muted); }
.cr-fee-row .val { font-weight:700; color:var(--cr-slate); }
.cr-fee-row.total { border-top:1px solid var(--cr-green-bd); margin-top:6px; padding-top:6px; }
.cr-fee-row.total .val { font-size:.95rem; color:var(--cr-green); }
.cr-currency-pill { display:inline-flex; align-items:center; gap:3px; background:var(--cr-blue-lt); color:var(--cr-blue-md); border-radius:20px; padding:2px 7px; font-size:.68rem; font-weight:700; }
.cr-card-footer { padding:12px 18px; border-top:1px solid var(--cr-border); background:#FAFBFC; display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.cr-enroll-bar { flex:1; min-width:120px; }
.cr-enroll-label { font-size:.7rem; color:var(--cr-muted); display:flex; justify-content:space-between; margin-bottom:4px; }
.cr-progress { height:5px; background:var(--cr-border); border-radius:10px; overflow:hidden; }
.cr-progress-fill { height:100%; border-radius:10px; background:var(--cr-blue-md); transition:width .4s; }
.cr-progress-fill.full { background:var(--cr-red); }

/* ── Table card ── */
.cr-table-card { background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:var(--cr-rlg); box-shadow:var(--cr-shadow); overflow:hidden; }
.cr-table-card thead th { background:var(--cr-slate); color:rgba(255,255,255,.8); font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; padding:12px 14px; border:none; white-space:nowrap; }
.cr-table-card tbody td { padding:13px 14px; font-size:.855rem; border-bottom:1px solid var(--cr-border); vertical-align:middle; }
.cr-table-card tbody tr:last-child td { border-bottom:none; }
.cr-table-card tbody tr:hover td { background:var(--cr-blue-lt); }

/* ── Badges ── */
.cr-badge { display:inline-flex; align-items:center; gap:4px; border-radius:20px; padding:3px 10px; font-size:.7rem; font-weight:700; white-space:nowrap; }
.cb-active   { background:var(--cr-green-lt);  color:var(--cr-green); }
.cb-inactive { background:var(--cr-amber-lt);  color:var(--cr-amber); }
.cb-archived { background:#F1F5F9; color:var(--cr-muted); }

/* ── Action buttons ── */
.cr-act { width:30px; height:30px; border:none; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; cursor:pointer; transition:all .15s; }
.ca-edit { background:var(--cr-amber-lt); color:var(--cr-amber); }
.ca-edit:hover { background:var(--cr-amber); color:#fff; }
.ca-arch { background:var(--cr-red-lt); color:var(--cr-red); }
.ca-arch:hover { background:var(--cr-red); color:#fff; }
.ca-view { background:var(--cr-blue-lt); color:var(--cr-blue-md); }
.ca-view:hover { background:var(--cr-blue-md); color:#fff; }
.ca-rest { background:var(--cr-green-lt); color:var(--cr-green); }
.ca-rest:hover { background:var(--cr-green); color:#fff; }

/* ── Modal ── */
.cr-modal .modal-content { border:none; border-radius:20px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.18); font-family:var(--cr-font); }
.cr-modal .modal-header { padding:20px 26px 16px; border-bottom:1px solid var(--cr-border); }
.cr-modal .modal-header.primary { background:var(--cr-blue-md); color:#fff; border-bottom:none; }
.cr-modal .modal-header.primary .btn-close { filter:invert(1); }
.cr-modal .modal-header h5 { font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
.cr-modal .modal-body { background:var(--cr-page); padding:22px 26px; }
.cr-modal .modal-footer { background:var(--cr-surface); border-top:1px solid var(--cr-border); padding:14px 26px; }

/* ── Modal form ── */
.cr-field label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--cr-muted); display:block; margin-bottom:5px; }
.cr-field label span { color:var(--cr-red); }
.cr-input, .cr-select, .cr-textarea { width:100%; height:40px; padding:0 12px; border:1.5px solid var(--cr-border2); border-radius:8px; font-family:var(--cr-font); font-size:.875rem; color:var(--cr-slate); background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
.cr-input:focus, .cr-select:focus, .cr-textarea:focus { border-color:var(--cr-blue-md); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.cr-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; cursor:pointer; }
.cr-textarea { height:auto; padding:10px 12px; resize:vertical; }
.cr-input-group { display:flex; align-items:center; border:1.5px solid var(--cr-border2); border-radius:8px; overflow:hidden; background:#fff; transition:border-color .15s,box-shadow .15s; }
.cr-input-group:focus-within { border-color:var(--cr-blue-md); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.cr-input-pfx { padding:0 10px; height:40px; display:flex; align-items:center; font-size:.9rem; font-weight:700; color:var(--cr-muted); border-right:1.5px solid var(--cr-border); background:var(--cr-page); flex-shrink:0; min-width:36px; justify-content:center; }
.cr-input-group .cr-input { border:none; box-shadow:none; border-radius:0; padding-left:8px; }
.cr-section-label { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--cr-muted); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.cr-section-label::after { content:''; flex:1; height:1px; background:var(--cr-border); }

/* ── Fee preview box in modal ── */
.cr-fee-preview { background:var(--cr-green-lt); border:1.5px solid var(--cr-green-bd); border-radius:10px; padding:14px 16px; }
.cr-fee-preview-title { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--cr-green); margin-bottom:10px; display:flex; align-items:center; gap:5px; }
.cr-fee-preview-total { font-size:1.5rem; font-weight:800; color:var(--cr-green); }

/* ── DataTables ── */
.dataTables_wrapper .dataTables_filter input { border:1.5px solid var(--cr-border2)!important; border-radius:8px!important; height:34px!important; font-family:var(--cr-font)!important; font-size:.84rem!important; box-shadow:none!important; padding:0 10px!important; outline:none!important; }
.dataTables_wrapper .dataTables_filter input:focus { border-color:var(--cr-blue-md)!important; }
.dataTables_wrapper .dataTables_length select { border:1.5px solid var(--cr-border2)!important; border-radius:8px!important; height:34px!important; font-family:var(--cr-font)!important; }
.dataTables_wrapper .paginate_button.current,.dataTables_wrapper .paginate_button.current:hover { background:var(--cr-blue-md)!important; border-color:var(--cr-blue-md)!important; color:#fff!important; }

@media(max-width:1024px){ .cr-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:640px) { .cr-kpi-grid{ grid-template-columns:1fr 1fr; } .cr-card-grid{ grid-template-columns:1fr; } }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>
<div class="sbvs-layout">
<main class="sbvs-main cr-wrap">

    <!-- Header -->
    <div class="cr-header fade-in">
        <div>
            <h2><i class="bi bi-journals me-2" style="color:var(--cr-blue-md);font-size:1.2rem;vertical-align:middle"></i>Course Management</h2>
            <p>Create, manage, and track all courses and their fee structures across branches.</p>
            <?php if (!$isSuperAdmin && $branchName): ?>
            <span class="cr-branch-tag"><i class="bi bi-building-fill"></i><?= htmlspecialchars($branchName) ?></span>
            <?php endif; ?>
            <span class="cr-currency-tag"><i class="bi bi-coin"></i>Default: <?= htmlspecialchars($defaultCurrency) ?> (<?= htmlspecialchars($currencySymbol) ?>)</span>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <div class="cr-view-toggle">
                <button class="cr-view-btn active" id="btnCardView" title="Card View"><i class="bi bi-grid-fill"></i></button>
                <button class="cr-view-btn" id="btnTableView" title="Table View"><i class="bi bi-table"></i></button>
            </div>
            <a href="course_info_sheet.php?all=1" class="cr-btn cr-btn-ghost cr-btn-sm" target="_blank" style="text-decoration:none">
                <i class="bi bi-printer-fill"></i> Print Catalogue
            </a>
            <button class="cr-btn cr-btn-ghost cr-btn-sm" id="exportBtn">
                <i class="bi bi-download"></i> Export CSV
            </button>
            <?php if ($isSuperAdmin || $isBranchAdmin): ?>
            <button class="cr-btn cr-btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                <i class="bi bi-plus-circle-fill"></i> Add Course
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPIs -->
    <div class="cr-kpi-grid">
        <div class="cr-kpi kb fade-in" style="animation-delay:.05s">
            <div class="cr-kpi-icon"><i class="bi bi-journals"></i></div>
            <div class="cr-kpi-val" id="kpiActive">—</div>
            <div class="cr-kpi-lbl">Active Courses</div>
        </div>
        <div class="cr-kpi kg fade-in" style="animation-delay:.1s">
            <div class="cr-kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="cr-kpi-val" id="kpiEnrolled">—</div>
            <div class="cr-kpi-lbl">Total Enrollments</div>
        </div>
        <div class="cr-kpi ka fade-in" style="animation-delay:.15s">
            <div class="cr-kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="cr-kpi-val" id="kpiRevenue">—</div>
            <div class="cr-kpi-lbl">Fee Revenue</div>
        </div>
        <div class="cr-kpi kr fade-in" style="animation-delay:.2s">
            <div class="cr-kpi-icon"><i class="bi bi-archive-fill"></i></div>
            <div class="cr-kpi-val" id="kpiArchived">—</div>
            <div class="cr-kpi-lbl">Archived</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="cr-filters fade-in" style="animation-delay:.25s">
        <?php if ($isSuperAdmin): ?>
        <div class="cr-filter-field">
            <label>Branch</label>
            <select id="fBranch" class="cr-fs" style="width:160px">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="cr-filter-field">
            <label>Status</label>
            <select id="fStatus" class="cr-fs" style="width:140px">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Archived">Archived</option>
                <option value="all">All Statuses</option>
            </select>
        </div>
        <div class="cr-filter-field">
            <label>Currency</label>
            <select id="fCurrency" class="cr-fs" style="width:130px">
                <option value="">Any Currency</option>
                <?php foreach ($allCurrencies as $code => $sym): ?>
                <option value="<?= $code ?>"><?= $code ?> (<?= $sym ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cr-filter-field" style="flex:1;min-width:180px">
            <label>Search</label>
            <div style="display:flex;gap:8px">
                <input type="text" id="fSearch" class="cr-fi" style="flex:1" placeholder="Course name or code…">
                <button class="cr-filter-btn" id="applyFilter">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
            </div>
        </div>
    </div>

    <!-- Card grid view -->
    <div id="cardView" class="fade-in" style="animation-delay:.3s">
        <div class="cr-card-grid" id="courseCardGrid">
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--cr-muted)">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading courses…
            </div>
        </div>
    </div>

    <!-- Table view (hidden by default) -->
    <div id="tableView" style="display:none" class="fade-in">
        <div class="cr-table-card">
            <div style="padding:14px 18px;border-bottom:1px solid var(--cr-border);display:flex;align-items:center;justify-content:space-between">
                <h6 style="font-size:.9rem;font-weight:700;color:var(--cr-slate);margin:0;display:flex;align-items:center;gap:7px">
                    <i class="bi bi-table" style="color:var(--cr-blue-md)"></i> Course List
                </h6>
                <span id="tableCount" style="font-size:.78rem;color:var(--cr-muted)"></span>
            </div>
            <div class="table-responsive">
                <table id="coursesTable" class="table align-middle w-100 mb-0">
                    <thead><tr>
                        <th>Course</th><th>Branch</th><th>Duration</th>
                        <th>Reg. Fee</th><th>Tuition Fee</th><th>Total</th>
                        <th>Currency</th><th>Enrolled</th><th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>


<!-- ══ COURSE MODAL (Add / Edit) ════════════════════════════ -->
<div class="modal fade cr-modal" id="courseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="courseForm" class="modal-content">
            <div class="modal-header primary">
                <h5 id="courseModalTitle"><i class="bi bi-journals"></i> Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="courseId">

                <!-- Basic info -->
                <div class="cr-section-label"><i class="bi bi-info-circle-fill"></i> Course Details</div>
                <div class="row g-3 mb-4">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-md-6">
                        <div class="cr-field">
                            <label>Branch <span>*</span></label>
                            <select id="f_branch" class="cr-select" required>
                                <option value="">— Select Branch —</option>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="f_branch" value="<?= $sessionBranch ?>">
                    <?php endif; ?>

                    <div class="col-md-<?= $isSuperAdmin ? '6' : '12' ?>">
                        <div class="cr-field">
                            <label>Course Name <span>*</span></label>
                            <input type="text" id="f_name" class="cr-input" placeholder="e.g. Advanced Computer Programming" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Course Code</label>
                            <input type="text" id="f_code" class="cr-input" placeholder="e.g. CS-301" style="font-family:monospace;text-transform:uppercase">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Duration (Label) <span>*</span></label>
                            <input type="text" id="f_duration" class="cr-input" placeholder="e.g. 6 Months" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Duration (Weeks)</label>
                            <input type="number" id="f_duration_weeks" class="cr-input" min="1" placeholder="e.g. 24">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="cr-field">
                            <label>Capacity (Max Students)</label>
                            <input type="number" id="f_capacity" class="cr-input" min="1" placeholder="Leave blank for unlimited">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="cr-field">
                            <label>Status</label>
                            <select id="f_status" class="cr-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="cr-field">
                            <label>Description</label>
                            <textarea id="f_description" class="cr-textarea" rows="2" placeholder="Brief course description…"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Fee structure -->
                <div class="cr-section-label"><i class="bi bi-cash-coin"></i> Fee Structure</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Currency <span>*</span></label>
                            <select id="f_currency" class="cr-select" required onchange="updateFeePreview()">
                                <?php foreach ($allCurrencies as $code => $sym): ?>
                                <option value="<?= $code ?>" <?= $code === $defaultCurrency ? 'selected' : '' ?>>
                                    <?= $code ?> — <?= $sym ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Registration Fee</label>
                            <div class="cr-input-group">
                                <span class="cr-input-pfx" id="pfx_reg"><?= htmlspecialchars($currencySymbol) ?></span>
                                <input type="number" id="f_reg_fee" class="cr-input" min="0" step="0.01" value="0.00" oninput="updateFeePreview()">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cr-field">
                            <label>Tuition Fee</label>
                            <div class="cr-input-group">
                                <span class="cr-input-pfx" id="pfx_tui"><?= htmlspecialchars($currencySymbol) ?></span>
                                <input type="number" id="f_tui_fee" class="cr-input" min="0" step="0.01" value="0.00" oninput="updateFeePreview()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live fee preview -->
                <div class="cr-fee-preview">
                    <div class="cr-fee-preview-title"><i class="bi bi-calculator-fill"></i> Fee Summary</div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-end">
                        <div>
                            <div style="font-size:.78rem;color:var(--cr-green);margin-bottom:2px">
                                Reg: <span id="prev_reg">0.00</span> &nbsp;+&nbsp; Tuition: <span id="prev_tui">0.00</span>
                            </div>
                            <div style="font-size:.72rem;color:var(--cr-muted)">Total payable by student</div>
                        </div>
                        <div class="cr-fee-preview-total" id="prev_total">0.00</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cr-btn cr-btn-ghost cr-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm" id="saveCourseBtn">
                    <i class="bi bi-save-fill"></i> Save Course
                </button>
            </div>
        </form>
    </div>
</div>


<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
const API         = 'models/api/course_api.php';
const isSA        = <?= $isSuperAdmin  ? 'true' : 'false' ?>;
const isBA        = <?= $isBranchAdmin ? 'true' : 'false' ?>;
const myBranch    = <?= $sessionBranch ?>;
const defCurrency = <?= json_encode($defaultCurrency) ?>;

// Currency symbol map from PHP
const SYMBOLS = <?= json_encode($allCurrencies) ?>;

const esc  = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const sym  = code => SYMBOLS[code] || code;
const fmt  = (n, code) => (sym(code||defCurrency)) + ' ' + parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtN = n => parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const inits= n => String(n||'').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();

let dtTable, currentData = [], currentView = 'card';

// ── View toggle ───────────────────────────────────────────────
$('#btnCardView').on('click', () => { setView('card'); });
$('#btnTableView').on('click', () => { setView('table'); });
function setView(v) {
    currentView = v;
    $('#btnCardView, #btnTableView').removeClass('active');
    $(v === 'card' ? '#btnCardView' : '#btnTableView').addClass('active');
    $('#cardView').toggle(v === 'card');
    $('#tableView').toggle(v === 'table');
    if (v === 'table' && dtTable) dtTable.columns.adjust();
}

// ── Load KPIs ─────────────────────────────────────────────────
function loadKPIs() {
    const bid = isSA ? ($('#fBranch').val()||'') : myBranch;
    $.getJSON(API + '?action=stats&branch_id=' + bid, function(res) {
        if (!res.success) return;
        const d = res.data;
        $('#kpiActive').text(d.active_courses||0);
        $('#kpiEnrolled').text(d.total_enrollments||0);
        $('#kpiRevenue').text(fmt(d.total_revenue||0, defCurrency));
        $('#kpiArchived').text(d.archived_courses||0);
    });
}

// ── Load courses ──────────────────────────────────────────────
function loadCourses() {
    const params = {
        action:    'list',
        branch_id: isSA ? ($('#fBranch').val()||'') : myBranch,
        status:    $('#fStatus').val()||'Active',
        currency:  $('#fCurrency').val()||'',
        search:    $('#fSearch').val()||'',
    };
    $.getJSON(API, params, function(res) {
        currentData = (res.success || res.status === 'success') ? (res.data||[]) : [];
        $('#tableCount').text(currentData.length + ' course' + (currentData.length!==1?'s':''));
        renderCards(currentData);
        renderTable(currentData);
    }).fail(function(xhr) {
        let msg = 'Could not load courses.';
        try { const p = JSON.parse(xhr.responseText||'{}'); if(p.message) msg=p.message; } catch(e) {}
        Swal.fire('Error', msg, 'error');
    });
}

// ── Card renderer ─────────────────────────────────────────────
function renderCards(data) {
    if (!data.length) {
        $('#courseCardGrid').html(`<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--cr-muted)">
            <i class="bi bi-journals" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.25"></i>
            No courses found. Adjust your filters or add a new course.
        </div>`);
        return;
    }

    const cards = data.map(c => {
        const total    = parseFloat(c.registration_fee||0) + parseFloat(c.tuition_fee||0);
        const currency = c.currency_code || defCurrency;
        const enrolled = parseInt(c.enrolled_count||0);
        const capacity = c.capacity ? parseInt(c.capacity) : null;
        const pct      = capacity ? Math.min(100, Math.round((enrolled/capacity)*100)) : 0;
        const statusCls= (c.status||'active').toLowerCase();
        const canEdit  = isSA || isBA;

        const enrollBar = capacity
            ? `<div class="cr-enroll-bar">
                <div class="cr-enroll-label"><span>Enrollment</span><span>${enrolled}/${capacity}</span></div>
                <div class="cr-progress"><div class="cr-progress-fill ${pct>=100?'full':''}" style="width:${pct}%"></div></div>
               </div>`
            : `<div style="font-size:.78rem;color:var(--cr-muted)"><i class="bi bi-people me-1"></i>${enrolled} enrolled</div>`;

        return `
        <div class="cr-course-card fade-in">
            <div class="cr-card-status-bar ${statusCls}"></div>
            <div class="cr-card-top">
                <div class="cr-card-header">
                    <div>
                        <div class="cr-course-name">${esc(c.name)}</div>
                        ${c.code ? `<span class="cr-course-code">${esc(c.code)}</span>` : ''}
                        ${c.branch_name ? `<div class="cr-course-branch"><i class="bi bi-building-fill"></i>${esc(c.branch_name)}</div>` : ''}
                    </div>
                    <div class="cr-course-icon">${inits(c.name)}</div>
                </div>
                <div class="cr-card-meta">
                    <div class="cr-meta-item">
                        <label>Duration</label>
                        <span>${esc(c.duration||'—')}</span>
                    </div>
                    <div class="cr-meta-item">
                        <label>Status</label>
                        <span><span class="cr-badge ${statusCls==='active'?'cb-active':statusCls==='inactive'?'cb-inactive':'cb-archived'}">${esc(c.status||'Active')}</span></span>
                    </div>
                </div>
                <div class="cr-fee-display">
                    <div class="cr-fee-row">
                        <span class="lbl">Registration</span>
                        <span class="val">${fmt(c.registration_fee||0, currency)}</span>
                    </div>
                    <div class="cr-fee-row">
                        <span class="lbl">Tuition</span>
                        <span class="val">${fmt(c.tuition_fee||0, currency)}</span>
                    </div>
                    <div class="cr-fee-row total">
                        <span class="lbl" style="font-weight:700;color:var(--cr-green)">Total Fee</span>
                        <span class="val">${fmt(total, currency)} <span class="cr-currency-pill">${esc(currency)}</span></span>
                    </div>
                </div>
            </div>
            <div class="cr-card-footer">
                ${enrollBar}
                <div style="display:flex;gap:5px;flex-shrink:0">
                    ${canEdit && c.status!=='Archived' ? `
                    <button class="cr-act ca-edit" onclick="openEdit(${c.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="cr-act ca-arch" onclick="archiveCourse(${c.id},'${esc(c.name)}')" title="Archive"><i class="bi bi-archive-fill"></i></button>` : ''}
                    ${canEdit && c.status==='Archived' ? `
                    <button class="cr-act ca-rest" onclick="restoreCourse(${c.id})" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>` : ''}
                    <button class="cr-act ca-view" onclick="viewCourse(${c.id})" title="View Details"><i class="bi bi-eye-fill"></i></button>
                </div>
            </div>
        </div>`;
    }).join('');
    $('#courseCardGrid').html(cards);
}

// ── Table renderer ────────────────────────────────────────────
function renderTable(data) {
    if (dtTable) { dtTable.destroy(); dtTable = null; }
    $('#coursesTable tbody').empty();

    const tbody = data.map(c => {
        const total    = parseFloat(c.registration_fee||0) + parseFloat(c.tuition_fee||0);
        const currency = c.currency_code || defCurrency;
        const statusCls= (c.status||'active').toLowerCase();
        const canEdit  = isSA || isBA;
        return `<tr>
            <td>
                <div style="font-weight:700;color:var(--cr-slate)">${esc(c.name)}</div>
                ${c.code ? `<div style="font-family:monospace;font-size:.72rem;color:var(--cr-muted)">${esc(c.code)}</div>` : ''}
            </td>
            <td style="font-size:.82rem;color:var(--cr-muted)">${esc(c.branch_name||'—')}</td>
            <td style="font-size:.82rem">${esc(c.duration||'—')}</td>
            <td style="font-weight:600">${fmt(c.registration_fee||0, currency)}</td>
            <td style="font-weight:600">${fmt(c.tuition_fee||0, currency)}</td>
            <td style="font-weight:800;color:var(--cr-green)">${fmt(total, currency)}</td>
            <td><span class="cr-badge" style="background:var(--cr-blue-lt);color:var(--cr-blue-md);font-size:.68rem">${esc(currency)}</span></td>
            <td style="font-size:.82rem">${parseInt(c.enrolled_count||0)}</td>
            <td><span class="cr-badge ${statusCls==='active'?'cb-active':statusCls==='inactive'?'cb-inactive':'cb-archived'}">${esc(c.status||'Active')}</span></td>
            <td style="text-align:center">
                <div style="display:flex;gap:4px;justify-content:center">
                    ${canEdit && c.status!=='Archived' ? `
                    <button class="cr-act ca-edit" onclick="openEdit(${c.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="cr-act ca-arch" onclick="archiveCourse(${c.id},'${esc(c.name)}')" title="Archive"><i class="bi bi-archive-fill"></i></button>` : ''}
                    ${canEdit && c.status==='Archived' ? `
                    <button class="cr-act ca-rest" onclick="restoreCourse(${c.id})" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>` : ''}
                    <button class="cr-act ca-view" onclick="viewCourse(${c.id})" title="Details"><i class="bi bi-eye-fill"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');

    $('#coursesTable tbody').html(tbody);
    dtTable = $('#coursesTable').DataTable({
        retrieve: true, responsive: true, pageLength: 25,
        order: [[0,'asc']],
        language: { emptyTable: 'No courses match your filters.' }
    });
}

// ── Fee preview (inside modal) ────────────────────────────────
function updateFeePreview() {
    const code  = $('#f_currency').val() || defCurrency;
    const s     = sym(code);
    const reg   = parseFloat($('#f_reg_fee').val()||0);
    const tui   = parseFloat($('#f_tui_fee').val()||0);
    const total = reg + tui;
    $('#pfx_reg, #pfx_tui').text(s);
    $('#prev_reg').text(s + ' ' + fmtN(reg));
    $('#prev_tui').text(s + ' ' + fmtN(tui));
    $('#prev_total').text(s + ' ' + fmtN(total));
}

// ── Open Add modal ────────────────────────────────────────────
$('#courseModal').on('show.bs.modal', function(e) {
    if ($(e.relatedTarget).data('bs-target') === '#courseModal') {
        // Fresh add
        if (!$('#courseId').val()) {
            $('#courseModalTitle').html('<i class="bi bi-journals me-2"></i>Add New Course');
        }
    }
});
$('#courseModal').on('hidden.bs.modal', function() {
    $('#courseForm')[0].reset();
    $('#courseId').val('');
    $('#f_currency').val(defCurrency);
    updateFeePreview();
    $('#courseModalTitle').html('<i class="bi bi-journals me-2"></i>Add New Course');
});

// ── Open Edit modal ───────────────────────────────────────────
function openEdit(id) {
    $.getJSON(API + '?action=get&id=' + id, function(res) {
        if (!(res.success || res.status==='success')) {
            Swal.fire('Error', res.message||'Could not load course.', 'error'); return;
        }
        const c = res.data;
        $('#courseId').val(c.id);
        $('#courseModalTitle').html('<i class="bi bi-pencil-square me-2"></i>Edit Course');
        if (isSA) $('#f_branch').val(c.branch_id);
        $('#f_name').val(c.name);
        $('#f_code').val(c.code||'');
        $('#f_duration').val(c.duration||'');
        $('#f_duration_weeks').val(c.duration_weeks||'');
        $('#f_capacity').val(c.capacity||'');
        $('#f_status').val(c.status||'Active');
        $('#f_description').val(c.description||'');
        $('#f_currency').val(c.currency_code||defCurrency);
        $('#f_reg_fee').val(parseFloat(c.registration_fee||0).toFixed(2));
        $('#f_tui_fee').val(parseFloat(c.tuition_fee||0).toFixed(2));
        updateFeePreview();
        new bootstrap.Modal(document.getElementById('courseModal')).show();
    });
}

// ── Submit form ───────────────────────────────────────────────
$('#courseForm').on('submit', function(e) {
    e.preventDefault();
    const id  = $('#courseId').val();
    const btn = $('#saveCourseBtn').prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving…');

    const payload = {
        action:           id ? 'update' : 'create',
        id:               id || '',
        branch_id:        $('#f_branch').val() || myBranch,
        name:             $('#f_name').val(),
        code:             $('#f_code').val(),
        duration:         $('#f_duration').val(),
        duration_weeks:   $('#f_duration_weeks').val(),
        capacity:         $('#f_capacity').val(),
        status:           $('#f_status').val(),
        description:      $('#f_description').val(),
        currency_code:    $('#f_currency').val(),
        registration_fee: $('#f_reg_fee').val(),
        tuition_fee:      $('#f_tui_fee').val(),
    };

    $.post(API, payload, function(res) {
        const ok = res.success || res.status === 'success';
        if (ok) {
            bootstrap.Modal.getInstance(document.getElementById('courseModal'))?.hide();
            Swal.fire({icon:'success',title:id?'Updated!':'Created!',timer:1800,showConfirmButton:false});
            loadCourses(); loadKPIs();
        } else {
            Swal.fire('Error', res.message||'Save failed.', 'error');
        }
    }, 'json').fail(function(xhr) {
        let msg = 'Server error.';
        try { const p=JSON.parse(xhr.responseText||'{}'); if(p.message) msg=p.message; } catch(e) {}
        Swal.fire('Error', msg, 'error');
    }).always(() => btn.prop('disabled',false).html('<i class="bi bi-save-fill me-1"></i>Save Course'));
});

// ── Archive ───────────────────────────────────────────────────
function archiveCourse(id, name) {
    Swal.fire({
        title:'Archive Course?',
        html:`Archive <strong>"${esc(name)}"</strong>?<br><small>No new enrollments. Existing records kept.</small>`,
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#DC2626', confirmButtonText:'Archive'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(API, {action:'archive', id}, res => {
            const ok = res.success || res.status==='success';
            ok ? (Swal.fire({icon:'success',title:'Archived',timer:1600,showConfirmButton:false}), loadCourses(), loadKPIs())
               : Swal.fire('Error', res.message, 'error');
        }, 'json');
    });
}

// ── Restore ───────────────────────────────────────────────────
function restoreCourse(id) {
    $.post(API, {action:'restore', id}, res => {
        const ok = res.success || res.status==='success';
        ok ? (Swal.fire({icon:'success',title:'Restored',timer:1600,showConfirmButton:false}), loadCourses(), loadKPIs())
           : Swal.fire('Error', res.message, 'error');
    }, 'json');
}

// ── View details ──────────────────────────────────────────────
function viewCourse(id) {
    $.getJSON(API + '?action=get&id=' + id, function(res) {
        if (!(res.success||res.status==='success')) { Swal.fire('Error', res.message, 'error'); return; }
        const c = res.data;
        const currency = c.currency_code || defCurrency;
        const total = parseFloat(c.registration_fee||0)+parseFloat(c.tuition_fee||0);
        Swal.fire({
            title: esc(c.name),
            html: `
            <div style="text-align:left;font-family:var(--cr-font);font-size:.875rem">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin-bottom:12px">
                    ${c.code?`<div><b>Code</b><br><code>${esc(c.code)}</code></div>`:''}
                    <div><b>Branch</b><br>${esc(c.branch_name||'—')}</div>
                    <div><b>Duration</b><br>${esc(c.duration||'—')}</div>
                    <div><b>Status</b><br>${esc(c.status)}</div>
                    <div><b>Enrolled</b><br>${c.enrolled_count||0}${c.capacity?' / '+c.capacity:''}</div>
                    <div><b>Revenue</b><br>${fmt(c.total_revenue||0,currency)}</div>
                </div>
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;padding:12px">
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#059669;margin-bottom:8px">Fee Structure (${esc(currency)})</div>
                    <div style="display:flex;justify-content:space-between"><span>Registration</span><b>${fmt(c.registration_fee||0,currency)}</b></div>
                    <div style="display:flex;justify-content:space-between"><span>Tuition</span><b>${fmt(c.tuition_fee||0,currency)}</b></div>
                    <div style="display:flex;justify-content:space-between;border-top:1px solid #A7F3D0;margin-top:8px;padding-top:8px">
                        <b>Total</b><b style="color:#059669;font-size:1.1rem">${fmt(total,currency)}</b></div>
                </div>
                ${c.description?`<p style="margin-top:10px;color:#64748B">${esc(c.description)}</p>`:''}
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 480,
        });
    });
}

// ── Export ────────────────────────────────────────────────────
$('#exportBtn').on('click', function() {
    if (!currentData.length) { Swal.fire('No Data','Load courses first.','info'); return; }
    const rows = [['Course','Code','Branch','Duration','Reg Fee','Tuition Fee','Total Fee','Currency','Status','Enrolled']];
    currentData.forEach(c => {
        const total = parseFloat(c.registration_fee||0)+parseFloat(c.tuition_fee||0);
        rows.push([c.name,c.code||'',c.branch_name||'',c.duration||'',
            c.registration_fee||0,c.tuition_fee||0,total.toFixed(2),
            c.currency_code||defCurrency,c.status,c.enrolled_count||0]);
    });
    const csv = rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv,' + encodeURIComponent(csv);
    a.download = 'courses_export_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
});

// ── Filters ───────────────────────────────────────────────────
$('#applyFilter').on('click', () => { loadCourses(); });
$('#fSearch').on('keydown', e => { if(e.key==='Enter') loadCourses(); });
if (isSA) $('#fBranch').on('change', () => { loadCourses(); loadKPIs(); });

// ── Init ──────────────────────────────────────────────────────
$(function() {
    updateFeePreview();
    loadKPIs();
    loadCourses();
});
</script>
</body>
</html>