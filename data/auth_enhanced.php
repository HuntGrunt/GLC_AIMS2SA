<?php
// data/auth_enhanced.php
require_once __DIR__ . '/auth.php';  
require_once __DIR__ . '/../vendor/autoload.php'; 
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/otp_service.php';

class EnhancedAuth extends Auth {
    
    // Step 1: Validate username/password and send OTP
    public static function initiateLogin($username, $password) {
        // Check for too many failed attempts
        if (self::isLockedOut($username)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again in 5 minutes.'];
        }
        
        // Get user from database
        $user = fetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        
        if (!$user) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Check password
        $passwordValid = false;
        if (strlen($user['password']) > 50) {
            $passwordValid = password_verify($password, $user['password']);
        } else {
            $passwordValid = ($password === $user['password']);
        }
        
        if (!$passwordValid) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Check if user has email
        if (empty($user['email'])) {
            return ['success' => false, 'message' => 'No email address found for this account. Please contact administrator.'];
        }
        
        // Create and send OTP
        $otpResult = OTPService::createOTP($user['id'], $user['email'], 'login');
        
        if ($otpResult['success']) {
            // Store user info in session temporarily (not fully logged in yet)
            $_SESSION['pending_login'] = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role_id' => $user['role_id'], // Add role_id for redirect
                'timestamp' => time()
            ];
            
            return [
                'success' => true, 
                'message' => 'OTP sent to your email address. Please check your email and enter the verification code.',
                'requires_otp' => true
            ];
        } else {
            return $otpResult;
        }
    }
    
    // Step 2: Verify OTP and complete login
    public static function completeLogin($email, $otp) {
        // FIXED: More flexible session checking
        if (!isset($_SESSION['pending_login'])) {
            return ['success' => false, 'message' => 'Invalid login session. Please start over.'];
        }
        
        $pendingLogin = $_SESSION['pending_login'];
        
        // Check if email matches
        if ($pendingLogin['email'] !== $email) {
            return ['success' => false, 'message' => 'Email mismatch. Please start over.'];
        }
        
        // Check session timeout (10 minutes)
        if (time() - $pendingLogin['timestamp'] > 600) {
            unset($_SESSION['pending_login']);
            return ['success' => false, 'message' => 'Login session expired. Please start over.'];
        }
        
        // Verify OTP
        $otpResult = OTPService::verifyOTP($email, $otp, 'login');
        
        if ($otpResult['success']) {
            $userId = $pendingLogin['user_id'];
            
            // Get fresh user data
            $user = fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
            
            if ($user) {
                // Clear pending login and failed attempts
                unset($_SESSION['pending_login']);
                self::clearFailedAttempts($user['username']);
                
                // Invalidate other OTPs for this user
                OTPService::invalidateUserOTPs($userId, 'login');
                
                // Complete login process (similar to original login)
                $sessionToken = bin2hex(random_bytes(32));
                $sessionExpires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
                
                executeUpdate(
                    "UPDATE users SET session_token = ?, session_expires = ?, last_login = NOW() WHERE id = ?",
                    [$sessionToken, $sessionExpires, $user['id']]
                );
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_name'] = self::getRoleName($user['role_id']);
                $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                $_SESSION['session_token'] = $sessionToken;
                $_SESSION['last_activity'] = time();
                
                // Log successful login
                ActivityLogger::log($user['id'], 'LOGIN_WITH_OTP', 'users', $user['id']);
                
                return ['success' => true, 'redirect' => self::getRedirectUrl($user['role_id'])];
            } else {
                unset($_SESSION['pending_login']);
                return ['success' => false, 'message' => 'User account not found or inactive.'];
            }
        } else {
            return $otpResult;
        }
    }
    
    // NEW: Handle OTP resend
    public static function resendOTP($email) {
        if (!isset($_SESSION['pending_login']) || $_SESSION['pending_login']['email'] !== $email) {
            return ['success' => false, 'message' => 'Invalid session. Please start login process again.'];
        }
        
        $pendingLogin = $_SESSION['pending_login'];
        $userId = $pendingLogin['user_id'];
        
        // Create new OTP
        $otpResult = OTPService::createOTP($userId, $email, 'login');
        
        if ($otpResult['success']) {
            // Update timestamp
            $_SESSION['pending_login']['timestamp'] = time();
            return ['success' => true, 'message' => 'New OTP sent to your email address.'];
        } else {
            return $otpResult;
        }
    }
    
    // NEW: Clear pending login session
    public static function clearPendingLogin() {
        unset($_SESSION['pending_login']);
        return ['success' => true, 'message' => 'Session cleared.'];
    }
    
    // Password reset initiation
    public static function initiatePasswordReset($email) {
        $user = fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        
        if (!$user) {
            // Don't reveal if email exists or not
            return ['success' => true, 'message' => 'If the email exists in our system, you will receive a password reset code.'];
        }
        
        $otpResult = OTPService::createOTP($user['id'], $user['email'], 'password_reset');
        
        if ($otpResult['success']) {
            return ['success' => true, 'message' => 'Password reset code sent to your email address.'];
        } else {
            return $otpResult;
        }
    }
    
    // Verify OTP and date of birth for password reset
    public static function verifyPasswordResetOTP($email, $otp, $dateOfBirth) {
        $otpResult = OTPService::verifyOTP($email, $otp, 'password_reset');
        
        if (!$otpResult['success']) {
            return $otpResult;
        }
        
        // Verify date of birth
        $user = fetchOne("SELECT * FROM users WHERE id = ? AND email = ? AND is_active = 1", [$otpResult['user_id'], $email]);
        
        if (!$user || !$user['date_of_birth'] || $user['date_of_birth'] !== $dateOfBirth) {
            return ['success' => false, 'message' => 'Date of birth does not match our records.'];
        }
        
        // Generate password reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
        
        executeUpdate(
            "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?",
            [$resetToken, $resetExpires, $user['id']]
        );
        
        return [
            'success' => true, 
            'message' => 'Verification successful. You can now reset your password.',
            'reset_token' => $resetToken,
            'user_id' => $user['id']
        ];
    }
    
    // Complete password reset
    public static function completePasswordReset($resetToken, $newPassword) {
        $user = fetchOne(
            "SELECT * FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND is_active = 1",
            [$resetToken]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Update password and clear reset token
        executeUpdate(
            "UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?",
            [$hashedPassword, $user['id']]
        );
        
        // Invalidate all OTPs for this user
        OTPService::invalidateUserOTPs($user['id']);
        
        // Log password reset
        ActivityLogger::log($user['id'], 'PASSWORD_RESET', 'users', $user['id']);
        
        return ['success' => true, 'message' => 'Password reset successfully. You can now login with your new password.'];
    }
    
    // Debug function to check pending login state
    public static function debugPendingLogin() {
        if (APP_ENV !== 'development') {
            return null;
        }
        
        return $_SESSION['pending_login'] ?? null;
    }
}