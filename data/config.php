<?php
// data/config.php
// Enhanced configuration with environment variables and security settings

// Load environment variables first
require_once __DIR__ . '/env_loader.php';

// Validate required environment variables
EnvLoader::validateRequired([
    'DB_HOST', 'DB_USER', 'DB_NAME', 
    'SESSION_SECRET', 'CSRF_SECRET',
    'SMTP_USERNAME', 'SMTP_PASSWORD'
]);

// Database configuration - now from environment variables
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', 'aims_ver1'));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Email/SMTP configuration - from environment variables
define('SMTP_HOST', EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_USERNAME', EnvLoader::get('SMTP_USERNAME'));
define('SMTP_PASSWORD', EnvLoader::get('SMTP_PASSWORD'));
define('SMTP_PORT', EnvLoader::getInt('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', EnvLoader::get('SMTP_ENCRYPTION', 'tls'));
define('EMAIL_FROM_ADDRESS', EnvLoader::get('EMAIL_FROM_ADDRESS', SMTP_USERNAME));
define('EMAIL_FROM_NAME', EnvLoader::get('EMAIL_FROM_NAME', 'GLC AIMS System'));

// Security configuration - from environment variables
define('SESSION_TIMEOUT', EnvLoader::getInt('SESSION_TIMEOUT', 3600)); // 1 hour
define('SESSION_SECRET', EnvLoader::get('SESSION_SECRET'));
define('CSRF_SECRET', EnvLoader::get('CSRF_SECRET'));
define('ENCRYPTION_KEY', EnvLoader::get('ENCRYPTION_KEY'));
define('MAX_LOGIN_ATTEMPTS', EnvLoader::getInt('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_TIME', EnvLoader::getInt('LOGIN_LOCKOUT_TIME', 900)); // 15 minutes
define('CSRF_TOKEN_EXPIRE', EnvLoader::getInt('CSRF_TOKEN_EXPIRE', 3600)); // 1 hour

// File upload configuration - from environment variables
define('MAX_FILE_SIZE', EnvLoader::getInt('MAX_FILE_SIZE', 10485760)); // 10MB
define('ALLOWED_FILE_TYPES', explode(',', EnvLoader::get('ALLOWED_FILE_TYPES', 'pdf,jpg,jpeg,png,gif,doc,docx,txt')));

// OTP configuration - from environment variables
define('OTP_EXPIRY_TIME', EnvLoader::getInt('OTP_EXPIRY_TIME', 300)); // 5 minutes
define('OTP_LENGTH', EnvLoader::getInt('OTP_LENGTH', 6));
define('OTP_MAX_ATTEMPTS', EnvLoader::getInt('OTP_MAX_ATTEMPTS', 3));
define('PASSWORD_RESET_EXPIRY', EnvLoader::getInt('PASSWORD_RESET_EXPIRY', 1800)); // 30 minutes

// Application configuration - from environment variables
define('APP_NAME', EnvLoader::get('APP_NAME', 'GLC Academic Information Management System'));
define('SITE_NAME', APP_NAME); // For backward compatibility
define('APP_URL', EnvLoader::get('APP_URL', 'http://localhost/GLC_AIMS'));
define('SITE_URL', APP_URL); // For backward compatibility
define('APP_ENV', EnvLoader::get('APP_ENV', 'production'));
define('APP_DEBUG', EnvLoader::getBool('APP_DEBUG', false));
define('APP_TIMEZONE', EnvLoader::get('APP_TIMEZONE', 'Asia/Manila'));

// Path configuration - from environment variables
define('UPLOAD_PATH', __DIR__ . '/../' . trim(EnvLoader::get('UPLOAD_PATH', 'uploads/'), '/') . '/');
define('LOG_PATH', __DIR__ . '/../' . trim(EnvLoader::get('LOG_PATH', 'logs/'), '/') . '/');

// Logging configuration - from environment variables
define('LOG_LEVEL', EnvLoader::get('LOG_LEVEL', 'info'));
define('ENABLE_ERROR_LOGGING', EnvLoader::getBool('ENABLE_ERROR_LOGGING', true));
define('ENABLE_ACTIVITY_LOGGING', EnvLoader::getBool('ENABLE_ACTIVITY_LOGGING', true));
define('ENABLE_SECURITY_LOGGING', EnvLoader::getBool('ENABLE_SECURITY_LOGGING', true));

// Rate limiting configuration - from environment variables
define('RATE_LIMIT_LOGIN', EnvLoader::getInt('RATE_LIMIT_LOGIN', 5));
define('RATE_LIMIT_OTP', EnvLoader::getInt('RATE_LIMIT_OTP', 3));
define('RATE_LIMIT_PASSWORD_RESET', EnvLoader::getInt('RATE_LIMIT_PASSWORD_RESET', 3));
define('RATE_LIMIT_TIME_WINDOW', EnvLoader::getInt('RATE_LIMIT_TIME_WINDOW', 300)); // 5 minutes

// Third-party API configuration - from environment variables (optional)
define('GOOGLE_MAPS_API_KEY', EnvLoader::get('GOOGLE_MAPS_API_KEY', ''));
define('TWILIO_SID', EnvLoader::get('TWILIO_SID', ''));
define('TWILIO_TOKEN', EnvLoader::get('TWILIO_TOKEN', ''));
define('TWILIO_PHONE_NUMBER', EnvLoader::get('TWILIO_PHONE_NUMBER', ''));

// Maintenance mode - from environment variables
define('MAINTENANCE_MODE', EnvLoader::getBool('MAINTENANCE_MODE', false));
define('MAINTENANCE_MESSAGE', EnvLoader::get('MAINTENANCE_MESSAGE', 'System is under maintenance. Please try again later.'));

// Create necessary directories if they don't exist
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        error_log("Failed to create upload directory: " . UPLOAD_PATH);
    }
}

if (!is_dir(LOG_PATH)) {
    if (!mkdir(LOG_PATH, 0755, true)) {
        error_log("Failed to create log directory: " . LOG_PATH);
    }
}

// Set PHP configuration based on environment
if (APP_ENV === 'development') {
    ini_set('display_errors', EnvLoader::getBool('DISPLAY_ERRORS', true) ? 1 : 0);
    
    // Handle ERROR_REPORTING environment variable properly
    $errorLevel = EnvLoader::get('ERROR_REPORTING', 'E_ALL');
    if (is_string($errorLevel)) {
        // Convert string constant to actual PHP constant
        switch (strtoupper($errorLevel)) {
            case 'E_ALL':
                $errorLevel = E_ALL;
                break;
            case 'E_ERROR':
                $errorLevel = E_ERROR;
                break;
            case 'E_WARNING':
                $errorLevel = E_WARNING;
                break;
            case 'E_NOTICE':
                $errorLevel = E_NOTICE;
                break;
            default:
                $errorLevel = E_ALL; // Default fallback
        }
    }
    error_reporting($errorLevel);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Set security-related PHP settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', EnvLoader::getBool('ENABLE_HTTPS_REDIRECT', false) ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Set custom session name for security
ini_set('session.name', 'AIMS_SESSION');

// Security headers (if enabled) - FIXED CSP
if (EnvLoader::getBool('ENABLE_SECURITY_HEADERS', true)) {
    // Basic security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=()');
    
    // Simple, working CSP based on environment
    if (APP_ENV === 'development') {
        // Very permissive CSP for development
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https: wss: ws:;");
    } else {
        // Production CSP - functional but secure
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self';");
    }
}

// Check for maintenance mode
if (MAINTENANCE_MODE && !in_array($_SERVER['REQUEST_URI'] ?? '', ['/maintenance.php', '/admin/'])) {
    http_response_code(503);
    header('Retry-After: 3600'); // Retry after 1 hour
    
    // Show maintenance page
    $maintenancePage = __DIR__ . '/../maintenance.php';
    if (file_exists($maintenancePage)) {
        include $maintenancePage;
    } else {
        echo '<h1>503 Service Unavailable</h1><p>' . MAINTENANCE_MESSAGE . '</p>';
    }
    exit;
}

// Log configuration loading (for debugging)
if (APP_ENV === 'development' && EnvLoader::getBool('SQL_DEBUG', false)) {
    error_log("AIMS Configuration loaded successfully");
    error_log("Environment: " . APP_ENV);
    error_log("Debug mode: " . (APP_DEBUG ? 'ON' : 'OFF'));
}
?>