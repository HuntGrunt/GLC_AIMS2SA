<?php
// faculty/submit_grades.php - Enhanced Faculty Grade Submission
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Faculty', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$selectedStudentId = $_GET['student_id'] ?? '';
$selectedSubject = $_GET['subject'] ?? '';

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $grade = trim($_POST['grade'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $schoolYear = trim($_POST['school_year'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Validation
    if ($studentId <= 0) {
        $message = 'Please select a student.';
        $messageType = 'error';
    } elseif (empty($subject)) {
        $message = 'Please enter a subject name.';
        $messageType = 'error';
    } elseif (empty($grade) || !is_numeric($grade)) {
        $message = 'Please enter a valid grade.';
        $messageType = 'error';
    } elseif ($grade < 0 || $grade > 100) {
        $message = 'Grade must be between 0 and 100.';
        $messageType = 'error';
    } else {
        // Verify student is assigned to this faculty
        $assignment = fetchOne("
            SELECT sa.id FROM subject_assignments sa
            WHERE sa.faculty_id = ? AND sa.student_id = ? AND sa.subject_name = ? AND sa.is_active = 1
        ", [$userId, $studentId, $subject]);
        
        if (!$assignment) {
            $message = 'You are not authorized to submit grades for this student in this subject.';
            $messageType = 'error';
        } else {
            // Check for existing pending or approved submission
            $existing = fetchOne("
                SELECT gs.id, gs.status FROM grade_submissions gs 
                WHERE gs.faculty_id = ? AND gs.student_id = ? AND gs.subject = ? 
                AND gs.semester = ? AND gs.school_year = ? AND gs.status IN ('pending', 'approved')
            ", [$userId, $studentId, $subject, $semester, $schoolYear]);
            
            if ($existing) {
                $statusText = $existing['status'] === 'pending' ? 'pending approval' : 'already approved';
                $message = "A grade submission for this student, subject, and term is $statusText.";
                $messageType = 'error';
            } else {
                // Insert new grade submission
                $result = executeUpdate(
                    "INSERT INTO grade_submissions (faculty_id, student_id, subject, grade, semester, school_year, remarks, status, submitted_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                    [$userId, $studentId, $subject, (float)$grade, $semester, $schoolYear, $remarks]
                );
                
                if ($result) {
                    $student = fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$studentId]);
                    $message = "Grade submission sent to registrar for approval: {$student['first_name']} {$student['last_name']} - {$subject} - {$grade}%";
                    $messageType = 'success';
                    
                    // Clear form
                    $selectedStudentId = '';
                    $selectedSubject = '';
                } else {
                    $message = 'Failed to submit grade. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get faculty's assigned students and subjects
$assignments = fetchAll("
    SELECT DISTINCT 
        sa.student_id,
        sa.subject_name,
        u.first_name,
        u.last_name,
        u.username,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        sa.semester,
        sa.school_year,
        (SELECT COUNT(*) FROM grade_submissions gs 
         WHERE gs.faculty_id = sa.faculty_id 
         AND gs.student_id = sa.student_id 
         AND gs.subject = sa.subject_name
         AND gs.status = 'approved') as approved_grades,
        (SELECT COUNT(*) FROM grade_submissions gs 
         WHERE gs.faculty_id = sa.faculty_id 
         AND gs.student_id = sa.student_id 
         AND gs.subject = sa.subject_name
         AND gs.status = 'pending') as pending_grades
    FROM subject_assignments sa
    JOIN users u ON sa.student_id = u.id
    WHERE sa.faculty_id = ? AND sa.is_active = 1 AND u.is_active = 1
    ORDER BY u.last_name, u.first_name, sa.subject_name
", [$userId]);

// Group assignments by student
$studentAssignments = [];
foreach ($assignments as $assignment) {
    $studentId = $assignment['student_id'];
    if (!isset($studentAssignments[$studentId])) {
        $studentAssignments[$studentId] = [
            'info' => $assignment,
            'subjects' => []
        ];
    }
    $studentAssignments[$studentId]['subjects'][] = $assignment;
}

// Get recent submissions
$recentSubmissions = fetchAll("
    SELECT gs.*, u.first_name, u.last_name, u.username
    FROM grade_submissions gs
    JOIN users u ON gs.student_id = u.id
    WHERE gs.faculty_id = ?
    ORDER BY gs.submitted_at DESC
    LIMIT 8
", [$userId]);

// Get unique subjects and terms for dropdowns
$subjects = fetchAll("
    SELECT DISTINCT subject_name
    FROM subject_assignments 
    WHERE faculty_id = ? AND is_active = 1
    ORDER BY subject_name
", [$userId]);

$terms = fetchAll("
    SELECT DISTINCT semester, school_year
    FROM subject_assignments 
    WHERE faculty_id = ? AND is_active = 1
    ORDER BY school_year DESC, semester
", [$userId]);

// GLC Grading System Functions
function getGLCGradePoint($percentage) {
    if ($percentage >= 99) return 1.0;
    if ($percentage >= 96) return 1.25;
    if ($percentage >= 93) return 1.5;
    if ($percentage >= 90) return 1.75;
    if ($percentage >= 87) return 2.0;
    if ($percentage >= 84) return 2.25;
    if ($percentage >= 81) return 2.5;
    if ($percentage >= 78) return 2.75;
    if ($percentage >= 75) return 3.0;
    if ($percentage >= 70) return 4.0;
    return 5.0;
}

function getGLCLetterGrade($percentage) {
    if ($percentage >= 99) return 'A+';
    if ($percentage >= 96) return 'A';
    if ($percentage >= 93) return 'A-';
    if ($percentage >= 90) return 'B+';
    if ($percentage >= 87) return 'B';
    if ($percentage >= 84) return 'B-';
    if ($percentage >= 81) return 'C+';
    if ($percentage >= 78) return 'C';
    if ($percentage >= 75) return 'C-';
    if ($percentage >= 70) return 'D';
    return 'F';
}

function getGLCAdjectiveRating($percentage) {
    if ($percentage >= 99) return 'Excellent +';
    if ($percentage >= 96) return 'Excellent -';
    if ($percentage >= 93) return 'Very Good +';
    if ($percentage >= 90) return 'Very Good -';
    if ($percentage >= 87) return 'Good +';
    if ($percentage >= 84) return 'Good -';
    if ($percentage >= 81) return 'Fair +';
    if ($percentage >= 78) return 'Fair -';
    if ($percentage >= 75) return 'Passed';
    if ($percentage >= 70) return 'Conditional';
    return 'Failed';
}

function getGradeLevel($grade) {
    if ($grade >= 90) return 'excellent';
    if ($grade >= 85) return 'good';
    if ($grade >= 80) return 'fair';
    return 'poor';
}
?>

<div class="page-header">
        <div class="header-text">
            <h1><i class="fas fa-plus-circle"></i> Submit Student Grades</h1>
            <p>Submit grades for registrar approval using Golden Link College grading system</p>
        </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" style="margin-bottom: 2rem;">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
    <div>
        <strong><?= $messageType === 'success' ? 'Success!' : 'Error!' ?></strong>
        <p><?= htmlspecialchars($message) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- GLC Grading System Reference -->
<div class="grading-system-ref" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem; border-left: 4px solid var(--accent-yellow);">
    <div class="section-header" style="margin-bottom: 1rem;">
        <h3><i class="fas fa-info-circle"></i> Golden Link College Grading System</h3>
        <button onclick="toggleGradingSystem()" class="btn-link" id="grading-toggle">
            <i class="fas fa-chevron-down"></i> Show Details
        </button>
    </div>
    
    <div id="grading-system-details" style="display: none;">
        <div class="table-responsive">
            <table class="grading-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 0.75rem; border: 1px solid #dee2e6;">Point System</th>
                        <th style="padding: 0.75rem; border: 1px solid #dee2e6;">% Equivalents</th>
                        <th style="padding: 0.75rem; border: 1px solid #dee2e6;">Letter Grade</th>
                        <th style="padding: 0.75rem; border: 1px solid #dee2e6;">Adjective Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">1.0</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">99-100</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">A+</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Excellent +</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">1.25</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">96-98</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">A</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Excellent -</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">1.5</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">93-95</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">A-</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Very Good +</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">1.75</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">90-92</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">B+</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Very Good -</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">2.0</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">87-89</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">B</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Good +</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">2.25</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">84-86</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">B-</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Good -</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">2.5</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">81-83</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">C+</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Fair +</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">2.75</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">78-80</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">C</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Fair -</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">3.0</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">75-77</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">C-</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Passed</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">4.0</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">70-74</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">D</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Conditional</td></tr>
                    <tr><td style="padding: 0.5rem; border: 1px solid #dee2e6;">5.0</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Below 70</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">F</td><td style="padding: 0.5rem; border: 1px solid #dee2e6;">Failed</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Grade Submission Form -->
<div class="submission-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <div class="section-header" style="margin-bottom: 2rem;">
        <h3><i class="fas fa-plus-circle"></i> Submit Grade</h3>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Enter grade information for registrar approval.</p>
    </div>
    
    <form method="POST" class="grade-form">
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div class="form-group">
                <label for="student_id" class="form-label">
                    <i class="fas fa-user-graduate"></i> Select Student *
                </label>
                <div class="searchable-select-container">
                    <input type="hidden" name="student_id" id="student_id" value="<?= htmlspecialchars($selectedStudentId) ?>">
                    <div class="searchable-select" id="student-select">
                        <div class="select-display" onclick="toggleDropdown('student-select')">
                            <span class="select-text" id="student-selected-text">Choose a student...</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search students..." onkeyup="filterStudents(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="select-options" id="student-options">
                                <?php foreach ($studentAssignments as $studentId => $data): ?>
                                    <div class="select-option" 
                                         data-value="<?= $studentId ?>"
                                         data-subjects="<?= htmlspecialchars(json_encode(array_column($data['subjects'], 'subject_name'))) ?>"
                                         onclick="selectStudent(this)">
                                        <div class="option-main">
                                            <strong><?= htmlspecialchars($data['info']['full_name']) ?></strong>
                                            <small>@<?= htmlspecialchars($data['info']['username']) ?></small>
                                        </div>
                                        <div class="option-stats" style="font-size: 0.8rem; color: var(--text-light);">
                                            <?= count($data['subjects']) ?> subject(s) • 
                                            <?= array_sum(array_column($data['subjects'], 'approved_grades')) ?> approved grades
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject" class="form-label">
                    <i class="fas fa-book"></i> Subject *
                </label>
                <select name="subject" id="subject" class="form-input form-select" required>
                    <option value="">Select Subject</option>
                </select>
                <small class="form-help" style="color: var(--text-light); font-size: 0.85rem; margin-top: 0.3rem;">
                    Only subjects assigned to the selected student will appear
                </small>
            </div>
        </div>
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div class="form-group">
                <label for="grade" class="form-label">
                    <i class="fas fa-star"></i> Grade (Percentage) *
                </label>
                <input type="number" name="grade" id="grade" class="form-input" 
                       step="0.01" min="0" max="100" placeholder="0.00" required
                       oninput="updateGradePreview(this.value)">
                <div id="grade-preview" class="grade-preview" style="margin-top: 0.5rem; display: none;">
                    <div class="grade-conversion" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; font-size: 0.85rem; font-weight: 500; padding: 0.75rem; background: #f8fafc; border-radius: 6px; border: 1px solid var(--border-gray);">
                        <div><strong>Point:</strong> <span id="point-value">-</span></div>
                        <div><strong>Letter:</strong> <span id="letter-grade">-</span></div>
                        <div><strong>Rating:</strong> <span id="adjective-rating">-</span></div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="semester" class="form-label">
                    <i class="fas fa-calendar-alt"></i> Semester *
                </label>
                <select name="semester" id="semester" class="form-input form-select" required>
                    <option value="">Select Semester</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer">Summer</option>
                    <option value="Intersession">Intersession</option>
                </select>
            </div>
        </div>
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div class="form-group">
                <label for="school_year" class="form-label">
                    <i class="fas fa-calendar"></i> School Year *
                </label>
                <input type="text" name="school_year" id="school_year" class="form-input" 
                       placeholder="e.g. 2024-2025" value="<?= date('Y') . '-' . (date('Y') + 1) ?>" required>
            </div>
        </div>
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="form-group">
                <label for="remarks" class="form-label">
                    <i class="fas fa-comment"></i> Remarks (Optional)
                </label>
                <textarea name="remarks" id="remarks" class="form-input" rows="3" 
                          placeholder="Optional comments about the student's performance..."></textarea>
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-start;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Submit for Approval
            </button>
            <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                <i class="fas fa-redo"></i> Clear Form
            </button>
        </div>
    </form>
</div>

<!-- Recent Submissions -->
<?php if (!empty($recentSubmissions)): ?>
<div class="recent-submissions" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <h3><i class="fas fa-history"></i> Recent Submissions</h3>
        <a href="/GLC_AIMS/faculty/my_submissions.php" class="btn-link">View All</a>
    </div>
    
    <div class="submissions-grid" style="display: grid; gap: 1rem;">
        <?php foreach ($recentSubmissions as $submission): ?>
            <div class="submission-item" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; transition: all 0.2s ease;">
                <div class="submission-info" style="flex: 1;">
                    <div class="student-name" style="font-weight: 600; color: var(--text-dark);">
                        <?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?>
                    </div>
                    <div class="submission-details" style="font-size: 0.9rem; color: var(--text-light); margin-top: 0.2rem;">
                        <strong><?= htmlspecialchars($submission['subject']) ?></strong>
                        <?php if ($submission['semester']): ?>
                            • <?= htmlspecialchars($submission['semester']) ?>
                        <?php endif; ?>
                        <?php if ($submission['school_year']): ?>
                            • <?= htmlspecialchars($submission['school_year']) ?>
                        <?php endif; ?>
                        • Submitted <?= date('M j, Y g:i A', strtotime($submission['submitted_at'])) ?>
                    </div>
                </div>
                <div class="submission-status" style="text-align: right;">
                    <div class="grade-badge grade-<?= getGradeLevel($submission['grade']) ?>" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem; display: inline-block; margin-bottom: 0.3rem;">
                        <?= number_format($submission['grade'], 1) ?>%
                    </div>
                    <br>
                    <span class="status-badge status-<?= $submission['status'] ?>" style="padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                        <?= ucfirst($submission['status']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
let isDropdownOpen = false;
let studentSubjects = {};

function toggleDropdown(selectId) {
    const select = document.getElementById(selectId);
    const dropdown = select.querySelector('.select-dropdown');
    const arrow = select.querySelector('.select-arrow');
    
    if (isDropdownOpen) {
        dropdown.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
        isDropdownOpen = false;
    } else {
        dropdown.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
        isDropdownOpen = true;
        const searchInput = dropdown.querySelector('input[type="text"]');
        if (searchInput) searchInput.focus();
    }
}

function filterStudents(query) {
    const optionsContainer = document.getElementById('student-options');
    const lowerQuery = query.toLowerCase();
    
    Array.from(optionsContainer.children).forEach(option => {
        const name = option.querySelector('strong').innerText.toLowerCase();
        const username = option.querySelector('small').innerText.toLowerCase();
        option.style.display = (name.includes(lowerQuery) || username.includes(lowerQuery)) 
            ? 'block' : 'none';
    });
}

function selectStudent(optionEl) {
    const studentId = optionEl.getAttribute('data-value');
    const studentName = optionEl.querySelector('strong').textContent;
    const subjects = JSON.parse(optionEl.getAttribute('data-subjects'));
    
    // Update hidden input
    document.getElementById('student_id').value = studentId;
    
    // Update display
    document.getElementById('student-selected-text').innerHTML = 
        `<strong>${studentName}</strong>`;
    
    // Update subject dropdown
    const subjectSelect = document.getElementById('subject');
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    
    subjects.forEach(subject => {
        const option = document.createElement('option');
        option.value = subject;
        option.textContent = subject;
        if (subject === '<?= htmlspecialchars($selectedSubject) ?>') {
            option.selected = true;
        }
        subjectSelect.appendChild(option);
    });
    
    // Close dropdown
    toggleDropdown('student-select');
}

function updateGradePreview(value) {
    const grade = parseFloat(value);
    const preview = document.getElementById('grade-preview');
    
    if (isNaN(grade) || value === '') {
        preview.style.display = 'none';
        return;
    }
    
    preview.style.display = 'block';
    
    let point = '-';
    let letter = '-';
    let adjective = '-';
    
    if (grade >= 99) { point = "1.0"; letter = "A+"; adjective = "Excellent +"; }
    else if (grade >= 96) { point = "1.25"; letter = "A"; adjective = "Excellent -"; }
    else if (grade >= 93) { point = "1.5"; letter = "A-"; adjective = "Very Good +"; }
    else if (grade >= 90) { point = "1.75"; letter = "B+"; adjective = "Very Good -"; }
    else if (grade >= 87) { point = "2.0"; letter = "B"; adjective = "Good +"; }
    else if (grade >= 84) { point = "2.25"; letter = "B-"; adjective = "Good -"; }
    else if (grade >= 81) { point = "2.5"; letter = "C+"; adjective = "Fair +"; }
    else if (grade >= 78) { point = "2.75"; letter = "C"; adjective = "Fair -"; }
    else if (grade >= 75) { point = "3.0"; letter = "C-"; adjective = "Passed"; }
    else if (grade >= 70) { point = "4.0"; letter = "D"; adjective = "Conditional"; }
    else { point = "5.0"; letter = "F"; adjective = "Failed"; }
    
    document.getElementById('point-value').innerText = point;
    document.getElementById('letter-grade').innerText = letter;
    document.getElementById('adjective-rating').innerText = adjective;
}

function resetForm() {
    document.getElementById('student_id').value = '';
    document.getElementById('student-selected-text').textContent = 'Choose a student...';
    document.getElementById('subject').innerHTML = '<option value="">Select Subject</option>';
    document.getElementById('grade-preview').style.display = 'none';
    
    // Reset other form fields
    document.getElementById('grade').value = '';
    document.getElementById('semester').value = '';
    document.getElementById('school_year').value = '<?= date('Y') . '-' . (date('Y') + 1) ?>';
    document.getElementById('remarks').value = '';
}

function toggleGradingSystem() {
    const details = document.getElementById('grading-system-details');
    const toggleBtn = document.getElementById('grading-toggle');
    if (details.style.display === 'none') {
        details.style.display = 'block';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
    } else {
        details.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show Details';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const select = document.getElementById('student-select');
    if (isDropdownOpen && !select.contains(event.target)) {
        toggleDropdown('student-select');
    }
});

// Auto-focus and initialization
document.addEventListener('DOMContentLoaded', function() {
    const studentId = document.getElementById('student_id').value;
    if (studentId) {
        const option = document.querySelector(`[data-value="${studentId}"]`);
        if (option) {
            selectStudent(option);
        }
    }
});
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
    
    /* Searchable Select Styles */
    .searchable-select-container {
        position: relative;
    }
    
    .searchable-select {
        position: relative;
        width: 100%;
    }
    
    .select-display {
        padding: 0.8rem 1rem;
        border: 2px solid var(--border-gray);
        border-radius: 8px;
        background: white;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        min-height: 48px;
    }
    
    .select-display:hover {
        border-color: var(--primary-blue);
    }
    
    .select-text {
        flex: 1;
        font-size: 0.95rem;
    }
    
    .select-arrow {
        color: var(--text-light);
        transition: transform 0.3s ease;
    }
    
    .select-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid var(--border-gray);
        border-top: none;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: none;
        max-height: 300px;
        overflow: hidden;
    }
    
    .select-search {
        position: relative;
        padding: 0.75rem;
        border-bottom: 1px solid var(--border-gray);
        background: #f8f9fa;
    }
    
    .select-search input {
        width: 100%;
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        border: 1px solid var(--border-gray);
        border-radius: 6px;
        font-size: 0.9rem;
        outline: none;
    }
    
    .select-search input:focus {
        border-color: var(--primary-blue);
    }
    
    .select-search i {
        position: absolute;
        right: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }
    
    .select-options {
        max-height: 250px;
        overflow-y: auto;
    }
    
    .select-option {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s ease;
    }
    
    .select-option:hover {
        background-color: #f8f9fa;
    }
    
    .select-option:last-child {
        border-bottom: none;
    }
    
    .option-main {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.2rem;
    }
    
    .option-main strong {
        color: var(--text-dark);
        font-size: 0.95rem;
    }
    
    .option-main small {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .form-row {
        display: grid;
        gap: 1.5rem;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--text-dark);
        font-size: 0.95rem;
    }
    
    .form-label i {
        margin-right: 0.5rem;
        color: var(--primary-blue);
    }
    
    .form-input {
        padding: 0.8rem 1rem;
        border: 2px solid var(--border-gray);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.75rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 3rem;
    }
    
    .submission-item:hover {
        background: #f9fafb;
        border-color: var(--primary-blue);
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
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-text h1 {
            font-size: 1.5rem;
        }
        
        .submission-section,
        .recent-submissions {
            padding: 1rem;
        }
        
        .submission-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .grade-conversion {
            grid-template-columns: 1fr !important;
            gap: 0.5rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>