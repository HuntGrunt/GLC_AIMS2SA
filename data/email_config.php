<?php
// data/email_config.php
// Updated email configuration using environment variables

require_once __DIR__ . '/config.php';

// PHPMailer configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $isConfigured = false;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Validate that email credentials are configured
            if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
                throw new Exception('Email credentials not configured in environment variables');
            }
            
            // SMTP Configuration - now from environment variables
            $this->mailer->isSMTP();
            $this->mailer->Host       = SMTP_HOST;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = SMTP_USERNAME;
            $this->mailer->Password   = SMTP_PASSWORD;
            $this->mailer->Port       = SMTP_PORT;
            
            // Set encryption type
            if (SMTP_ENCRYPTION === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Set from address using environment variables
            $this->mailer->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            
            // Enable debug output in development
            if (APP_ENV === 'development' && APP_DEBUG) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mailer->Debugoutput = 'error_log';
            }
            
            // Additional SMTP options for better compatibility
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $this->isConfigured = true;
            
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
            $this->isConfigured = false;
            
            // In development, show detailed error
            if (APP_ENV === 'development') {
                throw $e;
            }
        }
    }
    
    /**
     * Send OTP email to user
     * @param string $email Recipient email address
     * @param string $name Recipient name
     * @param string $otp OTP code
     * @param string $type Type of OTP (login, password_reset)
     * @return array Result array with success status and message
     */
    public function sendOTP($email, $name, $otp, $type = 'login') {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Email service not configured'];
        }
        
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipient
            $this->mailer->addAddress($email, $name);
            
            // Set subject based on OTP type
            $subject = ($type === 'login') ? 'Login Verification Code' : 'Password Reset Code';
            $this->mailer->Subject = $subject . ' - ' . APP_NAME;
            
            // Generate email body
            $body = $this->generateOTPEmailBody($name, $otp, $type);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            // Generate plain text alternative
            $this->mailer->AltBody = $this->generateOTPTextBody($name, $otp, $type);
            
            // Send email
            $result = $this->mailer->send();
            
            if ($result) {
                // Log successful email sending (without exposing the OTP)
                if (ENABLE_ACTIVITY_LOGGING) {
                    error_log("OTP email sent successfully to: " . $email . " (Type: $type)");
                }
                
                return ['success' => true, 'message' => 'OTP sent successfully'];
            } else {
                error_log("PHPMailer reported failure but no exception thrown");
                return ['success' => false, 'message' => 'Failed to send OTP email'];
            }
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            
            // Don't expose technical details to users in production
            if (APP_ENV === 'development') {
                return ['success' => false, 'message' => 'Email error: ' . $e->getMessage()];
            } else {
                return ['success' => false, 'message' => 'Failed to send OTP email. Please try again.'];
            }
        }
    }
    
    /**
     * Send general notification email
     * @param string $email Recipient email
     * @param string $name Recipient name  
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $altBody Plain text alternative
     * @return array Result array
     */
    public function sendNotification($email, $name, $subject, $body, $altBody = '') {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Email service not configured'];
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = $subject . ' - ' . APP_NAME;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            
            $result = $this->mailer->send();
            
            if ($result) {
                if (ENABLE_ACTIVITY_LOGGING) {
                    error_log("Notification email sent to: " . $email);
                }
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email'];
            }
            
        } catch (Exception $e) {
            error_log("Notification email failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email'];
        }
    }
    
    /**
     * Generate HTML email body for OTP
     */
    private function generateOTPEmailBody($name, $otp, $type) {
        $purpose = ($type === 'login') ? 'complete your login' : 'reset your password';
        $action = ($type === 'login') ? 'login verification' : 'password reset';
        $expiryMinutes = floor(OTP_EXPIRY_TIME / 60);
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>OTP Verification - " . APP_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .otp-code { 
                    font-size: 36px; 
                    font-weight: bold; 
                    color: #1e3a8a; 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 8px; 
                    letter-spacing: 8px; 
                    margin: 20px 0; 
                    border: 2px dashed #1e3a8a;
                }
                .warning { 
                    background: #fef3c7; 
                    border-left: 4px solid #fbbf24; 
                    padding: 15px; 
                    margin: 20px 0; 
                    border-radius: 4px;
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666; 
                    border-top: 1px solid #e5e7eb;
                }
                .security-tips {
                    background: #ecfdf5;
                    border: 1px solid #10b981;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                }
                .security-tips h3 { color: #10b981; margin-top: 0; }
                .security-tips ul { margin: 0; padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . APP_NAME . "</h1>
                    <p>OTP Verification</p>
                </div>
                
                <div class='content'>
                    <h2>Hello, {$name}!</h2>
                    <p>You have requested a verification code to {$purpose}. Please use the following One-Time Password (OTP):</p>
                    
                    <div class='otp-code'>{$otp}</div>
                    
                    <p><strong>This code will expire in {$expiryMinutes} minutes.</strong></p>
                    
                    <div class='warning'>
                        <strong>Security Notice:</strong> If you did not request this {$action}, please ignore this email and contact the system administrator immediately.
                    </div>
                    
                    <div class='security-tips'>
                        <h3>For your security:</h3>
                        <ul>
                            <li>Do not share this code with anyone</li>
                            <li>This code is valid for only {$expiryMinutes} minutes</li>
                            <li>Use this code only on the official " . APP_NAME . " website</li>
                            <li>Our support team will never ask for your OTP code</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions or concerns, please contact our support team.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>" . APP_NAME . "</strong><br>
                    Golden Link College Foundation Inc.<br>
                    This is an automated message, please do not reply.<br>
                    <small>Sent at: " . date('Y-m-d H:i:s T') . "</small></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email body for OTP
     */
    private function generateOTPTextBody($name, $otp, $type) {
        $purpose = ($type === 'login') ? 'complete your login' : 'reset your password';
        $action = ($type === 'login') ? 'login verification' : 'password reset';
        $expiryMinutes = floor(OTP_EXPIRY_TIME / 60);
        
            return "
            " . APP_NAME . " - OTP Verification

            Hello, {$name}!

            You have requested a verification code to {$purpose}.

            Your OTP Code: {$otp}

            This code will expire in {$expiryMinutes} minutes.

            SECURITY NOTICE: If you did not request this {$action}, please ignore this email and contact the system administrator immediately.

            For your security:
            - Do not share this code with anyone
            - This code is valid for only {$expiryMinutes} minutes  
            - Use this code only on the official website
            - Our support team will never ask for your OTP code

            " . APP_NAME . "
            Golden Link College Foundation Inc.
            This is an automated message, please do not reply.
        ";
    }
    
    /**
     * Test email configuration
     * @return array Result array
     */
    public function testConnection() {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Email service not configured'];
        }
        
        try {
            // Test SMTP connection without sending
            $this->mailer->SMTPDebug = 0; // Disable debug for test
            $result = $this->mailer->smtpConnect();
            
            if ($result) {
                $this->mailer->smtpClose();
                return ['success' => true, 'message' => 'SMTP connection successful'];
            } else {
                return ['success' => false, 'message' => 'SMTP connection failed'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
}
?>