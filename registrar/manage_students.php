<?php
// registrar/manage_students.php - Student Management Interface
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $studentId = (int)($_POST['student_id'] ?? 0);
    
    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] === '1' ? 1 : 0;
            $result = executeUpdate(
                "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ? AND role_id = 4",
                [$newStatus, $studentId]
            );
            if ($result) {
                $message = 'Student status updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update student status.';
                $messageType = 'error';
            }
            break;
            
        case 'delete_grade':
            $gradeId = (int)($_POST['grade_id'] ?? 0);
            $result = executeUpdate("DELETE FROM grades WHERE id = ?", [$gradeId]);
            if ($result) {
                $message = 'Grade deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete grade.';
                $messageType = 'error';
            }
            break;
            
        case 'delete_file':
            $fileId = (int)($_POST['file_id'] ?? 0);
            $file = fetchOne("SELECT file_path FROM student_files WHERE id = ?", [$fileId]);
            if ($file) {
                $filePath = __DIR__ . "/../" . ltrim($file['file_path'], '/GLC_AIMS/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $result = executeUpdate("DELETE FROM student_files WHERE id = ?", [$fileId]);
                if ($result) {
                    $message = 'File deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete file.';
                    $messageType = 'error';
                }
            }
            break;
    }
}

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;

// Build query
$whereConditions = ["u.role_id = 4"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "u.is_active = ?";
    $params[] = ($statusFilter === 'active') ? 1 : 0;
}

$whereClause = implode(' AND ', $whereConditions);

// Validate sort columns
$validSortColumns = ['first_name', 'last_name', 'username', 'email', 'created_at', 'last_login'];
if (!in_array($sortBy, $validSortColumns)) {
    $sortBy = 'created_at';
}
$sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Get students with pagination
$query = "
    SELECT u.*, 
           CONCAT(u.first_name, ' ', u.last_name) as full_name,
           (SELECT COUNT(*) FROM grades g WHERE g.user_id = u.id) as grade_count,
           (SELECT COUNT(*) FROM student_files sf WHERE sf.user_id = u.id) as file_count,
           (SELECT AVG(g.grade) FROM grades g WHERE g.user_id = u.id) as avg_grade
    FROM users u
    WHERE $whereClause
    ORDER BY u.$sortBy $sortOrder
";

$result = fetchPaginated($query, $params, $page, $perPage);
$students = $result['data'];
$pagination = $result['pagination'];

