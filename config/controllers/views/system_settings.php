<?php
ob_start();
session_start();
require_once '../../config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "config/controllers/views/login.php");
    exit;
}
if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

$pageTitle  = 'System Settings';
$activePage = 'system_settings.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>

<style>
/* ═══════════════════════════════════════════════════════════════
   SYSTEM SETTINGS — Authoritative Control Center
   Font: DM Mono (keys/code) + Figtree (UI)
   Palette: Deep graphite with cyan precision accents
═══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap');

:root {
    --ss-ink:      #0A0F1E;
    --ss-navy:     #0F1E3C;
    --ss-cyan:     #0891B2;
    --ss-cyan-lt:  #ECFEFF;
    --ss-cyan-bd:  #A5F3FC;
    --ss-emerald:  #059669;
    --ss-em-lt:    #ECFDF5;
    --ss-em-bd:    #A7F3D0;
    --ss-amber:    #D97706;
    --ss-am-lt:    #FFFBEB;
    --ss-am-bd:    #FDE68A;
    --ss-red:      #DC2626;
    --ss-red-lt:   #FEF2F2;
    --ss-red-bd:   #FECACA;
    --ss-violet:   #7C3AED;
    --ss-vi-lt:    #F5F3FF;
    --ss-vi-bd:    #DDD6FE;
    --ss-rose:     #E11D48;
    --ss-rose-lt:  #FFF1F2;
    --ss-slate:    #1E293B;
    --ss-muted:    #475569;
    --ss-subtle:   #94A3B8;
    --ss-surface:  #FFFFFF;
    --ss-page:     #F8FAFC;
    --ss-border:   #E2E8F0;
    --ss-border2:  #CBD5E1;
    --ss-shadow:   0 1px 3px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.07);
    --ss-shadow-md:0 4px 8px rgba(0,0,0,.07), 0 12px 32px rgba(0,0,0,.10);
    --ss-r:        10px;
    --ss-rlg:      16px;
    --ss-fd:       'Figtree', system-ui, sans-serif;
    --ss-fm:       'DM Mono', monospace;
}

.ss-wrap, .ss-wrap * { font-family: var(--ss-fd); box-sizing: border-box; }
.ss-wrap h1,.ss-wrap h2,.ss-wrap h3,.ss-wrap h4,.ss-wrap h5 { font-family: var(--ss-fd); }

/* ── Page Header ── */
.ss-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
.ss-header-left h2 { font-size:1.55rem; font-weight:800; color:var(--ss-ink); letter-spacing:-.03em; margin:0 0 5px; }
.ss-header-left p  { font-size:.875rem; color:var(--ss-muted); margin:0; }
.ss-header-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

