<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../database.php';

// Try to use existing internal mail service, else fallback to standard mail wrapper.
// Actually let's include EmailService directly to use its wrapper.
require_once '../EmailService.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $_SESSION['reset_error'] = "Please provide your email address.";
        header("Location: views/forgot_password.php");
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Check if user exists
    $stmt = $db->prepare("SELECT id, name FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate Token
        $token = bin2hex(random_bytes(50));
        // Token valid for 1 hour
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Update User records
        $updateStmt = $db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
        $updateStmt->bindParam(':token', $token);
        $updateStmt->bindParam(':expires', $expires);
        $updateStmt->bindParam(':id', $user['id']);
        
        if ($updateStmt->execute()) {
            // Build reset link (dynamic protocol & host)
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            
            // Adjust the base path according to the web root
            // BASE_URL is typically defined in config.php, let's try to use it if available
            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : $protocol . $host . '/sbvs';
            
            $resetLink = $base . "/config/controllers/views/reset_password.php?token=" . urlencode($token);

            // Send Email using EmailService logic
            $emailService = new EmailService();
            // Since EmailService might not have a generic method, we can adapt the inner template
            $subject = "Password Reset Request - SBVS Portal";
            $message = "
                <h2 style='color: #0f172a; margin-top: 0;'>Password Reset Request</h2>
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>We received a request to reset the password for your administrative account. If you made this request, please click the button below to set a new password.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='display: inline-block; background-color: #4338ca; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </div>
                <p><strong>Note:</strong> This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, you can safely ignore this email. Your current password will remain unchanged.</p>
            ";

            // Add public wrapping method to avoid overriding private methods in EmailService
            // Actually, wait, let's just use standard mail() with HTML headers here, as EmailService might have private wrapper. Let me define local mail logic safely for fallback.
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: SBVS Administrator <no-reply@sbvs.edu>\r\n";

            $htmlEmail = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Inter', system-ui, sans-serif; background-color: #f8fafc; padding: 20px; }
                    .box { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                </style>
            </head>
            <body>
                <div class='box'>
                    {$message}
                </div>
            </body>
            </html>
            ";

            @mail($email, $subject, $htmlEmail, $headers);
        }
    }

    // Always show success message to prevent user enumeration attacks
    $_SESSION['reset_success'] = "If an account matches that email, a recovery link has been sent.";
    header("Location: views/forgot_password.php");
    exit;
}
