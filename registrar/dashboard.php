<?php
// registrar/dashboard.php - Enhanced Registrar Dashboard
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Get comprehensive statistics for registrar-specific data
$studentStats = fetchOne("
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_students,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_students,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_students_month
    FROM users
    WHERE role_id = 4
");

$gradeStats = fetchOne("
    SELECT 
        COUNT(*) as total_grades,
        COUNT(DISTINCT user_id) as students_with_grades,
        COUNT(DISTINCT subject) as unique_subjects,
        AVG(grade) as average_grade,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_grades_week
    FROM grades
");

$fileStats = fetchOne("
    SELECT 
        COUNT(*) as total_files,
        COUNT(DISTINCT user_id) as students_with_files,
        SUM(file_size) as total_file_size,
        COUNT(CASE WHEN uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_files_week
    FROM student_files
");

$systemStats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as daily_activities,
        (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements,
        (SELECT COUNT(*) FROM borrow_transactions WHERE status = 'pending') as pending_requests
");

// Get recent students
$recentStudents = fetchAll("
    SELECT u.*, DATE(u.created_at) as join_date,
           (SELECT COUNT(*) FROM grades g WHERE g.user_id = u.id) as grade_count,
           (SELECT COUNT(*) FROM student_files sf WHERE sf.user_id = u.id) as file_count
    FROM users u
    WHERE u.role_id = 4 AND u.is_active = 1
    ORDER BY u.created_at DESC
    LIMIT 5
");

// Get recent grade entries
$recentGrades = fetchAll("
    SELECT g.*, u.first_name, u.last_name, u.username
    FROM grades g
    JOIN users u ON g.user_id = u.id
    ORDER BY g.created_at DESC
    LIMIT 8
");

// Get recent file uploads
$recentFiles = fetchAll("
    SELECT sf.*, u.first_name, u.last_name, u.username,
           ROUND(sf.file_size / 1024, 2) as file_size_kb
    FROM student_files sf
    JOIN users u ON sf.user_id = u.id
    ORDER BY sf.uploaded_at DESC
    LIMIT 8
");

// Get critical alerts for registrar
$criticalAlerts = [];

// Check for students without grades
$studentsWithoutGrades = fetchOne("
    SELECT COUNT(*) as count
    FROM users u
    WHERE u.role_id = 4 AND u.is_active = 1
    AND u.id NOT IN (SELECT DISTINCT user_id FROM grades)
")['count'] ?? 0;

if ($studentsWithoutGrades > 5) {
    $criticalAlerts[] = [
        'alert_type' => 'no_grades',
        'message' => $studentsWithoutGrades . ' students have no grade records',
        'severity' => 'warning',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Check for students without files
$studentsWithoutFiles = fetchOne("
    SELECT COUNT(*) as count
    FROM users u
    WHERE u.role_id = 4 AND u.is_active = 1
    AND u.id NOT IN (SELECT DISTINCT user_id FROM student_files)
")['count'] ?? 0;

if ($studentsWithoutFiles > 10) {
    $criticalAlerts[] = [
        'alert_type' => 'no_files',
        'message' => $studentsWithoutFiles . ' students have no uploaded files',
        'severity' => 'info',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-university"></i> Registrar Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</p>
</div>

<!-- Critical Alerts -->
<?php if (!empty($criticalAlerts)): ?>
<div class="alert alert-warning" style="margin-bottom: 2rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Attention Required!</strong>
        <p><?= count($criticalAlerts) ?> issues need attention: 
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
        
        <a href="/GLC_AIMS/registrar/pending_grade_approvals.php" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="action-content">
                <h4>Approve Grades</h4>
                <p>Approve and add student grades and academic records</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/upload_files.php" class="action-card">
            <div class="action-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
                <i class="fas fa-upload"></i>
            </div>
            <div class="action-content">
                <h4>Upload Student Files</h4>
                <p>Upload student credentials and documents</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/manage_students.php" class="action-card">
            <div class="action-icon" style="background: var(--light-blue); color: white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="action-content">
                <h4>Manage Students</h4>
                <p>View and manage student records and information</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/grade_reports.php" class="action-card">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-content">
                <h4>Grade Reports</h4>
                <p>Generate academic reports and transcripts</p>
            </div>
        </a>
    </div>
</div>

<!-- Statistics Grid -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fa fa-bar-chart"></i> Statistics</h3>
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $studentStats['total_students'] ?? 0 ?></div>
                <div class="stats-label">Total Students</div>
                <small style="color: var(--text-light);"><?= $studentStats['active_students'] ?? 0 ?> active • <?= $studentStats['inactive_students'] ?? 0 ?> inactive</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--success); color: white;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $gradeStats['total_grades'] ?? 0 ?></div>
                <div class="stats-label">Grade Records</div>
                <small style="color: var(--text-light);"><?= $gradeStats['students_with_grades'] ?? 0 ?> students with grades</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $fileStats['total_files'] ?? 0 ?></div>
                <div class="stats-label">Student Files</div>
                <small style="color: var(--text-light);"><?= formatFileSize($fileStats['total_file_size'] ?? 0) ?> total size</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: <?= ($gradeStats['average_grade'] ?? 0) >= 85 ? 'var(--success)' : 'var(--warning)' ?>; color: white;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= number_format($gradeStats['average_grade'] ?? 0, 1) ?></div>
                <div class="stats-label">Average Grade</div>
                <small style="color: var(--text-light);"><?= $gradeStats['unique_subjects'] ?? 0 ?> unique subjects</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: <?= ($gradeStats['new_grades_week'] ?? 0) > 0 ? 'var(--success)' : 'var(--warning)' ?>; color: white;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $gradeStats['new_grades_week'] ?? 0 ?></div>
                <div class="stats-label">New Grades (Week)</div>
                <small style="color: var(--text-light);">Recently added grades</small>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: <?= ($fileStats['new_files_week'] ?? 0) > 0 ? 'var(--success)' : 'var(--info)' ?>; color: white;">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $fileStats['new_files_week'] ?? 0 ?></div>
                <div class="stats-label">New Files (Week)</div>
                <small style="color: var(--text-light);">Recently uploaded files</small>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content Grid -->
<div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Students -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-user-graduate"></i> Recent Students</h3>
            <a href="/GLC_AIMS/registrar/manage_students.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentStudents)): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <p>No recent students</p>
            </div>
        <?php else: ?>
            <div class="users-list">
                <?php foreach ($recentStudents as $student): ?>
                    <div class="user-item">
                        <div class="user-info">
                            <div class="user-avatar"><?= substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1) ?></div>
                            <div class="user-details">
                                <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                <small>@<?= htmlspecialchars($student['username']) ?></small>
                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                    <span class="grade-badge">
                                        <?= $student['grade_count'] ?> grades
                                    </span>
                                    •
                                    <span class="file-badge">
                                        <?= $student['file_count'] ?> files
                                    </span>
                                    • Joined <?= $student['join_date'] ?>
                                </div>
                            </div>
                        </div>
                        <div class="user-actions">
                            <a href="/GLC_AIMS/registrar/student_profile.php?id=<?= $student['id'] ?>" class="btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent File Uploads -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-file-alt"></i> Recent File Uploads</h3>
            <a href="/GLC_AIMS/registrar/upload_files.php" class="btn-link">Upload Files</a>
        </div>
        
        <?php if (empty($recentFiles)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>No recent file uploads</p>
            </div>
        <?php else: ?>
            <div class="file-list">
                <?php foreach ($recentFiles as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="fas fa-<?= getFileIcon($file['file_name']) ?>"></i>
                            </div>
                            <div class="file-details">
                                <strong><?= htmlspecialchars($file['file_name']) ?></strong>
                                <small><?= htmlspecialchars($file['first_name'] . ' ' . $file['last_name']) ?></small>
                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                    <?= isset($file['file_size_kb']) ? number_format($file['file_size_kb'], 1) . ' KB' : 'Unknown size' ?>
                                    • <?= timeAgo($file['uploaded_at']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?= htmlspecialchars($file['file_path']) ?>" class="btn-sm btn-secondary" target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<!-- Second Row -->
<div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Grades -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-graduation-cap"></i> Recent Grades</h3>
            <!--<a href="/GLC_AIMS/registrar/upload_grades.php" class="btn-link">Add Grade</a>-->
        </div>
        
        <?php if (empty($recentGrades)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <p>No recent grades</p>
            </div>
        <?php else: ?>
            <div class="grade-list">
                <?php foreach ($recentGrades as $grade): ?>
                    <div class="grade-item">
                        <div class="grade-info">
                            <strong><?= htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) ?></strong>
                            <small><?= htmlspecialchars($grade['subject']) ?></small>
                            <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                <?= htmlspecialchars($grade['semester'] ?? 'N/A') ?>
                                <?= htmlspecialchars($grade['school_year'] ?? '') ?>
                                • <?= timeAgo($grade['created_at']) ?>
                            </div>
                        </div>
                        <div class="grade-score">
                            <span class="grade-value grade-<?= getGradeLevel($grade['grade']) ?>">
                                <?= number_format($grade['grade'], 1) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Academic Overview -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-chart-pie"></i> Academic Overview</h3>
        </div>
        
        <div class="academic-stats">
            <div class="academic-item">
                <div class="academic-info">
                    <strong>Students with Complete Records</strong>
                    <small>Both grades and files uploaded</small>
                </div>
                <div class="academic-number">
                    <?php
                    $completeRecords = fetchOne("
                        SELECT COUNT(DISTINCT u.id) as count
                        FROM users u
                        WHERE u.role_id = 4 AND u.is_active = 1
                        AND EXISTS (SELECT 1 FROM grades g WHERE g.user_id = u.id)
                        AND EXISTS (SELECT 1 FROM student_files sf WHERE sf.user_id = u.id)
                    ")['count'] ?? 0;
                    echo $completeRecords;
                    ?>
                </div>
            </div>
            
            <div class="academic-item">
                <div class="academic-info">
                    <strong>Grade Distribution</strong>
                    <small>Average performance level</small>
                </div>
                <div class="academic-number">
                    <?php
                    $avgGrade = $gradeStats['average_grade'] ?? 0;
                    if ($avgGrade >= 90) echo "Excellent";
                    elseif ($avgGrade >= 85) echo "Good";
                    elseif ($avgGrade >= 80) echo "Fair";
                    else echo "Needs Improvement";
                    ?>
                </div>
            </div>
            
            <div class="academic-item">
                <div class="academic-info">
                    <strong>File Upload Compliance</strong>
                    <small>Students with uploaded documents</small>
                </div>
                <div class="academic-number">
                    <?php
                    $fileCompliance = $studentStats['total_students'] > 0 
                        ? round(($fileStats['students_with_files'] / $studentStats['total_students']) * 100, 1)
                        : 0;
                    echo $fileCompliance . '%';
                    ?>
                </div>
            </div>
            
            <div class="academic-item">
                <div class="academic-info">
                    <strong>New Registrations</strong>
                    <small>This month</small>
                </div>
                <div class="academic-number">
                    <?= $studentStats['new_students_month'] ?? 0 ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getGradeLevel($grade) {
    if ($grade >= 90) return 'excellent';
    if ($grade >= 85) return 'good';
    if ($grade >= 80) return 'fair';
    return 'poor';
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'file-pdf',
        'jpg' => 'file-image',
        'jpeg' => 'file-image',
        'png' => 'file-image',
        'gif' => 'file-image',
        'doc' => 'file-word',
        'docx' => 'file-word',
        'xls' => 'file-excel',
        'xlsx' => 'file-excel',
        'ppt' => 'file-powerpoint',
        'pptx' => 'file-powerpoint'
    ];
    return $icons[$extension] ?? 'file';
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
    .grade-item,
    .file-item,
    .academic-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .user-item:last-child,
    .grade-item:last-child,
    .file-item:last-child,
    .academic-item:last-child {
        border-bottom: none;
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

    .grade-badge, .file-badge {
        padding: 0.1rem 0.4rem;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 500;
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }
    
    .grade-value {
        font-size: 1.2rem;
        font-weight: 600;
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
    }
    
    .grade-excellent {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .grade-good {
        background: rgba(59, 130, 246, 0.1);
        color: var(--light-blue);
    }
    
    .grade-fair {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .grade-poor {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .file-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--light-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .file-details {
        flex: 1;
        min-width: 0;
    }
    
    .file-details strong {
        font-size: 0.95rem;
        color: #333;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .file-details small {
        color: #555;
    }
    
    .academic-item {
        padding: 1rem 0;
    }
    
    .academic-info strong {
        font-size: 1rem;
        color: #333;
    }
    
    .academic-info small {
        color: var(--text-light);
    }
    
    .academic-number {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--primary-blue);
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
    
    .btn-secondary {
        background: var(--text-light);
        color: white;
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
        color: white;
    }
    
    .alert {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid;
    }
    
    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border-color: var(--warning);
        color: #92400e;
    }
    
    .alert i {
        font-size: 1.2rem;
        margin-top: 0.1rem;
        color: var(--warning);
    }
    
    .alert div {
        flex: 1;
    }
    
    .alert strong {
        display: block;
        margin-bottom: 0.3rem;
        font-weight: 600;
    }
    
    .alert p {
        margin: 0;
        font-size: 0.95rem;
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
    
    /* Grid System */
    .grid {
        display: grid;
        gap: 1rem;
    }
    
    .grid-2 {
        grid-template-columns: 1fr 1fr;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .grid-2 {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        .user-info,
        .file-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .user-item,
        .grade-item,
        .file-item,
        .academic-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
        }
        
        .stats-card {
            flex-direction: column;
            text-align: center;
        }
        
        .dashboard-section {
            padding: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .action-grid {
            gap: 0.5rem;
        }
        
        .stats-grid {
            gap: 1rem;
        }
        
        .quick-actions,
        .dashboard-section {
            margin-bottom: 1rem;
        }
        
        .file-details strong {
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>