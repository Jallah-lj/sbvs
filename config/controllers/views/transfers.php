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

$allBranches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$sessionBranchName = '';
if ($isBranchAdmin && $sessionBranch) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$sessionBranch]);
    $sessionBranchName = $bStmt->fetchColumn() ?: '';
}

$pageTitle  = 'Inter-Branch Transfers';
$activePage = 'transfers.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ══════════════════════════════════════════════════════════
   INTER-BRANCH TRANSFERS — International Academic Standard
   Font: Outfit (display) + Nunito Sans (body)
   Palette: Teal-slate professional with warm amber accents
══════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Nunito+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
    --tr-teal:     #0D4F5C;
    --tr-teal-md:  #0E7490;
    --tr-teal-lt:  #ECFEFF;
    --tr-teal-bd:  #A5F3FC;
    --tr-amber:    #92400E;
    --tr-am-md:    #D97706;
    --tr-am-lt:    #FFFBEB;
    --tr-am-bd:    #FDE68A;
    --tr-green:    #065F46;
    --tr-green-md: #059669;
    --tr-green-lt: #ECFDF5;
    --tr-green-bd: #A7F3D0;
    --tr-red:      #991B1B;
    --tr-red-md:   #DC2626;
    --tr-red-lt:   #FEF2F2;
    --tr-red-bd:   #FECACA;
    --tr-blue:     #1E3A5F;
    --tr-blue-md:  #2563EB;
    --tr-blue-lt:  #EFF6FF;
    --tr-blue-bd:  #BFDBFE;
    --tr-violet:   #4C1D95;
    --tr-vi-md:    #7C3AED;
    --tr-vi-lt:    #F5F3FF;
    --tr-vi-bd:    #DDD6FE;
    --tr-slate:    #0F172A;
    --tr-muted:    #475569;
    --tr-subtle:   #94A3B8;
    --tr-surface:  #FFFFFF;
    --tr-page:     #F0F4F8;
    --tr-border:   #E2E8F0;
    --tr-border2:  #CBD5E1;
    --tr-shadow:   0 1px 3px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.07);
    --tr-shadow-md:0 4px 8px rgba(0,0,0,.06), 0 12px 36px rgba(0,0,0,.10);
    --tr-r:        10px;
    --tr-rlg:      16px;
    --tr-rxl:      20px;
    --tr-fd:       'Outfit', system-ui, sans-serif;
    --tr-fb:       'Nunito Sans', system-ui, sans-serif;
    --tr-fm:       'JetBrains Mono', monospace;
}

.tr-wrap, .tr-wrap * { font-family: var(--tr-fb); box-sizing: border-box; }
.tr-wrap h1,.tr-wrap h2,.tr-wrap h3,.tr-wrap h4,.tr-wrap h5 { font-family: var(--tr-fd); }

/* ── Page Header ── */
.tr-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
.tr-header h2 { font-size:1.55rem; font-weight:800; color:var(--tr-slate); letter-spacing:-.03em; margin:0 0 5px; font-family:var(--tr-fd); }
.tr-header p  { font-size:.875rem; color:var(--tr-muted); margin:0; }
.tr-branch-tag { display:inline-flex; align-items:center; gap:5px; background:var(--tr-teal-lt); color:var(--tr-teal-md); border:1px solid var(--tr-teal-bd); border-radius:20px; padding:3px 11px; font-size:.72rem; font-weight:700; letter-spacing:.3px; margin-top:6px; }

