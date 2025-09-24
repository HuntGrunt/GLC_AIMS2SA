<?php
// enrollment.php - Fixed version with proper success message handling
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/enrolleesdb.php';
require_once __DIR__ . '/data/security.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for success parameter FIRST - before any other processing
$showSuccess = isset($_GET['success']) && $_GET['success'] === '1';

if ($showSuccess) {
    // Clear any session data after successful enrollment
    unset($_SESSION['agreed']);
    unset($_SESSION['enrollment_error']);
    unset($_SESSION['enrollment_old_values']);
    
    // Show success page directly
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enrollment Success - <?= SITE_NAME ?></title>
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
                --success: #10b981;
                --gradient-blue: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: var(--text-dark);
                background: var(--light-gray);
                min-height: 100vh;
            }

            .header {
                background: var(--gradient-blue);
                color: var(--white);
                padding: 1rem 0;
                box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
                position: sticky;
                top: 0;
                z-index: 100;
            }

            .header-content {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 2rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 1rem;
                font-size: 1.3rem;
                font-weight: 700;
            }

            .logo img {
                width: 50px;
                height: 50px;
                border-radius: 50px;
                box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
            }

            .back-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--white);
                text-decoration: none;
                padding: 0.5rem 1rem;
                border-radius: 8px;
                border: 2px solid transparent;
                transition: all 0.3s ease;
            }

            .back-btn:hover {
                background: rgba(251, 191, 36, 0.2);
                border-color: var(--accent-yellow);
            }

            .main-container {
                max-width: 800px;
                margin: 2rem auto;
                padding: 0 2rem;
            }

            .success-container {
                background: var(--white);
                border-radius: 20px;
                padding: 3rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                text-align: center;
                position: relative;
                overflow: hidden;
                animation: fadeInUp 0.5s ease-out;
            }

            .success-container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 6px;
                background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            }

            .success-icon {
                background: var(--success);
                color: white;
                width: 100px;
                height: 100px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2.5rem;
                margin: 0 auto 2rem;
                box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
                animation: bounceIn 0.8s ease-out 0.3s both;
            }

            .success-container h1 {
                color: var(--success);
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .success-container p {
                color: var(--text-light);
                font-size: 1.1rem;
                margin-bottom: 2rem;
                line-height: 1.8;
            }

            .info-card {
                background: var(--light-yellow);
                border: 1px solid var(--accent-yellow);
                border-radius: 12px;
                padding: 2rem;
                margin: 2rem 0;
                text-align: left;
            }

            .info-card h3 {
                color: var(--primary-blue);
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.3rem;
            }

            .info-card ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .info-card ul li {
                margin-bottom: 0.8rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: var(--text-dark);
            }

            .info-card ul li i {
                color: var(--accent-yellow);
                font-size: 1.1rem;
            }

            .countdown-card {
                background: rgba(30, 58, 138, 0.05);
                border: 2px solid var(--primary-blue);
                border-radius: 12px;
                padding: 1.5rem;
                margin: 2rem 0;
                text-align: center;
                font-weight: 600;
                color: var(--primary-blue);
            }

            .countdown-number {
                font-size: 1.5rem;
                color: var(--accent-yellow);
                font-weight: 700;
            }

            .nav-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem 2rem;
                border: none;
                border-radius: 12px;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                background: var(--gradient-blue);
                color: var(--white);
                margin: 1rem 0.5rem;
            }

            .nav-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(30, 58, 138, 0.3);
            }

            .nav-btn.secondary {
                background: var(--light-gray);
                color: var(--text-dark);
                border: 2px solid var(--border-gray);
            }

            .nav-btn.secondary:hover {
                background: #e5e7eb;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes bounceIn {
                0%, 20%, 40%, 60%, 80% {
                    animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
                }
                0% {
                    opacity: 0;
                    transform: scale3d(.3, .3, .3);
                }
                20% {
                    transform: scale3d(1.1, 1.1, 1.1);
                }
                40% {
                    transform: scale3d(.9, .9, .9);
                }
                60% {
                    opacity: 1;
                    transform: scale3d(1.03, 1.03, 1.03);
                }
                80% {
                    transform: scale3d(.97, .97, .97);
                }
                to {
                    opacity: 1;
                    transform: scale3d(1, 1, 1);
                }
            }

            @keyframes confetti-fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }

            .confetti {
                position: fixed;
                z-index: 10000;
                pointer-events: none;
                border-radius: 50%;
            }

            @media (max-width: 768px) {
                .main-container {
                    padding: 0 1rem;
                    margin: 1rem auto;
                }

                .success-container {
                    padding: 2rem 1.5rem;
                }

                .success-container h1 {
                    font-size: 2rem;
                }

                .nav-btn {
                    width: 100%;
                    justify-content: center;
                    margin: 0.5rem 0;
                }
            }
        </style>
    </head>
    <body>
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <img src="shared/GLC_LOGO.png" alt="GLC Logo">
                    <span><?= SITE_NAME ?></span>
                </div>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </header>

        <!-- Main Container -->
        <div class="main-container">
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1>Application Submitted Successfully!</h1>
                <p>
                    Thank you for applying to <?= SITE_NAME ?>. 
                    Your enrollment application has been received and will be reviewed by our admissions team.
                    You will receive a confirmation email within 24 hours with your application reference number.
                </p>

                <div class="info-card">
                    <h3><i class="fas fa-clipboard-list"></i>What's Next?</h3>
                    <ul>
                        <li>
                            <i class="fas fa-envelope"></i>
                            Check your email for confirmation and reference number
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            Wait for admissions team review (2-3 business days)
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            Prepare for possible interview or assessment
                        </li>
                        <li>
                            <i class="fas fa-file-contract"></i>
                            Submit original documents upon acceptance
                        </li>
                        <li>
                            <i class="fas fa-graduation-cap"></i>
                            Complete enrollment process if accepted
                        </li>
                    </ul>
                </div>

                <div class="countdown-card" id="countdownCard">
                    <i class="fas fa-clock"></i>
                    Redirecting to homepage in <span class="countdown-number" id="countdown">15</span> seconds...
                    <br><small style="margin-top: 0.5rem; display: block; font-weight: normal;">
                        <a href="index.php" style="color: var(--primary-blue); text-decoration: underline;">
                            Click here to go now
                        </a>
                    </small>
                </div>

                <div style="margin-top: 2rem;">
                    <a href="index.php" class="nav-btn">
                        <i class="fas fa-home"></i>
                        Return to Homepage
                    </a>
                    <a href="enrollment.php?start=0" class="nav-btn secondary">
                        <i class="fas fa-plus"></i>
                        Submit Another Application
                    </a>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Create confetti effect
                createConfetti();
                
                // Auto-redirect countdown
                let countdown = 15;
                const countdownElement = document.getElementById('countdown');
                
                // Update countdown every second
                const countdownTimer = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(countdownTimer);
                        window.location.href = 'index.php';
                    }
                }, 1000);
                
                // Clear countdown if user navigates away manually
                const homeButtons = document.querySelectorAll('a[href="index.php"]');
                homeButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        clearInterval(countdownTimer);
                    });
                });
            });
            
            function createConfetti() {
                const colors = ['#1e3a8a', '#3b82f6', '#fbbf24', '#10b981', '#ef4444'];
                const confettiCount = 60;
                
                for (let i = 0; i < confettiCount; i++) {
                    setTimeout(() => {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        confetti.style.cssText = `
                            top: -10px;
                            left: ${Math.random() * 100}vw;
                            width: ${Math.random() * 8 + 4}px;
                            height: ${Math.random() * 8 + 4}px;
                            background: ${colors[Math.floor(Math.random() * colors.length)]};
                            animation: confetti-fall ${Math.random() * 2 + 3}s linear forwards;
                        `;
                        
                        document.body.appendChild(confetti);
                        
                        setTimeout(() => {
                            if (confetti.parentNode) {
                                confetti.remove();
                            }
                        }, 5000);
                    }, i * 100);
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Rate limiting check
if (!Security::checkRateLimit('enrollment_view', RATE_LIMIT_LOGIN, RATE_LIMIT_TIME_WINDOW)) {
    http_response_code(429);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Too Many Requests</title>
            <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            /* Full-page gradient background */
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 50%, var(--light-blue) 100%);
            display: flex;
            justify-content: center;  /* center the alert */
            align-items: center;
            min-height: 100vh;        /* ensure body fills the viewport */
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            overflow: hidden;         /* optional */
        }

            body::before {
                content: '';
                position: absolute;      /* important */
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
                background-repeat: no-repeat;   /* prevents tiling */
                background-size: cover;         /* fills the body */
                opacity: 0.3;
                z-index: -1;                    /* keeps it behind content */
            }

            .error-alert {
                background: #ffffffff;
                color: #000000ff;
                padding: 20px;
                border: 1px solid #000000ff;
                border-radius: 20px;
                font-family: Arial, sans-serif;
                font-size: 20px;
                text-align: center;
                max-width: 500px;
                margin: 100px auto;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            }
        </style>
    </head>
    <body>
        <div class="error-alert">
            ⚠️ Too many requests. Please try again later.
        </div>
    </body>
    </html>
    <?php
    exit;
}

