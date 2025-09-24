<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_config.php';

class OTPService {
    private static $emailService = null;
    
    private static function getEmailService() {
        if (self::$emailService === null) {
            self::$emailService = new EmailService();
        }
        return self::$emailService;
    }
    
    public static function generateOTP() {
        return sprintf("%06d", mt_rand(100000, 999999));
    }
    
    public static function createOTP($userId, $email, $type = 'login') {
        try {
            // Clean up expired OTPs first
            self::cleanupExpiredOTPs();
            
            // Check for recent OTP (rate limiting)
            $recentOTP = fetchOne(
                "SELECT id FROM otp_verifications WHERE email = ? AND otp_type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                [$email, $type]
            );
            
            if ($recentOTP) {
                return ['success' => false, 'message' => 'Please wait 1 minute before requesting another OTP.'];
            }
            
            // Generate new OTP
            $otp = self::generateOTP();
            $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes from now
            
            // Store OTP in database
            $result = executeUpdate(
                "INSERT INTO otp_verifications (user_id, email, otp_code, otp_type, expires_at) VALUES (?, ?, ?, ?, ?)",
                [$userId, $email, $otp, $type, $expiresAt]
            );
            
            if ($result) {
                // Get user name for email
                $user = fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$userId]);
                $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                if (empty($userName)) $userName = 'User';
                
                // Send OTP via email
                $emailResult = self::getEmailService()->sendOTP($email, $userName, $otp, $type);
                
                if ($emailResult['success']) {
                    return ['success' => true, 'message' => 'OTP sent to your email address.', 'otp_id' => getLastInsertId()];
                } else {
                    // Remove OTP from database if email failed
                    executeUpdate("DELETE FROM otp_verifications WHERE user_id = ? AND email = ? AND otp_code = ?", [$userId, $email, $otp]);
                    return ['success' => false, 'message' => 'Failed to send OTP email. Please try again.'];
                }
            } else {
                return ['success' => false, 'message' => 'Failed to generate OTP. Please try again.'];
            }
        } catch (Exception $e) {
            error_log("OTP creation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    public static function verifyOTP($email, $otp, $type = 'login') {
        try {
            // Get OTP record
            $otpRecord = fetchOne(
                "SELECT id, user_id, attempts, is_verified FROM otp_verifications 
                 WHERE email = ? AND otp_code = ? AND otp_type = ? AND expires_at > NOW() AND is_verified = FALSE 
                 ORDER BY created_at DESC LIMIT 1",
                [$email, $otp, $type]
            );
            
            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid or expired OTP.'];
            }
            
            // Check attempt limit
            if ($otpRecord['attempts'] >= 3) {
                return ['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.'];
            }
            
            // Mark as verified and increment attempts
            executeUpdate(
                "UPDATE otp_verifications SET is_verified = TRUE, attempts = attempts + 1 WHERE id = ?",
                [$otpRecord['id']]
            );
            
            return ['success' => true, 'message' => 'OTP verified successfully.', 'user_id' => $otpRecord['user_id']];
            
        } catch (Exception $e) {
            error_log("OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    public static function invalidateUserOTPs($userId, $type = null) {
        try {
            $query = "UPDATE otp_verifications SET is_verified = TRUE WHERE user_id = ?";
            $params = [$userId];
            
            if ($type) {
                $query .= " AND otp_type = ?";
                $params[] = $type;
            }
            
            executeUpdate($query, $params);
        } catch (Exception $e) {
            error_log("OTP invalidation failed: " . $e->getMessage());
        }
    }
    
    public static function cleanupExpiredOTPs() {
        try {
            executeUpdate("DELETE FROM otp_verifications WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        } catch (Exception $e) {
            error_log("OTP cleanup failed: " . $e->getMessage());
        }
    }
}