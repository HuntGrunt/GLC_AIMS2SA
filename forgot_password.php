<?php
// forgot_password.php - Forgot password with OTP and DOB verification
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/data/auth_enhanced.php';
require_once __DIR__ . '/data/security.php';

$message = '';
$messageType = 'error';
$step = 'email'; // email, otp_dob, reset
$email = '';
$resetToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = getPost('csrf_token');
    if (!Security::verifyCSRFToken($csrfToken)) {
        $message = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $action = getPost('action');
        
        if ($action === 'send_otp') {
            $email = getPost('email', '', 'email');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
            } else {
                $result = EnhancedAuth::initiatePasswordReset($email);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                
                if ($result['success']) {
                    $step = 'otp_dob';
                }
            }
        } else if ($action === 'verify_otp_dob') {
            $email = getPost('email', '', 'email');
            $otp = getPost('otp');
            $dateOfBirth = getPost('date_of_birth');
            
            if (empty($email) || empty($otp) || empty($dateOfBirth)) {
                $message = 'Please fill in all required fields.';
                $step = 'otp_dob';
            } else {
                $result = EnhancedAuth::verifyPasswordResetOTP($email, $otp, $dateOfBirth);
                
                if ($result['success']) {
                    $step = 'reset';
                    $resetToken = $result['reset_token'];
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $step = 'otp_dob';
                }
            }
        } else if ($action === 'reset_password') {
            $resetToken = getPost('reset_token');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($newPassword) || empty($confirmPassword)) {
                $message = 'Please fill in all password fields.';
                $step = 'reset';
            } else if ($newPassword !== $confirmPassword) {
                $message = 'Passwords do not match.';
                $step = 'reset';
            } else if (strlen($newPassword) < 8) {
                $message = 'Password must be at least 8 characters long.';
                $step = 'reset';
            } else {
                $result = EnhancedAuth::completePasswordReset($resetToken, $newPassword);
                
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                    $step = 'success';
                } else {
                    $message = $result['message'];
                    $step = 'reset';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">
    <style>
        /* Reuse styles from login page */
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

        .forgot-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            flex: 1;
        }

        /* Floating Alert System - Positioned above the forgot card */
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

        .forgot-card {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
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
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: var(--text-light);
            font-size: 0.9rem;
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

        .btn-submit {
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

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .back-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--light-blue);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--border-gray);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 0.5rem;
            position: relative;
        }

        .step.active {
            background: var(--primary-blue);
            color: var(--white);
        }

        .step.completed {
            background: var(--success);
            color: var(--white);
        }

        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 40px;
            height: 2px;
            background: var(--border-gray);
            transform: translateY(-50%);
        }

        .step:last-child::after {
            display: none;
        }

        .step.completed::after {
            background: var(--success);
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            text-align: center;
            padding: 2rem 0;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        .password-requirements {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        .password-requirements ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
                justify-content: flex-start;
                align-items: center;
                padding: 1rem;
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
            
            .forgot-card {
                padding: 2rem;
            }

            .alert-overlay {
                top: -15px;
            }
        }

        @media (max-width: 480px) {
            .forgot-card {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="college-info">
        <h1>Golden Link College Foundation Inc.</h1>
        <p>"Be The Best That You Can Be!"</p>
    </div>

    <div class="forgot-container">
        <!-- Floating Alert Overlay -->
        <div class="alert-overlay" id="alertOverlay"></div>

        <div class="forgot-card">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="logo-text">Forgot Password</div>
                <div class="logo-subtitle">Reset your account password</div>
            </div>

            <?php if ($step !== 'success'): ?>
            <div class="step-indicator">
                <div class="step <?= in_array($step, ['email', 'otp_dob', 'reset']) ? 'active' : '' ?> <?= in_array($step, ['otp_dob', 'reset']) ? 'completed' : '' ?>">1</div>
                <div class="step <?= in_array($step, ['otp_dob', 'reset']) ? 'active' : '' ?> <?= $step === 'reset' ? 'completed' : '' ?>">2</div>
                <div class="step <?= $step === 'reset' ? 'active' : '' ?>">3</div>
            </div>
            <?php endif; ?>

            <?php if ($step === 'email'): ?>
            <!-- Step 1: Email Input -->
            <form method="post" action="">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="send_otp">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="form-input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="Enter your registered email"
                               value="<?= htmlspecialchars($email) ?>"
                               required>
                        <i class="fas fa-envelope form-icon"></i>
                    </div>
                    <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                        We'll send a verification code to this email address.
                    </small>
                </div>

                <button type="submit" class="btn-submit" id="emailBtn">
                    <i class="fas fa-paper-plane"></i>
                    Send Verification Code
                </button>
            </form>

            <br>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>

            <?php elseif ($step === 'otp_dob'): ?>
            <!-- Step 2: OTP and Date of Birth -->
            <form method="post" action="">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="verify_otp_dob">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="otp">Verification Code</label>
                    <div class="form-input-wrapper">
                        <input type="text" 
                               id="otp" 
                               name="otp" 
                               class="form-input" 
                               placeholder="000000"
                               maxlength="6"
                               required
                               pattern="[0-9]{6}"
                               style="text-align: center; font-size: 1.2rem; letter-spacing: 0.3rem;">
                        <i class="fas fa-shield-alt form-icon"></i>
                    </div>
                    <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                        Enter the 6-digit code sent to: <strong><?= htmlspecialchars($email) ?></strong>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_of_birth">Date of Birth</label>
                    <div class="form-input-wrapper">
                        <input type="date" 
                               id="date_of_birth" 
                               name="date_of_birth" 
                               class="form-input" 
                               required
                               max="<?= date('Y-m-d') ?>">
                        <i class="fas fa-calendar form-icon"></i>
                    </div>
                    <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                        Enter your date of birth as registered in the system.
                    </small>
                </div>

                <button type="submit" class="btn-submit" id="verifyBtn">
                    <i class="fas fa-check"></i>
                    Verify Information
                </button>
            </form>

            <br>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>

            <?php elseif ($step === 'reset'): ?>
            <!-- Step 3: Password Reset -->
            <form method="post" action="">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($resetToken) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <div class="form-input-wrapper">
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="Enter new password"
                               required
                               minlength="8">
                        <i class="fas fa-lock form-icon"></i>
                    </div>
                    <div class="password-requirements">
                        <strong>Password requirements:</strong>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Include letters and numbers</li>
                            <li>Avoid common words or phrases</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <div class="form-input-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Confirm new password"
                               required
                               minlength="8">
                        <i class="fas fa-lock form-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="resetBtn">
                    <i class="fas fa-key"></i>
                    Reset Password
                </button>
            </form>

            <?php elseif ($step === 'success'): ?>
            <!-- Success Message -->
            <div class="success-message">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Password Reset Successful!</h2>
                <p>Your password has been reset successfully. You can now login with your new password.</p>
                
                <a href="login.php" class="btn-submit" style="text-decoration: none; margin-top: 2rem;">
                    <i class="fas fa-sign-in-alt"></i>
                    Go to Login
                </a>
            </div>
            <?php endif; ?>
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

        // Format OTP input
        document.getElementById('otp')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Handle form submissions with loading states
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('.btn-submit');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="loading"></span> Processing...';
                    
                    // Re-enable after 10 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 10000);
                }
            });
        });

        // Auto-focus on appropriate field
        document.addEventListener('DOMContentLoaded', function() {
            const focusElement = document.querySelector('#email, #otp, #new_password');
            if (focusElement) {
                focusElement.focus();
            }
        });

        // Show PHP-generated messages as floating alerts
        <?php if ($message): ?>
        showAlert(<?= json_encode($message) ?>, <?= json_encode($messageType) ?>, <?= $messageType === 'warning' ? 7000 : 5000 ?>);
        <?php endif; ?>
    </script>
</body>
</html>