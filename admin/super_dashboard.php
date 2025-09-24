<?php
// admin/super_dashboard.php - Super Admin Dashboard with Branch Monitoring
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Super Admin"]);
require __DIR__ . "/../shared/header.php";

// Get comprehensive system overview - FIXED: Use is_active and role instead of status and role_name
$branchStats = [
    'admin' => fetchOne("
        SELECT 
            COUNT(*) as total_admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_admins,
            (SELECT COUNT(*) FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE role_id = 1) AND DATE(created_at) = CURDATE()) as daily_activities
        FROM users WHERE role_id = 1
    "),
    'registrar' => fetchOne("
        SELECT 
            COUNT(*) as total_registrars,
            (SELECT COUNT(*) FROM grades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_uploads,
            (SELECT COUNT(*) FROM student_files WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_files
        FROM users WHERE role_id = 2
    "),
    'sao' => fetchOne("
        SELECT 
            COUNT(*) as total_sao,
            (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements,
            (SELECT COUNT(*) FROM borrow_transactions WHERE status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM inventory_items WHERE quantity_available <= 2) as low_stock_items
        FROM users WHERE role_id = 3
    "),
    'faculty' => fetchOne("
        SELECT 
            COUNT(*) as total_faculty,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_faculty,
            (SELECT COUNT(DISTINCT borrower_id) FROM borrow_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_borrowers,
            (SELECT COUNT(*) FROM users WHERE role_id = 5 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_active
        FROM users WHERE role_id = 5
    "),
    'students' => fetchOne("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_students,
            (SELECT COUNT(DISTINCT borrower_id) FROM borrow_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_borrowers,
            (SELECT COUNT(*) FROM users WHERE role_id = 4 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_active
        FROM users WHERE role_id = 4
    ")
];

$systemOverview = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as daily_activities,
        0 as security_events,
        (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements
");

// Try to get security events if the table exists
try {
    $securityEvents = fetchOne("SELECT COUNT(*) as security_events FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($securityEvents) {
        $systemOverview['security_events'] = $securityEvents['security_events'];
    }
} catch (Exception $e) {
    // security_logs table doesn't exist, use default value of 0
}

// Global permissions and role management - FIXED: Use role instead of role_name and is_active
$rolePermissions = fetchAll("
    SELECT r.*, 
           COUNT(u.id) as user_count
    FROM roles r
    LEFT JOIN users u ON r.id = u.role_id AND u.is_active = 1
    GROUP BY r.id
    ORDER BY r.id
");

// Recent system-wide activities - FIXED: Use role instead of role_name
$recentSystemActivities = fetchAll("
    SELECT al.*, u.username, u.first_name, u.last_name, r.role as role_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    ORDER BY al.created_at DESC
    LIMIT 15
");

// Critical system alerts - FIXED: Use is_active instead of status
$criticalSystemAlerts = [];

// Check for suspended users
$suspendedCount = fetchOne("
    SELECT COUNT(*) as count
    FROM users 
    WHERE is_active = 0 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")['count'] ?? 0;

if ($suspendedCount > 2) {
    $criticalSystemAlerts[] = [
        'alert_type' => 'suspended_users',
        'message' => $suspendedCount . ' users suspended in last 24 hours',
        'severity' => 'warning',
        'latest_event' => date('Y-m-d H:i:s')
    ];
}

// Check for low stock items
$lowStockCount = fetchOne("
    SELECT COUNT(*) as count
    FROM inventory_items 
    WHERE quantity_available <= 2
")['count'] ?? 0;

if ($lowStockCount > 5) {
    $criticalSystemAlerts[] = [
        'alert_type' => 'low_stock',
        'message' => $lowStockCount . ' items are running low on stock',
        'severity' => 'warning',
        'latest_event' => date('Y-m-d H:i:s')
    ];
}

// Check for pending requests
$pendingRequests = fetchOne("
    SELECT COUNT(*) as count
    FROM borrow_transactions 
    WHERE status = 'pending'
")['count'] ?? 0;

if ($pendingRequests > 10) {
    $criticalSystemAlerts[] = [
        'alert_type' => 'pending_requests',
        'message' => $pendingRequests . ' borrow requests pending approval',
        'severity' => 'warning',
        'latest_event' => date('Y-m-d H:i:s')
    ];
}
?>

<div class="page-header">
    <h1><i class="fas fa-crown"></i> Super Admin Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</p>
</div>

<!-- Critical Alerts -->
<?php if (!empty($criticalSystemAlerts)): ?>
<div class="alert alert-warning" style="margin-bottom: 2rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>System Alerts Detected!</strong>
        <p><?= count($criticalSystemAlerts) ?> issues require attention: 
        <?php 
        $messages = array_column($criticalSystemAlerts, 'message');
        echo implode(', ', array_slice($messages, 0, 2));
        if (count($messages) > 2) echo ' and ' . (count($messages) - 2) . ' more';
        ?>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- System Overview Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-globe"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $systemOverview['total_users'] ?></div>
            <div class="stats-label">Total System Users</div>
            <small style="color: var(--text-light);">Active today: <?= $systemOverview['daily_activities'] ?></small>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value">99.8%</div>
            <div class="stats-label">System Uptime</div>
            <small style="color: var(--text-light);">Excellent performance</small>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-icon" style="background: <?= $systemOverview['security_events'] > 10 ? 'var(--warning)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $systemOverview['security_events'] ?></div>
            <div class="stats-label">Security Events (24h)</div>
            <small style="color: var(--text-light);"><?= $systemOverview['security_events'] > 10 ? 'Needs attention' : 'Normal activity' ?></small>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $systemOverview['active_announcements'] ?></div>
            <div class="stats-label">Active Announcements</div>
            <small style="color: var(--text-light);">System-wide notices</small>
        </div>
    </div>
</div>

<!-- Branch Monitoring Dashboard -->
<div class="branch-monitoring" style="margin-bottom: 2rem;">
    <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">
        <i class="fas fa-sitemap"></i> Branch Monitoring Portal
    </h3>
    <div class="grid grid-2" style="gap: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Registrar Branch -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-graduation-cap"></i> Registrar Portal </h3>
                <span class="status-badge status-healthy">Operational</span>
            </div>
            
            <div class="branch-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; padding: 1rem; background: var(--light-gray); border-radius: 12px;">
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['registrar']['total_registrars'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Staff Count</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['registrar']['weekly_uploads'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Weekly Uploads</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['registrar']['weekly_files'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Files Processed</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.8rem; margin-bottom: 1rem;">
                <a href="/GLC_AIMS/registrar/dashboard.php" class="btn-sm btn-primary" style="flex: 1; text-align: center; text-decoration: none;">View Dashboard</a>
                <a href="/GLC_AIMS/admin/users.php?role=registrar" class="btn-sm" style="flex: 1; text-align: center; text-decoration: none; background: var(--light-yellow); color: var(--text-dark);">Manage</a>
            </div>
        </div>

        <!-- SAO Branch -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-hands-helping"></i> SAO Portal </h3>
                <span class="status-badge status-healthy">Operational</span>
            </div>
            
            <div class="branch-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; padding: 1rem; background: var(--light-gray); border-radius: 12px;">
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['sao']['pending_requests'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Pending Requests</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['sao']['active_announcements'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Announcements</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['sao']['low_stock_items'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Low Stock</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.8rem; margin-bottom: 1rem;">
                <a href="/GLC_AIMS/sao/dashboard.php" class="btn-sm btn-primary" style="flex: 1; text-align: center; text-decoration: none;">View Dashboard</a>
                <a href="/GLC_AIMS/admin/users.php?role=sao" class="btn-sm" style="flex: 1; text-align: center; text-decoration: none; background: var(--light-yellow); color: var(--text-dark);">Manage</a>
            </div>
        </div>

        <!-- Faculty Branch -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-user-graduate"></i> Faculty Portal</h3>
                <span class="status-badge status-healthy">Operational</span>
            </div>
            
            <div class="branch-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; padding: 1rem; background: var(--light-gray); border-radius: 12px;">
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= number_format($branchStats['faculty']['total_faculty'] ?? 0) ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Total Faculty</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['faculty']['weekly_active'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Weekly Active</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['faculty']['active_borrowers'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Active Borrowers</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.8rem; margin-bottom: 1rem;">
                <a href="/GLC_AIMS/faculty/dashboard.php" class="btn-sm btn-primary" style="flex: 1; text-align: center; text-decoration: none;">View Portal</a>
                <a href="/GLC_AIMS/admin/users.php?role=faculty" class="btn-sm" style="flex: 1; text-align: center; text-decoration: none; background: var(--light-yellow); color: var(--text-dark);">Manage</a>
            </div>
        </div>        

        <!-- Student Branch -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-user-graduate"></i> Student Portal</h3>
                <span class="status-badge status-healthy">Operational</span>
            </div>
            
            <div class="branch-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; padding: 1rem; background: var(--light-gray); border-radius: 12px;">
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= number_format($branchStats['students']['total_students'] ?? 0) ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Total Students</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['students']['weekly_active'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Weekly Active</div>
                </div>
                <div class="metric" style="text-align: center;">
                    <div class="stats-value" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-blue);"><?= $branchStats['students']['active_borrowers'] ?? 0 ?></div>
                    <div class="stats-label" style="font-size: 0.8rem; color: var(--text-light);">Active Borrowers</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.8rem; margin-bottom: 1rem;">
                <a href="/GLC_AIMS/student/dashboard.php" class="btn-sm btn-primary" style="flex: 1; text-align: center; text-decoration: none;">View Portal</a>
                <a href="/GLC_AIMS/admin/users.php?role=student" class="btn-sm" style="flex: 1; text-align: center; text-decoration: none; background: var(--light-yellow); color: var(--text-dark);">Manage</a>
            </div>
        </div>
    </div>
</div>

<!-- Global Role & Permission Management -->
<!--<div class="role-management-section" style="margin-bottom: 2rem;">
    <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">
        <i class="fas fa-key"></i> Global Role & Permission Management
    </h3>
    <div class="grid grid-2" style="gap: 1.5rem;">
        <?php foreach ($rolePermissions as $role): ?>
        <div class="dashboard-section">
            <div class="section-header">
                <h4 style="margin: 0; color: var(--primary-blue);"><?= htmlspecialchars($role['role']) ?></h4>
                <div style="text-align: right;">
                    <span style="font-size: 1.5rem; font-weight: 700; color: var(--primary-blue); display: block; line-height: 1;"><?= $role['user_count'] ?></span>
                    <span style="font-size: 0.8rem; color: var(--text-light);">users</span>
                </div>
            </div>
            
            <div style="margin: 1rem 0;">
                <div style="font-size: 0.9rem; color: var(--text-dark); font-weight: 500; margin-bottom: 0.5rem;">Role ID: <?= $role['id'] ?></div>
                <div style="color: var(--text-light); font-style: italic; font-size: 0.85rem;">System role with standard permissions</div>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn-sm btn-warning btn-role-edit" data-role-id="<?= $role['id'] ?>" style="flex: 1;">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn-sm btn-primary btn-role-permissions" data-role-id="<?= $role['id'] ?>" style="flex: 1;">
                    <i class="fas fa-key"></i> Permissions
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div> -->

<!-- System Activity Timeline -->
<div class="activity-timeline-section">
    <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">
        <i class="fas fa-clock"></i> Recent System Activity
    </h3>
    <div class="dashboard-section">
        <?php if (empty($recentSystemActivities)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No recent system activity</p>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach (array_slice($recentSystemActivities, 0, 10) as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong><?= htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?></strong>
                            <small><?= getActivityDescription($activity['action']) ?></small>
                            <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $activity['role_name'])) ?>">
                                    <?= htmlspecialchars($activity['role_name']) ?>
                                </span>
                                • <?= timeAgo($activity['created_at']) ?>
                            </div>
                        </div>
                        <div class="activity-icon">
                            <i class="fas fa-<?= getActivityIcon($activity['action']) ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
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

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-healthy {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
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

    .activity-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .activity-item:last-child {
        border-bottom: none;
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

    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary-blue);
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

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .grid {
            grid-template-columns: 1fr !important;
        }
        
        .branch-metrics {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Role management handlers
    document.querySelectorAll('.btn-role-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const roleId = this.dataset.roleId;
            showNotification(`Role editing for role ID ${roleId} - Feature coming soon!`, 'info');
        });
    });
    
    document.querySelectorAll('.btn-role-permissions').forEach(btn => {
        btn.addEventListener('click', function() {
            const roleId = this.dataset.roleId;
            showNotification(`Permission management for role ID ${roleId} - Feature coming soon!`, 'info');
        });
    });
});

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
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>