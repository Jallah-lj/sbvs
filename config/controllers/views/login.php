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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --accent:       #6366f1;
            --accent-2:     #818cf8;
            --accent-dark:  #4f46e5;
            --glass-bg:     rgba(255,255,255,0.065);
            --glass-border: rgba(255,255,255,0.14);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Rich multi-stop mesh gradient */
            background:
                radial-gradient(ellipse at 20% 50%, rgba(99,102,241,0.35) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 20%, rgba(139,92,246,0.28) 0%, transparent 50%),
                radial-gradient(ellipse at 60% 80%, rgba(6,182,212,0.2)   0%, transparent 50%),
                linear-gradient(135deg, #050816 0%, #0f1117 50%, #0c0f1a 100%);
            /* Allow scrolling on short/landscape mobile instead of clipping */
            overflow-y: auto;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Dot-grid background overlay ─────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Floating orbs ───────────────────────────────────────── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.45;
            pointer-events: none;
            animation: float 10s ease-in-out infinite;
            z-index: 0;
        }
        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #6366f1, #4f46e5);
            top: -150px; left: -120px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #8b5cf6, #7c3aed);
            bottom: -100px; right: -80px;
            animation-delay: -3.5s;
        }
        .orb-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #06b6d4, #0284c7);
            top: 45%; left: 58%;
            animation-delay: -6s;
            opacity: 0.25;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-28px) scale(1.04); }
        }

        /* ── Login card ──────────────────────────────────────────── */
        .login-card {
            width: 100%;
            max-width: 430px;
            position: relative;
            z-index: 10;
            animation: cardIn 0.65s cubic-bezier(0.16,1,0.3,1) forwards;
            opacity: 0;
        }

        /* Gradient border via pseudo-element */
        .login-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 25px;
            background: linear-gradient(135deg,
                rgba(99,102,241,0.55) 0%,
                rgba(139,92,246,0.3) 50%,
                rgba(6,182,212,0.25) 100%);
            z-index: -1;
        }

        .login-card-inner {
            background: var(--glass-bg);
            backdrop-filter: blur(28px) saturate(1.6);
            -webkit-backdrop-filter: blur(28px) saturate(1.6);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.75rem 2.5rem 2.25rem;
            position: relative;
            overflow: hidden;
        }

        /* Subtle shimmer line at card top */
        .login-card-inner::before {
            content: '';
            position: absolute;
            top: 0; left: 20%; right: 20%;
            height: 1px;
            background: linear-gradient(90deg,
                transparent, rgba(255,255,255,0.35), transparent);
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(32px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* ── Brand ───────────────────────────────────────────────── */
        .brand-logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 0.6rem;
        }

        .brand-title {
            text-align: center;
            font-weight: 800;
            font-size: 1.35rem;
            color: #fff;
            letter-spacing: -0.03em;
            margin-bottom: .3rem;
        }

        .brand-subtitle {
            text-align: center;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.45);
            margin-bottom: 1.85rem;
            letter-spacing: 0.2px;
        }

        /* ── Error alert ─────────────────────────────────────────── */
        .login-alert {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-size: 0.84rem;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.38s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%       { transform: translateX(-5px); }
            75%       { transform: translateX(5px); }
        }

        /* ── Form ────────────────────────────────────────────────── */
        .form-group { margin-bottom: 1.2rem; }

        .form-group label {
            display: block;
            font-size: 0.76rem;
            font-weight: 600;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
            letter-spacing: 0.9px;
            margin-bottom: 0.45rem;
        }

        .input-wrap { position: relative; }

        .input-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3);
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.22s;
        }

        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.065);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 0.78rem 2.8rem 0.78rem 2.8rem;
            font-size: 0.9rem;
            color: #fff;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.22s, background 0.22s, box-shadow 0.22s;
            outline: none;
        }

        .input-wrap input::placeholder { color: rgba(255,255,255,0.28); }

        .input-wrap input:focus {
            border-color: rgba(99,102,241,0.7);
            background: rgba(99,102,241,0.08);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
        }

        .input-wrap input:focus + .input-icon { color: var(--accent-2); }

        /* Password toggle */
        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.32);
            cursor: pointer;
            font-size: 0.95rem;
            padding: 0;
            transition: color 0.22s;
        }
        .pwd-toggle:hover { color: rgba(255,255,255,0.65); }

        /* ── Submit button ────────────────────────────────────────── */
        .btn-login {
            width: 100%;
            padding: 0.82rem;
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.93rem;
            font-weight: 700;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.22s, box-shadow 0.22s;
            box-shadow: 0 4px 18px rgba(99,102,241,0.4);
            letter-spacing: 0.1px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(99,102,241,0.5);
        }
        .btn-login:active { transform: translateY(0); }

        /* Shine sweep on hover */
        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg,
                transparent, rgba(255,255,255,0.18), transparent);
            transition: left 0.5s;
        }
        .btn-login:hover::after { left: 100%; }

        /* ── Divider ─────────────────────────────────────────────── */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.4rem 0 1rem;
            color: rgba(255,255,255,0.2);
            font-size: 0.75rem;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        /* ── Footer ──────────────────────────────────────────────── */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.76rem;
            color: rgba(255,255,255,0.25);
        }

        /* ── Trust badges row ────────────────────────────────────── */
        .trust-row {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.75rem 1.2rem;
            margin-top: 1rem;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.28);
        }
        .trust-item i { font-size: 0.82rem; color: rgba(99,102,241,0.7); }
        /* ── Mobile responsiveness ───────────────────────────────── */
        @media (max-width: 480px) {
            .login-card-inner {
                padding: 1.85rem 1.4rem 1.6rem;
            }
            .login-card::before {
                border-radius: 21px;
            }
            .login-card-inner {
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Dot-grid overlay via body::before (CSS) -->

<!-- Floating orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="login-card">
<div class="login-card-inner">

    <!-- Logo -->
    <div class="brand-logo-wrap">
        <img src="../../assets/img/logo.svg" alt="Shining Bright" width="60" height="72">
    </div>

    <!-- Brand text -->
    <div class="text-center mb-1">
        <h3 class="fw-bolder mb-0" style="font-size:1.3rem; letter-spacing:-0.4px; color:#fff;">Shining Bright</h3>
        <p style="font-size:0.72rem; color:rgba(255,255,255,0.38); text-transform:uppercase; letter-spacing:1.2px; margin:4px 0 0;">Vocational School</p>
    </div>

    <div class="brand-title" style="margin-top:1.1rem;">SBVS Portal</div>
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
                <input type="email" name="email" id="loginEmail"
                       placeholder="admin@sbvs.edu" required autocomplete="email">
                <i class="bi bi-envelope input-icon"></i>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:1.6rem;">
            <label for="loginPassword">Password</label>
            <div class="input-wrap">
                <input type="password" name="password" id="loginPassword"
                       placeholder="••••••••" required autocomplete="current-password">
                <i class="bi bi-lock input-icon"></i>
                <button type="button" class="pwd-toggle" onclick="togglePwd()"
                        tabindex="-1" aria-label="Toggle password">
                    <i class="bi bi-eye" id="pwdIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            <i class="bi bi-shield-lock-fill me-2"></i>Secure Sign In
        </button>
    </form>

    <!-- Trust badges -->
    <div class="trust-row">
        <span class="trust-item"><i class="bi bi-shield-check"></i> Secure</span>
        <span class="trust-item"><i class="bi bi-lock"></i> Encrypted</span>
        <span class="trust-item"><i class="bi bi-person-check"></i> Role-Based</span>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> SBVS &middot; Vocational Student Management System
    </div>

</div><!-- /.login-card-inner -->
</div><!-- /.login-card -->

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