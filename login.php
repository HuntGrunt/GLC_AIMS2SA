<?php
// login.php - Enhanced login page with OTP support and floating alerts
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/data/auth_enhanced.php';
require_once __DIR__ . '/data/security.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    $redirectUrl = Auth::getRedirectUrl($user['role_id']);
    header("Location: $redirectUrl");
    exit;
}

$message = '';
$messageType = 'error';
$showOTPForm = false;
$userEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = getPost('csrf_token');
    if (!Security::verifyCSRFToken($csrfToken)) {
        $message = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $action = getPost('action', 'login');
        
        if ($action === 'login') {
            // Initial login attempt
            $username = getPost('username');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $message = 'Please enter both username and password.';
            } else {
                $result = EnhancedAuth::initiateLogin($username, $password);
                
                if ($result['success'] && isset($result['requires_otp'])) {
                    $showOTPForm = true;
                    $userEmail = $_SESSION['pending_login']['email'] ?? '';
                    $message = $result['message'];
                    $messageType = 'success';
                } else if ($result['success']) {
                    header("Location: " . $result['redirect']);
                    exit;
                } else {
                    // failed login - clear old OTP state
                    unset($_SESSION['pending_login']);
                    $message = $result['message'];
                }
            }
        } else if ($action === 'verify_otp') {
            // OTP verification
            $email = getPost('email', '', 'email');
            $otp = getPost('otp');
            
            if (empty($email) || empty($otp)) {
                $message = 'Please enter the verification code.';
                $showOTPForm = true;
                $userEmail = $email;
            } else {
                $result = EnhancedAuth::completeLogin($email, $otp);
                
                if ($result['success']) {
                    header("Location: " . $result['redirect']);
                    exit;
                } else {
                    $message = $result['message'];
                    $showOTPForm = true;
                    $userEmail = $email;
                }
            }
        } else if ($action === 'resend_otp') {
            // Resend OTP
            $email = getPost('email', '', 'email');
            
            if (empty($email)) {
                $message = 'Email address is required.';
            } else {
                $result = EnhancedAuth::resendOTP($email);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                
                if ($result['success']) {
                    $showOTPForm = true;
                    $userEmail = $email;
                }
            }
        } else if ($action === 'clear_pending') {
            // Clear pending login session
            EnhancedAuth::clearPendingLogin();
            // No message needed, just clear and continue
            // Force redirect back to login page
            //FIXED: Enable Back to Login after failed OTP entry
            header("Location: login.php");
            exit;
        }
    }
}

// FIXED: Check for pending login session - Don't clear it here!
// if (isset($_SESSION['pending_login'])) {
//     $showOTPForm = true;
//     $userEmail = $_SESSION['pending_login']['email'];
//     // DON'T clear pending_login here - it's needed for verification!
// }

//IMPORTANT NOTE:
// SUGGESTION: Require re-login if page is refreshed when otp is not submitted
//so that the verification is not forever up unless it is entered.
//meaning, when the page is refreshed and the user only got into the verification
//but never entered it, he will go back to the sign in page and wait 1 minute
//because he just requested a verification code that he never used,
//then he can proceed to the verification process again and log in to his account.

// REASON: it prevents “ghost sessions” where someone has an OTP request but no actual fresh login.
//
if (isset($_SESSION['pending_login'])) {
    // If the request was not POST (means it's a refresh or direct GET)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Clear pending login to force re-login
        EnhancedAuth::clearPendingLogin();
        $showOTPForm = false; 
    } else {
        // Still in OTP flow (POST verify_otp or resend_otp)
        $showOTPForm = true;
        $userEmail = $_SESSION['pending_login']['email'];
    }
}

