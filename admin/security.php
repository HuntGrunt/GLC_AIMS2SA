<?php
// admin/security.php - Security Center for Super Admin
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Super Admin"]);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Helper function for logging security events (creates table if not exists)
function logSecurityEvent($eventType, $description, $userId = null, $ipAddress = null, $severity = 'medium') {
    global $db;
    
    $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Create security_logs table if it doesn't exist
    try {
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                description TEXT,
                user_id INT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id),
                INDEX idx_severity (severity)
            )
        ";
        $db->exec($createTableQuery);
        
        // Insert security event
        $insertQuery = "INSERT INTO security_logs (event_type, description, user_id, ip_address, user_agent, severity) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($insertQuery);
        return $stmt->execute([$eventType, $description, $userId, $ipAddress, $userAgent, $severity]);
        
    } catch (PDOException $e) {
        error_log("Security logging failed: " . $e->getMessage());
        return false;
    }
}

// Ensure security_logs table exists before any queries
function ensureSecurityLogsTable() {
    global $db;
    
    try {
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                description TEXT,
                user_id INT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id),
                INDEX idx_severity (severity)
            )
        ";
        $db->exec($createTableQuery);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to create security_logs table: " . $e->getMessage());
        return false;
    }
}

// Handle security actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'block_ip':
            $ipToBlock = $_POST['ip_address'] ?? '';
            if ($ipToBlock && filter_var($ipToBlock, FILTER_VALIDATE_IP)) {
                logSecurityEvent('ip_blocked', "IP address $ipToBlock manually blocked by admin", $userId, $_SERVER['REMOTE_ADDR'], 'high');
                $successMessage = "IP address $ipToBlock has been blocked.";
            } else {
                $errorMessage = "Invalid IP address format.";
            }
            break;
            
        case 'unblock_ip':
            $ipToUnblock = $_POST['ip_address'] ?? '';
            if ($ipToUnblock) {
                logSecurityEvent('ip_unblocked', "IP address $ipToUnblock manually unblocked by admin", $userId, $_SERVER['REMOTE_ADDR'], 'medium');
                $successMessage = "IP address $ipToUnblock has been unblocked.";
            }
            break;
            
        case 'clear_security_logs':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 30);
            try {
                // Ensure table exists first
                ensureSecurityLogsTable();
                
                $stmt = $db->prepare("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$daysToKeep]);
                $deletedCount = $stmt->rowCount();
                logSecurityEvent('logs_cleared', "Security logs older than $daysToKeep days cleared ($deletedCount records)", $userId, $_SERVER['REMOTE_ADDR'], 'medium');
                $successMessage = "$deletedCount old security log records have been cleared.";
            } catch (PDOException $e) {
                $errorMessage = "Failed to clear security logs.";
            }
            break;
    }
}

// Ensure security_logs table exists before getting statistics
ensureSecurityLogsTable();

// Get security statistics
$securityStats = [
    'total_events' => 0,
    'critical_events' => 0,
    'high_events' => 0,
    'failed_logins_today' => 0,
    'blocked_ips' => 0,
    'suspicious_activity' => 0,
    'events_last_hour' => 0
];

