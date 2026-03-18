<?php
/**
 * System Reports 
 * Theme: FinTech Ledger & Analytics Command Center
 * 
 * High-performance, data-dense layout specifically engineered for financial 
 * and enrollment data review.
 */

session_start();
ob_start();
require_once '../../config.php';
require_once '../../database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}

$db = (new Database())->getConnection();

$role         = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin = ($role === 'Super Admin');
$isBranchAdmin = ($role === 'Branch Admin');
$isAdmin      = ($role === 'Admin');

$branches = $isSuperAdmin
    ? $db->query("SELECT id, name FROM branches WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$branchName = '';
if (!$isSuperAdmin && $sessionBranch) {
    $bStmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $bStmt->execute([$sessionBranch]);
    $branchName = $bStmt->fetchColumn() ?: '';
}

$pageTitle  = 'Analytics Ledger';
$activePage = 'reports.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<style>
    /* ── FinTech Ledger Theme (High-Contrast, Monospaced Data, Strict Borders) ── */
    :root {
        --ft-bg: #f8fafc;
        --ft-surface: #ffffff;
        --ft-border: #e2e8f0;
        --ft-text-dark: #0f172a;
        --ft-text-muted: #64748b;
        --ft-primary: #2563eb;
        --ft-success: #059669;
        --ft-accent: #38bdf8;
    }

    body {
        background-color: var(--ft-bg) !important;
        font-family: 'Inter', -apple-system, sans-serif;
    }

    /* ── Command Bar (Top Toolbar) ── */
    .command-bar {
        background: var(--ft-surface);
        border: 1px solid var(--ft-border);
        border-radius: 10px;
        padding: 1rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        margin-bottom: 1.5rem;
    }

    .form-control-ft {
        background: var(--ft-bg);
        border: 1px solid var(--ft-border);
        color: var(--ft-text-dark);
        font-weight: 500;
        font-size: 0.85rem;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .form-control-ft:focus {
        border-color: var(--ft-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .ft-btn {
        font-weight: 600;
        letter-spacing: 0.5px;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: transform 0.1s, box-shadow 0.2s;
        text-transform: uppercase;
    }
    .ft-btn:active { transform: translateY(1px); }
    .ft-btn-primary { background: var(--ft-text-dark); color: #fff; border: none; }
    .ft-btn-primary:hover { background: #334155; color: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .ft-btn-outline { background: var(--ft-surface); color: var(--ft-text-dark); border: 1px solid var(--ft-border); }
    .ft-btn-outline:hover { background: var(--ft-bg); }

    /* Segmented Controls for Tabs */
    .ft-segmented-control {
        display: inline-flex;
        background: var(--ft-bg);
        border-radius: 8px;
        padding: 4px;
        border: 1px solid var(--ft-border);
    }
    .ft-segment {
        padding: 0.5rem 1.25rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ft-text-muted);
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s;
        user-select: none;
    }
    .ft-segment.active {
        background: var(--ft-surface);
        color: var(--ft-text-dark);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .ft-segment:hover:not(.active) { color: var(--ft-text-dark); }

    /* ── Ledger KPI Metrics ── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .ft-kpi-card {
        background: var(--ft-surface);
        border: 1px solid var(--ft-border);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .ft-kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 4px; height: 100%;
        background: var(--ft-border);
    }
    .ft-kpi-card.kpi-revenue::before { background: var(--ft-success); }
    .ft-kpi-card.kpi-enroll::before { background: var(--ft-primary); }
    .ft-kpi-card.kpi-pending::before { background: var(--ft-accent); }

    .kpi-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--ft-text-muted);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .kpi-value {
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--ft-text-dark);
        font-family: 'JetBrains Mono', 'Courier New', monospace; /* Ledger feel */
        letter-spacing: -1px;
    }

    /* ── Datagrid Ledger ── */
    .ledger-container {
        background: var(--ft-surface);
        border: 1px solid var(--ft-border);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .ledger-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--ft-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafbfc;
    }

    /* Override DataTables for FinTech Theme */
    table.dataTable.ft-ledger {
        border-collapse: collapse !important;
        width: 100% !important;
        margin: 0 !important;
    }
    .ft-ledger thead th {
        background: var(--ft-surface);
        color: var(--ft-text-muted);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 1rem 1.5rem !important;
        border-bottom: 2px solid var(--ft-border) !important;
        font-weight: 700;
    }
    .ft-ledger tbody td {
        padding: 1rem 1.5rem !important;
        vertical-align: middle;
        border-bottom: 1px solid var(--ft-border) !important;
        font-size: 0.9rem;
        color: var(--ft-text-dark);
    }
    .ft-ledger tbody tr:hover {
        background-color: var(--ft-bg) !important;
    }
    
    /* Numeric Data Column styling */
    .ledger-num {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        font-weight: 600;
    }
    .ledger-success { color: var(--ft-success); }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .pill-success { background: #d1fae5; color: #047857; }
    .pill-warning { background: #fef3c7; color: #b45309; }
    .pill-info { background: #e0f2fe; color: #0369a1; }
    .pill-danger { background: #fee2e2; color: #b91c1c; }
    .pill-dark { background: #f1f5f9; color: #334155; }

    /* Custom DataTables wrappers */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--ft-text-dark) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 6px;
    }
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid var(--ft-border);
        border-radius: 6px;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
        box-shadow: none;
        outline: none;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--ft-primary);
    }

</style>
</head>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main p-4">

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-end mb-4 fade-up">
        <div>
            <h2 class="fw-bolder mb-1" style="color: var(--ft-text-dark); letter-spacing:-0.5px;">Analytics Ledger</h2>
            <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">
                <i class="bi bi-shield-check me-1 text-success"></i> Secure Financial & Statistical Reporting
            </p>
        </div>
        <?php if (!$isSuperAdmin && $branchName): ?>
            <div class="bg-white border rounded px-3 py-1 shadow-sm small fw-bold text-secondary">
                <i class="bi bi-geo-alt-fill me-1 text-primary"></i> <?= htmlspecialchars($branchName) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Command Bar (Filters & Tools) ── -->
    <div class="command-bar fade-up" style="animation-delay: 50ms;">
        
        <!-- Filter Controls -->
        <form id="reportEngineForm" class="d-flex flex-wrap align-items-center gap-3 m-0">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small fw-bold text-muted text-uppercase">Dates</label>
                <input type="date" name="start_date" id="start_date" class="form-control form-control-ft shadow-none" value="<?= date('Y-m-01') ?>">
                <span class="text-muted small">to</span>
                <input type="date" name="end_date" id="end_date" class="form-control form-control-ft shadow-none" value="<?= date('Y-m-d') ?>">
            </div>

            <?php if ($isSuperAdmin): ?>
            <div class="d-flex align-items-center gap-2 border-start ps-3 ms-2">
                <label class="form-label mb-0 small fw-bold text-muted text-uppercase">Scope</label>
                <select name="branch_id" id="branch_id" class="form-select form-control-ft shadow-none" style="min-width: 160px;">
                    <option value="">Global Network</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="ft-btn ft-btn-primary ms-2">
                <i class="bi bi-arrow-repeat me-1"></i> Sync Data
            </button>
        </form>

        <!-- Tools & Segment Toggles -->
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch m-0 pt-1">
                <input class="form-check-input" type="checkbox" role="switch" id="liveStreamToggle" style="cursor:pointer;">
                <label class="form-check-label small fw-bold text-primary text-uppercase" for="liveStreamToggle" style="cursor:pointer; letter-spacing:0.5px;">Live Stream</label>
            </div>
            
            <div class="border-start ps-3 ms-2">
                <button id="exportLedgerBtn" class="ft-btn ft-btn-outline">
                    <i class="bi bi-download me-1"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- ── Segmented Control (Tabs) ── -->
    <div class="mb-4 text-center fade-up" style="animation-delay: 100ms;">
        <div class="ft-segmented-control" id="ledgerTabs">
            <div class="ft-segment active" data-engine-mode="payments"><i class="bi bi-credit-card me-2"></i>Transactions</div>
            <div class="ft-segment" data-engine-mode="enrollments"><i class="bi bi-people me-2"></i>Enrollment Flow</div>
            <div class="ft-segment" data-engine-mode="branch_summary"><i class="bi bi-diagram-3 me-2"></i>Branch Matrix</div>
        </div>
    </div>

    <!-- ── KPI Metrics Panel ── -->
    <div class="kpi-grid fade-up" id="ledgerKPIs" style="animation-delay: 150ms;">
        <div class="ft-kpi-card kpi-revenue">
            <div class="kpi-title"><i class="bi bi-cash-stack me-1"></i> Gross Revenue</div>
            <div class="kpi-value text-success" id="kpi_revenue">$0.00</div>
        </div>
        <div class="ft-kpi-card kpi-enroll">
            <div class="kpi-title"><i class="bi bi-person-plus-fill me-1"></i> Active Intakes</div>
            <div class="kpi-value text-primary" id="kpi_enrollments">0</div>
        </div>
        <div class="ft-kpi-card kpi-pending">
            <div class="kpi-title"><i class="bi bi-clock-history me-1"></i> Outstanding Balances</div>
            <div class="kpi-value text-warning" id="kpi_pending">$0.00</div>
        </div>
    </div>

    <!-- ── Datagrid Ledger ── -->
    <div class="ledger-container fade-up" style="animation-delay: 200ms;">
        <div class="ledger-header">
            <h5 class="mb-0 fw-bold fs-6 text-uppercase" style="letter-spacing:1px;" id="ledgerTitle">
                <i class="bi bi-table me-2 text-primary"></i> Data Grid
            </h5>
            <div class="spinner-border spinner-border-sm text-primary d-none" id="ledgerSpinner"></div>
        </div>
        <div class="p-3">
            <!-- Table wrapper -->
            <div class="table-responsive">
                <table class="ft-ledger w-100" id="ledgerTable">
                    <thead id="ledgerHead"></thead>
                    <tbody id="ledgerBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>

<script>
/**
 * ReportEngine Module
 * Modern, ES6 logic for driving the FinTech Ledger page.
 */
const ReportEngine = (() => {
    const API_ENDPOINT = 'models/api/report_api.php';
    const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;

    let currentMode = 'payments'; // payments | enrollments | branch_summary
    let dataTableInst = null;
    let liveStreamTimer = null;
    let tablePageCache = 0;

    // -- Utilities --
    const formatCurrency = (val) => {
        const num = parseFloat(val) || 0;
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const cleanHTML = (str) => {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    };

    // -- DataTable Lifecycle --
    const killDT = () => {
        if (dataTableInst) {
            tablePageCache = dataTableInst.page();
            dataTableInst.destroy();
            dataTableInst = null;
        }
    };

    const initDT = () => {
        dataTableInst = $('#ledgerTable').DataTable({
            paging: true,
            order: [],
            responsive: true,
            pageLength: 25,
            destroy: true,
            language: { 
                emptyTable: '<div class="p-5 text-center text-muted"><i class="bi bi-slash-circle fs-1 d-block mb-2 opacity-50"></i> Ledger contains no matching records.</div>'
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"<"small"l><"w-25"f>>rt<"d-flex justify-content-between align-items-center mt-3"<"small text-muted"i><"pagination-sm"p>>'
        });
        if (tablePageCache > 0 && dataTableInst.page.info().pages > tablePageCache) {
            dataTableInst.page(tablePageCache).draw('page');
        }
    };

    // -- API Fetch & Render --
    const syncData = async (silent = false) => {
        const form = document.getElementById('reportEngineForm');
        const params = new URLSearchParams(new FormData(form));
        
        const modeMap = { 'payments': 'summary', 'enrollments': 'enrollments', 'branch_summary': 'branch_summary' };
        params.append('action', modeMap[currentMode] || 'summary');

        if (!silent) {
            document.getElementById('ledgerSpinner').classList.remove('d-none');
            killDT();
            tablePageCache = 0;
            document.getElementById('ledgerBody').innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Extracting data block...</td></tr>`;
        } else {
            // Caching page before silent reload
            if(dataTableInst) killDT();
        }

        try {
            const resp = await fetch(`${API_ENDPOINT}?${params.toString()}`);
            const data = await resp.json();

            if (data.status !== 'success') throw new Error(data.message || 'Ledger sync failed.');

            drawInterface(data);
            
        } catch (err) {
            if (!silent) {
                Swal.fire('Data Synchronization Error', err.message, 'error');
                document.getElementById('ledgerBody').innerHTML = `<tr><td colspan="10" class="text-center py-5 text-danger border-0">Data unrecoverable for requested parameters.</td></tr>`;
            }
        } finally {
            document.getElementById('ledgerSpinner').classList.add('d-none');
        }
    };

    const drawInterface = (res) => {
        const h = document.getElementById('ledgerHead');
        const b = document.getElementById('ledgerBody');
        let htmlHead = '';
        let htmlBody = '';

        const dataArray = res.data || [];

        // Modes Drawing
        if (currentMode === 'payments') {
            document.getElementById('ledgerTitle').innerHTML = '<i class="bi bi-wallet2 me-2 text-dark"></i> Transaction Ledger';
            document.getElementById('ledgerKPIs').style.display = 'grid';
            
            // KPIs
            document.getElementById('kpi_revenue').textContent = formatCurrency(res.summary?.revenue);
            document.getElementById('kpi_enrollments').textContent = res.summary?.enrollments || 0;
            document.getElementById('kpi_pending').textContent = formatCurrency(res.summary?.pending);

            // Table Structure
            htmlHead = `<tr><th>Timestamp</th><th>Client / Student</th><th>TxHash ID</th><th>Protocol</th><th>Value Settled</th>${isSuperAdmin ? '<th>Origin Node</th>' : ''}</tr>`;
            
            dataArray.forEach(item => {
                let pClass = item.method === 'Cash' ? 'pill-success' : (item.method === 'Mobile Money' ? 'pill-info' : 'pill-dark');
                htmlBody += `
                <tr>
                    <td class="text-muted small fw-semibold">${cleanHTML(item.date)}</td>
                    <td class="fw-bold text-dark">${cleanHTML(item.student_name)}</td>
                    <td><code class="bg-light px-2 py-1 rounded text-secondary border small">${cleanHTML(item.tx_id)}</code></td>
                    <td><span class="status-pill ${pClass}">${cleanHTML(item.method)}</span></td>
                    <td class="ledger-num ledger-success">${formatCurrency(item.amount)}</td>
                    ${isSuperAdmin ? `<td class="small text-muted"><i class="bi bi-diagram-2 me-1"></i>${cleanHTML(item.branch_name)}</td>` : ''}
                </tr>`;
            });

        } else if (currentMode === 'enrollments') {
            document.getElementById('ledgerTitle').innerHTML = '<i class="bi bi-person-lines-fill me-2 text-dark"></i> Enrollment Matrix';
            document.getElementById('ledgerKPIs').style.display = 'grid'; // Re-use KPIs from general summary api logic if provided, or hide. 
            // Note: The original returned global KPIs on enrollment as well. We'll leave them visible.

            htmlHead = `<tr><th>Initiated</th><th>Target ID</th><th>Asset / Course</th><th>Contract Vol.</th><th>Status</th>${isSuperAdmin ? '<th>Origin Node</th>' : ''}</tr>`;
            
            dataArray.forEach(item => {
                let sClass = item.status === 'Active' ? 'pill-success' : (item.status === 'Completed' ? 'pill-info' : 'pill-danger');
                htmlBody += `
                <tr>
                    <td class="text-muted small fw-semibold">${cleanHTML(item.date)}</td>
                    <td>
                        <div class="fw-bold">${cleanHTML(item.student_name)}</div>
                        <div class="text-muted small" style="font-family:monospace;">${cleanHTML(item.student_code)}</div>
                    </td>
                    <td class="fw-medium">${cleanHTML(item.course_name)}</td>
                    <td class="ledger-num">${formatCurrency(item.fees)}</td>
                    <td><span class="status-pill ${sClass}">${cleanHTML(item.status)}</span></td>
                    ${isSuperAdmin ? `<td class="small text-muted"><i class="bi bi-diagram-2 me-1"></i>${cleanHTML(item.branch_name)}</td>` : ''}
                </tr>`;
            });

        } else if (currentMode === 'branch_summary') {
            document.getElementById('ledgerTitle').innerHTML = '<i class="bi bi-building me-2 text-dark"></i> Network Performance Matrix';
            document.getElementById('ledgerKPIs').style.display = 'none';

            htmlHead = `<tr><th>Node Entity (Branch)</th><th>Active Clients</th><th>Personnel Volume</th><th>Product Catalog</th><th>Total Alpha (Rev)</th></tr>`;
            
            dataArray.forEach(item => {
                htmlBody += `
                <tr>
                    <td class="fw-bold text-dark"><i class="bi bi-hdd-network me-2 text-muted"></i>${cleanHTML(item.branch_name)}</td>
                    <td class="ledger-num">${parseInt(item.total_students||0)}</td>
                    <td class="ledger-num">${parseInt(item.total_staff||0)}</td>
                    <td class="ledger-num">${parseInt(item.total_courses||0)}</td>
                    <td class="ledger-num ledger-success">${formatCurrency(item.total_revenue)}</td>
                </tr>`;
            });
        }

        h.innerHTML = htmlHead;
        b.innerHTML = htmlBody;

        if (dataArray.length > 0) initDT();
    };

    // -- Export Module --
    const triggerExport = () => {
        const rows = [];
        const hText = [];
        document.querySelectorAll('#ledgerHead th').forEach(th => hText.push('"' + th.innerText.trim() + '"'));
        rows.push(hText.join(','));

        document.querySelectorAll('#ledgerBody tr').forEach(tr => {
            const rowData = [];
            tr.querySelectorAll('td').forEach(td => {
                rowData.push('"' + td.innerText.trim().replace(/"/g, '""') + '"');
            });
            if(rowData.length > 0) rows.push(rowData.join(','));
        });

        if (rows.length <= 1) return Swal.fire('Export Denied', 'No ledger data available to compile.', 'warning');

        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const objUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = objUrl;
        a.download = `FT_Ledger_${currentMode}_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(objUrl);
    };

    // -- Listeners & Bootsrap --
    const attachListeners = () => {
        // Form Sync
        document.getElementById('reportEngineForm').addEventListener('submit', (e) => {
            e.preventDefault();
            syncData();
        });

        // Tabs
        document.querySelectorAll('.ft-segment').forEach(seg => {
            seg.addEventListener('click', (e) => {
                document.querySelectorAll('.ft-segment').forEach(s => s.classList.remove('active'));
                const el = e.currentTarget;
                el.classList.add('active');
                currentMode = el.getAttribute('data-engine-mode');
                syncData();
            });
        });

        // Export
        document.getElementById('exportLedgerBtn').addEventListener('click', triggerExport);

        // Live Stream
        document.getElementById('liveStreamToggle').addEventListener('change', (e) => {
            if (e.target.checked) {
                syncData(true);
                liveStreamTimer = setInterval(() => syncData(true), 12000); // 12s polling
            } else {
                clearInterval(liveStreamTimer);
                liveStreamTimer = null;
            }
        });
    };

    return {
        init: () => {
            attachListeners();
            syncData();
        }
    };
})();

document.addEventListener('DOMContentLoaded', ReportEngine.init);
</script>
</body>
</html>
<?php ob_end_flush(); ?>