// Get alert message from URL parameters
$urlMessage = $_GET['msg'] ?? '';
$urlMessageType = $_GET['type'] ?? 'error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --light-yellow: #fef3c7;
            --dark-blue: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-gray: #e5e7eb;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 50%, var(--light-blue) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            position: relative;
            overflow: auto;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        /* Back to Home Button - Positioned at top-left corner */
        .back-to-home {
            position: fixed;
            top: 2rem;
            left: 2rem;
            z-index: 1000;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.95rem;
        }

        .back-to-home:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--accent-yellow);
            transform: translateY(-2px);
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            flex: 1;
        }

        .alert-overlay {
            position: absolute;
            top: -20px;
            left: 0;
            right: 0;
            z-index: 1000;
            pointer-events: none;
        }

        .alert {
            background: var(--white);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
            border-left: 4px solid;
            backdrop-filter: blur(15px);
            opacity: 0;
            transform: translateY(-30px) scale(0.9);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: auto;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .alert.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .alert.hide {
            opacity: 0;
            transform: translateY(-30px) scale(0.9);
        }

        /* Enhanced progress bar animation */
        .alert::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 0 0 12px 12px;
            width: 100%;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 5s linear;
        }

        .alert.show::after {
            transform: scaleX(1);
        }

        .alert-error {
            border-left-color: var(--error);
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            color: #7f1d1d;
        }

        .alert-error::after {
            background: linear-gradient(90deg, var(--error), #f87171);
        }

        .alert-success {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #d1fae5 0%, #ffffff 100%);
            color: #064e3b;
        }

        .alert-success::after {
            background: linear-gradient(90deg, var(--success), #34d399);
        }

        .alert-warning {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fef3c7 0%, #ffffff 100%);
            color: #78350f;
        }

        .alert-warning::after {
            background: linear-gradient(90deg, var(--warning), #fbbf24);
        }

        .alert-icon {
            font-size: 1.3rem;
            flex-shrink: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .alert-message {
            flex: 1;
            line-height: 1.4;
            font-weight: 600;
        }

        .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.3rem;
            border-radius: 50%;
            opacity: 0.7;
            transition: all 0.2s ease;
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert-close:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.1);
            transform: scale(1.1);
        }

        .login-card {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
            position: relative;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            color: var(--white);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
        }

        .logo-icon img.logo-img {
            width: 100px; 
            height: auto;
            display: block;
            margin: 0 auto; 
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
            transform: translateY(-1px);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .form-input:focus + .form-icon {
            color: var(--accent-yellow);
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--text-dark);
            border: 2px solid var(--border-gray);
        }

        .btn-secondary:hover {
            background: var(--border-gray);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .otp-form {
            display: none;
        }

        .otp-form.active {
            display: block;
            animation: fadeIn 0.5s ease-out;
        }

        .login-form.hidden {
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: bold;
        }

        .back-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--light-blue);
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: var(--primary-blue);
            text-decoration: underline;
            font-weight: 600;
        }

        .forgot-password a:hover {
            color: var(--light-blue);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent-yellow);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .college-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            color: var(--white);
            padding-right: 3rem;
            animation: fadeInRight 1.5s ease-out;
        }

        .college-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
        }

        .college-info p {
            font-style: italic;
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--light-yellow);
            text-shadow: 1px 1px 6px rgba(0,0,0,0.3);
        }

        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
                justify-content: flex-start;
                align-items: center;
                padding: 1rem;
                padding-top: 6rem; /* Add space for fixed button */
            }
            
            .back-to-home {
                top: 1rem;
                left: 1rem;
                font-size: 0.9rem;
                padding: 0.6rem 1rem;
            }
            
            .college-info {
                align-items: center;
                padding-right: 0;
                margin-bottom: 2rem;
                text-align: center;
            }
            
            .college-info h1 {
                font-size: 2rem;
            }
            
            .login-card {
                padding: 2rem;
            }

            .alert-overlay {
                top: -15px;
            }
        }
    </style>
