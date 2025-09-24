<?php
// registrar/pending_grade_approvals.php - Registrar Grade Approval System
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');
    
    // Get submission details
    $submission = fetchOne(
        "SELECT gs.*, u.first_name, u.last_name FROM grade_submissions gs 
         JOIN users u ON gs.student_id = u.id 
         WHERE gs.id = ? AND gs.status = 'pending'",
        [$submissionId]
    );
    
    if (!$submission) {
        $message = 'Invalid submission or submission not found.';
        $messageType = 'error';
    } else {
        switch ($action) {
            case 'approve':
                beginTransaction();
                try {
                    // Update submission status
                    executeUpdate(
                        "UPDATE grade_submissions SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, registrar_comments = ? WHERE id = ?",
                        [$userId, $comments, $submissionId]
                    );
                    
                    // Insert or update grade in main grades table
                    $existingGrade = fetchOne(
                        "SELECT id FROM grades WHERE user_id = ? AND subject = ? AND semester = ? AND school_year = ?",
                        [$submission['student_id'], $submission['subject'], $submission['semester'], $submission['school_year']]
                    );
                    
                    if ($existingGrade) {
                        // Update existing grade
                        executeUpdate(
                            "UPDATE grades SET grade = ?, updated_at = NOW() WHERE id = ?",
                            [$submission['grade'], $existingGrade['id']]
                        );
                    } else {
                        // Insert new grade
                        executeUpdate(
                            "INSERT INTO grades (user_id, subject, grade, semester, school_year, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                            [$submission['student_id'], $submission['subject'], $submission['grade'], $submission['semester'], $submission['school_year']]
                        );
                    }
                    
                    commitTransaction();
                    $message = "Grade approved and added to {$submission['first_name']} {$submission['last_name']}'s record.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    rollbackTransaction();
                    $message = 'Failed to approve grade. Please try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'reject':
                if (empty($comments)) {
                    $message = 'Please provide a reason for rejection.';
                    $messageType = 'error';
                } else {
                    $result = executeUpdate(
                        "UPDATE grade_submissions SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, registrar_comments = ? WHERE id = ?",
                        [$userId, $comments, $submissionId]
                    );
                    
                    if ($result) {
                        $message = "Grade submission rejected. Faculty will be notified.";
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to reject submission.';
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$facultyId = $_GET['faculty_id'] ?? '';
$subject = $_GET['subject'] ?? '';
$semester = $_GET['semester'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;

// Build query for pending submissions
$whereConditions = ["gs.status = 'pending'"];
$params = [];

if ($facultyId) {
    $whereConditions[] = "gs.faculty_id = ?";
    $params[] = $facultyId;
}

if ($subject) {
    $whereConditions[] = "gs.subject LIKE ?";
    $params[] = '%' . $subject . '%';
}

if ($semester) {
    $whereConditions[] = "gs.semester = ?";
    $params[] = $semester;
}

if ($search) {
    $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.username LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Get pending submissions with pagination
$query = "
    SELECT gs.*, 
           s.first_name as student_first_name, s.last_name as student_last_name, s.username as student_username,
           f.first_name as faculty_first_name, f.last_name as faculty_last_name, f.username as faculty_username
    FROM grade_submissions gs
    JOIN users s ON gs.student_id = s.id
    JOIN users f ON gs.faculty_id = f.id
    WHERE $whereClause
    ORDER BY gs.submitted_at ASC
";

$result = fetchPaginated($query, $params, $page, $perPage);
$pendingSubmissions = $result['data'];
$pagination = $result['pagination'];

// Get statistics
$stats = fetchOne("
    SELECT 
        COUNT(*) as total_pending,
        COUNT(CASE WHEN DATE(gs.submitted_at) = CURDATE() THEN 1 END) as today_submissions,
        COUNT(CASE WHEN gs.submitted_at < DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 END) as overdue_submissions
    FROM grade_submissions gs
    WHERE gs.status = 'pending'
");

// Get filter options
$faculties = fetchAll("
    SELECT DISTINCT f.id, f.first_name, f.last_name
    FROM grade_submissions gs
    JOIN users f ON gs.faculty_id = f.id
    WHERE gs.status = 'pending'
    ORDER BY f.last_name, f.first_name
");

$subjects = fetchAll("
    SELECT DISTINCT gs.subject
    FROM grade_submissions gs
    WHERE gs.status = 'pending'
    ORDER BY gs.subject
");

$semesters = fetchAll("
    SELECT DISTINCT gs.semester
    FROM grade_submissions gs
    WHERE gs.status = 'pending' AND gs.semester IS NOT NULL
    ORDER BY gs.semester
");

function getGradeLevel($grade) {
    if ($grade >= 90) return 'excellent';
    if ($grade >= 85) return 'good';
    if ($grade >= 80) return 'fair';
    return 'poor';
}
?>

<div class="page-header">
    <div class="header-text">
        <h1><i class="fas fa-clipboard-check"></i> Pending Grade Approvals</h1>
        <p>Review and approve faculty-submitted grades</p>
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
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['total_pending'] ?></div>
            <div class="stats-label">Pending Approvals</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--info); color: white;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['today_submissions'] ?></div>
            <div class="stats-label">Today's Submissions</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= $stats['overdue_submissions'] > 0 ? 'var(--error)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-<?= $stats['overdue_submissions'] > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $stats['overdue_submissions'] ?></div>
            <div class="stats-label">Overdue (>3 days)</div>
        </div>
    </div>
</div>

<!-- Overdue Alert -->
<?php if ($stats['overdue_submissions'] > 0): ?>
<div class="alert alert-warning" style="margin-bottom: 2rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Attention!</strong>
        <p><?= $stats['overdue_submissions'] ?> grade submission(s) have been pending for more than 3 days. Please review them promptly.</p>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <form method="GET" class="filters-form">
        <div class="filter-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="faculty_id">Faculty</label>
                <select name="faculty_id" id="faculty_id">
                    <option value="">All Faculty</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= $faculty['id'] ?>" <?= $facultyId == $faculty['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject</label>
                <select name="subject" id="subject">
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
                <label for="semester">Semester</label>
                <select name="semester" id="semester">
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
                       placeholder="Student name or username...">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="/GLC_AIMS/registrar/pending_grade_approvals.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Pending Submissions Table -->
<div class="table-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
        <h3><i class="fas fa-table"></i> Pending Grade Submissions</h3>
        <div class="table-info">
            Showing <?= count($pendingSubmissions) ?> of <?= $pagination['total'] ?> pending submissions
        </div>
    </div>
    
    <?php if (empty($pendingSubmissions)): ?>
        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fas fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Pending Submissions</h3>
            <p>All grade submissions have been reviewed. Great job!</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Faculty</th>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Term</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSubmissions as $submission): ?>
                        <tr class="<?= (strtotime($submission['submitted_at']) < strtotime('-3 days')) ? 'overdue-row' : '' ?>">
                            <td>
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?= substr($submission['student_first_name'], 0, 1) . substr($submission['student_last_name'], 0, 1) ?>
                                    </div>
                                    <div class="student-details">
                                        <strong><?= htmlspecialchars($submission['student_first_name'] . ' ' . $submission['student_last_name']) ?></strong>
                                        <small>@<?= htmlspecialchars($submission['student_username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="faculty-info">
                                    <strong><?= htmlspecialchars($submission['faculty_first_name'] . ' ' . $submission['faculty_last_name']) ?></strong>
                                    <small>@<?= htmlspecialchars($submission['faculty_username']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="subject-info">
                                    <strong><?= htmlspecialchars($submission['subject']) ?></strong>
                                    <?php if ($submission['remarks']): ?>
                                        <small title="<?= htmlspecialchars($submission['remarks']) ?>">
                                            <i class="fas fa-comment-alt"></i> Has remarks
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="grade-display">
                                    <span class="grade-badge grade-<?= getGradeLevel($submission['grade']) ?>">
                                        <?= number_format($submission['grade'], 1) ?>%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="term-info">
                                    <div><?= htmlspecialchars($submission['semester'] ?: 'N/A') ?></div>
                                    <small><?= htmlspecialchars($submission['school_year'] ?: 'N/A') ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="date-info">
                                    <div><?= date('M j, Y', strtotime($submission['submitted_at'])) ?></div>
                                    <small><?= date('g:i A', strtotime($submission['submitted_at'])) ?></small>
                                    <?php if (strtotime($submission['submitted_at']) < strtotime('-3 days')): ?>
                                        <span class="overdue-badge">OVERDUE</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-sm btn-info" onclick="viewSubmissionDetails(<?= $submission['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-sm btn-success" onclick="approveSubmission(<?= $submission['id'] ?>)" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-sm btn-danger" onclick="rejectSubmission(<?= $submission['id'] ?>)" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
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
                    <?= $pagination['total'] ?> submissions
                </div>
                
                <div class="pagination-controls">
                    <?php
                    $queryParams = array_merge($_GET, ['page' => '']);
                    unset($queryParams['page']);
                    $baseUrl = '/GLC_AIMS/registrar/pending_grade_approvals.php?' . http_build_query($queryParams) . '&page=';
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

<!-- Approval Modal -->
<div id="approvalModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Approve Grade Submission</h3>
            <button class="modal-close" onclick="closeModal('approvalModal')">&times;</button>
        </div>
        <form method="POST" id="approvalForm">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="submission_id" id="approval_submission_id">
            
            <div class="modal-body">
                <div id="approval-details"></div>
                
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="approval_comments" class="form-label">Comments (Optional)</label>
                    <textarea name="comments" id="approval_comments" class="form-input" rows="3" 
                              placeholder="Optional comments about the approval..."></textarea>
                </div>
                
                <div class="confirmation-note" style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <i class="fas fa-info-circle" style="color: var(--success);"></i>
                    <strong style="color: var(--success);">Note:</strong> Approving this submission will add the grade to the student's official record and make it visible to them.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approvalModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve Grade
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectionModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle"></i> Reject Grade Submission</h3>
            <button class="modal-close" onclick="closeModal('rejectionModal')">&times;</button>
        </div>
        <form method="POST" id="rejectionForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="submission_id" id="rejection_submission_id">
            
            <div class="modal-body">
                <div id="rejection-details"></div>
                
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="rejection_comments" class="form-label">Reason for Rejection *</label>
                    <textarea name="comments" id="rejection_comments" class="form-input" rows="4" 
                              placeholder="Please provide a clear reason for rejecting this grade submission..." required></textarea>
                </div>
                
                <div class="warning-note" style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--error);"></i>
                    <strong style="color: var(--error);">Note:</strong> The faculty member will be notified of the rejection and can resubmit the grade after making corrections.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectionModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject Submission
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Submission Details</h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="details-modal-body">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('detailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
const submissions = <?= json_encode($pendingSubmissions) ?>;

function viewSubmissionDetails(submissionId) {
    const submission = submissions.find(s => s.id == submissionId);
    if (!submission) return;
    
    const modalBody = document.getElementById('details-modal-body');
    modalBody.innerHTML = `
        <div class="submission-details">
            <div class="detail-section">
                <h4><i class="fas fa-user-graduate"></i> Student Information</h4>
                <div class="detail-grid">
                    <div><strong>Name:</strong> ${submission.student_first_name} ${submission.student_last_name}</div>
                    <div><strong>Username:</strong> @${submission.student_username}</div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-chalkboard-teacher"></i> Faculty Information</h4>
                <div class="detail-grid">
                    <div><strong>Name:</strong> ${submission.faculty_first_name} ${submission.faculty_last_name}</div>
                    <div><strong>Username:</strong> @${submission.faculty_username}</div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-graduation-cap"></i> Grade Information</h4>
                <div class="detail-grid">
                    <div><strong>Subject:</strong> ${submission.subject}</div>
                    <div><strong>Grade:</strong> <span class="grade-badge grade-${getGradeLevelJS(submission.grade)}">${parseFloat(submission.grade).toFixed(1)}%</span></div>
                    <div><strong>Semester:</strong> ${submission.semester || 'N/A'}</div>
                    <div><strong>School Year:</strong> ${submission.school_year || 'N/A'}</div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-clock"></i> Submission Information</h4>
                <div class="detail-grid">
                    <div><strong>Submitted:</strong> ${new Date(submission.submitted_at).toLocaleString()}</div>
                    <div><strong>Days Pending:</strong> ${Math.floor((new Date() - new Date(submission.submitted_at)) / (1000 * 60 * 60 * 24))} days</div>
                </div>
            </div>
            
            ${submission.remarks ? `
                <div class="detail-section">
                    <h4><i class="fas fa-comment"></i> Faculty Remarks</h4>
                    <div class="remarks-text">${submission.remarks}</div>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('detailsModal').style.display = 'flex';
}

function approveSubmission(submissionId) {
    const submission = submissions.find(s => s.id == submissionId);
    if (!submission) return;
    
    document.getElementById('approval_submission_id').value = submissionId;
    document.getElementById('approval-details').innerHTML = `
        <div class="approval-summary">
            <h4>Approve Grade for:</h4>
            <div class="summary-info">
                <strong>Student:</strong> ${submission.student_first_name} ${submission.student_last_name}<br>
                <strong>Subject:</strong> ${submission.subject}<br>
                <strong>Grade:</strong> <span class="grade-badge grade-${getGradeLevelJS(submission.grade)}">${parseFloat(submission.grade).toFixed(1)}%</span><br>
                <strong>Faculty:</strong> ${submission.faculty_first_name} ${submission.faculty_last_name}
            </div>
        </div>
    `;
    
    document.getElementById('approvalModal').style.display = 'flex';
}

function rejectSubmission(submissionId) {
    const submission = submissions.find(s => s.id == submissionId);
    if (!submission) return;
    
    document.getElementById('rejection_submission_id').value = submissionId;
    document.getElementById('rejection-details').innerHTML = `
        <div class="rejection-summary">
            <h4>Reject Grade for:</h4>
            <div class="summary-info">
                <strong>Student:</strong> ${submission.student_first_name} ${submission.student_last_name}<br>
                <strong>Subject:</strong> ${submission.subject}<br>
                <strong>Grade:</strong> <span class="grade-badge grade-${getGradeLevelJS(submission.grade)}">${parseFloat(submission.grade).toFixed(1)}%</span><br>
                <strong>Faculty:</strong> ${submission.faculty_first_name} ${submission.faculty_last_name}
            </div>
        </div>
    `;
    
    document.getElementById('rejectionModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    // Clear form data
    if (modalId === 'approvalModal') {
        document.getElementById('approval_comments').value = '';
    } else if (modalId === 'rejectionModal') {
        document.getElementById('rejection_comments').value = '';
    }
}

function getGradeLevelJS(grade) {
    if (grade >= 90) return 'excellent';
    if (grade >= 85) return 'good';
    if (grade >= 80) return 'fair';
    return 'poor';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        const modalId = e.target.id;
        closeModal(modalId);
    }
});

// Form validation
document.getElementById('rejectionForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('rejection_comments').value.trim();
    if (reason.length < 10) {
        e.preventDefault();
        alert('Please provide a detailed reason for rejection (at least 10 characters).');
    }
});
</script>