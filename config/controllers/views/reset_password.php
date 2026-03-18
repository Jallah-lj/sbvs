<?php 
session_start();
require_once '../../config.php';
require_once '../../database.php';

$token = $_GET['token'] ?? '';
$isValid = false;

if (!empty($token)) {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expires > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $isValid = true;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password – SBVS Enterprise Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #4338ca;
            --brand-gradient: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --input-bg: #f8fafc;
            --focus-ring: rgba(67, 56, 202, 0.15);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif; background-color: #ffffff;
            margin: 0; color: var(--text-main); -webkit-font-smoothing: antialiased; min-height: 100vh;
        }
        .auth-layout { display: flex; min-height: 100vh; }
        .auth-banner {
            flex: 1.2; background: var(--brand-gradient); color: white; padding: 4rem;
            display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;
        }
        .auth-banner::before {
            content: ''; position: absolute; top: -10%; right: -10%; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%;
        }
        .auth-banner::after {
            content: ''; position: absolute; bottom: -5%; left: -15%; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.4) 0%, transparent 60%); border-radius: 50%;
        }
        .banner-content { position: relative; z-index: 2; }
        .sys-badge {
            display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px); padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.8rem;
            font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.15); margin-bottom: 2rem;
        }
        .banner-title { font-size: 3rem; font-weight: 800; line-height: 1.1; letter-spacing: -1px; margin-bottom: 1.5rem; }
        .banner-subtitle { font-size: 1.1rem; color: rgba(255,255,255,0.8); max-width: 480px; line-height: 1.6; font-weight: 400; }
        .auth-form-wrap {
            flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; background: #ffffff; position: relative;
        }
        .login-container { width: 100%; max-width: 420px; }
        .login-header { text-align: left; margin-bottom: 2.5rem; }
        .login-header h2 { font-weight: 800; font-size: 2rem; letter-spacing: -0.5px; color: var(--text-main); margin-bottom: 0.5rem; }
        .login-header p { font-size: 0.95rem; color: var(--text-muted); margin: 0; }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem; }
        .input-icon-wrap i.prefix { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; pointer-events: none; transition: 0.2s; }
        .form-control { width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; font-size: 0.95rem; color: var(--text-main); transition: all 0.2s; font-family: inherit; }
        .form-control:focus { outline: none; background: #ffffff; border-color: var(--brand-primary); box-shadow: 0 0 0 4px var(--focus-ring); }
        .form-control:focus + i.prefix { color: var(--brand-primary); }
        .pwd-toggle { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; color: #94a3b8; cursor: pointer; transition: 0.2s; }
        .pwd-toggle:hover { color: var(--text-main); }
        .btn-submit { width: 100%; padding: 0.875rem; background: var(--brand-gradient); color: #fff; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 0.5rem; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(67, 56, 202, 0.25); }
        .form-alert { padding: 1rem; border-radius: 10px; font-size: 0.875rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem; }
        .form-alert.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; }
        .form-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        @media (max-width: 900px) { .auth-banner { display: none; } }
    </style>
</head>
<body>
<main class="auth-layout">
    <div class="auth-banner">
        <div class="banner-content">
            <div class="sys-badge"><span class="sys-badge-dot"></span> Secure Configuration</div>
            <h1 class="banner-title">Shining Bright<br>Vocational School</h1>
            <p class="banner-subtitle">Update your administrative credentials using our encrypted communication module.</p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="login-container">
            <?php if (!$isValid): ?>
                <div class="login-header">
                    <h2>Link Expired</h2>
                    <p>This password reset link is invalid or has expired.</p>
                </div>
                <div class="form-alert error">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>For security reasons, recovery links expire after 1 hour. Please request a new link.</div>
                </div>
                <a href="forgot_password.php" class="btn-submit" style="text-decoration: none;">Request New Link</a>
            <?php else: ?>
                <div class="login-header">
                    <h2>Set New Password</h2>
                    <p>Please enter your new secure password below.</p>
                </div>
                <?php if(isset($_SESSION['reset_error'])): ?>
                    <div class="form-alert error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= htmlspecialchars($_SESSION['reset_error']); unset($_SESSION['reset_error']); ?></div>
                    </div>
                <?php endif; ?>
                <form action="../ResetPasswordController.php" method="POST" id="resetPwForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <div class="form-label"><label for="newPassword">New Password</label></div>
                        <div class="input-icon-wrap">
                            <input type="password" name="password" id="newPassword" class="form-control" placeholder="Minimum 8 characters" required>
                            <i class="bi bi-lock prefix"></i>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <div class="form-label"><label for="confirmPassword">Confirm Password</label></div>
                        <div class="input-icon-wrap">
                            <input type="password" name="password_confirm" id="confirmPassword" class="form-control" placeholder="Retype new password" required>
                            <i class="bi bi-lock prefix"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">Update Password <i class="bi bi-check2"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
if(document.getElementById('resetPwForm')) {
    document.getElementById('resetPwForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
        btn.style.opacity = '0.85';
        btn.style.pointerEvents = 'none';
    });
}
</script>
</body>
</html>
