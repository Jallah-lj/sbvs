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

$role          = $_SESSION['role'] ?? '';
$branchId      = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin       = ($role === 'Admin');

if (!in_array($role, ['Super Admin', 'Branch Admin', 'Admin'])) {
    header("Location: dashboard.php");
    exit;
}

$branchName = '';
if (!$isSuperAdmin && $branchId) {
    $bq = $db->prepare("SELECT name FROM branches WHERE id=?");
    $bq->execute([$branchId]);
    $branchName = $bq->fetchColumn() ?: '';
}

$branches = [];
if ($isSuperAdmin) {
    $branches = $db->query("SELECT id, name FROM branches WHERE status='Active' ORDER BY name")
                   ->fetchAll(PDO::FETCH_ASSOC);
}

$userName = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
$userRole = htmlspecialchars($role);

$paymentMethods = [
    'Cash', 'Bank Transfer', 'Mobile Money - Orange', 'Mobile Money - MTN',
    'Check', 'Debit Card', 'Credit Card'
];

$pageTitle  = 'Payments';
$activePage = 'payments.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ══════════════════════════════════════════════════════════════
   PAYMENTS PAGE — Clean Financial Dashboard
   Font: Plus Jakarta Sans — structured, professional, warm
══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

:root {
    --p-blue:     #1E40AF;
    --p-blue-md:  #2563EB;
    --p-blue-lt:  #EFF6FF;
    --p-blue-bd:  #BFDBFE;
    --p-green:    #065F46;
    --p-green-md: #059669;
    --p-green-lt: #ECFDF5;
    --p-amber:    #92400E;
    --p-amber-md: #D97706;
    --p-amber-lt: #FFFBEB;
    --p-amber-bd: #FDE68A;
    --p-red:      #991B1B;
    --p-red-md:   #DC2626;
    --p-red-lt:   #FEF2F2;
    --p-slate:    #0F172A;
    --p-muted:    #64748B;
    --p-subtle:   #94A3B8;
    --p-surface:  #FFFFFF;
    --p-page:     #F8FAFC;
    --p-border:   #E2E8F0;
    --p-border2:  #CBD5E1;
    --p-font:     'Plus Jakarta Sans', system-ui, sans-serif;
    --p-shadow:   0 1px 2px rgba(0,0,0,.04), 0 4px 12px rgba(0,0,0,.06);
    --p-shadow-md:0 4px 6px rgba(0,0,0,.04), 0 10px 30px rgba(0,0,0,.08);
    --p-r:        10px;
    --p-rlg:      16px;
}

/* Base */
.pay-wrap, .pay-wrap * { font-family: var(--p-font); box-sizing: border-box; }
.pay-wrap input, .pay-wrap select, .pay-wrap textarea { font-family: var(--p-font); }

/* ── Page Header ── */
.pay-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; flex-wrap: wrap;
    gap: 16px; margin-bottom: 28px;
}
.pay-header h2 {
    font-size: 1.55rem; font-weight: 800;
    color: var(--p-slate); letter-spacing: -.03em; margin: 0 0 5px;
}
.pay-header p { font-size: .875rem; color: var(--p-muted); margin: 0; }
.pay-branch-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--p-blue-lt); color: var(--p-blue-md);
    border: 1px solid var(--p-blue-bd);
    border-radius: 20px; padding: 3px 10px;
    font-size: .72rem; font-weight: 700;
    letter-spacing: .3px;
}
.pay-header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

/* ── Buttons ── */
.pay-btn {
    display: inline-flex; align-items: center; gap: 7px;
    height: 40px; padding: 0 18px;
    border: none; border-radius: var(--p-r);
    font-family: var(--p-font); font-size: .85rem; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: all .15s; white-space: nowrap;
}
.pay-btn-primary {
    background: var(--p-blue-md); color: #fff;
    box-shadow: 0 2px 8px rgba(37,99,235,.3);
}
.pay-btn-primary:hover { background: var(--p-blue); box-shadow: 0 4px 14px rgba(37,99,235,.35); color: #fff; }
.pay-btn-ghost {
    background: var(--p-surface); color: var(--p-muted);
    border: 1.5px solid var(--p-border2);
}
.pay-btn-ghost:hover { background: #F1F5F9; color: var(--p-slate); }
.pay-btn-sm { height: 32px; padding: 0 12px; font-size: .78rem; }

/* ── KPI Cards ── */
.pay-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 24px;
}
.pay-kpi {
    background: var(--p-surface);
    border: 1px solid var(--p-border);
    border-radius: var(--p-rlg);
    padding: 20px 22px;
    box-shadow: var(--p-shadow);
    transition: box-shadow .2s, transform .2s;
    position: relative; overflow: hidden;
}
.pay-kpi:hover { box-shadow: var(--p-shadow-md); transform: translateY(-1px); }
.pay-kpi::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--p-rlg) var(--p-rlg) 0 0;
}
.pay-kpi.blue::before  { background: var(--p-blue-md); }
.pay-kpi.green::before { background: var(--p-green-md); }
.pay-kpi.amber::before { background: var(--p-amber-md); }
.pay-kpi.red::before   { background: var(--p-red-md); }

.pay-kpi-icon {
    width: 44px; height: 44px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 14px;
}
.pay-kpi.blue  .pay-kpi-icon { background: var(--p-blue-lt);  color: var(--p-blue-md);  }
.pay-kpi.green .pay-kpi-icon { background: var(--p-green-lt); color: var(--p-green-md); }
.pay-kpi.amber .pay-kpi-icon { background: var(--p-amber-lt); color: var(--p-amber-md); }
.pay-kpi.red   .pay-kpi-icon { background: var(--p-red-lt);   color: var(--p-red-md);   }

.pay-kpi-val {
    font-size: 1.6rem; font-weight: 800;
    color: var(--p-slate); letter-spacing: -.02em;
    line-height: 1; margin-bottom: 4px;
}
.pay-kpi-lbl {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--p-muted);
}

