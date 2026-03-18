<?php
/**
 * Instructor Approval Dashboard
 *
 * Displays pending instructor submissions (resource links, attendance logs, competency matrices)
 * with inline approve/reject actions. Access restricted to Super Admin, Branch Admin, and Admin roles.
 *
 * @author  System
 * @version 2.0
 * @package SBVS\Controllers\Views
 */

ob_start();
session_start();

// ── SECURITY: Verify authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// ── SECURITY: Role-based access control
require_once '../../DashboardSecurity.php';
$role = $_SESSION['role'] ?? '';
$isApprovalRole = in_array($role, ['Super Admin', 'Branch Admin', 'Admin'], true);
if (!$isApprovalRole) {
    header("Location: dashboard.php");
    exit;
}

// ── Initialize page variables
$csrfToken = DashboardSecurity::generateToken();
$pageTitle = 'Approval Triage';
$activePage = 'instructor_approval_dashboard.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
    /* ── Triage/Workflow Theme (Strict, Data-Dense, Action-Oriented) ── */
    :root {
        --triage-surface: #ffffff;
        --triage-bg: #f1f5f9;
        --triage-border: #e2e8f0;
        --triage-accent: #0f172a;
        --queue-hover: #f8fafc;
        --status-pending: #f59e0b;
        --status-approved: #10b981;
        --status-rejected: #ef4444;
    }

    body {
        background-color: var(--triage-bg);
    }

    .triage-header {
        background: var(--triage-surface);
        border-bottom: 1px solid var(--triage-border);
        padding: 1.5rem 2rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }

    .triage-layout {
        display: flex;
        height: calc(100vh - 180px);
        min-height: 600px;
        background: var(--triage-surface);
        border-radius: 12px;
        border: 1px solid var(--triage-border);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    /* Queue Pane (Left) */
    .pane-queue {
        width: 40%;
        min-width: 350px;
        border-right: 1px solid var(--triage-border);
        display: flex;
        flex-direction: column;
        background: #fbfbfc;
    }
    
    .queue-filters {
        padding: 1rem;
        border-bottom: 1px solid var(--triage-border);
        background: var(--triage-surface);
    }

    .queue-list {
        flex: 1;
        overflow-y: auto;
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .queue-item {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--triage-border);
        cursor: pointer;
        transition: all 0.2s;
        border-left: 4px solid transparent;
        background: var(--triage-surface);
    }
    
    .queue-item:hover {
        background: var(--queue-hover);
    }
    
    .queue-item.active {
        background: var(--triage-accent);
        color: white;
        border-left-color: #38bdf8;
    }
    .queue-item.active .text-muted { color: #94a3b8 !important; }
    .queue-item.active .status-dot { box-shadow: 0 0 0 2px rgba(255,255,255,0.2) !important; }

    /* Review Docket (Right) */
    .pane-docket {
        width: 60%;
        display: flex;
        flex-direction: column;
        background: var(--triage-surface);
    }

    .docket-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--triage-border);
    }

    .docket-body {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
    }

    .docket-footer {
        padding: 1.25rem 2rem;
        border-top: 1px solid var(--triage-border);
        background: #fafafa;
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }

    /* Status Indicators */
    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
    }
    .status-Pending { background: var(--status-pending); }
    .status-Approved { background: var(--status-approved); }
    .status-Rejected { background: var(--status-rejected); }

    .status-badge {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
    }
    .status-badge.Pending { background: #fef3c7; color: #b45309; }
    .status-badge.Approved { background: #d1fae5; color: #047857; }
    .status-badge.Rejected { background: #fee2e2; color: #b91c1c; }

    /* Payload Datagrid */
    .payload-grid {
        background: #f8fafc;
        border: 1px solid var(--triage-border);
        border-radius: 8px;
        overflow: hidden;
    }
    .payload-row {
        display: flex;
        border-bottom: 1px solid #e2e8f0;
    }
    .payload-row:last-child { border-bottom: none; }
    .payload-key {
        width: 30%;
        padding: 0.75rem 1rem;
        background: #f1f5f9;
        font-weight: 600;
        color: #475569;
        font-size: 0.85rem;
        text-transform: uppercase;
        border-right: 1px solid #e2e8f0;
    }
    .payload-val {
        width: 70%;
        padding: 0.75rem 1rem;
        color: #0f172a;
        font-family: inherit;
        font-size: 0.95rem;
        word-break: break-word;
    }

    /* Custom Scrollbar for Panes */
    .pane-queue::-webkit-scrollbar, .docket-body::-webkit-scrollbar { width: 6px; }
    .pane-queue::-webkit-scrollbar-track, .docket-body::-webkit-scrollbar-track { background: transparent; }
    .pane-queue::-webkit-scrollbar-thumb, .docket-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

    /* Empty States */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
        padding: 3rem;
        text-align: center;
    }
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main p-0"> 
    
    <!-- ── Header ── -->
    <div class="triage-header fade-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="fw-bolder mb-1 text-dark" style="letter-spacing: -0.5px;">
                    <i class="bi bi-inboxes text-primary me-2"></i>Approval Console
                </h3>
                <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">
                    Centralized triage for instructor submissions, requests, and structural updates.
                </p>
            </div>
            <div class="d-flex align-items-center gap-3 bg-light px-3 py-2 rounded-3 border">
                <span class="fw-bold text-dark fs-5" id="pendingCountBadge">0</span>
                <span class="text-muted fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Awaiting Review</span>
            </div>
        </div>
    </div>

    <!-- ── Triage Workspace ── -->
    <div class="p-4 fade-up" style="animation-delay: 100ms;">
        <div class="triage-layout">
            
            <!-- ── Left: Request Queue ── -->
            <div class="pane-queue">
                <div class="queue-filters d-flex align-items-center justify-content-between gap-2">
                    <span class="fw-bold text-secondary small text-uppercase" style="letter-spacing: 1px;">Task Inbox</span>
                    <select id="statusFilter" class="form-select form-select-sm border-secondary shadow-none fw-bold" style="width: 140px;">
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="">All History</option>
                    </select>
                </div>
                <div id="queueList" class="queue-list">
                    <!-- Queue Items injected via JS -->
                </div>
            </div>

            <!-- ── Right: Docket Review ── -->
            <div class="pane-docket" id="docketContainer">
                <div class="empty-state">
                    <i class="bi bi-file-earmark-medical empty-icon"></i>
                    <h5 class="fw-bold text-secondary">No Request Selected</h5>
                    <p class="small">Choose a task from the inbox to review its payload and determine access.</p>
                </div>
            </div>

        </div>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>

<script>
/**
 * TriageCenter - Workflow Module
 * Encapsulated logic for the strict Approval Dashboard UI.
 */
const TriageCenter = (() => {
    const API_TARGET = 'models/api/instructor_panel_api.php';
    const csrfToken = <?= json_encode($csrfToken) ?>;
    
    let cacheQueue = [];
    let currentTask = null;

    // -- Utilities --
    const safeText = (txt) => {
        const span = document.createElement('span');
        span.textContent = txt ?? '';
        return span.innerHTML;
    };

    const formatTimestamp = (dateString) => {
        if (!dateString) return 'Unknown Time';
        const d = new Date(dateString);
        return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
    };

    const parseJSON = (str) => {
        try { return JSON.parse(str); } 
        catch (e) { return {}; }
    };

    // -- Renderers --
    const renderPayloadGrid = (payloadObj) => {
        if (!payloadObj || Object.keys(payloadObj).length === 0) {
            return `<div class="p-3 text-muted text-center small border rounded bg-light">No structural payload provided.</div>`;
        }
        let html = '<div class="payload-grid">';
        for (const [key, value] of Object.entries(payloadObj)) {
            const cleanKey = safeText(key).replace(/_/g, ' ');
            const cleanVal = typeof value === 'object' ? '<pre class="m-0" style="font-size:0.75rem;">' + JSON.stringify(value, null, 2) + '</pre>' : safeText(value);
            html += `
                <div class="payload-row">
                    <div class="payload-key">${cleanKey}</div>
                    <div class="payload-val">${cleanVal}</div>
                </div>
            `;
        }
        html += '</div>';
        return html;
    };

    const loadInbox = async () => {
        const stat = document.getElementById('statusFilter').value;
        const qList = document.getElementById('queueList');
        
        qList.innerHTML = `<div class="p-4 text-center text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Syncing queue...</div>`;
        
        try {
            const formData = new URLSearchParams({ action: 'admin_pending_queue', status: stat });
            const response = await fetch(`${API_TARGET}?${formData.toString()}`);
            const data = await response.json();
            
            if (!data.success) throw new Error(data.message || "Failed to load");
            
            cacheQueue = data.data || [];
            
            const pendingCount = cacheQueue.filter(r => r.status === 'Pending').length;
            document.getElementById('pendingCountBadge').textContent = pendingCount;
            
            if (cacheQueue.length === 0) {
                qList.innerHTML = `<div class="p-5 text-center text-muted"><i class="bi bi-check2-all fs-1 d-block mb-3 opacity-25"></i>Inbox Empty</div>`;
                renderDocket(null);
                return;
            }
            
            drawQueueElements();
            
            // Auto-select behavior
            if (currentTask && cacheQueue.find(t => Number(t.id) === Number(currentTask.id))) {
                selectTask(currentTask.id);
            } else {
                selectTask(cacheQueue[0].id);
            }
            
        } catch (err) {
            qList.innerHTML = `<div class="p-4 text-center text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> ${err.message}</div>`;
        }
    };

    const drawQueueElements = () => {
        const qList = document.getElementById('queueList');
        qList.innerHTML = '';
        
        cacheQueue.forEach(task => {
            const item = document.createElement('div');
            const isActive = currentTask && currentTask.id === task.id ? 'active' : '';
            item.className = `queue-item ${isActive}`;
            item.onclick = () => selectTask(task.id);
            item.id = `qItem_${task.id}`;
            
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="fw-bold d-flex align-items-center gap-2">
                        <span class="status-dot status-${task.status}"></span>
                        <span class="text-truncate" style="max-width:200px;">${safeText(task.request_type)}</span>
                    </div>
                    <span class="small opacity-75">#${task.id}</span>
                </div>
                <div class="small fw-medium mb-1"><i class="bi bi-person me-1"></i> ${safeText(task.submitted_by_name)}</div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><i class="bi bi-clock me-1"></i> ${formatTimestamp(task.created_at)}</span>
                    <span class="status-badge ${task.status}">${task.status}</span>
                </div>
            `;
            qList.appendChild(item);
        });
    };

    const selectTask = (taskId) => {
        currentTask = cacheQueue.find(t => Number(t.id) === Number(taskId));
        drawQueueElements(); // Re-render to update active classes
        renderDocket(currentTask);
    };

    const renderDocket = (task) => {
        const docket = document.getElementById('docketContainer');
        if (!task) {
            docket.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-file-earmark-medical empty-icon"></i>
                    <h5 class="fw-bold text-secondary">No Task Selected</h5>
                    <p class="small">Choose a request from the queue to process.</p>
                </div>
            `;
            return;
        }

        const payloadObj = parseJSON(task.payload_json);
        const isPending = task.status === 'Pending';
        
        let metadataHTML = `
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Target Entity</div>
                    <div class="fw-medium">${safeText(task.entity_table)} (ID: ${task.entity_id})</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Assigned Campus</div>
                    <div class="fw-medium">${safeText(task.branch_name || 'Global / Unassigned')}</div>
                </div>
            </div>
        `;

        if (task.status === 'Rejected') {
            metadataHTML += `
                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 pb-3">
                    <div class="fw-bold text-danger small text-uppercase mb-1"><i class="bi bi-x-octagon-fill me-1"></i> Rejection Rationale</div>
                    <div class="text-dark">${safeText(task.rejection_reason || 'No explicit reason provided.')}</div>
                </div>
            `;
        }

        const footerActionHTML = isPending ? `
            <div class="flex-grow-1">
                <input type="text" id="actionReason" class="form-control form-control-sm border-secondary shadow-none bg-white" placeholder="Optional rationale (Required if Rejecting)..." style="height: 38px;">
            </div>
            <button class="btn btn-danger fw-bold shadow-sm px-4" onclick="TriageCenter.executeDecision(${task.id}, 'Rejected')" style="height: 38px;">
                <i class="bi bi-dash-circle me-1"></i> Reject
            </button>
            <button class="btn btn-success fw-bold shadow-sm px-4" onclick="TriageCenter.executeDecision(${task.id}, 'Approved')" style="height: 38px;">
                <i class="bi bi-check2-circle me-1"></i> Authorize
            </button>
        ` : `
            <div class="w-100 text-center text-muted fw-semibold small">
                <i class="bi bi-lock-fill me-1"></i> This workflow docket is sealed with status: <span class="status-badge ${task.status} ms-1">${task.status}</span>
            </div>
        `;

        docket.innerHTML = `
            <!-- Header -->
            <div class="docket-header d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge ${task.status === 'Pending' ? 'bg-warning text-dark' : (task.status === 'Approved' ? 'bg-success' : 'bg-danger')} mb-2">${task.status}</span>
                    <h4 class="fw-bolder mb-1">${safeText(task.request_type)}</h4>
                    <div class="text-muted small">Initiated by <strong>${safeText(task.submitted_by_name)}</strong> &bull; ${formatTimestamp(task.created_at)}</div>
                </div>
                <div class="bg-light border rounded px-3 py-2 text-center shadow-sm">
                    <div class="small fw-bold text-muted text-uppercase mb-1">Record ID</div>
                    <h5 class="mb-0 text-dark font-monospace">${task.id}</h5>
                </div>
            </div>

            <!-- Body -->
            <div class="docket-body">
                ${metadataHTML}
                <div class="fw-bold mb-3 text-dark text-uppercase small" style="letter-spacing:1px; border-bottom:2px solid #e2e8f0; padding-bottom:5px;">Transmission Payload</div>
                ${renderPayloadGrid(payloadObj)}
            </div>

            <!-- Footer / Controls -->
            <div class="docket-footer shadow-sm">
                ${footerActionHTML}
            </div>
        `;
    };

    const executeDecision = async (id, decision) => {
        const reasonInput = document.getElementById('actionReason');
        const reason = reasonInput ? reasonInput.value.trim() : '';

        if (decision === 'Rejected' && !reason) {
            Swal.fire('Rationale Required', 'A rejection requires a mandatory reason for the audit logs.', 'warning');
            if(reasonInput) reasonInput.focus();
            return;
        }

        try {
            const fd = new URLSearchParams();
            fd.append('action', 'review_request');
            fd.append('csrf_token', csrfToken);
            fd.append('request_id', id);
            fd.append('status', decision);
            fd.append('rejection_reason', reason);

            const res = await fetch(API_TARGET, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
                    .fire({ icon: 'success', title: `Workflow ${decision}` });
                currentTask = null; // Clear context so it jumps to next
                loadInbox();
            } else {
                throw new Error(data.message || 'Validation failed');
            }
        } catch (err) {
            Swal.fire('Transaction Halted', err.message, 'error');
        }
    };

    return {
        init: () => {
            document.getElementById('statusFilter').addEventListener('change', loadInbox);
            loadInbox();
            setInterval(loadInbox, 15000); // 15s refetch
        },
        executeDecision
    };
})();

document.addEventListener('DOMContentLoaded', TriageCenter.init);
</script>
</body>
</html>
<?php ob_end_flush(); ?>