</head>
<body>
    <!-- Back to Home Button - Fixed at top-left corner -->
    <a href="index.php" class="back-to-home">
        <i class="fas fa-home"></i>
        Back to Home
    </a>

    <div class="college-info">
        <h1>Golden Link College Foundation Inc.</h1>
        <p>"Be The Best That You Can Be!"</p>
    </div>
    
    <div class="login-container">
        <!-- Floating Alert Overlay -->
        <div class="alert-overlay" id="alertOverlay"></div>

        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">
                    <img src="shared/GLC_LOGO.png" alt="GLC Logo" class="logo-img">
                </div>
                <div class="logo-text">GLC AIMS</div>
                <div class="logo-subtitle">Academic Information Management System</div>
            </div>

            <!-- Main Login Form -->
            <form method="post" action="" id="loginForm" class="login-form <?= $showOTPForm ? 'hidden' : '' ?>">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="form-input-wrapper">
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required
                               autocomplete="username">
                        <i class="fas fa-user form-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="form-input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password">
                        <i class="fas fa-lock form-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
                
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </form>

            <!-- OTP Verification Form -->
            <form method="post" action="" id="otpForm" class="otp-form <?= $showOTPForm ? 'active' : '' ?>">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="email" value="<?= htmlspecialchars($userEmail) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="otp">Verification Code</label>
                    <div class="form-input-wrapper">
                        <input type="text" 
                               id="otp" 
                               name="otp" 
                               class="form-input otp-input" 
                               placeholder="000000"
                               maxlength="6"
                               required
                               pattern="[0-9]{6}"
                               autocomplete="one-time-code">
                        <i class="fas fa-shield-alt form-icon"></i>
                    </div>
                    <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                        Enter the 6-digit code sent to: <strong><?= htmlspecialchars($userEmail) ?></strong>
                    </small>
                </div>

                <button type="submit" class="btn-login" id="otpBtn">
                    <i class="fas fa-check"></i>
                    Verify Code
                </button>
                
                <button type="button" class="btn-login btn-secondary" id="resendBtn" onclick="resendOTP()">
                    <i class="fas fa-redo"></i>
                    Resend Code
                </button>

                <br>

                <a href="#" class="back-link" onclick="goBackToLogin()">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </form>
        </div>
    </div>

    <script>
        // Enhanced Alert System for Floating Alerts
        class FloatingAlertManager {
            constructor() {
                this.container = document.getElementById('alertOverlay');
                this.alerts = [];
            }

            show(message, type = 'error', duration = 5000, closable = true) {
                const alertId = 'alert_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                const alert = document.createElement('div');
                alert.id = alertId;
                alert.className = `alert alert-${type}`;
                
                const icon = this.getIcon(type);
                
                alert.innerHTML = `
                    <i class="fas fa-${icon} alert-icon"></i>
                    <div class="alert-message">${message}</div>
                    ${closable ? '<button class="alert-close" onclick="alertManager.close(\'' + alertId + '\')"><i class="fas fa-times"></i></button>' : ''}
                `;
                
                this.container.appendChild(alert);
                this.alerts.push(alertId);
                
                // Trigger show animation
                setTimeout(() => {
                    alert.classList.add('show');
                }, 100);
                
                // Auto-close after duration
                if (duration > 0) {
                    setTimeout(() => {
                        this.close(alertId);
                    }, duration);
                }
                
                return alertId;
            }

            close(alertId) {
                const alert = document.getElementById(alertId);
                if (alert) {
                    alert.classList.add('hide');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                        this.alerts = this.alerts.filter(id => id !== alertId);
                    }, 500);
                }
            }

            closeAll() {
                this.alerts.forEach(alertId => {
                    this.close(alertId);
                });
            }

            getIcon(type) {
                const icons = {
                    'error': 'exclamation-circle',
                    'success': 'check-circle',
                    'warning': 'exclamation-triangle',
                    'info': 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
        }

        // Initialize alert manager
        const alertManager = new FloatingAlertManager();

        // Alert helper function
        function showAlert(message, type = 'error', duration = 5000) {
            return alertManager.show(message, type, duration);
        }

        // Form Navigation Functions
        function goBackToLogin() {
            // Clear the pending login session when going back
            fetch('<?= $_SERVER["PHP_SELF"] ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_pending&csrf_token=<?= Security::generateCSRFToken() ?>'
            }).then(() => {
                // location.reload();
                //FIXED: Enable Back to Login after failed OTP entry
                window.location.href = "login.php"; // explicit redirect instead if reload
            });
        }

        function resendOTP() {
            // Submit the original login form again to regenerate OTP
            const email = document.querySelector('input[name="email"]').value;
            
            // Create a form to resend OTP
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            form.innerHTML = `
                <input type="hidden" name="action" value="resend_otp">
                <input type="hidden" name="email" value="${email}">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-focus and input formatting
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.otp-form.active')) {
                document.getElementById('otp').focus();
            } else {
                document.getElementById('username').focus();
            }
            
            // Check for URL parameters and show alerts
            checkURLParams();
        });

        // Format OTP input
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Handle form submissions
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="loading"></span> Signing In...';
        });

        document.getElementById('otpForm').addEventListener('submit', function(e) {
            const otpBtn = document.getElementById('otpBtn');
            otpBtn.disabled = true;
            otpBtn.innerHTML = '<span class="loading"></span> Verifying...';
        });

        // Check for URL parameters and display alerts
        function checkURLParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const type = urlParams.get('type') || 'error';
            
            if (msg) {
                const message = decodeURIComponent(msg);
                let alertType = type;
                
                // Determine alert type based on message content
                if (message.toLowerCase().includes('too many') || message.toLowerCase().includes('minutes')) {
                    alertType = 'warning';
                } else if (message.toLowerCase().includes('success') || message.toLowerCase().includes('sent')) {
                    alertType = 'success';
                } else if (message.toLowerCase().includes('logged out')) {
                    alertType = 'info';
                }
                
                showAlert(message, alertType, alertType === 'warning' ? 7000 : 5000);
                
                // Clear URL parameters to prevent showing alert on refresh
                history.replaceState(null, null, window.location.pathname);
            }
        }

        // Show PHP-generated messages
        <?php if ($message): ?>
        showAlert(<?= json_encode($message) ?>, <?= json_encode($messageType) ?>, <?= $messageType === 'warning' ? 7000 : 5000 ?>);
        <?php endif; ?>
    </script>
</body>
</html>