/* ── Filter Panel ── */
.pay-filter-panel {
    background: var(--p-surface);
    border: 1px solid var(--p-border);
    border-radius: var(--p-rlg);
    padding: 18px 22px;
    box-shadow: var(--p-shadow);
    margin-bottom: 20px;
}
.pay-filter-label {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--p-muted); display: block; margin-bottom: 5px;
}
.pay-filter-input, .pay-filter-select {
    width: 100%; height: 36px; padding: 0 11px;
    border: 1.5px solid var(--p-border2);
    border-radius: 8px;
    font-family: var(--p-font); font-size: .84rem;
    color: var(--p-slate); background: #fff; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.pay-filter-input:focus, .pay-filter-select:focus {
    border-color: var(--p-blue-md);
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.pay-filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px; cursor: pointer;
}
.pay-filter-search-wrap {
    display: flex; gap: 8px;
}
.pay-filter-search-wrap .pay-filter-input { flex: 1; }
.pay-filter-btn {
    height: 36px; padding: 0 14px;
    background: var(--p-blue-md); color: #fff;
    border: none; border-radius: 8px;
    font-family: var(--p-font); font-size: .84rem;
    font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 5px;
    transition: background .15s;
    white-space: nowrap;
}
.pay-filter-btn:hover { background: var(--p-blue); }

/* ── Table Card ── */
.pay-table-card {
    background: var(--p-surface);
    border: 1px solid var(--p-border);
    border-radius: var(--p-rlg);
    box-shadow: var(--p-shadow);
    overflow: hidden;
}
.pay-table-card .card-head {
    padding: 16px 22px;
    border-bottom: 1px solid var(--p-border);
    display: flex; align-items: center;
    justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.pay-table-card .card-head h6 {
    font-size: .9rem; font-weight: 700;
    color: var(--p-slate); margin: 0;
    display: flex; align-items: center; gap: 7px;
}
.pay-table-card .card-head h6 i { color: var(--p-blue-md); }

/* Table overrides */
#paymentsTable thead th {
    background: #F8FAFC;
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--p-muted);
    padding: 11px 14px;
    border-bottom: 1px solid var(--p-border);
    white-space: nowrap;
}
#paymentsTable tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #F1F5F9;
    font-size: .855rem;
}
#paymentsTable tbody tr:last-child td { border-bottom: none; }
#paymentsTable tbody tr:hover td { background: #F8FBFF; }
#paymentsTable tbody tr.void-row td { opacity: .55; }
#paymentsTable tbody tr.void-row:hover td { background: #FEF2F2; opacity: .75; }