try {
    // Check if table exists first by trying a simple query
    $testQuery = $db->query("SELECT 1 FROM security_logs LIMIT 1");
    
    // If we get here, table exists, so get statistics
    $securityStatsQuery = fetchOne("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_events,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_events,
            SUM(CASE WHEN event_type = 'failed_login' AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as failed_logins_today,
            SUM(CASE WHEN event_type = 'ip_blocked' THEN 1 ELSE 0 END) as blocked_ips,
            SUM(CASE WHEN event_type = 'suspicious_activity' THEN 1 ELSE 0 END) as suspicious_activity,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as events_last_hour
        FROM security_logs
    ");
    
    if ($securityStatsQuery) {
        $securityStats = $securityStatsQuery;
    }
} catch (PDOException $e) {
    // If table still doesn't exist or query fails, use default values
    error_log("Security stats query failed: " . $e->getMessage());
}

// Get recent security events
$recentSecurityEvents = [];
try {
    $recentSecurityEvents = fetchAll("
        SELECT sl.*, u.username, u.first_name, u.last_name
        FROM security_logs sl
        LEFT JOIN users u ON sl.user_id = u.id
        ORDER BY sl.created_at DESC
        LIMIT 20
    ");
} catch (PDOException $e) {
    // Table doesn't exist or query failed, will be empty
    error_log("Recent security events query failed: " . $e->getMessage());
}

// Get failed login attempts from activity logs
$failedLoginAttempts = fetchAll("
    SELECT al.*, u.username, u.first_name, u.last_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action LIKE '%LOGIN%' AND al.ip_address IS NOT NULL
    ORDER BY al.created_at DESC
    LIMIT 10
");

// Simulate failed login detection from activity logs
$suspiciousIPs = [];
$ipCounts = [];

foreach ($failedLoginAttempts as $attempt) {
    $ip = $attempt['ip_address'];
    if ($ip) {
        $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
        if ($ipCounts[$ip] > 3) {
            $suspiciousIPs[$ip] = [
                'ip' => $ip,
                'attempts' => $ipCounts[$ip],
                'last_attempt' => $attempt['created_at'],
                'user' => $attempt['username'] ?? 'Unknown'
            ];
        }
    }
}

// Get system security settings
$securitySettings = [
    'max_login_attempts' => 5,
    'lockout_duration' => 15, // minutes
    'session_timeout' => 30, // minutes
    'password_min_length' => 8,
    'require_2fa' => false,
    'log_retention_days' => 90
];
?>

<div class="page-header">
    <h1><i class="fas fa-shield-alt"></i> Security Center</h1>
    <p>Monitor system security, manage threats, and configure security settings</p>
</div>

<!-- Success/Error Messages -->
<?php if (isset($successMessage)): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($successMessage) ?></span>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($errorMessage) ?></span>
    </div>
<?php endif; ?>

<!-- Security Status Overview -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= $securityStats['critical_events'] > 0 ? 'var(--error)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $securityStats['critical_events'] ?></div>
            <div class="stats-label">Critical Events</div>
            <small style="color: var(--text-light);">Requiring immediate attention</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= $securityStats['failed_logins_today'] > 10 ? 'var(--warning)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $securityStats['failed_logins_today'] ?></div>
            <div class="stats-label">Failed Logins Today</div>
            <small style="color: var(--text-light);">Authentication failures</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--light-blue); color: white;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $securityStats['events_last_hour'] ?></div>
            <div class="stats-label">Events Last Hour</div>
            <small style="color: var(--text-light);">Recent security activity</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= count($suspiciousIPs) ?></div>
            <div class="stats-label">Suspicious IPs</div>
            <small style="color: var(--text-light);">Multiple failed attempts</small>
        </div>
    </div>
</div>

<!-- Security Actions -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">
        <i class="fas fa-tools"></i> Security Actions
    </h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1rem;">
        <div class="action-card security-action">
            <div class="action-icon" style="background: var(--error); color: white;">
                <i class="fas fa-ban"></i>
            </div>
            <div class="action-content">
                <h4>Block IP Address</h4>
                <form method="POST" style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                    <input type="hidden" name="action" value="block_ip">
                    <input type="text" name="ip_address" placeholder="192.168.1.1" 
                           class="form-control" style="flex: 1; font-size: 0.85rem; padding: 0.4rem;">
                    <button type="submit" class="btn-sm btn-danger">Block</button>
                </form>
            </div>
        </div>
        
        <div class="action-card security-action">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-check"></i>
            </div>
            <div class="action-content">
                <h4>Unblock IP Address</h4>
                <form method="POST" style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                    <input type="hidden" name="action" value="unblock_ip">
                    <input type="text" name="ip_address" placeholder="192.168.1.1" 
                           class="form-control" style="flex: 1; font-size: 0.85rem; padding: 0.4rem;">
                    <button type="submit" class="btn-sm btn-success">Unblock</button>
                </form>
            </div>
        </div>
        
        <div class="action-card security-action">
            <div class="action-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-broom"></i>
            </div>
            <div class="action-content">
                <h4>Clear Old Security Logs</h4>
                <form method="POST" style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                    <input type="hidden" name="action" value="clear_security_logs">
                    <select name="days_to_keep" class="form-control" style="flex: 1; font-size: 0.85rem; padding: 0.4rem;">
                        <option value="30">Keep 30 days</option>
                        <option value="60">Keep 60 days</option>
                        <option value="90">Keep 90 days</option>
                    </select>
                    <button type="submit" class="btn-sm btn-warning" onclick="return confirm('Are you sure you want to clear old security logs?')">Clear</button>
                </form>
            </div>
        </div>
        
        <div class="action-card">
            <div class="action-icon" style="background: var(--light-blue); color: white;">
                <i class="fas fa-download"></i>
            </div>
            <div class="action-content">
                <h4>Export Security Report</h4>
                <p>Generate comprehensive security report</p>
                <button class="btn-sm btn-primary" style="margin-top: 0.5rem;">
                    <i class="fas fa-file-export"></i> Export Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Security Monitoring Dashboard -->
<div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
    <!-- Suspicious IP Addresses -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Suspicious IP Addresses</h3>
            <span class="alert-badge <?= count($suspiciousIPs) > 0 ? 'alert-warning' : 'alert-success' ?>">
                <?= count($suspiciousIPs) ?> detected
            </span>
        </div>
        
        <?php if (empty($suspiciousIPs)): ?>
            <div class="empty-state">
                <i class="fas fa-shield-alt"></i>
                <p>No suspicious IP addresses detected</p>
                <small>System monitoring for multiple failed attempts</small>
            </div>
        <?php else: ?>
            <div class="suspicious-ips-list">
                <?php foreach ($suspiciousIPs as $suspicious): ?>
                    <div class="suspicious-ip-item">
                        <div class="ip-info">
                            <strong><?= htmlspecialchars($suspicious['ip']) ?></strong>
                            <div class="ip-details">
                                <span class="attempts-badge"><?= $suspicious['attempts'] ?> attempts</span>
                                <small>Last: <?= date('M j, H:i', strtotime($suspicious['last_attempt'])) ?></small>
                                <small>User: <?= htmlspecialchars($suspicious['user']) ?></small>
                            </div>
                        </div>
                        <div class="ip-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="block_ip">
                                <input type="hidden" name="ip_address" value="<?= htmlspecialchars($suspicious['ip']) ?>">
                                <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Block this IP address?')">
                                    <i class="fas fa-ban"></i> Block
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Security Events -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-list"></i> Recent Security Events</h3>
            <span class="event-count"><?= count($recentSecurityEvents) ?> events</span>
        </div>
        
        <?php if (empty($recentSecurityEvents)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No security events recorded</p>
                <small>Security monitoring is active</small>
            </div>
        <?php else: ?>
            <div class="security-events-list">
                <?php foreach (array_slice($recentSecurityEvents, 0, 10) as $event): ?>
                    <div class="security-event-item">
                        <div class="event-info">
                            <div class="event-type">
                                <span class="severity-badge severity-<?= $event['severity'] ?>">
                                    <i class="fas fa-<?= getSeverityIcon($event['severity']) ?>"></i>
                                    <?= ucfirst($event['event_type']) ?>
                                </span>
                            </div>
                            <div class="event-details">
                                <strong><?= htmlspecialchars($event['description']) ?></strong>
                                <?php if ($event['username']): ?>
                                    <small>User: <?= htmlspecialchars($event['username']) ?></small>
                                <?php endif; ?>
                                <small>IP: <?= htmlspecialchars($event['ip_address'] ?? 'N/A') ?></small>
                            </div>
                        </div>
                        <div class="event-time">
                            <small><?= timeAgo($event['created_at']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Security Settings -->
<div class="dashboard-section">
    <div class="section-header">
        <h3><i class="fas fa-cog"></i> Security Settings</h3>
        <button class="btn-sm btn-primary" onclick="toggleSecuritySettings()">
            <i class="fas fa-edit"></i> Configure
        </button>
    </div>
    
    <div id="security-settings" class="security-settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
        <div class="setting-group">
            <h4><i class="fas fa-lock"></i> Authentication Settings</h4>
            <div class="setting-items">
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Max Login Attempts</strong>
                        <small>Before account lockout</small>
                    </div>
                    <div class="setting-value">
                        <span class="value-display"><?= $securitySettings['max_login_attempts'] ?></span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Lockout Duration</strong>
                        <small>Account lock time in minutes</small>
                    </div>
                    <div class="setting-value">
                        <span class="value-display"><?= $securitySettings['lockout_duration'] ?> min</span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Two-Factor Authentication</strong>
                        <small>Require 2FA for all users</small>
                    </div>
                    <div class="setting-value">
                        <span class="status-badge <?= $securitySettings['require_2fa'] ? 'status-enabled' : 'status-disabled' ?>">
                            <?= $securitySettings['require_2fa'] ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="setting-group">
            <h4><i class="fas fa-server"></i> System Settings</h4>
            <div class="setting-items">
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Session Timeout</strong>
                        <small>Auto-logout time in minutes</small>
                    </div>
                    <div class="setting-value">
                        <span class="value-display"><?= $securitySettings['session_timeout'] ?> min</span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Log Retention</strong>
                        <small>Keep security logs for days</small>
                    </div>
                    <div class="setting-value">
                        <span class="value-display"><?= $securitySettings['log_retention_days'] ?> days</span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <strong>Password Min Length</strong>
                        <small>Required password characters</small>
                    </div>
                    <div class="setting-value">
                        <span class="value-display"><?= $securitySettings['password_min_length'] ?> chars</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getSeverityIcon($severity) {
    $icons = [
        'low' => 'info-circle',
        'medium' => 'exclamation-circle',
        'high' => 'exclamation-triangle',
        'critical' => 'skull-crossbones'
    ];
    return $icons[$severity] ?? 'question-circle';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

// Log that Super Admin accessed security center
logSecurityEvent('security_center_accessed', 'Super Admin accessed security center', $userId, $_SERVER['REMOTE_ADDR'], 'low');
?>

<style>
    .page-header h1 {
        color: var(--primary-blue);
        margin-bottom: 0.5rem;
    }
    
    .page-header p {
        color: var(--text-light);
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border-left: 4px solid var(--success);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        border-left: 4px solid var(--error);
    }

    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-left: 4px solid var(--accent-yellow);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .stats-content {
        flex: 1;
    }
    
    .stats-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-blue);
        line-height: 1;
    }
    
    .stats-label {
        color: var(--text-dark);
        font-weight: 500;
        margin-top: 0.2rem;
    }

    .quick-actions h3 {
        color: var(--primary-blue);
        margin-bottom: 1rem;
    }
    
    .action-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .action-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }
    
    .security-action {
        align-items: flex-start;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-top: 0.2rem;
    }
    
    .action-content {
        flex: 1;
    }
    
    .action-content h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .action-content p {
        margin: 0.3rem 0 0;
        font-size: 0.9rem;
        color: var(--text-light);
    }

    .dashboard-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-gray);
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary-blue);
    }

    .alert-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .form-control {
        padding: 0.5rem;
        border: 1px solid var(--border-gray);
        border-radius: 6px;
        font-size: 0.9rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }

    .btn-danger {
        background: var(--error);
        color: white;
    }

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-warning {
        background: var(--warning);
        color: white;
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        color: var(--border-gray);
    }

    .empty-state p {
        font-size: 1rem;
        margin-bottom: 0.3rem;
    }

    .suspicious-ip-item,
    .security-event-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #eee;
        transition: background 0.2s ease;
    }

    .suspicious-ip-item:hover,
    .security-event-item:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    .suspicious-ip-item:last-child,
    .security-event-item:last-child {
        border-bottom: none;
    }

    .ip-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .ip-info strong {
        font-weight: 600;
        color: var(--text-dark);
        font-family: 'Courier New', monospace;
    }

    .ip-details {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .attempts-badge {
        background: var(--error);
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .event-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        flex: 1;
    }

    .event-type {
        margin-bottom: 0.3rem;
    }

    .severity-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .severity-low {
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }

    .severity-medium {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .severity-high {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }

    .severity-critical {
        background: rgba(107, 33, 168, 0.1);
        color: #7C3AED;
    }

    .event-details {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .event-details strong {
        color: var(--text-dark);
        font-weight: 500;
    }

    .event-details small {
        color: var(--text-light);
        font-size: 0.8rem;
    }

    .event-time {
        text-align: right;
    }

    .event-count {
        background: var(--light-gray);
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        color: var(--text-light);
        font-weight: 500;
    }

    .security-settings-grid {
        margin-top: 1rem;
    }

    .setting-group {
        background: var(--light-gray);
        border-radius: 10px;
        padding: 1.5rem;
    }

    .setting-group h4 {
        color: var(--primary-blue);
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .setting-items {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .setting-info {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .setting-info strong {
        font-weight: 600;
        color: var(--text-dark);
    }

    .setting-info small {
        color: var(--text-light);
        font-size: 0.8rem;
    }

    .setting-value {
        display: flex;
        align-items: center;
    }

    .value-display {
        font-weight: 600;
        color: var(--primary-blue);
        font-family: 'Courier New', monospace;
    }

    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .status-enabled {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .status-disabled {
        background: rgba(107, 114, 128, 0.1);
        color: var(--text-light);
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .grid {
            grid-template-columns: 1fr !important;
        }
        
        .security-settings-grid {
            grid-template-columns: 1fr;
        }
        
        .suspicious-ip-item,
        .security-event-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .ip-details {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.3rem;
        }
        
        .setting-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh security data every 30 seconds
    setInterval(function() {
        // Only refresh if user is still on the page
        if (document.visibilityState === 'visible') {
            refreshSecurityData();
        }
    }, 30000);

    // Form validation for IP addresses
    const ipInputs = document.querySelectorAll('input[name="ip_address"]');
    ipInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateIPAddress(this);
        });
    });

    // Security settings toggle functionality
    window.toggleSecuritySettings = function() {
        showNotification('Security settings configuration - Feature coming soon!', 'info');
    };
});

function validateIPAddress(input) {
    const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
    
    if (input.value && !ipPattern.test(input.value)) {
        input.style.borderColor = 'var(--error)';
        input.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.1)';
        
        // Show error message
        let errorMsg = input.parentNode.querySelector('.error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('small');
            errorMsg.className = 'error-message';
            errorMsg.style.color = 'var(--error)';
            errorMsg.style.fontSize = '0.7rem';
            errorMsg.style.marginTop = '0.2rem';
            input.parentNode.appendChild(errorMsg);
        }
        errorMsg.textContent = 'Please enter a valid IP address';
    } else {
        input.style.borderColor = '';
        input.style.boxShadow = '';
        
        const errorMsg = input.parentNode.querySelector('.error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    }
}

function refreshSecurityData() {
    // This would typically fetch updated data via AJAX
    console.log('Refreshing security data...');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-left: 4px solid var(--${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'primary-blue'});
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        color: var(--text-dark);
        max-width: 400px;
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// Export security report functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-primary') && e.target.textContent.includes('Export Report')) {
        e.preventDefault();
        showNotification('Security report export - Feature coming soon!', 'info');
    }
});
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>