// FIXED:Agreement will reset when user clicks back to homepage
if (isset($_GET['start']) && $_GET['start'] === '0') {
    unset($_SESSION['agreed']);
    unset($_SESSION['enrollment_error']);
    unset($_SESSION['enrollment_old_values']);
    unset($_SESSION['form_started']); // also reset form progress
}

// Step1: Agreement Page
$showForm = false;
$message = '';
$messageType = 'error';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    $csrfToken = getPost('csrf_token');
    if (!Security::verifyCSRFToken($csrfToken)) {
        $message = 'Invalid security token. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        if (isset($_POST['agree'])) {
            // User clicked "I Agree" → show the form right away
            $_SESSION['agreed'] = true;
            $showForm = true;
        } elseif (isset($_POST['home'])) {
            // User clicked "Go Back to Homepage" → redirect
            header("Location: index.php");
            exit;
        } elseif (isset($_POST['form_submit'])) {
            // User submitted enrollment form (first step or partial save)
            $_SESSION['form_started'] = true;
            $showForm = true;
        }
    }
} else {
    // Handle refresh / direct GET request
    if (isset($_SESSION['agreed']) && $_SESSION['agreed'] === true) {
        if (!empty($_SESSION['form_started'])) {
            // User already started filling the form → keep showing it
            $showForm = true;
        } else {
            // Reset agreement if form not started → show agreement again
            unset($_SESSION['agreed']);
            $showForm = false;
        }
    }
}

