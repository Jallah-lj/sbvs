<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}

require_once '../../config.php';
require_once '../../database.php';
$db = (new Database())->getConnection();

$role          = $_SESSION['role'] ?? '';
$sessionBranch = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin  = ($role === 'Super Admin');

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: courses.php'); exit; }

// ── Fetch course + branch data ────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT c.id, c.name AS course_name, c.duration, c.registration_fee,
            c.tuition_fee, c.fees, c.description, c.branch_id,
            b.name AS branch_name, b.address AS branch_address,
            b.phone AS branch_phone, b.email AS branch_email
     FROM courses c
     LEFT JOIN branches b ON c.branch_id = b.id
     WHERE c.id = ?"
);
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) { header('Location: courses.php'); exit; }

// Branch admins may only print sheets for their own branch
if (!$isSuperAdmin && $sessionBranch && (int)$course['branch_id'] !== $sessionBranch) {
    header('Location: courses.php'); exit;
}

// ── Fetch relevant school-wide settings ───────────────────────────────────────
$settingRows = $db->query(
    "SELECT setting_key, setting_val FROM system_settings
     WHERE setting_key IN ('school_name','school_email','school_phone','currency_symbol')
     LIMIT 10"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$schoolName     = $settingRows['school_name']     ?? 'Shining Bright Vocational School';
$schoolPhone    = $settingRows['school_phone']    ?? '';
$schoolEmail    = $settingRows['school_email']    ?? '';
$currencySymbol = $settingRows['currency_symbol'] ?? '$';

$totalFee = (float)($course['fees'] ?: ((float)$course['registration_fee'] + (float)$course['tuition_fee']));

$printedAt = date('F j, Y \a\t g:i A');
$printedBy = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Info Sheet – <?= htmlspecialchars($course['course_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* ── Variables ──────────────────────────────────────────────── */
        :root {
            --primary:       #4f46e5;
            --primary-dark:  #3730a3;
            --accent:        #f59e0b;
            --accent-light:  #fef3c7;
            --success:       #059669;
            --text:          #0f172a;
            --muted:         #64748b;
            --border:        #e2e8f0;
            --bg:            #f1f5f9;
            --white:         #ffffff;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Toolbar (no-print) ─────────────────────────────────────── */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar .title {
            flex: 1;
            font-weight: 700;
            font-size: .95rem;
            color: var(--text);
        }
        .btn-print {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 22px;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .btn-back {
            background: transparent;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 8px 18px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .btn-back:hover { background: var(--bg); }
        .btn-print:hover { background: var(--primary-dark); }

        /* ── Sheet wrapper ──────────────────────────────────────────── */
        .sheet-wrap {
            max-width: 820px;
            margin: 32px auto 60px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .page-label {
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 0 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── Sheet card ─────────────────────────────────────────────── */
        .sheet-card {
            background: var(--white);
            border-radius: 14px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.06), 0 10px 20px -3px rgba(0,0,0,.06);
            overflow: hidden;
            position: relative;
        }

        /* ── Guilloche bars ─────────────────────────────────────────── */
        .guilloche { display: block; width: 100%; height: 18px; overflow: hidden; }

        /* ── Accent header band ─────────────────────────────────────── */
        .sheet-header-band {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 28px 40px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sheet-header-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sheet-header-brand img { filter: drop-shadow(0 2px 6px rgba(0,0,0,.35)); }
        .school-name {
            font-size: 1.35rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -.02em;
            line-height: 1.15;
        }
        .school-sub {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,.65);
        }
        .badge-sheet-type {
            background: rgba(255,255,255,.18);
            border: 1.5px solid rgba(255,255,255,.4);
            border-radius: 30px;
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 6px 16px;
            white-space: nowrap;
        }

        /* ── Sheet body ─────────────────────────────────────────────── */
        .sheet-body { padding: 36px 40px 32px; position: relative; }
        .sheet-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 4px;
            color: rgba(79,70,229,0.04);
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
            text-transform: uppercase;
            user-select: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sheet-content { position: relative; z-index: 1; }

        /* ── Course hero ────────────────────────────────────────────── */
        .course-hero {
            background: linear-gradient(135deg, #ede9fe 0%, #fef3c7 100%);
            border: 1.5px solid #c4b5fd;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .course-title {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary-dark);
            letter-spacing: -.02em;
            line-height: 1.2;
        }
        .course-branch-tag {
            font-size: .75rem;
            font-weight: 700;
            color: var(--muted);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .duration-pill {
            background: var(--primary);
            color: #fff;
            border-radius: 30px;
            padding: 10px 22px;
            font-weight: 800;
            font-size: .95rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Fee summary cards ──────────────────────────────────────── */
        .fee-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 28px;
        }
        .fee-card {
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            text-align: center;
        }
        .fee-card.total {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .fee-card-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .fee-card.total .fee-card-label { color: rgba(255,255,255,.8); }
        .fee-card-amount {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.02em;
        }
        .fee-card.total .fee-card-amount { color: #fff; font-size: 1.55rem; }
        .fee-card-note {
            font-size: .7rem;
            color: var(--muted);
            margin-top: 4px;
        }
        .fee-card.total .fee-card-note { color: rgba(255,255,255,.7); }

        /* ── Two-column grid ────────────────────────────────────────── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        @media (max-width: 600px) { .two-col { grid-template-columns: 1fr; } }

        /* ── Info section ───────────────────────────────────────────── */
        .info-section { margin-bottom: 28px; }
        .section-label {
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--primary);
            border-left: 3px solid var(--primary);
            padding-left: 8px;
            margin-bottom: 12px;
        }
        .info-text {
            font-size: .9rem;
            color: #374151;
            line-height: 1.65;
        }

        /* ── Service list ───────────────────────────────────────────── */
        .service-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .service-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .88rem;
            color: #374151;
            line-height: 1.5;
        }
        .service-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: #fff;
            font-size: .72rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Requirements list ──────────────────────────────────────── */
        .req-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .req-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .88rem;
            color: #374151;
            line-height: 1.5;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .req-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--accent);
            color: #78350f;
            font-size: .72rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Branch location box ────────────────────────────────────── */
        .location-card {
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 20px 24px;
            background: #f8fafc;
        }
        .location-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .88rem;
            color: #374151;
            margin-bottom: 10px;
        }
        .location-item:last-child { margin-bottom: 0; }
        .location-icon {
            color: var(--primary);
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Steps how-to-apply ─────────────────────────────────────── */
        .steps-list { counter-reset: step; display: flex; flex-direction: column; gap: 10px; }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: .88rem;
            color: #374151;
            line-height: 1.5;
        }
        .step-num {
            counter-increment: step;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--success) 0%, #065f46 100%);
            color: #fff;
            font-size: .75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Sheet footer ───────────────────────────────────────────── */
        .sheet-footer {
            border-top: 1.5px dashed var(--border);
            padding-top: 16px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 12px;
        }
        .footer-note { font-size: .7rem; color: var(--muted); line-height: 1.55; max-width: 340px; }
        .footer-meta { font-size: .7rem; color: var(--muted); text-align: right; }

        /* ── Print overrides ────────────────────────────────────────── */
        @media print {
            body { background: #fff !important; }
            .toolbar { display: none !important; }
            .sheet-wrap { margin: 0 !important; gap: 0 !important; max-width: 100% !important; }
            .page-label { display: none !important; }
            .sheet-card {
                box-shadow: none !important;
                border-radius: 0 !important;
                page-break-after: always;
            }
            .sheet-card:last-child { page-break-after: avoid; }
            .sheet-header-band, .fee-card.total, .duration-pill,
            .service-icon, .req-num, .step-num, .course-hero {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<!-- ── Toolbar ──────────────────────────────────────────────────────── -->
<div class="toolbar no-print">
    <a href="courses.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to Courses
    </a>
    <span class="title">
        Course Information Sheet — <?= htmlspecialchars($course['course_name']) ?>
    </span>
    <button onclick="window.print()" class="btn-print">
        <i class="bi bi-printer-fill"></i> Print / Save PDF
    </button>
</div>

<!-- ── Sheet Pages ──────────────────────────────────────────────────── -->
<div class="sheet-wrap">

    <!-- ════════════════════════════════════════════════════════════
         PAGE 1 — FRONT
         Audience: Prospective students & parents
    ═════════════════════════════════════════════════════════════ -->
    <div class="page-label">Page 1 — Front</div>
    <div class="sheet-card">

        <!-- Top guilloche -->
        <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <pattern id="gp" x="0" y="0" width="40" height="18" patternUnits="userSpaceOnUse">
                    <path d="M0 9 Q5 0 10 9 Q15 18 20 9 Q25 0 30 9 Q35 18 40 9" fill="none" stroke="#6366f1" stroke-width="0.6" opacity="0.3"/>
                    <path d="M0 9 Q5 2 10 9 Q15 16 20 9 Q25 2 30 9 Q35 16 40 9" fill="none" stroke="#8b5cf6" stroke-width="0.4" opacity="0.2"/>
                </pattern>
            </defs>
            <rect width="820" height="18" fill="url(#gp)"/>
        </svg>

        <!-- Header band -->
        <div class="sheet-header-band">
            <div class="sheet-header-brand">
                <img src="../../assets/img/logo.svg" alt="SBVS Logo" width="50" height="60"
                     onerror="this.style.display='none'">
                <div>
                    <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
                    <div class="school-sub">Official Course Information Sheet</div>
                    <?php if (!empty($course['branch_name'])): ?>
                    <div style="font-size:.75rem; color:rgba(255,255,255,.75); margin-top:4px; display:flex; align-items:center; gap:5px;">
                        <i class="bi bi-building"></i>
                        <?= htmlspecialchars($course['branch_name']) ?> Branch
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="badge-sheet-type">
                <i class="bi bi-file-earmark-text me-1"></i> Prospectus
            </div>
        </div>

        <!-- Body -->
        <div class="sheet-body">
        <div class="sheet-watermark" aria-hidden="true"><?= strtoupper(htmlspecialchars($schoolName)) ?> · OFFICIAL INFORMATION SHEET</div>
        <div class="sheet-content">

            <!-- Course Hero -->
            <div class="course-hero">
                <div>
                    <div class="course-title"><?= htmlspecialchars($course['course_name']) ?></div>
                    <?php if (!empty($course['branch_name'])): ?>
                    <div class="course-branch-tag">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?= htmlspecialchars($course['branch_name']) ?> Branch
                    </div>
                    <?php endif; ?>
                </div>
                <div class="duration-pill">
                    <i class="bi bi-clock-fill"></i>
                    <?= htmlspecialchars($course['duration']) ?>
                </div>
            </div>

            <!-- Fee Cards -->
            <div class="fee-row">
                <div class="fee-card">
                    <div class="fee-card-label"><i class="bi bi-person-check me-1"></i>Registration Fee</div>
                    <div class="fee-card-amount"><?= $currencySymbol . number_format((float)$course['registration_fee'], 2) ?></div>
                    <div class="fee-card-note">One-time enrolment</div>
                </div>
                <div class="fee-card">
                    <div class="fee-card-label"><i class="bi bi-mortarboard me-1"></i>Tuition Fee</div>
                    <div class="fee-card-amount"><?= $currencySymbol . number_format((float)$course['tuition_fee'], 2) ?></div>
                    <div class="fee-card-note">Full course tuition</div>
                </div>
                <div class="fee-card total">
                    <div class="fee-card-label">Total Fees</div>
                    <div class="fee-card-amount"><?= $currencySymbol . number_format($totalFee, 2) ?></div>
                    <div class="fee-card-note">Registration + Tuition</div>
                </div>
            </div>

            <!-- Two-column: About + Services -->
            <div class="two-col">

                <!-- About this course -->
                <div class="info-section">
                    <div class="section-label">About This Course</div>
                    <div class="info-text">
                        <?php if (!empty($course['description'])): ?>
                            <?= nl2br(htmlspecialchars($course['description'])) ?>
                        <?php else: ?>
                            This programme equips students with practical, industry-ready skills in
                            <strong><?= htmlspecialchars($course['course_name']) ?></strong>.
                            Graduates receive a nationally recognised certificate upon successful completion.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Services offered -->
                <div class="info-section">
                    <div class="section-label">Services Offered</div>
                    <ul class="service-list">
                        <li>
                            <span class="service-icon"><i class="bi bi-tools"></i></span>
                            <span><strong>Hands-on practical training</strong> with industry-standard tools and equipment.</span>
                        </li>
                        <li>
                            <span class="service-icon"><i class="bi bi-award-fill"></i></span>
                            <span><strong>Certified completion</strong> — nationally recognised certificate issued on graduation.</span>
                        </li>
                        <li>
                            <span class="service-icon"><i class="bi bi-briefcase-fill"></i></span>
                            <span><strong>Job placement assistance</strong> — career guidance and employer connections.</span>
                        </li>
                        <li>
                            <span class="service-icon"><i class="bi bi-person-hearts"></i></span>
                            <span><strong>Student counselling</strong> — academic and personal support services.</span>
                        </li>
                        <li>
                            <span class="service-icon"><i class="bi bi-book-fill"></i></span>
                            <span><strong>Library &amp; resource centre</strong> with course materials and workshops.</span>
                        </li>
                    </ul>
                </div>

            </div><!-- /two-col -->

            <!-- Branch contact -->
            <div class="info-section">
                <div class="section-label">Branch Contact</div>
                <div class="location-card">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <div class="location-item">
                                <i class="bi bi-building location-icon"></i>
                                <div>
                                    <strong><?= htmlspecialchars($course['branch_name'] ?: $schoolName) ?></strong><br>
                                    <span style="font-size:.8rem; color:var(--muted);">Branch Campus</span>
                                </div>
                            </div>
                            <?php if (!empty($course['branch_address'])): ?>
                            <div class="location-item">
                                <i class="bi bi-geo-alt-fill location-icon"></i>
                                <span><?= nl2br(htmlspecialchars($course['branch_address'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php $phone = $course['branch_phone'] ?: $schoolPhone; ?>
                            <?php if ($phone): ?>
                            <div class="location-item">
                                <i class="bi bi-telephone-fill location-icon"></i>
                                <span><?= htmlspecialchars($phone) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php $email = $course['branch_email'] ?: $schoolEmail; ?>
                            <?php if ($email): ?>
                            <div class="location-item">
                                <i class="bi bi-envelope-fill location-icon"></i>
                                <span><?= htmlspecialchars($email) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="location-item">
                                <i class="bi bi-clock-fill location-icon"></i>
                                <span>Mon – Fri&nbsp;&nbsp;8:00 AM – 5:00 PM<br>
                                      Sat&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;9:00 AM – 1:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sheet-footer">
                <div class="footer-note">
                    This document is an official information sheet issued by
                    <?= htmlspecialchars($schoolName) ?>.
                    For admissions enquiries please visit the branch or contact us directly.
                </div>
                <div class="footer-meta">
                    Printed by <strong><?= $printedBy ?></strong><br>
                    <?= $printedAt ?>
                </div>
            </div>

        </div><!-- /sheet-content -->
        </div><!-- /sheet-body -->

        <!-- Bottom guilloche -->
        <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect width="820" height="18" fill="url(#gp)"/>
        </svg>

    </div><!-- /page 1 card -->


    <!-- ════════════════════════════════════════════════════════════
         PAGE 2 — BACK
         Audience: Applicants — what to bring / how to enrol
    ═════════════════════════════════════════════════════════════ -->
    <div class="page-label">Page 2 — Back</div>
    <div class="sheet-card">

        <!-- Top guilloche -->
        <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <pattern id="gp2" x="0" y="0" width="40" height="18" patternUnits="userSpaceOnUse">
                    <path d="M0 9 Q5 0 10 9 Q15 18 20 9 Q25 0 30 9 Q35 18 40 9" fill="none" stroke="#f59e0b" stroke-width="0.6" opacity="0.3"/>
                    <path d="M0 9 Q5 2 10 9 Q15 16 20 9 Q25 2 30 9 Q35 16 40 9" fill="none" stroke="#d97706" stroke-width="0.4" opacity="0.2"/>
                </pattern>
            </defs>
            <rect width="820" height="18" fill="url(#gp2)"/>
        </svg>

        <!-- Header band (amber for back page) -->
        <div class="sheet-header-band" style="background: linear-gradient(135deg, #d97706 0%, #92400e 100%);">
            <div class="sheet-header-brand">
                <img src="../../assets/img/logo.svg" alt="SBVS Logo" width="44" height="53"
                     onerror="this.style.display='none'">
                <div>
                    <div class="school-name"><?= htmlspecialchars($course['course_name']) ?></div>
                    <div class="school-sub">Registration Requirements &amp; How to Enrol</div>
                </div>
            </div>
            <div class="badge-sheet-type" style="background:rgba(255,255,255,.15);">
                <i class="bi bi-clipboard2-check me-1"></i> Admissions Guide
            </div>
        </div>

        <!-- Body -->
        <div class="sheet-body">
        <div class="sheet-watermark" aria-hidden="true"><?= strtoupper(htmlspecialchars($schoolName)) ?> · OFFICIAL INFORMATION SHEET</div>
        <div class="sheet-content">

            <div class="two-col">

                <!-- Registration requirements -->
                <div class="info-section">
                    <div class="section-label">Required Documents</div>
                    <p style="font-size:.82rem; color:var(--muted); margin-bottom:12px;">
                        Please bring <strong>originals and one clear photocopy</strong> of each document listed below when you visit the admissions office.
                    </p>
                    <ol class="req-list">
                        <li>
                            <span class="req-num">1</span>
                            <span><strong>National ID Card or Passport</strong> — valid government-issued photo identification.</span>
                        </li>
                        <li>
                            <span class="req-num">2</span>
                            <span><strong>Birth Certificate</strong> — original or certified copy.</span>
                        </li>
                        <li>
                            <span class="req-num">3</span>
                            <span><strong>School Leaving Certificate</strong> or equivalent academic credential (WAEC, Junior WAEC, or primary school certificate).</span>
                        </li>
                        <li>
                            <span class="req-num">4</span>
                            <span><strong>2 Passport-size Photographs</strong> — recent, clear, white background.</span>
                        </li>
                        <li>
                            <span class="req-num">5</span>
                            <span><strong>Completed Application Form</strong> — available free of charge at the admissions office or downloadable from the portal.</span>
                        </li>
                        <li>
                            <span class="req-num">6</span>
                            <span><strong>Registration Fee Payment</strong> — <strong><?= $currencySymbol . number_format((float)$course['registration_fee'], 2) ?></strong> payable at the cashier upon form submission.</span>
                        </li>
                    </ol>

                    <div style="margin-top:14px; padding:10px 14px; background:#fef3c7; border:1.5px solid #fcd34d; border-radius:8px; font-size:.8rem; color:#78350f; display:flex; gap:8px; align-items:flex-start; -webkit-print-color-adjust:exact; print-color-adjust:exact;">
                        <i class="bi bi-info-circle-fill" style="flex-shrink:0; margin-top:1px;"></i>
                        <span>Applicants under 18 must also submit a <strong>Parent / Guardian Consent Letter</strong> signed and dated.</span>
                    </div>
                </div>

                <!-- Right column: how-to-apply steps + location -->
                <div>
                    <div class="info-section">
                        <div class="section-label">How to Apply</div>
                        <div class="steps-list">
                            <div class="step-item">
                                <span class="step-num">1</span>
                                <span>Visit the <strong><?= htmlspecialchars($course['branch_name'] ?: 'nearest') ?> branch</strong> admissions office during working hours.</span>
                            </div>
                            <div class="step-item">
                                <span class="step-num">2</span>
                                <span>Collect and complete the <strong>Application Form</strong> (free of charge).</span>
                            </div>
                            <div class="step-item">
                                <span class="step-num">3</span>
                                <span>Submit the completed form along with <strong>all required documents</strong> to the admissions officer.</span>
                            </div>
                            <div class="step-item">
                                <span class="step-num">4</span>
                                <span>Pay the <strong>Registration Fee</strong> (<?= $currencySymbol . number_format((float)$course['registration_fee'], 2) ?>) at the cashier and receive your payment receipt.</span>
                            </div>
                            <div class="step-item">
                                <span class="step-num">5</span>
                                <span>Await your <strong>Enrolment Confirmation</strong> and class schedule — issued within 1–3 working days.</span>
                            </div>
                            <div class="step-item">
                                <span class="step-num">6</span>
                                <span>Pay the <strong>Tuition Fee</strong> (<?= $currencySymbol . number_format((float)$course['tuition_fee'], 2) ?>) on or before the first day of class.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Branch location details -->
                    <div class="info-section">
                        <div class="section-label">Branch Location</div>
                        <div class="location-card">
                            <div class="location-item" style="margin-bottom:10px;">
                                <i class="bi bi-building location-icon"></i>
                                <div>
                                    <strong style="font-size:.95rem;"><?= htmlspecialchars($course['branch_name'] ?: $schoolName) ?></strong><br>
                                    <span style="font-size:.78rem; color:var(--muted);">
                                        <?= htmlspecialchars($schoolName) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($course['branch_address'])): ?>
                            <div class="location-item">
                                <i class="bi bi-geo-alt-fill location-icon"></i>
                                <span><?= nl2br(htmlspecialchars($course['branch_address'])) ?></span>
                            </div>
                            <?php else: ?>
                            <div class="location-item" style="color:var(--muted); font-size:.82rem;">
                                <i class="bi bi-geo-alt location-icon"></i>
                                <span><em>Address not yet set — update in Branches management.</em></span>
                            </div>
                            <?php endif; ?>
                            <?php $ph = $course['branch_phone'] ?: $schoolPhone; ?>
                            <?php if ($ph): ?>
                            <div class="location-item">
                                <i class="bi bi-telephone-fill location-icon"></i>
                                <span><?= htmlspecialchars($ph) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php $em = $course['branch_email'] ?: $schoolEmail; ?>
                            <?php if ($em): ?>
                            <div class="location-item">
                                <i class="bi bi-envelope-fill location-icon"></i>
                                <span><?= htmlspecialchars($em) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="location-item" style="margin-bottom:0;">
                                <i class="bi bi-clock-fill location-icon"></i>
                                <span style="font-size:.82rem;">
                                    Mon – Fri: 8:00 AM – 5:00 PM&nbsp;&nbsp;|&nbsp;&nbsp;Sat: 9:00 AM – 1:00 PM
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /two-col -->

            <!-- Footer disclaimer -->
            <div class="sheet-footer">
                <div class="footer-note">
                    <strong>Important:</strong> Enrolment is confirmed only after all required documents have been
                    verified and the registration fee has been paid in full. Fees are non-refundable once
                    the course has commenced.
                </div>
                <div style="text-align:right;">
                    <div style="font-size:.75rem; font-weight:700; color:var(--primary); margin-bottom:4px;">
                        <?= htmlspecialchars($schoolName) ?>
                    </div>
                    <div style="font-size:.7rem; color:var(--muted);">
                        Course: <strong><?= htmlspecialchars($course['course_name']) ?></strong>
                        &nbsp;·&nbsp;
                        Duration: <?= htmlspecialchars($course['duration']) ?>
                    </div>
                    <div style="font-size:.7rem; color:var(--muted); margin-top:2px;">
                        Printed by <?= $printedBy ?> &nbsp;·&nbsp; <?= $printedAt ?>
                    </div>
                </div>
            </div>

        </div><!-- /sheet-content -->
        </div><!-- /sheet-body -->

        <!-- Bottom guilloche -->
        <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect width="820" height="18" fill="url(#gp2)"/>
        </svg>

    </div><!-- /page 2 card -->

</div><!-- /sheet-wrap -->

</body>
</html>
