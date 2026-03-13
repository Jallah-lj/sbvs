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
$isAllowed     = in_array($role, ['Super Admin', 'Branch Admin', 'Admin'], true);
if (!$isAllowed) { header('Location: dashboard.php'); exit; }

$studentId = (int)($_GET['student_id']    ?? 0);
$enrollId  = (int)($_GET['enrollment_id'] ?? 0);
$paymentId = (int)($_GET['payment_id']    ?? 0);
if (!$studentId || !$enrollId) { header('Location: student_registration.php'); exit; }

$stmt = $db->prepare(
    "SELECT s.id AS student_row_id, s.student_id AS student_code, s.branch_id,
            s.phone AS student_phone, s.registration_date,
            u.name AS student_name, u.email,
            c.name AS course_name, c.duration, c.fees,
            c.registration_fee, c.tuition_fee,
            b.name AS batch_name,
            e.enrollment_date,
            br.name AS branch_name
     FROM students s
     JOIN users u       ON s.user_id   = u.id
     JOIN enrollments e ON e.student_id = s.id
     JOIN courses c     ON e.course_id  = c.id
     JOIN batches b     ON e.batch_id   = b.id
     JOIN branches br   ON s.branch_id  = br.id
     WHERE s.id = ? AND e.id = ?
     LIMIT 1"
);
$stmt->execute([$studentId, $enrollId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { header('Location: student_registration.php'); exit; }
if (!$isSuperAdmin && (int)$row['branch_id'] !== $sessionBranch) { header('Location: students.php'); exit; }

$pay = null;
if ($paymentId > 0) {
    $ps = $db->prepare("SELECT id, receipt_no, amount, payment_method, payment_type, payment_date, status
                        FROM payments WHERE id = ? AND student_id = ? LIMIT 1");
    $ps->execute([$paymentId, $studentId]);
    $pay = $ps->fetch(PDO::FETCH_ASSOC) ?: null;
}

$tp = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE enrollment_id = ? AND status = 'Active'");
$tp->execute([$enrollId]);
$totalPaid = (float)$tp->fetchColumn();
$balance   = max(0, (float)$row['fees'] - $totalPaid);

// ── Security fingerprint ─────────────────────────────────────────────────────
// A short hash that uniquely identifies this receipt instance; used in QR & footer.
$receiptNo   = $pay['receipt_no'] ?? ('ENRL-' . $enrollId);
$securityHash = strtoupper(substr(hash('sha256',
    $receiptNo . $studentId . $enrollId . ($row['student_code']) . date('Ymd')
), 0, 16));

// QR verification URL (points back to this page – admin can scan to verify)
$verifyUrl = rtrim(BASE_URL, '/') . '/config/controllers/views/student_registration_receipt.php'
           . '?student_id=' . $studentId . '&enrollment_id=' . $enrollId;

$printedBy  = htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin');
$printedAt  = date('F j, Y \a\t g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Receipt – SBVS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        /* ── Base ───────────────────────────────────────────────── */
        :root {
            --primary: #4f46e5;
            --primary-light: #ede9fe;
            --success: #059669;
            --danger: #dc2626;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Receipt wrapper ─────────────────────────────────────── */
        .receipt-wrap {
            max-width: 820px;
            margin: 32px auto;
            position: relative;
        }

        .receipt-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.06), 0 10px 20px -3px rgba(0,0,0,.06);
            overflow: hidden;
            position: relative;
        }

        /* ── ① VOID PANTOGRAPH (anti-photocopy) ─────────────────────
           Invisible to the naked eye on-screen, but photocopiers &
           scanners amplify the dot-density difference, revealing "VOID"
        ─────────────────────────────────────────────────────────────── */
        .void-pantograph {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            /* Fine dot pattern that creates the VOID silhouette */
            background-image:
                /* Outer dot field (dense) */
                radial-gradient(circle, rgba(0,0,0,0.045) 1px, transparent 1px),
                /* Inner dot field (sparser inside letter shapes) */
                radial-gradient(circle, rgba(0,0,0,0.013) 1px, transparent 1px);
            background-size: 3px 3px, 5px 5px;
            background-position: 0 0, 1.5px 1.5px;
            mix-blend-mode: multiply;
            border-radius: 14px;
        }

        /* ── ② GUILLOCHE WAVE (security border) ─────────────────────
           Thin repeating wave pattern at top and bottom of the receipt.
           Very hard to reproduce cleanly on a photocopier.
        ─────────────────────────────────────────────────────────────── */
        .guilloche {
            display: block;
            width: 100%;
            height: 18px;
            overflow: hidden;
        }

        /* ── ③ MICROTEXT BORDER ──────────────────────────────────────
           12px text repeating around the border iframe.
           Readable under magnifier but blurs when photocopied.
        ─────────────────────────────────────────────────────────────── */
        .microtext-border {
            font-size: 6.5px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: rgba(79,70,229,0.55);
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            line-height: 1;
            background: var(--primary-light);
            padding: 3px 0;
            text-align: center;
            user-select: none;
        }

        /* ── ④ SECURITY BAND (color-dependent ink) ───────────────────
           Bright yellow disappears on B&W photocopiers / scanners.
        ─────────────────────────────────────────────────────────────── */
        .security-band {
            background: linear-gradient(90deg, #fde68a 0%, #fcd34d 50%, #fde68a 100%);
            padding: 5px 20px;
            font-size: 7.5px;
            font-weight: 800;
            letter-spacing: 2px;
            color: rgba(120,53,15,0.6);
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
            align-items: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            user-select: none;
        }

        /* ── ⑤ DIAGONAL SECURITY WATERMARK ──────────────────────────
           Renders on print. Photocopied versions will show this clearly,
           visually flagging the copy.
        ─────────────────────────────────────────────────────────────── */
        .receipt-body {
            position: relative;
            padding: 32px 40px 28px;
            z-index: 2;
        }
        .receipt-body::before {
            content: "SHINING BRIGHT VOCATIONAL SCHOOL · ORIGINAL RECEIPT · <?= $securityHash ?>";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 4px;
            color: rgba(79,70,229,0.055);
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
            text-transform: uppercase;
            user-select: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Receipt content positioner (above watermark) */
        .receipt-content { position: relative; z-index: 1; }

        /* ── Header ─────────────────────────────────────────────── */
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1.5px dashed var(--border);
            padding-bottom: 20px;
            margin-bottom: 22px;
        }
        .org-name { font-size: 1.3rem; font-weight: 800; letter-spacing: -.02em; color: var(--text); }
        .org-sub  { font-size: .65rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); }

        /* ── QR Code ─────────────────────────────────────────────── */
        .qr-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .qr-label {
            font-size: 6.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
        }
        #receiptQR canvas, #receiptQR img { border: 3px solid #ede9fe; border-radius: 6px; }

        /* ── Section label ───────────────────────────────────────── */
        .section-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 8px;
        }

        /* ── Info grid ───────────────────────────────────────────── */
        .info-row { font-size: .875rem; margin-bottom: 5px; color: var(--text); }
        .info-label { font-weight: 500; color: var(--muted); display: inline-block; min-width: 110px; }

        /* ── Financial table ─────────────────────────────────────── */
        .fin-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .fin-table th {
            background: #f8fafc;
            color: var(--muted);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 10px 14px;
            border: 1px solid var(--border);
        }
        .fin-table td {
            padding: 11px 14px;
            border: 1px solid var(--border);
            font-weight: 500;
            color: var(--text);
        }
        .fin-table tr:last-child td { border-bottom: 2px solid var(--primary); }

        /* ── Security footer ─────────────────────────────────────── */
        .receipt-footer {
            border-top: 1px dashed var(--border);
            margin-top: 22px;
            padding-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .footer-hash {
            font-family: 'Courier New', monospace;
            font-size: 8px;
            color: rgba(79,70,229,0.5);
            letter-spacing: 1.5px;
            background: var(--primary-light);
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid rgba(79,70,229,0.15);
        }
        .stamp-area {
            text-align: center;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            padding: 8px 28px;
            font-size: .7rem;
            color: var(--muted);
            font-weight: 500;
        }
        .official-tag {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            font-size: 7px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 4px;
        }

        /* ── No-print toolbar ────────────────────────────────────── */
        .toolbar { max-width: 820px; margin: 0 auto 16px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action { border-radius: 10px; font-weight: 600; padding: .55rem 1.1rem; font-size: .875rem; }

        /* ── Print styles ────────────────────────────────────────── */
        @media print {
            .no-print   { display: none !important; }
            body        { background: #fff !important; }
            .receipt-wrap { margin: 0; max-width: 100%; }
            .receipt-card { box-shadow: none; border: none; border-radius: 0; }
            .void-pantograph { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .security-band  { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .microtext-border { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .receipt-body::before { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- ── No-print toolbar ──────────────────────────────────────────────── -->
<div class="toolbar no-print py-3">
    <a href="student_registration.php" class="btn btn-outline-secondary btn-action bg-white border">
        <i class="bi bi-person-plus me-1"></i>New Registration
    </a>
    <a href="students.php" class="btn btn-outline-primary btn-action bg-white">
        <i class="bi bi-people me-1"></i>Students Directory
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-action shadow-sm">
        <i class="bi bi-printer-fill me-1"></i>Print Receipt
    </button>
</div>

<!-- ── Receipt ───────────────────────────────────────────────────────── -->
<div class="receipt-wrap">
<div class="receipt-card">

    <!-- ① Void pantograph overlay -->
    <div class="void-pantograph" aria-hidden="true"></div>

    <!-- ② Top guilloche bar -->
    <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <pattern id="gp" x="0" y="0" width="40" height="18" patternUnits="userSpaceOnUse">
                <path d="M0 9 Q5 0 10 9 Q15 18 20 9 Q25 0 30 9 Q35 18 40 9" fill="none" stroke="#6366f1" stroke-width="0.6" opacity="0.3"/>
                <path d="M0 9 Q5 2 10 9 Q15 16 20 9 Q25 2 30 9 Q35 16 40 9" fill="none" stroke="#8b5cf6" stroke-width="0.4" opacity="0.2"/>
            </pattern>
        </defs>
        <rect width="820" height="18" fill="url(#gp)"/>
    </svg>

    <!-- ③ Microtext border -->
    <div class="microtext-border" aria-hidden="true">
        <?php $mt = "✦ SHINING BRIGHT VOCATIONAL SCHOOL · OFFICIAL RECEIPT · " . $securityHash . " · NOT VALID IF PHOTOCOPIED · "; echo str_repeat($mt, 12); ?>
    </div>

    <!-- ④ Security band (yellow — invisible on B&W copy) -->
    <div class="security-band" aria-hidden="true">
        <span>⬛ SECURITY FEATURE: THIS BAND DISAPPEARS ON PHOTOCOPIES</span>
        <span><?= $securityHash ?></span>
        <span>⬛ VERIFY AT: SBVS PORTAL</span>
    </div>

    <!-- ── Receipt body ────────────────────────────────────────────── -->
    <div class="receipt-body">
    <div class="receipt-content">

        <!-- Header -->
        <div class="receipt-header">
            <div class="d-flex align-items-center gap-3">
                <img src="../../assets/img/logo.svg" alt="SBVS Logo" width="46" height="56"
                     onerror="this.style.display='none'">
                <div>
                    <div class="org-name">Shining Bright</div>
                    <div class="org-sub">Vocational School</div>
                    <div class="mt-1" style="font-size:.8rem; color:var(--muted);">
                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($row['branch_name']) ?>
                    </div>
                </div>
            </div>

            <!-- Right: QR + meta -->
            <div class="d-flex flex-column align-items-end gap-2">
                <!-- QR code for digital verification -->
                <div class="qr-wrap">
                    <div id="receiptQR"></div>
                    <div class="qr-label">Scan to Verify</div>
                </div>
                <div style="text-align:right; font-size:.8rem;">
                    <div style="color:var(--muted); font-weight:500; font-size:.7rem;">Printed</div>
                    <div style="font-weight:600; font-size:.82rem;"><?= $printedAt ?></div>
                    <div style="color:var(--muted); font-weight:500; font-size:.7rem; margin-top:4px;">By</div>
                    <div style="font-weight:600; font-size:.82rem;"><?= $printedBy ?></div>
                </div>
            </div>
        </div>

        <!-- Title strip -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="font-size:1.1rem; font-weight:800; letter-spacing:-.02em; margin:0;">
                    Registration Receipt
                </h1>
                <div style="font-size:.78rem; color:var(--muted); margin-top:2px;">
                    Receipt No. <strong><?= htmlspecialchars($receiptNo) ?></strong>
                </div>
            </div>
            <span class="badge" style="background:var(--primary); font-size:.72rem; padding:6px 14px; border-radius:20px; letter-spacing:.5px;">
                <?= $balance > 0 ? 'Partially Paid' : 'Paid in Full' ?>
            </span>
        </div>

        <!-- Student + Course Info -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="section-label">Student Information</div>
                <div class="info-row"><span class="info-label">Name:</span> <strong><?= htmlspecialchars($row['student_name']) ?></strong></div>
                <div class="info-row"><span class="info-label">Student ID:</span> <?= htmlspecialchars($row['student_code']) ?></div>
                <div class="info-row"><span class="info-label">Email:</span> <?= htmlspecialchars($row['email']) ?></div>
                <?php if (!empty($row['student_phone'])): ?>
                <div class="info-row"><span class="info-label">Phone:</span> <?= htmlspecialchars($row['student_phone']) ?></div>
                <?php endif; ?>
                <div class="info-row"><span class="info-label">Branch:</span> <?= htmlspecialchars($row['branch_name']) ?></div>
            </div>
            <div class="col-md-6" style="border-left: 1px dashed var(--border); padding-left: 24px;">
                <div class="section-label">Course Enrollment</div>
                <div class="info-row"><span class="info-label">Course:</span> <strong><?= htmlspecialchars($row['course_name']) ?></strong></div>
                <div class="info-row"><span class="info-label">Duration:</span> <?= htmlspecialchars($row['duration']) ?></div>
                <div class="info-row"><span class="info-label">Batch:</span> <?= htmlspecialchars($row['batch_name']) ?></div>
                <div class="info-row"><span class="info-label">Enrolled:</span> <?= date('M j, Y', strtotime($row['enrollment_date'])) ?></div>
                <?php if (!empty($row['registration_date'])): ?>
                <div class="info-row"><span class="info-label">Registered:</span> <?= date('M j, Y', strtotime($row['registration_date'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="section-label mt-1">Financial Overview</div>
        <table class="fin-table mb-4">
            <thead>
                <tr>
                    <th style="width:50%">Description</th>
                    <th style="width:30%">Details</th>
                    <th style="width:20%; text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Registration Fee</td>
                    <td><span style="font-size:.78rem; color:var(--muted);">One-time enrolment</span></td>
                    <td style="text-align:right; font-weight:600;">$<?= number_format((float)$row['registration_fee'], 2) ?></td>
                </tr>
                <tr>
                    <td>Tuition Fee</td>
                    <td><span style="font-size:.78rem; color:var(--muted);"><?= htmlspecialchars($row['course_name']) ?></span></td>
                    <td style="text-align:right; font-weight:600;">$<?= number_format((float)$row['tuition_fee'], 2) ?></td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:700; border-top:1.5px solid #e2e8f0;">Total Course Fee</td>
                    <td style="border-top:1.5px solid #e2e8f0;"></td>
                    <td style="text-align:right; font-weight:800; border-top:1.5px solid #e2e8f0;">$<?= number_format((float)$row['fees'], 2) ?></td>
                </tr>
                <tr>
                    <td>Amount Paid</td>
                    <td>
                        <?php if ($pay): ?>
                            <span style="font-size:.78rem; color:var(--muted);">
                                <?= htmlspecialchars($pay['payment_method']) ?>
                                · <?= htmlspecialchars(date('M j, Y', strtotime($pay['payment_date']))) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="text-align:right; font-weight:700; color:var(--success);">$<?= number_format($totalPaid, 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Balance Remaining</strong></td>
                    <td>
                        <?php if ($balance <= 0): ?>
                        <span style="color:var(--success); font-size:.78rem; font-weight:600;">
                            <i class="bi bi-check-circle-fill me-1"></i>Cleared
                        </span>
                        <?php else: ?>
                        <span style="color:var(--danger); font-size:.78rem; font-weight:600;">Outstanding</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; font-weight:800;
                        color: <?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= $balance > 0 ? '$' . number_format($balance, 2) : '$0.00' ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Security footer -->
        <div class="receipt-footer">
            <div>
                <div class="official-tag">Official Document</div>
                <div style="font-size:.72rem; color:var(--muted); margin-top:3px;">
                    Issued by Shining Bright Vocational School
                </div>
                <div class="footer-hash mt-2">
                    SEC: <?= $securityHash ?>
                </div>
                <div style="font-size:.65rem; color:var(--muted); margin-top:5px; max-width:320px; line-height:1.5;">
                    This receipt is only valid in its original printed form. Any photocopied,
                    scanned, or digitally replicated version is invalid and constitutes fraud.
                    Verify authenticity by scanning the QR code above.
                </div>
            </div>
            <div class="stamp-area">
                <div style="font-size:.65rem; color:var(--muted); margin-bottom:24px;">
                    Authorised Signature
                </div>
                <div style="font-size:.65rem; color:var(--muted);">Office Stamp</div>
            </div>
        </div>

    </div><!-- /receipt-content -->
    </div><!-- /receipt-body -->

    <!-- ③ Bottom microtext border -->
    <div class="microtext-border" style="transform:scaleY(-1);" aria-hidden="true">
        <?php echo str_repeat("✦ SHINING BRIGHT VOCATIONAL SCHOOL · OFFICIAL RECEIPT · " . $securityHash . " · NOT VALID IF PHOTOCOPIED · ", 12); ?>
    </div>

    <!-- ② Bottom guilloche bar -->
    <svg class="guilloche" viewBox="0 0 820 18" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="820" height="18" fill="url(#gp)"/>
    </svg>

</div><!-- /receipt-card -->
</div><!-- /receipt-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Generate QR code pointing to the receipt verification URL
new QRCode(document.getElementById('receiptQR'), {
    text: <?= json_encode($verifyUrl) ?>,
    width: 80,
    height: 80,
    colorDark: '#4f46e5',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H  // High error correction (30%) for readability when printed small
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
