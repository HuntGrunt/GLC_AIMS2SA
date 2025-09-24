<?php
// admin/dashboard.php - Enhanced Admin Dashboard
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Get comprehensive statistics - FIXED: Use is_active instead of status
$userStats = fetchOne("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as students,
        SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as sao,
        SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as registrar,
        SUM(CASE WHEN role_id IN (1,5) THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as suspended_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_month
    FROM users
");

$systemStats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as daily_activities,
        (SELECT COUNT(*) FROM activity_logs WHERE action = 'LOGIN' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_logins,
        (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements,
        (SELECT COUNT(*) FROM borrow_transactions WHERE status = 'pending') as pending_requests,
        (SELECT COUNT(*) FROM inventory_items WHERE quantity_available <= 2) as low_stock_items
");

// Initialize securityStats with default values since security_logs table might not exist
$securityStats = [
    'total_security_events' => 0,
    'failed_logins' => 0,
    'suspicious_activities' => 0,
    'recent_events' => 0
];

// Try to get security stats if table exists
try {
    $securityStatsQuery = fetchOne("
        SELECT 
            COALESCE(COUNT(*), 0) as total_security_events,
            COALESCE(SUM(CASE WHEN event_type = 'failed_login' THEN 1 ELSE 0 END), 0) as failed_logins,
            COALESCE(SUM(CASE WHEN event_type = 'suspicious_activity' THEN 1 ELSE 0 END), 0) as suspicious_activities,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END), 0) as recent_events
        FROM security_logs
    ");
    if ($securityStatsQuery) {
        $securityStats = $securityStatsQuery;
    }
} catch (Exception $e) {
    // security_logs table doesn't exist, use default values
    error_log("Security logs table not found, using default values");
}

// Get recent activities - FIXED: Use is_active instead of status
$recentUsers = fetchAll("
    SELECT u.*, r.role as role_name, DATE(u.created_at) as join_date
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.is_active = 1
    ORDER BY u.created_at DESC
    LIMIT 5
");

$recentLogs = fetchAll("
    SELECT al.*, u.username, u.first_name, u.last_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");

// Get critical alerts
$criticalAlerts = [];

// Check for suspended users - FIXED: Use is_active instead of status
$suspendedCount = fetchOne("
    SELECT COUNT(*) as count
    FROM users 
    WHERE is_active = 0 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")['count'] ?? 0;

if ($suspendedCount > 2) {
    $criticalAlerts[] = [
        'alert_type' => 'suspended_users',
        'message' => $suspendedCount . ' users suspended in last 24 hours',
        'severity' => 'warning',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Check for low stock items
if (($systemStats['low_stock_items'] ?? 0) > 5) {
    $criticalAlerts[] = [
        'alert_type' => 'low_stock',
        'message' => $systemStats['low_stock_items'] . ' items are running low on stock',
        'severity' => 'warning',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Check for pending requests needing attention
if (($systemStats['pending_requests'] ?? 0) > 10) {
    $criticalAlerts[] = [
        'alert_type' => 'pending_requests',
        'message' => $systemStats['pending_requests'] . ' borrow requests pending approval',
        'severity' => 'warning',
        'created_at' => date('Y-m-d H:i:s')
    ];
}
?>

<div class="page-header">
    <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</p>
</div>

<!-- Critical Alerts -->
<?php if (!empty($criticalAlerts)): ?>
<div class="alert alert-warning" style="margin-bottom: 2rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>System Alerts Detected!</strong>
        <p><?= count($criticalAlerts) ?> issues require attention: 
        <?php 
        $messages = array_column($criticalAlerts, 'message');
        echo implode(', ', array_slice($messages, 0, 2));
        if (count($messages) > 2) echo ' and ' . (count($messages) - 2) . ' more';
        ?>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
        
        <a href="/GLC_AIMS/admin/users.php" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="action-content">
                <h4>Manage Users</h4>
                <p>View and manage <?= $userStats['total_users'] ?? 0 ?> system users</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/admin/reports.php" class="action-card">
            <div class="action-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-content">
                <h4>Generate Reports</h4>
                <p>System analytics and user reports</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/admin/activity_logs.php" class="action-card">
            <div class="action-icon" style="background: var(--light-blue); color: white;">
                <i class="fas fa-history"></i>
            </div>
            <div class="action-content">
                <h4>Activity Logs</h4>
                <p>Monitor user activities and system events</p>
            </div>
        </a>
        
        <?php if ($userRole === 'Super Admin'): ?>   
        <a href="/GLC_AIMS/admin/security.php" class="action-card">
            <div class="action-icon" style="background: var(--error); color: white;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="action-content">
                <h4>Security Center</h4>
                <p>Monitor security events and threats</p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Grid -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fa fa-bar-chart"></i> Statistics</h3>
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--primary-blue); color: white;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= $userStats['total_users'] ?? 0 ?></div>
                    <div class="stats-label">Total Users</div>
                    <small style="color: var(--text-light);"><?= $userStats['active_users'] ?? 0 ?> active • <?= $userStats['suspended_users'] ?? 0 ?> inactive</small>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--success); color: white;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= $userStats['students'] ?? 0 ?></div>
                    <div class="stats-label">Students</div>
                    <small style="color: var(--text-light);">Active student accounts</small>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--warning); color: white;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= ($userStats['admins'] ?? 0) + ($userStats['registrar'] ?? 0) + ($userStats['sao'] ?? 0) ?></div>
                    <div class="stats-label">Staff</div>
                    <small style="color: var(--text-light);">Administrative personnel</small>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: <?= ($systemStats['daily_activities'] ?? 0) > 50 ? 'var(--success)' : 'var(--warning)' ?>; color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= $systemStats['daily_activities'] ?? 0 ?></div>
                    <div class="stats-label">Daily Activities</div>
                    <small style="color: var(--text-light);"><?= $systemStats['weekly_logins'] ?? 0 ?> logins this week</small>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: <?= ($systemStats['pending_requests'] ?? 0) > 0 ? 'var(--warning)' : 'var(--success)' ?>; color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= $systemStats['pending_requests'] ?? 0 ?></div>
                    <div class="stats-label">Pending Requests</div>
                    <small style="color: var(--text-light);">Borrow requests awaiting approval</small>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: <?= ($systemStats['low_stock_items'] ?? 0) > 0 ? 'var(--error)' : 'var(--success)' ?>; color: white;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= $systemStats['low_stock_items'] ?? 0 ?></div>
                    <div class="stats-label">Low Stock Items</div>
                    <small style="color: var(--text-light);">Items need restocking</small>
                </div>
            </div>
        </div>
</div>

<!-- Dashboard Content Grid -->
<div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Users -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-users"></i> Recent Users</h3>
            <a href="/GLC_AIMS/admin/users.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentUsers)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No recent users</p>
            </div>
        <?php else: ?>
            <div class="users-list" style="text-align: left;">
                <?php foreach ($recentUsers as $user): ?>
                    <div class="user-item" style="justify-content: space-between; align-items: center;">
                        <div class="user-info" style="justify-content: flex-start;">
                            <div class="user-avatar"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></div>
                            <div class="user-details" style="text-align: left;">
                                <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                <small>@<?= htmlspecialchars($user['username']) ?></small>
                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                    <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $user['role_name'])) ?>">
                                        <?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                    • Joined <?= $user['join_date'] ?>
                                </div>
                            </div>
                        </div>
                        <div class="user-actions" style="margin-left: auto;">
                            <a href="/GLC_AIMS/admin/users.php?edit=<?= $user['id'] ?>" class="btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <a href="/GLC_AIMS/admin/activity_logs.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentLogs)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No recent activity</p>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentLogs as $log): ?>
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></strong>
                            <small><?= getActivityDescription($log['action']) ?></small>
                            <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                <?= timeAgo($log['created_at']) ?>
                            </div>
                        </div>
                        <div class="activity-icon">
                            <i class="fas fa-<?= getActivityIcon($log['action']) ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- System Overview -->
<div class="grid grid-2" style="gap: 2rem;">
    <!-- System Health -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-heartbeat"></i> System Health</h3>
        </div>
        
        <div class="health-items">
            <div class="health-item">
                <div class="health-info">
                    <strong>Database</strong>
                    <small>Connection healthy</small>
                </div>
                <div class="health-status">
                    <span class="status-badge status-healthy">
                        <i class="fas fa-check"></i>
                    </span>
                </div>
            </div>
            
            <div class="health-item">
                <div class="health-info">
                    <strong>User Sessions</strong>
                    <small><?= $userStats['active_users'] ?? 0 ?> active sessions</small>
                </div>
                <div class="health-status">
                    <span class="status-badge status-healthy">
                        <i class="fas fa-check"></i>
                    </span>
                </div>
            </div>
            
            <div class="health-item">
                <div class="health-info">
                    <strong>System Load</strong>
                    <small>Normal operations</small>
                </div>
                <div class="health-status">
                    <span class="status-badge <?= ($systemStats['daily_activities'] ?? 0) > 100 ? 'status-warning' : 'status-healthy' ?>">
                        <i class="fas fa-<?= ($systemStats['daily_activities'] ?? 0) > 100 ? 'exclamation-triangle' : 'check' ?>"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-chart-pie"></i> Quick Statistics</h3>
        </div>
        
        <div class="quick-stats">
            <div class="stat-item">
                <div class="stat-info">
                    <strong>New Users</strong>
                    <small>This month</small>
                </div>
                <div class="stat-number">
                    <?= $userStats['new_users_month'] ?? 0 ?>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-info">
                    <strong>Active Announcements</strong>
                    <small>System-wide</small>
                </div>
                <div class="stat-number">
                    <?= $systemStats['active_announcements'] ?? 0 ?>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-info">
                    <strong>Security Events</strong>
                    <small>Last 24 hours</small>
                </div>
                <div class="stat-number">
                    <?= $securityStats['recent_events'] ?? 0 ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getActivityIcon($action) {
    $icons = [
        'LOGIN' => 'sign-in-alt',
        'LOGIN_WITH_OTP' => 'sign-in-alt',
        'LOGOUT' => 'sign-out-alt',
        'CREATE_BORROW_REQUEST' => 'plus-circle',
        'CREATE_ANNOUNCEMENT' => 'bullhorn',
        'APPROVE_BORROW_REQUEST' => 'check',
        'REJECT_BORROW_REQUEST' => 'times',
        'PASSWORD_RESET' => 'key',
        'TOGGLE_ANNOUNCEMENT_STATUS' => 'toggle-on',
        'DELETE_ANNOUNCEMENT' => 'trash'
    ];
    return $icons[$action] ?? 'circle';
}

function getActivityDescription($action) {
    $descriptions = [
        'LOGIN' => 'logged into the system',
        'LOGIN_WITH_OTP' => 'logged in using OTP verification',
        'LOGOUT' => 'logged out of the system',
        'CREATE_BORROW_REQUEST' => 'created a borrow request',
        'CREATE_ANNOUNCEMENT' => 'created an announcement',
        'APPROVE_BORROW_REQUEST' => 'approved a borrow request',
        'REJECT_BORROW_REQUEST' => 'rejected a borrow request',
        'PASSWORD_RESET' => 'reset their password',
        'TOGGLE_ANNOUNCEMENT_STATUS' => 'toggled announcement status',
        'DELETE_ANNOUNCEMENT' => 'deleted an announcement'
    ];
    return $descriptions[$action] ?? 'performed an action';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}
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
        align-items: center;
        gap: 1rem;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        text-decoration: none;
        color: inherit;
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
    }
    
    .action-content h4 {
        margin: 0;
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
    
    .btn-link {
        font-size: 0.9rem;
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 500;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .user-item,
    .activity-item,
    .health-item,
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .user-item:last-child,
    .activity-item:last-child,
    .health-item:last-child,
    .stat-item:last-child {
        border-bottom: none;
    }
    
    /* .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-yellow);
        color: var(--primary-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
        flex-shrink: 0;
    } */
    
    .role-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .role-student {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .role-sao {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .role-registrar {
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }
    
    .role-admin,
    .role-super-admin {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }
    
    .activity-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--light-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-size: 0.9rem;
    }
    
    .status-healthy {
        background: var(--success);
        color: white;
    }
    
    .status-warning {
        background: var(--warning);
        color: white;
    }
    
    .status-error {
        background: var(--error);
        color: white;
    }
    
    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
    }
    
    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-blue);
    }

    .user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f4b400;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: bold;
        color: #fff;
        font-size: 1rem;
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-details strong {
        font-size: 1rem;
        color: #333;
    }

    .user-details small {
        color: #555;
    }

    .user-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: var(--text-light);
    }

    .user-actions .btn-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        .user-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .user-item,
        .activity-item,
        .health-item,
        .stat-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>