/* ── Buttons ── */
.ss-btn { display:inline-flex; align-items:center; gap:7px; height:40px; padding:0 18px; border:none; border-radius:var(--ss-r); font-family:var(--ss-fd); font-size:.855rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap; }
.ss-btn-primary { background:var(--ss-cyan); color:#fff; box-shadow:0 2px 8px rgba(8,145,178,.3); }
.ss-btn-primary:hover { background:#0E7490; box-shadow:0 4px 14px rgba(8,145,178,.35); }
.ss-btn-primary:disabled { opacity:.5; cursor:not-allowed; box-shadow:none; }
.ss-btn-ghost { background:var(--ss-surface); color:var(--ss-muted); border:1.5px solid var(--ss-border2); }
.ss-btn-ghost:hover { background:var(--ss-page); color:var(--ss-slate); }
.ss-btn-sm { height:34px; padding:0 14px; font-size:.8rem; }
.ss-btn-danger { background:var(--ss-red); color:#fff; }
.ss-btn-danger:hover { background:#B91C1C; }

/* ── Status badge ── */
.ss-version-badge { display:inline-flex; align-items:center; gap:5px; background:var(--ss-navy); color:#7DD3FC; border-radius:20px; padding:4px 12px; font-size:.72rem; font-weight:700; font-family:var(--ss-fm); letter-spacing:.5px; }

/* ── System health strip ── */
.ss-health-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
.ss-health-card { background:var(--ss-surface); border:1px solid var(--ss-border); border-radius:var(--ss-rlg); padding:16px 18px; box-shadow:var(--ss-shadow); display:flex; align-items:center; gap:12px; transition:box-shadow .2s; position:relative; overflow:hidden; }
.ss-health-card:hover { box-shadow:var(--ss-shadow-md); }
.ss-health-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; }
.ss-health-card.hc-ok::before     { background:var(--ss-emerald); }
.ss-health-card.hc-warn::before   { background:var(--ss-amber); }
.ss-health-card.hc-info::before   { background:var(--ss-cyan); }
.ss-health-card.hc-neutral::before{ background:var(--ss-subtle); }
.ss-health-icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
.hc-ok .ss-health-icon     { background:var(--ss-em-lt);    color:var(--ss-emerald); }
.hc-warn .ss-health-icon   { background:var(--ss-am-lt);    color:var(--ss-amber); }
.hc-info .ss-health-icon   { background:var(--ss-cyan-lt);  color:var(--ss-cyan); }
.hc-neutral .ss-health-icon{ background:var(--ss-page);     color:var(--ss-muted); }
.ss-health-val { font-size:1.1rem; font-weight:800; color:var(--ss-slate); letter-spacing:-.01em; font-family:var(--ss-fd); }
.ss-health-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--ss-subtle); margin-top:2px; }

/* ── Left-rail navigation (settings sidebar) ── */
.ss-layout { display:grid; grid-template-columns:220px 1fr; gap:20px; }
.ss-nav { background:var(--ss-surface); border:1px solid var(--ss-border); border-radius:var(--ss-rlg); padding:10px; box-shadow:var(--ss-shadow); height:fit-content; position:sticky; top:20px; }
.ss-nav-section { font-size:.64rem; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:var(--ss-subtle); padding:10px 10px 6px; }
.ss-nav-btn { display:flex; align-items:center; gap:9px; width:100%; padding:9px 12px; border:none; border-radius:8px; background:transparent; font-family:var(--ss-fd); font-size:.855rem; font-weight:500; color:var(--ss-muted); cursor:pointer; text-align:left; transition:all .15s; }
.ss-nav-btn:hover { background:var(--ss-page); color:var(--ss-slate); }
.ss-nav-btn.active { background:var(--ss-cyan-lt); color:var(--ss-cyan); font-weight:700; }
.ss-nav-btn i { font-size:.9rem; width:16px; text-align:center; flex-shrink:0; }
.ss-nav-btn .nav-badge { margin-left:auto; min-width:18px; height:18px; background:var(--ss-cyan); color:#fff; border-radius:9px; padding:0 5px; font-size:.65rem; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
.ss-nav-btn.active .nav-badge { background:var(--ss-cyan); }
.ss-nav-divider { height:1px; background:var(--ss-border); margin:6px 0; }

/* ── Settings panels ── */
.ss-panel { display:none; }
.ss-panel.active { display:block; }

/* ── Section card ── */
.ss-section { background:var(--ss-surface); border:1px solid var(--ss-border); border-radius:var(--ss-rlg); box-shadow:var(--ss-shadow); margin-bottom:16px; overflow:hidden; }
.ss-section-head { padding:16px 20px; border-bottom:1px solid var(--ss-border); display:flex; align-items:center; justify-content:space-between; }
.ss-section-head-left { display:flex; align-items:center; gap:10px; }
.ss-section-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
.ss-section-head h5 { font-size:.92rem; font-weight:700; color:var(--ss-slate); margin:0; }
.ss-section-head p  { font-size:.78rem; color:var(--ss-muted); margin:2px 0 0; }
.ss-section-body { padding:20px; }

/* ── Setting row ── */
.ss-setting-row { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:14px 0; border-bottom:1px solid var(--ss-border); }
.ss-setting-row:last-child { border-bottom:none; padding-bottom:0; }
.ss-setting-row:first-child { padding-top:0; }
.ss-setting-info { flex:1; min-width:0; }
.ss-setting-label { font-size:.875rem; font-weight:700; color:var(--ss-slate); margin-bottom:3px; }
.ss-setting-desc  { font-size:.78rem; color:var(--ss-muted); line-height:1.5; }
.ss-setting-key   { font-family:var(--ss-fm); font-size:.72rem; color:var(--ss-subtle); margin-top:4px; background:var(--ss-page); border:1px solid var(--ss-border); border-radius:4px; padding:1px 6px; display:inline-block; }
.ss-setting-impact{ display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-radius:20px; padding:2px 8px; margin-top:5px; }
.si-high   { background:var(--ss-red-lt);  color:var(--ss-red); }
.si-medium { background:var(--ss-am-lt);   color:var(--ss-amber); }
.si-low    { background:var(--ss-em-lt);   color:var(--ss-emerald); }
.si-info   { background:var(--ss-cyan-lt); color:var(--ss-cyan); }
.ss-setting-control { width:240px; flex-shrink:0; }

/* ── Input variants ── */
.ss-input,.ss-select,.ss-textarea { width:100%; height:38px; padding:0 12px; border:1.5px solid var(--ss-border2); border-radius:8px; font-family:var(--ss-fd); font-size:.875rem; color:var(--ss-slate); background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
.ss-input:focus,.ss-select:focus,.ss-textarea:focus { border-color:var(--ss-cyan); box-shadow:0 0 0 3px rgba(8,145,178,.12); }
.ss-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; cursor:pointer; }
.ss-textarea { height:auto; padding:10px 12px; resize:vertical; }
.ss-input-group { display:flex; align-items:center; border:1.5px solid var(--ss-border2); border-radius:8px; overflow:hidden; background:#fff; transition:border-color .15s,box-shadow .15s; }
.ss-input-group:focus-within { border-color:var(--ss-cyan); box-shadow:0 0 0 3px rgba(8,145,178,.12); }
.ss-input-pfx { padding:0 10px; height:38px; display:flex; align-items:center; font-size:.84rem; font-weight:600; color:var(--ss-muted); border-right:1.5px solid var(--ss-border); background:var(--ss-page); flex-shrink:0; font-family:var(--ss-fm); }
.ss-input-group .ss-input { border:none; box-shadow:none; border-radius:0; padding-left:8px; }

/* ── Toggle switch ── */
.ss-toggle-wrap { display:flex; align-items:center; justify-content:space-between; background:var(--ss-page); border:1px solid var(--ss-border); border-radius:8px; padding:10px 14px; }
.ss-toggle-wrap label { font-size:.855rem; font-weight:500; color:var(--ss-slate); margin:0; cursor:pointer; }
.ss-toggle { position:relative; width:44px; height:24px; flex-shrink:0; }
.ss-toggle input { opacity:0; width:0; height:0; }
.ss-toggle-slider { position:absolute; inset:0; background:var(--ss-border2); border-radius:24px; cursor:pointer; transition:background .2s; }
.ss-toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.ss-toggle input:checked + .ss-toggle-slider { background:var(--ss-cyan); }
.ss-toggle input:checked + .ss-toggle-slider::before { transform:translateX(20px); }

/* ── Color picker ── */
.ss-color-wrap { display:flex; align-items:center; gap:8px; }
.ss-color-preview { width:36px; height:36px; border-radius:7px; border:2px solid var(--ss-border2); cursor:pointer; flex-shrink:0; }
.ss-color-input { flex:1; }

/* ── Danger zone ── */
.ss-danger-zone { background:var(--ss-red-lt); border:1.5px solid var(--ss-red-bd); border-radius:var(--ss-rlg); padding:20px; margin-bottom:16px; }
.ss-danger-zone-title { font-size:.92rem; font-weight:800; color:var(--ss-red); display:flex; align-items:center; gap:7px; margin-bottom:16px; }
.ss-danger-action { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:12px 0; border-bottom:1px solid var(--ss-red-bd); }
.ss-danger-action:last-child { border-bottom:none; padding-bottom:0; }

/* ── Alert ── */
.ss-alert { display:none; border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:.875rem; font-weight:600; display:flex; align-items:center; gap:8px; }
.ss-alert.show { display:flex; }
.ss-alert-success { background:var(--ss-em-lt); color:var(--ss-emerald); border:1.5px solid var(--ss-em-bd); }
.ss-alert-error   { background:var(--ss-red-lt); color:var(--ss-red);     border:1.5px solid var(--ss-red-bd); }
.ss-alert-warning { background:var(--ss-am-lt); color:var(--ss-amber);   border:1.5px solid var(--ss-am-bd); }

/* ── Audit log ── */
.ss-audit-row { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid var(--ss-border); font-size:.82rem; }
.ss-audit-row:last-child { border-bottom:none; }
.ss-audit-dot { width:8px; height:8px; border-radius:50%; background:var(--ss-cyan); flex-shrink:0; margin-top:5px; }
.ss-audit-key { font-family:var(--ss-fm); font-size:.78rem; color:var(--ss-cyan); background:var(--ss-cyan-lt); border-radius:4px; padding:1px 6px; }
.ss-audit-val { font-weight:700; color:var(--ss-slate); }
.ss-audit-by  { color:var(--ss-muted); font-size:.75rem; }

/* ── Save bar (sticky bottom) ── */
.ss-save-bar { position:sticky; bottom:0; background:var(--ss-surface); border-top:1px solid var(--ss-border); padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; box-shadow:0 -4px 16px rgba(0,0,0,.07); z-index:10; border-radius:0 0 var(--ss-rlg) var(--ss-rlg); }
.ss-dirty-indicator { font-size:.78rem; color:var(--ss-amber); font-weight:700; display:none; align-items:center; gap:5px; }
.ss-dirty-indicator.show { display:flex; }

/* ── Responsive ── */
@media(max-width:900px) {
    .ss-layout { grid-template-columns:1fr; }
    .ss-health-strip { grid-template-columns:1fr 1fr; }
    .ss-nav { position:static; display:flex; flex-wrap:wrap; gap:4px; padding:8px; }
    .ss-nav-section { display:none; }
    .ss-setting-control { width:100%; }
    .ss-setting-row { flex-direction:column; gap:8px; }
}
@media(max-width:540px) {
    .ss-health-strip { grid-template-columns:1fr; }
}
</style>

<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main ss-wrap">

    <!-- ── Page Header ────────────────────────────────────── -->
    <div class="ss-header fade-in">
        <div class="ss-header-left">
            <h2><i class="bi bi-gear-wide-connected me-2" style="color:var(--ss-cyan);font-size:1.2rem;vertical-align:middle"></i>System Settings</h2>
            <p>Global configuration, policies, financial controls, and integration settings for the entire platform.</p>
        </div>
        <div class="ss-header-actions">
            <span class="ss-version-badge" id="versionBadge">
                <i class="bi bi-shield-check-fill"></i> SBVS v2.0
            </span>
            <button class="ss-btn ss-btn-ghost ss-btn-sm" id="exportSettingsBtn">
                <i class="bi bi-download"></i> Export Config
            </button>
            <button class="ss-btn ss-btn-primary" id="saveAllBtn">
                <i class="bi bi-floppy-fill"></i> Save All Settings
            </button>
        </div>
    </div>

    <!-- ── Alert ──────────────────────────────────────────── -->
    <div class="ss-alert" id="settingsAlert"></div>

    <!-- ── System Health Strip ────────────────────────────── -->
    <div class="ss-health-strip fade-in" style="animation-delay:.05s">
        <div class="ss-health-card hc-ok">
            <div class="ss-health-icon"><i class="bi bi-database-check"></i></div>
            <div>
                <div class="ss-health-val" id="hDbStatus">Active</div>
                <div class="ss-health-lbl">Database</div>
            </div>
        </div>
        <div class="ss-health-card hc-info">
            <div class="ss-health-icon"><i class="bi bi-building-gear"></i></div>
            <div>
                <div class="ss-health-val" id="hBranchCount">—</div>
                <div class="ss-health-lbl">Active Branches</div>
            </div>
        </div>
        <div class="ss-health-card hc-info">
            <div class="ss-health-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="ss-health-val" id="hSettingsCount">—</div>
                <div class="ss-health-lbl">Config Keys</div>
            </div>
        </div>
        <div class="ss-health-card hc-neutral">
            <div class="ss-health-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="ss-health-val" id="hLastSaved">—</div>
                <div class="ss-health-lbl">Last Saved</div>
            </div>
        </div>
    </div>

    <!-- ── Main Layout ────────────────────────────────────── -->
    <div class="ss-layout fade-in" style="animation-delay:.1s">

        <!-- Left Nav Rail -->
        <div class="ss-nav" id="ssNav">
            <div class="ss-nav-section">Configuration</div>
            <button class="ss-nav-btn active" data-panel="general">
                <i class="bi bi-building"></i> General
                <span class="nav-badge" id="nb-general">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="finance">
                <i class="bi bi-cash-coin"></i> Finance
                <span class="nav-badge" id="nb-finance">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="governance">
                <i class="bi bi-shield-check"></i> Governance
                <span class="nav-badge" id="nb-governance">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="academic">
                <i class="bi bi-mortarboard-fill"></i> Academic
                <span class="nav-badge" id="nb-academic">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="attendance">
                <i class="bi bi-calendar-check-fill"></i> Attendance
                <span class="nav-badge" id="nb-attendance">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="notifications">
                <i class="bi bi-bell-fill"></i> Notifications
                <span class="nav-badge" id="nb-notifications">0</span>
            </button>
            <div class="ss-nav-divider"></div>
            <div class="ss-nav-section">System</div>
            <button class="ss-nav-btn" data-panel="integrations">
                <i class="bi bi-plug-fill"></i> Integrations
                <span class="nav-badge" id="nb-integrations">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="security">
                <i class="bi bi-lock-fill"></i> Security
                <span class="nav-badge" id="nb-security">0</span>
            </button>
            <button class="ss-nav-btn" data-panel="audit">
                <i class="bi bi-journal-text"></i> Audit Log
            </button>
            <div class="ss-nav-divider"></div>
            <button class="ss-nav-btn" data-panel="danger" style="color:var(--ss-red)">
                <i class="bi bi-exclamation-triangle-fill"></i> Danger Zone
            </button>
        </div>

        <!-- Right Content Area -->
        <div id="ssPanels">

            <!-- ══ GENERAL ══════════════════════════════════ -->
            <div class="ss-panel active" id="panel-general">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-cyan-lt);color:var(--ss-cyan)"><i class="bi bi-building"></i></div>
                            <div>
                                <h5>Institution Information</h5>
                                <p>Displayed on receipts, payslips, and official documents.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-general">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Institution Name</div>
                                <div class="ss-setting-desc">Full legal name printed on all official documents and receipts.</div>
                                <span class="ss-setting-key">institution_name</span>
                                <span class="ss-setting-impact si-info">System-wide</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[institution_name]" type="text" placeholder="e.g. Shining Bright Vocational School" data-key="institution_name">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Institution Tagline</div>
                                <div class="ss-setting-desc">Short slogan shown on receipts and the login page.</div>
                                <span class="ss-setting-key">institution_tagline</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[institution_tagline]" type="text" placeholder="e.g. Empowering Futures" data-key="institution_tagline">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Headquarters Address</div>
                                <div class="ss-setting-desc">Printed on official letters and payslip footers.</div>
                                <span class="ss-setting-key">hq_address</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[hq_address]" type="text" placeholder="e.g. 12 Broad Street, Monrovia" data-key="hq_address">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Contact Phone</div>
                                <div class="ss-setting-desc">Shown on receipts and the login screen.</div>
                                <span class="ss-setting-key">contact_phone</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[contact_phone]" type="text" placeholder="+231 XXXX XXXX" data-key="contact_phone">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Contact Email</div>
                                <div class="ss-setting-desc">Official contact email for system-generated messages.</div>
                                <span class="ss-setting-key">contact_email</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[contact_email]" type="email" placeholder="admin@institution.edu" data-key="contact_email">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Default Currency</div>
                                <div class="ss-setting-desc">Currency symbol used globally across all financial records and receipts.</div>
                                <span class="ss-setting-key">default_currency</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[default_currency]" data-key="default_currency">
                                    <option value="USD">USD — US Dollar ($)</option>
                                    <option value="LRD">LRD — Liberian Dollar (L$)</option>
                                    <option value="GHS">GHS — Ghanaian Cedi (₵)</option>
                                    <option value="NGN">NGN — Nigerian Naira (₦)</option>
                                    <option value="KES">KES — Kenyan Shilling (Ksh)</option>
                                    <option value="ZAR">ZAR — South African Rand (R)</option>
                                    <option value="EUR">EUR — Euro (€)</option>
                                    <option value="GBP">GBP — British Pound (£)</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Academic Year Format</div>
                                <div class="ss-setting-desc">How student IDs and records are dated (e.g. 2025–2026).</div>
                                <span class="ss-setting-key">academic_year_format</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[academic_year_format]" data-key="academic_year_format">
                                    <option value="YYYY">Single Year (2026)</option>
                                    <option value="YYYY-YYYY">Range (2025–2026)</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Date Display Format</div>
                                <div class="ss-setting-desc">How dates are shown across the interface and documents.</div>
                                <span class="ss-setting-key">date_format</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[date_format]" data-key="date_format">
                                    <option value="d M Y">18 Mar 2026</option>
                                    <option value="d/m/Y">18/03/2026</option>
                                    <option value="m/d/Y">03/18/2026</option>
                                    <option value="Y-m-d">2026-03-18</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ FINANCE ═══════════════════════════════════ -->
            <div class="ss-panel" id="panel-finance">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-em-lt);color:var(--ss-emerald)"><i class="bi bi-cash-coin"></i></div>
                            <div>
                                <h5>Payment &amp; Fee Policies</h5>
                                <p>Controls how payments, discounts, and receipts work system-wide.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-finance">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Max Discount % (Branch Admin)</div>
                                <div class="ss-setting-desc">Maximum fee discount a Branch Admin can apply without Super Admin approval. Amounts above this ceiling trigger an approval workflow.</div>
                                <span class="ss-setting-key">max_discount_percent</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <div class="ss-input-group">
                                    <span class="ss-input-pfx">%</span>
                                    <input class="ss-input ss-field" name="settings[max_discount_percent]" type="number" min="0" max="100" step="0.5" placeholder="e.g. 20" data-key="max_discount_percent">
                                </div>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Transfer Admin Fee</div>
                                <div class="ss-setting-desc">Default administrative fee charged on inter-branch student transfers. Can be overridden per request.</div>
                                <span class="ss-setting-key">transfer_admin_fee</span>
                                <span class="ss-setting-impact si-medium">Medium Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <div class="ss-input-group">
                                    <span class="ss-input-pfx">$</span>
                                    <input class="ss-input ss-field" name="settings[transfer_admin_fee]" type="number" min="0" step="0.01" placeholder="0.00" data-key="transfer_admin_fee">
                                </div>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Receipt Number Prefix</div>
                                <div class="ss-setting-desc">Prefix for all auto-generated payment receipt numbers (e.g. RCP-2026-XXXX).</div>
                                <span class="ss-setting-key">receipt_prefix</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[receipt_prefix]" type="text" placeholder="RCP" data-key="receipt_prefix" style="font-family:var(--ss-fm)">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Allow Partial Payments</div>
                                <div class="ss-setting-desc">Permit students to pay course fees in installments. Disabling this requires full upfront payment.</div>
                                <span class="ss-setting-key">allow_partial_payments</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[allow_partial_payments]" data-key="allow_partial_payments">
                                    <option value="1">Yes — Allow installment payments</option>
                                    <option value="0">No — Full payment required upfront</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Late Payment Penalty (%)</div>
                                <div class="ss-setting-desc">Percentage added to overdue balances after the grace period. Set to 0 to disable.</div>
                                <span class="ss-setting-key">late_payment_penalty_pct</span>
                                <span class="ss-setting-impact si-medium">Medium Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <div class="ss-input-group">
                                    <span class="ss-input-pfx">%</span>
                                    <input class="ss-input ss-field" name="settings[late_payment_penalty_pct]" type="number" min="0" max="50" step="0.5" placeholder="0" data-key="late_payment_penalty_pct">
                                </div>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Grace Period (Days)</div>
                                <div class="ss-setting-desc">Days after due date before penalty is applied. Standard is 7–14 days.</div>
                                <span class="ss-setting-key">payment_grace_days</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[payment_grace_days]" type="number" min="0" placeholder="7" data-key="payment_grace_days">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Receipt Security Hash</div>
                                <div class="ss-setting-desc">Enable SHA-256 verification hashes on printed receipts to prevent forgery.</div>
                                <span class="ss-setting-key">receipt_security_hash</span>
                                <span class="ss-setting-impact si-info">Security</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[receipt_security_hash]" data-key="receipt_security_hash">
                                    <option value="1">Enabled — Print security hash on receipts</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ GOVERNANCE ════════════════════════════════ -->
            <div class="ss-panel" id="panel-governance">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-vi-lt);color:var(--ss-violet)"><i class="bi bi-shield-check"></i></div>
                            <div>
                                <h5>Governance &amp; Access Policies</h5>
                                <p>Controls who can see what across branches and roles.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-governance">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Cross-Branch Report Access</div>
                                <div class="ss-setting-desc">Allow Branch Admins to view anonymised reports from other branches. Super Admins always have full access.</div>
                                <span class="ss-setting-key">allow_cross_branch_reports</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[allow_cross_branch_reports]" data-key="allow_cross_branch_reports">
                                    <option value="0">No — Branch Admins see only their branch</option>
                                    <option value="1">Yes — Allow cross-branch visibility</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Transfer Approval Chain</div>
                                <div class="ss-setting-desc">Who must approve an inter-branch student transfer request before it is actioned.</div>
                                <span class="ss-setting-key">transfer_approval_chain</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[transfer_approval_chain]" data-key="transfer_approval_chain">
                                    <option value="both">Both Origin + Destination Admins</option>
                                    <option value="origin_only">Origin Branch Admin only</option>
                                    <option value="sa_only">Super Admin only</option>
                                    <option value="auto">Auto-approve (no manual review)</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Void Payment Authorization</div>
                                <div class="ss-setting-desc">Who is authorized to void a payment record. Voiding is logged and non-reversible.</div>
                                <span class="ss-setting-key">void_payment_auth</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[void_payment_auth]" data-key="void_payment_auth">
                                    <option value="sa_only">Super Admin only</option>
                                    <option value="branch_admin">Branch Admin and above</option>
                                    <option value="admin">Admin and above</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Payroll Run Authorization</div>
                                <div class="ss-setting-desc">Minimum role required to initiate and process a payroll run.</div>
                                <span class="ss-setting-key">payroll_run_auth</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[payroll_run_auth]" data-key="payroll_run_auth">
                                    <option value="sa_only">Super Admin only</option>
                                    <option value="branch_admin">Branch Admin and above</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Student Self-Enrollment</div>
                                <div class="ss-setting-desc">Allow students to self-enroll in courses via a public portal. If disabled, enrollment is admin-only.</div>
                                <span class="ss-setting-key">student_self_enrollment</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[student_self_enrollment]" data-key="student_self_enrollment">
                                    <option value="0">No — Admin-only enrollment</option>
                                    <option value="1">Yes — Students can self-enroll</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ ACADEMIC ══════════════════════════════════ -->
            <div class="ss-panel" id="panel-academic">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:#EFF6FF;color:#2563EB"><i class="bi bi-mortarboard-fill"></i></div>
                            <div>
                                <h5>Academic Configuration</h5>
                                <p>Student IDs, grading, enrollment rules, and course policies.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-academic">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Student ID Prefix</div>
                                <div class="ss-setting-desc">The prefix used in auto-generated student IDs (e.g. VS-2026-XXXXXX).</div>
                                <span class="ss-setting-key">student_id_prefix</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[student_id_prefix]" type="text" placeholder="VS" data-key="student_id_prefix" style="font-family:var(--ss-fm)">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Max Enrollments per Student</div>
                                <div class="ss-setting-desc">Maximum simultaneous course enrollments a student can hold. Set to 0 for unlimited.</div>
                                <span class="ss-setting-key">max_enrollments_per_student</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[max_enrollments_per_student]" type="number" min="0" placeholder="1" data-key="max_enrollments_per_student">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Enrollment Requires Payment</div>
                                <div class="ss-setting-desc">Block enrollment confirmation until at least an initial payment is recorded.</div>
                                <span class="ss-setting-key">enrollment_requires_payment</span>
                                <span class="ss-setting-impact si-medium">Medium Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[enrollment_requires_payment]" data-key="enrollment_requires_payment">
                                    <option value="0">No — Allow enrollment without payment</option>
                                    <option value="1">Yes — Require initial payment</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Certificate Issuing Policy</div>
                                <div class="ss-setting-desc">When a student is eligible to receive their completion certificate.</div>
                                <span class="ss-setting-key">certificate_issue_policy</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[certificate_issue_policy]" data-key="certificate_issue_policy">
                                    <option value="full_payment">Full payment cleared only</option>
                                    <option value="course_complete">Course completion regardless of balance</option>
                                    <option value="manual">Manual issuing by Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ ATTENDANCE ════════════════════════════════ -->
            <div class="ss-panel" id="panel-attendance">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:#ECFDF5;color:#059669"><i class="bi bi-calendar-check-fill"></i></div>
                            <div>
                                <h5>Attendance Policies</h5>
                                <p>Minimum attendance thresholds and late marking rules.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-attendance">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Minimum Attendance % for Certificate</div>
                                <div class="ss-setting-desc">Students below this threshold are flagged and may be blocked from receiving certificates.</div>
                                <span class="ss-setting-key">min_attendance_pct</span>
                                <span class="ss-setting-impact si-high">High Impact</span>
                            </div>
                            <div class="ss-setting-control">
                                <div class="ss-input-group">
                                    <span class="ss-input-pfx">%</span>
                                    <input class="ss-input ss-field" name="settings[min_attendance_pct]" type="number" min="0" max="100" placeholder="75" data-key="min_attendance_pct">
                                </div>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Allow Past-Date Attendance</div>
                                <div class="ss-setting-desc">Allow admins to record attendance for dates in the past. Useful for catch-up entry.</div>
                                <span class="ss-setting-key">allow_past_attendance</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[allow_past_attendance]" data-key="allow_past_attendance">
                                    <option value="0">No — Today only</option>
                                    <option value="1">Yes — Allow backdating</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Late Threshold (Minutes)</div>
                                <div class="ss-setting-desc">How many minutes after class start a student is marked "Late" instead of "Present".</div>
                                <span class="ss-setting-key">late_threshold_minutes</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[late_threshold_minutes]" type="number" min="0" placeholder="15" data-key="late_threshold_minutes">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ NOTIFICATIONS ═════════════════════════════ -->
            <div class="ss-panel" id="panel-notifications">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:#FFF7ED;color:#EA580C"><i class="bi bi-bell-fill"></i></div>
                            <div>
                                <h5>Notification Settings</h5>
                                <p>Email, SMS, and in-app alert configurations.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-notifications">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Payment Receipt Email</div>
                                <div class="ss-setting-desc">Automatically email a payment receipt to the student after every successful payment.</div>
                                <span class="ss-setting-key">email_receipt_on_payment</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[email_receipt_on_payment]" data-key="email_receipt_on_payment">
                                    <option value="0">Disabled</option>
                                    <option value="1">Enabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Low Attendance Alert Threshold</div>
                                <div class="ss-setting-desc">Send an alert to the Branch Admin when a student's attendance drops below this %.</div>
                                <span class="ss-setting-key">low_attendance_alert_pct</span>
                            </div>
                            <div class="ss-setting-control">
                                <div class="ss-input-group">
                                    <span class="ss-input-pfx">%</span>
                                    <input class="ss-input ss-field" name="settings[low_attendance_alert_pct]" type="number" min="0" max="100" placeholder="60" data-key="low_attendance_alert_pct">
                                </div>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Transfer Status Notifications</div>
                                <div class="ss-setting-desc">Notify involved admins at each stage of an inter-branch transfer workflow.</div>
                                <span class="ss-setting-key">notify_transfer_status</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[notify_transfer_status]" data-key="notify_transfer_status">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Payroll Completion Notify</div>
                                <div class="ss-setting-desc">Send summary email to Super Admin when a payroll run is marked as Paid.</div>
                                <span class="ss-setting-key">notify_payroll_complete</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[notify_payroll_complete]" data-key="notify_payroll_complete">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ INTEGRATIONS ══════════════════════════════ -->
            <div class="ss-panel" id="panel-integrations">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-am-lt);color:var(--ss-amber)"><i class="bi bi-plug-fill"></i></div>
                            <div>
                                <h5>External Integrations</h5>
                                <p>Third-party services, SMS gateways, and email providers.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-integrations">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMTP Host</div>
                                <div class="ss-setting-desc">Outgoing mail server for system emails and receipts.</div>
                                <span class="ss-setting-key">smtp_host</span>
                                <span class="ss-setting-impact si-info">Integration</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[smtp_host]" type="text" placeholder="smtp.gmail.com" data-key="smtp_host" style="font-family:var(--ss-fm)">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMTP Port</div>
                                <div class="ss-setting-desc">Standard: 587 (TLS) or 465 (SSL).</div>
                                <span class="ss-setting-key">smtp_port</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[smtp_port]" type="number" placeholder="587" data-key="smtp_port" style="font-family:var(--ss-fm)">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMTP Username</div>
                                <div class="ss-setting-desc">Email address used to authenticate with the SMTP server.</div>
                                <span class="ss-setting-key">smtp_user</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[smtp_user]" type="email" placeholder="noreply@institution.edu" data-key="smtp_user">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMTP Password</div>
                                <div class="ss-setting-desc">Stored encrypted. Leave blank to keep the existing password.</div>
                                <span class="ss-setting-key">smtp_password</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[smtp_password]" type="password" placeholder="••••••••••" data-key="smtp_password">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMS Gateway API Key</div>
                                <div class="ss-setting-desc">API key for the configured SMS provider (Twilio, Africa's Talking, etc.).</div>
                                <span class="ss-setting-key">sms_api_key</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[sms_api_key]" type="password" placeholder="••••••••••" data-key="sms_api_key">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">SMS Sender ID</div>
                                <div class="ss-setting-desc">The name shown on outgoing SMS messages (max 11 characters, no spaces).</div>
                                <span class="ss-setting-key">sms_sender_id</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[sms_sender_id]" type="text" placeholder="SBVS" maxlength="11" data-key="sms_sender_id" style="font-family:var(--ss-fm);text-transform:uppercase">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ SECURITY ══════════════════════════════════ -->
            <div class="ss-panel" id="panel-security">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-red-lt);color:var(--ss-red)"><i class="bi bi-lock-fill"></i></div>
                            <div>
                                <h5>Security &amp; Session Settings</h5>
                                <p>Login rules, session timeout, and password policy.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ss-section-body" id="sg-security">
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Session Timeout (Minutes)</div>
                                <div class="ss-setting-desc">Inactive sessions are terminated after this period. Recommended: 30–60 minutes.</div>
                                <span class="ss-setting-key">session_timeout_minutes</span>
                                <span class="ss-setting-impact si-info">Security</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[session_timeout_minutes]" type="number" min="5" max="480" placeholder="30" data-key="session_timeout_minutes">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Max Login Attempts</div>
                                <div class="ss-setting-desc">Account is temporarily locked after this many consecutive failed logins.</div>
                                <span class="ss-setting-key">max_login_attempts</span>
                                <span class="ss-setting-impact si-info">Security</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[max_login_attempts]" type="number" min="3" max="20" placeholder="5" data-key="max_login_attempts">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Password Min Length</div>
                                <div class="ss-setting-desc">Minimum characters required for all user passwords. Applies on next password change.</div>
                                <span class="ss-setting-key">password_min_length</span>
                            </div>
                            <div class="ss-setting-control">
                                <input class="ss-input ss-field" name="settings[password_min_length]" type="number" min="6" max="32" placeholder="8" data-key="password_min_length">
                            </div>
                        </div>
                        <div class="ss-setting-row">
                            <div class="ss-setting-info">
                                <div class="ss-setting-label">Two-Factor Authentication</div>
                                <div class="ss-setting-desc">Require 2FA for Super Admin accounts. Branch Admins are prompted but not required.</div>
                                <span class="ss-setting-key">require_2fa_superadmin</span>
                                <span class="ss-setting-impact si-info">Security</span>
                            </div>
                            <div class="ss-setting-control">
                                <select class="ss-select ss-field" name="settings[require_2fa_superadmin]" data-key="require_2fa_superadmin">
                                    <option value="0">Disabled</option>
                                    <option value="1">Required for Super Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ AUDIT LOG ══════════════════════════════════ -->
            <div class="ss-panel" id="panel-audit">
                <div class="ss-section">
                    <div class="ss-section-head">
                        <div class="ss-section-head-left">
                            <div class="ss-section-icon" style="background:var(--ss-page);color:var(--ss-muted)"><i class="bi bi-journal-text"></i></div>
                            <div>
                                <h5>Settings Audit Log</h5>
                                <p>Record of all system setting changes by administrators.</p>
                            </div>
                        </div>
                        <button class="ss-btn ss-btn-ghost ss-btn-sm" onclick="loadAuditLog()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="ss-section-body" id="auditLogBody">
                        <div style="text-align:center;padding:40px;color:var(--ss-muted)">
                            <i class="bi bi-journal-text" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>
                            Click Refresh to load the audit log.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ DANGER ZONE ════════════════════════════════ -->
            <div class="ss-panel" id="panel-danger">
                <div class="ss-danger-zone">
                    <div class="ss-danger-zone-title"><i class="bi bi-exclamation-triangle-fill"></i> Danger Zone</div>
                    <div class="ss-danger-action">
                        <div>
                            <div class="ss-setting-label" style="color:var(--ss-red)">Reset All Settings to Defaults</div>
                            <div class="ss-setting-desc">Revert all system settings to their factory defaults. Cannot be undone.</div>
                        </div>
                        <button class="ss-btn ss-btn-danger ss-btn-sm" id="resetDefaultsBtn">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                        </button>
                    </div>
                    <div class="ss-danger-action">
                        <div>
                            <div class="ss-setting-label" style="color:var(--ss-red)">Clear System Cache</div>
                            <div class="ss-setting-desc">Forces all cached settings to reload. Safe to run at any time.</div>
                        </div>
                        <button class="ss-btn ss-btn-ghost ss-btn-sm" id="clearCacheBtn" style="border-color:var(--ss-red-bd);color:var(--ss-red)">
                            <i class="bi bi-trash3"></i> Clear Cache
                        </button>
                    </div>
                </div>
            </div>

            <!-- Save bar -->
            <div class="ss-save-bar" id="ssSaveBar" style="display:none;">
                <div class="ss-dirty-indicator show" id="dirtyIndicator">
                    <i class="bi bi-circle-fill" style="font-size:.5rem"></i> Unsaved changes
                </div>
                <div style="display:flex;gap:10px">
                    <button class="ss-btn ss-btn-ghost ss-btn-sm" id="discardBtn">Discard</button>
                    <button class="ss-btn ss-btn-primary" id="saveBarBtn">
                        <i class="bi bi-floppy-fill"></i> Save Changes
                    </button>
                </div>
            </div>

        </div><!-- /ssPanels -->
    </div><!-- /ss-layout -->

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
/* ═══════════════════════════════════════════════════════════════
   SYSTEM SETTINGS — Full JS
   Loads from API, populates fields, tracks dirty state,
   saves, exports, and provides audit log.
═══════════════════════════════════════════════════════════════ */
// API path — matches the pattern used by attendance_api, payment_api, etc.
// If your project uses a different base path, change this one constant.
const SS_API = 'models/api/system_settings_api.php';

