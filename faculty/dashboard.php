<?php
// faculty/dashboard.php - Enhanced Faculty Dashboard
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Faculty', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_name'];

// Get comprehensive faculty statistics
$gradeStats = fetchOne("
    SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN gs.status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
        SUM(CASE WHEN gs.status = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
        SUM(CASE WHEN gs.status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
        COUNT(DISTINCT gs.student_id) as students_graded,
        AVG(gs.grade) as average_grade,
        COUNT(CASE WHEN DATE(gs.submitted_at) = CURDATE() THEN 1 END) as today_submissions,
        COUNT(CASE WHEN gs.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_submissions
    FROM grade_submissions gs
    WHERE gs.faculty_id = ?
", [$userId]);

// Get subject assignment statistics
$subjectStats = fetchOne("
    SELECT 
        COUNT(DISTINCT sa.subject_name) as total_subjects,
        COUNT(DISTINCT sa.student_id) as total_assigned_students,
        COUNT(*) as total_assignments
    FROM subject_assignments sa
    WHERE sa.faculty_id = ? AND sa.is_active = 1
", [$userId]);

// Get recent grade submissions
$recentSubmissions = fetchAll("
    SELECT gs.*, u.first_name, u.last_name, u.username,
           r.first_name as reviewer_first_name, r.last_name as reviewer_last_name
    FROM grade_submissions gs
    JOIN users u ON gs.student_id = u.id
    LEFT JOIN users r ON gs.reviewed_by = r.id
    WHERE gs.faculty_id = ?
    ORDER BY gs.submitted_at DESC
    LIMIT 8
", [$userId]);

// Get subject assignments with student counts
$assignedSubjects = fetchAll("
    SELECT sa.subject_name, 
           COUNT(sa.student_id) as student_count,
           AVG(COALESCE(gs.grade, 0)) as avg_grade,
           COUNT(gs.id) as submitted_grades
    FROM subject_assignments sa
    LEFT JOIN grade_submissions gs ON sa.student_id = gs.student_id 
        AND sa.subject_name = gs.subject 
        AND sa.faculty_id = gs.faculty_id
        AND gs.status = 'approved'
    WHERE sa.faculty_id = ? AND sa.is_active = 1
    GROUP BY sa.subject_name
    ORDER BY sa.subject_name
", [$userId]);

// Get assigned students with their progress
$assignedStudents = fetchAll("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.username,
           COUNT(DISTINCT sa.subject_name) as assigned_subjects,
           COUNT(DISTINCT gs.id) as submitted_grades,
           AVG(CASE WHEN gs.status = 'approved' THEN gs.grade END) as avg_grade,
           MAX(gs.submitted_at) as last_submission
    FROM subject_assignments sa
    JOIN users u ON sa.student_id = u.id
    LEFT JOIN grade_submissions gs ON sa.student_id = gs.student_id 
        AND sa.subject_name = gs.subject 
        AND sa.faculty_id = gs.faculty_id
    WHERE sa.faculty_id = ? AND sa.is_active = 1 AND u.is_active = 1
    GROUP BY u.id, u.first_name, u.last_name, u.username
    ORDER BY u.last_name, u.first_name
    LIMIT 10
", [$userId]);

// Get critical alerts
$criticalAlerts = [];

// Check for overdue submissions (pending > 5 days)
$overdueCount = fetchOne("
    SELECT COUNT(*) as count
    FROM grade_submissions gs
    WHERE gs.faculty_id = ? AND gs.status = 'pending' 
    AND gs.submitted_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
", [$userId])['count'] ?? 0;

if ($overdueCount > 0) {
    $criticalAlerts[] = [
        'type' => 'overdue',
        'message' => "$overdueCount grade submission(s) pending review for over 5 days",
        'severity' => 'warning',
        'icon' => 'clock'
    ];
}

// Check for rejected submissions needing attention
$rejectedCount = fetchOne("
    SELECT COUNT(*) as count
    FROM grade_submissions gs
    WHERE gs.faculty_id = ? AND gs.status = 'rejected'
    AND gs.reviewed_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
", [$userId])['count'] ?? 0;

if ($rejectedCount > 0) {
    $criticalAlerts[] = [
        'type' => 'rejected',
        'message' => "$rejectedCount recently rejected submissions need attention",
        'severity' => 'error',
        'icon' => 'exclamation-triangle'
    ];
}

function getStatusClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'error';
        default: return 'info';
    }
}

function getGradeLevel($grade) {
    if ($grade >= 90) return 'excellent';
    if ($grade >= 85) return 'good';
    if ($grade >= 80) return 'fair';
    return 'poor';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return floor($time/2592000) . 'mo ago';
}
?>

<div class="page-header">
        <div class="header-text">
            <h1><i class="fas fa-chalkboard-teacher"></i> Faculty Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</p>
        </div>
</div>

<!-- Critical Alerts -->
<?php if (!empty($criticalAlerts)): ?>
<div class="alerts-section" style="margin-bottom: 2rem;">
    <?php foreach ($criticalAlerts as $alert): ?>
        <div class="alert alert-<?= $alert['severity'] ?>">
            <i class="fas fa-<?= $alert['icon'] ?>"></i>
            <div>
                <strong>Attention Required!</strong>
                <p><?= htmlspecialchars($alert['message']) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
        
        <a href="/GLC_AIMS/faculty/submit_grades.php" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="action-content">
                <h4>Submit New Grades</h4>
                <p>Submit student grades for registrar approval</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/faculty/my_submissions.php" class="action-card">
            <div class="action-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-list-alt"></i>
            </div>
            <div class="action-content">
                <h4>My Submissions</h4>
                <p>View and manage grade submissions</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/faculty/my_students.php" class="action-card">
            <div class="action-icon" style="background: var(--success); color: white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="action-content">
                <h4>My Students</h4>
                <p>View assigned students and subjects</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/faculty/grade_reports.php" class="action-card">
            <div class="action-icon" style="background: var(--info); color: white;">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-content">
                <h4>Grade Reports</h4>
                <p>Generate performance analytics</p>
            </div>
        </a>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $gradeStats['total_submissions'] ?? 0 ?></div>
            <div class="stats-label">Total Submissions</div>
            <small style="color: var(--text-light);"><?= $gradeStats['students_graded'] ?? 0 ?> students graded</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $gradeStats['pending_submissions'] ?? 0 ?></div>
            <div class="stats-label">Pending Review</div>
            <small style="color: var(--text-light);">Awaiting registrar approval</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $gradeStats['approved_submissions'] ?? 0 ?></div>
            <div class="stats-label">Approved</div>
            <small style="color: var(--text-light);">Successfully processed</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= ($gradeStats['average_grade'] ?? 0) >= 85 ? 'var(--success)' : 'var(--info)' ?>; color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['average_grade'] ?? 0, 1) ?></div>
            <div class="stats-label">Average Grade</div>
            <small style="color: var(--text-light);">Overall performance</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
            <i class="fas fa-book"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $subjectStats['total_subjects'] ?? 0 ?></div>
            <div class="stats-label">Assigned Subjects</div>
            <small style="color: var(--text-light);"><?= $subjectStats['total_assigned_students'] ?? 0 ?> students total</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--info); color: white;">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $gradeStats['week_submissions'] ?? 0 ?></div>
            <div class="stats-label">This Week</div>
            <small style="color: var(--text-light);"><?= $gradeStats['today_submissions'] ?? 0 ?> submitted today</small>
        </div>
    </div>
</div>

<!-- Dashboard Content Grid -->
<div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Submissions -->
    <div class="dashboard-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
            <h3><i class="fas fa-history"></i> Recent Submissions</h3>
            <a href="/GLC_AIMS/faculty/my_submissions.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentSubmissions)): ?>
            <div class="empty-state" style="text-align: center; padding: 2rem; color: var(--text-light);">
                <i class="fas fa-graduation-cap" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                <h4>No submissions yet</h4>
                <p>Start by submitting grades for your students</p>
                <a href="/GLC_AIMS/faculty/submit_grades.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Submit Grades
                </a>
            </div>
        <?php else: ?>
            <div class="submissions-list">
                <?php foreach ($recentSubmissions as $submission): ?>
                    <div class="submission-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0; border-bottom: 1px solid #eee;">
                        <div class="submission-info" style="flex: 1;">
                            <div class="student-name" style="font-weight: 600; color: var(--text-dark);">
                                <?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?>
                            </div>
                            <div class="submission-details" style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.2rem;">
                                <strong><?= htmlspecialchars($submission['subject']) ?></strong>
                                <?php if ($submission['semester']): ?>
                                    • <?= htmlspecialchars($submission['semester']) ?>
                                <?php endif; ?>
                                <?php if ($submission['school_year']): ?>
                                    • <?= htmlspecialchars($submission['school_year']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="submission-meta" style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.2rem;">
                                <?= timeAgo($submission['submitted_at']) ?>
                                <?php if ($submission['reviewer_first_name']): ?>
                                    • Reviewed by <?= htmlspecialchars($submission['reviewer_first_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="submission-status" style="text-align: right;">
                            <div class="grade-badge grade-<?= getGradeLevel($submission['grade']) ?>" style="padding: 0.3rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem;">
                                <?= number_format($submission['grade'], 1) ?>%
                            </div>
                            <div class="status-badge status-<?= $submission['status'] ?>" style="padding: 0.2rem 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 500; text-transform: uppercase;">
                                <?= ucfirst($submission['status']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assigned Students -->
    <div class="dashboard-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
            <h3><i class="fas fa-users"></i> My Students</h3>
            <a href="/GLC_AIMS/faculty/my_students.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($assignedStudents)): ?>
            <div class="empty-state" style="text-align: center; padding: 2rem; color: var(--text-light);">
                <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                <h4>No students assigned</h4>
                <p>Contact your administrator for student assignments</p>
            </div>
        <?php else: ?>
            <div class="students-list">
                <?php foreach ($assignedStudents as $student): ?>
                    <div class="student-item" style="display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #eee;">
                        <div class="student-info" style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                            <div class="student-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: var(--accent-yellow); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--primary-blue); font-size: 0.9rem;">
                                <?= substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1) ?>
                            </div>
                            <div class="student-details" style="flex: 1;">
                                <div class="student-name" style="font-weight: 600; color: var(--text-dark);">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                </div>
                                <div class="student-meta" style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.1rem;">
                                    @<?= htmlspecialchars($student['username']) ?> •
                                    <?= $student['assigned_subjects'] ?> subject(s) •
                                    <?= $student['submitted_grades'] ?> grades submitted
                                    <?php if ($student['avg_grade']): ?>
                                        • Avg: <?= number_format($student['avg_grade'], 1) ?>%
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="student-actions">
                            <a href="/GLC_AIMS/faculty/submit_grades.php?student_id=<?= $student['id'] ?>" 
                               class="btn-sm btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.6rem;">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assigned Subjects Overview -->
<div class="subjects-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <h3><i class="fas fa-book"></i> My Subject Assignments</h3>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Overview of your assigned subjects and student progress</p>
    </div>
    
    <?php if (empty($assignedSubjects)): ?>
        <div class="empty-state" style="text-align: center; padding: 2rem; color: var(--text-light);">
            <i class="fas fa-book" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
            <h4>No subject assignments</h4>
            <p>Contact your administrator for subject assignments</p>
        </div>
    <?php else: ?>
        <div class="subjects-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <?php foreach ($assignedSubjects as $subject): ?>
                <div class="subject-card" style="border: 1px solid var(--border-gray); border-radius: 10px; padding: 1.5rem; transition: all 0.3s ease;">
                    <div class="subject-header" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div class="subject-icon" style="width: 45px; height: 45px; border-radius: 8px; background: linear-gradient(135deg, var(--primary-blue), var(--light-blue)); color: white; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="subject-info" style="flex: 1;">
                            <h4 style="margin: 0; color: var(--text-dark); font-size: 1.1rem;"><?= htmlspecialchars($subject['subject_name']) ?></h4>
                            <p style="margin: 0.2rem 0 0; color: var(--text-light); font-size: 0.9rem;"><?= $subject['student_count'] ?> students enrolled</p>
                        </div>
                    </div>
                    
                    <div class="subject-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="stat-item" style="text-align: center; padding: 0.8rem; background: #f8fafc; border-radius: 6px;">
                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary-blue);"><?= $subject['submitted_grades'] ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-light);">Grades Submitted</div>
                        </div>
                        <div class="stat-item" style="text-align: center; padding: 0.8rem; background: #f8fafc; border-radius: 6px;">
                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--success);">
                                <?= $subject['avg_grade'] > 0 ? number_format($subject['avg_grade'], 1) : '—' ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-light);">Average Grade</div>
                        </div>
                    </div>
                    
                    <div class="subject-actions" style="display: flex; gap: 0.5rem;">
                        <a href="/GLC_AIMS/faculty/submit_grades.php?subject=<?= urlencode($subject['subject_name']) ?>" 
                           class="btn btn-primary" style="flex: 1; justify-content: center; font-size: 0.85rem; padding: 0.6rem;">
                            <i class="fas fa-plus"></i> Add Grades
                        </a>
                        <a href="/GLC_AIMS/faculty/my_students.php?subject=<?= urlencode($subject['subject_name']) ?>" 
                           class="btn btn-secondary" style="padding: 0.6rem 0.8rem;">
                            <i class="fas fa-users"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

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
        font-size: 2rem;
    }
    
    .header-text p {
        color: var(--text-light);
        font-size: 1.1rem;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        flex-shrink: 0;
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
    
    .action-card {
        background: white;
        border: 1px solid var(--border-gray);
        border-radius: 12px;
        padding: 1.5rem;
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
        border-color: var(--primary-blue);
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
    
    .empty-state h4 {
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }
    
    .submission-item:last-child,
    .student-item:last-child {
        border-bottom: none;
    }
    
    .grade-badge {
        display: inline-block;
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
    
    .status-badge {
        display: inline-block;
    }
    
    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .status-approved {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-rejected {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }
    
    .subject-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-blue);
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
        background: var(--accent-yellow);
        color: var(--primary-blue);
    }
    
    .btn:hover, .btn-sm:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        text-decoration: none;
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
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border-color: var(--error);
        color: #991b1b;
    }
    
    .alert i {
        font-size: 1.2rem;
        margin-top: 0.1rem;
    }
    
    .alert-warning i {
        color: var(--warning);
    }
    
    .alert-error i {
        color: var(--error);
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
        --white: #ffffff;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .header-content {
            flex-direction: column;
            gap: 1rem;
        }
        
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        
        .subjects-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .header-text h1 {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        .student-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .submission-item,
        .student-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .subjects-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-card {
            flex-direction: column;
            text-align: center;
        }
        
        .dashboard-section,
        .subjects-section,
        .quick-actions {
            padding: 1rem;
        }
        
        .subject-card {
            padding: 1rem;
        }
        
        .subject-stats {
            grid-template-columns: 1fr;
        }
        
        .subject-actions {
            flex-direction: column;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>