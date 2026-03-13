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
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

    <!-- ── Page Header ──────────────────────────────────────── -->
    <div class="page-header fade-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1;">
            <div>
                <h4 class="fw-bold mb-1" style="letter-spacing:-.02em;"><i class="bi bi-gear-wide-connected me-2"></i>System Settings</h4>
                <p class="mb-0 opacity-75" style="font-size:.9rem;">Global policies, financial controls, and integration settings</p>
            </div>
            <button class="btn btn-light btn-sm px-3 d-flex align-items-center gap-2" style="font-weight:600;border-radius:10px;" id="saveAllBtn">
                <i class="bi bi-floppy-fill"></i> Save All Settings
            </button>
        </div>
    </div>

    <!-- ── Alert ────────────────────────────────────────────── -->
    <div id="settingsAlert" class="alert d-none mb-4" role="alert"></div>

    <!-- ── Settings Tabs ────────────────────────────────────── -->
    <div class="card fade-up">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-general"><i class="bi bi-building me-1"></i> General</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-finance"><i class="bi bi-cash-coin me-1"></i> Finance</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-governance"><i class="bi bi-shield-check me-1"></i> Governance</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-integrations"><i class="bi bi-plug-fill me-1"></i> Integrations</a></li>
            </ul>
        </div>
        <div class="card-body">
            <form id="settingsForm">
            <div class="tab-content mt-2">

                <!-- General -->
                <div class="tab-pane fade show active" id="tab-general">
                    <div class="row g-3 settings-group" data-category="general"></div>
                </div>

                <!-- Finance -->
                <div class="tab-pane fade" id="tab-finance">
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3" style="border-radius:10px;">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div><strong>Policy Enforcement:</strong> The <em>Max Discount %</em> sets the ceiling Branch Admins can apply without triggering an approval request to a Super Admin.</div>
                    </div>
                    <div class="row g-3 settings-group" data-category="finance"></div>
                </div>

                <!-- Governance -->
                <div class="tab-pane fade" id="tab-governance">
                    <div class="row g-3 settings-group" data-category="governance"></div>
                </div>

                <!-- Integrations -->
                <div class="tab-pane fade" id="tab-integrations">
                    <div class="row g-3 settings-group" data-category="integrations"></div>
                </div>

            </div>
            </form>
        </div>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<script>
$(document).ready(function () {

    // Load settings
    $.getJSON('models/api/system_settings_api.php?action=list', function (res) {
        const settings = res.data || [];
        settings.forEach(function (s) {
            const group = $(`.settings-group[data-category="${s.category}"]`);
            if (!group.length) return;

            let input;
            if (s.setting_key === 'allow_cross_branch_reports') {
                input = `<select class="form-select" name="settings[${escHtml(s.setting_key)}]">
                            <option value="0" ${s.setting_val === '0' ? 'selected' : ''}>No – Branch Admins see only their branch</option>
                            <option value="1" ${s.setting_val === '1' ? 'selected' : ''}>Yes – Allow cross-branch report access</option>
                         </select>`;
            } else {
                input = `<input type="text" class="form-control" name="settings[${escHtml(s.setting_key)}]" value="${escHtml(s.setting_val)}" placeholder="${escHtml(s.label || s.setting_key)}">`;
            }

            const icon = categoryIcon(s.category);
            group.append(`
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">${icon} ${escHtml(s.label || s.setting_key)}</label>
                    ${input}
                    <div class="form-text text-muted" style="font-size:.75rem;">Key: <code>${escHtml(s.setting_key)}</code></div>
                </div>`);
        });

        if (!settings.length) {
            $('.settings-group').each(function () {
                $(this).append('<div class="col-12 text-muted text-center py-3">No settings found in this category.</div>');
            });
        }
    });

    // Save all
    $('#saveAllBtn').on('click', function () {
        const btn  = $(this);
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        $.ajax({
            url: 'models/api/system_settings_api.php?action=save',
            type: 'POST',
            data: $('#settingsForm').serialize(),
            dataType: 'json',
            success: function (res) {
                const alertEl = $('#settingsAlert');
                alertEl.removeClass('d-none alert-success alert-danger')
                       .addClass(res.status === 'success' ? 'alert-success' : 'alert-danger')
                       .html(`<i class="bi bi-${res.status === 'success' ? 'check-circle' : 'x-circle'} me-2"></i>${escHtml(res.message)}`);
                setTimeout(() => alertEl.addClass('d-none'), 4000);
            },
            error: function () {
                $('#settingsAlert').removeClass('d-none').addClass('alert-danger').text('Server error. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="bi bi-floppy-fill me-1"></i> Save All Settings');
            }
        });
    });

    function categoryIcon(cat) {
        return { general: '🏫', finance: '💰', governance: '🛡️', integrations: '🔌' }[cat] || '⚙️';
    }
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
});
</script>
</body>
</html>