// Get statistics
$totalStudents = fetchOne("SELECT COUNT(*) as count FROM users WHERE role_id = 4")['count'] ?? 0;
$activeStudents = fetchOne("SELECT COUNT(*) as count FROM users WHERE role_id = 4 AND is_active = 1")['count'] ?? 0;
$studentsWithGrades = fetchOne("
    SELECT COUNT(DISTINCT user_id) as count 
    FROM grades g 
    JOIN users u ON g.user_id = u.id 
    WHERE u.role_id = 4
")['count'] ?? 0;
$studentsWithFiles = fetchOne("
    SELECT COUNT(DISTINCT user_id) as count 
    FROM student_files sf 
    JOIN users u ON sf.user_id = u.id 
    WHERE u.role_id = 4
")['count'] ?? 0;
?>

<div class="page-header">
    <div class="header-text">
        <h1><i class="fas fa-users"></i> Manage Students</h1>
        <p>View and manage student records, grades, and files</p>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" style="margin-bottom: 2rem;">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
    <div>
        <?= htmlspecialchars($message) ?>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $totalStudents ?></div>
            <div class="stats-label">Total Students</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success);">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $activeStudents ?></div>
            <div class="stats-label">Active Students</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning);">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $studentsWithGrades ?></div>
            <div class="stats-label">With Grades</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--info);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $studentsWithFiles ?></div>
            <div class="stats-label">With Files</div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="filters-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <form method="GET" class="filters-form" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div class="form-group">
            <label for="search">Search Students</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search by name, username, or email...">
        </div>
        
        <div class="form-group">
            <label for="status">Status Filter</label>
            <select name="status" id="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Students</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="sort">Sort By</label>
            <select name="sort" id="sort">
                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date Registered</option>
                <option value="first_name" <?= $sortBy === 'first_name' ? 'selected' : '' ?>>First Name</option>
                <option value="last_name" <?= $sortBy === 'last_name' ? 'selected' : '' ?>>Last Name</option>
                <option value="username" <?= $sortBy === 'username' ? 'selected' : '' ?>>Username</option>
                <option value="last_login" <?= $sortBy === 'last_login' ? 'selected' : '' ?>>Last Login</option>
            </select>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="/GLC_AIMS/registrar/manage_students.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="table-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
        <h3><i class="fas fa-table"></i> Student Records</h3>
        <div class="table-actions">
            <a href="/GLC_AIMS/registrar/export_students.php" class="btn-link">
                <i class="fas fa-download"></i> Export Data
            </a>
        </div>
    </div>
    
    <?php if (empty($students)): ?>
        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Students Found</h3>
            <p>No students match your current filters. Try adjusting your search criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Contact</th>
                        <th>Academic Records</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?= substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1) ?>
                                    </div>
                                    <div class="student-details">
                                        <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                        <small>@<?= htmlspecialchars($student['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <div><?= htmlspecialchars($student['email'] ?? 'No email') ?></div>
                                    <small><?= htmlspecialchars($student['phone_number'] ?? 'No phone') ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="academic-summary">
                                    <div class="academic-stats">
                                        <span class="stat-badge">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?= $student['grade_count'] ?> grades
                                        </span>
                                        <span class="stat-badge">
                                            <i class="fas fa-file"></i>
                                            <?= $student['file_count'] ?> files
                                        </span>
                                    </div>
                                    <?php if ($student['avg_grade']): ?>
                                        <small>Avg: <?= number_format($student['avg_grade'], 1) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <option value="1" <?= $student['is_active'] ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= !$student['is_active'] ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <div class="date-info">
                                    <div><?= date('M j, Y', strtotime($student['created_at'])) ?></div>
                                    <small><?= timeAgo($student['created_at']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/GLC_AIMS/registrar/student_profile.php?id=<?= $student['id'] ?>" 
                                       class="btn-sm btn-primary" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/GLC_AIMS/registrar/upload_grades.php?student_id=<?= $student['id'] ?>" 
                                       class="btn-sm btn-success" title="Add Grade">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <a href="/GLC_AIMS/registrar/upload_files.php?username=<?= urlencode($student['username']) ?>" 
                                       class="btn-sm btn-warning" title="Upload File">
                                        <i class="fas fa-upload"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-wrapper" style="margin-top: 2rem; display: flex; justify-content: center; align-items: center; gap: 1rem;">
                <div class="pagination-info">
                    Showing <?= ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 ?> to 
                    <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) ?> of 
                    <?= $pagination['total'] ?> students
                </div>
                
                <div class="pagination-controls">
                    <?php
                    $queryParams = array_merge($_GET, ['page' => '']);
                    unset($queryParams['page']);
                    $baseUrl = '/GLC_AIMS/registrar/manage_students.php?' . http_build_query($queryParams) . '&page=';
                    ?>
                    
                    <?php if ($pagination['has_prev']): ?>
                        <a href="<?= $baseUrl . ($pagination['current_page'] - 1) ?>" class="btn-sm btn-secondary">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <a href="<?= $baseUrl . $i ?>" 
                           class="btn-sm <?= $i === $pagination['current_page'] ? 'btn-primary' : 'btn-secondary' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['has_next']): ?>
                        <a href="<?= $baseUrl . ($pagination['current_page'] + 1) ?>" class="btn-sm btn-secondary">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return floor($time/2592000) . 'mo ago';
}
?>

<style>
    .page-header {
        margin-bottom: 2rem;
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
    }
    
    .header-text h1 {
        color: var(--primary-blue);
        margin-bottom: 0.5rem;
    }
    
    .header-text p {
        color: var(--text-light);
        font-size: 1.1rem;
    }
    
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .stats-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-blue);
    }
    
    .stats-label {
        color: var(--text-dark);
        font-weight: 500;
    }
    
    .filters-form {
        align-items: end;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-group label {
        font-weight: 500;
        color: var(--text-dark);
        font-size: 0.9rem;
    }
    
    .form-group input,
    .form-group select {
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f9fafb;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-dark);
        border-bottom: 2px solid #e5e7eb;
    }
    
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }
    
    .data-table tr:hover {
        background: #f9fafb;
    }
    
    .student-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .student-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-yellow);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--primary-blue);
    }
    
    .student-details strong {
        display: block;
        font-size: 1rem;
        color: var(--text-dark);
    }
    
    .student-details small {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    .contact-info div {
        font-size: 0.95rem;
        color: var(--text-dark);
    }
    
    .contact-info small {
        color: var(--text-light);
        font-size: 0.8rem;
    }
    
    .academic-stats {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 0.3rem;
    }
    
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.5rem;
        background: rgba(30, 64, 175, 0.1);
        color: var(--primary-blue);
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-select {
        padding: 0.4rem 0.6rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.85rem;
        background: white;
    }
    
    .date-info div {
        font-size: 0.95rem;
        color: var(--text-dark);
    }
    
    .date-info small {
        color: var(--text-light);
        font-size: 0.8rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .btn, .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }
    
    .btn-secondary {
        background: var(--text-light);
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
    
    .btn:hover, .btn-sm:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        text-decoration: none;
    }
    
    .btn-link {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
    
    .alert {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border-color: var(--success);
        color: #065f46;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border-color: var(--error);
        color: #991b1b;
    }
    
    .alert i {
        font-size: 1.2rem;
        margin-top: 0.1rem;
    }
    
    .alert-success i {
        color: var(--success);
    }
    
    .alert-error i {
        color: var(--error);
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .pagination-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
    
    .pagination-info {
        color: var(--text-light);
        font-size: 0.9rem;
    }
    
    .pagination-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    /* CSS Variables */
    :root {
        --primary-blue: #1e40af;
        --light-blue: #3b82f6;
        --accent-yellow: #f4b400;
        --success: #10b981;
        --warning: #f59e0b;
        --error: #ef4444;
        --info: #06b6d4;
        --text-dark: #1f2937;
        --text-light: #6b7280;
        --border-gray: #e5e7eb;
        --background: #f9fafb;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .filters-form {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .header-content {
            flex-direction: column;
            gap: 1rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .data-table {
            font-size: 0.85rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .student-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .academic-stats {
            flex-direction: column;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .pagination-wrapper {
            flex-direction: column;
        }
        
        .pagination-controls {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .table-section {
            padding: 1rem;
        }
        
        .filters-section {
            padding: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.5rem 0.25rem;
        }
        
        .btn, .btn-sm {
            font-size: 0.8rem;
            padding: 0.5rem;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>