const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const fmt = v => '$' + parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

let originalValues = {};  // snapshot of loaded values for dirty detection
let isDirty        = false;

// ── Alert helper ─────────────────────────────────────────────
function showAlert(msg, type = 'success') {
    const el = $('#settingsAlert');
    el.removeClass('ss-alert-success ss-alert-error ss-alert-warning').addClass('show');
    el.addClass('ss-alert-' + type);
    el.html(`<i class="bi bi-${type==='success'?'check-circle-fill':type==='warning'?'exclamation-triangle-fill':'x-circle-fill'} me-2"></i>${esc(msg)}`);
    clearTimeout(window._alertTimer);
    window._alertTimer = setTimeout(() => el.removeClass('show'), 5000);
}

// ── Nav switching ─────────────────────────────────────────────
function switchPanel(panel) {
    $('.ss-nav-btn').removeClass('active');
    $(`.ss-nav-btn[data-panel="${panel}"]`).addClass('active');
    $('.ss-panel').removeClass('active');
    $(`#panel-${panel}`).addClass('active');
    if (panel === 'audit') loadAuditLog();
}

$(document).on('click', '.ss-nav-btn', function() {
    switchPanel($(this).data('panel'));
});

// ── Load settings from API ────────────────────────────────────
function loadSettings() {
    $.getJSON(SS_API + '?action=list', function(res) {
        const settings = res.data || [];
        $('#hSettingsCount').text(settings.length);

        // Count badges per category
        const counts = {};
        settings.forEach(s => { counts[s.category] = (counts[s.category]||0)+1; });
        Object.entries(counts).forEach(([cat, n]) => $(`#nb-${cat}`).text(n));

        // Populate each field that exists in the HTML
        settings.forEach(s => {
            const field = $(`[data-key="${s.setting_key}"]`);
            if (!field.length) return;

            if (field.is('select')) {
                field.val(s.setting_val);
                // If option doesn't exist, add it
                if (field.val() === null) {
                    field.append(`<option value="${esc(s.setting_val)}">${esc(s.setting_val)}</option>`);
                    field.val(s.setting_val);
                }
            } else if (field.attr('type') === 'checkbox') {
                field.prop('checked', s.setting_val === '1');
            } else if (field.attr('type') !== 'password') {
                // Don't pre-fill passwords
                field.val(s.setting_val);
            }

            originalValues[s.setting_key] = s.setting_val;
        });

        // Update health strip with last save time
        const lastSaved = settings.find(s => s.updated_at);
        if (lastSaved?.updated_at) {
            $('#hLastSaved').text(new Date(lastSaved.updated_at).toLocaleDateString('en-US',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}));
        }
    }).fail(function(xhr, status, error) {
        let msg = 'Could not load settings. ';
        try {
            const r = JSON.parse(xhr.responseText || '{}');
            msg += r.message || ('HTTP ' + xhr.status + ': ' + error);
        } catch(e) {
            // Response wasn't JSON — likely a PHP fatal or 404
            const preview = (xhr.responseText || '').substring(0, 200).replace(/<[^>]*>/g,'').trim();
            msg += preview || ('HTTP ' + xhr.status + ' — check the API path and server logs.');
        }
        showAlert(msg, 'error');
        console.error('Settings API error:', xhr.status, xhr.responseText);
    });

    // Health stats
    $.getJSON(SS_API + '?action=health', function(res) {
        if (res.branch_count !== undefined) $('#hBranchCount').text(res.branch_count);
        if (res.version)                    $('#versionBadge').html(`<i class="bi bi-shield-check-fill"></i> ${esc(res.version)}`);
    });
}

