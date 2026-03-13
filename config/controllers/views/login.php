<?php 
session_start(); 
// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – SBVS Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --glass-bg: rgba(255,255,255,0.12);
            --glass-border: rgba(255,255,255,0.18);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #312e81 70%, #1e293b 100%);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Floating orbs ───────────────────────────── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            pointer-events: none;
            animation: float 8s ease-in-out infinite;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: #6366f1;
            top: -100px; left: -80px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 350px; height: 350px;
            background: #8b5cf6;
            bottom: -80px; right: -60px;
            animation-delay: -3s;
        }
        .orb-3 {
            width: 250px; height: 250px;
            background: #06b6d4;
            top: 50%; left: 60%;
            animation-delay: -5s;
            opacity: 0.25;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        /* ── Login card ──────────────────────────────── */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(24px) saturate(1.5);
            -webkit-backdrop-filter: blur(24px) saturate(1.5);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem 2.5rem 2.5rem;
            position: relative;
            z-index: 10;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards;
            opacity: 0;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Brand ───────────────────────────────────── */
        .brand-icon {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
        }
        .brand-icon i { font-size: 1.5rem; color: #fff; }
        .brand-title {
            text-align: center;
            font-weight: 800;
            font-size: 1.4rem;
            color: #fff;
            letter-spacing: -0.03em;
            margin-bottom: .35rem;
        }
        .brand-subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-bottom: 2rem;
        }

        /* ── Error alert ─────────────────────────────── */
        .login-alert {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* ── Form inputs ─────────────────────────────── */
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0.5rem;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.35);
            font-size: 1rem;
            transition: color 0.25s;
        }
        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 0.8rem 2.8rem 0.8rem 2.8rem;
            font-size: 0.9rem;
            color: #fff;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
            outline: none;
        }
        .input-wrap input::placeholder { color: rgba(255,255,255,0.3); }
        .input-wrap input:focus {
            border-color: var(--accent);
            background: rgba(99,102,241,0.08);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .input-wrap input:focus ~ i { color: var(--accent); }

        /* Password toggle */
        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.35);
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            transition: color 0.25s;
        }
        .pwd-toggle:hover { color: rgba(255,255,255,0.7); }

        /* ── Submit button ────────────────────────────── */
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.25s, box-shadow 0.25s;
            box-shadow: 0 4px 15px rgba(99,102,241,0.35);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99,102,241,0.45);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        /* Shine sweep */
        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s;
        }
        .btn-login:hover::after { left: 100%; }

        /* ── Footer ──────────────────────────────────── */
        .login-footer {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>

<!-- Floating orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="login-card">
    <div class="brand-icon d-flex align-items-center justify-content-center bg-transparent shadow-none" style="margin-bottom: 0.5rem;">
        <img src="../../assets/img/logo.svg" alt="Shining Bright" width="64" height="76">
    </div>
    <div class="text-center mb-4">
        <h3 class="fw-bolder mb-1" style="font-size: 1.5rem; letter-spacing: -0.5px;">Shining Bright</h3>
        <p class="text-white-50 small text-uppercase tracking-wider">Vocational School</p>
    </div>
    <div class="brand-title">SBVS Portal</div>
    <div class="brand-subtitle">Sign in to your management account</div>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="login-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="../AuthController.php" method="POST" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="form-group">
            <label for="loginEmail">Email Address</label>
            <div class="input-wrap">
                <input type="email" name="email" id="loginEmail" placeholder="admin@sbvs.edu" required autocomplete="email">
                <i class="bi bi-envelope"></i>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:1.75rem;">
            <label for="loginPassword">Password</label>
            <div class="input-wrap">
                <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                <i class="bi bi-lock"></i>
                <button type="button" class="pwd-toggle" onclick="togglePwd()" tabindex="-1" aria-label="Toggle password">
                    <i class="bi bi-eye" id="pwdIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            <i class="bi bi-shield-lock-fill me-2"></i>Secure Login
        </button>
    </form>

    <div class="login-footer">
        &copy; <?= date('Y') ?> SBVS &middot; Vocational Student Management System
    </div>
</div>

<script>
function togglePwd() {
    const inp = document.getElementById('loginPassword');
    const ico = document.getElementById('pwdIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        inp.type = 'password';
        ico.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Subtle loading state on submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Signing in...';
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.8';
});
</script>
</body>
</html>