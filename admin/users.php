<?php
// admin/users.php - Comprehensive User Management
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);
require __DIR__ . "/../shared/header.php";

$userRole = $_SESSION['role_name'];
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $result = createNewUser($_POST);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'update_user') {
        $result = updateUser($_POST);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'suspend_user') {
        $result = suspendUser($_POST['user_id'], $_POST['reason'] ?? '');
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'activate_user') {
        $result = activateUser($_POST['user_id']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'delete_user') {
        $result = deleteUser($_POST['user_id']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Build where clause - FIXED: Added check to exclude deleted users
$whereConditions = ['u.deleted_at IS NULL']; // Only show non-deleted users
$params = [];

if ($roleFilter) {
    $whereConditions[] = "r.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter) {
    if ($statusFilter === 'active') {
        $whereConditions[] = "u.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "u.is_active = 0";
    }
}

if ($searchQuery) {
    $whereConditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Get users with pagination - FIXED: Use role instead of role_name
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$users = fetchAll("
    SELECT u.*, r.role as role_name, r.id as role_level,
           DATE(u.created_at) as join_date,
           DATE(u.last_login) as last_login_date,
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND DATE(created_at) = CURDATE()) as daily_activities
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE {$whereClause}
    ORDER BY {$sortBy} {$sortOrder}
    LIMIT {$limit} OFFSET {$offset}
", $params);

$totalUsers = fetchOne("
    SELECT COUNT(*) as count
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE {$whereClause}
", $params)['count'] ?? 0;

$totalPages = ceil($totalUsers / $limit);

// Get roles for dropdown
$roles = fetchAll("SELECT * FROM roles ORDER BY id");

// Get user statistics - FIXED: Use is_active instead of status and exclude deleted users
$userStats = fetchOne("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_month,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_week
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.deleted_at IS NULL
", []);

// User management functions
function createNewUser($data) {
    try {
        // Validate input
        $required = ['username', 'email', 'first_name', 'last_name', 'role_id', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field '{$field}' is required."];
            }
        }
        
        // Check if username or email already exists (including deleted users)
        $existing = fetchOne("SELECT id, deleted_at FROM users WHERE username = ? OR email = ?", 
                            [$data['username'], $data['email']]);
        if ($existing) {
            if ($existing['deleted_at']) {
                return ['success' => false, 'message' => 'Username or email was previously used by a deleted account. Please use different credentials.'];
            } else {
                return ['success' => false, 'message' => 'Username or email already exists.'];
            }
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $result = executeUpdate("
            INSERT INTO users (username, email, first_name, last_name, role_id, password, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ", [
            $data['username'],
            $data['email'], 
            $data['first_name'],
            $data['last_name'],
            $data['role_id'],
            $hashedPassword
        ]);
        
        if ($result) {
            // Log activity
            ActivityLogger::log($_SESSION['user_id'], 'CREATE_USER', 'users', getLastInsertId(), null, [
                'created_user' => $data['username']
            ]);
            return ['success' => true, 'message' => 'User created successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to create user.'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()];
    }
}

function updateUser($data) {
    try {
        $userId = $data['user_id'];
        
        // Check if user exists and is not deleted
        $userCheck = fetchOne("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$userCheck) {
            return ['success' => false, 'message' => 'User not found or has been deleted.'];
        }
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        $updateFields = ['first_name', 'last_name', 'email', 'role_id'];
        foreach ($updateFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No fields to update.'];
        }
        
        $params[] = $userId;
        $updateClause = implode(', ', $updates);
        
        $result = executeUpdate("UPDATE users SET {$updateClause}, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL", $params);
        
        if ($result) {
            ActivityLogger::log($_SESSION['user_id'], 'UPDATE_USER', 'users', $userId);
            return ['success' => true, 'message' => 'User updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update user or user not found.'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()];
    }
}

function suspendUser($userId, $reason = '') {
    try {
        // Check if user exists and is not deleted
        $userCheck = fetchOne("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$userCheck) {
            return ['success' => false, 'message' => 'User not found or has been deleted.'];
        }
        
        $result = executeUpdate("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$userId]);
        
        if ($result) {
            ActivityLogger::log($_SESSION['user_id'], 'SUSPEND_USER', 'users', $userId, null, [
                'reason' => $reason
            ]);
            return ['success' => true, 'message' => 'User suspended successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to suspend user or user not found.'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error suspending user: ' . $e->getMessage()];
    }
}

function activateUser($userId) {
    try {
        // Check if user exists and is not deleted
        $userCheck = fetchOne("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$userCheck) {
            return ['success' => false, 'message' => 'User not found or has been deleted.'];
        }
        
        $result = executeUpdate("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$userId]);
        
        if ($result) {
            ActivityLogger::log($_SESSION['user_id'], 'ACTIVATE_USER', 'users', $userId);
            return ['success' => true, 'message' => 'User activated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to activate user or user not found.'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error activating user: ' . $e->getMessage()];
    }
}

// FIXED: Proper soft delete function that works with your existing db.php
function deleteUser($userId) {
    global $db; // Use the existing global PDO connection from db.php
    
    try {
        // Get user info for logging (before deletion)
        $user = fetchOne("SELECT username, email FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found or already deleted.'];
        }
        
        // Start transaction for data integrity
        if (!beginTransaction()) {
            return ['success' => false, 'message' => 'Failed to start transaction.'];
        }
        
        try {
            // Soft delete - mark as deleted with timestamp (RECOMMENDED)
            $result = executeUpdate("
                UPDATE users 
                SET deleted_at = NOW(), 
                    is_active = 0, 
                    updated_at = NOW(),
                    username = CONCAT(username, '_deleted_', UNIX_TIMESTAMP()),
                    email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP())
                WHERE id = ? AND deleted_at IS NULL
            ", [$userId]);
            
            if ($result) {
                // Log the deletion activity
                ActivityLogger::log($_SESSION['user_id'], 'DELETE_USER', 'users', $userId, null, [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'deletion_type' => 'soft_delete'
                ]);
                
                commitTransaction();
                return ['success' => true, 'message' => 'User permanently deleted successfully.'];
            } else {
                rollbackTransaction();
                return ['success' => false, 'message' => 'Failed to delete user.'];
            }
            
        } catch (Exception $e) {
            rollbackTransaction();
            throw $e;
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()];
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> User Management</h1>
    <p>Manage all system users, roles, and permissions</p>
</div>

<!-- Display messages -->
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" id="messageAlert">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
    <div>
        <strong><?= $messageType === 'success' ? 'Success!' : 'Error!' ?></strong>
        <p><?= htmlspecialchars($message) ?></p>
    </div>
    <button class="alert-close" onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <a href="#" onclick="openCreateUserModal()" class="action-card">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="action-content">
                <h4>Add New User</h4>
                <p>Create student, faculty, or staff account</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/admin/import_users.php" class="action-card">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-file-upload"></i>
            </div>
            <div class="action-content">
                <h4>Import Users</h4>
                <p>Bulk import users from CSV file</p>
            </div>
        </a>
        
        <a href="#" onclick="exportUsers()" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-download"></i>
            </div>
            <div class="action-content">
                <h4>Export Users</h4>
                <p>Download user data as CSV file</p>
            </div>
        </a>
    </div>
</div>

<!-- Statistics Grid -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fa fa-bar-chart"></i> User Statistics</h3>
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $userStats['total_users'] ?? 0 ?></div>
                <div class="stats-label">Total Users</div>
                <small style="color: var(--text-light);">+<?= $userStats['new_users_month'] ?? 0 ?> this month</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--success); color: white;">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $userStats['active_users'] ?? 0 ?></div>
                <div class="stats-label">Active Users</div>
                <small style="color: var(--text-light);"><?= $userStats['active_week'] ?? 0 ?> active this week</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $userStats['inactive_users'] ?? 0 ?></div>
                <div class="stats-label">Inactive Users</div>
                <small style="color: var(--text-light);"><?= $userStats['inactive_users'] > 0 ? 'Need attention' : 'All good' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="dashboard-section" style="margin-bottom: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-search"></i> Search & Filter Users</h3>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end; margin-top: 1rem;">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-dark);">Search Users</label>
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-light); z-index: 1;"></i>
                <input type="text" 
                       id="searchInput" 
                       placeholder="Search by name, username, or email..." 
                       value="<?= htmlspecialchars($searchQuery) ?>"
                       onkeyup="handleSearch(event)"
                       style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.5rem; border: 2px solid var(--border-gray); border-radius: 8px; font-size: 1rem;">
            </div>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-dark);">Role</label>
            <select id="roleFilter" onchange="applyFilters()" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: white;">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?= $role['role'] ?>" <?= $roleFilter === $role['role'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($role['role']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-dark);">Status</label>
            <select id="statusFilter" onchange="applyFilters()" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: white;">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-dark);">Sort By</label>
            <select id="sortFilter" onchange="applyFilters()" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: white;">
                <option value="created_at-DESC" <?= $sortBy === 'created_at' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                <option value="created_at-ASC" <?= $sortBy === 'created_at' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                <option value="last_name-ASC" <?= $sortBy === 'last_name' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Last Name A-Z</option>
                <option value="last_name-DESC" <?= $sortBy === 'last_name' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Last Name Z-A</option>
                <option value="last_login-DESC" <?= $sortBy === 'last_login' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Recently Active</option>
            </select>
        </div>
        
        <div>
            <button onclick="resetFilters()" style="padding: 0.8rem 1rem; background: var(--light-gray); color: var(--text-dark); border: 2px solid var(--border-gray); border-radius: 8px; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="dashboard-section">
    <div class="section-header">
        <h3><i class="fas fa-users"></i> Users (<?= number_format($totalUsers) ?> total)</h3>
        <div style="display: flex; gap: 1rem;">
            <span style="font-size: 0.9rem; color: var(--text-light);">
                Showing <?= (($page - 1) * $limit) + 1 ?> - <?= min($page * $limit, $totalUsers) ?>
            </span>
        </div>
    </div>
    
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No users found</p>
        <button onclick="resetFilters()" style="margin-top: 1rem; padding: 0.8rem 1.5rem; background: var(--primary-blue); color: white; border: none; border-radius: 8px; cursor: pointer;">
            Reset Filters
        </button>
    </div>
    <?php else: ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">User</th>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">Role</th>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">Status</th>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">Join Date</th>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">Last Login</th>
                    <th style="background: var(--primary-blue); color: white; padding: 1rem; text-align: left; font-weight: 600; border: none;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr data-user-id="<?= $user['id'] ?>" class="user-row">
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray);">
                        <div class="user-info" style="justify-content: flex-start; text-align: left;">
                            <div class="user-avatar"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></div>
                            <div class="user-details" style="text-align: left;">
                                <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                <small>@<?= htmlspecialchars($user['username']) ?></small>
                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                    <?= htmlspecialchars($user['email']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray);">
                        <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $user['role_name'])) ?>">
                            <?= htmlspecialchars($user['role_name']) ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray);">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="status-badge status-<?= $user['is_active'] ? 'healthy' : 'error' ?>">
                                <i class="fas fa-<?= $user['is_active'] ? 'check' : 'times' ?>"></i>
                            </span>
                            <span><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <?php if ($user['daily_activities'] > 0): ?>
                        <small style="color: var(--text-light); font-size: 0.8rem;">
                            <?= $user['daily_activities'] ?> activities today
                        </small>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray); color: var(--text-dark);">
                        <?= $user['join_date'] ? date('M d, Y', strtotime($user['join_date'])) : 'N/A' ?>
                    </td>
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray); color: var(--text-dark);">
                        <?php if ($user['last_login_date']): ?>
                            <?= date('M d, Y', strtotime($user['last_login_date'])) ?>
                            <br><small style="color: var(--text-light);"><?= timeAgo($user['last_login']) ?></small>
                        <?php else: ?>
                            <span style="color: var(--text-light);">Never</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-gray);">
                        <div style="display: flex; gap: 0.5rem;">
                            <button onclick="editUser(<?= $user['id'] ?>)" class="btn-sm btn-primary" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <?php if ($user['is_active']): ?>
                            <button onclick="suspendUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="btn-sm" style="background: var(--warning); color: white;" title="Suspend User">
                                <i class="fas fa-user-slash"></i>
                            </button>
                            <?php else: ?>
                            <button onclick="activateUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="btn-sm" style="background: var(--success); color: white;" title="Activate User">
                                <i class="fas fa-user-check"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($userRole === 'Super Admin' && $user['role_name'] !== 'Super Admin'): ?>
                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="btn-sm" style="background: var(--error); color: white;" title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin: 2rem 0; padding: 1.5rem; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-link">
        <i class="fas fa-chevron-left"></i> Previous
    </a>
    <?php endif; ?>
    
    <?php
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    
    for ($i = $startPage; $i <= $endPage; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
           class="btn-link <?= $i === $page ? 'active' : '' ?>" 
           style="<?= $i === $page ? 'background: var(--primary-blue); color: white;' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-link">
        Next <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Create User Modal -->
<div id="createUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Create New User</h2>
            <button onclick="closeModal('createUserModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="createUserForm">
            <input type="hidden" name="action" value="create_user">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">First Name *</label>
                    <input type="text" name="first_name" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Last Name *</label>
                    <input type="text" name="last_name" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
                </div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Username *</label>
                <input type="text" name="username" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Email Address *</label>
                <input type="email" name="email" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Role *</label>
                    <select name="role_id" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: white;">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Password *</label>
                    <input type="password" name="password" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-gray);">
                <button type="button" onclick="closeModal('createUserModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit User</h2>
            <button onclick="closeModal('editUserModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="editUserForm">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">First Name</label>
                    <input type="text" id="editFirstName" name="first_name" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Last Name</label>
                    <input type="text" id="editLastName" name="last_name" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
                </div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Email Address</label>
                <input type="email" id="editEmail" name="email" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Role</label>
                <select id="editRole" name="role_id" style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: white;">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-gray);">
                <button type="button" onclick="closeModal('editUserModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search and filter functions
function handleSearch(event) {
    if (event.key === 'Enter') {
        applyFilters();
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const sort = document.getElementById('sortFilter').value.split('-');
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (role) params.set('role', role);
    if (status) params.set('status', status);
    if (sort[0]) params.set('sort', sort[0]);
    if (sort[1]) params.set('order', sort[1]);
    
    window.location.href = '?' + params.toString();
}

function resetFilters() {
    window.location.href = window.location.pathname;
}

// Modal functions
function openCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// User management functions
function editUser(userId) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUserModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function suspendUser(userId, username) {
    if (confirm(`Are you sure you want to suspend user "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="suspend_user">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="reason" value="Suspended by admin">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function activateUser(userId, username) {
    if (confirm(`Are you sure you want to activate user "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="activate_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// FIXED: Updated delete confirmation message to be more clear
function deleteUser(userId, username) {
    if (confirm(`⚠️ PERMANENT DELETION WARNING ⚠️\n\nAre you sure you want to permanently delete user "${username}"?\n\nThis action will:\n- Mark the user as deleted\n- Deactivate their account\n- Modify their username/email to prevent conflicts\n- This action cannot be undone\n\nType "DELETE" in the next prompt to confirm.`)) {
        const confirmation = prompt(`To confirm permanent deletion of user "${username}", please type "DELETE" (all caps):`);
        if (confirmation === "DELETE") {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Deletion cancelled. User was not deleted.');
        }
    }
}

function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export_users.php?' + params.toString();
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.getElementById('messageAlert');
    if (alert) {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
});
</script>

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

    .alert {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border-left: 4px solid var(--success);
        color: var(--success);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--error);
        color: var(--error);
    }
    
    .alert-close {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    
    .alert-close:hover {
        background: rgba(0, 0, 0, 0.1);
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

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
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

    /* .role-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
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
        width: 24px;
        height: 24px;
        border-radius: 50%;
        font-size: 0.8rem;
    }
    
    .status-healthy {
        background: var(--success);
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
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }
    
    .btn-secondary {
        background: var(--light-gray);
        color: var(--text-dark);
    }
    
    .btn-sm:hover,
    .btn-primary:hover,
    .btn-secondary:hover {
        opacity: 0.9;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .btn-link {
        color: var(--primary-blue);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .btn-link:hover {
        background: var(--light-blue);
        text-decoration: none;
    }
    
    .btn-link.active {
        background: var(--primary-blue);
        color: white;
    }

    .user-row:hover {
        background: var(--light-blue);
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-gray);
    }
    
    .modal-header h2 {
        margin: 0;
        color: var(--primary-blue);
        font-size: 1.4rem;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.2rem;
        color: var(--text-light);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background: var(--light-gray);
    }

    @media (max-width: 768px) {
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            flex-direction: column;
            text-align: center;
        }
        
        .user-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>

<?php
function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 604800) return floor($time/86400) . 'd ago';
    
    return date('M d, Y', strtotime($datetime));
}

require __DIR__ . "/../shared/footer.php";