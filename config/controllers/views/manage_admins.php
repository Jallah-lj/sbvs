<?php
declare(strict_types=1);

ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

require_once '../../database.php';
require_once '../../helpers.php';
require_once '../../DashboardSecurity.php';

try {
    $db = (new Database())->getConnection();
    // Fetch active branches for assignment
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
    error_log("Manage Admins DB Error: " . $e->getMessage());
}

$csrfToken = DashboardSecurity::generateToken();

// ── Page Identity ───────────────────────────────────────────────
$pageTitle  = 'Manage Admins';
$activePage = 'manage_admins.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
    /* Modern Adjustments & Enhancements */
    .stat-card { border: none; border-radius: 12px; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .stat-label { font-size: 0.85rem; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

    .table thead th { background-color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding-block: 1rem; border-bottom: 2px solid #e2e8f0; }
    .table tbody td { padding-block: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    
    .badge-soft-success { background-color: #dcfce7; color: #059669; font-weight: 600; padding: 0.35em 0.65em; border-radius: 6px; }
    .badge-soft-danger { background-color: #fee2e2; color: #dc2626; font-weight: 600; padding: 0.35em 0.65em; border-radius: 6px; }
    .badge-soft-primary { background-color: #e0e7ff; color: #4f46e5; font-weight: 600; padding: 0.35em 0.65em; border-radius: 6px; }

    .modal-content { border: none; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
    .form-control, .form-select { border-radius: 8px; padding: 0.6rem 1rem; border-color: #cbd5e1; }
    .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); border-color: #6366f1; }
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
    <main class="sbvs-main p-4">

        <!-- ── Page Header ────────────────────────────── -->
        <header class="page-header fade-up mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-indigo-500"></i> Branch Admin Management
                </h4>
                <p class="mb-0 text-muted small">Create, track, and manage all branch administrator accounts and communications.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm rounded-pill fw-semibold d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#messageAdminModal">
                    <i class="bi bi-megaphone-fill"></i> Message Admins
                </button>
                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm rounded-pill fw-semibold d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-plus-fill"></i> Add Admin
                </button>
            </div>
        </header>

        <!-- ── Stat Cards ─────────────────────────────── -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3 fade-up">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark" id="totalAdmins">0</div>
                            <div class="stat-label">Total Admins</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3 fade-up" style="animation-delay: 50ms;">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark" id="activeAdmins">0</div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3 fade-up" style="animation-delay: 100ms;">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark" id="inactiveAdmins">0</div>
                            <div class="stat-label">Inactive</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3 fade-up" style="animation-delay: 150ms;">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark" id="branchCount">0</div>
                            <div class="stat-label">Managed Branches</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- ── Operational Intelligence ─────────────────── -->
            <div class="col-12 col-xxl-4 fade-up" style="animation-delay: 200ms;">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-activity text-primary me-2"></i>Branch Intel</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                <div class="text-secondary fw-medium small">Total Students Enrolled</div>
                                <div class="fw-bold text-dark fs-5" id="ops_total_students">0</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 bg-warning bg-opacity-10 rounded-3">
                                <div class="text-warning-emphasis fw-medium small">Pending Transfers</div>
                                <div class="fw-bold text-warning-emphasis fs-5" id="ops_pending_transfers">0</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 bg-danger bg-opacity-10 rounded-3">
                                <div class="text-danger-emphasis fw-medium small">Accounts with Balances</div>
                                <div class="fw-bold text-danger-emphasis fs-5" id="ops_outstanding_accounts">0</div>
                            </div>
                        </div>
                        <hr class="text-muted opacity-25">
                        <div class="table-responsive" style="max-height: 250px;">
                            <table class="table table-borderless table-hover align-middle mb-0 small" id="opsTable">
                                <thead>
                                    <tr class="text-secondary">
                                        <th class="ps-0">Admin / Branch</th>
                                        <th class="text-end pe-0">Last Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="2" class="text-center text-muted py-3">Loading operational data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Admin Table ────────────────────────────── -->
            <div class="col-12 col-xxl-8 fade-up" style="animation-delay: 250ms;">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table text-indigo-500 me-2"></i>Administrator Directory</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100" id="adminTable">
                                <thead>
                                    <tr>
                                        <th width="5%">id</th>
                                        <th width="25%">Admin Profile</th>
                                        <th width="20%">Branch Assignment</th>
                                        <th width="15%">Status</th>
                                        <th width="15%">Performance</th>
                                        <th width="20%" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ── Modals Subsystem ─────────────────────────────────────────────────── -->

<!-- Admin Detail Drill-down Modal -->
<div class="modal fade" id="adminDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-vcard text-primary me-2"></i>Administrator 360° View</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" id="adminDetailBody">
                <div class="d-flex justify-content-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Branch Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="addAdminForm" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <div class="modal-header border-bottom px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>Register Branch Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Sarah Jenkins" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="admin@domain.com" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Branch Assignment <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Target Branch...</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= htmlspecialchars((string)$b['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($b['name'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-secondary small">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="passwordField" class="form-control border-end-0" placeholder="Min. 8 chars" required minlength="8">
                            <button class="btn border border-start-0 bg-white text-muted" type="button" tabindex="-1" onclick="togglePwd('passwordField', 'pwEyeAdd')">
                                <i class="bi bi-eye" id="pwEyeAdd"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-secondary small">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="confirmPasswordField" class="form-control" placeholder="Match password" required minlength="8">
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3 rounded-bottom-4">
                <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-medium px-4 d-flex align-items-center gap-2" id="saveAdminBtn">
                    <i class="bi bi-hdd"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="editAdminForm" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="hidden" name="id" id="edit_a_id">
            <div class="modal-header border-bottom px-4 pt-4 bg-warning bg-opacity-10">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Modify Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-secondary small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_a_name" class="form-control" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-secondary small">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_a_status" class="form-select fw-medium" required>
                            <option value="Active" class="text-success">🟢 Active</option>
                            <option value="Inactive" class="text-danger">🔴 Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_a_email" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Branch Assignment <span class="text-danger">*</span></label>
                        <select name="branch_id" id="edit_a_branch_id" class="form-select" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= htmlspecialchars((string)$b['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($b['name'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 mt-4 pt-3 border-top">
                        <label class="form-label fw-semibold text-secondary small d-flex justify-content-between">
                            <span>Reset Password</span>
                            <span class="text-muted fw-normal fst-italic">Leave blank preserving current</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="password" id="editPasswordField" class="form-control border-end-0" placeholder="Min. 8 characters" minlength="8">
                            <button class="btn border border-start-0 bg-white text-muted" type="button" tabindex="-1" onclick="togglePwd('editPasswordField', 'pwEyeEdit')">
                                <i class="bi bi-eye" id="pwEyeEdit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3 rounded-bottom-4">
                <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal">Discard</button>
                <button type="submit" class="btn btn-warning text-dark fw-bold px-4 d-flex align-items-center gap-2" id="updateAdminBtn">
                    <i class="bi bi-check2-circle"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Communications Modal -->
<div class="modal fade" id="messageAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="messageAdminForm" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <div class="modal-header border-bottom px-4 pt-4 bg-primary bg-opacity-10">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-envelope-paper-fill me-2"></i>Dispatch Communication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                
                <div class="row g-3 bg-light p-3 rounded-3 mb-4">
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold text-secondary small">Target Audience</label>
                        <select name="target_type" id="msg_target_type" class="form-select border-0 shadow-sm" required onchange="AdminManager.updateMsgUI()">
                            <option value="all">🌐 Network (All Active)</option>
                            <option value="branch">🏢 Branch Direct</option>
                            <option value="admin">👤 Specific Admin</option>
                        </select>
                    </div>
                    <div class="col-sm-4" id="msgBranchWrap" style="display:none;">
                        <label class="form-label fw-semibold text-secondary small">Select Branch</label>
                        <select name="branch_id" id="msg_branch_id" class="form-select border-0 shadow-sm" onchange="AdminManager.refreshMsgRecipients()">
                            <option value="">Choose Branch...</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4" id="msgAdminWrap" style="display:none;">
                        <label class="form-label fw-semibold text-secondary small">Select Administrator</label>
                        <select name="admin_id" id="msg_admin_id" class="form-select border-0 shadow-sm">
                            <option value="">Select Recipient...</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-semibold text-secondary small">Subject Line <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control fw-medium" maxlength="150" placeholder="Memo topic..." required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-secondary small">Priority Matrix</label>
                        <select name="priority" class="form-select fw-medium text-dark">
                            <option value="normal" class="text-secondary">Normal</option>
                            <option value="high" class="text-warning">High Priority</option>
                            <option value="urgent" class="text-danger fw-bold">!! URGENT !!</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Message Body <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="5" maxlength="2500" placeholder="Formulate your organizational message here..." required style="resize: vertical;"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="send_email" id="msg_send_email" value="1" checked>
                            <label class="form-check-label ms-2 text-muted fw-medium small" for="msg_send_email">
                                <i class="bi bi-box-arrow-up-right"></i> Cross-post to recipient's registered email address
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-5 border border-bottom-0 border-start-0 border-end-0 pt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>Communication Log</h6>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-none small d-flex align-items-center gap-1" onclick="AdminManager.loadMessageLog()">
                            <i class="bi bi-arrow-clockwise"></i> Sync
                        </button>
                    </div>
                    <div id="messageHistory" class="rounded-3 border overflow-auto bg-white" style="max-height: 250px;">
                        <div class="p-4 text-center text-muted small"><i class="spinner-border spinner-border-sm me-2"></i>Loading logs...</div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top bg-light px-4 py-3 rounded-bottom-4">
                <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary fw-medium px-4 d-flex align-items-center gap-2" id="sendMessageBtn">
                    <i class="bi bi-send-fill text-white"></i> Transmit
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
// Toggle Password Utility
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Global Core App Pattern
const AdminManager = (() => {
    
    let adminCache = [];
    let dtInstance = null;

    // Escaper
    const sanitize = (str) => {
        if (str === null || str === undefined) return '';
        const temp = document.createElement('div');
        temp.textContent = String(str);
        return temp.innerHTML;
    };

    const formatDate = (isoString) => {
        if (!isoString) return '<span class="text-muted fst-italic">Never</span>';
        const d = new Date(isoString.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoString;
        return d.toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
    };

    // Sub-routine: Build DataTables
    const initializeTable = () => {
        if (dtInstance) dtInstance.destroy();
        dtInstance = $('#adminTable').DataTable({
            processing: true,
            ajax: {
                url: 'models/api/admin_api.php?action=list',
                dataSrc: (json) => {
                    if(!json.data) return [];
                    
                    adminCache = json.data;
                    const cTotal = adminCache.length;
                    const cActive = adminCache.filter(a => a.status === 'Active').length;
                    const bCount = new Set(adminCache.map(a => a.branch_id).filter(id => id)).size;

                    document.getElementById('totalAdmins').textContent = cTotal;
                    document.getElementById('activeAdmins').textContent = cActive;
                    document.getElementById('inactiveAdmins').textContent = cTotal - cActive;
                    document.getElementById('branchCount').textContent = bCount;

                    refreshMsgRecipients();
                    loadIntel();
                    return adminCache;
                }
            },
            columns: [
                { data: null, render: (d,t,r,m) => `<span class="text-secondary small fw-medium">#${m.row + 1}</span>` },
                { 
                    data: 'name', 
                    render: (d, t, r) => `
                        <div class="d-flex flex-column" role="button" onclick="AdminManager.viewDetails(${r.id})">
                            <span class="fw-bold text-primary">${sanitize(d)}</span>
                            <span class="small text-muted"><i class="bi bi-envelope me-1"></i>${sanitize(r.email)}</span>
                        </div>
                    ` 
                },
                { 
                    data: 'branch_name', 
                    render: (d) => !!d 
                        ? `<div class="badge-soft-primary fw-semibold d-inline-flex align-items-center gap-1"><i class="bi bi-building"></i> ${sanitize(d)}</div>` 
                        : `<span class="badge bg-secondary opacity-50">Unassigned</span>`
                },
                { 
                    data: 'status', 
                    render: (d) => String(d) === 'Active' 
                        ? `<div class="badge-soft-success"><i class="bi bi-check2-circle"></i> Active</div>` 
                        : `<div class="badge-soft-danger"><i class="bi bi-x-circle"></i> Inactive</div>`
                },
                {
                    data: null,
                    orderable: false,
                    render: (d) => {
                        return `<span class="small text-muted"><i class="bi bi-clock-history"></i> See Intel</span>`;
                    }
                },
                {
                    data: null, 
                    orderable: false, 
                    className: 'text-end',
                    render: (d) => {
                        const payload = btoa(encodeURIComponent(JSON.stringify(d)));
                        return `
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-sm btn-light border" title="Examine" onclick="AdminManager.viewDetails(${d.id})"><i class="bi bi-search text-primary"></i></button>
                            <button class="btn btn-sm btn-light border" title="Modify" onclick="AdminManager.editPrompt('${payload}')"><i class="bi bi-pencil-fill text-warning"></i></button>
                            ${d.status === 'Active' ? `<button class="btn btn-sm btn-light border text-danger" title="Suspend" onclick="AdminManager.toggleStatus(${d.id}, 'Suspend', '${sanitize(d.name)}')"><i class="bi bi-power"></i></button>` : `<button class="btn btn-sm btn-light border text-success" title="Activate" onclick="AdminManager.toggleStatus(${d.id}, 'Activate', '${sanitize(d.name)}')"><i class="bi bi-lightning-charge-fill"></i></button>`}
                        </div>`;
                    }
                }
            ],
            language: { emptyTable: "<div class='text-muted py-4 fw-medium'><i class='bi bi-inbox-fill fs-3 d-block mb-2'></i>No registered administrators discovered.</div>" }
        });
    };

    // Sub-routine: Async Operational Intel Fetch
    const loadIntel = async () => {
        const tbody = document.querySelector('#opsTable tbody');
        try {
            const req = await fetch('models/api/admin_api.php?action=insights');
            const res = await req.json();
            
            if(res.summary) {
                document.getElementById('ops_total_students').textContent = res.summary.total_students?.toLocaleString() || 0;
                document.getElementById('ops_pending_transfers').textContent = res.summary.pending_transfers?.toLocaleString() || 0;
                document.getElementById('ops_outstanding_accounts').textContent = res.summary.outstanding_accounts?.toLocaleString() || 0;
            }

            if (!res.top || !res.top.length) {
                tbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted small py-3">Insufficient data footprint</td></tr>`;
                return;
            }
            
            tbody.innerHTML = res.top.map(r => `
                <tr>
                    <td class="ps-0 py-2">
                        <div class="fw-semibold text-dark mb-1">${sanitize(r.name)}</div>
                        <div class="badge text-bg-light text-secondary border fw-normal small px-2 py-1"><i class="bi bi-geo-alt-fill opacity-50 me-1"></i>${sanitize(r.branch_name)}</div>
                    </td>
                    <td class="pe-0 py-2 text-end text-muted small whitespace-nowrap">
                        ${formatDate(r.last_activity)}
                    </td>
                </tr>
            `).join('');

        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="2" class="text-center text-danger small py-3"><i class="bi bi-exclamation-triangle"></i> Intel unreachable</td></tr>`;
            console.error('Intel Error:', e);
        }
    };

    // Sub-routine: Setup Communications Logic
    const updateMsgUI = () => {
        const type = document.getElementById('msg_target_type').value;
        const bWrap = document.getElementById('msgBranchWrap');
        const aWrap = document.getElementById('msgAdminWrap');
        
        bWrap.style.display = (type === 'branch' || type === 'admin') ? 'block' : 'none';
        aWrap.style.display = (type === 'admin') ? 'block' : 'none';
        refreshMsgRecipients();
    };

    const refreshMsgRecipients = () => {
        const adminSel = document.getElementById('msg_admin_id');
        const branchId = document.getElementById('msg_branch_id').value;
        const eligibles = adminCache.filter(a => a.status === 'Active' && (!branchId || String(a.branch_id) === branchId));
        
        adminSel.innerHTML = '<option value="">Select Recipient...</option>' + 
            eligibles.map(a => `<option value="${a.id}">${sanitize(a.name)} (${sanitize(a.branch_name || 'Generic')})</option>`).join('');
    };

    const loadMessageLog = async () => {
        const box = document.getElementById('messageHistory');
        box.innerHTML = '<div class="p-4 text-center text-primary small"><span class="spinner-border spinner-border-sm me-2"></span>Querying logs...</div>';
        try {
            const req = await fetch('models/api/admin_api.php?action=messages');
            const res = await req.json();
            
            if(!res.data || !res.data.length) {
                box.innerHTML = '<div class="p-4 text-center text-muted small bg-light rounded h-100 d-flex align-items-center justify-content-center">Archive Empty</div>';
                return;
            }

            box.innerHTML = '<div class="list-group list-group-flush">' + res.data.map(r => {
                const priorityBadge = r.priority === 'urgent' ? '<span class="badge bg-danger rounded-pill">Urgent</span>' : (r.priority === 'high' ? '<span class="badge bg-warning text-dark rounded-pill">High</span>' : '<span class="badge bg-secondary rounded-pill fw-normal">Normal</span>');
                return `
                    <div class="list-group-item px-3 py-3 border-bottom text-start">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <h6 class="fw-bold text-dark mb-0">${sanitize(r.subject)}</h6>
                            <span class="small text-muted fst-italic ms-2">${formatDate(r.created_at)}</span>
                        </div>
                        <div class="small mb-2 d-flex align-items-center gap-2">
                            ${priorityBadge} <span class="text-secondary"><i class="bi bi-person-fill"></i> ${sanitize(r.recipient_name)} • ${sanitize(r.branch_name || 'System Wide')}</span>
                        </div>
                        <p class="mb-0 text-secondary bg-light p-2 rounded small" style="white-space:pre-wrap; border-left: 3px solid #dee2e6;">${sanitize(r.message)}</p>
                    </div>
                `;
            }).join('') + '</div>';

        } catch(e) {
            box.innerHTML = '<div class="p-4 text-center text-danger small bg-danger bg-opacity-10 h-100"><i class="bi bi-x-circle me-1"></i> Failed obtaining log integrity.</div>';
        }
    };


    // Actions
    const initBindings = () => {
        // Form submits using fetch for modern feel
        document.getElementById('addAdminForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveAdminBtn');
            const pwd = document.getElementById('passwordField').value;
            const cPwd = document.getElementById('confirmPasswordField').value;
            const cpf = document.getElementById('confirmPasswordField');

            if(pwd !== cPwd) {
                cpf.classList.add('is-invalid');
                return;
            }
            cpf.classList.remove('is-invalid');

            const fd = new FormData(e.target);
            btn.disabled = true; btn.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Executing...';

            try {
                const req = await fetch('models/api/admin_api.php?action=save', { method: 'POST', body: fd });
                const res = await req.json();
                if(res.status === 'success') {
                    Swal.fire({ title: 'Deployed!', text: res.message, icon: 'success', customClass: { confirmButton: 'btn btn-primary px-4 rounded-pill' } });
                    bootstrap.Modal.getInstance(document.getElementById('addAdminModal')).hide();
                    e.target.reset();
                    dtInstance.ajax.reload();
                } else throw new Error(res.message);
            } catch(ex) {
                Swal.fire('Interruption', ex.message || 'Network gateway disconnected', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-hdd"></i> Create Account';
            }
        });

        document.getElementById('editAdminForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('updateAdminBtn');
            const fd = new FormData(e.target);
            btn.disabled = true; btn.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Syncing...';

            try {
                const req = await fetch('models/api/admin_api.php?action=update', { method: 'POST', body: fd });
                const res = await req.json();
                if(res.status === 'success') {
                    Swal.fire({ title: 'Synchronized!', text: res.message, icon: 'success', customClass: { confirmButton: 'btn btn-primary px-4 rounded-pill' } });
                    bootstrap.Modal.getInstance(document.getElementById('editAdminModal')).hide();
                    dtInstance.ajax.reload();
                } else throw new Error(res.message);
            } catch(ex) {
                Swal.fire('Conflict', ex.message || 'Gateway unresolvable.', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Changes';
            }
        });

        document.getElementById('messageAdminForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const type = document.getElementById('msg_target_type').value;
            const mB = document.getElementById('msg_branch_id').value;
            const mA = document.getElementById('msg_admin_id').value;

            if (type === 'branch' && !mB) return Swal.fire('Check Target', 'Target branch assignment is required.', 'warning');
            if (type === 'admin' && !mA) return Swal.fire('Check Target', 'Target administrative account is required.', 'warning');

            const btn = document.getElementById('sendMessageBtn');
            const fd = new FormData(e.target);
            btn.disabled = true; btn.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Transmitting...';

            try {
                const req = await fetch('models/api/admin_api.php?action=send_message', { method: 'POST', body: fd });
                const res = await req.json();
                if(res.status === 'success') {
                    const msgToast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                    msgToast.fire({ icon: 'success', title: 'Package Dispatched' });
                    e.target.reset();
                    updateMsgUI();
                    loadMessageLog();
                } else throw new Error(res.message);
            } catch(ex) {
                Swal.fire('Transmission Failure', ex.message || 'Signal lost to upstream server', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill text-white"></i> Transmit';
            }
        });

        // Trigger updates when msg modal opens
        document.getElementById('messageAdminModal').addEventListener('shown.bs.modal', () => {
            updateMsgUI();
            loadMessageLog();
        });
    };

    return {
        init: () => {
            initializeTable();
            initBindings();
        },
        updateMsgUI, refreshMsgRecipients, loadMessageLog,
        
        editPrompt: (encodedData) => {
            const data = JSON.parse(decodeURIComponent(atob(encodedData)));
            document.getElementById('edit_a_id').value = data.id;
            document.getElementById('edit_a_name').value = data.name;
            document.getElementById('edit_a_email').value = data.email;
            document.getElementById('edit_a_branch_id').value = data.branch_id || '';
            document.getElementById('edit_a_status').value = data.status;
            document.getElementById('editPasswordField').value = '';
            
            const m = new bootstrap.Modal(document.getElementById('editAdminModal'));
            m.show();
        },

        toggleStatus: (id, logicAction, name) => {
            const isSuspension = logicAction === 'Suspend';
            Swal.fire({
                title: `${isSuspension ? 'Suspend' : 'Activate'} Admin?`,
                html: `${isSuspension ? 'Prevent' : 'Restore'} access for <b>${name}</b> infrastructure abilities?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isSuspension ? '#dc2626' : '#059669',
                cancelButtonColor: '#64748b',
                confirmButtonText: `Yes, ${logicAction}`
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const fd = new URLSearchParams();
                        fd.append('action', 'delete'); // Backend api endpoint reuses 'delete' logic for toggling vs strictly dropping
                        fd.append('id', id);
                        fd.append('csrf_token', CSRF_TOKEN);
                        
                        const req = await fetch('models/api/admin_api.php', { method: 'POST', body: fd, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
                        const res = await req.json();
                        if(res.status === 'success') {
                            Swal.fire({ title: 'Completed', icon: 'success', text: res.message, timer: 2000, showConfirmButton: false });
                            dtInstance.ajax.reload();
                        } else throw new Error(res.message);
                    } catch (ex) {
                        Swal.fire('Failure', ex.message || 'Update rejected by server', 'error');
                    }
                }
            });
        },

        viewDetails: async (id) => {
            const modal = new bootstrap.Modal(document.getElementById('adminDetailModal'));
            modal.show();
            const body = document.getElementById('adminDetailBody');
            body.innerHTML = '<div class="d-flex justify-content-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Parsing...</span></div></div>';

            try {
                const req = await fetch('models/api/admin_api.php?action=detail&id=' + encodeURIComponent(id));
                const res = await req.json();
                
                if(res.status !== 'success') throw new Error(res.message || 'Corrupted profile fetch');

                const a = res.admin || {};
                const m = res.metrics || {};
                const msgs = res.recent_messages || [];

                let msgHtml = msgs.length ? '<div class="list-group list-group-flush custom-scroll" style="max-height: 250px; overflow-y:auto;">' + msgs.map(x => `
                    <div class="list-group-item bg-transparent border-bottom border-light px-0 py-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">${sanitize(x.subject)}</span>
                            <span class="badge ${x.priority==='urgent'?'bg-danger':'bg-secondary'} rounded-pill small px-2 py-1">${sanitize(String(x.priority).toUpperCase())}</span>
                        </div>
                        <div class="small text-secondary mb-2 fst-italic"><i class="bi bi-calendar-event me-1"></i> ${formatDate(x.created_at)} • via ${sanitize(x.channel || 'Internal System')}</div>
                        <p class="mb-0 text-muted small bg-white p-2 rounded border">${sanitize(x.message)}</p>
                    </div>
                `).join('') + '</div>' : '<div class="text-center text-muted small py-4 bg-white rounded border border-light">No communication fragments discovered.</div>';

                const aStatObj = a.status === 'Active' ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Nominal</span>' : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i> Suspended</span>';
                
                body.innerHTML = `
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <!-- Identity Card -->
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-body p-4 position-relative overflow-hidden">
                                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="bi bi-person-badge text-primary" style="font-size: 6rem;"></i></div>
                                    <div class="d-flex align-items-center gap-3 mb-4">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 64px; height: 64px;">
                                            ${sanitize(a.name).charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1">${sanitize(a.name)}</h5>
                                            ${aStatObj}
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-3 fs-6 text-secondary position-relative">
                                        <div><i class="bi bi-envelope-at me-2 text-primary opacity-75"></i> <a href="mailto:${sanitize(a.email)}" class="text-decoration-none text-secondary hover-primary">${sanitize(a.email)}</a></div>
                                        <div><i class="bi bi-clock-history me-2 text-primary opacity-75"></i> Last Ping: <strong class="text-dark">${formatDate(m.last_activity)}</strong></div>
                                    </div>
                                    <hr class="my-4">
                                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-building-gear text-indigo-500 me-2"></i>Infrastructure Link -> ${sanitize(a.branch_name || 'N/A')}</h6>
                                    <div class="d-flex flex-column gap-2 small text-secondary">
                                        <div><i class="bi bi-telephone me-2"></i> ${sanitize(a.branch_phone || '--')}</div>
                                        <div><i class="bi bi-envelope me-2"></i> ${sanitize(a.branch_email || '--')}</div>
                                        <div><i class="bi bi-geo-alt me-2"></i> ${sanitize(a.branch_address || '--')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 d-flex flex-column gap-4">
                            <!-- Capabilities Matrix -->
                            <div class="card border-0 shadow-sm rounded-4">
                                <div class="card-header bg-white border-bottom-0 px-4 pt-4 pb-0"><h6 class="fw-bold text-dark mb-0"><i class="bi bi-cpu text-primary me-2"></i>Entity Operation Load</h6></div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-light rounded-3 text-center border h-100"><div class="fw-bold text-primary fs-4">${Number(m.students || 0).toLocaleString()}</div><div class="small fw-medium text-secondary text-uppercase mt-1" style="font-size:0.7rem;">Students</div></div></div>
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-light rounded-3 text-center border h-100"><div class="fw-bold text-dark fs-4">${Number(m.teachers || 0).toLocaleString()}</div><div class="small fw-medium text-secondary text-uppercase mt-1" style="font-size:0.7rem;">Instructors</div></div></div>
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-light rounded-3 text-center border h-100"><div class="fw-bold text-dark fs-4">${Number(m.courses || 0).toLocaleString()}</div><div class="small fw-medium text-secondary text-uppercase mt-1" style="font-size:0.7rem;">Curriculums</div></div></div>
                                        
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-success bg-opacity-10 rounded-3 text-center border border-success border-opacity-25 h-100"><div class="fw-bold text-success fs-4">${Number(m.active_enrollments || 0).toLocaleString()}</div><div class="small fw-bold text-success text-uppercase mt-1" style="font-size:0.7rem;">Act. Engagements</div></div></div>
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-warning bg-opacity-10 rounded-3 text-center border border-warning border-opacity-25 h-100"><div class="fw-bold text-warning-emphasis fs-4">${Number(m.pending_transfers || 0).toLocaleString()}</div><div class="small fw-bold text-warning-emphasis text-uppercase mt-1" style="font-size:0.7rem;">Transfer Queues</div></div></div>
                                        <div class="col-4 col-sm-4"><div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center border border-danger border-opacity-25 h-100"><div class="fw-bold text-danger fs-4">${Number(m.outstanding_accounts || 0).toLocaleString()}</div><div class="small fw-bold text-danger text-uppercase mt-1" style="font-size:0.7rem;">Debt Anomalies</div></div></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Comm Fragments -->
                            <div class="card border-0 shadow-sm rounded-4 flex-grow-1">
                                <div class="card-header bg-white border-bottom-0 px-4 pt-4 pb-0"><h6 class="fw-bold text-dark mb-0"><i class="bi bi-chat-square-text text-secondary me-2"></i>Communication Fragments Targeting Entity</h6></div>
                                <div class="card-body p-4 pt-3">
                                    ${msgHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;

            } catch (ex) {
                body.innerHTML = `<div class="alert alert-danger mb-0 rounded-3 border-danger d-flex align-items-center gap-2 px-4 shadow-sm"><i class="bi bi-exclamation-octagon fs-4"></i> Data core retrieval failed. ${sanitize(ex.message)}</div>`;
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    AdminManager.init();
});
</script>
</body>
</html>