/* ── Buttons ── */
.tr-btn { display:inline-flex; align-items:center; gap:7px; height:40px; padding:0 18px; border:none; border-radius:var(--tr-r); font-family:var(--tr-fb); font-size:.855rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap; }
.tr-btn-teal { background:var(--tr-teal-md); color:#fff; box-shadow:0 2px 8px rgba(14,116,144,.3); }
.tr-btn-teal:hover { background:var(--tr-teal); color:#fff; box-shadow:0 4px 14px rgba(14,116,144,.35); }
.tr-btn-ghost { background:var(--tr-surface); color:var(--tr-muted); border:1.5px solid var(--tr-border2); }
.tr-btn-ghost:hover { background:var(--tr-page); color:var(--tr-slate); }
.tr-btn-sm { height:34px; padding:0 14px; font-size:.8rem; }
.tr-btn-green  { background:var(--tr-green-md); color:#fff; }
.tr-btn-green:hover  { background:var(--tr-green); color:#fff; }
.tr-btn-red    { background:var(--tr-red-md);   color:#fff; }
.tr-btn-red:hover    { background:var(--tr-red);   color:#fff; }
.tr-btn-amber  { background:var(--tr-am-md);    color:#fff; }
.tr-btn-amber:hover  { background:var(--tr-amber); color:#fff; }
.tr-btn-blue   { background:var(--tr-blue-md);  color:#fff; }
.tr-btn-blue:hover   { background:var(--tr-blue);  color:#fff; }
.tr-btn-violet { background:var(--tr-vi-md);    color:#fff; }
.tr-btn-violet:hover { background:var(--tr-violet); color:#fff; }

/* ── KPI Grid ── */
.tr-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.tr-kpi { background:var(--tr-surface); border:1px solid var(--tr-border); border-radius:var(--tr-rlg); padding:20px 20px 18px; box-shadow:var(--tr-shadow); transition:box-shadow .2s,transform .2s; position:relative; overflow:hidden; }
.tr-kpi:hover { box-shadow:var(--tr-shadow-md); transform:translateY(-1px); }
.tr-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--tr-rlg) var(--tr-rlg) 0 0; }
.tr-kpi.kp::before { background:var(--tr-am-md); }
.tr-kpi.kc::before { background:var(--tr-green-md); }
.tr-kpi.kr::before { background:var(--tr-red-md); }
.tr-kpi.kt::before { background:var(--tr-teal-md); }
.tr-kpi-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:12px; }
.kp .tr-kpi-icon { background:var(--tr-am-lt);    color:var(--tr-am-md); }
.kc .tr-kpi-icon { background:var(--tr-green-lt); color:var(--tr-green-md); }
.kr .tr-kpi-icon { background:var(--tr-red-lt);   color:var(--tr-red-md); }
.kt .tr-kpi-icon { background:var(--tr-teal-lt);  color:var(--tr-teal-md); }
.tr-kpi-val { font-family:var(--tr-fd); font-size:1.6rem; font-weight:800; color:var(--tr-slate); letter-spacing:-.02em; line-height:1; margin-bottom:4px; }
.kp .tr-kpi-val { color:var(--tr-am-md); }
.kc .tr-kpi-val { color:var(--tr-green-md); }
.kr .tr-kpi-val { color:var(--tr-red-md); }
.kt .tr-kpi-val { color:var(--tr-teal-md); }
.tr-kpi-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--tr-subtle); }

/* ── Transfer Pipeline ── */
.tr-pipeline { background:var(--tr-surface); border:1px solid var(--tr-border); border-radius:var(--tr-rlg); padding:18px 24px; box-shadow:var(--tr-shadow); margin-bottom:24px; }
.tr-pipeline-title { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--tr-muted); margin-bottom:14px; display:flex; align-items:center; gap:7px; }
.tr-pipeline-title::after { content:''; flex:1; height:1px; background:var(--tr-border); }
.tr-pipe-steps { display:flex; align-items:center; gap:0; overflow-x:auto; }
.tr-pipe-step { display:flex; align-items:center; gap:8px; flex:1; min-width:110px; }
.tr-pipe-step:not(:last-child)::after { content:'→'; color:var(--tr-border2); font-size:.9rem; margin:0 4px; flex-shrink:0; }
.tr-pipe-dot { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; flex-shrink:0; font-family:var(--tr-fd); transition:all .2s; }
.tr-pipe-step.ps-done .tr-pipe-dot  { background:var(--tr-green-lt); color:var(--tr-green-md); border:2px solid var(--tr-green-bd); }
.tr-pipe-step.ps-curr .tr-pipe-dot  { background:var(--tr-teal-md); color:#fff; box-shadow:0 0 0 4px var(--tr-teal-lt); }
.tr-pipe-step.ps-pend .tr-pipe-dot  { background:var(--tr-page); color:var(--tr-subtle); border:2px solid var(--tr-border2); }
.tr-pipe-label { font-size:.73rem; font-weight:700; color:var(--tr-slate); }
.tr-pipe-step.ps-pend .tr-pipe-label { color:var(--tr-subtle); }
.tr-pipe-sub { font-size:.67rem; color:var(--tr-muted); margin-top:1px; }

/* ── Filter + Table Card ── */
.tr-card { background:var(--tr-surface); border:1px solid var(--tr-border); border-radius:var(--tr-rlg); box-shadow:var(--tr-shadow); overflow:hidden; }
.tr-card-head { padding:16px 22px; border-bottom:1px solid var(--tr-border); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.tr-card-head h5 { font-family:var(--tr-fd); font-size:.95rem; font-weight:700; color:var(--tr-slate); margin:0; display:flex; align-items:center; gap:8px; }
.tr-card-head h5 i { color:var(--tr-teal-md); }

/* ── Filter bar ── */
.tr-filter-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.tr-filter-input, .tr-filter-select { height:34px; padding:0 11px; border:1.5px solid var(--tr-border2); border-radius:8px; font-family:var(--tr-fb); font-size:.83rem; color:var(--tr-slate); background:#fff; outline:none; transition:border-color .15s; }
.tr-filter-input:focus, .tr-filter-select:focus { border-color:var(--tr-teal-md); box-shadow:0 0 0 3px rgba(14,116,144,.1); }
.tr-filter-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; padding-right:26px; cursor:pointer; }
.tr-filter-btn { height:34px; padding:0 14px; background:var(--tr-teal-md); color:#fff; border:none; border-radius:8px; font-family:var(--tr-fb); font-size:.83rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; }
.tr-filter-btn:hover { background:var(--tr-teal); }

/* ── Table ── */
.tr-table thead th { background:var(--tr-teal); color:rgba(255,255,255,.8); font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; padding:12px 14px; border:none; white-space:nowrap; }
.tr-table tbody td { padding:13px 14px; font-size:.855rem; border-bottom:1px solid var(--tr-border); vertical-align:middle; color:var(--tr-slate); }
.tr-table tbody tr:last-child td { border-bottom:none; }
.tr-table tbody tr:hover td { background:var(--tr-teal-lt); }

/* DataTables cosmetics */
.dataTables_wrapper .dataTables_filter input { border:1.5px solid var(--tr-border2)!important; border-radius:8px!important; height:34px!important; font-family:var(--tr-fb)!important; font-size:.83rem!important; box-shadow:none!important; padding:0 10px!important; outline:none!important; }
.dataTables_wrapper .dataTables_filter input:focus { border-color:var(--tr-teal-md)!important; }
.dataTables_wrapper .dataTables_length select { border:1.5px solid var(--tr-border2)!important; border-radius:8px!important; height:34px!important; font-family:var(--tr-fb)!important; }
.dataTables_wrapper .dataTables_info,.dataTables_wrapper .dataTables_paginate { font-size:.82rem; color:var(--tr-muted); }
.dataTables_wrapper .paginate_button { border-radius:7px!important; }
.dataTables_wrapper .paginate_button.current,.dataTables_wrapper .paginate_button.current:hover { background:var(--tr-teal-md)!important; border-color:var(--tr-teal-md)!important; color:#fff!important; }

/* ── Badges ── */
.tr-badge { display:inline-flex; align-items:center; gap:4px; border-radius:20px; padding:3px 10px; font-size:.7rem; font-weight:700; letter-spacing:.3px; white-space:nowrap; font-family:var(--tr-fb); }
.tb-pending  { background:var(--tr-am-lt);    color:var(--tr-am-md); }
.tb-complete { background:var(--tr-green-lt); color:var(--tr-green-md); }
.tb-rejected { background:var(--tr-red-lt);   color:var(--tr-red-md); }
.tb-hold     { background:var(--tr-blue-lt);  color:var(--tr-blue-md); }
.tb-cond     { background:var(--tr-vi-lt);    color:var(--tr-vi-md); }
.tb-default  { background:var(--tr-page);     color:var(--tr-muted); border:1px solid var(--tr-border2); }
.tr-status-dot { width:6px; height:6px; border-radius:50%; display:inline-block; flex-shrink:0; }
.sd-pend  { background:var(--tr-am-md);    box-shadow:0 0 0 3px var(--tr-am-lt); }
.sd-done  { background:var(--tr-green-md); box-shadow:0 0 0 3px var(--tr-green-lt); }
.sd-reject{ background:var(--tr-red-md);   box-shadow:0 0 0 3px var(--tr-red-lt); }
.sd-hold  { background:var(--tr-blue-md);  box-shadow:0 0 0 3px var(--tr-blue-lt); }

/* ── Transfer ID ── */
.tr-id { font-family:var(--tr-fm); font-size:.8rem; font-weight:600; color:var(--tr-teal-md); background:var(--tr-teal-lt); border-radius:5px; padding:2px 8px; border:1px solid var(--tr-teal-bd); }

/* ── Branch arrow cell ── */
.tr-route { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.tr-branch-from { display:inline-flex; align-items:center; gap:4px; background:var(--tr-red-lt); color:var(--tr-red-md); border-radius:6px; padding:3px 9px; font-size:.75rem; font-weight:700; }
.tr-branch-to   { display:inline-flex; align-items:center; gap:4px; background:var(--tr-green-lt); color:var(--tr-green-md); border-radius:6px; padding:3px 9px; font-size:.75rem; font-weight:700; }
.tr-arrow       { color:var(--tr-subtle); font-size:.85rem; }

/* ── Student cell ── */
.tr-student-name { font-weight:700; color:var(--tr-slate); font-size:.875rem; }
.tr-student-code { font-family:var(--tr-fm); font-size:.72rem; color:var(--tr-muted); margin-top:1px; }
.tr-avatar { width:34px; height:34px; border-radius:50%; background:var(--tr-teal-lt); color:var(--tr-teal-md); font-family:var(--tr-fd); font-size:.72rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* ── Action button ── */
.tr-action-btn { display:inline-flex; align-items:center; gap:5px; height:30px; padding:0 12px; border:none; border-radius:7px; font-family:var(--tr-fb); font-size:.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all .15s; }
.tab-review { background:var(--tr-teal-lt); color:var(--tr-teal-md); }
.tab-review:hover { background:var(--tr-teal-md); color:#fff; }

/* ── Modals ── */
.tr-modal .modal-content { border:none; border-radius:20px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.18); font-family:var(--tr-fb); }
.tr-modal .modal-header { padding:20px 26px 16px; border-bottom:1px solid var(--tr-border); }
.tr-modal .modal-header.teal { background:var(--tr-teal-md); color:#fff; border-bottom:none; }
.tr-modal .modal-header.teal .btn-close { filter:invert(1); }
.tr-modal .modal-header h5 { font-family:var(--tr-fd); font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
.tr-modal .modal-body { background:var(--tr-page); padding:22px 26px; }
.tr-modal .modal-footer { background:var(--tr-surface); border-top:1px solid var(--tr-border); padding:14px 26px; }

/* ── Modal form ── */
.tr-field label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--tr-muted); display:block; margin-bottom:5px; }
.tr-field label span { color:var(--tr-red-md); }
.tr-input,.tr-select,.tr-textarea { width:100%; height:40px; padding:0 12px; border:1.5px solid var(--tr-border2); border-radius:8px; font-family:var(--tr-fb); font-size:.875rem; color:var(--tr-slate); background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
.tr-input:focus,.tr-select:focus,.tr-textarea:focus { border-color:var(--tr-teal-md); box-shadow:0 0 0 3px rgba(14,116,144,.1); }
.tr-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; cursor:pointer; }
.tr-textarea { height:auto; padding:10px 12px; resize:vertical; }
.tr-input-group { display:flex; align-items:center; border:1.5px solid var(--tr-border2); border-radius:8px; overflow:hidden; background:#fff; transition:border-color .15s,box-shadow .15s; }
.tr-input-group:focus-within { border-color:var(--tr-teal-md); box-shadow:0 0 0 3px rgba(14,116,144,.1); }
.tr-input-pfx { padding:0 12px; height:40px; display:flex; align-items:center; font-size:.84rem; font-weight:600; color:var(--tr-muted); border-right:1.5px solid var(--tr-border); background:var(--tr-page); flex-shrink:0; font-family:var(--tr-fm); }
.tr-input-group .tr-input { border:none; box-shadow:none; border-radius:0; padding-left:10px; }

/* ── Section label ── */
.tr-section-label { font-size:.67rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--tr-muted); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.tr-section-label::after { content:''; flex:1; height:1px; background:var(--tr-border); }

/* ── Step wizard (inside modal) ── */
.tr-wizard { display:flex; gap:0; margin-bottom:20px; }
.tr-wiz-step { display:flex; align-items:center; gap:8px; flex:1; }
.tr-wiz-step:not(:last-child)::after { content:''; flex:1; height:2px; background:var(--tr-border2); margin:0 8px; }
.tr-wiz-step.ws-done::after { background:var(--tr-teal-md); }
.tr-wiz-num { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; flex-shrink:0; font-family:var(--tr-fd); }
.ws-done .tr-wiz-num  { background:var(--tr-teal-md); color:#fff; }
.ws-curr .tr-wiz-num  { background:var(--tr-teal-md); color:#fff; box-shadow:0 0 0 4px var(--tr-teal-lt); }
.ws-pend .tr-wiz-num  { background:var(--tr-page); color:var(--tr-subtle); border:2px solid var(--tr-border2); }
.tr-wiz-lbl { font-size:.72rem; font-weight:700; color:var(--tr-slate); white-space:nowrap; }
.ws-pend .tr-wiz-lbl  { color:var(--tr-subtle); }

/* ── Fee breakdown card ── */
.tr-fee-card { background:var(--tr-surface); border:1.5px solid var(--tr-border2); border-radius:10px; overflow:hidden; }
.tr-fee-card-head { background:var(--tr-teal-lt); padding:10px 16px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--tr-teal-md); border-bottom:1px solid var(--tr-teal-bd); display:flex; align-items:center; gap:6px; }
.tr-fee-rows { padding:6px 0; }
.tr-fee-row { display:flex; justify-content:space-between; align-items:center; padding:8px 16px; font-size:.84rem; }
.tr-fee-row.divider { border-top:1px solid var(--tr-border); margin-top:4px; padding-top:12px; }
.tr-fee-row .lbl { color:var(--tr-muted); }
.tr-fee-row .val { font-weight:700; color:var(--tr-slate); font-family:var(--tr-fm); }
.tr-fee-row.total .val { font-size:1.05rem; color:var(--tr-teal-md); }
.tr-fee-row.outstanding .val { color:var(--tr-red-md); }
.tr-fee-row.paid .val { color:var(--tr-green-md); }

/* ── Warning box ── */
.tr-warn { display:flex; gap:10px; align-items:flex-start; background:var(--tr-am-lt); border:1.5px solid var(--tr-am-bd); border-radius:10px; padding:14px 16px; margin-bottom:14px; }
.tr-warn i { color:var(--tr-am-md); font-size:1rem; flex-shrink:0; margin-top:2px; }
.tr-warn p { font-size:.855rem; color:var(--tr-amber); margin:0; line-height:1.5; }
.tr-info { display:flex; gap:10px; align-items:flex-start; background:var(--tr-teal-lt); border:1.5px solid var(--tr-teal-bd); border-radius:10px; padding:14px 16px; margin-bottom:14px; }
.tr-info i { color:var(--tr-teal-md); font-size:1rem; flex-shrink:0; margin-top:2px; }
.tr-info p { font-size:.855rem; color:var(--tr-teal); margin:0; line-height:1.5; }
.tr-same-warn { display:none; }
.tr-same-warn.show { display:flex; }

/* ── Responsive ── */
@media(max-width:1024px){ .tr-kpi-grid{grid-template-columns:repeat(2,1fr);} }
@media(max-width:640px){  .tr-kpi-grid{grid-template-columns:1fr 1fr;} .tr-pipe-steps{flex-wrap:wrap;gap:10px;} }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main tr-wrap">

    <!-- ── Page Header ──────────────────────────────────── -->
    <div class="tr-header fade-in">
        <div>
            <h2><i class="bi bi-arrow-left-right me-2" style="color:var(--tr-teal-md);font-size:1.2rem;vertical-align:middle;"></i>Inter-Branch Transfers</h2>
            <p><?= $isSuperAdmin ? 'Global view of all inter-branch student transfer requests — review, approve, and manage.' : 'Submit and track student transfer requests for your branch.' ?></p>
            <?php if ($isBranchAdmin && $sessionBranchName): ?>
            <span class="tr-branch-tag"><i class="bi bi-building-fill"></i><?= htmlspecialchars($sessionBranchName) ?></span>
            <?php endif; ?>
        </div>
        <button class="tr-btn tr-btn-teal" data-bs-toggle="modal" data-bs-target="#newTransferModal">
            <i class="bi bi-plus-circle-fill"></i> New Transfer Request
        </button>
    </div>

    <!-- ── KPI Cards ────────────────────────────────────── -->
    <div class="tr-kpi-grid">
        <div class="tr-kpi kp fade-in" style="animation-delay:.05s">
            <div class="tr-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="tr-kpi-val" id="statPending">—</div>
            <div class="tr-kpi-lbl">Pending Approval</div>
        </div>
        <div class="tr-kpi kc fade-in" style="animation-delay:.1s">
            <div class="tr-kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div class="tr-kpi-val" id="statComplete">—</div>
            <div class="tr-kpi-lbl">Completed</div>
        </div>
        <div class="tr-kpi kr fade-in" style="animation-delay:.15s">
            <div class="tr-kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
            <div class="tr-kpi-val" id="statRejected">—</div>
            <div class="tr-kpi-lbl">Rejected</div>
        </div>
        <div class="tr-kpi kt fade-in" style="animation-delay:.2s">
            <div class="tr-kpi-icon"><i class="bi bi-list-check"></i></div>
            <div class="tr-kpi-val" id="statTotal">—</div>
            <div class="tr-kpi-lbl">Total Transfers</div>
        </div>
    </div>

    <!-- ── International Transfer Pipeline ─────────────── -->
    <div class="tr-pipeline fade-in" style="animation-delay:.25s">
        <div class="tr-pipeline-title"><i class="bi bi-diagram-3-fill"></i> Standard Inter-Branch Transfer Workflow</div>
        <div class="tr-pipe-steps">
            <div class="tr-pipe-step ps-done">
                <div class="tr-pipe-dot"><i class="bi bi-check-lg"></i></div>
                <div><div class="tr-pipe-label">1. Request Filed</div><div class="tr-pipe-sub">Origin branch submits</div></div>
            </div>
            <div class="tr-pipe-step ps-done">
                <div class="tr-pipe-dot"><i class="bi bi-check-lg"></i></div>
                <div><div class="tr-pipe-label">2. Eligibility Check</div><div class="tr-pipe-sub">Academic + financial review</div></div>
            </div>
            <div class="tr-pipe-step ps-curr">
                <div class="tr-pipe-dot">3</div>
                <div><div class="tr-pipe-label">3. Origin Approval</div><div class="tr-pipe-sub">Origin branch approves</div></div>
            </div>
            <div class="tr-pipe-step ps-pend">
                <div class="tr-pipe-dot">4</div>
                <div><div class="tr-pipe-label">4. Dest. Acceptance</div><div class="tr-pipe-sub">Destination confirms</div></div>
            </div>
            <div class="tr-pipe-step ps-pend">
                <div class="tr-pipe-dot">5</div>
                <div><div class="tr-pipe-label">5. Fee Settlement</div><div class="tr-pipe-sub">Balance transferred</div></div>
            </div>
            <div class="tr-pipe-step ps-pend">
                <div class="tr-pipe-dot">6</div>
                <div><div class="tr-pipe-label">6. Records Migrated</div><div class="tr-pipe-sub">Enrollment updated</div></div>
            </div>
            <div class="tr-pipe-step ps-pend">
                <div class="tr-pipe-dot">7</div>
                <div><div class="tr-pipe-label">7. Complete</div><div class="tr-pipe-sub">Student enrolled</div></div>
            </div>
        </div>
    </div>

    <!-- ── Transfer Queue ───────────────────────────────── -->
    <div class="tr-card fade-in" style="animation-delay:.3s">
        <div class="tr-card-head">
            <h5><i class="bi bi-table"></i> Transfer Queue</h5>
            <div class="tr-filter-bar">
                <select id="fStatus" class="tr-filter-select" style="width:150px">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Complete">Completed</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Hold">On Hold</option>
                </select>
                <?php if ($isSuperAdmin): ?>
                <select id="fBranch" class="tr-filter-select" style="width:155px">
                    <option value="">All Branches</option>
                    <?php foreach ($allBranches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <input type="date" id="fDateFrom" class="tr-filter-input" style="width:130px">
                <input type="date" id="fDateTo"   class="tr-filter-input" style="width:130px">
                <button class="tr-filter-btn" id="applyFilter">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table tr-table align-middle w-100 mb-0" id="transfersTable">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>Student</th>
                        <th>Route</th>
                        <th>Course</th>
                        <th>Fee Status</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</main>
</div>


<!-- ══════════════ NEW TRANSFER MODAL ══════════════════════ -->
<div class="modal fade tr-modal" id="newTransferModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="newTransferForm" class="modal-content">
            <div class="modal-header teal">
                <h5><i class="bi bi-arrow-left-right"></i> New Transfer Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Step wizard -->
                <div class="tr-wizard mb-4">
                    <div class="tr-wiz-step ws-curr" id="wiz1">
                        <div class="tr-wiz-num">1</div>
                        <span class="tr-wiz-lbl">Student</span>
                    </div>
                    <div class="tr-wiz-step ws-pend" id="wiz2">
                        <div class="tr-wiz-num">2</div>
                        <span class="tr-wiz-lbl">Destination</span>
                    </div>
                    <div class="tr-wiz-step ws-pend" id="wiz3">
                        <div class="tr-wiz-num">3</div>
                        <span class="tr-wiz-lbl">Fee Review</span>
                    </div>
                    <div class="tr-wiz-step ws-pend" id="wiz4">
                        <div class="tr-wiz-num">4</div>
                        <span class="tr-wiz-lbl">Reason</span>
                    </div>
                </div>

                <!-- Section 1: Origin & Student -->
                <div class="tr-section-label"><i class="bi bi-person-fill"></i> Student Details</div>
                <div class="row g-3 mb-4">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Origin Branch <span>*</span></label>
                            <select name="origin_branch_id" id="originBranchSel" class="tr-select" required>
                                <option value="">— Select origin branch —</option>
                                <?php foreach ($allBranches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="origin_branch_id" value="<?= $sessionBranch ?>">
                    <?php endif; ?>

                    <div class="col-md-<?= $isSuperAdmin ? '6' : '12' ?>">
                        <div class="tr-field">
                            <label>Student <span>*</span></label>
                            <select name="student_id" id="studentSel" class="tr-select" required
                                    onchange="onStudentChange()">
                                <option value=""><?= $isSuperAdmin ? '— Select origin branch first —' : '— Select student —' ?></option>
                            </select>
                            <div style="font-size:.75rem;color:var(--tr-muted);margin-top:4px" id="studentLoadNote"></div>
                        </div>
                    </div>
                </div>

                <!-- Student info card (shown after selection) -->
                <div id="studentInfoCard" style="display:none;margin-bottom:16px;">
                    <div class="tr-fee-card">
                        <div class="tr-fee-card-head"><i class="bi bi-person-badge-fill"></i> Current Enrollment &amp; Financial Status</div>
                        <div class="tr-fee-rows">
                            <div class="tr-fee-row">
                                <span class="lbl">Student ID</span>
                                <span class="val" id="si_code">—</span>
                            </div>
                            <div class="tr-fee-row">
                                <span class="lbl">Enrolled Course</span>
                                <span class="val" id="si_course">—</span>
                            </div>
                            <div class="tr-fee-row">
                                <span class="lbl">Course Fee</span>
                                <span class="val" id="si_fee">—</span>
                            </div>
                            <div class="tr-fee-row paid">
                                <span class="lbl">Amount Paid</span>
                                <span class="val" id="si_paid">—</span>
                            </div>
                            <div class="tr-fee-row outstanding divider">
                                <span class="lbl" style="font-weight:700;color:var(--tr-red-md)">Outstanding Balance</span>
                                <span class="val" id="si_balance">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Destination -->
                <div class="tr-section-label"><i class="bi bi-building-fill"></i> Destination Branch</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Destination Branch <span>*</span></label>
                            <select name="destination_branch_id" id="destBranchSel" class="tr-select" required
                                    onchange="onDestChange()">
                                <option value="">— Select destination —</option>
                                <?php foreach ($allBranches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Preferred Transfer Date</label>
                            <input type="date" name="transfer_date" id="transferDate" class="tr-input"
                                   value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="tr-warn tr-same-warn" id="sameBranchWarn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <p>Origin and destination branches cannot be the same. Please select a different destination.</p>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Fee & Payment Handling -->
                <div class="tr-section-label"><i class="bi bi-cash-stack"></i> Fee Settlement &amp; Payment Transfer</div>
                <div class="tr-info mb-3">
                    <i class="bi bi-info-circle-fill"></i>
                    <p>The outstanding balance from the origin branch will be migrated to the destination branch. You can choose how unpaid fees are handled during the transfer.</p>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Fee Transfer Policy <span>*</span></label>
                            <select name="fee_policy" id="feePolicySel" class="tr-select" required>
                                <option value="migrate">Migrate full balance to destination</option>
                                <option value="clear_before">Student must clear balance before transfer</option>
                                <option value="partial_credit">Apply partial credit to new enrollment</option>
                                <option value="waive">Waive outstanding (requires SA approval)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Transfer Fee (Admin Charge)</label>
                            <div class="tr-input-group">
                                <span class="tr-input-pfx">$</span>
                                <input type="number" name="transfer_fee" id="transferFee" class="tr-input"
                                       value="0.00" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="col-12" id="clearBeforeWarn" style="display:none;">
                        <div class="tr-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <p>Student must settle the outstanding balance at the origin branch before the transfer can be processed.</p>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Documents & Reason -->
                <div class="tr-section-label"><i class="bi bi-file-earmark-text-fill"></i> Transfer Justification</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Transfer Category <span>*</span></label>
                            <select name="transfer_category" class="tr-select" required>
                                <option value="">— Select category —</option>
                                <option value="Relocation">Family / Personal Relocation</option>
                                <option value="Academic">Academic Advancement</option>
                                <option value="Financial">Financial Reasons</option>
                                <option value="Convenience">Geographic Convenience</option>
                                <option value="Branch_Closure">Branch Restructuring</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tr-field">
                            <label>Priority Level</label>
                            <select name="priority" class="tr-select">
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="tr-field">
                            <label>Reason for Transfer <span>*</span></label>
                            <textarea name="reason" class="tr-textarea" rows="3"
                                      placeholder="Provide a clear justification for this transfer request. Include any relevant academic, financial, or personal circumstances…"
                                      required></textarea>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="tr-field">
                            <label>Supporting Documents <span style="color:var(--tr-muted);font-weight:400;text-transform:none">(optional)</span></label>
                            <input type="text" name="documents" class="tr-input"
                                   placeholder="Reference any attached documents (e.g. relocation letter, medical note)">
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="tr-btn tr-btn-ghost tr-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="tr-btn tr-btn-teal tr-btn-sm" id="submitTransferBtn">
                    <i class="bi bi-send-fill"></i> Submit Transfer Request
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════ TRANSFER DETAIL DRAWER MODAL ════════════ -->
<div class="modal fade tr-modal" id="transferDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header teal">
                <h5><i class="bi bi-file-earmark-text-fill"></i> Transfer Details — <span id="detailTransferId" style="font-family:var(--tr-fm);font-size:.9rem;opacity:.85"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody" style="min-height:300px;">
                <div class="text-center" style="padding:40px">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer" id="detailActions">
                <button type="button" class="tr-btn tr-btn-ghost tr-btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
/* ═══════════════════════════════════════════════════════════════
   INTER-BRANCH TRANSFERS — Full JS
   Includes: submit, listing, filtering, detail modal with
   financial breakdown, approval workflow, fee settlement
═══════════════════════════════════════════════════════════════ */
const API       = 'models/api/transfer_api.php';
const STU_API   = 'models/api/student_api.php';
const isSA      = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranch  = <?= $sessionBranch ?>;

const fmt  = v => '$' + parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const esc  = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const inits= n => String(n||'').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
const fmtDate = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}) : '—';

let dtTable;

// ── Status badge ─────────────────────────────────────────────
function statusBadge(s) {
    if (!s) return '<span class="tr-badge tb-default">—</span>';
    const sl = String(s).toLowerCase();
    let cls = 'tb-default', dot = '';
    if (sl.includes('pending') || sl.includes('origin') || sl.includes('dest'))
        { cls='tb-pending'; dot='<span class="tr-status-dot sd-pend"></span>'; }
    else if (sl.includes('complete'))
        { cls='tb-complete'; dot='<span class="tr-status-dot sd-done"></span>'; }
    else if (sl.includes('reject'))
        { cls='tb-rejected'; dot='<span class="tr-status-dot sd-reject"></span>'; }
    else if (sl.includes('hold'))
        { cls='tb-hold'; dot='<span class="tr-status-dot sd-hold"></span>'; }
    else if (sl.includes('cond'))
        { cls='tb-cond'; dot=''; }
    return `<span class="tr-badge ${cls}">${dot} ${esc(s)}</span>`;
}

// ── Fee status badge ─────────────────────────────────────────
function feeStatusBadge(balance, policy) {
    const b = parseFloat(balance||0);
    if (b <= 0) return '<span class="tr-badge tb-complete"><i class="bi bi-check-circle-fill"></i> Cleared</span>';
    const policyMap = {
        'migrate':       '<span class="tr-badge tb-pending">Migrating</span>',
        'clear_before':  '<span class="tr-badge tb-rejected">Must Clear</span>',
        'partial_credit':'<span class="tr-badge tb-hold">Partial Credit</span>',
        'waive':         '<span class="tr-badge tb-cond">Waived</span>',
    };
    return policyMap[policy] || `<span class="tr-badge tb-pending">${fmt(b)} due</span>`;
}

// ── Load table ────────────────────────────────────────────────
function loadTable() {
    const params = {
        status:    $('#fStatus').val()   || '',
        branch_id: $('#fBranch').val()   || '',
        date_from: $('#fDateFrom').val() || '',
        date_to:   $('#fDateTo').val()   || '',
    };

    if (dtTable) dtTable.destroy();

    dtTable = $('#transfersTable').DataTable({
        processing: true,
        ajax: {
            url: API + '?action=list&'
                + 'status='    + encodeURIComponent(params.status||'')
                + '&branch_id='+ encodeURIComponent(params.branch_id||'')
                + '&date_from='+ encodeURIComponent(params.date_from||'')
                + '&date_to='  + encodeURIComponent(params.date_to||''),
            dataSrc: function(res) {
                // Accept both res.success and res.status === 'success'
                const rows = (res.success || res.status === 'success') ? (res.data || []) : [];
                $('#statPending').text(rows.filter(r => /pending|hold/i.test(r.status)).length);
                $('#statComplete').text(rows.filter(r => /complete/i.test(r.status)).length);
                $('#statRejected').text(rows.filter(r => /reject/i.test(r.status)).length);
                $('#statTotal').text(rows.length);
                return rows;
            }
        },
        columns: [
            {
                data: 'transfer_id',
                render: d => `<span class="tr-id">${esc(d)}</span>`
            },
            {
                data: null,
                render: r => `<div style="display:flex;align-items:center;gap:8px">
                    <div class="tr-avatar">${inits(r.student_name)}</div>
                    <div>
                        <div class="tr-student-name">${esc(r.student_name)}</div>
                        <div class="tr-student-code">${esc(r.student_code)}</div>
                    </div>
                </div>`
            },
            {
                data: null,
                render: r => `<div class="tr-route">
                    <span class="tr-branch-from"><i class="bi bi-box-arrow-up-right"></i>${esc(r.origin_branch)}</span>
                    <span class="tr-arrow">→</span>
                    <span class="tr-branch-to"><i class="bi bi-box-arrow-in-right"></i>${esc(r.destination_branch)}</span>
                </div>`
            },
            {
                data: 'course_name',
                render: d => d ? `<span style="font-size:.82rem;color:var(--tr-muted)">${esc(d)}</span>` : '<span style="color:var(--tr-subtle);font-size:.8rem">—</span>'
            },
            {
                data: null,
                render: r => feeStatusBadge(r.outstanding_balance, r.fee_policy)
            },
            {
                data: 'created_at',
                render: d => `<span style="font-size:.8rem;color:var(--tr-muted)">${fmtDate(d)}</span>`
            },
            {
                data: 'status',
                render: statusBadge
            },
            {
                data: 'id',
                orderable: false,
                className: 'text-center',
                render: id => `<button class="tr-action-btn tab-review" onclick="openDetail(${id})">
                    <i class="bi bi-eye-fill"></i> Review
                </button>`
            }
        ],
        order: [[5, 'desc']],
        responsive: true,
        language: {
            emptyTable: '<div style="text-align:center;padding:40px;color:var(--tr-muted)"><i class="bi bi-arrow-left-right" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.25"></i>No transfer requests found.</div>',
            processing: '<div style="padding:20px"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading transfers…</div>'
        }
    });
}

$('#applyFilter').on('click', loadTable);

// ── Students loader ───────────────────────────────────────────
function loadStudents(branchId) {
    const sel = $('#studentSel').empty().append('<option value="">Loading…</option>');
    $('#studentLoadNote').text('');
    $('#studentInfoCard').hide();
    const url = STU_API + '?action=list_simple' + (branchId ? '&branch_id=' + branchId : '');
    $.getJSON(url, function(res) {
        sel.empty().append('<option value="">— Select student —</option>');
        const students = res.data || [];
        if (!students.length) {
            sel.append('<option value="" disabled>No active students found</option>');
            $('#studentLoadNote').text('No enrolled students found for this branch.');
        } else {
            students.forEach(s => {
                sel.append(`<option value="${s.id}" data-code="${esc(s.student_id||'')}" data-course="${esc(s.course_name||'')}" data-fee="${s.course_fee||0}" data-paid="${s.total_paid||0}" data-balance="${s.balance||0}">${esc(s.name)} (${esc(s.student_id||'')})</option>`);
            });
        }
    });
}

function onStudentChange() {
    const sel = $('#studentSel option:selected');
    if (!sel.val()) { $('#studentInfoCard').hide(); updateWizard(1); return; }
    const balance = parseFloat(sel.data('balance')||0);
    $('#si_code').text(sel.data('code') || '—');
    $('#si_course').text(sel.data('course') || '—');
    $('#si_fee').text(fmt(sel.data('fee')||0));
    $('#si_paid').text(fmt(sel.data('paid')||0));
    $('#si_balance').text(balance > 0 ? fmt(balance) : 'Cleared ✓');
    $('#studentInfoCard').show();
    updateWizard(2);
}

function onDestChange() {
    const origin = isSA ? parseInt($('#originBranchSel').val()||0) : myBranch;
    const dest   = parseInt($('#destBranchSel').val()||0);
    if (origin && dest && origin === dest) {
        $('#sameBranchWarn').addClass('show');
        $('#submitTransferBtn').prop('disabled', true);
    } else {
        $('#sameBranchWarn').removeClass('show');
        $('#submitTransferBtn').prop('disabled', false);
    }
    if (dest) updateWizard(3);
}

function updateWizard(active) {
    for (let i = 1; i <= 4; i++) {
        const el = $(`#wiz${i}`);
        el.removeClass('ws-done ws-curr ws-pend');
        if (i < active)      el.addClass('ws-done');
        else if (i === active) el.addClass('ws-curr');
        else                   el.addClass('ws-pend');
    }
}

$('#feePolicySel').on('change', function() {
    $('#clearBeforeWarn').toggle($(this).val() === 'clear_before');
    if ($(this).val()) updateWizard(4);
});

// ── Submit transfer ───────────────────────────────────────────
$('#newTransferForm').on('submit', function(e) {
    e.preventDefault();
    const origin = parseInt($('[name="origin_branch_id"]').first().val()||0);
    const dest   = parseInt($('#destBranchSel').val()||0);
    if (origin && dest && origin === dest) {
        Swal.fire('Validation Error','Origin and destination cannot be the same.','warning'); return;
    }
    if (!$('#studentSel').val()) {
        Swal.fire('Required','Please select a student.','warning'); return;
    }

    const btn = $('#submitTransferBtn');
    btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting…');

    $.ajax({
        url: API + '?action=create',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Transfer Request Submitted',
                    html: `Transfer ID: <strong style="font-family:var(--tr-fm);color:var(--tr-teal-md)">${esc(res.transfer_id_str)}</strong><br>
                           <span style="font-size:.875rem;color:var(--tr-muted)">The request is now pending origin branch approval.</span>`,
                    confirmButtonText: 'View Details',
                    showCancelButton: true,
                    cancelButtonText: 'Back to Queue'
                }).then(r => {
                    if (r.isConfirmed && res.id) {
                        bootstrap.Modal.getInstance(document.getElementById('newTransferModal'))?.hide();
                        setTimeout(() => openDetail(res.id), 300);
                    } else {
                        bootstrap.Modal.getInstance(document.getElementById('newTransferModal'))?.hide();
                        loadTable();
                    }
                });
            } else {
                Swal.fire('Error', res.message || 'Could not submit transfer.', 'error');
            }
        },
        error: () => Swal.fire('Error','Server error. Please try again.','error'),
        complete: () => btn.prop('disabled',false).html('<i class="bi bi-send-fill me-1"></i>Submit Transfer Request')
    });
});

$('#newTransferModal').on('hidden.bs.modal', function() {
    $('#newTransferForm')[0].reset();
    $('#sameBranchWarn').removeClass('show');
    $('#studentInfoCard').hide();
    $('#clearBeforeWarn').hide();
    updateWizard(1);
    if (isSA) {
        $('#studentSel').empty().append('<option value="">— Select origin branch first —</option>');
    } else {
        loadStudents(myBranch);
    }
});

// ── Transfer detail modal ─────────────────────────────────────
function openDetail(id) {
    $('#detailBody').html('<div style="text-align:center;padding:40px"><div class="spinner-border text-primary"></div><div style="font-size:.84rem;color:var(--tr-muted);margin-top:10px">Loading transfer details…</div></div>');
    $('#detailActions').html('<button type="button" class="tr-btn tr-btn-ghost tr-btn-sm" data-bs-dismiss="modal">Close</button>');

    const detailModalEl = document.getElementById('transferDetailModal');
    let detailModal = bootstrap.Modal.getInstance(detailModalEl);
    if (!detailModal) detailModal = new bootstrap.Modal(detailModalEl);
    detailModal.show();

    $.getJSON(API + '?action=get&id=' + id)
        .done(function(res) {
            // Accept both res.success (bool) and legacy res.status === 'success'
            const ok = res.success === true || res.status === 'success';
            if (!ok) {
                $('#detailBody').html('<div style="text-align:center;padding:40px;color:var(--tr-red-md)">'
                    + '<i class="bi bi-exclamation-circle" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.5"></i>'
                    + esc(res.message || 'Transfer not found.') + '</div>');
                return;
            }
            const d = res.data || {};
            // Merge top-level timeline key returned by the fixed API
            if (!d.timeline && res.timeline) d.timeline = res.timeline;
            $('#detailTransferId').text(d.transfer_id || ('#' + id));
            renderDetailBody(d);
            renderDetailActions(d);
        })
        .fail(function(xhr) {
            let msg = 'Server error. Please try again.';
            try { const p = JSON.parse(xhr.responseText || '{}'); if (p.message) msg = p.message; } catch(e) {}
            $('#detailBody').html('<div style="text-align:center;padding:40px;color:var(--tr-red-md)">'
                + '<i class="bi bi-wifi-off" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.5"></i>'
                + esc(msg) + '</div>');
        });
}

function renderDetailBody(d) {
    const statusColor = /complete/i.test(d.status) ? 'var(--tr-green-md)' : /reject/i.test(d.status) ? 'var(--tr-red-md)' : 'var(--tr-am-md)';
    const timeline = (d.timeline||[]).map((t,i) => `
        <div style="display:flex;gap:12px;${i<(d.timeline.length-1)?'padding-bottom:14px;border-left:2px solid var(--tr-border);margin-left:7px;padding-left:20px':''}">
            <div style="width:16px;height:16px;border-radius:50%;background:${/complete|approve/i.test(t.action)?'var(--tr-green-md)':/reject/i.test(t.action)?'var(--tr-red-md)':'var(--tr-teal-md)'};flex-shrink:0;margin-top:2px;${i>0?'margin-left:-28px':''}"></div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:var(--tr-slate)">${esc(t.action)}</div>
                <div style="font-size:.75rem;color:var(--tr-muted)">${esc(t.actor||'')} · ${fmtDate(t.created_at)}</div>
                ${t.note ? `<div style="font-size:.78rem;color:var(--tr-muted);margin-top:2px;font-style:italic">"${esc(t.note)}"</div>` : ''}
            </div>
        </div>`).join('');

    const html = `
<div style="font-family:var(--tr-fb)">

    <!-- Status banner -->
    <div style="background:var(--tr-teal);color:#fff;border-radius:12px;padding:16px 20px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:4px">Transfer Status</div>
            ${statusBadge(d.status)}
        </div>
        <div style="text-align:right">
            <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Filed</div>
            <div style="font-size:.84rem;font-weight:600">${fmtDate(d.created_at)}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Priority</div>
            <span style="background:${d.priority==='Urgent'?'rgba(239,68,68,.2)':'rgba(255,255,255,.15)'};border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:700">${esc(d.priority||'Normal')}</span>
        </div>
    </div>

    <!-- 3-col grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px">

        <!-- Student info -->
        <div style="background:var(--tr-surface);border:1px solid var(--tr-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--tr-teal-lt);padding:10px 16px;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tr-teal-md);display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--tr-teal-bd)"><i class="bi bi-person-fill"></i> Student</div>
            <div style="padding:14px 16px">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    <div class="tr-avatar">${inits(d.student_name)}</div>
                    <div>
                        <div style="font-weight:700;color:var(--tr-slate);font-size:.9rem">${esc(d.student_name)}</div>
                        <div style="font-family:var(--tr-fm);font-size:.72rem;color:var(--tr-muted)">${esc(d.student_code||'')}</div>
                    </div>
                </div>
                <table style="width:100%;font-size:.8rem;border-collapse:collapse">
                    <tr><td style="color:var(--tr-muted);padding:3px 0">Course</td><td style="font-weight:600;color:var(--tr-slate);text-align:right">${esc(d.course_name||'—')}</td></tr>
                    <tr><td style="color:var(--tr-muted);padding:3px 0">Category</td><td style="font-weight:600;text-align:right"><span class="tr-badge tb-hold" style="font-size:.68rem">${esc(d.transfer_category||'—')}</span></td></tr>
                </table>
            </div>
        </div>

        <!-- Route info -->
        <div style="background:var(--tr-surface);border:1px solid var(--tr-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--tr-teal-lt);padding:10px 16px;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tr-teal-md);display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--tr-teal-bd)"><i class="bi bi-arrow-left-right"></i> Transfer Route</div>
            <div style="padding:14px 16px">
                <div style="text-align:center;margin-bottom:12px">
                    <span class="tr-branch-from" style="margin-bottom:6px;display:inline-flex">${esc(d.origin_branch||'—')}</span>
                    <div style="color:var(--tr-subtle);font-size:1.2rem;margin:4px 0">↓</div>
                    <span class="tr-branch-to" style="display:inline-flex">${esc(d.destination_branch||'—')}</span>
                </div>
                <table style="width:100%;font-size:.8rem;border-collapse:collapse">
                    <tr><td style="color:var(--tr-muted);padding:3px 0">Transfer Date</td><td style="font-weight:600;text-align:right">${fmtDate(d.transfer_date)}</td></tr>
                    <tr><td style="color:var(--tr-muted);padding:3px 0">Admin Fee</td><td style="font-family:var(--tr-fm);font-weight:600;text-align:right">${fmt(d.transfer_fee||0)}</td></tr>
                </table>
            </div>
        </div>

        <!-- Financial summary -->
        <div style="background:var(--tr-surface);border:1px solid var(--tr-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--tr-teal-lt);padding:10px 16px;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tr-teal-md);display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--tr-teal-bd)"><i class="bi bi-cash-stack"></i> Financial</div>
            <div style="padding:6px 0">
                <div style="display:flex;justify-content:space-between;padding:6px 16px;font-size:.8rem"><span style="color:var(--tr-muted)">Course Fee</span><span style="font-family:var(--tr-fm);font-weight:600">${fmt(d.course_fee||0)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 16px;font-size:.8rem"><span style="color:var(--tr-muted)">Paid</span><span style="font-family:var(--tr-fm);font-weight:600;color:var(--tr-green-md)">${fmt(d.total_paid||0)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:8px 16px;font-size:.84rem;border-top:1px solid var(--tr-border);margin-top:4px"><span style="font-weight:700;color:${parseFloat(d.outstanding_balance||0)>0?'var(--tr-red-md)':'var(--tr-green-md)'}">Balance</span><span style="font-family:var(--tr-fm);font-weight:800;color:${parseFloat(d.outstanding_balance||0)>0?'var(--tr-red-md)':'var(--tr-green-md)'}">${parseFloat(d.outstanding_balance||0)>0?fmt(d.outstanding_balance):'Cleared ✓'}</span></div>
                <div style="padding:6px 16px;"><span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--tr-muted)">Fee Policy: </span><span style="font-size:.72rem;font-weight:700;color:var(--tr-teal-md)">${esc(d.fee_policy||'migrate')}</span></div>
            </div>
        </div>

    </div>

    <!-- Reason & Notes + Timeline -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="background:var(--tr-surface);border:1px solid var(--tr-border);border-radius:12px;padding:16px">
            <div style="font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tr-muted);margin-bottom:10px">Transfer Reason</div>
            <p style="font-size:.875rem;color:var(--tr-slate);line-height:1.6;margin:0">${esc(d.reason||'No reason provided.')}</p>
            ${d.documents ? `<div style="margin-top:10px;padding:8px 12px;background:var(--tr-page);border-radius:7px;font-size:.8rem;color:var(--tr-muted)"><i class="bi bi-paperclip me-1"></i>${esc(d.documents)}</div>` : ''}
        </div>
        <div style="background:var(--tr-surface);border:1px solid var(--tr-border);border-radius:12px;padding:16px">
            <div style="font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tr-muted);margin-bottom:12px">Approval Timeline</div>
            ${timeline || '<div style="font-size:.82rem;color:var(--tr-muted);font-style:italic">No activity recorded yet.</div>'}
        </div>
    </div>

</div>`;
    $('#detailBody').html(html);
}

function renderDetailActions(d) {
    // Match against the actual ENUM values: 'Pending Origin Approval',
    // 'Origin On Hold', 'Origin Rejected', 'Pending Destination Approval',
    // 'Destination Conditionally Approved', 'Destination Rejected', 'Transfer Complete'
    const s = String(d.status || '').toLowerCase();
    const rid = d.id;
    let btns = '<button type="button" class="tr-btn tr-btn-ghost tr-btn-sm" data-bs-dismiss="modal">Close</button>';

    const isComplete  = s.includes('complete');
    const isRejected  = s.includes('rejected');
    const isPendOrig  = s.includes('pending origin');
    const isOnHold    = s.includes('on hold');
    const isPendDest  = s.includes('pending destination');
    const isCondAppr  = s.includes('conditionally');

    if (isPendOrig || isOnHold) {
        btns += `<button class="tr-btn tr-btn-green tr-btn-sm" onclick="actionTransfer(${rid},'approve_origin')"><i class="bi bi-check-circle-fill"></i> Approve (Origin)</button>`;
        btns += `<button class="tr-btn tr-btn-red tr-btn-sm" onclick="actionTransfer(${rid},'reject')"><i class="bi bi-x-circle-fill"></i> Reject</button>`;
    }
    if (isPendDest || isCondAppr) {
        btns += `<button class="tr-btn tr-btn-teal tr-btn-sm" onclick="actionTransfer(${rid},'approve_dest')"><i class="bi bi-check2-all"></i> Accept (Destination)</button>`;
        btns += `<button class="tr-btn tr-btn-red tr-btn-sm" onclick="actionTransfer(${rid},'reject')"><i class="bi bi-x-circle-fill"></i> Reject</button>`;
    }
    if (!isComplete && !isRejected && isSA) {
        btns += `<button class="tr-btn tr-btn-ghost tr-btn-sm" onclick="actionTransfer(${rid},'hold')" style="border-color:var(--tr-blue-bd);color:var(--tr-blue-md)"><i class="bi bi-pause-circle-fill"></i> Hold</button>`;
    }

    $('#detailActions').html(btns);
}

function actionTransfer(id, action) {
    const actionLabels = {
        approve_origin: 'Approve from Origin Branch',
        approve_dest:   'Accept at Destination Branch',
        reject:         'Reject this Transfer',
        hold:           'Place on Hold',
        fee_settled:    'Mark Fee as Settled',
        complete:       'Mark Transfer Complete'
    };
    const isDanger = ['reject'].includes(action);
    const needsNote = ['reject','hold'].includes(action);

    Swal.fire({
        title: actionLabels[action] || action,
        html: needsNote ? `<textarea id="swal-note" class="swal2-input" placeholder="Add a note (required)…" style="height:80px;resize:vertical;font-family:var(--tr-fb)"></textarea>` : `<p style="color:var(--tr-muted)">Confirm this action?</p>`,
        icon: isDanger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: actionLabels[action],
        confirmButtonColor: isDanger ? '#DC2626' : '#0E7490',
        preConfirm: () => {
            if (needsNote) {
                const note = document.getElementById('swal-note')?.value?.trim();
                if (!note) { Swal.showValidationMessage('A note is required.'); return false; }
                return note;
            }
            return true;
        }
    }).then(r => {
        if (!r.isConfirmed) return;
        // Send both id formats so both old and new API endpoint variants work
        $.post(API + '?action=' + action, {
            id: id,
            transfer_request_id: id,
            note: typeof r.value === 'string' ? r.value : '',
            rationale: typeof r.value === 'string' ? r.value : ''
        }, function(res) {
            if (res.success) {
                Swal.fire({icon:'success',title:'Done',text:res.message||'Action completed.',timer:2000,showConfirmButton:false});
                bootstrap.Modal.getInstance(document.getElementById('transferDetailModal'))?.hide();
                loadTable();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
}

// ── Init ──────────────────────────────────────────────────────
$(function() {
    loadTable();
    if (!isSA && myBranch) loadStudents(myBranch);
    $('#originBranchSel').on('change', function() {
        const bid = $(this).val();
        if (bid) loadStudents(bid);
        else $('#studentSel').empty().append('<option value="">— Select origin branch first —</option>');
        $('#studentInfoCard').hide();
    });
});
</script>
</body>
</html>