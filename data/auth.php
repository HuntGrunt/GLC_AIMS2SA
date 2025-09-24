<?php
// data/auth.php
// Enhanced authentication system with security features

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {
    private static $currentUser = null;
    
    public static function login($username, $password) {
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
        
        // Check password - handle both hashed and plain text for development
        $passwordValid = false;
        if (strlen($user['password']) > 50) {
            // Likely a hashed password
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Plain text password for development
            $passwordValid = ($password === $user['password']);
        }
        
        if (!$passwordValid) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Clear failed attempts on successful login
        self::clearFailedAttempts($username);
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $sessionExpires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        
        // Update user's session info
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
        ActivityLogger::log($user['id'], 'LOGIN', 'users', $user['id']);
        
        return ['success' => true, 'redirect' => self::getRedirectUrl($user['role_id'])];
    }
    
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            // Clear session token in database
            executeUpdate("UPDATE users SET session_token = NULL, session_expires = NULL WHERE id = ?", [$_SESSION['user_id']]);
            
            // Log logout
            ActivityLogger::log($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Please log in to access this page."));
            exit;
        }
        
        // Check session timeout
        if (self::isSessionExpired()) {
            self::logout();
            header("Location: /GLC_AIMS/login.php?msg=" . urlencode("Session expired. Please log in again."));
            exit;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
    
    public static function requireRole($allowedRoles = []) {
        self::requireLogin();
        
        $currentRole = $_SESSION['role_name'] ?? '';
        if (!in_array($currentRole, $allowedRoles)) {
            http_response_code(403);
            include __DIR__ . '/../shared/access_denied.php';
            exit;
        }
    }
    
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verify session token in database
        $user = fetchOne(
            "SELECT id FROM users WHERE id = ? AND session_token = ? AND session_expires > NOW()",
            [$_SESSION['user_id'], $_SESSION['session_token']]
        );
        
        return $user !== false;
    }
    
    public static function isSessionExpired() {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        return (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT;
    }
    
    public static function getCurrentUser() {
        if (self::$currentUser === null && self::isLoggedIn()) {
            self::$currentUser = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        }
        return self::$currentUser;
    }
    
    public static function hasRole($role) {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $role;
    }
    
    public static function hasPermission($permission) {
        $role = $_SESSION['role_name'] ?? '';
        
        $permissions = [
            'Super Admin' => ['*'], // All permissions
            'Registrar' => ['manage_grades', 'upload_files', 'view_students'],
            'SAO' => ['manage_announcements', 'manage_inventory', 'approve_borrows'],
            'Faculty' => ['submit_grades', 'view_assigned_students', 'manage_own_submissions'],
            'Student' => ['view_own_data', 'borrow_items']
        ];
        
        if (!isset($permissions[$role])) {
            return false;
        }
        
        return in_array('*', $permissions[$role]) || in_array($permission, $permissions[$role]);
    }
    
    // Get role name from role ID
    public static function getRoleName($roleId) {
        $role = fetchOne("SELECT role FROM roles WHERE id = ?", [$roleId]);
        return $role ? $role['role'] : '';
    }
    
    // Get redirect URL based on role ID - FIXED with proper Faculty mapping
    public static function getRedirectUrl($roleId) {
        // First try to get role name to handle both ID and name-based redirects
        $roleName = self::getRoleName($roleId);
        
        // Primary redirect mapping by role ID
        $redirects = [
            1 => '/GLC_AIMS/admin/dashboard.php',
            2 => '/GLC_AIMS/registrar/dashboard.php', 
            3 => '/GLC_AIMS/sao/dashboard.php',
            4 => '/GLC_AIMS/student/dashboard.php',
            5 => '/GLC_AIMS/faculty/dashboard.php'  // Added Faculty role
        ];
        
        // Fallback mapping by role name (in case role IDs are different)
        $roleNameRedirects = [
            'Super Admin' => '/GLC_AIMS/admin/dashboard.php',
            'Admin' => '/GLC_AIMS/admin/dashboard.php',
            'Registrar' => '/GLC_AIMS/registrar/dashboard.php',
            'SAO' => '/GLC_AIMS/sao/dashboard.php', 
            'Student' => '/GLC_AIMS/student/dashboard.php',
            'Faculty' => '/GLC_AIMS/faculty/dashboard.php'
        ];
        
        // Try role ID first, then role name, then default
        if (isset($redirects[$roleId])) {
            return $redirects[$roleId];
        } elseif (isset($roleNameRedirects[$roleName])) {
            return $roleNameRedirects[$roleName];
        }
        
        // Default fallback
        return '/GLC_AIMS/student/dashboard.php';
    }
    
    // Get role ID from role name (utility function)
    public static function getRoleId($roleName) {
        $role = fetchOne("SELECT id FROM roles WHERE role = ?", [$roleName]);
        return $role ? $role['id'] : null;
    }
    
    // Check if user is locked out due to failed attempts
    protected static function isLockedOut($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        
        if (!file_exists($lockFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($lockFile), true);
        
        if ($data['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lockoutEnd = $data['last_attempt'] + LOGIN_LOCKOUT_TIME;
            return time() < $lockoutEnd;
        }
        
        return false;
    }
    
    // Record failed login attempt
    protected static function recordFailedAttempt($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        
        $data = ['attempts' => 0, 'last_attempt' => 0];
        if (file_exists($lockFile)) {
            $data = json_decode(file_get_contents($lockFile), true);
        }
        
        $data['attempts']++;
        $data['last_attempt'] = time();
        
        // Ensure LOG_PATH directory exists
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        file_put_contents($lockFile, json_encode($data));
    }
    
    // Clear failed login attempts
    protected static function clearFailedAttempts($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
    
    // Debug function to check role mappings
    public static function debugRoleMappings() {
        if (APP_ENV !== 'development') {
            return [];
        }
        
        $roles = fetchAll("SELECT id, role FROM roles ORDER BY id");
        $mappings = [];
        
        foreach ($roles as $role) {
            $mappings[] = [
                'id' => $role['id'],
                'name' => $role['role'],
                'redirect' => self::getRedirectUrl($role['id'])
            ];
        }
        
        return $mappings;
    }
}

class ActivityLogger {
    public static function log($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        // Skip logging if activity logging is disabled
        if (!defined('ENABLE_ACTIVITY_LOGGING') || !ENABLE_ACTIVITY_LOGGING) {
            return;
        }
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            executeUpdate(
                "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $tableName,
                    $recordId,
                    $oldValues ? json_encode($oldValues) : null,
                    $newValues ? json_encode($newValues) : null,
                    $ipAddress,
                    $userAgent
                ]
            );
        } catch (Exception $e) {
            // Log the error but don't break the application
            error_log("ActivityLogger error: " . $e->getMessage());
        }
    }
    
    // Get recent activities for a user
    public static function getUserActivities($userId, $limit = 10) {
        return fetchAll(
            "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
    
    // Get recent activities for all users (admin function)
    public static function getAllActivities($limit = 50) {
        return fetchAll(
            "SELECT al.*, u.username, u.first_name, u.last_name 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC 
             LIMIT ?",
            [$limit]
        );
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-logout on session timeout
if (isset($_SESSION['user_id']) && Auth::isSessionExpired()) {
    Auth::logout();
}

// Helper function for development - debug current user role
if (APP_ENV === 'development' && isset($_SESSION['user_id'])) {
    function debugCurrentUser() {
        echo "<!-- DEBUG: Current user role: " . ($_SESSION['role_name'] ?? 'None') . " -->";
        echo "<!-- DEBUG: User ID: " . ($_SESSION['user_id'] ?? 'None') . " -->";
        echo "<!-- DEBUG: Expected redirect: " . Auth::getRedirectUrl(Auth::getRoleId($_SESSION['role_name'] ?? '')) . " -->";
    }
}
?>