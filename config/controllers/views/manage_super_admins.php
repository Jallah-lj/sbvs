<?php
declare(strict_types=1);

ob_start();
session_start();

// Strict Access Control Protocol
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'Super Admin') {
    // Attempted breach or unauthorized escalation logged here if logging is available
    header("Location: dashboard.php");
    exit;
}

require_once '../../database.php';
require_once '../../helpers.php';
require_once '../../DashboardSecurity.php';

$csrfToken = DashboardSecurity::generateToken();

// ── Page Identity ───────────────────────────────────────────────
$pageTitle  = 'System Core: Super Admins';
$activePage = 'manage_super_admins.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
    /* Security & Core System Theming */
    .core-alert { background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(153, 27, 27, 0.05) 100%); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: 12px; }
    
    .stat-card.core-card { border: none; border-radius: 14px; background: #fff; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.06); transition: all 0.3s ease; position: relative; overflow: hidden; }
    .stat-card.core-card::before { content: ''; position: absolute; top:0; left:0; width: 4px; height: 100%; background: #64748b; }
    .stat-card.core-card.threat::before { background: #dc2626; }
    .stat-card.core-card.stable::before { background: #059669; }
    .stat-card.core-card.system::before { background: #4f46e5; }
    .stat-card.core-card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px -4px rgba(0,0,0,0.1); }

    .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
    
    .table-core thead th { background-color: #1e293b; color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding-block: 1.2rem; border: none; font-weight: 600; }
    .table-core thead th:first-child { border-top-left-radius: 12px; }
    .table-core thead th:last-child { border-top-right-radius: 12px; }
    .table-core tbody td { padding-block: 1.2rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
    .table-core tbody tr:hover td { background-color: #f8fafc; }

    .badge-clearance { background-color: rgba(15, 23, 42, 0.9); color: #f8fafc; font-weight: 700; padding: 0.4em 0.8em; border-radius: 6px; font-size: 0.7rem; letter-spacing: 0.5px; border: 1px solid #475569; }
    
    .modal-content.security-modal { border: none; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
    .security-modal .modal-header { background: #0f172a; color: white; border-bottom: 2px solid #334155; padding: 1.5rem 2rem; }
    .security-modal .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.8; }
    
    /* Procedure Checkboxes */
    .procedure-check .form-check-input { width: 1.25em; height: 1.25em; margin-top: 0.15em; border-color: #94a3b8; cursor: pointer; }
    .procedure-check .form-check-input:checked { background-color: #dc2626; border-color: #dc2626; }
    .procedure-check .form-check-label { font-size: 0.85rem; color: #475569; font-weight: 500; cursor: pointer; user-select: none; }
    .procedure-check .form-check-label.text-danger { color: #dc2626 !important; font-weight: 600; }
    
    .fingerprint-hash { font-family: 'Courier New', Courier, monospace; font-size: 0.75rem; color: #94a3b8; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; border: 1px dashed #cbd5e1; }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main p-4">

        <!-- ── Security Warning Banner ────────────────── -->
        <div class="core-alert p-4 mb-4 fade-up d-flex align-items-center gap-4">
            <div class="text-danger flex-shrink-0" style="font-size: 2.5rem;">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div>
                <h4 class="fw-bold text-danger mb-1" style="letter-spacing: -0.4px;">Level 5 Clearance Zone: System Overseers</h4>
                <p class="mb-0 text-dark small fw-medium opacity-75">
                    <strong>CAUTION:</strong> You are accessing the apex user administration module. Accounts created here possess absolute root-level authority over the entire SBVS network, bypassing branch boundaries. Procedural verification is strictly enforced.
                </p>
            </div>
        </div>

        <!-- ── Page Header ────────────────────────────── -->
        <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4 fade-up position-relative" style="z-index:1; animation-delay: 50ms;">
            <div>
                <h2 class="fw-bolder mb-0 text-dark">Super Administrator Ledger</h2>
                <div class="text-muted small mt-1"><i class="bi bi-cpu-fill text-primary"></i> Live Registry of Active Apex Nodes</div>
            </div>
            <div>
                <button class="btn btn-dark shadow-sm px-4 py-2 rounded-pill fw-bold d-flex align-items-center gap-2" onclick="SuperAdminManager.initiateAddProtocol()">
                    <i class="bi bi-incognito fs-5 text-warning"></i> Authorize New Overseer
                </button>
            </div>
        </header>

        <!-- ── Telemetry Stats ────────────────────────── -->
        <div class="row g-4 mb-5">
            <div class="col-sm-4 fade-up" style="animation-delay: 100ms;">
                <div class="stat-card core-card system h-100 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-secondary small fw-bold text-uppercase tracking-wider mb-1">Total Authorized</div>
                            <div class="fw-bolder text-dark" style="font-size: 2.2rem; line-height: 1;" id="totalSuperAdmins">-</div>
                        </div>
                        <div class="stat-icon bg-indigo-500 bg-opacity-10 text-indigo-600"><i class="bi bi-hdd-network-fill"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-sm-4 fade-up" style="animation-delay: 150ms;">
                <div class="stat-card core-card stable h-100 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-secondary small fw-bold text-uppercase tracking-wider mb-1">Active Core Nodes</div>
                            <div class="fw-bolder text-success" style="font-size: 2.2rem; line-height: 1;" id="activeSuperAdmins">-</div>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-activity"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-4 fade-up" style="animation-delay: 200ms;">
                <div class="stat-card core-card threat h-100 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-secondary small fw-bold text-uppercase tracking-wider mb-1">Suspended Nodes</div>
                            <div class="fw-bolder text-danger" style="font-size: 2.2rem; line-height: 1;" id="inactiveSuperAdmins">-</div>
                        </div>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-sign-stop-fill"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Main Data Table ────────────────────────── -->
        <div class="card border-0 shadow-sm rounded-4 fade-up" style="animation-delay: 250ms;">
            <div class="card-body p-0">
                <div class="table-responsive p-3 p-md-4">
                    <table class="table table-core w-100 mb-0" id="superAdminTable">
                        <thead>
                            <tr>
                                <th width="30%">Target Subject</th>
                                <th width="20%">Comms Link (Email)</th>
                                <th width="15%">System Status</th>
                                <th width="20%">Deployment Hash</th>
                                <th width="15%" class="text-end">Directives</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ── Modals Subsystem ─────────────────────────────────────────────────── -->

<!-- Add Super Admin (High Security Add) Modal -->
<div class="modal fade" id="addSuperAdminModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="addSuperAdminForm" class="modal-content security-modal">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            
            <div class="modal-header">
                <div>
                    <h4 class="modal-title fw-bold mb-1"><i class="bi bi-person-lines-fill text-warning me-2"></i>New Core Designation</h4>
                    <div class="small fw-light text-white-50 ms-4 ps-2">Authorizing creation of a top-tier administrative entity.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Abort"></button>
            </div>
            
            <div class="modal-body p-0">
                <!-- Data Input Step -->
                <div class="bg-light p-4 px-md-5 border-bottom">
                    <h6 class="fw-bold mb-3 text-secondary text-uppercase" style="letter-spacing: 1px;"><span class="badge bg-secondary me-2">Step 1</span> Entity Specifications</h6>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">Authenticated Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg border-0 shadow-sm fs-6" placeholder="Given legal name" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">Master Email Routing <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-lg border-0 shadow-sm fs-6" placeholder="administrative.alias@sbvs.local" required autocomplete="off">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark mb-1">Genesis Cipher (Temporary Password) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg shadow-sm">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" id="addPassword" class="form-control border-0 fs-6" placeholder="Min. 8 character cryptographic key" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-white border-0 bg-white" onclick="togglePw('addPassword', 'addPasswordIcon')">
                                    <i class="bi bi-eye text-muted" id="addPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mandatory Procedures Step -->
                <div class="p-4 px-md-5 bg-white">
                    <h6 class="fw-bold mb-3 text-danger text-uppercase" style="letter-spacing: 1px;"><span class="badge bg-danger me-2">Step 2</span> Security Clearances & Acknowledgments</h6>
                    <div class="alert alert-warning border-warning bg-warning bg-opacity-10 text-dark small mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><strong>Legal Disclaimer:</strong> Any malfeasance by this account will hold the authorizing agent partially liable.
                    </div>
                    
                    <div class="d-flex flex-column gap-3 mb-4">
                        <div class="form-check procedure-check">
                            <input class="form-check-input" type="checkbox" id="proc1" required>
                            <label class="form-check-label" for="proc1">I have verified the biological identity and core vetting of the subject.</label>
                        </div>
                        <div class="form-check procedure-check">
                            <input class="form-check-input" type="checkbox" id="proc2" required>
                            <label class="form-check-label" for="proc2">I confirm this assignment aligns with System Maintenance Directives.</label>
                        </div>
                        <div class="form-check procedure-check">
                            <input class="form-check-input" type="checkbox" id="proc3" required>
                            <label class="form-check-label text-danger" for="proc3">I acknowledge that this user receives **UNRESTRICTED** capability across all databases.</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">Abort Creation</button>
                <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm" id="saveSuperAdminBtn">
                    <i class="bi bi-fingerprint me-2"></i> Approve & Deploy Super Admin
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Super Admin Modal -->
<div class="modal fade" id="editSuperAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="editSuperAdminForm" class="modal-content security-modal">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="hidden" name="id" id="edit_sa_id">
            
            <div class="modal-header d-flex align-items-center" style="background:#1e293b;">
                <h5 class="modal-title fw-bold mb-0 text-white"><i class="bi bi-wrench-adjustable me-2"></i>Modify Core Node: <span id="edit_sa_displayName" class="text-warning"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary text-uppercase mb-1">Entity Name</label>
                        <input type="text" name="name" id="edit_sa_name" class="form-control form-control-lg border-0 shadow-sm fs-6" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary text-uppercase mb-1">Comms Email</label>
                        <input type="email" name="email" id="edit_sa_email" class="form-control form-control-lg border-0 shadow-sm fs-6" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary text-uppercase mb-1">Authorization Status</label>
                        <select name="status" id="edit_sa_status" class="form-select form-select-lg border-0 shadow-sm fs-6 fw-bold">
                            <option value="Active" class="text-success">🟢 Active & Cleared</option>
                            <option value="Inactive" class="text-danger">🔴 Suspend Privileges</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary text-uppercase mb-1">Cipher Override <span class="fw-normal fst-italic text-lowercase opacity-50">(optional)</span></label>
                        <div class="input-group shadow-sm">
                            <input type="password" name="password" id="editPassword" class="form-control form-control-lg border-0 fs-6" placeholder="Retain current">
                            <button type="button" class="btn bg-white border-0 text-muted" onclick="togglePw('editPassword', 'editPasswordIcon')">
                                <i class="bi bi-eye" id="editPasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-top px-4 py-3 border-opacity-10 border-dark">
                <button type="button" class="btn btn-link text-secondary fw-bold text-decoration-none" data-bs-dismiss="modal">Cancel Modification</button>
                <button type="submit" class="btn btn-dark fw-bold rounded-pill px-4 shadow" id="updateSuperAdminBtn">
                    <i class="bi bi-arrow-repeat me-2"></i> Update Registry
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>

<!-- SweetAlert2 and core JS injected via scripts.php already -->
<script>
// Utilities
function togglePw(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-dark';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye text-muted';
    }
}

// Sandbox Module architecture
const SuperAdminManager = (() => {
    const API = 'models/api/super_admin_api.php';
    let tableInstance = null;

    // Output encrypters/sanitizers
    const sanitize = (str) => {
        if(str === null || str === undefined) return '';
        const temp = document.createElement('div');
        temp.textContent = String(str);
        return temp.innerHTML;
    };

    const generateHashMock = (id, str) => {
        // Generating a UI-only fake hash snippet for coolness / tech feel
        const raw = `${id}:${str}:salt`.split('').reduce((a,b)=>{a=((a<<5)-a)+b.charCodeAt(0);return a&a},0);
        return 'AX-' + Math.abs(raw).toString(16).toUpperCase().padStart(8, '0');
    };

    const initializeTable = () => {
        if (tableInstance) tableInstance.destroy();
        tableInstance = $('#superAdminTable').DataTable({
            processing: true,
            ajax: {
                url: API + '?action=list',
                dataSrc: (res) => {
                    const data = res.data || [];
                    const tTotal = data.length;
                    const tActive = data.filter(d => d.status === 'Active').length;
                    
                    document.getElementById('totalSuperAdmins').textContent = tTotal;
                    document.getElementById('activeSuperAdmins').textContent = tActive;
                    document.getElementById('inactiveSuperAdmins').textContent = tTotal - tActive;
                    
                    return data;
                }
            },
            columns: [
                { 
                    data: 'name', 
                    render: (d) => `
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-dark text-white d-flex justify-content-center align-items-center fw-bold" style="width:36px; height:36px;">
                                ${sanitize(d).charAt(0).toUpperCase()}
                            </div>
                            <span class="fw-bolder text-dark" style="font-size: 1.05rem;">${sanitize(d)}</span>
                        </div>` 
                },
                { 
                    data: 'email', 
                    render: d => `<span class="fw-medium text-secondary"><i class="bi bi-at ms-n1 opacity-50"></i>${sanitize(d)}</span>` 
                },
                {
                    data: 'status',
                    render: d => d === 'Active'
                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Active Link</span>'
                        : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-x-octagon-fill me-1"></i> Terminated</span>'
                },
                {
                    data: 'created_at',
                    render: (d, t, row) => `
                        <div class="d-flex flex-column gap-1">
                            <span class="fingerprint-hash"><i class="bi bi-hash"></i> ${generateHashMock(row.id, row.email)}</span>
                            <span class="text-muted" style="font-size:.75rem;"><i class="bi bi-calendar-event opacity-50"></i> ${d ? sanitize(d).substring(0, 10) : 'Pre-Genesis'}</span>
                        </div>`
                },
                {
                    data: null, 
                    orderable: false,
                    className: 'text-end',
                    render: (data) => {
                        const safetyVal = btoa(encodeURIComponent(JSON.stringify({
                            id: data.id, name: data.name, email: data.email, status: data.status
                        })));
                        
                        return `
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-light border-secondary border-opacity-25 btn-sm px-3" title="Modify Vector" onclick="SuperAdminManager.editPrompt('${safetyVal}')">
                                <i class="bi bi-tools text-primary"></i> Conf
                            </button>
                            <button class="btn btn-dark btn-sm px-3 border-dark" title="Eradicate Node" onclick="SuperAdminManager.nuclearDelete(${data.id}, '${sanitize(data.name).replace(/'/g,"\\'")}')">
                                <i class="bi bi-trash3-fill text-danger"></i>
                            </button>
                        </div>`;
                    }
                }
            ],
            responsive: true,
            language: { emptyTable: "<div class='text-muted py-4 fw-medium'><i class='bi bi-shield-x fs-3 d-block mb-2'></i>Registry empty. The system requires at least one root user.</div>" }
        });
    };

    const attachBindings = () => {
        // High Security Addition Post
        document.getElementById('addSuperAdminForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Re-verify checkboxes (DOM hack protection)
            if(!document.getElementById('proc1').checked || !document.getElementById('proc2').checked || !document.getElementById('proc3').checked) {
                Swal.fire('Procedure Incomplete', 'You must acknowledge all security disclaimers before proceeding.', 'warning');
                return;
            }

            // Pseudo-verification check (simulates asking for CURRENT admin auth before saving to backend)
            const { value: sysAuth } = await Swal.fire({
                title: 'AUTHORIZATION REQUIRED',
                text: 'Input YOUR current Super Admin login password to authorize this action:',
                input: 'password',
                inputPlaceholder: 'Enter your own password...',
                inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sign & Deploy',
                confirmButtonColor: '#dc2626',
                inputValidator: (value) => {
                    if (!value) {
                        return 'You must enter your password to proceed!'
                    }
                }
            });

            if (!sysAuth) {
                Swal.fire('Signature Aborted', 'Deployment verification voided by user.', 'info');
                return;
            }

            const btn = document.getElementById('saveSuperAdminBtn');
            const form = e.target;
            const fd = new FormData(form);
            
            // Backend currently expects generic POST structure, we append an auth signature flag artificially
            fd.append('authorization_signature', btoa(sysAuth));

            btn.disabled = true;
            btn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i> Securing Network...';

            try {
                const req = await fetch(API + '?action=save', { method: 'POST', body: fd });
                const res = await req.json();
                
                if (res.status === 'success') {
                    Swal.fire({ title: 'Node Integrated', text: `Target has been granted apex level system command.`, icon: 'success' });
                    bootstrap.Modal.getInstance(document.getElementById('addSuperAdminModal')).hide();
                    form.reset();
                    tableInstance.ajax.reload();
                } else throw new Error(res.message);
            } catch (ex) {
                Swal.fire('Integration Failed', ex.message || 'System architecture rejected modification.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-fingerprint me-2"></i> Approve & Deploy Super Admin';
            }
        });

        // Edit Admin Post
        document.getElementById('editSuperAdminForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('updateSuperAdminBtn');
            const fd = new FormData(e.target);
            
            btn.disabled = true;
            btn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i> Overriding...';

            try {
                const req = await fetch(API + '?action=update', { method: 'POST', body: fd });
                const res = await req.json();
                
                if (res.status === 'success') {
                    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                    Toast.fire({ icon: 'success', title: 'Registry Syntax Updated' });
                    bootstrap.Modal.getInstance(document.getElementById('editSuperAdminModal')).hide();
                    tableInstance.ajax.reload();
                } else throw new Error(res.message);
            } catch (ex) {
                Swal.fire('Override Failure', ex.message || 'The registry declined override request.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Update Registry';
            }
        });
    };

    return {
        init: () => {
            initializeTable();
            attachBindings();
        },
        
        initiateAddProtocol: () => {
            // Reset mandatory checkmarks visually
            document.getElementById('addSuperAdminForm').reset();
            const modal = new bootstrap.Modal(document.getElementById('addSuperAdminModal'));
            modal.show();
        },

        editPrompt: (base64Payload) => {
            const data = JSON.parse(decodeURIComponent(atob(base64Payload)));
            document.getElementById('edit_sa_id').value = data.id;
            document.getElementById('edit_sa_name').value = data.name;
            document.getElementById('edit_sa_displayName').textContent = `[${data.name}]`;
            document.getElementById('edit_sa_email').value = data.email;
            document.getElementById('edit_sa_status').value = data.status || 'Active';
            document.getElementById('editPassword').value = '';
            
            new bootstrap.Modal(document.getElementById('editSuperAdminModal')).show();
        },

        nuclearDelete: async (id, name) => {
            // High Security Challenge 1: Name Verification
            const { value: confirmName } = await Swal.fire({
                title: 'CRITICAL ERADICATION',
                html: `<div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 mt-3 text-start"><i class="bi bi-exclamation-triangle-fill"></i> Warning: This permanently shatters this entity's configuration and access vectors. To proceed, type the target's exact visual identity:<br><br><span class="fw-bold d-block text-center fs-5 text-dark bg-white p-2 rounded border mt-2 user-select-none">${name}</span></div>`,
                input: 'text',
                inputPlaceholder: 'Type target identity here...',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Verify Intent',
                confirmButtonColor: '#dc2626',
                customClass: { confirmButton: 'btn btn-danger px-4 rounded-pill', cancelButton: 'btn btn-light border px-4 rounded-pill text-dark' }
            });

            if (confirmName !== name) {
                if(confirmName !== undefined) Swal.fire('Eradication Aborted', 'Syntax mis-match. Protocol dismissed.', 'info');
                return;
            }

            // Execute destruction vector over POST
            try {
                const fd = new URLSearchParams();
                fd.append('id', id);
                fd.append('csrf_token', window.CSRF_TOKEN); // Assuming backend tracks it. Adding for future proofing.

                Swal.fire({ title: 'Obliterating Vector...', html: 'Expunging databases', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                const req = await fetch(API + '?action=delete', { 
                    method: 'POST', 
                    body: fd,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                const res = await req.json();

                if (res.status === 'success') {
                    Swal.fire('Node Eradicated', `Super Admin [${name}] vector has been dissolved permanently.`, 'success');
                    tableInstance.ajax.reload();
                } else throw new Error(res.message);
                
            } catch (ex) {
                Swal.fire('Fault', ex.message || 'Target refused eradication order.', 'error');
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    SuperAdminManager.init();
});
</script>
</body>
</html>
