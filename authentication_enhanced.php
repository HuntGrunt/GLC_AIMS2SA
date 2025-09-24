<?php
// authentication_enhanced.php - Enhanced authentication handler with OTP
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/data/auth_enhanced.php';
require_once __DIR__ . '/data/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    $redirectUrl = Auth::getRedirectUrl($user['role_id']);
    header("Location: $redirectUrl");
    exit;
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /GLC_AIMS/login.php");
    exit;
}

// Rate limiting check
if (!Security::checkRateLimit('login', 5, 300)) {
    header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Too many login attempts. Please try again in 5 minutes."));
    exit;
}

// Verify CSRF token
$csrfToken = getPost('csrf_token');
if (!Security::verifyCSRFToken($csrfToken)) {
    Security::logSecurityEvent('csrf_token_mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Invalid security token. Please refresh the page and try again."));
    exit;
}

$action = getPost('action', 'login');

if ($action === 'login') {
    // Clear any previous OTP session when starting a new login attempt
    unset($_SESSION['otp_email']);
    unset($_SESSION['otp_pending']);
    unset($_SESSION['otp_expiry']);

    // Initial login attempt
    $username = getPost('username');
    $password = $_POST['password'] ?? ''; // Don't sanitize password

    // Validate input
    $errors = Security::validateInput([
        'username' => $username,
        'password' => $password
    ], [
        'username' => 'required|min:3|max:50',
        'password' => 'required|min:6'
    ]);

    if (!empty($errors)) {
        $errorMessage = "Please check your input and try again.";
        header("Location: /GLC_AIMS/login.php?msg=" . urlencode($errorMessage));
        exit;
    }

    // Attempt login initiation
    $result = EnhancedAuth::initiateLogin($username, $password);

    if ($result['success']) {
        // Redirect back to login.php to show OTP form
        header("Location: /GLC_AIMS/login.php");
        exit;
    } else {

        Security::logSecurityEvent('failed_login', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        header("Location: /GLC_AIMS/login.php?msg=" . urlencode($result['message']));
        exit;
    }
    
} else if ($action === 'verify_otp') {
    // OTP verification
    $email = getPost('email', '', 'email');
    $otp = getPost('otp');
    
    // Validate input
    if (empty($email) || empty($otp)) {
        header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Please enter the verification code."));
        exit;
    }
    
    // Attempt OTP verification and complete login
    $result = EnhancedAuth::completeLogin($email, $otp);
    
    if ($result['success']) {
        // Successful login - redirect to appropriate dashboard
        header("Location: " . $result['redirect']);
        exit;
    } else {
        // Failed OTP verification
        Security::logSecurityEvent('failed_otp_verification', [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        header("Location: /GLC_AIMS/login.php?msg=" . urlencode($result['message']));
        exit;
    }
}

// Invalid action
header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Invalid action."));
exit;