<?php
// admin/activity_logs.php - Enhanced Activity Logs Management
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);
require __DIR__ . "/../shared/header.php";
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Add the missing helper function that doesn't conflict with db.php
function execute($query, $params = []) {
    return executeUpdate($query, $params);
}

// Add the missing logActivity function
function logActivity($userId, $action, $description = '', $ipAddress = null) {
    $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = "INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
    return execute($query, [$userId, $action, $ipAddress, $userAgent]);
}

// Pagination settings
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Filter parameters
$filterUser = $_GET['user'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build filter conditions - FIXED: Use role instead of role_name
$whereConditions = ["1=1"];
$params = [];

if ($filterUser) {
    $whereConditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}

if ($filterRole) {
    $whereConditions[] = "r.role = ?";
    $params[] = $filterRole;
}

if ($filterAction) {
    $whereConditions[] = "al.action = ?";
    $params[] = $filterAction;
}

if ($filterDate) {
    $whereConditions[] = "DATE(al.created_at) = ?";
    $params[] = $filterDate;
}

if ($searchQuery) {
    $whereConditions[] = "(al.ip_address LIKE ? OR u.username LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination - FIXED: Use role instead of role_name
$totalQuery = "
    SELECT COUNT(*) as total
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE $whereClause
";

$totalResult = fetchOne($totalQuery, $params);
$totalRecords = $totalResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

// Get activity logs with user information - FIXED: Use role instead of role_name
$logsQuery = "
    SELECT 
        al.id, al.user_id, al.action, 
        al.ip_address, al.user_agent, al.created_at,
        u.username, u.first_name, u.last_name, u.is_active,
        r.role as role_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE $whereClause
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
";

$activityLogs = fetchAll($logsQuery, $params);

// Get filter options for dropdowns - FIXED: Use role instead of role_name
$availableRoles = fetchAll("SELECT DISTINCT r.role FROM roles r JOIN users u ON r.id = u.role_id ORDER BY r.role");
$availableActions = fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");

// Get activity statistics for the current filters
$statsQuery = "
    SELECT 
        COUNT(*) as total_activities,
        COUNT(DISTINCT al.user_id) as unique_users,
        SUM(CASE WHEN al.action LIKE '%LOGIN%' THEN 1 ELSE 0 END) as login_activities,
        SUM(CASE WHEN al.action LIKE '%BORROW%' THEN 1 ELSE 0 END) as borrow_activities,
        SUM(CASE WHEN al.action LIKE '%ANNOUNCEMENT%' THEN 1 ELSE 0 END) as announcement_activities,
        SUM(CASE WHEN DATE(al.created_at) = CURDATE() THEN 1 ELSE 0 END) as today_activities,
        MIN(al.created_at) as earliest_activity,
        MAX(al.created_at) as latest_activity
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE $whereClause
";

$activityStats = fetchOne($statsQuery, $params);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selectedLogs = $_POST['selected_logs'] ?? [];
    $bulkAction = $_POST['bulk_action'];
    
    if (!empty($selectedLogs) && $bulkAction === 'delete') {
        $placeholders = str_repeat('?,', count($selectedLogs) - 1) . '?';
        $deleteQuery = "DELETE FROM activity_logs WHERE id IN ($placeholders)";
        
        if (execute($deleteQuery, $selectedLogs)) {
            $successMessage = count($selectedLogs) . " activity log(s) deleted successfully.";
            // Log this admin action
            logActivity($userId, 'BULK_DELETE_ACTIVITY_LOGS', "Deleted " . count($selectedLogs) . " activity logs");
        } else {
            $errorMessage = "Failed to delete selected activity logs.";
        }
    }
}

// Handle individual log deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    
    if (execute("DELETE FROM activity_logs WHERE id = ?", [$deleteId])) {
        $successMessage = "Activity log deleted successfully.";
        logActivity($userId, 'DELETE_ACTIVITY_LOG', "Deleted activity log ID: $deleteId");
    } else {
        $errorMessage = "Failed to delete activity log.";
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-history"></i> Activity Logs</h1>
    <p>Monitor and manage system activity logs and user actions</p>
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

<!-- Activity Statistics -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($activityStats['total_activities'] ?? 0) ?></div>
            <div class="stats-label">Total Activities</div>
            <small style="color: var(--text-light);"><?= $activityStats['unique_users'] ?? 0 ?> unique users</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-sign-in-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $activityStats['login_activities'] ?? 0 ?></div>
            <div class="stats-label">Login Activities</div>
            <small style="color: var(--text-light);">Authentication events</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $activityStats['today_activities'] ?? 0 ?></div>
            <div class="stats-label">Today's Activities</div>
            <small style="color: var(--text-light);">Current day events</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--light-blue); color: white;">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $activityStats['borrow_activities'] ?? 0 ?></div>
            <div class="stats-label">Borrow Activities</div>
            <small style="color: var(--text-light);">Equipment transactions</small>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="dashboard-section" style="margin-bottom: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-filter"></i> Filter & Search Logs</h3>
        <div class="filter-info">
            <span class="filter-badge">Showing <?= number_format(count($activityLogs)) ?> of <?= number_format($totalRecords) ?> logs</span>
        </div>
    </div>
    
    <form method="GET" class="filters-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div class="form-group">
            <label for="search">Search Logs</label>
            <input type="text" name="search" id="search" class="form-control" 
                   placeholder="Search IP address, username..." 
                   value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        
        <div class="form-group">
            <label for="user">User</label>
            <input type="text" name="user" id="user" class="form-control" 
                   placeholder="Username or name..." 
                   value="<?= htmlspecialchars($filterUser) ?>">
        </div>
        
        <div class="form-group">
            <label for="role">Role</label>
            <select name="role" id="role" class="form-control">
                <option value="">All Roles</option>
                <?php foreach ($availableRoles as $role): ?>
                    <option value="<?= htmlspecialchars($role['role']) ?>" 
                            <?= $filterRole === $role['role'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['role']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="action">Action</label>
            <select name="action" id="action" class="form-control">
                <option value="">All Actions</option>
                <?php foreach ($availableActions as $action): ?>
                    <option value="<?= htmlspecialchars($action['action']) ?>" 
                            <?= $filterAction === $action['action'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($action['action']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" name="date" id="date" class="form-control" 
                   value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
        
        <div class="form-group">
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary" style="width: 100%; text-align: center; text-decoration: none;">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Activity Logs Table -->
<div class="dashboard-section">
    <div class="section-header">
        <h3><i class="fas fa-list"></i> Activity Log Entries</h3>
        <div class="table-actions">
            <?php if ($userRole === 'Super Admin'): ?>
                <button id="bulk-delete-btn" class="btn-sm btn-danger" style="display: none;">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            <?php endif; ?>
            <button id="export-btn" class="btn-sm btn-primary">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <?php if (empty($activityLogs)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No activity logs found</p>
            <small>Try adjusting your search filters or date range</small>
        </div>
    <?php else: ?>
        <form id="bulk-form" method="POST">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($userRole === 'Super Admin'): ?>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="select-all">
                                </th>
                            <?php endif; ?>
                            <th>User</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <?php if ($userRole === 'Super Admin'): ?>
                                    <td>
                                        <input type="checkbox" name="selected_logs[]" value="<?= $log['id'] ?>" class="log-checkbox">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="user-info">
                                        <div class="user-details">
                                            <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong>
                                            <small>@<?= htmlspecialchars($log['username']) ?></small>
                                        </div>
                                        <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $log['role_name'])) ?>">
                                            <?= htmlspecialchars($log['role_name']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-badge action-<?= strtolower(str_replace('_', '-', $log['action'])) ?>">
                                        <i class="fas fa-<?= getActivityIcon($log['action']) ?>"></i>
                                        <?= htmlspecialchars($log['action']) ?>
                                    </div>
                                </td>
                                <td>
                                    <code class="ip-address"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                </td>
                                <td>
                                    <div class="datetime-info">
                                        <strong><?= date('M j, Y', strtotime($log['created_at'])) ?></strong>
                                        <small><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        <div class="time-ago"><?= timeAgo($log['created_at']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-sm btn-info view-details" 
                                                data-log='<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($userRole === 'Super Admin'): ?>
                                            <a href="?delete_id=<?= $log['id'] ?>" 
                                               class="btn-sm btn-danger delete-log" 
                                               onclick="return confirm('Are you sure you want to delete this log entry?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($userRole === 'Super Admin'): ?>
                <input type="hidden" name="bulk_action" value="delete">
            <?php endif; ?>
        </form>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $limit, $totalRecords)) ?> 
                of <?= number_format($totalRecords) ?> entries
            </div>
            
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Log Details Modal -->
<div id="log-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Activity Log Details</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="log-details"></div>
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
        'DELETE_ANNOUNCEMENT' => 'trash',
        'BULK_DELETE_ACTIVITY_LOGS' => 'trash-alt',
        'DELETE_ACTIVITY_LOG' => 'trash'
    ];
    return $icons[$action] ?? 'circle';
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

    .filter-badge {
        background: var(--light-gray);
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.8rem;
        color: var(--text-light);
        font-weight: 500;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 500;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
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
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }

    .btn-secondary {
        background: var(--light-gray);
        color: var(--text-dark);
    }

    .btn:hover {
        transform: translateY(-1px);
        text-decoration: none;
    }

    .btn-primary:hover {
        background: #4C5B9F;
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
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
    
    .btn-primary.btn-sm {
        background: var(--primary-blue);
        color: white;
    }

    .btn-danger {
        background: var(--error);
        color: white;
    }

    .btn-info {
        background: var(--light-blue);
        color: white;
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
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
        transform: translateY(-1px);
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

    .table-responsive {
        overflow-x: auto;
        margin-top: 1rem;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        font-size: 0.9rem;
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
        font-size: 0.85rem;
    }

    .table tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    /* .user-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    } */

            .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-details strong {
        font-weight: 600;
        color: var(--text-dark);
        display: block;
    }

    .user-details small {
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

    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--light-gray);
        color: var(--text-dark);
        max-width: 150px;
    }

    .action-login,
    .action-login-with-otp {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .action-logout {
        background: rgba(107, 114, 128, 0.1);
        color: var(--text-light);
    }

    .action-create-borrow-request {
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }

    .action-approve-borrow-request {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .action-reject-borrow-request {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }

    .log-description {
        color: var(--text-dark);
        font-size: 0.85rem;
        max-width: 200px;
        word-wrap: break-word;
    }

    .ip-address {
        background: var(--light-gray);
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        color: var(--text-dark);
        font-family: 'Courier New', monospace;
    }

    .datetime-info {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }

    .datetime-info strong {
        font-weight: 600;
        color: var(--text-dark);
    }

    .datetime-info small {
        color: var(--text-light);
        font-size: 0.8rem;
    }

    .time-ago {
        font-size: 0.75rem;
        color: var(--text-light);
        font-style: italic;
    }

    .action-buttons {
        display: flex;
        gap: 0.3rem;
    }

    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-gray);
    }

    .pagination-info {
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .pagination {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .page-btn {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-gray);
        border-radius: 6px;
        color: var(--text-dark);
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .page-btn:hover {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
        text-decoration: none;
    }

    .page-btn.active {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-gray);
    }

    .modal-header h3 {
        margin: 0;
        color: var(--primary-blue);
        font-size: 1.2rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: var(--light-gray);
        color: var(--text-dark);
    }

    .modal-body {
        padding: 1.5rem;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filters-form {
            grid-template-columns: 1fr;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .table-actions {
            margin-top: 0.5rem;
        }
        
        .pagination-container {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .table-responsive {
            font-size: 0.8rem;
        }
        
        .table th,
        .table td {
            padding: 0.5rem;
        }
        
        .action-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            max-width: 120px;
        }
        
        .user-info,
        .datetime-info {
            gap: 0.1rem;
        }
        
        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    const selectAllCheckbox = document.getElementById('select-all');
    const logCheckboxes = document.querySelectorAll('.log-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkForm = document.getElementById('bulk-form');

    if (selectAllCheckbox && logCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            logCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleBulkActions();
        });

        logCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', toggleBulkActions);
        });
    }

    function toggleBulkActions() {
        const checkedBoxes = document.querySelectorAll('.log-checkbox:checked');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.style.display = checkedBoxes.length > 0 ? 'inline-flex' : 'none';
        }
    }

    // Bulk delete functionality
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const checkedBoxes = document.querySelectorAll('.log-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one log entry to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${checkedBoxes.length} selected log entries? This action cannot be undone.`)) {
                bulkForm.submit();
            }
        });
    }

    // View details functionality
    const viewButtons = document.querySelectorAll('.view-details');
    const modal = document.getElementById('log-modal');
    const modalClose = document.querySelector('.modal-close');
    const logDetailsContainer = document.getElementById('log-details');

    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const logData = JSON.parse(this.dataset.log);
            showLogDetails(logData);
        });
    });

    function showLogDetails(log) {
        const detailsHtml = `
            <div class="log-detail-grid" style="display: grid; gap: 1rem;">
                <div class="detail-group">
                    <strong>Log ID:</strong>
                    <span>${log.id}</span>
                </div>
                <div class="detail-group">
                    <strong>User:</strong>
                    <span>${log.first_name} ${log.last_name} (@${log.username})</span>
                </div>
                <div class="detail-group">
                    <strong>Role:</strong>
                    <span class="role-badge role-${log.role_name.toLowerCase().replace(' ', '-')}">${log.role_name}</span>
                </div>
                <div class="detail-group">
                    <strong>Action:</strong>
                    <span class="action-badge action-${log.action.toLowerCase().replace('_', '-')}">
                        <i class="fas fa-${getActionIcon(log.action)}"></i> ${log.action}
                    </span>
                </div>
                <div class="detail-group">
                    <strong>IP Address:</strong>
                    <code>${log.ip_address || 'N/A'}</code>
                </div>
                <div class="detail-group">
                    <strong>User Agent:</strong>
                    <small style="word-break: break-all;">${log.user_agent || 'N/A'}</small>
                </div>
                <div class="detail-group">
                    <strong>Date & Time:</strong>
                    <span>${new Date(log.created_at).toLocaleString()}</span>
                </div>
            </div>
        `;
        
        logDetailsContainer.innerHTML = detailsHtml;
        modal.style.display = 'flex';
    }

    // Modal close functionality
    if (modalClose) {
        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Export functionality
    const exportBtn = document.getElementById('export-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = '?' + params.toString();
        });
    }

    function getActionIcon(action) {
        const icons = {
            'LOGIN': 'sign-in-alt',
            'LOGIN_WITH_OTP': 'sign-in-alt',
            'LOGOUT': 'sign-out-alt',
            'CREATE_BORROW_REQUEST': 'plus-circle',
            'CREATE_ANNOUNCEMENT': 'bullhorn',
            'APPROVE_BORROW_REQUEST': 'check',
            'REJECT_BORROW_REQUEST': 'times',
            'PASSWORD_RESET': 'key',
            'TOGGLE_ANNOUNCEMENT_STATUS': 'toggle-on',
            'DELETE_ANNOUNCEMENT': 'trash',
            'BULK_DELETE_ACTIVITY_LOGS': 'trash-alt',
            'DELETE_ACTIVITY_LOG': 'trash'
        };
        return icons[action] || 'circle';
    }
});
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>