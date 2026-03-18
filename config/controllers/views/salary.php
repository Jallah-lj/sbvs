<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit;
}
require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    header("Location: dashboard.php"); exit;
}

$branches = [];
$branchName = '';
if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($sessionBranch) {
        $bStmt = $db->prepare("SELECT name FROM branches WHERE id=?");
        $bStmt->execute([$sessionBranch]);
        $branchName = $bStmt->fetchColumn() ?: '';
    }
}
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');

$pageTitle  = 'Salary Management';
$activePage = 'salary.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ══════════════════════════════════════════════════════════════
   SALARY MANAGEMENT — International HR Standard
   Font: Sora (display) + IBM Plex Sans (data/body)
   Palette: Deep navy + emerald + warm amber
══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap');

:root {
    /* Brand palette */
    --sl-navy:     #0F1C3F;
    --sl-navy-md:  #1A2E5A;
    --sl-navy-lt:  #EEF2FF;
    --sl-navy-bd:  #C7D2FE;
    --sl-emerald:  #065F46;
    --sl-em-md:    #059669;
    --sl-em-lt:    #ECFDF5;
    --sl-em-bd:    #A7F3D0;
    --sl-amber:    #92400E;
    --sl-am-md:    #D97706;
    --sl-am-lt:    #FFFBEB;
    --sl-am-bd:    #FDE68A;
    --sl-red:      #991B1B;
    --sl-red-md:   #DC2626;
    --sl-red-lt:   #FEF2F2;
    --sl-red-bd:   #FECACA;
    --sl-violet:   #4C1D95;
    --sl-vi-md:    #7C3AED;
    --sl-vi-lt:    #F5F3FF;
    --sl-vi-bd:    #DDD6FE;
    /* Neutrals */
    --sl-slate:    #0F172A;
    --sl-muted:    #475569;
    --sl-subtle:   #94A3B8;
    --sl-surface:  #FFFFFF;
    --sl-page:     #F1F5F9;
    --sl-border:   #E2E8F0;
    --sl-border2:  #CBD5E1;
    /* Typography */
    --sl-font-d:   'Sora', system-ui, sans-serif;
    --sl-font-b:   'IBM Plex Sans', system-ui, sans-serif;
    --sl-font-m:   'IBM Plex Mono', monospace;
    /* Geometry */
    --sl-r:        10px;
    --sl-rlg:      16px;
    --sl-rxl:      20px;
    --sl-shadow:   0 1px 3px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.07);
    --sl-shadow-md:0 4px 8px rgba(0,0,0,.06), 0 12px 36px rgba(0,0,0,.10);
    --sl-shadow-lg:0 8px 16px rgba(0,0,0,.08), 0 24px 48px rgba(0,0,0,.14);
}

/* ── Base ── */
.sl-wrap, .sl-wrap * { font-family: var(--sl-font-b); box-sizing: border-box; }
.sl-wrap h1, .sl-wrap h2, .sl-wrap h3, .sl-wrap h4, .sl-wrap h5 { font-family: var(--sl-font-d); }

/* ── Page header ── */
.sl-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 16px; flex-wrap: wrap;
    margin-bottom: 32px;
}
.sl-header-left h2 {
    font-size: 1.65rem; font-weight: 800;
    color: var(--sl-slate); letter-spacing: -.03em;
    margin: 0 0 5px; font-family: var(--sl-font-d);
}
.sl-header-left p { font-size: .875rem; color: var(--sl-muted); margin: 0; }
.sl-branch-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--sl-navy-lt); color: var(--sl-navy-md);
    border: 1px solid var(--sl-navy-bd); border-radius: 20px;
    padding: 3px 11px; font-size: .72rem; font-weight: 700;
    letter-spacing: .3px; margin-top: 6px;
}
.sl-header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