// Check for error messages from save_enrollment.php
if (isset($_SESSION['enrollment_error'])) {
    $message = $_SESSION['enrollment_error']['message'];
    $messageType = $_SESSION['enrollment_error']['type'];
    unset($_SESSION['enrollment_error']);
}

// Get old values for form repopulation (from session or GET)
$old = $_SESSION['enrollment_old_values'] ?? $_GET ?? [];
if (isset($_SESSION['enrollment_old_values'])) {
    unset($_SESSION['enrollment_old_values']);
}

if (!$showForm) {
    // Always show agreement unless "I Agree" was just clicked
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Enrollment Agreement - <?= SITE_NAME ?></title>
        <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">
        <style>
            :root {
                --primary-blue: #1e3a8a;
                --light-blue: #3b82f6;
                --dark-blue: #1e40af;
                --white: #ffffff;
                --accent-yellow: #fbbf24;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue), var(--light-blue));
                display: flex;
                justify-content: center;
                align-items: flex-start;
                min-height: 100vh;
                margin: 0;
                position: relative;
                padding: 2rem 1rem;
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
                z-index: -1;
            }
            .agreement-box {
                background: var(--white);
                padding: 3rem;
                border-radius: 20px;
                box-shadow: 0 25px 50px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 720px;
                text-align: center;
                position: relative;
                z-index: 1;
                height: auto;
            }
            .logo {
                margin-bottom: 2rem;
            }
            .logo img {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                margin-bottom: 1rem;
            }
            .logo h2 {
                color: var(--primary-blue);
                margin: 0;
                font-size: 1.8rem;
            }
            .agreement-content {
                text-align: left;
                margin: 2rem 0;
                padding: 1.5rem;
                background: #f8fafc;
                border-radius: 12px;
                border-left: 4px solid var(--accent-yellow);
            }
            .agreement-content h3 {
                color: var(--primary-blue);
                margin-bottom: 1rem;
            }
            .agreement-content ul {
                margin: 1rem 0;
                padding-left: 1.5rem;
            }
            .agreement-content li {
                margin-bottom: 0.5rem;
                color: #374151;
            }
            .checkbox-container {
                margin: 2rem 0;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .checkbox-container input {
                transform: scale(1.2);
            }
            .checkbox-container label {
                font-weight: 600;
                color: var(--primary-blue);
            }
            button {
                width: 100%;
                padding: 1rem;
                border: none;
                border-radius: 12px;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                margin: 0.5rem 0;
                transition: all 0.3s ease;
            }
            .btn-primary {
                background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
                color: var(--white);
            }
            .btn-primary:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(30, 58, 138, 0.3);
            }
            .btn-primary:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .btn-secondary {
                background: #f3f4f6;
                color: var(--primary-blue);
                border: 2px solid #e5e7eb;
            }
            .btn-secondary:hover {
                background: #e5e7eb;
            }
        </style>
        <script>
            function toggleProceed() {
                const checkbox = document.getElementById('agreeCheck');
                const proceedBtn = document.getElementById('proceedBtn');
                proceedBtn.disabled = !checkbox.checked;
            }
        </script>
    </head>
    <body>
        <div class="agreement-box">
            <div class="logo">
                <img src="shared/GLC_LOGO.png" alt="GLC Logo">
                <h2>Enrollment Agreement</h2>
            </div>
            
            <div class="agreement-content">
                <h3>Data Privacy and Terms</h3>
                <p>Before proceeding with your enrollment application, please read and acknowledge the following:</p>
                <ul>
                    <li><strong>Data Accuracy:</strong> All information provided must be true, complete, and accurate.</li>
                    <li><strong>Privacy Policy:</strong> Your personal data will be used solely for enrollment and academic purposes.</li>
                    <li><strong>Document Verification:</strong> All uploaded documents must be authentic and verifiable.</li>
                    <li><strong>Communication:</strong> You agree to receive enrollment-related communications via the provided contact information.</li>
                    <li><strong>Processing Time:</strong> Applications are typically processed within 3-5 business days.</li>
                    <li><strong>Incomplete Applications:</strong> Missing or invalid information may delay processing.</li>
                </ul>
                <p><strong>By proceeding, you consent to the use of your data for enrollment purposes at <?= SITE_NAME ?>.</strong></p>
            </div>
            
            <form method="POST">
                <?= csrfTokenInput() ?>
                <div class="checkbox-container">
                    <input type="checkbox" id="agreeCheck" onclick="toggleProceed()" required>
                    <label for="agreeCheck">I have read, understood, and agree to the terms above</label>
                </div>
                <button type="submit" name="agree" id="proceedBtn" class="btn-primary" disabled>
                    Proceed to Enrollment Form
                </button>
                <button type="submit" name="home" class="btn-secondary">
                    Go Back to Homepage
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment - <?= SITE_NAME ?></title>
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
            --gradient-blue: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--light-gray);
            min-height: 100vh;
        }

        /* Alert System */
        .alert-overlay {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
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
            transform: translateX(100px);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: auto;
            max-width: 400px;
        }

        .alert.show {
            opacity: 1;
            transform: translateX(0);
        }

        .alert-error {
            border-left-color: var(--error);
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            color: #7f1d1d;
        }

        .alert-success {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #d1fae5 0%, #ffffff 100%);
            color: #064e3b;
        }

        .alert-warning {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fef3c7 0%, #ffffff 100%);
            color: #78350f;
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
        }

        .alert-close:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .header {
            background: var(--gradient-blue);
            color: var(--white);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(251, 191, 36, 0.2);
            border-color: var(--accent-yellow);
        }

        /* Main Container */
        .main-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .enrollment-header {
            text-align: center;
            margin-bottom: 3rem;
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .enrollment-header .icon {
            background: var(--gradient-blue);
            color: white;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 2rem;
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
        }

        .header-logo {
            width: 120px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .enrollment-header h1 {
            color: var(--primary-blue);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .enrollment-header p {
            color: var(--text-light);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-blue);
        }

        .form-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            gap: 1rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            background: var(--light-gray);
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .step.active {
            background: var(--gradient-blue);
            color: var(--white);
        }

        .step.completed {
            background: var(--success);
            color: var(--white);
        }

        /* Form Sections */
        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        label {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required {
            color: var(--error);
        }

        input, select, textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
        }

        input.error, select.error, textarea.error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* File Upload */
        .file-upload {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed var(--border-gray);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--light-gray);
        }

        .file-upload:hover {
            border-color: var(--accent-yellow);
            background: rgba(251, 191, 36, 0.05);
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            border: none;
            padding: 0;
        }

        .file-upload-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            pointer-events: none;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--accent-yellow);
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-gray);
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .nav-btn.prev {
            background: var(--light-gray);
            color: var(--text-dark);
            border: 2px solid var(--border-gray);
        }

        .nav-btn.prev:hover:not(:disabled) {
            background: var(--border-gray);
        }

        .nav-btn.next {
            background: var(--gradient-blue);
            color: var(--white);
        }

        .nav-btn.next:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 58, 138, 0.3);
        }

        .nav-btn.submit {
            background: var(--success);
            color: var(--white);
        }

        .nav-btn.submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--light-gray);
            border-radius: 3px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-blue);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Success Message */
        .success-message {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .success-icon {
            background: var(--success);
            color: white;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 2rem;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .success-message h2 {
            color: var(--success);
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .success-message p {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        /* Info Cards */
        .info-card {
            background: var(--light-yellow);
            border: 1px solid var(--accent-yellow);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .info-card h3 {
            color: var(--primary-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card ul {
            list-style: none;
            margin-left: 1rem;
        }

        .info-card ul li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card ul li::before {
            content: '✓';
            color: var(--success);
            font-weight: bold;
        }

        /* Error Messages */
        .error-message {
            color: var(--error);
            font-size: 0.9rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Loading Spinner */
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 0 1rem;
                margin: 1rem auto;
            }

            .form-container {
                padding: 2rem 1.5rem;
            }

            .enrollment-header {
                padding: 2rem 1.5rem;
            }

            .enrollment-header h1 {
                font-size: 2rem;
            }

            .form-steps {
                flex-direction: column;
                align-items: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-navigation {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-btn {
                width: 100%;
                justify-content: center;
            }

            .alert-overlay {
                top: 10px;
                right: 10px;
                left: 10px;
            }

            .alert {
                max-width: none;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section.active {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Overlay -->
    <div class="alert-overlay" id="alertOverlay"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="shared/GLC_LOGO.png" alt="GLC Logo">
                <span><?= SITE_NAME ?></span>
            </div>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Enrollment Header -->
        <div class="enrollment-header">
            <div class="icon">
                <img src="shared/GLC_LOGO.png" alt="<?= SITE_NAME ?> Logo" class="header-logo">
            </div>
            <h1>Enrollment Application</h1>
            <p>Begin your educational journey at <?= SITE_NAME ?>. Complete the form below to apply for admission to our programs.</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 25%"></div>
            </div>

            <!-- Form Steps -->
            <div class="form-steps">
                <div class="step active" data-step="1">
                    <i class="fas fa-user"></i>
                    <span>Personal Info</span>
                </div>
                <div class="step" data-step="2">
                    <i class="fas fa-home"></i>
                    <span>Contact</span>
                </div>
                <div class="step" data-step="3">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Academic</span>
                </div>
                <div class="step" data-step="4">
                    <i class="fas fa-file-alt"></i>
                    <span>Documents</span>
                </div>
            </div>

            <form id="enrollmentForm" action="save_enrollment.php" method="POST" enctype="multipart/form-data">
                <?= csrfTokenInput() ?>
                
                <!-- Step 1: Personal Information -->
                <div class="form-section active" data-section="1">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name <span class="required">*</span></label>
                                <input type="text" id="firstName" name="firstName" value="<?= Security::sanitizeInput($old['firstName'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter your first name
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="middleName">Middle Name</label>
                                <input type="text" id="middleName" name="middleName" value="<?= Security::sanitizeInput($old['middleName'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name <span class="required">*</span></label>
                                <input type="text" id="lastName" name="lastName" value="<?= Security::sanitizeInput($old['lastName'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter your last name
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthDate">Date of Birth <span class="required">*</span></label>
                                <input type="date" id="birthDate" name="birthDate" value="<?= Security::sanitizeInput($old['birthDate'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter your date of birth
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender <span class="required">*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?= (($old['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= (($old['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= (($old['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                                </select>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please select your gender
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="civilStatus">Civil Status <span class="required">*</span></label>
                                <select id="civilStatus" name="civilStatus" required>
                                    <option value="">Select Civil Status</option>
                                    <option value="single" <?= (($old['civilStatus'] ?? '') === 'single') ? 'selected' : '' ?>>Single</option>
                                    <option value="married" <?= (($old['civilStatus'] ?? '') === 'married') ? 'selected' : '' ?>>Married</option>
                                    <option value="divorced" <?= (($old['civilStatus'] ?? '') === 'divorced') ? 'selected' : '' ?>>Divorced</option>
                                    <option value="widowed" <?= (($old['civilStatus'] ?? '') === 'widowed') ? 'selected' : '' ?>>Widowed</option>
                                </select>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please select your civil status
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nationality">Nationality <span class="required">*</span></label>
                                <input type="text" id="nationality" name="nationality" placeholder="Filipino" value="<?= Security::sanitizeInput($old['nationality'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter your nationality
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="religion">Religion</label>
                                <input type="text" id="religion" name="religion" value="<?= Security::sanitizeInput($old['religion'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Contact Information -->
                <div class="form-section" data-section="2">
                    <div class="section-title">
                        <i class="fas fa-home"></i>
                        Contact Information
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?= Security::sanitizeInput($old['email'] ?? '', 'email') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter a valid email address
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" placeholder="+63 XXX XXX XXXX" value="<?= Security::sanitizeInput($old['phone'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter a valid phone number
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Complete Address <span class="required">*</span></label>
                            <textarea id="address" name="address" placeholder="House/Unit Number, Street, Barangay, City, Province, Postal Code" required><?= Security::sanitizeInput($old['address'] ?? '') ?></textarea>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter your complete address
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ParentGuardianContact">Parent/Guardian Contact Name <span class="required">*</span></label>
                                <input type="text" id="ParentGuardianContact" name="ParentGuardianContact" value="<?= Security::sanitizeInput($old['ParentGuardianContact'] ?? $old['parentGuardianContact'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter Parent/Guardian contact name
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="ParentGuardianPhone">Parent/Guardian Contact Phone <span class="required">*</span></label>
                                <input type="tel" id="ParentGuardianPhone" name="ParentGuardianPhone" value="<?= Security::sanitizeInput($old['ParentGuardianPhone'] ?? $old['parentGuardianPhone'] ?? '') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter Parent/Guardian contact phone
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="relationship">Relationship <span class="required">*</span></label>
                                <select id="relationship" name="relationship" required>
                                    <option value="">Select Relationship</option>
                                    <option value="parent" <?= (($old['relationship'] ?? '') === 'parent') ? 'selected' : '' ?>>Parent</option>
                                    <option value="grandparent" <?= (($old['relationship'] ?? '') === 'grandparent') ? 'selected' : '' ?>>Grandparent</option>
                                    <option value="guardian" <?= (($old['relationship'] ?? '') === 'guardian') ? 'selected' : '' ?>>Guardian</option>
                                    <option value="sibling" <?= (($old['relationship'] ?? '') === 'sibling') ? 'selected' : '' ?>>Sibling</option>
                                    <option value="spouse" <?= (($old['relationship'] ?? '') === 'spouse') ? 'selected' : '' ?>>Spouse</option>
                                    <option value="other" <?= (($old['relationship'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                                </select>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please select relationship
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Academic Information -->
                <div class="form-section" data-section="3">
                    <div class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Academic Information
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="program">Preferred Program <span class="required">*</span></label>
                                <select id="program" name="program" required>
                                    <option value="">Select a Program</option>
                                    <option value="bsed-english" <?= (($old['program'] ?? '') === 'bsed-english') ? 'selected' : '' ?>>BSEd Major in English</option>
                                    <option value="bsed-math" <?= (($old['program'] ?? '') === 'bsed-math') ? 'selected' : '' ?>>BSEd Major in Mathematics</option>
                                    <option value="bsed-science" <?= (($old['program'] ?? '') === 'bsed-science') ? 'selected' : '' ?>>BSEd Major in Science</option>
                                    <option value="beed" <?= (($old['program'] ?? '') === 'beed') ? 'selected' : '' ?>>BEEd General Education</option>
                                    <option value="bsit" <?= (($old['program'] ?? '') === 'bsit') ? 'selected' : '' ?>>BS in Information Technology</option>
                                    <option value="bsba" <?= (($old['program'] ?? '') === 'bsba') ? 'selected' : '' ?>>BS in Business Administration</option>
                                    <option value="bsais" <?= (($old['program'] ?? '') === 'bsais') ? 'selected' : '' ?>>BS in Accounting Information System</option>
                                    <option value="bspsychology" <?= (($old['program'] ?? '') === 'bspsychology') ? 'selected' : '' ?>>BS in Psychology</option>
                                </select>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please select a program
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="enrollmentType">Enrollment Type <span class="required">*</span></label>
                                <select id="enrollmentType" name="enrollmentType" required>
                                    <option value="">Select Type</option>
                                    <option value="new" <?= (($old['enrollmentType'] ?? '') === 'new') ? 'selected' : '' ?>>New Student</option>
                                    <option value="transfer" <?= (($old['enrollmentType'] ?? '') === 'transfer') ? 'selected' : '' ?>>Transfer Student</option>
                                    <option value="returning" <?= (($old['enrollmentType'] ?? '') === 'returning') ? 'selected' : '' ?>>Returning Student</option>
                                </select>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please select enrollment type
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="lastSchool">Last School Attended <span class="required">*</span></label>
                            <input type="text" id="lastSchool" name="lastSchool" value="<?= Security::sanitizeInput($old['lastSchool'] ?? '') ?>" required>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter your last school attended
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="yearGraduated">Year Graduated</label>
                                <input type="number" id="yearGraduated" name="yearGraduated" min="1990" max="2025" value="<?= Security::sanitizeInput($old['yearGraduated'] ?? '', 'int') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Value must be between 1990 and 2025
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gpa">GPA/General Average</label>
                                <input type="number" id="gpa" name="gpa" step="0.01" min="1" max="5" placeholder="e.g., 3.5" value="<?= Security::sanitizeInput($old['gpa'] ?? '', 'float') ?>" required>
                                <div class="error-message" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Value must be between 1 and 5
                                </div>
                            </div>
                        </div>

                        <div class="info-card">
                            <h3><i class="fas fa-info-circle"></i>Program Information</h3>
                            <p>Our programs are designed to provide comprehensive education and practical skills. Each program includes:</p>
                            <ul>
                                <li>Core curriculum courses</li>
                                <li>Specialized major subjects</li>
                                <li>Practical training and internships</li>
                                <li>Character development programs</li>
                                <li>Career guidance and placement assistance</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Documents -->
                <div class="form-section" data-section="4">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Required Documents
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-upload"></i>Document Requirements</h3>
                        <p>Please prepare and upload the following documents (PDF format, max 5MB each):</p>
                        <ul>
                            <li>Birth Certificate (NSO/PSA Copy)</li>
                            <li>High School Diploma/Certificate of Graduation</li>
                            <li>Transcript of Records</li>
                            <li>Certificate of Good Moral Character</li>
                            <li>2x2 Recent Photo (colored, white background)</li>
                            <li>Medical Certificate</li>
                        </ul>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="birthCert">Birth Certificate <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="birthCert" name="birthCert" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload birth certificate
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="diploma">High School Diploma <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="diploma" name="diploma" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload diploma
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="transcript">Transcript of Records <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="transcript" name="transcript" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload transcript of records
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="goodMoral">Certificate of Good Moral Character <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="goodMoral" name="goodMoral" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload certificate of good moral character
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="photo">2x2 Recent Photo <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>JPG, PNG (Max 2MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload 2x2 photo
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="medical">Medical Certificate <span class="required">*</span></label>
                            <div class="file-upload">
                                <input type="file" id="medical" name="medical" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            <div class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload medical certificate
                            </div>
                        </div>
                    </div>

                    <div class="info-card" style="background: rgba(239, 68, 68, 0.1); border-color: var(--error);">
                        <h3 style="color: var(--error);"><i class="fas fa-exclamation-triangle"></i>Important Notes</h3>
                        <p style="color: var(--text-dark);">
                            <strong>Document Submission:</strong><br>
                            • All documents must be clear and legible<br>
                            • Original documents will be required during enrollment confirmation<br>
                            • Incomplete submissions will delay processing<br>
                            • For questions about document requirements, contact our admissions office
                        </p>
                    </div>
                </div>

                <!-- Form Navigation -->
                <div class="form-navigation">
                    <button type="button" class="nav-btn prev" id="prevBtn" onclick="previousStep()" disabled>
                        <i class="fas fa-arrow-left"></i>
                        Previous
                    </button>
                    <button type="button" class="nav-btn next" id="nextBtn" onclick="nextStep()">
                        Next
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="nav-btn submit" id="submitBtn" style="display: none;">
                        <i class="fas fa-paper-plane"></i>
                        Submit Application
                    </button>
                </div>
            </form>

        </div>

        <!-- Success Message -->
        <div class="success-message" id="successMessage" style="display:none;">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>Application Submitted Successfully!</h2>
            <p>
                Thank you for applying to <?= SITE_NAME ?>. 
                Your application has been received and will be reviewed by our admissions team.
                You will receive a confirmation email within 24 hours with your application reference number.
            </p>
            <div style="background: var(--light-yellow); border: 1px solid var(--accent-yellow); border-radius: 12px; padding: 1.5rem; margin: 2rem 0;">
                <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">What's Next?</h3>
                <ul style="list-style: none; text-align: left;">
                    <li style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-envelope" style="color: var(--accent-yellow);"></i>
                        Check your email for confirmation and reference number
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-calendar-alt" style="color: var(--accent-yellow);"></i>
                        Wait for admissions team review (2-3 business days)
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-phone" style="color: var(--accent-yellow);"></i>
                        Prepare for possible interview or assessment
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-file-contract" style="color: var(--accent-yellow);"></i>
                        Submit original documents upon acceptance
                    </li>
                </ul>
            </div>
            <a href="index.php" class="nav-btn next">
                <i class="fas fa-home"></i>
                Return to Homepage
            </a>
        </div>
    </div>

    <!-- Success Page Handler -->
    <?php if (isset($_GET['success'])): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Hide the enrollment header and form
            document.querySelector('.enrollment-header').style.display = 'none';
            document.querySelector('.form-container').style.display = 'none';
            
            // Show success message
            const successMessage = document.getElementById('successMessage');
            successMessage.style.display = 'block';
            
            // Smooth scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Create confetti effect
            createConfetti();
            
            // Add success alert
            showAlert('success', 'Enrollment application submitted successfully! Check your email for confirmation.');
            
            // Auto-redirect countdown
            let countdown = 15;
            const countdownElement = document.createElement('div');
            countdownElement.style.cssText = `
                background: rgba(30, 58, 138, 0.1);
                border: 2px solid var(--primary-blue);
                border-radius: 12px;
                padding: 1rem;
                margin: 2rem 0;
                text-align: center;
                font-weight: 600;
                color: var(--primary-blue);
            `;
            countdownElement.innerHTML = `
                <i class="fas fa-clock"></i>
                Redirecting to homepage in <span id="countdown">${countdown}</span> seconds...
                <br><small style="margin-top: 0.5rem; display: block; font-weight: normal;">
                    <a href="index.php" style="color: var(--primary-blue); text-decoration: underline;">
                        Click here to go now
                    </a>
                </small>
            `;
            
            // Insert countdown before the "Return to Homepage" button
            const returnButton = successMessage.querySelector('.nav-btn');
            returnButton.parentNode.insertBefore(countdownElement, returnButton);
            
            // Update countdown every second
            const countdownTimer = setInterval(() => {
                countdown--;
                const countdownSpan = document.getElementById('countdown');
                if (countdownSpan) {
                    countdownSpan.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(countdownTimer);
                    window.location.href = 'index.php';
                }
            }, 1000);
            
            // Clear countdown if user navigates away manually
            const returnToHomeBtn = returnButton;
            if (returnToHomeBtn) {
                returnToHomeBtn.addEventListener('click', () => {
                    clearInterval(countdownTimer);
                });
            }
            
            // Animate success message
            successMessage.style.opacity = '0';
            successMessage.style.transform = 'translateY(20px)';
            successMessage.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                successMessage.style.opacity = '1';
                successMessage.style.transform = 'translateY(0)';
            }, 100);
        });
        
        function createConfetti() {
            const colors = ['#1e3a8a', '#3b82f6', '#fbbf24', '#10b981'];
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        top: -10px;
                        left: ${Math.random() * 100}vw;
                        width: 10px;
                        height: 10px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        z-index: 10000;
                        border-radius: 50%;
                        pointer-events: none;
                        animation: confetti-fall 3s linear forwards;
                    `;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => {
                        confetti.remove();
                    }, 3000);
                }, i * 50);
            }
        }
    </script>
    <?php endif; ?>

    <script>
        // Enhanced alert system
        function showAlert(type, message) {
            const alertOverlay = document.getElementById('alertOverlay');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const iconMap = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            alert.innerHTML = `
                <i class="fas ${iconMap[type]}"></i>
                <span>${message}</span>
                <button class="alert-close" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            alertOverlay.appendChild(alert);
            
            // Show alert with animation
            setTimeout(() => alert.classList.add('show'), 100);
            
            // Auto remove after 8 seconds
            setTimeout(() => closeAlert(alert), 8000);
        }

        function closeAlert(element) {
            const alert = element.closest ? element.closest('.alert') : element;
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 500);
        }

        // Display server-side messages
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showAlert('<?= $messageType ?>', '<?= addslashes($message) ?>');
        });
        <?php endif; ?>

        // Form validation and navigation
        let currentStep = 1;
        const totalSteps = 4;

        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateStep();
                }
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStep();
            }
        }

        function updateStep() {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });

            // Show current section
            document.querySelector(`[data-section="${currentStep}"]`).classList.add('active');

            // Update step indicators
            document.querySelectorAll('.step').forEach((step, index) => {
                const stepNumber = index + 1;
                step.classList.remove('active', 'completed');
                
                if (stepNumber < currentStep) {
                    step.classList.add('completed');
                } else if (stepNumber === currentStep) {
                    step.classList.add('active');
                }
            });

            // Update progress bar
            const progressPercentage = (currentStep / totalSteps) * 100;
            document.querySelector('.progress-fill').style.width = progressPercentage + '%';

            // Update navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            prevBtn.disabled = currentStep === 1;
            
            if (currentStep === totalSteps) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-flex';
            } else {
                nextBtn.style.display = 'inline-flex';
                submitBtn.style.display = 'none';
            }

            // Smooth scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Enhanced form validation
        function validateCurrentStep() {
            const currentSection = document.querySelector(`[data-section="${currentStep}"]`);
            const requiredFields = currentSection.querySelectorAll('[required]');
            let isValid = true;
            let firstError = null;

            requiredFields.forEach(field => {
                const errorMessage = field.parentNode.querySelector('.error-message');
                let fieldValid = true;
                
                // Basic required field validation
                if (!field.value.trim()) {
                    fieldValid = false;
                    if (errorMessage) errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i>This field is required';
                }

                // Email validation
                if (field.type === 'email' && field.value.trim()) {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(field.value)) {
                        fieldValid = false;
                        if (errorMessage) errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i>Please enter a valid email address';
                    }
                }

                // Phone validation
                if (field.type === 'tel' && field.value.trim()) {
                    const phonePattern = /^[\+]?[0-9\s\-\(\)]{10,}$/;
                    if (!phonePattern.test(field.value)) {
                        fieldValid = false;
                        if (errorMessage) errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i>Please enter a valid phone number';
                    }
                }

                // File size validation
                if (field.type === 'file' && field.files.length > 0) {
                    const file = field.files[0];
                    const maxSize = field.id === 'photo' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
                    
                    if (file.size > maxSize) {
                        fieldValid = false;
                        if (errorMessage) errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i>File size must be less than ${field.id === 'photo' ? '2MB' : '5MB'}`;
                    }
                }

                // Number range validation
                if (field.type === 'number' && field.value.trim() !== '') {
                    const value = parseFloat(field.value);
                    const min = parseFloat(field.min);
                    const max = parseFloat(field.max);

                    if (!isNaN(value) && (value < min || value > max)) {
                        fieldValid = false;
                        if (errorMessage) errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i>Value must be between ${min} and ${max}`;
                    }
                }

                // Update field styling and error display
                if (!fieldValid) {
                    field.classList.add('error');
                    if (errorMessage) errorMessage.style.display = 'flex';
                    isValid = false;
                    if (!firstError) firstError = field;
                } else {
                    field.classList.remove('error');
                    if (errorMessage) errorMessage.style.display = 'none';
                }
            });

            if (!isValid && firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showAlert('error', 'Please fix the errors in the form before continuing.');
            }

            return isValid;
        }

        // Clear error on input
        document.addEventListener('input', function(e) {
            if (e.target.matches('input, select, textarea')) {
                e.target.classList.remove('error');
                const errorMessage = e.target.parentNode.querySelector('.error-message');
                if (errorMessage) errorMessage.style.display = 'none';
            }
        });

        // File upload handling
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const fileUpload = this.closest('.file-upload');
                const fileUploadText = fileUpload.querySelector('.file-upload-text span');
                
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const fileSize = this.files[0].size;
                    const maxSize = this.id === 'photo' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
                    
                    if (fileSize <= maxSize) {
                        fileUploadText.textContent = `Selected: ${fileName}`;
                        fileUpload.style.borderColor = 'var(--success)';
                        fileUpload.style.background = 'rgba(16, 185, 129, 0.05)';
                    } else {
                        fileUploadText.textContent = `File too large: ${fileName}`;
                        fileUpload.style.borderColor = 'var(--error)';
                        fileUpload.style.background = 'rgba(239, 68, 68, 0.05)';
                    }
                } else {
                    fileUploadText.textContent = 'Click to upload or drag and drop';
                    fileUpload.style.borderColor = 'var(--border-gray)';
                    fileUpload.style.background = 'var(--light-gray)';
                }
            });
        });

        // Enhanced form submission
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            if (!validateCurrentStep()) {
                e.preventDefault();
                return;
            }
            
            // Show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="loading"></i> Submitting...';
            
            // If validation fails, reset button
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }, 10000); // Reset after 10 seconds if still disabled
        });

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            updateStep();
            
            // Auto-format phone numbers
            document.querySelectorAll('input[type="tel"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.startsWith('9') && value.length === 10) {
                        value = '63' + value;
                    }
                    
                    if (value.startsWith('63')) {
                        value = '+' + value;
                    }
                    
                    e.target.value = value;
                });
            });

            // Drag and drop file handling
            document.querySelectorAll('.file-upload').forEach(fileUpload => {
                const input = fileUpload.querySelector('input[type="file"]');
                
                fileUpload.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.borderColor = 'var(--accent-yellow)';
                    this.style.background = 'rgba(251, 191, 36, 0.1)';
                });
                
                fileUpload.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.style.borderColor = 'var(--border-gray)';
                    this.style.background = 'var(--light-gray)';
                });
                
                fileUpload.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.borderColor = 'var(--border-gray)';
                    this.style.background = 'var(--light-gray)';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        input.files = files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.matches('input:not([type="submit"]), select')) {
                e.preventDefault();
                nextStep();
            }
        });
    </script>
</body>
</html>