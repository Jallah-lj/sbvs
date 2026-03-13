<?php
class EmailService {
    private $senderEmail;
    private $senderName;
    private $headers;

    public function __construct() {
        $this->senderEmail = 'no-reply@sbvs.example.com'; 
        $this->senderName = 'Shining Bright Vocational School';
        
        // Setup standard HTML mail headers
        $this->headers  = "MIME-Version: 1.0\r\n";
        $this->headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $this->headers .= "From: {$this->senderName} <{$this->senderEmail}>\r\n";
        $this->headers .= "Reply-To: info@sbvs.example.com\r\n";
        $this->headers .= "X-Mailer: PHP/" . phpversion();
    }

    /**
     * Sends a welcome email to a newly registered student
     */
    public function sendWelcomeEmail($to, $studentName, $studentId, $courseName, $loginEmail, $loginPassword) {
        $subject = "Welcome to Shining Bright Vocational School!";
        
        $html = $this->getHtmlWrapper("
            <h2 style='color: #0f172a; margin-top: 0;'>Welcome, {$studentName}!</h2>
            <p>Congratulations on your enrollment! We are absolutely thrilled to welcome you to <strong>Shining Bright Vocational School</strong>.</p>
            <p>You have successfully been registered for the following course:</p>
            <div class='info-box'>
                <strong>Course:</strong> {$courseName}<br>
                <strong>Student ID:</strong> {$studentId}
            </div>
            <p>You can use the details below to log into the student portal in the future:</p>
            <div class='info-box'>
                <strong>Email:</strong> {$loginEmail}<br>
                <strong>Password:</strong> {$loginPassword} <em>(This is your Date of Birth: YYYY-MM-DD)</em>
            </div>
            <p>We recommend changing your password after your first login.</p>
            <br>
            <p>If you have any questions, please reply to this email or visit our front desk.</p>
        ");

        return $this->send($to, $subject, $html);
    }

    /**
     * Sends a payment receipt email
     */
    public function sendPaymentReceipt($to, $studentName, $receiptNo, $amountPaid, $method, $balance, $courseName) {
        $subject = "Payment Receipt #{$receiptNo}";
        
        $html = $this->getHtmlWrapper("
            <h2 style='color: #0f172a; margin-top: 0;'>Payment Receipt</h2>
            <p>Dear {$studentName},</p>
            <p>We have successfully received your recent payment. Thank you!</p>
            
            <table class='receipt-table' width='100%' cellpadding='0' cellspacing='0'>
                <tr>
                    <th>Receipt Number</th>
                    <td>{$receiptNo}</td>
                </tr>
                <tr>
                    <th>Course</th>
                    <td>{$courseName}</td>
                </tr>
                <tr>
                    <th>Payment Method</th>
                    <td>{$method}</td>
                </tr>
                <tr>
                    <th>Amount Paid</th>
                    <td style='color: #10b981; font-weight: bold;'>$" . number_format($amountPaid, 2) . "</td>
                </tr>
                <tr>
                    <th>Remaining Balance</th>
                    <td style='font-weight: bold; color: " . ($balance <= 0 ? '#10b981' : '#ef4444') . ";'>
                        $" . number_format($balance, 2) . "
                    </td>
                </tr>
            </table>
            
            <p style='margin-top: 25px;'>You can always view your full payment history in your student portal.</p>
        ");

        return $this->send($to, $subject, $html);
    }

    /**
     * Internal generic send wrapper.
     */
    private function send($to, $subject, $html) {
        // We use @ to suppress warnings in case mail() isn't configured in XAMPP
        // In a real environment, this would safely route.
        try {
            @mail($to, $subject, $html, $this->headers);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Provides a beautiful wrapper template for all outgoing emails
     */
    private function getHtmlWrapper($content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
                .email-wrapper { width: 100%; background-color: #f8fafc; padding: 40px 0; }
                .email-content { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                .email-header { background: linear-gradient(135deg, #4f46e5, #8b5cf6); padding: 30px; text-align: center; color: #ffffff; }
                .email-header h1 { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 0.5px; }
                .email-body { padding: 40px 30px; color: #334155; line-height: 1.6; font-size: 16px; }
                .email-footer { background-color: #f1f5f9; padding: 20px; text-align: center; color: #64748b; font-size: 13px; }
                
                .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin: 20px 0; }
                
                .receipt-table { margin: 20px 0; border-collapse: collapse; }
                .receipt-table th, .receipt-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                .receipt-table th { background-color: #f8fafc; color: #64748b; font-size: 13px; text-transform: uppercase; width: 40%; }
                .receipt-table td { color: #0f172a; }
                
                .button { display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='email-content'>
                    <div class='email-header'>
                        <h1>Shining Bright</h1>
                        <div style='font-size: 12px; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8;'>Vocational School</div>
                    </div>
                    <div class='email-body'>
                        {$content}
                    </div>
                    <div class='email-footer'>
                        &copy; " . date('Y') . " Shining Bright Vocational School. All rights reserved.<br>
                        This is an automated message, please do not reply.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