/* ── Buttons ── */
.sl-btn {
    display: inline-flex; align-items: center; gap: 7px;
    height: 40px; padding: 0 18px; border: none;
    border-radius: var(--sl-r);
    font-family: var(--sl-font-b); font-size: .855rem; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: all .15s; white-space: nowrap;
}
.sl-btn-navy {
    background: var(--sl-navy); color: #fff;
    box-shadow: 0 2px 8px rgba(15,28,63,.35);
}
.sl-btn-navy:hover { background: var(--sl-navy-md); color: #fff; box-shadow: 0 4px 14px rgba(15,28,63,.4); }
.sl-btn-emerald {
    background: var(--sl-em-md); color: #fff;
    box-shadow: 0 2px 8px rgba(5,150,105,.28);
}
.sl-btn-emerald:hover { background: var(--sl-emerald); color: #fff; }
.sl-btn-ghost {
    background: var(--sl-surface); color: var(--sl-muted);
    border: 1.5px solid var(--sl-border2);
}
.sl-btn-ghost:hover { background: var(--sl-page); color: var(--sl-slate); }
.sl-btn-sm { height: 32px; padding: 0 12px; font-size: .78rem; }
.sl-btn-danger { background: var(--sl-red-md); color: #fff; }
.sl-btn-danger:hover { background: var(--sl-red); color: #fff; }
.sl-btn-amber { background: var(--sl-am-md); color: #fff; }
.sl-btn-amber:hover { background: var(--sl-amber); color: #fff; }
.sl-btn-violet { background: var(--sl-vi-md); color: #fff; }
.sl-btn-violet:hover { background: var(--sl-violet); color: #fff; }

/* ── KPI Cards ── */
.sl-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 28px;
}
.sl-kpi {
    background: var(--sl-surface);
    border: 1px solid var(--sl-border);
    border-radius: var(--sl-rlg);
    padding: 22px 22px 18px;
    box-shadow: var(--sl-shadow);
    transition: box-shadow .2s, transform .2s;
    position: relative; overflow: hidden;
}
.sl-kpi:hover { box-shadow: var(--sl-shadow-md); transform: translateY(-2px); }
.sl-kpi::after {
    content: ''; position: absolute;
    bottom: 0; left: 0; right: 0; height: 3px;
}
.sl-kpi.k-navy::after   { background: var(--sl-navy); }
.sl-kpi.k-emerald::after{ background: var(--sl-em-md); }
.sl-kpi.k-amber::after  { background: var(--sl-am-md); }
.sl-kpi.k-violet::after { background: var(--sl-vi-md); }

.sl-kpi-top {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 14px;
}
.sl-kpi-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.k-navy .sl-kpi-icon    { background: var(--sl-navy-lt);  color: var(--sl-navy); }
.k-emerald .sl-kpi-icon { background: var(--sl-em-lt);    color: var(--sl-em-md); }
.k-amber .sl-kpi-icon   { background: var(--sl-am-lt);    color: var(--sl-am-md); }
.k-violet .sl-kpi-icon  { background: var(--sl-vi-lt);    color: var(--sl-vi-md); }

.sl-kpi-trend {
    font-size: .72rem; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
}
.trend-up { background: var(--sl-em-lt); color: var(--sl-em-md); }
.sl-kpi-val {
    font-size: 1.65rem; font-weight: 800;
    color: var(--sl-slate); letter-spacing: -.02em;
    line-height: 1; margin-bottom: 4px;
    font-family: var(--sl-font-d);
}
.sl-kpi-lbl {
    font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--sl-subtle);
}
.sl-kpi-sub {
    font-size: .75rem; color: var(--sl-muted);
    margin-top: 3px;
}

/* ── Payroll Pipeline ── */
.sl-pipeline {
    background: var(--sl-surface);
    border: 1px solid var(--sl-border);
    border-radius: var(--sl-rlg);
    padding: 20px 24px;
    box-shadow: var(--sl-shadow);
    margin-bottom: 28px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 0;
    overflow-x: auto;
}
.sl-pipeline-step {
    display: flex; align-items: center; gap: 10px;
    flex: 1; min-width: 120px; position: relative;
    cursor: default;
}
.sl-pipeline-step:not(:last-child)::after {
    content: '';
    position: absolute; right: -2px; top: 50%;
    transform: translateY(-50%);
    width: 4px; height: 4px;
    background: var(--sl-border2);
    border-radius: 50%;
    z-index: 1;
}
.sl-pip-num {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 800;
    flex-shrink: 0; transition: all .2s;
    font-family: var(--sl-font-d);
}
.sl-pipeline-step.done .sl-pip-num {
    background: var(--sl-em-lt); color: var(--sl-em-md);
    border: 2px solid var(--sl-em-bd);
}
.sl-pipeline-step.current .sl-pip-num {
    background: var(--sl-navy); color: #fff;
    box-shadow: 0 0 0 4px var(--sl-navy-bd);
}
.sl-pipeline-step.pending .sl-pip-num {
    background: var(--sl-page); color: var(--sl-subtle);
    border: 2px solid var(--sl-border2);
}
.sl-pip-info {}
.sl-pip-title {
    font-size: .78rem; font-weight: 700;
    letter-spacing: .3px; color: var(--sl-slate);
}
.sl-pipeline-step.pending .sl-pip-title { color: var(--sl-subtle); }
.sl-pip-desc { font-size: .7rem; color: var(--sl-muted); margin-top: 1px; }
.sl-pip-sep { width: 24px; flex-shrink: 0; }

/* ── Tab navigation ── */
.sl-tabs {
    display: flex; gap: 4px;
    background: var(--sl-page);
    border: 1px solid var(--sl-border);
    border-radius: var(--sl-rlg);
    padding: 5px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.sl-tab {
    display: flex; align-items: center; gap: 7px;
    padding: 9px 18px; border: none; border-radius: var(--sl-r);
    font-family: var(--sl-font-b); font-size: .85rem; font-weight: 600;
    color: var(--sl-muted); background: transparent;
    cursor: pointer; white-space: nowrap;
    transition: all .15s; text-decoration: none;
}
.sl-tab:hover { color: var(--sl-slate); background: rgba(255,255,255,.7); }
.sl-tab.active {
    color: var(--sl-navy); background: var(--sl-surface);
    box-shadow: var(--sl-shadow);
}
.sl-tab .tab-badge {
    min-width: 20px; height: 20px;
    background: var(--sl-navy-lt); color: var(--sl-navy);
    border-radius: 10px; padding: 0 6px;
    font-size: .68rem; font-weight: 800;
    display: inline-flex; align-items: center; justify-content: center;
}
.sl-tab.active .tab-badge { background: var(--sl-navy); color: #fff; }

/* ── Tab panels ── */
.sl-tab-panel { display: none; }
.sl-tab-panel.active { display: block; }

/* ── Section card ── */
.sl-card {
    background: var(--sl-surface);
    border: 1px solid var(--sl-border);
    border-radius: var(--sl-rlg);
    box-shadow: var(--sl-shadow);
    overflow: hidden;
    margin-bottom: 20px;
}
.sl-card-head {
    padding: 18px 22px;
    border-bottom: 1px solid var(--sl-border);
    display: flex; align-items: center;
    justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.sl-card-head h5 {
    font-size: .95rem; font-weight: 700;
    color: var(--sl-slate); margin: 0;
    display: flex; align-items: center; gap: 8px;
    font-family: var(--sl-font-d);
}
.sl-card-head h5 i { color: var(--sl-navy); }
.sl-card-body { padding: 0; }

/* ── Table ── */
.sl-table thead th {
    background: var(--sl-navy);
    color: rgba(255,255,255,.8);
    font-size: .67rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .7px;
    padding: 12px 15px; white-space: nowrap;
    border: none;
}
.sl-table thead th:first-child { border-radius: 0; }
.sl-table tbody td {
    padding: 13px 15px; font-size: .855rem;
    border-bottom: 1px solid var(--sl-border);
    vertical-align: middle;
    color: var(--sl-slate);
}
.sl-table tbody tr:last-child td { border-bottom: none; }
.sl-table tbody tr:hover td { background: var(--sl-navy-lt); }
.sl-table tbody tr.voided td { opacity: .5; }
.sl-table tbody tr.voided:hover td { background: var(--sl-red-lt); opacity: .7; }

/* DataTables overrides */
.dataTables_wrapper .dataTables_filter input {
    border: 1.5px solid var(--sl-border2) !important;
    border-radius: 8px !important; height: 34px !important;
    font-family: var(--sl-font-b) !important; font-size: .84rem !important;
    box-shadow: none !important; padding: 0 10px !important; outline: none !important;
}
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--sl-navy) !important; }
.dataTables_wrapper .dataTables_length select {
    border: 1.5px solid var(--sl-border2) !important;
    border-radius: 8px !important; height: 34px !important;
    font-family: var(--sl-font-b) !important; font-size: .84rem !important;
    padding: 0 26px 0 10px !important;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate { font-size: .82rem; color: var(--sl-muted); }
.dataTables_wrapper .paginate_button { border-radius: 7px !important; }
.dataTables_wrapper .paginate_button.current,
.dataTables_wrapper .paginate_button.current:hover {
    background: var(--sl-navy) !important;
    border-color: var(--sl-navy) !important; color: #fff !important;
}

/* ── Badges ── */
.sl-badge {
    display: inline-flex; align-items: center; gap: 4px;
    border-radius: 20px; padding: 3px 10px;
    font-size: .7rem; font-weight: 700;
    letter-spacing: .3px; white-space: nowrap;
    font-family: var(--sl-font-b);
}
.sb-navy    { background: var(--sl-navy-lt); color: var(--sl-navy); }
.sb-emerald { background: var(--sl-em-lt);   color: var(--sl-em-md); }
.sb-amber   { background: var(--sl-am-lt);   color: var(--sl-am-md); }
.sb-red     { background: var(--sl-red-lt);  color: var(--sl-red-md); }
.sb-violet  { background: var(--sl-vi-lt);   color: var(--sl-vi-md); }
.sb-muted   { background: var(--sl-page);    color: var(--sl-muted); border: 1px solid var(--sl-border2); }
.sb-earning { background: #F0FDF4; color: #166534; }
.sb-deduct  { background: #FEF2F2; color: #991B1B; }
.sb-tax     { background: #FFFBEB; color: #92400E; }

/* ── Status indicators ── */
.sl-status-dot {
    width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0;
}
.sd-active  { background: var(--sl-em-md); box-shadow: 0 0 0 3px var(--sl-em-lt); }
.sd-pending { background: var(--sl-am-md); box-shadow: 0 0 0 3px var(--sl-am-lt); }
.sd-paid    { background: #3B82F6; box-shadow: 0 0 0 3px #EFF6FF; }
.sd-voided  { background: var(--sl-red-md); box-shadow: 0 0 0 3px var(--sl-red-lt); }

/* ── Employee avatar ── */
.sl-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; font-weight: 800;
    flex-shrink: 0; text-transform: uppercase;
    background: var(--sl-navy-lt); color: var(--sl-navy);
    font-family: var(--sl-font-d);
}

/* ── Table action buttons ── */
.sl-tbl-act {
    width: 30px; height: 30px;
    border: none; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; cursor: pointer;
    transition: all .15s;
}
.sta-navy   { background: var(--sl-navy-lt); color: var(--sl-navy); }
.sta-navy:hover { background: var(--sl-navy); color: #fff; }
.sta-amber  { background: var(--sl-am-lt); color: var(--sl-am-md); }
.sta-amber:hover { background: var(--sl-am-md); color: #fff; }
.sta-red    { background: var(--sl-red-lt); color: var(--sl-red-md); }
.sta-red:hover { background: var(--sl-red-md); color: #fff; }
.sta-emerald{ background: var(--sl-em-lt); color: var(--sl-em-md); }
.sta-emerald:hover { background: var(--sl-em-md); color: #fff; }
.sta-violet { background: var(--sl-vi-lt); color: var(--sl-vi-md); }
.sta-violet:hover { background: var(--sl-vi-md); color: #fff; }

/* ── Mono code ── */
.sl-code {
    font-family: var(--sl-font-m); font-size: .8rem;
    background: var(--sl-page); color: var(--sl-muted);
    border-radius: 5px; padding: 2px 7px;
    border: 1px solid var(--sl-border);
}

/* ── Run row period ── */
.sl-period { font-family: var(--sl-font-d); font-size: .88rem; font-weight: 700; color: var(--sl-slate); }
.sl-period-date { font-size: .75rem; color: var(--sl-muted); margin-top: 1px; }

/* ── Modals ── */
.sl-modal .modal-content {
    border: none; border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--sl-shadow-lg);
    font-family: var(--sl-font-b);
}
.sl-modal .modal-header {
    padding: 20px 26px 16px;
    border-bottom: 1px solid var(--sl-border);
}
.sl-modal .modal-header.navy {
    background: var(--sl-navy); color: #fff; border-bottom: none;
}
.sl-modal .modal-header.navy .btn-close { filter: invert(1); }
.sl-modal .modal-header.emerald {
    background: linear-gradient(135deg, var(--sl-emerald), var(--sl-em-md));
    color: #fff; border-bottom: none;
}
.sl-modal .modal-header.emerald .btn-close { filter: invert(1); }
.sl-modal .modal-header.amber-warn {
    background: var(--sl-am-lt); border-bottom: 1px solid var(--sl-am-bd);
}
.sl-modal .modal-header.danger {
    background: var(--sl-red-lt); border-bottom: 1px solid var(--sl-red-bd);
}
.sl-modal .modal-header h5 {
    font-family: var(--sl-font-d); font-size: 1rem;
    font-weight: 700; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.sl-modal .modal-body { background: var(--sl-page); padding: 22px 26px; }
.sl-modal .modal-body.white { background: #fff; }
.sl-modal .modal-footer {
    background: var(--sl-surface);
    border-top: 1px solid var(--sl-border);
    padding: 14px 26px;
}

/* ── Modal form elements ── */
.sl-field label {
    font-size: .67rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--sl-muted); display: block; margin-bottom: 5px;
    font-family: var(--sl-font-b);
}
.sl-field label span { color: var(--sl-red-md); }
.sl-input, .sl-select, .sl-textarea {
    width: 100%; height: 40px; padding: 0 12px;
    border: 1.5px solid var(--sl-border2);
    border-radius: 8px;
    font-family: var(--sl-font-b); font-size: .875rem;
    color: var(--sl-slate); background: #fff; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.sl-input:focus, .sl-select:focus, .sl-textarea:focus {
    border-color: var(--sl-navy);
    box-shadow: 0 0 0 3px rgba(15,28,63,.1);
}
.sl-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    padding-right: 30px; cursor: pointer;
}
.sl-textarea { height: auto; padding: 10px 12px; resize: vertical; }
.sl-input-group {
    display: flex; align-items: center;
    border: 1.5px solid var(--sl-border2); border-radius: 8px;
    overflow: hidden; background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.sl-input-group:focus-within {
    border-color: var(--sl-navy);
    box-shadow: 0 0 0 3px rgba(15,28,63,.1);
}
.sl-input-pfx {
    padding: 0 12px; height: 40px;
    display: flex; align-items: center;
    font-size: .84rem; font-weight: 600;
    color: var(--sl-muted);
    border-right: 1.5px solid var(--sl-border);
    background: var(--sl-page); flex-shrink: 0;
    font-family: var(--sl-font-m);
}
.sl-input-group .sl-input { border: none; box-shadow: none; border-radius: 0; padding-left: 10px; }

/* ── Section divider ── */
.sl-section-label {
    font-size: .67rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .7px;
    color: var(--sl-muted); margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
    font-family: var(--sl-font-b);
}
.sl-section-label::after {
    content: ''; flex: 1; height: 1px;
    background: var(--sl-border);
}

/* ── Grade range hint ── */
.sl-grade-hint {
    font-size: .78rem; color: var(--sl-navy);
    font-weight: 600; margin-top: 4px;
    display: flex; align-items: center; gap: 5px;
}

/* ── Override input container ── */
.sl-override-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
.sl-override-item {}
.sl-override-item label {
    font-size: .7rem; font-weight: 700;
    color: var(--sl-muted); display: block; margin-bottom: 4px;
    display: flex; align-items: center; gap: 5px;
}

/* ── Preview panel ── */
.sl-preview {
    background: var(--sl-surface);
    border: 1.5px solid var(--sl-em-bd);
    border-radius: 12px; overflow: hidden;
}
.sl-preview-head {
    background: var(--sl-em-lt);
    padding: 12px 16px;
    display: flex; align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--sl-em-bd);
}
.sl-preview-head span {
    font-size: .72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sl-em-md);
    display: flex; align-items: center; gap: 6px;
}
.sl-preview-kpis {
    display: grid; grid-template-columns: repeat(3,1fr);
    border-bottom: 1px solid var(--sl-border);
}
.sl-preview-kpi {
    padding: 14px 16px; text-align: center;
    border-right: 1px solid var(--sl-border);
}
.sl-preview-kpi:last-child { border-right: none; }
.sl-preview-kpi label {
    font-size: .67rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--sl-muted); display: block; margin-bottom: 4px;
}
.sl-preview-kpi span { font-size: 1.1rem; font-weight: 800; font-family: var(--sl-font-d); }

/* ── Payslip ── */
.sl-payslip-header {
    background: var(--sl-navy); color: #fff;
    padding: 24px 28px; border-radius: 12px 12px 0 0;
}
.sl-payslip-body { padding: 24px 28px; background: #fff; }
.sl-payslip-footer {
    background: var(--sl-page); padding: 14px 28px;
    border-top: 1px solid var(--sl-border);
    border-radius: 0 0 12px 12px;
    font-size: .75rem; color: var(--sl-muted); text-align: center;
}
.sl-payslip-net {
    background: var(--sl-navy-lt);
    border: 2px solid var(--sl-navy-bd);
    border-radius: 12px; padding: 20px;
    text-align: center; margin-top: 16px;
}
.sl-payslip-net label {
    font-size: .7rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .8px;
    color: var(--sl-navy); display: block; margin-bottom: 6px;
}
.sl-payslip-net span {
    font-size: 2rem; font-weight: 800;
    color: var(--sl-navy); font-family: var(--sl-font-d);
    letter-spacing: -.03em;
}

/* ── Toggle switches ── */
.sl-toggle-row {
    display: flex; justify-content: space-between; align-items: center;
    background: var(--sl-page); border: 1px solid var(--sl-border);
    border-radius: 8px; padding: 10px 14px;
}
.sl-toggle-row label { font-size: .85rem; font-weight: 500; color: var(--sl-slate); margin: 0; }

/* ── Void warning ── */
.sl-void-warning {
    background: var(--sl-red-lt); border: 1.5px solid var(--sl-red-bd);
    border-radius: 10px; padding: 14px 16px;
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px;
}
.sl-void-warning i { color: var(--sl-red-md); font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
.sl-void-warning p { font-size: .875rem; color: var(--sl-red); margin: 0; line-height: 1.5; }

/* ── Run status pills ── */
.sl-run-status { display: flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 600; }

/* ── Responsive ── */
@media (max-width: 1100px) {
    .sl-kpi-grid { grid-template-columns: repeat(2,1fr); }
    .sl-override-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 640px) {
    .sl-kpi-grid { grid-template-columns: 1fr; }
    .sl-override-grid { grid-template-columns: 1fr; }
    .sl-tabs { flex-wrap: nowrap; }
}
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main sl-wrap">

    <!-- ── Page Header ─────────────────────────────────── -->
    <div class="sl-header fade-in">
        <div class="sl-header-left">
            <h2><i class="bi bi-cash-coin me-2" style="color:var(--sl-navy);font-size:1.3rem;vertical-align:middle;"></i>Salary & Payroll Management</h2>
            <p>Configure pay grades, salary components, employee profiles, and process payroll runs.</p>
            <?php if (!$isSuperAdmin && $branchName): ?>
            <span class="sl-branch-tag"><i class="bi bi-building-fill"></i><?= htmlspecialchars($branchName) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($isSuperAdmin || $isBranchAdmin): ?>
        <div class="sl-header-actions">
            <button class="sl-btn sl-btn-navy" onclick="openRunModal()">
                <i class="bi bi-play-circle-fill"></i> New Payroll Run
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── KPI Cards ───────────────────────────────────── -->
    <div class="sl-kpi-grid">
        <div class="sl-kpi k-navy fade-in" style="animation-delay:.05s">
            <div class="sl-kpi-top">
                <div class="sl-kpi-icon"><i class="bi bi-people-fill"></i></div>
                <span class="sl-kpi-trend trend-up" id="kpiProfiledTrend" style="display:none"></span>
            </div>
            <div class="sl-kpi-val" id="kpiProfiled">—</div>
            <div class="sl-kpi-lbl">Profiled Employees</div>
            <div class="sl-kpi-sub" id="kpiProfiledSub">Active salary profiles</div>
        </div>
        <div class="sl-kpi k-emerald fade-in" style="animation-delay:.1s">
            <div class="sl-kpi-top">
                <div class="sl-kpi-icon"><i class="bi bi-safe2-fill"></i></div>
            </div>
            <div class="sl-kpi-val" id="kpiBudget">—</div>
            <div class="sl-kpi-lbl">Monthly Salary Budget</div>
            <div class="sl-kpi-sub">Total basic payroll allocation</div>
        </div>
        <div class="sl-kpi k-amber fade-in" style="animation-delay:.15s">
            <div class="sl-kpi-top">
                <div class="sl-kpi-icon"><i class="bi bi-hourglass-bottom"></i></div>
            </div>
            <div class="sl-kpi-val" id="kpiPending">—</div>
            <div class="sl-kpi-lbl">Pending Net Payout</div>
            <div class="sl-kpi-sub">Processed, not yet paid</div>
        </div>
        <div class="sl-kpi k-violet fade-in" style="animation-delay:.2s">
            <div class="sl-kpi-top">
                <div class="sl-kpi-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="sl-kpi-val" id="kpiPaidMonth">—</div>
            <div class="sl-kpi-lbl">Disbursed This Month</div>
            <div class="sl-kpi-sub">Net salary paid</div>
        </div>
    </div>

    <!-- ── Payroll Process Pipeline ────────────────────── -->
    <div class="sl-pipeline fade-in" style="animation-delay:.25s">
        <div class="sl-pipeline-step done">
            <div class="sl-pip-num"><i class="bi bi-check-lg"></i></div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">1. Grade Setup</div>
                <div class="sl-pip-desc">Define pay bands</div>
            </div>
        </div>
        <div class="sl-pip-sep"></div>
        <div class="sl-pipeline-step done">
            <div class="sl-pip-num"><i class="bi bi-check-lg"></i></div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">2. Components</div>
                <div class="sl-pip-desc">Earnings &amp; deductions</div>
            </div>
        </div>
        <div class="sl-pip-sep"></div>
        <div class="sl-pipeline-step done">
            <div class="sl-pip-num"><i class="bi bi-check-lg"></i></div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">3. Employee Profiles</div>
                <div class="sl-pip-desc">Assign salaries</div>
            </div>
        </div>
        <div class="sl-pip-sep"></div>
        <div class="sl-pipeline-step current">
            <div class="sl-pip-num">4</div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">4. Payroll Run</div>
                <div class="sl-pip-desc">Generate payslips</div>
            </div>
        </div>
        <div class="sl-pip-sep"></div>
        <div class="sl-pipeline-step pending">
            <div class="sl-pip-num">5</div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">5. Approval</div>
                <div class="sl-pip-desc">Review &amp; confirm</div>
            </div>
        </div>
        <div class="sl-pip-sep"></div>
        <div class="sl-pipeline-step pending">
            <div class="sl-pip-num">6</div>
            <div class="sl-pip-info">
                <div class="sl-pip-title">6. Disbursement</div>
                <div class="sl-pip-desc">Mark as paid</div>
            </div>
        </div>
    </div>

    <!-- ── Tab Navigation ──────────────────────────────── -->
    <div class="sl-tabs fade-in" style="animation-delay:.3s" id="slTabs">
        <?php if ($isSuperAdmin): ?>
        <button class="sl-tab" data-tab="grades" onclick="switchTab('grades')">
            <i class="bi bi-layers-fill"></i> Pay Grades
            <span class="tab-badge" id="tbGrades">—</span>
        </button>
        <button class="sl-tab" data-tab="components" onclick="switchTab('components')">
            <i class="bi bi-list-check"></i> Components
            <span class="tab-badge" id="tbComps">—</span>
        </button>
        <?php endif; ?>
        <button class="sl-tab" data-tab="profiles" onclick="switchTab('profiles')">
            <i class="bi bi-person-badge-fill"></i> Employee Salaries
            <span class="tab-badge" id="tbProfiles">—</span>
        </button>
        <button class="sl-tab active" data-tab="runs" onclick="switchTab('runs')">
            <i class="bi bi-play-circle-fill"></i> Payroll Runs
            <span class="tab-badge" id="tbRuns">—</span>
        </button>
    </div>

    <!-- ══ TAB PANELS ════════════════════════════════════ -->

    <!-- GRADES -->
    <?php if ($isSuperAdmin): ?>
    <div class="sl-tab-panel" id="panel-grades">
        <div class="sl-card">
            <div class="sl-card-head">
                <h5><i class="bi bi-layers-fill"></i> Salary Grades / Pay Bands</h5>
                <button class="sl-btn sl-btn-navy sl-btn-sm" onclick="openGradeModal()">
                    <i class="bi bi-plus-lg"></i> Add Grade
                </button>
            </div>
            <div class="sl-card-body">
                <div class="table-responsive">
                    <table class="table sl-table align-middle w-100 mb-0" id="gradesTable">
                        <thead><tr>
                            <th>Grade Name</th><th>Level</th>
                            <th>Min Salary</th><th>Max Salary</th>
                            <th>Employees</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- COMPONENTS -->
    <div class="sl-tab-panel" id="panel-components">
        <div class="sl-card">
            <div class="sl-card-head">
                <h5><i class="bi bi-list-check"></i> Salary Components</h5>
                <button class="sl-btn sl-btn-navy sl-btn-sm" onclick="openComponentModal()">
                    <i class="bi bi-plus-lg"></i> Add Component
                </button>
            </div>
            <div class="sl-card-body">
                <div class="table-responsive">
                    <table class="table sl-table align-middle w-100 mb-0" id="componentsTable">
                        <thead><tr>
                            <th>Name</th><th>Code</th><th>Type</th>
                            <th>Calculation</th><th>Value</th>
                            <th>Applies To</th><th>Flags</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PROFILES -->
    <div class="sl-tab-panel" id="panel-profiles">
        <div class="sl-card">
            <div class="sl-card-head">
                <h5><i class="bi bi-person-badge-fill"></i> Employee Salary Profiles</h5>
                <button class="sl-btn sl-btn-navy sl-btn-sm" onclick="openProfileModal()">
                    <i class="bi bi-person-plus-fill"></i> Assign Salary
                </button>
            </div>
            <div class="sl-card-body">
                <div class="table-responsive">
                    <table class="table sl-table align-middle w-100 mb-0" id="profilesTable">
                        <thead><tr>
                            <th>Employee</th><th>Role</th><th>Branch</th>
                            <th>Grade</th><th>Basic</th><th>Mode</th>
                            <th>Effective</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PAYROLL RUNS -->
    <div class="sl-tab-panel active" id="panel-runs">
        <div class="sl-card">
            <div class="sl-card-head">
                <h5><i class="bi bi-play-circle-fill"></i> Payroll Runs</h5>
                <div style="font-size:.78rem;color:var(--sl-muted)" id="runsCount"></div>
            </div>
            <div class="sl-card-body">
                <div class="table-responsive">
                    <table class="table sl-table align-middle w-100 mb-0" id="runsTable">
                        <thead><tr>
                            <th>Period</th><th>Branch</th>
                            <th>Employees</th><th>Gross</th>
                            <th>Deductions</th><th>Net Pay</th>
                            <th>Status</th><th class="text-center">Actions</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</main>
</div>


<!-- ════════════════ GRADE MODAL ════════════════════════ -->
<div class="modal fade sl-modal" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="gradeForm" class="modal-content">
            <div class="modal-header navy">
                <h5 id="gradeModalTitle"><i class="bi bi-layers-fill"></i> Add Pay Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grade_id">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="sl-field">
                            <label>Grade Name <span>*</span></label>
                            <input type="text" id="grade_name" class="sl-input" placeholder="e.g. Senior Lecturer" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Level <span>*</span></label>
                            <input type="number" id="grade_level" class="sl-input" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Min Salary (USD)</label>
                            <div class="sl-input-group">
                                <span class="sl-input-pfx">$</span>
                                <input type="number" id="grade_min" class="sl-input" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Max Salary (USD)</label>
                            <div class="sl-input-group">
                                <span class="sl-input-pfx">$</span>
                                <input type="number" id="grade_max" class="sl-input" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sl-field">
                            <label>Description / Notes</label>
                            <textarea id="grade_desc" class="sl-textarea" rows="2" placeholder="Optional description…"></textarea>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sl-field">
                            <label>Status</label>
                            <select id="grade_status" class="sl-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sl-btn sl-btn-navy sl-btn-sm">
                    <i class="bi bi-save-fill"></i> Save Grade
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ════════════════ COMPONENT MODAL ════════════════════ -->
<div class="modal fade sl-modal" id="componentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="componentForm" class="modal-content">
            <div class="modal-header emerald">
                <h5 id="componentModalTitle"><i class="bi bi-list-check"></i> Add Salary Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="comp_id">
                <div class="sl-section-label">Basic Information</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Component Name <span>*</span></label>
                            <input type="text" id="comp_name" class="sl-input" placeholder="e.g. Housing Allowance" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Code <span>*</span> <span style="color:var(--sl-muted);font-weight:400;text-transform:none">(auto-generated)</span></label>
                            <input type="text" id="comp_code" class="sl-input" placeholder="e.g. HRA" style="font-family:var(--sl-font-m);text-transform:uppercase" required>
                        </div>
                    </div>
                </div>

                <div class="sl-section-label">Calculation Rules</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Component Type</label>
                            <select id="comp_type" class="sl-select">
                                <option value="Earning">Earning</option>
                                <option value="Deduction">Deduction</option>
                                <option value="Tax">Tax</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Calculation Method</label>
                            <select id="comp_calc" class="sl-select" onchange="togglePctOf()">
                                <option value="Fixed">Fixed Amount</option>
                                <option value="Percentage">Percentage of Base</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Value <span>*</span></label>
                            <div class="sl-input-group">
                                <span class="sl-input-pfx" id="compValueSuffix">$</span>
                                <input type="number" id="comp_value" class="sl-input" min="0" step="0.0001" value="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" id="pctOfWrapper" style="display:none;">
                        <div class="sl-field">
                            <label>Percentage Base</label>
                            <select id="comp_pct_of" class="sl-select">
                                <option value="basic_salary">Basic Salary</option>
                                <option value="gross_salary">Gross Salary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Applies To Role</label>
                            <select id="comp_applies_to" class="sl-select">
                                <option value="All">All Roles</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Admin">Admin</option>
                                <option value="Branch Admin">Branch Admin</option>
                                <option value="Super Admin">Super Admin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sl-section-label">Flags &amp; Settings</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Sort Order</label>
                            <input type="number" id="comp_sort" class="sl-input" value="0" min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Status</label>
                            <select id="comp_status" class="sl-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="display:flex;flex-direction:column;gap:8px;padding-top:22px;">
                            <div class="sl-toggle-row">
                                <label for="comp_taxable">Taxable</label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="comp_taxable" checked>
                                </div>
                            </div>
                            <div class="sl-toggle-row">
                                <label for="comp_mandatory">Mandatory</label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="comp_mandatory">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sl-btn sl-btn-emerald sl-btn-sm">
                    <i class="bi bi-save-fill"></i> Save Component
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ════════════════ PROFILE MODAL ══════════════════════ -->
<div class="modal fade sl-modal" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="profileForm" class="modal-content">
            <div class="modal-header navy">
                <h5 id="profileModalTitle"><i class="bi bi-person-badge-fill"></i> Assign Salary Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="prof_id">

                <!-- Section 1: Employee & Grade -->
                <div class="sl-section-label">Employee &amp; Pay Grade</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Employee <span>*</span></label>
                            <select id="prof_user_id" class="sl-select" required onchange="onEmployeeChange()">
                                <option value="">— Select Employee —</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Salary Grade <span style="color:var(--sl-muted);font-weight:400;text-transform:none">(optional)</span></label>
                            <select id="prof_grade_id" class="sl-select" onchange="applyGradeRange()">
                                <option value="">— Custom / No Grade —</option>
                            </select>
                            <div class="sl-grade-hint" id="gradeRangeHint" style="display:none">
                                <i class="bi bi-arrows-expand-vertical"></i> <span id="gradeRangeText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Basic Salary (USD) <span>*</span></label>
                            <div class="sl-input-group">
                                <span class="sl-input-pfx">$</span>
                                <input type="number" id="prof_basic" class="sl-input" min="0.01" step="0.01" placeholder="0.00" required oninput="triggerPreview()">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Effective Date <span>*</span></label>
                            <input type="date" id="prof_eff_date" class="sl-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Status</label>
                            <select id="prof_status" class="sl-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Payment & Banking -->
                <div class="sl-section-label">Payment &amp; Banking Details</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Payment Mode</label>
                            <select id="prof_pay_mode" class="sl-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Bank Name</label>
                            <input type="text" id="prof_bank_name" class="sl-input" placeholder="e.g. LBS Bank">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sl-field">
                            <label>Account Number</label>
                            <input type="text" id="prof_acct_no" class="sl-input" placeholder="e.g. 1234567890" style="font-family:var(--sl-font-m)">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Component Overrides -->
                <div class="sl-section-label">Component Overrides <span style="color:var(--sl-muted);font-weight:400;text-transform:none;letter-spacing:0">&mdash; Leave blank to use global defaults</span></div>
                <div id="overridesContainer" style="min-height:60px;">
                    <p style="font-size:.84rem;color:var(--sl-muted);margin:0">
                        <i class="bi bi-arrow-up-circle me-1"></i> Select an employee above to load salary components.
                    </p>
                </div>

                <!-- Section 4: Live Preview -->
                <div id="previewSection" style="display:none;margin-top:20px;">
                    <div class="sl-section-label">Live Salary Breakdown Preview</div>
                    <div class="sl-preview">
                        <div class="sl-preview-head">
                            <span><i class="bi bi-calculator-fill"></i> Calculated Breakdown</span>
                            <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" onclick="triggerPreview()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div class="sl-preview-kpis">
                            <div class="sl-preview-kpi">
                                <label>Gross Earnings</label>
                                <span style="color:var(--sl-em-md)" id="prevGross">$0.00</span>
                            </div>
                            <div class="sl-preview-kpi">
                                <label>Total Deductions</label>
                                <span style="color:var(--sl-red-md)" id="prevDed">$0.00</span>
                            </div>
                            <div class="sl-preview-kpi">
                                <label>Net Salary</label>
                                <span style="color:var(--sl-navy)" id="prevNet">$0.00</span>
                            </div>
                        </div>
                        <div style="padding:0 4px 4px;">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr style="background:var(--sl-page)">
                                        <th style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--sl-muted);padding:9px 14px">Component</th>
                                        <th style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--sl-muted);padding:9px 14px">Type</th>
                                        <th style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--sl-muted);padding:9px 14px;text-align:right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="prevLines"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sl-btn sl-btn-navy sl-btn-sm" id="profSaveBtn">
                    <i class="bi bi-save-fill"></i> Save Salary Profile
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ════════════════ RUN MODAL ══════════════════════════ -->
<div class="modal fade sl-modal" id="runModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="runForm" class="modal-content">
            <div class="modal-header navy">
                <h5><i class="bi bi-play-circle-fill"></i> Create Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($isSuperAdmin): ?>
                <div class="sl-field mb-3">
                    <label>Branch Scope</label>
                    <select id="run_branch_id" class="sl-select">
                        <option value="">All Branches (Global Payroll)</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Pay Period Month <span>*</span></label>
                            <select id="run_month" class="sl-select" required>
                                <?php foreach ($months as $i => $m): ?>
                                <option value="<?= $i+1 ?>" <?= ($i+1)===$currentMonth?'selected':'' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sl-field">
                            <label>Pay Year <span>*</span></label>
                            <input type="number" id="run_year" class="sl-input"
                                   value="<?= $currentYear ?>" min="2000" max="2099" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sl-field">
                            <label>Pay Date <span>*</span></label>
                            <input type="date" id="run_pay_date" class="sl-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sl-field">
                            <label>Notes / Reference</label>
                            <textarea id="run_notes" class="sl-textarea" rows="2" placeholder="Optional internal notes…"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sl-btn sl-btn-navy sl-btn-sm">
                    <i class="bi bi-play-fill"></i> Generate Payroll Draft
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ════════════════ VOID RUN MODAL ════════════════════ -->
<div class="modal fade sl-modal" id="voidRunModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form id="voidRunForm" class="modal-content">
            <div class="modal-header danger">
                <h5 style="color:var(--sl-red-md)"><i class="bi bi-x-octagon-fill"></i> Void Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="void_run_id">
                <div class="sl-void-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <p><strong>This action is irreversible.</strong> All payslips generated in this run will be voided, and the run will be locked. Employee balances will be restored.</p>
                </div>
                <div class="sl-field">
                    <label>Void Reason <span>*</span></label>
                    <textarea id="void_reason" name="void_reason" class="sl-textarea"
                              rows="3" placeholder="Provide a detailed reason (e.g. duplicate run, calculation error…)" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sl-btn sl-btn-danger sl-btn-sm">
                    <i class="bi bi-x-octagon-fill"></i> Confirm Void
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ════════════════ SLIPS LIST MODAL ══════════════════ -->
<div class="modal fade sl-modal" id="slipsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header navy">
                <h5><i class="bi bi-file-earmark-text-fill"></i> Payslips — <span id="slipsModalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:0;background:#fff;">
                <div class="table-responsive">
                    <table class="table sl-table align-middle w-100 mb-0" id="slipsTable">
                        <thead><tr>
                            <th>Employee</th><th>Role</th><th>Branch</th>
                            <th>Grade</th><th>Basic</th><th>Gross</th>
                            <th>Deductions</th><th>Net Pay</th>
                            <th>Mode</th><th>Status</th><th>Action</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════ PAYSLIP DETAIL MODAL ═══════════════ -->
<div class="modal fade sl-modal" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header navy">
                <h5><i class="bi bi-receipt-cutoff"></i> Employee Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body white" id="payslipBody" style="padding:0;"></div>
            <div class="modal-footer">
                <button type="button" class="sl-btn sl-btn-ghost sl-btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="sl-btn sl-btn-navy sl-btn-sm" onclick="printPayslip()">
                    <i class="bi bi-printer-fill"></i> Print Payslip
                </button>
            </div>
        </div>
    </div>
</div>

<div id="payslipPrintableArea" style="display:none;"></div>


<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
/* ════════════════════════════════════════════════════════════
   SALARY PAGE — JavaScript
   International payroll flow: Grades → Components → Profiles
   → Runs (Draft → Process → Approve → Disburse)
════════════════════════════════════════════════════════════ */
const IS_SA  = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const IS_BA  = <?= $isBranchAdmin ? 'true' : 'false' ?>;
const API    = '../views/models/api/salary_api.php';

const fmt = n => '$' + parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtN = n => parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const esc  = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const inits = n => String(n||'').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();

let gradesData=[], componentsData=[], employeesData=[], previewTimer=null;
let dtGrades, dtComps, dtProfiles, dtRuns, dtSlips;
const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.sl-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sl-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`)?.classList.add('active');
    document.getElementById(`panel-${tab}`)?.classList.add('active');
    // Adjust DataTables
    const maps = {grades:dtGrades, components:dtComps, profiles:dtProfiles, runs:dtRuns};
    setTimeout(() => maps[tab]?.columns.adjust(), 50);
}

// ── KPI ───────────────────────────────────────────────────────
function loadKPI() {
    $.getJSON(API + '?action=stats', function(res) {
        if (!res.success) return;
        const d = res.data;
        $('#kpiProfiled').text(d.profiled_employees || 0);
        $('#tbProfiles').text(d.profiled_employees || 0);
        $('#kpiBudget').text(fmt(d.total_basic_budget));
        $('#kpiPending').text(fmt(d.pending_net_payout));
        $('#kpiPaidMonth').text(fmt(d.paid_this_month));
    });
}

// ── GRADES ────────────────────────────────────────────────────
function loadGrades() {
    $.getJSON(API + '?action=grades_list', function(res) {
        if (!res.success) return;
        gradesData = res.data;
        $('#tbGrades').text(res.data.length);
        if (dtGrades) dtGrades.destroy();
        const tbody = res.data.map(g => `
            <tr>
                <td>
                    <div style="font-weight:700;color:var(--sl-slate)">${esc(g.name)}</div>
                    ${g.description ? `<div style="font-size:.75rem;color:var(--sl-muted)">${esc(g.description)}</div>` : ''}
                </td>
                <td><span class="sl-badge sb-navy">L${g.level}</span></td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;font-weight:600">${fmt(g.min_salary)}</td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;font-weight:600">${fmt(g.max_salary)}</td>
                <td><span class="sl-badge sb-muted">${g.employee_count||0} staff</span></td>
                <td><span class="sl-badge ${g.status==='Active'?'sb-emerald':'sb-muted'}">
                    <span class="sl-status-dot ${g.status==='Active'?'sd-active':''}"></span> ${g.status}
                </span></td>
                <td><div class="d-flex gap-1">
                    <button class="sl-tbl-act sta-amber" onclick="editGrade(${g.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="sl-tbl-act sta-red" onclick="deleteGrade(${g.id},'${esc(g.name)}')" title="Delete"><i class="bi bi-trash3"></i></button>
                </div></td>
            </tr>`).join('');
        $('#gradesTable tbody').html(tbody);
        dtGrades = $('#gradesTable').DataTable({retrieve:true,responsive:true,pageLength:10,
            language:{emptyTable:'<div style="text-align:center;padding:32px;color:var(--sl-muted)"><i class="bi bi-layers" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No pay grades defined yet.</div>'}});
    });
}
function loadGradeOptions() {
    $.getJSON(API + '?action=grades_list', function(res) {
        if (!res.success) return;
        let opts = '<option value="">— Custom / No Grade —</option>';
        res.data.filter(g=>g.status==='Active').forEach(g => {
            opts += `<option value="${g.id}" data-min="${g.min_salary}" data-max="${g.max_salary}">${esc(g.name)} (L${g.level}) — ${fmt(g.min_salary)} – ${fmt(g.max_salary)}</option>`;
        });
        $('#prof_grade_id').html(opts);
    });
}
function openGradeModal(id) {
    $('#grade_id').val(''); $('#gradeForm')[0].reset();
    $('#gradeModalTitle').html(id ? '<i class="bi bi-pencil-square me-2"></i>Edit Pay Grade' : '<i class="bi bi-layers-fill me-2"></i>Add Pay Grade');
    if (id) {
        const g = gradesData.find(x=>x.id==id);
        if (!g) return;
        $('#grade_id').val(g.id); $('#grade_name').val(g.name); $('#grade_level').val(g.level);
        $('#grade_min').val(g.min_salary); $('#grade_max').val(g.max_salary);
        $('#grade_desc').val(g.description||''); $('#grade_status').val(g.status);
    }
    new bootstrap.Modal(document.getElementById('gradeModal')).show();
}
function editGrade(id) { openGradeModal(id); }
function deleteGrade(id, name) {
    Swal.fire({title:'Delete Grade?',html:`Delete pay grade <strong>"${name}"</strong>?`,icon:'warning',
        showCancelButton:true,confirmButtonColor:'#DC2626',confirmButtonText:'Delete',
        cancelButtonText:'Cancel'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=grade_delete',{id}, res => {
            res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadGrades(), loadGradeOptions())
                        : Swal.fire('Error',res.message,'error');
        }, 'json'); });
}
$('#gradeForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#grade_id').val();
    const data = {name:$('#grade_name').val(),level:$('#grade_level').val(),
        min_salary:$('#grade_min').val(),max_salary:$('#grade_max').val(),
        description:$('#grade_desc').val(),status:$('#grade_status').val()};
    if (id) data.id = id;
    $.post(API+'?action='+(id?'grade_update':'grade_save'), data, res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('gradeModal'))?.hide();
            loadGrades(); loadGradeOptions();
            Swal.fire({icon:'success',title:'Grade saved',timer:1500,showConfirmButton:false});
        } else Swal.fire('Error',res.message,'error');
    }, 'json');
});

// ── COMPONENTS ────────────────────────────────────────────────
function loadComponents() {
    $.getJSON(API + '?action=components_list', function(res) {
        if (!res.success) return;
        componentsData = res.data;
        $('#tbComps').text(res.data.filter(c=>c.status==='Active').length);
        if (dtComps) dtComps.destroy();
        const tbody = res.data.map(c => {
            const typeClass = {Earning:'sb-earning',Deduction:'sb-deduct',Tax:'sb-tax'}[c.type] || 'sb-muted';
            const valTxt = c.calc_type==='Percentage'
                ? `${c.value}% <span style="color:var(--sl-muted);font-size:.75rem">of ${String(c.percentage_of||'').replace('_',' ')}</span>`
                : `<span style="font-family:var(--sl-font-m)">${fmt(c.value)}</span>`;
            const flags = [];
            if (c.taxable)      flags.push('<span class="sl-badge sb-navy" style="font-size:.65rem">TAX</span>');
            if (c.is_mandatory) flags.push('<span class="sl-badge sb-amber" style="font-size:.65rem">REQUIRED</span>');
            return `<tr>
                <td style="font-weight:700;color:var(--sl-slate)">${esc(c.name)}</td>
                <td><span class="sl-code">${esc(c.code)}</span></td>
                <td><span class="sl-badge ${typeClass}">${c.type}</span></td>
                <td style="font-size:.82rem">${c.calc_type==='Percentage'?'Percentage':'Fixed'}</td>
                <td>${valTxt}</td>
                <td><span class="sl-badge sb-muted">${c.applies_to}</span></td>
                <td>${flags.join(' ')}</td>
                <td><span class="sl-badge ${c.status==='Active'?'sb-emerald':'sb-muted'}">
                    <span class="sl-status-dot ${c.status==='Active'?'sd-active':''}"></span> ${c.status}
                </span></td>
                <td><div class="d-flex gap-1">
                    <button class="sl-tbl-act sta-amber" onclick="editComponent(${c.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="sl-tbl-act sta-red" onclick="deleteComponent(${c.id},'${esc(c.name)}')" title="Delete"><i class="bi bi-trash3"></i></button>
                </div></td>
            </tr>`;
        }).join('');
        $('#componentsTable tbody').html(tbody);
        dtComps = $('#componentsTable').DataTable({retrieve:true,responsive:true,pageLength:15,
            language:{emptyTable:'<div style="text-align:center;padding:32px;color:var(--sl-muted)"><i class="bi bi-list-check" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No components defined yet.</div>'}});
    });
}
function openComponentModal(id) {
    $('#comp_id').val(''); $('#componentForm')[0].reset(); $('#comp_taxable').prop('checked',true);
    $('#componentModalTitle').html(id ? '<i class="bi bi-pencil-square me-2"></i>Edit Component' : '<i class="bi bi-list-check me-2"></i>Add Salary Component');
    togglePctOf();
    if (id) {
        const c = componentsData.find(x=>x.id==id);
        if (!c) return;
        $('#comp_id').val(c.id); $('#comp_name').val(c.name); $('#comp_code').val(c.code);
        $('#comp_type').val(c.type); $('#comp_calc').val(c.calc_type); $('#comp_value').val(c.value);
        $('#comp_pct_of').val(c.percentage_of||'basic_salary'); $('#comp_taxable').prop('checked',!!c.taxable);
        $('#comp_mandatory').prop('checked',!!c.is_mandatory); $('#comp_applies_to').val(c.applies_to);
        $('#comp_sort').val(c.sort_order); $('#comp_status').val(c.status);
        togglePctOf();
    }
    new bootstrap.Modal(document.getElementById('componentModal')).show();
}
function editComponent(id) { openComponentModal(id); }
function deleteComponent(id, name) {
    Swal.fire({title:'Delete Component?',html:`Delete <strong>"${name}"</strong>?`,icon:'warning',
        showCancelButton:true,confirmButtonColor:'#DC2626',confirmButtonText:'Delete'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=component_delete',{id}, res => {
            res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadComponents())
                        : Swal.fire('Error',res.message,'error');
        }, 'json'); });
}
function togglePctOf() {
    const isPct = $('#comp_calc').val() === 'Percentage';
    $('#pctOfWrapper').toggle(isPct);
    $('#compValueSuffix').text(isPct ? '%' : '$');
}
$('#comp_name').on('input', function() {
    if (!$('#comp_id').val()) {
        $('#comp_code').val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,''));
    }
});
$('#componentForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#comp_id').val();
    const data = {name:$('#comp_name').val(),code:$('#comp_code').val(),type:$('#comp_type').val(),
        calc_type:$('#comp_calc').val(),value:$('#comp_value').val(),percentage_of:$('#comp_pct_of').val(),
        applies_to:$('#comp_applies_to').val(),sort_order:$('#comp_sort').val(),status:$('#comp_status').val()};
    if ($('#comp_taxable').is(':checked'))   data.taxable = 1;
    if ($('#comp_mandatory').is(':checked')) data.is_mandatory = 1;
    if (id) data.id = id;
    $.post(API+'?action='+(id?'component_update':'component_save'), data, res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('componentModal'))?.hide();
            loadComponents();
            Swal.fire({icon:'success',title:'Component saved',timer:1500,showConfirmButton:false});
        } else Swal.fire('Error',res.message,'error');
    }, 'json');
});

// ── PROFILES ──────────────────────────────────────────────────
function loadEmployeeOptions() {
    $.getJSON(API + '?action=employees_list', function(res) {
        if (!res.success) return;
        employeesData = res.data;
        let opts = '<option value="">— Select Employee —</option>';
        res.data.forEach(e => {
            opts += `<option value="${e.id}" data-role="${esc(e.employee_role||e.role)}" data-branch="${e.branch_id}">${esc(e.name)} (${esc(e.role)}) — ${esc(e.branch_name||'')}</option>`;
        });
        $('#prof_user_id').html(opts);
    });
}
function loadProfiles() {
    $.getJSON(API + '?action=profiles_list', function(res) {
        if (!res.success) return;
        $('#tbProfiles').text(res.data.length);
        if (dtProfiles) dtProfiles.destroy();
        const tbody = res.data.map(p => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="sl-avatar">${inits(p.employee_name)}</div>
                        <div>
                            <div style="font-weight:700;color:var(--sl-slate);font-size:.875rem">${esc(p.employee_name)}</div>
                            <div style="font-size:.72rem;color:var(--sl-muted)">${esc(p.email||'')}</div>
                        </div>
                    </div>
                </td>
                <td><span class="sl-badge sb-muted">${esc(p.employee_role)}</span></td>
                <td style="font-size:.82rem;color:var(--sl-muted)">${esc(p.branch_name||'—')}</td>
                <td>${p.grade_name
                    ? `<span class="sl-badge sb-navy">${esc(p.grade_name)} · L${p.grade_level}</span>`
                    : '<span style="color:var(--sl-subtle);font-size:.8rem;font-style:italic">Custom</span>'}</td>
                <td style="font-family:var(--sl-font-m);font-size:.88rem;font-weight:700;color:var(--sl-slate)">${fmt(p.basic_salary)}</td>
                <td style="font-size:.82rem">
                    <i class="bi bi-${p.payment_mode==='Cash'?'cash-coin text-success':p.payment_mode==='Bank Transfer'?'bank text-primary':'phone text-info'} me-1"></i>
                    ${esc(p.payment_mode)}
                </td>
                <td style="font-size:.78rem;color:var(--sl-muted);font-family:var(--sl-font-m)">${p.effective_date||'—'}</td>
                <td><span class="sl-badge ${p.status==='Active'?'sb-emerald':'sb-muted'}">
                    <span class="sl-status-dot ${p.status==='Active'?'sd-active':''}"></span> ${p.status}
                </span></td>
                <td><div class="d-flex gap-1">
                    <button class="sl-tbl-act sta-violet" onclick="viewProfileCalc(${p.id})" title="View Breakdown"><i class="bi bi-calculator-fill"></i></button>
                    <button class="sl-tbl-act sta-amber" onclick="editProfile(${p.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button class="sl-tbl-act sta-red" onclick="deleteProfile(${p.id},'${esc(p.employee_name)}')" title="Delete"><i class="bi bi-trash3"></i></button>
                </div></td>
            </tr>`).join('');
        $('#profilesTable tbody').html(tbody);
        dtProfiles = $('#profilesTable').DataTable({retrieve:true,responsive:true,pageLength:15,
            language:{emptyTable:'<div style="text-align:center;padding:32px;color:var(--sl-muted)"><i class="bi bi-person-badge" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No salary profiles assigned yet.</div>'}});
    });
}
function openProfileModal(id) {
    $('#prof_id').val(''); $('#profileForm')[0].reset(); $('#prof_user_id').prop('disabled',false);
    $('#previewSection').hide();
    $('#overridesContainer').html('<p style="font-size:.84rem;color:var(--sl-muted);margin:0"><i class="bi bi-arrow-up-circle me-1"></i>Select an employee above to load salary components.</p>');
    if (!id) { $('#prof_eff_date').val('<?= date('Y-m-d') ?>'); new bootstrap.Modal(document.getElementById('profileModal')).show(); return; }
    $.getJSON(API + '?action=profile_get&id='+id, function(res) {
        if (!res.success) { Swal.fire('Error',res.message,'error'); return; }
        const d = res.data;
        $('#prof_id').val(d.id); $('#prof_user_id').val(d.user_id).prop('disabled',true);
        $('#prof_grade_id').val(d.grade_id||''); $('#prof_basic').val(d.basic_salary);
        $('#prof_eff_date').val(d.effective_date); $('#prof_pay_mode').val(d.payment_mode);
        $('#prof_bank_name').val(d.bank_name||''); $('#prof_acct_no').val(d.account_number||'');
        $('#prof_status').val(d.status);
        if (d.grade_id) applyGradeRange();
        loadComponentOverrideInputs(d.user_id, d.overrides, function() {
            if (d.preview) showPreviewResult(d.preview);
        });
        new bootstrap.Modal(document.getElementById('profileModal')).show();
    });
}
function editProfile(id) { openProfileModal(id); }
function viewProfileCalc(id) { openProfileModal(id); }
function deleteProfile(id, name) {
    Swal.fire({title:'Delete Profile?',html:`Delete salary profile for <strong>"${name}"</strong>?`,icon:'warning',
        showCancelButton:true,confirmButtonColor:'#DC2626',confirmButtonText:'Delete'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=profile_delete',{id}, res => {
            res.success ? (Swal.fire({icon:'success',title:'Deleted',timer:1500,showConfirmButton:false}), loadProfiles(), loadKPI())
                        : Swal.fire('Error',res.message,'error');
        }, 'json'); });
}
function onEmployeeChange() {
    const uid = $('#prof_user_id').val();
    if (uid) loadComponentOverrideInputs(uid, {}, ()=>{});
    triggerPreview();
}
function loadComponentOverrideInputs(uid, existingOverrides, cb) {
    if (!uid) {
        $('#overridesContainer').html('<p style="font-size:.84rem;color:var(--sl-muted);margin:0">Select an employee to load components.</p>');
        return;
    }
    $.getJSON(API + '?action=components_list', function(res) {
        if (!res.success || !res.data.length) {
            $('#overridesContainer').html('<p style="font-size:.84rem;color:var(--sl-muted);margin:0">No active components defined.</p>');
            if(cb) cb(); return;
        }
        const active = res.data.filter(c=>c.status==='Active');
        if (!active.length) { $('#overridesContainer').html('<p style="font-size:.84rem;color:var(--sl-muted);margin:0">No active components.</p>'); if(cb) cb(); return; }
        const overrides = existingOverrides || {};
        const typeClass = {Earning:'sb-earning',Deduction:'sb-deduct',Tax:'sb-tax'};
        let html = '<div class="sl-override-grid">';
        active.forEach(c => {
            const ov = overrides[c.id] !== undefined ? overrides[c.id] : '';
            const suffix = c.calc_type === 'Percentage' ? '%' : '$';
            const placeholder = c.calc_type === 'Percentage' ? c.value+'% (global default)' : fmtN(c.value)+' (global default)';
            html += `<div class="sl-override-item">
                <label>
                    <span class="sl-badge ${typeClass[c.type]||'sb-muted'}" style="font-size:.6rem;padding:2px 6px">${c.type}</span>
                    ${esc(c.name)} <span style="font-family:var(--sl-font-m);font-size:.68rem;color:var(--sl-subtle)">${esc(c.code)}</span>
                </label>
                <div class="sl-input-group">
                    <span class="sl-input-pfx">${suffix}</span>
                    <input type="number" class="sl-input override-input" data-comp-id="${c.id}"
                           placeholder="${placeholder}" value="${ov}" min="0" step="0.01" oninput="triggerPreview()">
                </div>
            </div>`;
        });
        html += '</div>';
        $('#overridesContainer').html(html);
        if(cb) cb();
    });
}
function applyGradeRange() {
    const sel = $('#prof_grade_id option:selected');
    const min = sel.data('min'), max = sel.data('max');
    if (min !== undefined && max !== undefined && sel.val()) {
        $('#gradeRangeText').text(`Pay range: ${fmt(min)} – ${fmt(max)}`);
        $('#gradeRangeHint').show();
    } else {
        $('#gradeRangeHint').hide();
    }
}
function triggerPreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(doPreview, 600);
}
function doPreview() {
    const uid = $('#prof_user_id').val();
    const basic = parseFloat($('#prof_basic').val())||0;
    if (!uid || basic <= 0) { $('#previewSection').hide(); return; }
    const overrides = {};
    $('.override-input').each(function() {
        const val = $(this).val();
        if (val !== '') overrides[$(this).data('comp-id')] = val;
    });
    $.post(API+'?action=preview_salary', {user_id:uid,basic_salary:basic,overrides:JSON.stringify(overrides)}, res => {
        if (res.success) showPreviewResult(res);
    }, 'json');
}
function showPreviewResult(res) {
    if (!res) return;
    $('#prevGross').text(fmt(res.gross));
    $('#prevDed').text(fmt(res.deductions));
    $('#prevNet').text(fmt(res.net));
    const lines = (res.lines||[]).map(l => {
        const isEarn = l.component_type === 'Earning';
        const cls = isEarn ? 'var(--sl-em-md)' : 'var(--sl-red-md)';
        const sign = isEarn ? '+' : '–';
        return `<tr>
            <td style="padding:8px 14px;font-size:.84rem">${esc(l.component_name)} <span class="sl-code" style="font-size:.72rem">${esc(l.component_code)}</span></td>
            <td style="padding:8px 14px"><span class="sl-badge ${isEarn?'sb-earning':'sb-deduct'}" style="font-size:.68rem">${l.component_type}</span></td>
            <td style="padding:8px 14px;text-align:right;font-weight:700;color:${cls};font-family:var(--sl-font-m)">${sign}${fmt(l.amount)}</td>
        </tr>`;
    }).join('');
    $('#prevLines').html(lines || '<tr><td colspan="3" style="text-align:center;padding:16px;color:var(--sl-muted)">No components</td></tr>');
    $('#previewSection').show();
}
$('#profileForm').on('submit', function(e) {
    e.preventDefault();
    const id = $('#prof_id').val();
    const overrides = {};
    $('.override-input').each(function() {
        const val = $(this).val();
        if (val !== '') overrides[$(this).data('comp-id')] = val;
    });
    const data = {
        user_id:$('#prof_user_id').val(), grade_id:$('#prof_grade_id').val()||'',
        basic_salary:$('#prof_basic').val(), effective_date:$('#prof_eff_date').val(),
        payment_mode:$('#prof_pay_mode').val(), bank_name:$('#prof_bank_name').val(),
        account_number:$('#prof_acct_no').val(), status:$('#prof_status').val(),
        overrides:JSON.stringify(overrides)
    };
    if (id) data.id = id;
    const btn = $('#profSaveBtn').prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving…');
    $.post(API+'?action='+(id?'profile_update':'profile_save'), data, res => {
        btn.prop('disabled',false).html('<i class="bi bi-save-fill me-1"></i>Save Salary Profile');
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('profileModal'))?.hide();
            $('#prof_user_id').prop('disabled',false);
            loadProfiles(); loadKPI(); loadEmployeeOptions();
            Swal.fire({icon:'success',title:'Salary profile saved',timer:1500,showConfirmButton:false});
        } else Swal.fire('Error',res.message,'error');
    }, 'json');
});
$('#profileModal').on('hidden.bs.modal', function() { $('#prof_user_id').prop('disabled',false); });

// ── PAYROLL RUNS ──────────────────────────────────────────────
function loadRuns() {
    $.getJSON(API + '?action=runs_list', function(res) {
        if (!res.success) return;
        $('#tbRuns').text(res.data.length);
        $('#runsCount').text(res.data.length + ' run' + (res.data.length!==1?'s':''));
        if (dtRuns) dtRuns.destroy();
        const statusBadge = {
            Draft:      '<span class="sl-badge sb-muted"><span class="sl-status-dot"></span> Draft</span>',
            Processed:  '<span class="sl-badge sb-amber"><span class="sl-status-dot sd-pending"></span> Processed</span>',
            Paid:       '<span class="sl-badge sb-navy"><span class="sl-status-dot sd-paid"></span> Paid</span>',
            Voided:     '<span class="sl-badge sb-red"><span class="sl-status-dot sd-voided"></span> Voided</span>',
        };
        const tbody = res.data.map(r => {
            const period = MONTHS[r.pay_period_month] + ' ' + r.pay_period_year;
            const branch = r.branch_name
                ? `<span class="sl-badge sb-muted"><i class="bi bi-building me-1"></i>${esc(r.branch_name)}</span>`
                : '<span class="sl-badge sb-navy"><i class="bi bi-globe me-1"></i>All Branches</span>';
            let actions = `<button class="sl-tbl-act sta-navy" onclick="viewSlips(${r.id},'${esc(period)}')" title="View Payslips"><i class="bi bi-file-earmark-text-fill"></i></button>`;
            if (r.status==='Draft')     actions += `<button class="sl-tbl-act sta-emerald" onclick="processRun(${r.id})" title="Process & Generate Payslips"><i class="bi bi-play-circle-fill"></i></button>`;
            if (r.status==='Processed' && (IS_SA||IS_BA)) actions += `<button class="sl-tbl-act sta-violet" onclick="markPaid(${r.id})" title="Mark as Disbursed"><i class="bi bi-check-circle-fill"></i></button>`;
            if (r.status!=='Voided' && IS_SA) actions += `<button class="sl-tbl-act sta-red" onclick="voidRun(${r.id})" title="Void Run"><i class="bi bi-x-octagon-fill"></i></button>`;
            return `<tr${r.status==='Voided'?' class="voided"':''}>
                <td>
                    <div class="sl-period">${period}</div>
                    <div class="sl-period-date"><i class="bi bi-calendar3 me-1"></i>${r.pay_date||'—'}</div>
                </td>
                <td>${branch}</td>
                <td><span class="sl-badge sb-navy"><i class="bi bi-people-fill me-1"></i>${r.slip_count||0}</span></td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;font-weight:600;color:var(--sl-slate)">${fmt(r.total_gross)}</td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;font-weight:600;color:var(--sl-red-md)">(${fmt(r.total_deductions)})</td>
                <td style="font-family:var(--sl-font-d);font-size:.96rem;font-weight:800;color:var(--sl-em-md)">${fmt(r.total_net)}</td>
                <td>${statusBadge[r.status] || statusBadge.Draft}</td>
                <td><div class="d-flex gap-1 justify-content-center">${actions}</div></td>
            </tr>`;
        }).join('');
        $('#runsTable tbody').html(tbody);
        dtRuns = $('#runsTable').DataTable({retrieve:true,responsive:true,pageLength:12,order:[[0,'desc']],
            language:{emptyTable:'<div style="text-align:center;padding:32px;color:var(--sl-muted)"><i class="bi bi-play-circle" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No payroll runs yet. Click "New Payroll Run" to start.</div>'}});
    });
}
function openRunModal() {
    $('#runForm')[0].reset();
    $('#run_month').val('<?= $currentMonth ?>');
    $('#run_year').val('<?= $currentYear ?>');
    $('#run_pay_date').val('<?= date('Y-m-d') ?>');
    new bootstrap.Modal(document.getElementById('runModal')).show();
}
$('#runForm').on('submit', function(e) {
    e.preventDefault();
    const data = {month:$('#run_month').val(),year:$('#run_year').val(),pay_date:$('#run_pay_date').val(),notes:$('#run_notes').val()};
    <?php if ($isSuperAdmin): ?>data.branch_id = $('#run_branch_id').val();<?php endif; ?>
    $.post(API+'?action=run_create', data, res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('runModal'))?.hide();
            loadRuns();
            Swal.fire({icon:'success',title:'Payroll Draft Created',text:'You can now process it to generate payslips.',timer:2500,showConfirmButton:false});
        } else Swal.fire('Error',res.message,'error');
    }, 'json');
});
function processRun(runId) {
    Swal.fire({title:'Process Payroll?',
        html:'This will generate payslips for all active salary profiles.<br><br><strong>This step can be reviewed before marking as paid.</strong>',
        icon:'question',showCancelButton:true,confirmButtonColor:'#059669',confirmButtonText:'<i class="bi bi-play-fill me-1"></i>Process Now'})
    .then(r => { if (!r.isConfirmed) return;
        Swal.fire({title:'Processing payroll…',html:'Calculating salary components for all employees.',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        $.post(API+'?action=run_process',{run_id:runId}, res => {
            Swal.close();
            if (res.success) {
                Swal.fire({icon:'success',title:'Payroll Processed!',
                    html:`Generated <strong>${res.slip_count}</strong> payslips.<br>Total Net Pay: <strong>${fmt(res.total_net)}</strong>`,
                    confirmButtonText:'View Payslips'}).then(r2 => { if(r2.isConfirmed) viewSlips(runId,''); });
                loadRuns(); loadKPI();
            } else Swal.fire('Error',res.message,'error');
        }, 'json');
    });
}
function markPaid(runId) {
    Swal.fire({title:'Confirm Disbursement',
        html:'Mark all payslips in this run as <strong>Paid</strong>?<br>This indicates salaries have been disbursed to employees.',
        icon:'question',showCancelButton:true,confirmButtonColor:'#7C3AED',confirmButtonText:'<i class="bi bi-check-circle-fill me-1"></i>Mark as Paid'})
    .then(r => { if (!r.isConfirmed) return;
        $.post(API+'?action=run_mark_paid',{run_id:runId}, res => {
            res.success ? (Swal.fire({icon:'success',title:'Marked as Paid',timer:2000,showConfirmButton:false}), loadRuns(), loadKPI())
                        : Swal.fire('Error',res.message,'error');
        }, 'json');
    });
}
function voidRun(runId) {
    $('#void_run_id').val(runId); $('#void_reason').val('');
    new bootstrap.Modal(document.getElementById('voidRunModal')).show();
}
$('#voidRunForm').on('submit', function(e) {
    e.preventDefault();
    $.post(API+'?action=run_void',{run_id:$('#void_run_id').val(),void_reason:$('#void_reason').val()}, res => {
        bootstrap.Modal.getInstance(document.getElementById('voidRunModal'))?.hide();
        if (res.success) {
            loadRuns(); loadKPI();
            Swal.fire({icon:'success',title:'Run Voided',timer:1500,showConfirmButton:false});
        } else Swal.fire('Error',res.message,'error');
    }, 'json');
});

// ── PAYSLIPS ──────────────────────────────────────────────────
function viewSlips(runId, period) {
    $('#slipsModalTitle').text(period);
    $.getJSON(API + '?action=slips_list&run_id='+runId, function(res) {
        if (!res.success) return;
        const statusBadge = {Pending:'sb-amber',Paid:'sb-navy',Voided:'sb-red'};
        const tbody = res.data.map(s => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:9px">
                        <div class="sl-avatar" style="width:32px;height:32px;font-size:.66rem">${inits(s.employee_name)}</div>
                        <div>
                            <div style="font-weight:700;font-size:.84rem;color:var(--sl-slate)">${esc(s.employee_name)}</div>
                            <div style="font-size:.72rem;color:var(--sl-muted)">${esc(s.email||'')}</div>
                        </div>
                    </div>
                </td>
                <td><span class="sl-badge sb-muted" style="font-size:.7rem">${esc(s.employee_role)}</span></td>
                <td style="font-size:.8rem;color:var(--sl-muted)">${esc(s.branch_name||'—')}</td>
                <td>${s.grade_name?`<span class="sl-badge sb-navy" style="font-size:.7rem">${esc(s.grade_name)}</span>`:'<span style="color:var(--sl-subtle);font-size:.78rem">—</span>'}</td>
                <td style="font-family:var(--sl-font-m);font-size:.82rem;color:var(--sl-muted)">${fmt(s.basic_salary)}</td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;font-weight:600">${fmt(s.gross_salary)}</td>
                <td style="font-family:var(--sl-font-m);font-size:.84rem;color:var(--sl-red-md)">(${fmt(s.total_deductions)})</td>
                <td style="font-family:var(--sl-font-d);font-size:.92rem;font-weight:800;color:var(--sl-em-md)">${fmt(s.net_salary)}</td>
                <td style="font-size:.8rem">${esc(s.payment_mode)}</td>
                <td><span class="sl-badge ${statusBadge[s.status]||'sb-muted'} sl-run-status">
                    <span class="sl-status-dot ${s.status==='Paid'?'sd-paid':s.status==='Pending'?'sd-pending':s.status==='Voided'?'sd-voided':''}"></span>
                    ${s.status}
                </span></td>
                <td><button class="sl-tbl-act sta-navy" onclick="viewPayslip(${s.id})" title="View Payslip"><i class="bi bi-receipt"></i></button></td>
            </tr>`).join('');
        if (dtSlips) dtSlips.destroy();
        $('#slipsTable tbody').html(tbody);
        dtSlips = $('#slipsTable').DataTable({retrieve:true,responsive:true,pageLength:25,
            language:{emptyTable:'No payslips in this run.'}});
        new bootstrap.Modal(document.getElementById('slipsModal')).show();
    });
}

// ── PAYSLIP DETAIL ────────────────────────────────────────────
function viewPayslip(slipId) {
    $('#payslipBody').html('<div style="text-align:center;padding:40px"><div class="spinner-border text-primary"></div></div>');
    new bootstrap.Modal(document.getElementById('payslipModal')).show();

    $.getJSON(API + '?action=slip_detail&slip_id='+slipId, function(res) {
        if (!res.success) { $('#payslipBody').html(`<div style="text-align:center;padding:40px;color:var(--sl-red-md)">${esc(res.message)}</div>`); return; }
        const d = res.data;
        const earnings   = (d.lines||[]).filter(l=>l.component_type==='Earning');
        const deductions = (d.lines||[]).filter(l=>l.component_type!=='Earning');
        const printed    = new Date().toLocaleString('en-US',{dateStyle:'medium',timeStyle:'short'});

        const earningsRows = earnings.map(l =>
            `<tr><td style="padding:7px 14px;font-size:.84rem">${esc(l.component_name)}</td>
             <td style="padding:7px 14px;font-family:var(--sl-font-m);font-size:.84rem;font-weight:600;text-align:right;color:#059669">+${fmt(l.amount)}</td></tr>`
        ).join('');
        const deductionRows = deductions.map(l =>
            `<tr><td style="padding:7px 14px;font-size:.84rem">${esc(l.component_name)}</td>
             <td style="padding:7px 14px;font-family:var(--sl-font-m);font-size:.84rem;font-weight:600;text-align:right;color:#DC2626">(${fmt(l.amount)})</td></tr>`
        ).join('');

        const html = `
<div style="font-family:'IBM Plex Sans',sans-serif;background:#fff;border-radius:12px;overflow:hidden">
    <!-- Header -->
    <div style="background:#0F1C3F;color:#fff;padding:24px 28px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="display:flex;align-items:center;gap:12px">
                <img src="../../assets/img/logo.svg" alt="Logo" width="36" height="45" onerror="this.style.display='none'">
                <div>
                    <div style="font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;letter-spacing:-.02em">Shining Bright</div>
                    <div style="font-size:.62rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.55)">Vocational School</div>
                    <div style="font-size:.75rem;color:rgba(255,255,255,.6);margin-top:2px">${esc(d.branch_name||'')}</div>
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:.62rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.45)">Pay Slip</div>
                <div style="font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;margin:3px 0">PS-${String(d.id).padStart(5,'0')}</div>
                <div style="display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:20px;padding:3px 12px;font-size:.78rem;font-weight:600">
                    ${MONTHS[d.pay_period_month]} ${d.pay_period_year}
                </div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.45);margin-top:5px">Pay Date: ${d.pay_date||'—'}</div>
            </div>
        </div>
    </div>

    <!-- Employee + Payment info -->
    <div style="padding:20px 28px;border-bottom:1px solid #E2E8F0;display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#94A3B8;margin-bottom:8px">Employee Details</div>
            <div style="font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:#0F172A;margin-bottom:3px">${esc(d.employee_name)}</div>
            ${d.teacher_id ? `<div style="font-family:'IBM Plex Mono',monospace;font-size:.78rem;color:#64748B;margin-bottom:3px">ID: ${esc(d.teacher_id)}</div>` : ''}
            <div style="font-size:.82rem;color:#64748B;margin-bottom:5px">${esc(d.email||'')}</div>
            <span style="background:#EEF2FF;color:#1A2E5A;border:1px solid #C7D2FE;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700">${esc(d.employee_role)}</span>
            ${d.grade_name ? ` <span style="background:#F5F3FF;color:#7C3AED;border:1px solid #DDD6FE;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700">${esc(d.grade_name)} · L${d.grade_level}</span>` : ''}
        </div>
        <div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#94A3B8;margin-bottom:8px">Payment Details</div>
            <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                <tr><td style="color:#64748B;padding:3px 0;width:38%">Method</td><td style="font-weight:600;color:#0F172A">${esc(d.payment_mode)}</td></tr>
                <tr><td style="color:#64748B;padding:3px 0">Bank</td><td style="font-weight:600;color:#0F172A">${esc(d.bank_name||'—')}</td></tr>
                <tr><td style="color:#64748B;padding:3px 0">Account</td><td style="font-family:'IBM Plex Mono',monospace;font-size:.8rem;font-weight:600;color:#0F172A">${esc(d.account_number||'—')}</td></tr>
                <tr><td style="color:#64748B;padding:3px 0">Status</td><td>
                    <span style="background:${d.status==='Paid'?'#EEF2FF':d.status==='Pending'?'#FFFBEB':'#FEF2F2'};
                           color:${d.status==='Paid'?'#1A2E5A':d.status==='Pending'?'#D97706':'#DC2626'};
                           border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700">
                        ${esc(d.status)}
                    </span>
                </td></tr>
            </table>
        </div>
    </div>

    <!-- Earnings & Deductions -->
    <div style="padding:20px 28px;display:grid;grid-template-columns:1fr 1fr;gap:20px;border-bottom:1px solid #E2E8F0">
        <!-- Earnings -->
        <div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#059669;margin-bottom:8px">Earnings</div>
            <table style="width:100%;border-collapse:collapse;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden">
                <tbody>
                    <tr style="background:#F8FAFC">
                        <td style="padding:7px 14px;font-size:.84rem;font-weight:600;color:#475569">Basic Salary</td>
                        <td style="padding:7px 14px;font-family:'IBM Plex Mono',monospace;font-size:.84rem;font-weight:700;text-align:right;color:#059669">+${fmt(d.basic_salary)}</td>
                    </tr>
                    ${earningsRows}
                </tbody>
                <tfoot>
                    <tr style="background:#ECFDF5;border-top:2px solid #A7F3D0">
                        <td style="padding:10px 14px;font-size:.82rem;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.4px">Gross</td>
                        <td style="padding:10px 14px;font-family:'IBM Plex Mono',monospace;font-size:.96rem;font-weight:800;text-align:right;color:#059669">${fmt(d.gross_salary)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <!-- Deductions -->
        <div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#DC2626;margin-bottom:8px">Deductions</div>
            <table style="width:100%;border-collapse:collapse;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden">
                <tbody>
                    ${deductionRows || '<tr><td colspan="2" style="padding:12px 14px;text-align:center;color:#94A3B8;font-size:.82rem;font-style:italic">No deductions</td></tr>'}
                </tbody>
                <tfoot>
                    <tr style="background:#FEF2F2;border-top:2px solid #FECACA">
                        <td style="padding:10px 14px;font-size:.82rem;font-weight:700;color:#DC2626;text-transform:uppercase;letter-spacing:.4px">Total</td>
                        <td style="padding:10px 14px;font-family:'IBM Plex Mono',monospace;font-size:.96rem;font-weight:800;text-align:right;color:#DC2626">(${fmt(d.total_deductions)})</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Net Pay -->
    <div style="padding:20px 28px;text-align:center;background:#EEF2FF;border-bottom:1px solid #C7D2FE">
        <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#1A2E5A;margin-bottom:8px">Net Salary Payable</div>
        <div style="font-family:'Sora',sans-serif;font-size:2.2rem;font-weight:800;color:#0F1C3F;letter-spacing:-.03em">${fmt(d.net_salary)}</div>
    </div>

    <!-- Footer -->
    <div style="padding:14px 28px;background:#F8FAFC;text-align:center;font-size:.72rem;color:#94A3B8;border-top:1px solid #E2E8F0">
        <i class="bi bi-shield-fill-check me-1" style="color:#059669"></i>
        System-generated payslip — PS-${String(d.id).padStart(5,'0')} · Printed: ${printed}
    </div>
</div>`;
        $('#payslipBody').html(html);
    });
}

function printPayslip() {
    const body = document.getElementById('payslipBody');
    if (!body) return;
    const area  = document.getElementById('payslipPrintableArea');
    area.innerHTML = body.innerHTML;
    const style = document.createElement('style');
    style.textContent = `@media print{body>*:not(#payslipPrintableArea){display:none!important}#payslipPrintableArea{display:block!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}`;
    area.appendChild(style);
    area.style.display = 'block';
    window.print();
    setTimeout(() => { area.style.display = 'none'; area.innerHTML = ''; }, 500);
}

// ── Bootstrap ─────────────────────────────────────────────────
$(function() {
    loadKPI();
    <?php if ($isSuperAdmin): ?>
    loadGrades();
    loadComponents();
    <?php endif; ?>
    loadProfiles();
    loadRuns();
    loadEmployeeOptions();
    loadGradeOptions();
});
</script>
</body>
</html>