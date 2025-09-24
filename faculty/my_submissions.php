<?php
// faculty/my_submissions.php - Enhanced Faculty Grade Submissions Management
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Faculty', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    
    switch ($action) {
        case 'withdraw':
            // Only allow withdrawal of pending submissions
            $submission = fetchOne(
                "SELECT * FROM grade_submissions WHERE id = ? AND faculty_id = ? AND status = 'pending'",
                [$submissionId, $userId]
            );
            
            if ($submission) {
                $result = executeUpdate(
                    "DELETE FROM grade_submissions WHERE id = ?",
                    [$submissionId]
                );
                
                if ($result) {
                    $message = 'Grade submission withdrawn successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to withdraw submission.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Cannot withdraw this submission.';
                $messageType = 'error';
            }
            break;
            
        case 'resubmit':
            // Allow resubmission of rejected submissions
            $submission = fetchOne(
                "SELECT * FROM grade_submissions WHERE id = ? AND faculty_id = ? AND status = 'rejected'",
                [$submissionId, $userId]
            );
            
            if ($submission) {
                $result = executeUpdate(
                    "UPDATE grade_submissions SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL, registrar_comments = NULL WHERE id = ?",
                    [$submissionId]
                );
                
                if ($result) {
                    $message = 'Grade resubmitted for review.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to resubmit grade.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Cannot resubmit this submission.';
                $messageType = 'error';
            }
            break;
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$subject = $_GET['subject'] ?? '';
$semester = $_GET['semester'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;

// Build query
$whereConditions = ["gs.faculty_id = ?"];
$params = [$userId];

if ($status !== 'all') {
    $whereConditions[] = "gs.status = ?";
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($subject) {
    $whereConditions[] = "gs.subject LIKE ?";
    $params[] = '%' . $subject . '%';
}

if ($semester) {
    $whereConditions[] = "gs.semester = ?";
    $params[] = $semester;
}

$whereClause = implode(' AND ', $whereConditions);

// Get submissions with pagination
$query = "
    SELECT gs.*, u.first_name, u.last_name, u.username,
           r.first_name as reviewer_first_name, r.last_name as reviewer_last_name
    FROM grade_submissions gs
    JOIN users u ON gs.student_id = u.id
    LEFT JOIN users r ON gs.reviewed_by = r.id
    WHERE $whereClause
    ORDER BY gs.submitted_at DESC
";

$result = fetchPaginated($query, $params, $page, $perPage);
$submissions = $result['data'];
$pagination = $result['pagination'];

// Get statistics
$stats = fetchOne("
    SELECT 
        COUNT(*) as total_submissions,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        AVG(grade) as average_grade,
        COUNT(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 END) as today_submissions,
        COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_submissions
    FROM grade_submissions
    WHERE faculty_id = ?
", [$userId]);

// Get filter options
$subjects = fetchAll("
    SELECT DISTINCT gs.subject 
    FROM grade_submissions gs 
    WHERE gs.faculty_id = ? 
    ORDER BY gs.subject
", [$userId]);

$semesters = fetchAll("
    SELECT DISTINCT gs.semester 
    FROM grade_submissions gs 
    WHERE gs.faculty_id = ? AND gs.semester IS NOT NULL 
    ORDER BY gs.semester
", [$userId]);

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
            <h1><i class="fas fa-list-alt"></i> My Grade Submissions</h1>
            <p>Track and manage your submitted grades and their approval status</p>
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
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-list-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['total_submissions'] ?></div>
            <div class="stats-label">Total Submissions</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['pending'] ?></div>
            <div class="stats-label">Pending Review</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['approved'] ?></div>
            <div class="stats-label">Approved</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--error); color: white;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['rejected'] ?></div>
            <div class="stats-label">Rejected</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= ($stats['average_grade'] ?? 0) >= 85 ? 'var(--success)' : 'var(--info)' ?>; color: white;">
            <i class="fas fa-calculator"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($stats['average_grade'] ?? 0, 1) ?></div>
            <div class="stats-label">Average Grade</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['week_submissions'] ?></div>
            <div class="stats-label">This Week</div>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="filters-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <form method="GET" class="filters-form">
        <div class="filter-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="status">Status Filter</label>
                <select name="status" id="status" class="form-input">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject Filter</label>
                <select name="subject" id="subject" class="form-input">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $subj): ?>
                        <option value="<?= htmlspecialchars($subj['subject']) ?>" 
                                <?= $subject === $subj['subject'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="semester">Semester Filter</label>
                <select name="semester" id="semester" class="form-input">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= htmlspecialchars($sem['semester']) ?>" 
                                <?= $semester === $sem['semester'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sem['semester']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="search">Search Student</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                       class="form-input" placeholder="Student name or username...">
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="/GLC_AIMS/faculty/my_submissions.php" class="btn btn-secondary">
                <i class="fas fa-refresh"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Submissions Table -->
<div class="submissions-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
        <h3><i class="fas fa-table"></i> Grade Submissions</h3>
        <div class="table-info" style="color: var(--text-light); font-size: 0.9rem;">
            Showing <?= count($submissions) ?> of <?= $pagination['total'] ?> submissions
        </div>
    </div>
    
    <?php if (empty($submissions)): ?>
        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fas fa-list-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Submissions Found</h3>
            <p style="margin-bottom: 1.5rem;">
                <?php if ($status !== 'all' || $search || $subject || $semester): ?>
                    No grade submissions match your current filters.
                <?php else: ?>
                    You haven't submitted any grades yet.
                <?php endif; ?>
            </p>
            <a href="/GLC_AIMS/faculty/submit_grades.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit Your First Grade
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="submissions-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Student</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Subject</th>
                        <th style="padding: 1rem; text-align: center; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Grade</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Term</th>
                        <th style="padding: 1rem; text-align: center; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Status</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Submitted</th>
                        <th style="padding: 1rem; text-align: center; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid #e5e7eb;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s ease;">
                            <td style="padding: 1rem; vertical-align: middle;">
                                <div class="student-info">
                                    <div class="student-name" style="font-weight: 600; color: var(--text-dark);">
                                        <?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?>
                                    </div>
                                    <div class="student-username" style="font-size: 0.85rem; color: var(--text-light);">
                                        @<?= htmlspecialchars($submission['username']) ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle;">
                                <div class="subject-info">
                                    <div class="subject-name" style="font-weight: 500; color: var(--text-dark);">
                                        <?= htmlspecialchars($submission['subject']) ?>
                                    </div>
                                    <?php if ($submission['remarks']): ?>
                                        <div class="subject-remarks" style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                            <i class="fas fa-comment" style="margin-right: 0.3rem;"></i>
                                            <?= htmlspecialchars(substr($submission['remarks'], 0, 50)) ?><?= strlen($submission['remarks']) > 50 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: middle;">
                                <span class="grade-badge grade-<?= getGradeLevel($submission['grade']) ?>" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem; display: inline-block;">
                                    <?= number_format($submission['grade'], 1) ?>%
                                </span>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle;">
                                <div class="term-info">
                                    <div style="font-weight: 500; color: var(--text-dark);">
                                        <?= htmlspecialchars($submission['semester'] ?: 'N/A') ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-light);">
                                        <?= htmlspecialchars($submission['school_year'] ?: 'N/A') ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: middle;">
                                <span class="status-badge status-<?= $submission['status'] ?>" style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; text-transform: capitalize; display: inline-block;">
                                    <?= ucfirst($submission['status']) ?>
                                </span>
                                <?php if ($submission['status'] === 'rejected' && $submission['registrar_comments']): ?>
                                    <div style="margin-top: 0.3rem;">
                                        <button class="btn-link" onclick="showComments('<?= htmlspecialchars($submission['registrar_comments']) ?>')" style="font-size: 0.75rem;">
                                            <i class="fas fa-comment-dots"></i> View reason
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle;">
                                <div class="submission-date">
                                    <div style="font-weight: 500; color: var(--text-dark);">
                                        <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-light);">
                                        <?= date('g:i A', strtotime($submission['submitted_at'])) ?>
                                        • <?= timeAgo($submission['submitted_at']) ?>
                                    </div>
                                    <?php if ($submission['reviewed_at']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.2rem;">
                                            Reviewed <?= timeAgo($submission['reviewed_at']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: middle;">
                                <div class="action-buttons" style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <button onclick="viewSubmission(<?= htmlspecialchars(json_encode($submission)) ?>)" 
                                            class="btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if ($submission['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to withdraw this submission?')">
                                            <input type="hidden" name="action" value="withdraw">
                                            <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                                            <button type="submit" class="btn-sm btn-warning" title="Withdraw">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($submission['status'] === 'rejected'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to resubmit this grade?')">
                                            <input type="hidden" name="action" value="resubmit">
                                            <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                                            <button type="submit" class="btn-sm btn-success" title="Resubmit">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-wrapper" style="margin-top: 2rem; display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="pagination-info" style="color: var(--text-light); font-size: 0.9rem;">
                    Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                </div>
                
                <div class="pagination-controls" style="display: flex; gap: 0.5rem; align-items: center;">
                    <?php
                    $queryParams = array_merge($_GET, ['page' => '']);
                    unset($queryParams['page']);
                    $baseUrl = '/GLC_AIMS/faculty/my_submissions.php?' . http_build_query($queryParams) . '&page=';
                    ?>
                    
                    <?php if ($pagination['has_prev']): ?>
                        <a href="<?= $baseUrl . ($pagination['current_page'] - 1) ?>" class="btn-sm btn-secondary">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <a href="<?= $baseUrl . $i ?>" 
                           class="btn-sm <?= $i === $pagination['current_page'] ? 'btn-primary' : 'btn-secondary' ?>" 
                           style="min-width: 2rem; justify-content: center;">
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

<!-- Submission Details Modal -->
<div id="submissionModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 2rem; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 1rem;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0; color: var(--primary-blue);"><i class="fas fa-info-circle"></i> Submission Details</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div id="commentsModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; margin: 1rem;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0; color: var(--error);"><i class="fas fa-comment-dots"></i> Registrar Comments</h3>
            <button onclick="closeCommentsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">×</button>
        </div>
        <div class="modal-body">
            <div id="commentsText" style="padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid var(--error);"></div>
        </div>
    </div>
</div>

<script>
function viewSubmission(submission) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div class="submission-details">
            <div class="detail-section" style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">
                    <i class="fas fa-user-graduate"></i> Student Information
                </h4>
                <div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div><strong>Name:</strong> ${submission.first_name} ${submission.last_name}</div>
                    <div><strong>Username:</strong> @${submission.username}</div>
                </div>
            </div>
            
            <div class="detail-section" style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">
                    <i class="fas fa-graduation-cap"></i> Grade Information
                </h4>
                <div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div><strong>Subject:</strong> ${submission.subject}</div>
                    <div><strong>Grade:</strong> <span class="grade-badge grade-${getGradeLevelJS(submission.grade)}">${parseFloat(submission.grade).toFixed(1)}%</span></div>
                    <div><strong>Semester:</strong> ${submission.semester || 'N/A'}</div>
                    <div><strong>School Year:</strong> ${submission.school_year || 'N/A'}</div>
                </div>
            </div>
            
            <div class="detail-section" style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">
                    <i class="fas fa-clock"></i> Timeline
                </h4>
                <div class="timeline-item" style="margin-bottom: 0.5rem;">
                    <strong>Submitted:</strong> ${new Date(submission.submitted_at).toLocaleString()}
                </div>
                ${submission.reviewed_at ? `
                    <div class="timeline-item" style="margin-bottom: 0.5rem;">
                        <strong>Reviewed:</strong> ${new Date(submission.reviewed_at).toLocaleString()}
                    </div>
                    ${submission.reviewer_first_name ? `
                        <div class="timeline-item">
                            <strong>Reviewer:</strong> ${submission.reviewer_first_name} ${submission.reviewer_last_name || ''}
                        </div>
                    ` : ''}
                ` : ''}
            </div>
            
            <div class="detail-section">
                <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Status & Comments
                </h4>
                <div style="margin-bottom: 1rem;">
                    <span class="status-badge status-${submission.status}" style="padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.9rem; text-transform: capitalize;">
                        ${submission.status.charAt(0).toUpperCase() + submission.status.slice(1)}
                    </span>
                </div>
                ${submission.remarks ? `
                    <div style="margin-bottom: 1rem;">
                        <strong>Your Remarks:</strong>
                        <div style="padding: 0.8rem; background: #f0f9ff; border-radius: 8px; margin-top: 0.5rem; border-left: 4px solid var(--info);">
                            ${submission.remarks}
                        </div>
                    </div>
                ` : ''}
                ${submission.registrar_comments ? `
                    <div>
                        <strong>Registrar Comments:</strong>
                        <div style="padding: 0.8rem; background: #fef2f2; border-radius: 8px; margin-top: 0.5rem; border-left: 4px solid var(--error);">
                            ${submission.registrar_comments}
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('submissionModal').style.display = 'flex';
}

function showComments(comments) {
    document.getElementById('commentsText').textContent = comments;
    document.getElementById('commentsModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('submissionModal').style.display = 'none';
}

function closeCommentsModal() {
    document.getElementById('commentsModal').style.display = 'none';
}

function getGradeLevelJS(grade) {
    if (grade >= 90) return 'excellent';
    if (grade >= 85) return 'good';
    if (grade >= 80) return 'fair';
    return 'poor';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Auto-refresh page every 5 minutes to check for status updates
setInterval(function() {
    // Only refresh if there are pending submissions
    if (<?= $stats['pending'] ?> > 0) {
        location.reload();
    }
}, 300000); // 5 minutes
</script>

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
    
    .form-input {
        padding: 0.75rem;
        border: 2px solid var(--border-gray);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    .submissions-table tbody tr:hover {
        background-color: #f9fafb;
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
    
    .btn-info {
        background: var(--info);
        color: white;
    }
    
    .btn-warning {
        background: var(--warning);
        color: white;
    }
    
    .btn-success {
        background: var(--success);
        color: white;
    }
    
    .btn:hover, .btn-sm:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        text-decoration: none;
    }
    
    .btn-link {
        background: none;
        border: none;
        color: var(--primary-blue);
        text-decoration: none;
        cursor: pointer;
        font-size: inherit;
        padding: 0;
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
        
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .filter-row {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-text h1 {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-row {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .submissions-table {
            font-size: 0.9rem;
        }
        
        .submissions-table th,
        .submissions-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: stretch;
        }
        
        .pagination-controls {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .modal-content {
            margin: 0.5rem;
            padding: 1.5rem;
        }
        
        .detail-grid {
            grid-template-columns: 1fr !important;
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
        
        .filters-section,
        .submissions-section {
            padding: 1rem;
        }
        
        .submissions-table th,
        .submissions-table td {
            padding: 0.5rem 0.25rem;
        }
        
        .modal-content {
            margin: 0.25rem;
            padding: 1rem;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>