// ── Dirty state tracking ──────────────────────────────────────
function checkDirty() {
    let dirty = false;
    $('.ss-field').each(function() {
        const key = $(this).data('key');
        if (!key) return;
        const cur = $(this).attr('type') === 'checkbox'
            ? ($(this).prop('checked') ? '1' : '0')
            : $(this).val();
        if (originalValues[key] !== undefined && String(cur) !== String(originalValues[key])) {
            dirty = true;
        }
    });
    isDirty = dirty;
    $('#ssSaveBar').toggle(dirty);
    $('#dirtyIndicator').toggleClass('show', dirty);
}

$(document).on('change input', '.ss-field', checkDirty);

// ── Save settings ─────────────────────────────────────────────
function doSave(btnEl) {
    const btn = $(btnEl);
    btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving…');

    // Collect all field values
    const data = {action:'save'};
    $('.ss-field').each(function() {
        const key = $(this).data('key');
        if (!key) return;
        if ($(this).attr('type') === 'password' && !$(this).val()) return; // skip blank passwords
        const val = $(this).attr('type') === 'checkbox'
            ? ($(this).prop('checked') ? '1' : '0')
            : $(this).val();
        data[`settings[${key}]`] = val;
    });

    $.post(SS_API, data, function(res) {
        const ok = res.success || res.status === 'success';
        if (ok) {
            showAlert(res.message || 'All settings saved successfully.', 'success');
            // Update original snapshot
            $('.ss-field').each(function() {
                const key = $(this).data('key');
                if (!key) return;
                if ($(this).attr('type') !== 'password' || $(this).val()) {
                    originalValues[key] = $(this).attr('type') === 'checkbox'
                        ? ($(this).prop('checked') ? '1' : '0')
                        : $(this).val();
                }
            });
            isDirty = false;
            $('#ssSaveBar').hide();
            $('#hLastSaved').text('Just now');
        } else {
            showAlert(res.message || 'Save failed. Please try again.', 'error');
        }
    }, 'json').fail(function() {
        showAlert('Server error during save. Check the API.', 'error');
    }).always(function() {
        btn.prop('disabled',false).html('<i class="bi bi-floppy-fill me-1"></i> Save All Settings');
    });
}

