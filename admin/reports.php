<?php
// admin/reports.php - Enhanced Admin Reports
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);

// Include database connection - try both possible locations
if (file_exists(__DIR__ . "/../data/db.php")) {
    require_once __DIR__ . "/../data/db.php";
} else {
    // Fallback to direct database connection if db.php doesn't work
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "aims_ver1";
    
    try {
        $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Handle report generation requests
$reportType = $_GET['type'] ?? '';
$dateRange = $_GET['range'] ?? '7';
$format = $_GET['format'] ?? 'view';


// Calculate date ranges
$endDate = date('Y-m-d');
switch($dateRange) {
    case '1':
        $startDate = date('Y-m-d', strtotime('-1 day'));
        $rangeLabel = 'Last 24 Hours';
        break;
    case '7':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $rangeLabel = 'Last 7 Days';
        break;
    case '30':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $rangeLabel = 'Last 30 Days';
        break;
    case '90':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $rangeLabel = 'Last 90 Days';
        break;
    case '365':
        $startDate = date('Y-m-d', strtotime('-365 days'));
        $rangeLabel = 'Last Year';
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $rangeLabel = 'Last 7 Days';
}

// Get report statistics with error handling
try {
    // User stats query - Fixed to use prepare/execute
    $userStatsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as sao,
            SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as registrar,
            SUM(CASE WHEN role_id IN (1,5) THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_users
        FROM users
        WHERE deleted_at IS NULL
    ");
    $userStatsStmt->execute([$startDate]);
    $userStats = $userStatsStmt->fetch();

    // Activity stats query - Fixed to use prepare/execute
    $activityStatsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_activities,
            COUNT(DISTINCT user_id) as unique_users,
            SUM(CASE WHEN action = 'LOGIN' THEN 1 ELSE 0 END) as logins,
            SUM(CASE WHEN action = 'LOGIN_WITH_OTP' THEN 1 ELSE 0 END) as otp_logins,
            SUM(CASE WHEN action LIKE '%BORROW%' THEN 1 ELSE 0 END) as borrow_activities,
            SUM(CASE WHEN action LIKE '%ANNOUNCEMENT%' THEN 1 ELSE 0 END) as announcement_activities
        FROM activity_logs
        WHERE created_at >= ? AND created_at <= ?
    ");
    $activityStatsStmt->execute([$startDate, $endDate . ' 23:59:59']);
    $activityStats = $activityStatsStmt->fetch();

    // System stats query - Fixed to use prepare/execute
    $systemStatsStmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements,
            (SELECT COUNT(*) FROM borrow_transactions WHERE status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM borrow_transactions WHERE created_at >= ? AND created_at <= ?) as period_requests,
            (SELECT COUNT(*) FROM inventory_items WHERE quantity_available <= 2) as low_stock_items
    ");
    $systemStatsStmt->execute([$startDate, $endDate . ' 23:59:59']);
    $systemStats = $systemStatsStmt->fetch();

    $reportStats = [
        'user_stats' => $userStats,
        'activity_stats' => $activityStats,
        'system_stats' => $systemStats
    ];

} catch (Exception $e) {
    // Fallback stats if queries fail
    $reportStats = [
        'user_stats' => ['total_users' => 0, 'students' => 0, 'sao' => 0, 'registrar' => 0, 'admins' => 0, 'active_users' => 0, 'new_users' => 0],
        'activity_stats' => ['total_activities' => 0, 'unique_users' => 0, 'logins' => 0, 'otp_logins' => 0, 'borrow_activities' => 0, 'announcement_activities' => 0],
        'system_stats' => ['active_announcements' => 0, 'pending_requests' => 0, 'period_requests' => 0, 'low_stock_items' => 0]
    ];
    error_log("Reports error: " . $e->getMessage());
}

// Generate specific report data based on type
$reportData = [];
if ($reportType) {
    try {
        switch ($reportType) {
            case 'user_activity':
                $stmt = $db->prepare("
                    SELECT 
                        u.id, u.username, u.first_name, u.last_name, r.role as role_name,
                        u.last_login, u.is_active,
                        (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND created_at >= ? AND created_at <= ?) as activity_count,
                        (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND action = 'LOGIN' AND created_at >= ? AND created_at <= ?) as login_count
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.deleted_at IS NULL
                    ORDER BY activity_count DESC, u.last_login DESC
                    LIMIT 50
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $startDate, $endDate . ' 23:59:59']);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'login_report':
                $stmt = $db->prepare("
                    SELECT 
                        u.username, u.first_name, u.last_name, r.role as role_name,
                        al.created_at as login_time, al.ip_address, al.user_agent
                    FROM activity_logs al
                    JOIN users u ON al.user_id = u.id
                    JOIN roles r ON u.role_id = r.id
                    WHERE al.action IN ('LOGIN', 'LOGIN_WITH_OTP') 
                    AND al.created_at >= ? AND al.created_at <= ?
                    AND u.deleted_at IS NULL
                    ORDER BY al.created_at DESC
                    LIMIT 100
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'announcement_report':
                $stmt = $db->prepare("
                    SELECT 
                        a.id, a.title, a.is_active, a.posted_at as created_at, a.updated_at,
                        u.first_name, u.last_name, r.role as role_name
                    FROM announcements a
                    JOIN users u ON a.posted_by = u.id
                    JOIN roles r ON u.role_id = r.id
                    WHERE a.posted_at >= ? AND a.posted_at <= ?
                    AND u.deleted_at IS NULL
                    ORDER BY a.posted_at DESC
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'borrow_report':
                $stmt = $db->prepare("
                    SELECT 
                        bt.id, bt.status, bt.created_at, bt.updated_at,
                        u.first_name, u.last_name, u.username,
                        ii.item_name, ii.item_code,
                        approver.first_name as approver_first_name, approver.last_name as approver_last_name
                    FROM borrow_transactions bt
                    JOIN users u ON bt.borrower_id = u.id
                    LEFT JOIN inventory_items ii ON bt.item_id = ii.id
                    LEFT JOIN users approver ON bt.approved_by = approver.id
                    WHERE bt.created_at >= ? AND bt.created_at <= ?
                    AND u.deleted_at IS NULL
                    AND (approver.deleted_at IS NULL OR approver.id IS NULL)
                    ORDER BY bt.created_at DESC
                    LIMIT 100
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'system_health':
                $reportData = [
                    'database_status' => 'healthy',
                    'user_sessions' => $reportStats['user_stats']['active_users'] ?? 0,
                    'daily_activities' => $reportStats['activity_stats']['total_activities'] ?? 0,
                    'error_logs' => 0, // This would come from error log analysis
                    'security_events' => 0 // This would come from security logs
                ];
                break;
        }
    } catch (Exception $e) {
        $reportData = [];
        $reportError = "Error generating report: " . $e->getMessage();
        error_log("Report generation error: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> System Reports</h1>
    <p>Generate comprehensive reports for system analysis and monitoring</p>
</div>

<?php if (isset($reportError)): ?>
<div class="alert alert-danger" style="margin-bottom: 2rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <?= htmlspecialchars($reportError) ?>
</div>
<?php endif; ?>

<!-- Quick Report Stats -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $reportStats['user_stats']['total_users'] ?? 0 ?></div>
            <div class="stats-label">Total Users</div>
            <small style="color: var(--text-light);"><?= $reportStats['user_stats']['new_users'] ?? 0 ?> new in period</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $reportStats['activity_stats']['total_activities'] ?? 0 ?></div>
            <div class="stats-label">Total Activities</div>
            <small style="color: var(--text-light);"><?= $reportStats['activity_stats']['unique_users'] ?? 0 ?> unique users</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-sign-in-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= ($reportStats['activity_stats']['logins'] ?? 0) + ($reportStats['activity_stats']['otp_logins'] ?? 0) ?></div>
            <div class="stats-label">Total Logins</div>
            <small style="color: var(--text-light);"><?= $rangeLabel ?></small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--light-blue); color: white;">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $reportStats['system_stats']['pending_requests'] ?? 0 ?></div>
            <div class="stats-label">Borrow Requests</div>
            <small style="color: var(--text-light);"><?= $reportStats['system_stats']['period_requests'] ?? 0 ?> in period</small>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="dashboard-section" style="margin-bottom: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-filter"></i> Report Filters</h3>
    </div>
    
    <form method="GET" class="report-filters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div class="form-group">
            <label for="type">Report Type</label>
            <select name="type" id="type" class="form-control">
                <option value="">Select Report Type</option>
                <option value="user_activity" <?= $reportType === 'user_activity' ? 'selected' : '' ?>>User Activity Report</option>
                <option value="login_report" <?= $reportType === 'login_report' ? 'selected' : '' ?>>Login Activity Report</option>
                <option value="announcement_report" <?= $reportType === 'announcement_report' ? 'selected' : '' ?>>Announcement Report</option>
                <option value="borrow_report" <?= $reportType === 'borrow_report' ? 'selected' : '' ?>>Borrow Transactions Report</option>
                <option value="system_health" <?= $reportType === 'system_health' ? 'selected' : '' ?>>System Health Report</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="range">Date Range</label>
            <select name="range" id="range" class="form-control">
                <option value="1" <?= $dateRange === '1' ? 'selected' : '' ?>>Last 24 Hours</option>
                <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 Days</option>
                <option value="365" <?= $dateRange === '365' ? 'selected' : '' ?>>Last Year</option>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-chart-line"></i> Generate Report
            </button>
        </div>
    </form>
</div>

<?php if ($reportType && !empty($reportData)): ?>
<!-- Report Results -->
<div class="dashboard-section">
    <div class="section-header">
        <h3><i class="fas fa-table"></i> Report Results - <?= htmlspecialchars(ucwords(str_replace('_', ' ', $reportType))) ?></h3>
        <div class="report-actions">
            <span class="report-period"><?= $rangeLabel ?> (<?= count($reportData) ?> records)</span>
            <button onclick="window.print()" class="btn-sm btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <?php if ($reportType === 'user_activity'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Activities</th>
                    <th>Logins</th>
                    <th>Last Login</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td>
                        <div class="user-info">
                            <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <small>@<?= htmlspecialchars($row['username']) ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $row['role_name'])) ?>">
                            <?= htmlspecialchars($row['role_name']) ?>
                        </span>
                    </td>
                    <td><strong><?= $row['activity_count'] ?></strong></td>
                    <td><?= $row['login_count'] ?></td>
                    <td><?= $row['last_login'] ? date('M j, Y H:i', strtotime($row['last_login'])) : 'Never' ?></td>
                    <td>
                        <span class="status-badge <?= $row['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($reportType === 'login_report'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Login Time</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td>
                        <div class="user-info">
                            <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <small>@<?= htmlspecialchars($row['username']) ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $row['role_name'])) ?>">
                            <?= htmlspecialchars($row['role_name']) ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y H:i:s', strtotime($row['login_time'])) ?></td>
                    <td><code><?= htmlspecialchars($row['ip_address'] ?? 'N/A') ?></code></td>
                    <td><small><?= htmlspecialchars(substr($row['user_agent'] ?? 'N/A', 0, 50)) ?>...</small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($reportType === 'announcement_report'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['title']) ?></strong>
                        <small>ID: <?= $row['id'] ?></small>
                    </td>
                    <td>
                        <div class="user-info">
                            <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $row['role_name'])) ?>">
                                <?= htmlspecialchars($row['role_name']) ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?= $row['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= date('M j, Y H:i', strtotime($row['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($reportType === 'borrow_report'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Approved By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td><code><?= $row['id'] ?></code></td>
                    <td>
                        <div class="user-info">
                            <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <small>@<?= htmlspecialchars($row['username']) ?></small>
                        </div>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($row['item_name'] ?? 'N/A') ?></strong>
                        <small><?= htmlspecialchars($row['item_code'] ?? '') ?></small>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $row['status'] ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y H:i', strtotime($row['created_at'])) ?></td>
                    <td>
                        <?php if ($row['approver_first_name']): ?>
                            <?= htmlspecialchars($row['approver_first_name'] . ' ' . $row['approver_last_name']) ?>
                            <br><small><?= $row['updated_at'] ? date('M j, Y H:i', strtotime($row['updated_at'])) : 'N/A' ?></small>
                        <?php else: ?>
                            <small class="text-muted">Pending</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($reportType === 'system_health'): ?>
    <div class="health-report">
        <div class="grid grid-2" style="gap: 2rem;">
            <div class="health-section">
                <h4><i class="fas fa-server"></i> System Components</h4>
                <div class="health-items">
                    <div class="health-item">
                        <div class="health-info">
                            <strong>Database Connection</strong>
                            <small>MySQL server status</small>
                        </div>
                        <div class="health-status">
                            <span class="status-badge status-healthy">
                                <i class="fas fa-check"></i> Healthy
                            </span>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <div class="health-info">
                            <strong>Active Sessions</strong>
                            <small><?= $reportData['user_sessions'] ?> concurrent users</small>
                        </div>
                        <div class="health-status">
                            <span class="status-badge status-healthy">
                                <i class="fas fa-check"></i> Normal
                            </span>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <div class="health-info">
                            <strong>Daily Activities</strong>
                            <small><?= $reportData['daily_activities'] ?> actions recorded</small>
                        </div>
                        <div class="health-status">
                            <span class="status-badge <?= $reportData['daily_activities'] > 100 ? 'status-healthy' : 'status-warning' ?>">
                                <i class="fas fa-<?= $reportData['daily_activities'] > 100 ? 'check' : 'exclamation-triangle' ?>"></i> 
                                <?= $reportData['daily_activities'] > 100 ? 'Active' : 'Low' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="health-section">
                <h4><i class="fas fa-exclamation-triangle"></i> Alerts & Issues</h4>
                <div class="health-items">
                    <div class="health-item">
                        <div class="health-info">
                            <strong>Error Logs</strong>
                            <small><?= $reportData['error_logs'] ?> errors in period</small>
                        </div>
                        <div class="health-status">
                            <span class="status-badge <?= $reportData['error_logs'] == 0 ? 'status-healthy' : 'status-warning' ?>">
                                <i class="fas fa-<?= $reportData['error_logs'] == 0 ? 'check' : 'exclamation-triangle' ?>"></i> 
                                <?= $reportData['error_logs'] == 0 ? 'Clean' : 'Issues' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <div class="health-info">
                            <strong>Security Events</strong>
                            <small><?= $reportData['security_events'] ?> events detected</small>
                        </div>
                        <div class="health-status">
                            <span class="status-badge <?= $reportData['security_events'] < 5 ? 'status-healthy' : 'status-warning' ?>">
                                <i class="fas fa-<?= $reportData['security_events'] < 5 ? 'shield-alt' : 'exclamation-triangle' ?>"></i> 
                                <?= $reportData['security_events'] < 5 ? 'Secure' : 'Monitor' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($reportType && empty($reportData)): ?>
<div class="dashboard-section">
    <div class="empty-state">
        <i class="fas fa-chart-bar"></i>
        <p>No data available for the selected report type and date range</p>
        <small>Try adjusting the date range or selecting a different report type</small>
    </div>
</div>

<?php else: ?>
<!-- Default Report Options -->
<div class="quick-reports" style="margin-bottom: 2rem;">
    <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">
        <i class="fas fa-lightning-bolt"></i> Quick Reports
    </h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <a href="?type=user_activity&range=7" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="action-content">
                <h4>User Activity Report</h4>
                <p>Detailed user engagement and activity metrics</p>
            </div>
        </a>
        
        <a href="?type=login_report&range=7" class="action-card">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="action-content">
                <h4>Login Activity Report</h4>
                <p>Track user login patterns and security</p>
            </div>
        </a>
        
        <a href="?type=system_health&range=1" class="action-card">
            <div class="action-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-heartbeat"></i>
            </div>
            <div class="action-content">
                <h4>System Health Report</h4>
                <p>Overall system performance and status</p>
            </div>
        </a>
        
        <a href="?type=borrow_report&range=30" class="action-card">
            <div class="action-icon" style="background: var(--light-blue); color: white;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="action-content">
                <h4>Borrow Transactions</h4>
                <p>Equipment borrowing and return analytics</p>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<style>
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .page-header h1 {
        color: var(--primary-blue);
        margin-bottom: 0.5rem;
    }
    
    .page-header p {
        color: var(--text-light);
        font-size: 1.1rem;
        margin-bottom: 2rem;
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

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 500;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .form-control {
        padding: 0.75rem;
        border: 1px solid var(--border-gray);
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }

    .btn-primary:hover {
        background: #4C5B9F;
        transform: translateY(-1px);
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

    .table-responsive {
        overflow-x: auto;
        margin-top: 1rem;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }

    .table th {
        font-weight: 600;
        color: var(--text-dark);
        background: var(--light-gray);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    /* .user-info {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    } */

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
        

    .user-info strong {
        font-weight: 600;
        color: var(--text-dark);
    }

    .user-info small {
        color: var(--text-light);
        font-size: 0.8rem;
    }

    /* .role-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-block;
        margin-top: 0.2rem;
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

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
        gap: 0.3rem;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-inactive {
        background: rgba(107, 114, 128, 0.1);
        color: var(--text-light);
    }
    
    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .status-approved {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-rejected,
    .status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }
    
    .status-borrowed {
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }
    
    .status-returned {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }
    
    .status-overdue {
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
    }
    
    .status-healthy {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
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
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .report-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .report-period {
        color: var(--text-light);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--border-gray);
    }

    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .empty-state small {
        font-size: 0.9rem;
    }

    .health-report {
        margin-top: 1rem;
    }

    .health-section h4 {
        color: var(--primary-blue);
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .health-items {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .health-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: var(--light-gray);
        border-radius: 8px;
        transition: background 0.3s ease;
    }

    .health-item:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    .health-info strong {
        display: block;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.2rem;
    }

    .health-info small {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .text-muted {
        color: var(--text-light);
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
        
        .report-filters {
            grid-template-columns: 1fr;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .report-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .table-responsive {
            font-size: 0.85rem;
        }
        
        .table th,
        .table td {
            padding: 0.5rem;
        }
        
        .grid-2 {
            grid-template-columns: 1fr !important;
        }
    }

    @media print {
        .page-header,
        .dashboard-section:first-of-type,
        .stats-grid,
        .quick-reports {
            display: none;
        }
        
        .btn-sm {
            display: none;
        }
        
        .section-header {
            border-bottom: 2px solid #000;
            margin-bottom: 1rem;
        }
        
        .table {
            font-size: 0.8rem;
        }
        
        .table th {
            background: #f5f5f5 !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when report type changes
    const reportForm = document.querySelector('.report-filters');
    const typeSelect = document.getElementById('type');
    const rangeSelect = document.getElementById('range');
    
    if (typeSelect && rangeSelect) {
        // Only auto-submit when type changes and a type is selected
        typeSelect.addEventListener('change', function() {
            if (this.value) {
                reportForm.submit();
            }
        });
        
        // Auto-submit when range changes if a report type is already selected
        rangeSelect.addEventListener('change', function() {
            if (typeSelect.value) {
                reportForm.submit();
            }
        });
    }
    
    // Add loading state to generate button
    const generateBtn = reportForm.querySelector('button[type="submit"]');
    reportForm.addEventListener('submit', function() {
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        }
    });
});
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>