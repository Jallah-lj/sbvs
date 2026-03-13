<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Unauthorized access.");
}

require_once '../../database.php';
$db = (new Database())->getConnection();

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!$type || !$id) {
    die("Invalid parameters. Missing type or id.");
}

$photo = '../../assets/img/default-avatar.png'; // default
$title = '';
$name = '';
$idNumber = '';
$roleText = '';
$branchName = '';
$phone = '';
$email = '';
$validUntil = date('Y') + 1;

if ($type === 'student') {
    $stmt = $db->prepare("
        SELECT s.student_id, u.name, u.email, s.phone, s.photo_url, b.name as branch_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN branches b ON s.branch_id = b.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die("Student not found.");
    
    $name = $data['name'];
    $idNumber = $data['student_id'];
    $roleText = 'STUDENT';
    $branchName = $data['branch_name'] ?? 'Global';
    $phone = $data['phone'] ?: 'N/A';
    $email = $data['email'] ?: 'N/A';
    if ($data['photo_url']) $photo = '../../../' . $data['photo_url'];
} elseif ($type === 'teacher' || $type === 'staff') {
    $stmt = $db->prepare("
        SELECT u.name, u.email, u.role, t.phone, t.specialization, b.name as branch_name 
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN branches b ON t.branch_id = b.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die("Staff/Instructor not found.");
    
    $name = $data['name'];
    $idNumber = 'EMP-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $roleText = strtoupper($data['role'] ?: 'INSTRUCTOR');
    $branchName = $data['branch_name'] ?? 'Global';
    $phone = $data['phone'] ?: 'N/A';
    $email = $data['email'] ?: 'N/A';
    // Handle specific photo column if it exists in the future, for now fallback to default
    if (isset($data['photo_url']) && $data['photo_url']) $photo = '../../../' . $data['photo_url'];
} else {
    die("Invalid type.");
}

// Ensure photo path is clean. If not starting with assets or uploads, it might not render properly.
if (!file_exists($photo) && $photo !== '../../assets/img/default-avatar.png') {
    $photo = 'https://via.placeholder.com/150?text=Photo';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card - <?= htmlspecialchars($name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $type === 'student' ? '#4f46e5' : '#0ea5e9' ?>;
            --secondary-color: <?= $type === 'student' ? '#8b5cf6' : '#0284c7' ?>;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 30px;
            min-height: 100vh;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card-wrapper {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .id-card {
            width: 330px;
            height: 520px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }

        /* Guilloche / Banknote Pattern Background */
        .id-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, rgba(79, 70, 229, 0.03) 0, rgba(79, 70, 229, 0.03) 1px, transparent 1px, transparent 10px),
                repeating-linear-gradient(-45deg, rgba(79, 70, 229, 0.03) 0, rgba(79, 70, 229, 0.03) 1px, transparent 1px, transparent 10px),
                radial-gradient(circle at 50% 50%, rgba(255,255,255,0.8) 20%, transparent 80%),
                radial-gradient(circle at center, transparent 30%, rgba(0, 0, 0, 0.02) 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* Ghost Profile Watermark */
        .ghost-profile {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 220px;
            height: 220px;
            border-radius: 50%;
            opacity: 0.06;
            background-size: cover;
            background-position: center;
            filter: grayscale(100%) contrast(200%);
            z-index: 1;
            mix-blend-mode: multiply;
            pointer-events: none;
        }

        /* Front Card Details */
        .card-header-bg {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            height: 120px;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
            overflow: hidden;
            border-bottom: 4px solid #f59e0b;
        }

        .card-header-bg::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .card-header-bg::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
        }

        .card-content {
            position: relative;
            z-index: 4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 20px 0 20px;
            flex-grow: 1;
        }

        .school-info {
            text-align: center;
            color: white;
            margin-bottom: 10px;
            width: 100%;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .school-logo-icon {
            font-size: 1.8rem;
            color: #f59e0b;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .school-text {
            text-align: left;
        }

        .school-name {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.1;
        }

        .school-sub {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.95;
        }

        .profile-wrapper {
            position: relative;
            margin-top: 5px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-img-container {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 4px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 10;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            background-color: #ffffff;
        }

        .role-badge {
            position: absolute;
            bottom: -10px;
            background: var(--primary-color);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            border: 2px solid white;
            z-index: 11;
        }

        .user-info {
            text-align: center;
            margin-bottom: 15px;
            width: 100%;
        }

        .user-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .details-grid {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 15px;
            padding: 12px 15px;
            background: rgba(255,255,255,0.7);
            border-radius: 12px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: inset 0 0 10px rgba(255,255,255,0.5), 0 2px 10px rgba(0,0,0,0.02);
            z-index: 5;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .detail-label {
            font-size: 0.6rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.8rem;
            color: #0f172a;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .security-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            margin-top: auto;
            margin-bottom: 5px;
            z-index: 5;
        }

        .qr-placeholder {
            width: 50px;
            height: 50px;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 3px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .qr-placeholder img {
            width: 100%;
            height: 100%;
            border-radius: 4px;
        }

        .barcode-container {
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .barcode {
            font-family: 'Libre Barcode 39 Text', cursive;
            font-size: 28px;
            color: #0f172a;
            line-height: 1;
            transform: scaleY(0.7);
            margin-top: -5px;
        }

        /* Microprint Line */
        .micro-print {
            font-size: 3.5px;
            line-height: 1;
            letter-spacing: 0.5px;
            color: #64748b;
            text-transform: uppercase;
            overflow: hidden;
            white-space: nowrap;
            width: 100%;
            text-align: center;
            opacity: 0.7;
            margin-top: 8px;
            z-index: 5;
        }

        /* Hologram Foil Seal */
        .hologram-seal {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.9), transparent),
                        conic-gradient(#f9d423, #ff4e50, #8A2387, #E94057, #f27121, #f9d423);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2), inset 0 0 8px rgba(255,255,255,0.8);
            border: 1px dashed rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            animation: foil-shift 4s infinite alternate;
        }

        .hologram-seal span {
            font-size: 6px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .hologram-seal i {
            font-size: 14px;
            margin-bottom: 1px;
        }

        @keyframes foil-shift {
            0% { filter: hue-rotate(0deg) brightness(1); }
            100% { filter: hue-rotate(45deg) brightness(1.2); }
        }

        .card-footer {
            background: #0f172a;
            color: #f8fafc;
            padding: 8px;
            text-align: center;
            font-size: 0.65rem;
            font-weight: 600;
            margin-top: auto;
            border-radius: 0 0 16px 16px;
            z-index: 5;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-top: 2px solid var(--primary-color);
        }
        /* Back Card Details */
        .card-back {
            background: #ffffff;
            padding: 25px 20px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* 'VOID' / 'DO NOT COPY' Pantograph effect */
        .pantograph {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 4rem;
            font-weight: 900;
            color: rgba(0,0,0,0.02);
            white-space: nowrap;
            letter-spacing: 5px;
            z-index: 1;
            pointer-events: none;
            text-shadow: 1px 1px 1px rgba(255,255,255,0.5);
        }

        .back-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            position: relative;
            z-index: 5;
        }

        .back-header h5 {
            font-weight: 800;
            color: #0f172a;
            font-size: 1.1rem;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .rules-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.68rem;
            color: #334155;
            line-height: 1.6;
            position: relative;
            z-index: 5;
            font-weight: 600;
        }

        .rules-list li {
            margin-bottom: 8px;
            padding-left: 18px;
            position: relative;
        }

        .rules-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-size: 0.8rem;
            font-weight: 900;
        }

        .signature-block {
            margin-top: auto;
            text-align: center;
            position: relative;
            z-index: 5;
        }

        .signature-line {
            width: 70%;
            border-top: 1.5px solid #64748b;
            margin: 40px auto 5px;
        }

        .signature-text {
            font-size: 0.7rem;
            color: #475569;
            font-weight: 800;
            text-transform: uppercase;
        }

        .print-btn {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            border: none;
            padding: 12px 35px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            background: linear-gradient(135deg, #1e293b, #0f172a);
        }

        @media print {
            body { 
                background: white !important; 
                padding: 0; 
                margin: 0; 
            }
            .no-print { display: none !important; }
            .id-card-wrapper { gap: 10mm; }
            .id-card {
                box-shadow: none !important;
                border: 1px solid #e2e8f0;
                page-break-inside: avoid;
            }
            /* Darken elements for better print contrast */
            .pantograph { color: rgba(0,0,0,0.04) !important; }
            .hologram-seal {
                /* Printers struggle with bright gradients, convert to a darker seal */
                background: #ccc !important; 
                border: 2px solid #666 !important;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-btn no-print">
        <i class="bi bi-printer-fill"></i> Print Secure ID Card
    </button>

    <div class="id-card-wrapper">
        <!-- FRONT OF CARD -->
        <div class="id-card">
            <!-- Ghost Profile Watermark -->
            <div class="ghost-profile" style="background-image: url('<?= $photo ?>');"></div>
            
            <div class="card-header-bg"></div>
            
            <div class="card-content">
                <div class="school-info">
                    <i class="bi bi-shield-shaded school-logo-icon"></i>
                    <div class="school-text">
                        <h2 class="school-name">SHINING BRIGHT</h2>
                        <div class="school-sub">Vocational School</div>
                    </div>
                </div>
                
                <div class="profile-wrapper">
                    <div class="profile-img-container">
                        <img src="<?= $photo ?>" alt="Profile" class="profile-img">
                    </div>
                    <div class="role-badge"><?= $roleText ?></div>
                </div>
                
                <div class="user-info">
                    <h3 class="user-name"><?= htmlspecialchars($name) ?></h3>
                </div>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">ID Number</span>
                        <span class="detail-value"><?= htmlspecialchars($idNumber) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Valid Thru</span>
                        <span class="detail-value">DEC <?= $validUntil ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Branch</span>
                        <span class="detail-value"><?= htmlspecialchars($branchName) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact</span>
                        <span class="detail-value"><?= htmlspecialchars($phone) ?></span>
                    </div>
                </div>

                <div class="security-footer">
                    <div class="qr-placeholder">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($idNumber) ?>" alt="QR Code">
                    </div>
                    
                    <div class="barcode-container">
                        <div class="barcode">*<?= htmlspecialchars($idNumber) ?>*</div>
                    </div>
                    
                    <div class="hologram-seal">
                        <i class="bi bi-shield-check"></i>
                        <span>Valid</span>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="padding: 6px;">
                <div class="micro-print" style="margin: 0; color: #cbd5e1; opacity: 0.5;">
                    <?= str_repeat('SHININGBRIGHTVOCATIONALSCHOOLSECUREID', 4) ?>
                </div>
            </div>
        </div>
        <!-- BACK OF CARD -->
        <div class="id-card card-back">
            <!-- Anti-copy Pantograph Text -->
            <div class="pantograph">DO NOT COPY</div>
            <div class="pantograph" style="top: 20%; left: 20%; font-size: 2rem;">SECURE</div>
            <div class="pantograph" style="top: 80%; left: 80%; font-size: 2rem;">VALID</div>
            
            <!-- Microprint border equivalent -->
            <div class="micro-print" style="position: absolute; top: 5px; left: 0; opacity: 0.4;">
                <?= str_repeat('PROPERTYOFSBVSIDDOCUMENT', 8) ?>
            </div>

            <div>
                <div class="back-header">
                    <h5>Important Information</h5>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 700;">Return If Found</div>
                </div>
                
                <ul class="rules-list">
                    <li>This card remains the property of Shining Bright Vocational School (SBVS).</li>
                    <li>Must be carried at all times on school premises and presented upon request.</li>
                    <li>Non-transferable. Alteration, forgery, or unauthorized use is strictly prohibited.</li>
                    <li>If found, please return to any SBVS branch or hand to campus security.</li>
                    <li>Contact: info@sbvs.edu | Phone: +1 800 555 1234</li>
                </ul>
            </div>

            <!-- Return Microprint footer equivalent -->
            <div class="micro-print" style="position: absolute; bottom: 5px; left: 0; opacity: 0.4;">
                <?= str_repeat('AUTHORIZEDSIGNATUREREQUIRED', 8) ?>
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-text">Authorized Signature</div>
            </div>
        </div>
    </div>

</body>
</html>