$('#saveAllBtn, #saveBarBtn').on('click', function() { doSave(this); });

$('#discardBtn').on('click', function() {
    // Restore originals
    $('.ss-field').each(function() {
        const key = $(this).data('key');
        if (!key || originalValues[key] === undefined) return;
        if ($(this).is('select'))
            $(this).val(originalValues[key]);
        else if ($(this).attr('type') === 'checkbox')
            $(this).prop('checked', originalValues[key] === '1');
        else if ($(this).attr('type') !== 'password')
            $(this).val(originalValues[key]);
    });
    isDirty = false;
    $('#ssSaveBar').hide();
    showAlert('Changes discarded.', 'warning');
});

// ── Export config ─────────────────────────────────────────────
$('#exportSettingsBtn').on('click', function() {
    const snapshot = {};
    $('.ss-field').each(function() {
        const key = $(this).data('key');
        if (!key) return;
        if ($(this).attr('type') === 'password') return; // exclude secrets
        snapshot[key] = $(this).attr('type') === 'checkbox'
            ? ($(this).prop('checked') ? '1' : '0')
            : $(this).val();
    });
    const blob = new Blob([JSON.stringify(snapshot, null, 2)], {type:'application/json'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sbvs_settings_export_' + new Date().toISOString().slice(0,10) + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
});

// ── Audit log ─────────────────────────────────────────────────
function loadAuditLog() {
    $('#auditLogBody').html('<div style="text-align:center;padding:32px"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading log…</div>');
    $.getJSON(SS_API + '?action=audit_log', function(res) {
        const logs = res.data || [];
        if (!logs.length) {
            $('#auditLogBody').html('<div style="text-align:center;padding:32px;color:var(--ss-muted)"><i class="bi bi-journal-text" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No audit entries found.</div>');
            return;
        }
        const rows = logs.map(l => `
            <div class="ss-audit-row">
                <div class="ss-audit-dot"></div>
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
                        <span class="ss-audit-key">${esc(l.setting_key)}</span>
                        <span style="font-size:.78rem;color:var(--ss-muted)">changed from</span>
                        <span class="ss-audit-val">${esc(l.old_value||'—')}</span>
                        <span style="font-size:.78rem;color:var(--ss-muted)">→</span>
                        <span class="ss-audit-val" style="color:var(--ss-cyan)">${esc(l.new_value||'—')}</span>
                    </div>
                    <span class="ss-audit-by">by ${esc(l.changed_by||'System')} · ${esc(l.created_at||'')}</span>
                </div>
            </div>`).join('');
        $('#auditLogBody').html(rows);
    }).fail(function() {
        $('#auditLogBody').html('<div style="text-align:center;padding:32px;color:var(--ss-red)">Could not load audit log.</div>');
    });
}

// ── Danger zone ───────────────────────────────────────────────
$('#resetDefaultsBtn').on('click', function() {
    Swal.fire({
        title: 'Reset All Settings?',
        html: 'This will restore <strong>all system settings</strong> to factory defaults.<br>This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        confirmButtonText: 'Yes, Reset Everything',
        cancelButtonText: 'Cancel'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(SS_API, {action:'reset_defaults'}, function(res) {
            const ok = res.success || res.status === 'success';
            if (ok) {
                showAlert('Settings reset to defaults.', 'success');
                originalValues = {};
                loadSettings();
            } else {
                showAlert(res.message || 'Reset failed.', 'error');
            }
        }, 'json');
    });
});

$('#clearCacheBtn').on('click', function() {
    const btn = $(this);
    btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Clearing…');
    $.post(SS_API, {action:'clear_cache'}, function(res) {
        const ok = res.success || res.status === 'success';
        showAlert(ok ? 'Cache cleared successfully.' : (res.message||'Failed.'), ok ? 'success' : 'error');
    }, 'json').always(() => {
        btn.prop('disabled',false).html('<i class="bi bi-trash3 me-1"></i> Clear Cache');
    });
});

// ── Warn on unsaved changes before leaving ────────────────────
window.addEventListener('beforeunload', e => {
    if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});

// ── Init ──────────────────────────────────────────────────────
$(function() {
    loadSettings();
});
</script>
</body>
</html>