/* DataTables cosmetics */
.dataTables_wrapper .dataTables_filter input {
    border: 1.5px solid var(--p-border2) !important;
    border-radius: 8px !important; height: 34px !important;
    font-family: var(--p-font) !important; font-size: .84rem !important;
    box-shadow: none !important; padding: 0 10px !important;
    outline: none !important;
}
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--p-blue-md) !important; }
.dataTables_wrapper .dataTables_length select {
    border: 1.5px solid var(--p-border2) !important;
    border-radius: 8px !important; height: 34px !important;
    font-family: var(--p-font) !important; font-size: .84rem !important;
    padding: 0 28px 0 10px !important;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate { font-size: .82rem; color: var(--p-muted); }
.dataTables_wrapper .paginate_button { border-radius: 7px !important; }
.dataTables_wrapper .paginate_button.current,
.dataTables_wrapper .paginate_button.current:hover {
    background: var(--p-blue-md) !important;
    border-color: var(--p-blue-md) !important;
    color: #fff !important;
}

/* ── Inline badges ── */
.p-badge {
    display: inline-flex; align-items: center; gap: 4px;
    border-radius: 20px; padding: 3px 10px;
    font-size: .71rem; font-weight: 700; letter-spacing: .3px;
    white-space: nowrap;
}
.p-badge-success { background: var(--p-green-lt); color: var(--p-green-md); }
.p-badge-warning { background: var(--p-amber-lt); color: var(--p-amber-md); }
.p-badge-danger  { background: var(--p-red-lt);   color: var(--p-red-md);   }
.p-badge-info    { background: var(--p-blue-lt);  color: var(--p-blue-md);  }

/* ── Action buttons in table ── */
.tbl-action {
    width: 30px; height: 30px;
    border: none; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; cursor: pointer;
    transition: all .15s;
}
.tbl-action-view { background: var(--p-blue-lt); color: var(--p-blue-md); }
.tbl-action-view:hover { background: var(--p-blue-md); color: #fff; }
.tbl-action-void { background: var(--p-red-lt); color: var(--p-red-md); }
.tbl-action-void:hover { background: var(--p-red-md); color: #fff; }

/* ── Receipt number ── */
.receipt-code {
    font-family: 'Courier New', monospace;
    font-size: .8rem; font-weight: 700;
    color: var(--p-blue-md);
    background: var(--p-blue-lt);
    border-radius: 5px; padding: 2px 7px;
}

/* ── Student cell ── */
.student-name { font-weight: 600; color: var(--p-slate); font-size: .855rem; }
.student-code {
    font-family: monospace; font-size: .72rem;
    color: var(--p-muted); margin-top: 1px;
}

/* ── Modals ── */
.pay-modal .modal-content {
    border: none; border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
}
.pay-modal .modal-header {
    padding: 18px 24px 14px;
    border-bottom: 1px solid var(--p-border);
}
.pay-modal .modal-header.blue  { background: var(--p-blue-md); color: #fff; }
.pay-modal .modal-header.blue  .btn-close { filter: invert(1); }
.pay-modal .modal-header.amber { background: #fffbeb; border-bottom: 1px solid var(--p-amber-bd); }
.pay-modal .modal-header h5 {
    font-family: var(--p-font); font-size: 1rem;
    font-weight: 700; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.pay-modal .modal-body { background: #F8FAFC; padding: 22px 24px; }
.pay-modal .modal-footer {
    background: var(--p-surface);
    border-top: 1px solid var(--p-border);
    padding: 14px 24px;
}

/* ── Modal form elements ── */
.modal-field label {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--p-muted); display: block; margin-bottom: 5px;
}
.modal-field label span { color: var(--p-red-md); }
.modal-input, .modal-select, .modal-textarea {
    width: 100%; padding: 0 12px;
    height: 40px;
    border: 1.5px solid var(--p-border2);
    border-radius: 8px;
    font-family: var(--p-font); font-size: .875rem;
    color: var(--p-slate); background: #fff; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.modal-input:focus, .modal-select:focus, .modal-textarea:focus {
    border-color: var(--p-blue-md);
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.modal-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    padding-right: 30px; cursor: pointer;
}
.modal-textarea { height: auto; padding: 10px 12px; resize: vertical; }
.modal-input-group {
    display: flex; align-items: center;
    border: 1.5px solid var(--p-border2); border-radius: 8px;
    overflow: hidden; background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.modal-input-group:focus-within {
    border-color: var(--p-blue-md);
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.modal-input-pfx {
    padding: 0 11px; height: 40px;
    display: flex; align-items: center;
    font-size: .85rem; font-weight: 600;
    color: var(--p-muted);
    border-right: 1.5px solid var(--p-border);
    background: #F8FAFC; flex-shrink: 0;
}
.modal-input-group .modal-input { border: none; box-shadow: none; border-radius: 0; padding-left: 10px; }

/* ── Student autocomplete ── */
.p-autocomplete { position: relative; }
.p-ac-results {
    position: absolute; top: 100%; left: 0; right: 0;
    background: var(--p-surface);
    border: 1.5px solid var(--p-border2);
    border-radius: 9px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    z-index: 1000; max-height: 260px;
    overflow-y: auto; margin-top: 4px;
    display: none;
}
.p-ac-item {
    padding: 10px 14px; cursor: pointer;
    display: flex; align-items: center; gap: 10px;
    border-bottom: 1px solid #F1F5F9;
    font-size: .875rem; transition: background .1s;
}
.p-ac-item:last-child { border-bottom: none; }
.p-ac-item:hover { background: var(--p-blue-lt); }
.p-ac-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--p-blue-lt); color: var(--p-blue-md);
    font-size: .72rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.p-ac-name { font-weight: 600; color: var(--p-slate); }
.p-ac-meta { font-size: .75rem; color: var(--p-muted); margin-top: 1px; }
.p-ac-branch { font-size: .72rem; color: var(--p-subtle); margin-left: auto; }

/* ── Balance info card ── */
.p-balance-card {
    background: var(--p-surface);
    border: 1.5px solid var(--p-border2);
    border-radius: 10px; overflow: hidden;
    display: none; margin-top: 2px;
}
.p-balance-card.show { display: block; }
.p-balance-card.cleared { border-color: #a7f3d0; }
.p-balance-grid {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
}
.p-balance-cell {
    padding: 14px 16px;
    border-right: 1px solid var(--p-border);
    text-align: center;
}
.p-balance-cell:last-child { border-right: none; }
.p-balance-cell label {
    font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--p-muted); display: block; margin-bottom: 4px;
}
.p-balance-cell span {
    font-size: 1.05rem; font-weight: 800; color: var(--p-slate);
}
.p-balance-cell.danger  span { color: var(--p-red-md);   }
.p-balance-cell.success span { color: var(--p-green-md); }

/* ── Payment type hint ── */
.p-pay-hint { font-size: .78rem; margin-top: 5px; display: flex; align-items: center; gap: 5px; }
.p-pay-hint.full     { color: var(--p-green-md); }
.p-pay-hint.partial  { color: var(--p-amber-md); }

/* ── Receipt preview ── */
#receiptContent { font-family: var(--p-font); }

/* ── Void warning ── */
.p-void-warning {
    background: var(--p-amber-lt);
    border: 1.5px solid var(--p-amber-bd);
    border-radius: 10px; padding: 14px 16px;
    display: flex; gap: 10px; align-items: flex-start;
    margin-bottom: 16px;
}
.p-void-warning i { color: var(--p-amber-md); font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
.p-void-warning p { font-size: .875rem; color: var(--p-amber); margin: 0; line-height: 1.5; }

/* ── Responsive ── */
@media (max-width: 1024px) {
    .pay-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
    .pay-kpi-grid { grid-template-columns: 1fr; }
    .pay-header { gap: 12px; }
}
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main pay-wrap">

    <!-- ── Page Header ───────────────────────────────────── -->
    <div class="pay-header fade-in">
        <div>
            <h2><i class="bi bi-credit-card-2-front-fill me-2 text-primary" style="font-size:1.3rem;vertical-align:middle;"></i>Payments &amp; Receipts</h2>
            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <p class="mb-0">Financial transaction history and receipt management</p>
                <?php if (!$isSuperAdmin && $branchName): ?>
                <span class="pay-branch-badge">
                    <i class="bi bi-building-fill"></i>
                    <?= htmlspecialchars($branchName) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pay-header-actions">
            <button class="pay-btn pay-btn-primary"
                    data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <i class="bi bi-plus-circle-fill"></i> Record Payment
            </button>
            <a id="exportBtn" href="#" class="pay-btn pay-btn-ghost">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────── -->
    <div class="pay-kpi-grid">
        <div class="pay-kpi blue fade-in" style="animation-delay:.05s">
            <div class="pay-kpi-icon"><i class="bi bi-wallet2-fill"></i></div>
            <div class="pay-kpi-val" id="statTotal">—</div>
            <div class="pay-kpi-lbl">Total Revenue</div>
        </div>
        <div class="pay-kpi green fade-in" style="animation-delay:.1s">
            <div class="pay-kpi-icon"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="pay-kpi-val" id="statMonthly">—</div>
            <div class="pay-kpi-lbl">This Month</div>
        </div>
        <div class="pay-kpi amber fade-in" style="animation-delay:.15s">
            <div class="pay-kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="pay-kpi-val" id="statOutstanding">—</div>
            <div class="pay-kpi-lbl">Outstanding</div>
        </div>
        <div class="pay-kpi red fade-in" style="animation-delay:.2s">
            <div class="pay-kpi-icon"><i class="bi bi-slash-circle-fill"></i></div>
            <div class="pay-kpi-val" id="statVoid">—</div>
            <div class="pay-kpi-lbl">Voided Txns</div>
        </div>
    </div>

    <!-- ── Filter Panel ──────────────────────────────────── -->
    <div class="pay-filter-panel fade-in" style="animation-delay:.25s">
        <div class="row g-3 align-items-end">
            <?php if ($isSuperAdmin): ?>
            <div class="col-md-2 col-6">
                <label class="pay-filter-label">Branch</label>
                <select id="fBranch" class="pay-filter-select">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 col-6">
                <label class="pay-filter-label">From Date</label>
                <input type="date" id="fDateFrom" class="pay-filter-input">
            </div>
            <div class="col-md-2 col-6">
                <label class="pay-filter-label">To Date</label>
                <input type="date" id="fDateTo" class="pay-filter-input">
            </div>
            <div class="col-md-2 col-6">
                <label class="pay-filter-label">Method</label>
                <select id="fMethod" class="pay-filter-select">
                    <option value="">All Methods</option>
                    <?php foreach ($paymentMethods as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="pay-filter-label">Status</label>
                <select id="fStatus" class="pay-filter-select">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Void">Voided</option>
                </select>
            </div>
            <div class="col-md-<?= $isSuperAdmin ? '2' : '4' ?> col-12">
                <label class="pay-filter-label">Search</label>
                <div class="pay-filter-search-wrap">
                    <input type="text" id="fSearch" class="pay-filter-input"
                           placeholder="Receipt # or student name…">
                    <button class="pay-filter-btn" id="applyFilters">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Payments Table ────────────────────────────────── -->
    <div class="pay-table-card fade-in" style="animation-delay:.3s">
        <div class="card-head">
            <h6><i class="bi bi-table"></i> Transaction Records</h6>
            <div style="font-size:.78rem;color:var(--p-muted)" id="tableCount"></div>
        </div>
        <div class="table-responsive">
            <table id="paymentsTable" class="table table-hover mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Course</th>
                        <?php if ($isSuperAdmin): ?><th>Branch</th><?php endif; ?>
                        <th class="text-end">Amount</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody">
                    <tr>
                        <td colspan="<?= $isSuperAdmin ? 11 : 10 ?>"
                            class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading transactions…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>


<!-- ════════════════ RECORD PAYMENT MODAL ════════════════════ -->
<div class="modal fade pay-modal" id="recordPaymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header blue">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle-fill"></i> Record New Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="recordPaymentForm">
            <div class="modal-body">

                <!-- Student search -->
                <div class="modal-field mb-3">
                    <label>Student <span>*</span></label>
                    <div class="p-autocomplete">
                        <div class="modal-input-group">
                            <span class="modal-input-pfx"><i class="bi bi-search"></i></span>
                            <input type="text" id="studentSearch" class="modal-input"
                                   placeholder="Type student name or ID…" autocomplete="off">
                        </div>
                        <div id="studentResults" class="p-ac-results"></div>
                    </div>
                    <input type="hidden" id="hiddenStudentId" name="student_id">
                </div>

                <!-- Enrollment select -->
                <div class="modal-field mb-3" id="enrollmentRow" style="display:none;">
                    <label>Enrollment / Course <span>*</span></label>
                    <select id="enrollmentSelect" name="enrollment_id" class="modal-select">
                        <option value="">— Select Enrollment —</option>
                    </select>
                </div>

                <!-- Balance card -->
                <div class="p-balance-card" id="balanceCard">
                    <div class="p-balance-grid">
                        <div class="p-balance-cell">
                            <label>Course Fee</label>
                            <span id="balFee">—</span>
                        </div>
                        <div class="p-balance-cell success">
                            <label>Total Paid</label>
                            <span id="balPaid">—</span>
                        </div>
                        <div class="p-balance-cell danger" id="balOutstandingCell">
                            <label>Outstanding</label>
                            <span id="balOutstanding">—</span>
                        </div>
                    </div>
                </div>

                <!-- Payment fields -->
                <div id="paymentFields" style="display:none; margin-top:16px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="modal-field">
                                <label>Amount (USD) <span>*</span></label>
                                <div class="modal-input-group">
                                    <span class="modal-input-pfx" style="color:var(--p-green-md)">$</span>
                                    <input type="number" id="payAmount" name="amount"
                                           class="modal-input" step="0.01" min="0.01" placeholder="0.00">
                                </div>
                                <div id="payTypeLabel"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="modal-field">
                                <label>Payment Method <span>*</span></label>
                                <select id="payMethod" name="payment_method" class="modal-select">
                                    <option value="">— Select Method —</option>
                                    <?php foreach ($paymentMethods as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="modal-field">
                                <label>Payment Date <span>*</span></label>
                                <input type="date" id="payDate" name="payment_date"
                                       class="modal-input" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="modal-field">
                                <label>Transaction / Reference ID</label>
                                <input type="text" name="transaction_id" class="modal-input"
                                       placeholder="Bank ref, mobile txn ID… (optional)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="modal-field">
                                <label>Notes</label>
                                <input type="text" name="notes" class="modal-input"
                                       placeholder="Optional note…">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="pay-btn pay-btn-ghost pay-btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="pay-btn pay-btn-primary pay-btn-sm"
                        id="submitPayBtn" disabled>
                    <i class="bi bi-check-circle-fill"></i> Record Payment
                </button>
            </div>
            </form>

        </div>
    </div>
</div>


<!-- ════════════════ RECEIPT MODAL ═══════════════════════════ -->
<div class="modal fade pay-modal" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content">
            <div class="modal-header blue">
                <h5 class="modal-title"><i class="bi bi-receipt-cutoff"></i> Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:0; background:#fff;">
                <div id="receiptContent" style="padding:24px 28px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="pay-btn pay-btn-ghost pay-btn-sm"
                        data-bs-dismiss="modal">Close</button>
                <button type="button" class="pay-btn pay-btn-primary pay-btn-sm"
                        onclick="printReceipt()">
                    <i class="bi bi-printer-fill"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════ VOID MODAL ══════════════════════════════ -->
<div class="modal fade pay-modal" id="voidModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header amber">
                <h5 class="modal-title" style="color:var(--p-amber);">
                    <i class="bi bi-slash-circle-fill"></i> Void Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="voidForm">
            <div class="modal-body">
                <div class="p-void-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <p><strong>This action cannot be undone.</strong> The payment record will be retained for audit purposes, but the transaction will be marked as void and the student's balance will be restored.</p>
                </div>
                <div style="font-size:.875rem; color:var(--p-muted); margin-bottom:14px;">
                    Voiding receipt:
                    <strong class="receipt-code" id="voidReceiptNo"></strong>
                </div>
                <input type="hidden" id="voidPaymentId" name="payment_id">
                <div class="modal-field">
                    <label>Reason for Voiding <span>*</span></label>
                    <textarea id="voidReason" name="void_reason" class="modal-textarea"
                              rows="3"
                              placeholder="e.g. Duplicate entry, wrong amount, data entry error…"
                              required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="pay-btn pay-btn-ghost pay-btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="pay-btn pay-btn-sm"
                        style="background:var(--p-red-md);color:#fff;">
                    <i class="bi bi-slash-circle-fill"></i> Confirm Void
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Print area (hidden) -->
<div id="printReceiptArea" style="display:none;"></div>


<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
/* ════════════════════════════════════════════════════════════
   PAYMENTS PAGE — JavaScript
════════════════════════════════════════════════════════════ */
const isSuperAdmin  = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const printedByUser = <?= json_encode($userName) ?>;
const API           = 'models/api/payment_api.php';
let dtTable         = null;
let currentFilters  = {};

// ── Formatters ────────────────────────────────────────────────
function fmtMoney(v) {
    return '$' + parseFloat(v || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}
function fmtDate(s) {
    if (!s) return '—';
    return new Date(s).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
}
function initials(name) {
    return String(name || '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
}
function methodIcon(m) {
    const icons = {
        'Cash':                   'bi-cash-stack',
        'Bank Transfer':          'bi-bank2',
        'Mobile Money - Orange':  'bi-phone',
        'Mobile Money - MTN':     'bi-phone-fill',
        'Check':                  'bi-file-earmark-text',
        'Debit Card':             'bi-credit-card',
        'Credit Card':            'bi-credit-card-2-front'
    };
    return `<i class="bi ${icons[m] || 'bi-cash'} me-1"></i>${m || '—'}`;
}
function buildExportUrl() {
    return API + '?' + new URLSearchParams({action: 'export', ...currentFilters});
}

// ── Stats ─────────────────────────────────────────────────────
function loadStats() {
    const bf = isSuperAdmin ? ($('#fBranch').val() || '') : '';
    $.getJSON(API, {action: 'stats', branch_id: bf}, function(res) {
        if (!res.success) return;
        const d = res.data;
        $('#statTotal').text(fmtMoney(d.total_rev));
        $('#statMonthly').text(fmtMoney(d.monthly_rev));
        $('#statOutstanding').text(fmtMoney(d.outstanding));
        $('#statVoid').text(d.void_count);
    });
}

// ── Table ─────────────────────────────────────────────────────
function loadTable() {
    currentFilters = {
        branch_id: isSuperAdmin ? ($('#fBranch').val() || '') : '',
        date_from: $('#fDateFrom').val(),
        date_to:   $('#fDateTo').val(),
        method:    $('#fMethod').val(),
        status:    $('#fStatus').val(),
        search:    $('#fSearch').val(),
    };
    $('#exportBtn').attr('href', buildExportUrl());

    $.getJSON(API, {action: 'list', ...currentFilters}, function(res) {
        if (!res.success) {
            Swal.fire('Error', res.message || 'Failed to load data', 'error'); return;
        }
        renderTable(res.data || []);
    }).fail(function() {
        Swal.fire('Error', 'Could not connect to the server.', 'error');
    });
}

function renderTable(data) {
    if (dtTable) { dtTable.destroy(); dtTable = null; }
    $('#paymentsTableBody').empty();
    $('#tableCount').text(data.length + ' record' + (data.length !== 1 ? 's' : ''));

    const cols = [
        {
            data: 'receipt_no',
            render: d => `<span class="receipt-code">${d || '—'}</span>`
        },
        {
            data: 'payment_date',
            render: d => `<span class="text-nowrap" style="font-size:.82rem;color:var(--p-muted)">${fmtDate(d)}</span>`
        },
        {
            data: 'student_name',
            render: (d, t, r) => `<div class="student-name">${d || '—'}</div>
                                   <div class="student-code">${r.student_code || ''}</div>`
        },
        {
            data: 'course_name',
            render: d => d
                ? `<span style="font-size:.84rem">${d}</span>`
                : '<em style="color:var(--p-subtle);font-size:.82rem">N/A</em>'
        },
    ];

    if (isSuperAdmin) {
        cols.push({
            data: 'branch_name',
            render: d => `<span style="font-size:.82rem">${d || '—'}</span>`
        });
    }

    cols.push(
        {
            data: 'amount',
            className: 'text-end',
            render: d => `<span style="font-weight:700;color:var(--p-green-md)">${fmtMoney(d)}</span>`
        },
        {
            data: 'payment_type',
            render: d => d === 'Full'
                ? '<span class="p-badge p-badge-success"><i class="bi bi-check-circle-fill"></i> Full</span>'
                : '<span class="p-badge p-badge-warning"><i class="bi bi-pie-chart-fill"></i> Partial</span>'
        },
        {
            data: 'payment_method',
            render: d => `<span style="font-size:.82rem;white-space:nowrap">${methodIcon(d)}</span>`
        },
        {
            data: 'balance',
            className: 'text-end',
            render: (d, t, r) => {
                if (d === null || d === undefined) return '<span style="color:var(--p-subtle)">—</span>';
                return parseFloat(d) > 0
                    ? `<span style="font-weight:700;color:var(--p-red-md);font-size:.84rem">${fmtMoney(d)}</span>`
                    : '<span class="p-badge p-badge-success"><i class="bi bi-check-circle-fill"></i> Cleared</span>';
            }
        },
        {
            data: 'status',
            render: (d, t, r) => {
                if (d === 'Void') {
                    const tip = r.void_reason
                        ? ` title="${String(r.void_reason).replace(/"/g,'&quot;')}"` : '';
                    return `<span class="p-badge p-badge-danger" data-bs-toggle="tooltip"${tip}>
                                <i class="bi bi-slash-circle-fill"></i> Void
                            </span>`;
                }
                return '<span class="p-badge p-badge-success"><i class="bi bi-shield-check-fill"></i> Active</span>';
            }
        },
        {
            data: null,
            orderable: false,
            className: 'text-center',
            render: (d, t, r) => {
                let btns = `<div class="d-flex justify-content-center gap-1">
                    <button class="tbl-action tbl-action-view" onclick="showReceipt(${r.id})" title="View Receipt">
                        <i class="bi bi-receipt"></i>
                    </button>`;
                if (r.status === 'Active') {
                    btns += `<button class="tbl-action tbl-action-void"
                                 onclick="openVoid(${r.id},'${String(r.receipt_no||'').replace(/'/g,"\\'")}')"
                                 title="Void Payment">
                                <i class="bi bi-slash-circle"></i>
                             </button>`;
                }
                btns += '</div>';
                return btns;
            }
        }
    );

    dtTable = $('#paymentsTable').DataTable({
        data,
        columns: cols,
        order: [[1, 'desc']],
        responsive: true,
        pageLength: 25,
        language: {
            emptyTable:  '<div class="text-center py-5" style="color:var(--p-muted)"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No payments found. Adjust your filters.</div>',
            zeroRecords: '<div class="text-center py-5" style="color:var(--p-muted)"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching records found.</div>',
        },
        rowCallback: function(row, data) {
            if (data.status === 'Void') $(row).addClass('void-row');
        }
    });

    $('[data-bs-toggle="tooltip"]').tooltip({ html: true });
}

// ── Init ──────────────────────────────────────────────────────
$(document).ready(function() {
    loadStats();
    loadTable();

    $('#applyFilters').on('click', function() { loadStats(); loadTable(); });
    $('#fSearch').on('keydown', function(e) {
        if (e.key === 'Enter') { loadStats(); loadTable(); }
    });
    if (isSuperAdmin) {
        $('#fBranch').on('change', function() { loadStats(); loadTable(); });
    }
});

// ── Student autocomplete ──────────────────────────────────────
let searchTimer;

$('#studentSearch').on('input', function() {
    clearTimeout(searchTimer);
    const q = $(this).val().trim();
    $('#hiddenStudentId').val('');
    resetEnrollmentSection();
    if (q.length < 2) { $('#studentResults').hide(); return; }

    searchTimer = setTimeout(function() {
        $.getJSON(API, {action: 'search_students', q}, function(rows) {
            if (!rows.length) {
                $('#studentResults').html(
                    '<div class="p-ac-item" style="color:var(--p-muted)">No students found</div>'
                ).show();
                return;
            }
            const html = rows.map(s => `
                <div class="p-ac-item" data-id="${s.id}" data-name="${s.name}" data-code="${s.code}">
                    <div class="p-ac-avatar">${initials(s.name)}</div>
                    <div>
                        <div class="p-ac-name">${s.name}</div>
                        <div class="p-ac-meta">${s.code}</div>
                    </div>
                    <span class="p-ac-branch">${s.branch || ''}</span>
                </div>`).join('');
            $('#studentResults').html(html).show();
        });
    }, 300);
});

$(document).on('click', '.p-ac-item[data-id]', function() {
    const id   = $(this).data('id');
    const name = $(this).data('name');
    const code = $(this).data('code');
    $('#studentSearch').val(`${name}  (${code})`);
    $('#hiddenStudentId').val(id);
    $('#studentResults').hide();
    loadEnrollments(id);
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('.p-autocomplete').length) $('#studentResults').hide();
});

function resetEnrollmentSection() {
    $('#enrollmentRow').hide();
    $('#balanceCard').removeClass('show cleared');
    $('#paymentFields').hide();
    $('#submitPayBtn').prop('disabled', true);
    $('#enrollmentSelect').html('<option value="">— Select Enrollment —</option>');
    $('#payTypeLabel').text('');
}

function loadEnrollments(studentId) {
    $.getJSON(API, {action: 'get_enrollments', student_id: studentId}, function(res) {
        if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
        let opts = '<option value="">— Select Enrollment —</option>';
        res.data.forEach(e => {
            const bal     = parseFloat(e.balance).toFixed(2);
            const cleared = e.balance <= 0;
            opts += `<option value="${e.enrollment_id}"
                        data-fee="${e.fees}"
                        data-paid="${e.total_paid}"
                        data-balance="${e.balance}"
                        ${cleared ? 'disabled' : ''}>
                    ${e.course_name} — Fee: $${parseFloat(e.fees).toFixed(2)} | Outstanding: $${bal}${cleared ? ' ✓ Cleared' : ''}
                    </option>`;
        });
        $('#enrollmentSelect').html(opts);
        $('#enrollmentRow').show();
        updateBalanceCard();
    });
}

$('#enrollmentSelect').on('change', updateBalanceCard);

function updateBalanceCard() {
    const sel = $('#enrollmentSelect option:selected');
    const fee  = parseFloat(sel.data('fee')     || 0);
    const paid = parseFloat(sel.data('paid')    || 0);
    const bal  = parseFloat(sel.data('balance') || 0);

    if (!sel.val()) {
        $('#balanceCard').removeClass('show cleared');
        $('#paymentFields').hide();
        $('#submitPayBtn').prop('disabled', true);
        return;
    }

    $('#balFee').text(fmtMoney(fee));
    $('#balPaid').text(fmtMoney(paid));
    $('#balOutstanding').text(fmtMoney(bal));

    const card = $('#balanceCard');
    card.addClass('show');

    if (bal > 0) {
        card.removeClass('cleared');
        $('#balOutstandingCell').addClass('danger').removeClass('success');
        $('#paymentFields').show();
        $('#payAmount').attr('max', bal).val(bal.toFixed(2));
        checkPayType();
        $('#submitPayBtn').prop('disabled', false);
    } else {
        card.addClass('cleared');
        $('#balOutstandingCell').removeClass('danger').addClass('success');
        $('#paymentFields').hide();
        $('#submitPayBtn').prop('disabled', true);
    }
}

$('#payAmount').on('input', checkPayType);

function checkPayType() {
    const amount = parseFloat($('#payAmount').val() || 0);
    const bal    = parseFloat($('#enrollmentSelect option:selected').data('balance') || 0);
    const lbl    = $('#payTypeLabel');
    if (amount <= 0) { lbl.html(''); return; }
    if (amount >= bal - 0.01) {
        lbl.html('<div class="p-pay-hint full"><i class="bi bi-check-circle-fill"></i> This will fully clear the balance.</div>');
    } else {
        const rem = (bal - amount).toFixed(2);
        lbl.html(`<div class="p-pay-hint partial"><i class="bi bi-pie-chart-fill"></i> Partial payment — <strong>$${rem}</strong> will remain outstanding.</div>`);
    }
}

// ── Submit payment ────────────────────────────────────────────
$('#recordPaymentForm').on('submit', function(e) {
    e.preventDefault();
    if (!$('#hiddenStudentId').val()) {
        Swal.fire('Required', 'Please select a student.', 'warning'); return;
    }
    if (!$('#enrollmentSelect').val()) {
        Swal.fire('Required', 'Please select an enrollment.', 'warning'); return;
    }

    const btn = $('#submitPayBtn');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Recording…');

    $.post(API, $(this).serialize() + '&action=record', function(res) {
        btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Record Payment');

        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal')).hide();
            Swal.fire({
                title: 'Payment Recorded!',
                html: `Receipt <strong class="text-primary">${res.receipt_no}</strong> has been created.`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-receipt me-1"></i> View Receipt',
                cancelButtonText: 'Close',
                confirmButtonColor: '#2563EB',
            }).then(r => {
                if (r.isConfirmed) showReceipt(res.payment_id);
                loadTable(); loadStats();
                resetRecordForm();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Record Payment');
        Swal.fire('Error', 'Server error. Please try again.', 'error');
    });
});

$('#recordPaymentModal').on('hidden.bs.modal', resetRecordForm);
function resetRecordForm() {
    $('#recordPaymentForm')[0].reset();
    $('#studentSearch').val('');
    $('#hiddenStudentId').val('');
    resetEnrollmentSection();
}

// ── Security hash ─────────────────────────────────────────────
async function makeHash(str) {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('').substring(0, 16).toUpperCase();
}

// ── View Receipt ──────────────────────────────────────────────
function showReceipt(paymentId) {
    $('#receiptContent').html(
        '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>'
    );
    new bootstrap.Modal(document.getElementById('receiptModal')).show();

    $.getJSON(API, {action: 'get_receipt', payment_id: paymentId}, async function(res) {
        if (!res.success) {
            $('#receiptContent').html(`<div class="text-center text-danger py-4">${res.message}</div>`);
            return;
        }
        const d       = res.data;
        const isVoid  = d.status === 'Void';
        const today   = new Date();
        const printed = today.toLocaleString('en-US', {dateStyle:'medium', timeStyle:'short'});
        const secHash = await makeHash(
            (d.receipt_no || d.id) + d.student_code + (d.amount || '') + today.toISOString().slice(0,10)
        );
        const verifyUrl = `${window.location.origin}/sbvs/sbvs/config/controllers/views/payments.php?verify=${paymentId}`;
        const micro    = `✦ SHINING BRIGHT VOCATIONAL SCHOOL · OFFICIAL RECEIPT · ${secHash} · NOT VALID IF PHOTOCOPIED · `.repeat(10);

        const statusColor  = isVoid ? '#DC2626' : (parseFloat(d.balance) > 0 ? '#D97706' : '#059669');
        const statusLabel  = isVoid ? '⊘ VOIDED' : (parseFloat(d.balance) > 0 ? 'PARTIAL PAYMENT' : 'PAID IN FULL');
        const balColor     = parseFloat(d.balance) > 0 ? '#DC2626' : '#059669';
        const balText      = parseFloat(d.balance) > 0 ? fmtMoney(d.balance) : '✔ CLEARED';

        const detailRows = [
            ['Date',         fmtDate(d.payment_date)],
            ['Student',      `<strong>${d.student_name}</strong>`],
            ['Student ID',   `<span style="font-family:monospace;font-size:.82rem">${d.student_code}</span>`],
            ['Course',       d.course_name || '—'],
            ['Branch',       d.branch_name || '—'],
            ['Method',       methodIcon(d.payment_method)],
            d.transaction_id ? ['Reference ID', d.transaction_id] : null,
            d.notes          ? ['Notes', `<em>${d.notes}</em>`]   : null,
        ].filter(Boolean).map(([label, val]) =>
            `<tr>
                <td style="color:#64748B;font-size:.8rem;font-weight:500;padding:6px 0;width:38%;vertical-align:top;">${label}</td>
                <td style="color:#0F172A;font-size:.82rem;padding:6px 0;">${val}</td>
            </tr>`
        ).join('');

        const html = `
<div id="receiptPrintable" style="font-family:'Plus Jakarta Sans',sans-serif;background:#fff;position:relative;${isVoid ? 'opacity:.8' : ''}">

    <!-- Top security strip -->
    <div style="background:#1E40AF;padding:5px 14px;display:flex;justify-content:space-between;align-items:center;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
        <span style="font-size:6px;font-weight:800;letter-spacing:2px;color:rgba(255,255,255,.6);text-transform:uppercase;">SHINING BRIGHT VOCATIONAL SCHOOL · OFFICIAL RECEIPT</span>
        <span style="font-family:monospace;font-size:6.5px;color:rgba(255,255,255,.8);font-weight:700;">${secHash}</span>
    </div>

    <!-- Microtext band -->
    <div style="font-size:5.5px;font-weight:700;letter-spacing:1.5px;color:rgba(30,64,175,.45);text-transform:uppercase;white-space:nowrap;overflow:hidden;background:#EFF6FF;padding:3px 0;text-align:center;user-select:none;">${micro}</div>

    <div style="padding:20px 22px 18px;">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:1px dashed #E2E8F0;margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="../../assets/img/logo.svg" alt="Logo" width="38" height="46"
                     onerror="this.style.display='none'">
                <div>
                    <div style="font-size:.95rem;font-weight:800;color:#0F172A;letter-spacing:-.02em;">Shining Bright</div>
                    <div style="font-size:.6rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#64748B;">Vocational School</div>
                    ${d.branch_name ? `<div style="font-size:.72rem;color:#94A3B8;margin-top:1px;">${d.branch_name}</div>` : ''}
                    ${d.branch_phone ? `<div style="font-size:.68rem;color:#94A3B8;">☎ ${d.branch_phone}</div>` : ''}
                </div>
            </div>
            <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                <div id="payQR_${paymentId}"></div>
                <div style="font-size:.6rem;color:#94A3B8;letter-spacing:.5px;text-align:right;">SCAN TO VERIFY</div>
                <div style="text-align:right;">
                    <div style="font-size:.62rem;color:#94A3B8;">Printed by</div>
                    <div style="font-size:.78rem;font-weight:600;color:#0F172A;">${printedByUser}</div>
                    <div style="font-size:.68rem;color:#94A3B8;margin-top:2px;">${printed}</div>
                </div>
            </div>
        </div>

        <!-- Receipt title & status -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div>
                <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#1E40AF;margin-bottom:2px;">Payment Receipt</div>
                <div style="font-size:1.1rem;font-weight:800;color:#0F172A;letter-spacing:-.02em;">${d.receipt_no || '—'}</div>
            </div>
            <span style="background:${statusColor};color:#fff;font-size:.68rem;font-weight:700;padding:5px 14px;border-radius:20px;letter-spacing:.5px;">${statusLabel}</span>
        </div>

        ${isVoid ? `<div style="text-align:center;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:10px;margin-bottom:14px;">
            <div style="font-size:1rem;font-weight:900;color:#DC2626;letter-spacing:3px;">⊘ VOID</div>
            <div style="font-size:.72rem;color:#64748B;margin-top:4px;">Reason: ${d.void_reason || '—'}</div>
        </div>` : ''}

        <!-- Details -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:14px;">${detailRows}</table>

        <!-- Financial table -->
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:14px;">
            <thead>
                <tr>
                    <th style="background:#F8FAFC;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748B;padding:8px 12px;border:1px solid #E2E8F0;">Description</th>
                    <th style="background:#F8FAFC;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748B;padding:8px 12px;border:1px solid #E2E8F0;text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="padding:8px 12px;border:1px solid #E2E8F0;">Total Course Fee</td>
                    <td style="padding:8px 12px;border:1px solid #E2E8F0;text-align:right;font-weight:600;">${fmtMoney(d.fees)}</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #E2E8F0;">Previously Paid</td>
                    <td style="padding:8px 12px;border:1px solid #E2E8F0;text-align:right;color:#64748B;">${fmtMoney(d.prev_paid)}</td></tr>
                <tr style="background:#F0FDF4;">
                    <td style="padding:10px 12px;border:1px solid #E2E8F0;font-weight:700;color:#065F46;"><i class="bi bi-check-circle-fill"></i> This Payment</td>
                    <td style="padding:10px 12px;border:1px solid #E2E8F0;text-align:right;font-weight:800;font-size:1rem;color:#059669;">${fmtMoney(d.amount)}</td></tr>
                <tr style="background:${parseFloat(d.balance)>0?'#FFFBEB':'#F0FDF4'};">
                    <td style="padding:10px 12px;border:2px solid #1E40AF;font-weight:700;">Remaining Balance</td>
                    <td style="padding:10px 12px;border:2px solid #1E40AF;text-align:right;font-weight:800;color:${balColor};">${balText}</td></tr>
            </tbody>
        </table>

        <!-- Footer -->
        <div style="border-top:1px dashed #E2E8F0;padding-top:12px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
            <div>
                <span style="background:#1E40AF;color:#fff;font-size:6px;font-weight:800;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;border-radius:20px;display:inline-block;">Official Receipt</span>
                <div style="font-family:monospace;font-size:7px;color:rgba(30,64,175,.6);letter-spacing:1.5px;background:#EFF6FF;padding:3px 8px;border-radius:4px;border:1px solid #BFDBFE;margin-top:5px;">SEC: ${secHash}</div>
                <div style="font-size:.58rem;color:#94A3B8;margin-top:4px;max-width:240px;line-height:1.5;">Valid in original form only. Photocopy or scan is not valid.</div>
            </div>
            <div style="text-align:center;border:1.5px dashed #CBD5E1;border-radius:8px;padding:6px 20px;">
                <div style="font-size:.6rem;color:#94A3B8;margin-bottom:18px;">Authorised Signature</div>
                <div style="font-size:.6rem;color:#94A3B8;">Office Stamp</div>
            </div>
        </div>

    </div>

    <!-- Bottom microtext -->
    <div style="font-size:5.5px;font-weight:700;letter-spacing:1.5px;color:rgba(30,64,175,.45);text-transform:uppercase;white-space:nowrap;overflow:hidden;background:#EFF6FF;padding:3px 0;text-align:center;user-select:none;transform:scaleY(-1);">${micro}</div>

    <!-- Bottom security strip -->
    <div style="background:#1E40AF;padding:4px 14px;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
        <div style="font-size:5.5px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,.5);text-transform:uppercase;text-align:center;">NOT VALID IF PHOTOCOPIED · SHINING BRIGHT VOCATIONAL SCHOOL · ${secHash}</div>
    </div>

</div>`;

        $('#receiptContent').html(html);

        setTimeout(() => {
            const qrEl = document.getElementById('payQR_' + paymentId);
            if (qrEl && typeof QRCode !== 'undefined') {
                new QRCode(qrEl, {
                    text: verifyUrl,
                    width: 68, height: 68,
                    colorDark: '#1E40AF', colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        }, 60);
    });
}

function printReceipt() {
    const content = document.getElementById('receiptPrintable');
    if (!content) return;
    const area  = document.getElementById('printReceiptArea');
    area.innerHTML = content.outerHTML;
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            body > *:not(#printReceiptArea) { display:none !important; }
            #printReceiptArea {
                display:block !important;
                -webkit-print-color-adjust:exact;
                print-color-adjust:exact;
            }
        }`;
    area.appendChild(style);
    area.style.display = 'block';
    window.print();
    setTimeout(() => { area.style.display = 'none'; area.innerHTML = ''; }, 600);
}

// ── Void ──────────────────────────────────────────────────────
function openVoid(paymentId, receiptNo) {
    $('#voidPaymentId').val(paymentId);
    $('#voidReceiptNo').text(receiptNo || '#' + paymentId);
    $('#voidReason').val('');
    new bootstrap.Modal(document.getElementById('voidModal')).show();
}

$('#voidForm').on('submit', function(e) {
    e.preventDefault();
    const reason = $('#voidReason').val().trim();
    if (!reason) {
        Swal.fire('Required', 'Please provide a reason for voiding.', 'warning'); return;
    }

    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Voiding…');

    $.post(API, {
        action:      'void',
        payment_id:  $('#voidPaymentId').val(),
        void_reason: reason
    }, function(res) {
        btn.prop('disabled', false).html('<i class="bi bi-slash-circle-fill me-1"></i> Confirm Void');
        bootstrap.Modal.getInstance(document.getElementById('voidModal')).hide();

        if (res.success) {
            Swal.fire({
                title: 'Payment Voided',
                text: res.message,
                icon: 'success',
                timer: 2500,
                showConfirmButton: false
            });
            loadTable(); loadStats();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="bi bi-slash-circle-fill me-1"></i> Confirm Void');
        Swal.fire('Error', 'Server error.', 'error');
    });
});
</script>
</body>
</html>