<?php
// registrar/upload_grades.php - Enhanced Grade Upload with Searchable Dropdown
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

// Initialize variables
$message = '';
$messageType = '';
$selectedStudentId = $_GET['student_id'] ?? '';

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $grade = trim($_POST['grade'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $schoolYear = trim($_POST['school_year'] ?? '');
    $remarks = trim($_POST['remarks'] ?? ''); // Keep for form but won't save to DB
    
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
        // Verify student exists and is active
        $student = fetchOne("SELECT id, first_name, last_name, username FROM users WHERE id = ? AND role_id = 4 AND is_active = 1", [$studentId]);
        
        if (!$student) {
            $message = 'Invalid student selected.';
            $messageType = 'error';
        } else {
            // Check for duplicate grade entry
            $existing = fetchOne("
                SELECT g.id FROM grades g 
                WHERE g.user_id = ? AND g.subject = ? AND g.semester = ? AND g.school_year = ?
            ", [$studentId, $subject, $semester, $schoolYear]);
            
            if ($existing) {
                $message = 'A grade for this subject, semester, and school year already exists for this student.';
                $messageType = 'error';
            } else {
                // Insert new grade
                $result = executeUpdate(
                    "INSERT INTO grades (user_id, subject, grade, semester, school_year, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$studentId, $subject, (float)$grade, $semester, $schoolYear]
                );
                
                if ($result) {
                    $message = "Grade successfully added for {$student['first_name']} {$student['last_name']} in {$subject}.";
                    $messageType = 'success';
                    
                    // Clear form after successful submission
                    $selectedStudentId = '';
                } else {
                    $message = 'Failed to save grade. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get all active students for dropdown with additional info
$students = fetchAll("
    SELECT id, username, first_name, last_name, 
           CONCAT(first_name, ' ', last_name) as full_name,
           (SELECT COUNT(*) FROM grades WHERE user_id = users.id) as grade_count,
           (SELECT AVG(grade) FROM grades WHERE user_id = users.id) as avg_grade
    FROM users 
    WHERE role_id = 4 AND is_active = 1
    ORDER BY last_name, first_name
");

// Get recent grades added today
$recentGrades = fetchAll("
    SELECT g.*, u.first_name, u.last_name, u.username
    FROM grades g
    JOIN users u ON g.user_id = u.id
    WHERE DATE(g.created_at) = CURDATE()
    ORDER BY g.created_at DESC
    LIMIT 10
");

// Get grade statistics
$gradeStats = fetchOne("
    SELECT 
        COUNT(*) as total_grades,
        COUNT(DISTINCT g.user_id) as students_with_grades,
        COUNT(DISTINCT g.subject) as total_subjects,
        AVG(g.grade) as average_grade,
        COUNT(CASE WHEN DATE(g.created_at) = CURDATE() THEN 1 END) as today_grades
    FROM grades g
    JOIN users u ON g.user_id = u.id
    WHERE u.role_id = 4
");

// Get common subjects for quick selection
$commonSubjects = fetchAll("
    SELECT g.subject, COUNT(*) as usage_count
    FROM grades g
    JOIN users u ON g.user_id = u.id
    WHERE u.role_id = 4
    GROUP BY g.subject
    ORDER BY usage_count DESC, g.subject ASC
    LIMIT 10
");

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
        <h1><i class="fas fa-graduation-cap"></i> Upload Student Grades</h1>
        <p>Add and manage student academic grades using Golden Link College grading system</p>
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

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['total_grades'] ?? 0) ?></div>
            <div class="stats-label">Total Grades</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['students_with_grades'] ?? 0) ?></div>
            <div class="stats-label">Students with Grades</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-book"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['total_subjects'] ?? 0) ?></div>
            <div class="stats-label">Total Subjects</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= ($gradeStats['average_grade'] ?? 0) >= 85 ? 'var(--success)' : 'var(--info)' ?>; color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['average_grade'] ?? 0, 1) ?></div>
            <div class="stats-label">Average Grade</div>
        </div>
    </div>
</div>

<!-- GLC Grading System Reference -->
<div class="grading-system-ref" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
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

<!-- Grade Upload Form -->
<div class="upload-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <div class="section-header" style="margin-bottom: 2rem;">
        <h3><i class="fas fa-plus-circle"></i> Add New Grade</h3>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Select a student and enter their academic grade information.</p>
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
                                <?php foreach ($students as $student): ?>
                                    <div class="select-option" 
                                         data-value="<?= $student['id'] ?>"
                                         data-grades="<?= $student['grade_count'] ?>"
                                         data-avg="<?= number_format($student['avg_grade'] ?? 0, 1) ?>"
                                         onclick="selectStudent(this)">
                                        <div class="option-main">
                                            <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                            <small>@<?= htmlspecialchars($student['username']) ?></small>
                                        </div>
                                        <div class="option-stats">
                                            <?= $student['grade_count'] ?> grades • Avg: <?= number_format($student['avg_grade'] ?? 0, 1) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="student-info" class="student-info-display" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #f8fafc; border-radius: 6px; font-size: 0.9rem;">
                    <span id="student-grades-count"></span> grades recorded • Average: <span id="student-avg-grade"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject" class="form-label">
                    <i class="fas fa-book"></i> Subject *
                </label>
                <input type="text" name="subject" id="subject" class="form-input" 
                       placeholder="Enter subject name..." required list="common-subjects">
                <datalist id="common-subjects">
                    <?php foreach ($commonSubjects as $subj): ?>
                        <option value="<?= htmlspecialchars($subj['subject']) ?>">
                    <?php endforeach; ?>
                </datalist>
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
                <div id="grade-preview" class="grade-preview" style="margin-top: 0.5rem;">
                    <div class="grade-conversion" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; font-size: 0.85rem; font-weight: 500;">
                        <div>Point: <span id="point-value">-</span></div>
                        <div>Letter: <span id="letter-grade">-</span></div>
                        <div>Rating: <span id="adjective-rating">-</span></div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="semester" class="form-label">
                    <i class="fas fa-calendar-alt"></i> Semester
                </label>
                <select name="semester" id="semester" class="form-input form-select">
                    <option value="">Select Semester</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer">Summer</option>
                    <option value="Intersession">Intersession</option>
                </select>
            </div>
        </div>
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="form-group">
                <label for="school_year" class="form-label">
                    <i class="fas fa-calendar"></i> School Year
                </label>
                <input type="text" name="school_year" id="school_year" class="form-input" 
                       placeholder="e.g. 2024-2025" value="<?= date('Y') . '-' . (date('Y') + 1) ?>">
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-start;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Grade
            </button>
            <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                <i class="fas fa-redo"></i> Clear Form
            </button>
        </div>
    </form>
</div>

<!-- Recent Grades -->
<?php if (!empty($recentGrades)): ?>
<div class="recent-grades" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <h3><i class="fas fa-history"></i> Recent Grades Added (Today)</h3>
        <div class="grade-count-badge" style="background: var(--success); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500;">
            <?= count($recentGrades) ?> grades added today
        </div>
    </div>
    
    <div class="grades-list">
        <?php foreach ($recentGrades as $grade): ?>
            <div class="grade-item" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #eee; transition: background 0.2s ease;">
                <div class="student-grade-info" style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                    <div class="student-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent-yellow); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--primary-blue);">
                        <?= substr($grade['first_name'], 0, 1) . substr($grade['last_name'], 0, 1) ?>
                    </div>
                    <div class="grade-details">
                        <h4 style="margin: 0; font-size: 1rem; color: var(--text-dark);">
                            <?= htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) ?>
                        </h4>
                        <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-light);">
                            <strong><?= htmlspecialchars($grade['subject']) ?></strong>
                            <?php if ($grade['semester']): ?>
                                • <?= htmlspecialchars($grade['semester']) ?>
                            <?php endif; ?>
                            <?php if ($grade['school_year']): ?>
                                • <?= htmlspecialchars($grade['school_year']) ?>
                            <?php endif; ?>
                            • <?= date('g:i A', strtotime($grade['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <div class="grade-display">
                    <div class="grade-info-compact">
                        <span class="grade-badge grade-<?= getGradeLevel($grade['grade']) ?>" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">
                            <?= number_format($grade['grade'], 1) ?>%
                        </span>
                        <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.2rem; text-align: center;">
                            <?= getGLCLetterGrade($grade['grade']) ?> (<?= getGLCGradePoint($grade['grade']) ?>)
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="/GLC_AIMS/registrar/grade_reports.php" class="btn btn-secondary">
            <i class="fas fa-chart-bar"></i> View All Grades & Reports
        </a>
    </div>
</div>
<?php endif; ?>

<script>
let isDropdownOpen = false;
let allStudents = <?= json_encode($students) ?>;

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
        // Focus on search input
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
            ? 'flex' : 'none';
    });
}

function selectStudent(optionEl) {
    const studentId = optionEl.getAttribute('data-value');
    const grades = optionEl.getAttribute('data-grades');
    const avg = optionEl.getAttribute('data-avg');
    const name = optionEl.querySelector('strong').innerText;
    
    // Set hidden input
    document.getElementById('student_id').value = studentId;
    
    // Update display text
    document.getElementById('student-selected-text').innerText = name;
    
    // Show student info
    document.getElementById('student-info').style.display = 'block';
    document.getElementById('student-grades-count').innerText = grades;
    document.getElementById('student-avg-grade').innerText = avg;
    
    // Close dropdown
    toggleDropdown('student-select');
}

function updateGradePreview(value) {
    const grade = parseFloat(value);
    if (isNaN(grade)) {
        document.getElementById('point-value').innerText = '-';
        document.getElementById('letter-grade').innerText = '-';
        document.getElementById('adjective-rating').innerText = '-';
        return;
    }
    
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
    document.getElementById('student-selected-text').innerText = 'Choose a student...';
    document.getElementById('student-info').style.display = 'none';
    document.getElementById('point-value').innerText = '-';
    document.getElementById('letter-grade').innerText = '-';
    document.getElementById('adjective-rating').innerText = '-';
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
    
    .grading-system-ref {
        border-left: 4px solid var(--accent-yellow);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .student-search {
        border: 2px dashed var(--border-gray);
        border-radius: 10px;
        padding: 1.5rem;
        background: #fafafa;
    }
    
    .search-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .search-results {
        border-left: 3px solid var(--info);
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
        background: var(--white);
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
    
    .student-info-display {
        border-left: 3px solid var(--primary-blue);
    }
    
    .grade-preview {
        transition: all 0.3s ease;
    }
    
    .grade-conversion {
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 6px;
        border: 1px solid var(--border-gray);
    }
    
    .grade-item:hover {
        background: #f9fafb;
    }
    
    .grade-item:last-child {
        border-bottom: none;
    }
    
    .grade-badge {
        display: inline-block;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .grade-excellent {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .grade-good {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info);
    }
    
    .grade-fair {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .grade-poor {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }
    
    .grade-count-badge {
        flex-shrink: 0;
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
        background: var(--text-light);
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
            grid-template-columns: 1fr !important;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .grade-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .student-grade-info {
            width: 100%;
        }
        
        .search-actions {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .upload-section, .recent-grades, .grading-system-ref {
            padding: 1rem;
        }
        
        .stats-card {
            flex-direction: column;
            text-align: center;
            gap: 0.5rem;
        }
        
        .student-search {
            padding: 1rem;
        }
        
        .grade-conversion {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
    }
</style>

<?php require __DIR__ . "/../shared/footer.php"; ?>
