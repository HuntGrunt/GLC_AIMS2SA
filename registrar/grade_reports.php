<?php
// registrar/grade_reports.php - Fixed Grade Reports and Analytics
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

// Get filter parameters
$reportType = $_GET['report_type'] ?? 'summary';
$semester = $_GET['semester'] ?? '';
$schoolYear = $_GET['school_year'] ?? '';
$studentId = (int)($_GET['student_id'] ?? 0);
$subject = $_GET['subject'] ?? '';

// Get available semesters and school years
$semesters = fetchAll("
    SELECT DISTINCT semester 
    FROM grades 
    WHERE semester IS NOT NULL AND semester != ''
    ORDER BY semester
");

$schoolYears = fetchAll("
    SELECT DISTINCT school_year 
    FROM grades 
    WHERE school_year IS NOT NULL AND school_year != ''
    ORDER BY school_year DESC
");

$subjects = fetchAll("
    SELECT DISTINCT subject 
    FROM grades 
    ORDER BY subject
");

// Build WHERE conditions
$whereConditions = ["1=1"];
$params = [];

if (!empty($semester)) {
    $whereConditions[] = "g.semester = ?";
    $params[] = $semester;
}

if (!empty($schoolYear)) {
    $whereConditions[] = "g.school_year = ?";
    $params[] = $schoolYear;
}

if ($studentId > 0) {
    $whereConditions[] = "g.user_id = ?";
    $params[] = $studentId;
}

if (!empty($subject)) {
    $whereConditions[] = "g.subject = ?";
    $params[] = $subject;
}

$whereClause = implode(' AND ', $whereConditions);

// Generate different types of reports
$reportData = [];

switch ($reportType) {
    case 'summary':
        // Overall grade statistics
        $reportData = [
            'stats' => fetchOne("
                SELECT 
                    COUNT(*) as total_grades,
                    COUNT(DISTINCT g.user_id) as total_students,
                    COUNT(DISTINCT g.subject) as total_subjects,
                    AVG(g.grade) as average_grade,
                    MIN(g.grade) as lowest_grade,
                    MAX(g.grade) as highest_grade,
                    COUNT(CASE WHEN g.grade >= 90 THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN g.grade >= 85 AND g.grade < 90 THEN 1 END) as good_count,
                    COUNT(CASE WHEN g.grade >= 80 AND g.grade < 85 THEN 1 END) as fair_count,
                    COUNT(CASE WHEN g.grade < 80 THEN 1 END) as poor_count
                FROM grades g
                JOIN users u ON g.user_id = u.id
                WHERE u.role_id = 4 AND $whereClause
            ", $params),
            
            'by_semester' => fetchAll("
                SELECT 
                    g.semester,
                    g.school_year,
                    COUNT(*) as grade_count,
                    COUNT(DISTINCT g.user_id) as student_count,
                    AVG(g.grade) as avg_grade,
                    MIN(g.grade) as min_grade,
                    MAX(g.grade) as max_grade
                FROM grades g
                JOIN users u ON g.user_id = u.id
                WHERE u.role_id = 4 AND $whereClause
                GROUP BY g.semester, g.school_year
                ORDER BY g.school_year DESC, g.semester
            ", $params),
            
            'by_subject' => fetchAll("
                SELECT 
                    g.subject,
                    COUNT(*) as grade_count,
                    COUNT(DISTINCT g.user_id) as student_count,
                    AVG(g.grade) as avg_grade,
                    MIN(g.grade) as min_grade,
                    MAX(g.grade) as max_grade
                FROM grades g
                JOIN users u ON g.user_id = u.id
                WHERE u.role_id = 4 AND $whereClause
                GROUP BY g.subject
                ORDER BY g.subject
            ", $params)
        ];
        break;
        
    case 'student_list':
        // Student grade listing with fixed student names
        $reportData = fetchAll("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.username,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                COUNT(g.id) as total_grades,
                AVG(g.grade) as average_grade,
                MIN(g.grade) as lowest_grade,
                MAX(g.grade) as highest_grade,
                COUNT(CASE WHEN g.grade >= 90 THEN 1 END) as excellent_count,
                COUNT(CASE WHEN g.grade >= 85 AND g.grade < 90 THEN 1 END) as good_count,
                COUNT(CASE WHEN g.grade >= 80 AND g.grade < 85 THEN 1 END) as fair_count,
                COUNT(CASE WHEN g.grade < 80 THEN 1 END) as poor_count
            FROM users u
            LEFT JOIN grades g ON u.id = g.user_id AND ($whereClause OR g.id IS NULL)
            WHERE u.role_id = 4 AND u.is_active = 1
            GROUP BY u.id, u.first_name, u.last_name, u.username
            HAVING COUNT(g.id) > 0
            ORDER BY average_grade DESC, u.last_name, u.first_name
        ", $params);
        break;
        
    case 'detailed_grades':
        // Detailed grade listing with proper student names
        $reportData = fetchAll("
            SELECT 
                g.*,
                u.first_name,
                u.last_name,
                u.username,
                CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM grades g
            JOIN users u ON g.user_id = u.id
            WHERE u.role_id = 4 AND $whereClause
            ORDER BY g.school_year DESC, g.semester, u.last_name, u.first_name, g.subject
        ", $params);
        break;
        
    case 'transcript':
        // Individual student transcript
        if ($studentId > 0) {
            $student = fetchOne("
                SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM users u
                WHERE u.id = ? AND u.role_id = 4
            ", [$studentId]);
            
            if ($student) {
                $reportData = [
                    'student' => $student,
                    'grades_by_semester' => fetchAll("
                        SELECT 
                            g.semester,
                            g.school_year,
                            COUNT(*) as subject_count,
                            AVG(g.grade) as semester_gpa
                        FROM grades g
                        WHERE g.user_id = ? AND $whereClause
                        GROUP BY g.semester, g.school_year
                        ORDER BY g.school_year DESC, g.semester
                    ", array_merge([$studentId], $params)),
                    
                    'all_grades' => fetchAll("
                        SELECT *
                        FROM grades g
                        WHERE g.user_id = ? AND $whereClause
                        ORDER BY g.school_year DESC, g.semester, g.subject
                    ", array_merge([$studentId], $params))
                ];
            }
        }
        break;
}

// Handle export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportToCSV($reportType, $reportData, $params);
    exit();
}

// Helper function to export data as CSV
function exportToCSV($type, $data, $params) {
    $filename = "grade_report_" . $type . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    switch ($type) {
        case 'student_list':
            fputcsv($output, ['Student Name', 'Username', 'Total Grades', 'Average', 'Lowest', 'Highest', 'Excellent', 'Good', 'Fair', 'Poor']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['full_name'],
                    $row['username'],
                    $row['total_grades'],
                    number_format($row['average_grade'] ?? 0, 2),
                    number_format($row['lowest_grade'] ?? 0, 2),
                    number_format($row['highest_grade'] ?? 0, 2),
                    $row['excellent_count'],
                    $row['good_count'],
                    $row['fair_count'],
                    $row['poor_count']
                ]);
            }
            break;
            
        case 'detailed_grades':
            fputcsv($output, ['Student Name', 'Username', 'Subject', 'Grade', 'Semester', 'School Year', 'Date Added']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['full_name'],
                    $row['username'],
                    $row['subject'],
                    number_format($row['grade'], 2),
                    $row['semester'] ?? 'N/A',
                    $row['school_year'] ?? 'N/A',
                    date('Y-m-d', strtotime($row['created_at']))
                ]);
            }
            break;
    }
    
    fclose($output);
}
?>

<div class="page-header">
    <div class="header-text">
        <h1><i class="fas fa-chart-bar"></i> Grade Reports & Analytics</h1>
        <p>Generate comprehensive academic reports and statistics</p>
    </div>
    <div class="header-actions">
        <?php if ($reportType === 'student_list' || $reportType === 'detailed_grades'): ?>
        <a href="<?= $_SERVER['REQUEST_URI'] ?>&export=csv" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export CSV
        </a>
        <?php endif; ?>
            
        <?php if ($reportType === 'transcript' && !empty($reportData['student'])): ?>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Transcript
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Report Filters -->
<div class="filters-section" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <form method="GET" class="filters-form">
        <div class="filter-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="report_type">Report Type</label>
                <select name="report_type" id="report_type" onchange="toggleStudentSelect()">
                    <option value="summary" <?= $reportType === 'summary' ? 'selected' : '' ?>>Summary Statistics</option>
                    <option value="student_list" <?= $reportType === 'student_list' ? 'selected' : '' ?>>Student Grade List</option>
                    <option value="detailed_grades" <?= $reportType === 'detailed_grades' ? 'selected' : '' ?>>Detailed Grade Report</option>
                    <option value="transcript" <?= $reportType === 'transcript' ? 'selected' : '' ?>>Student Transcript</option>
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
                <label for="school_year">School Year</label>
                <select name="school_year" id="school_year">
                    <option value="">All Years</option>
                    <?php foreach ($schoolYears as $year): ?>
                        <option value="<?= htmlspecialchars($year['school_year']) ?>" 
                                <?= $schoolYear === $year['school_year'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['school_year']) ?>
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
        </div>
        
        <div id="student-select" class="filter-row" style="display: <?= $reportType === 'transcript' ? 'block' : 'none' ?>; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="student_id">Select Student (for Transcript)</label>
                <select name="student_id" id="student_id">
                    <option value="">Choose a student...</option>
                    <?php
                    $students = fetchAll("
                        SELECT id, first_name, last_name, username 
                        FROM users 
                        WHERE role_id = 4 AND is_active = 1 
                        ORDER BY last_name, first_name
                    ");
                    foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" 
                                <?= $studentId === $student['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?> (@<?= htmlspecialchars($student['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Generate Report
            </button>
            <a href="/GLC_AIMS/registrar/grade_reports.php" class="btn btn-secondary">
                <i class="fas fa-refresh"></i> Reset Filters
            </a>
        </div>
    </form>
</div>

<!-- Report Content -->
<?php if ($reportType === 'summary' && !empty($reportData['stats'])): ?>
    <!-- Summary Statistics Report -->
    <div class="report-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
        <div class="section-header">
            <h3><i class="fas fa-chart-pie"></i> Grade Summary Statistics</h3>
        </div>
        
        <!-- Overall Statistics -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--primary-blue);">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= number_format($reportData['stats']['total_grades']) ?></div>
                    <div class="stats-label">Total Grades</div>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--success);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= number_format($reportData['stats']['total_students']) ?></div>
                    <div class="stats-label">Students</div>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--warning);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= number_format($reportData['stats']['total_subjects']) ?></div>
                    <div class="stats-label">Subjects</div>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon" style="background: var(--info);">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?= number_format($reportData['stats']['average_grade'], 1) ?></div>
                    <div class="stats-label">Average Grade</div>
                </div>
            </div>
        </div>
        
        <!-- Grade Distribution -->
        <div class="grade-distribution" style="margin-bottom: 2rem;">
            <h4>Grade Distribution</h4>
            <div class="distribution-chart" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem;">
                <div class="distribution-item">
                    <div class="distribution-bar" style="background: var(--success); height: <?= ($reportData['stats']['total_grades'] > 0) ? ($reportData['stats']['excellent_count'] / $reportData['stats']['total_grades']) * 100 : 0 ?>%; min-height: 20px; max-height: 200px; border-radius: 4px;"></div>
                    <div class="distribution-label">
                        <strong><?= $reportData['stats']['excellent_count'] ?></strong>
                        <small>Excellent (90+)</small>
                    </div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-bar" style="background: var(--info); height: <?= ($reportData['stats']['total_grades'] > 0) ? ($reportData['stats']['good_count'] / $reportData['stats']['total_grades']) * 100 : 0 ?>%; min-height: 20px; max-height: 200px; border-radius: 4px;"></div>
                    <div class="distribution-label">
                        <strong><?= $reportData['stats']['good_count'] ?></strong>
                        <small>Good (85-89)</small>
                    </div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-bar" style="background: var(--warning); height: <?= ($reportData['stats']['total_grades'] > 0) ? ($reportData['stats']['fair_count'] / $reportData['stats']['total_grades']) * 100 : 0 ?>%; min-height: 20px; max-height: 200px; border-radius: 4px;"></div>
                    <div class="distribution-label">
                        <strong><?= $reportData['stats']['fair_count'] ?></strong>
                        <small>Fair (80-84)</small>
                    </div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-bar" style="background: var(--error); height: <?= ($reportData['stats']['total_grades'] > 0) ? ($reportData['stats']['poor_count'] / $reportData['stats']['total_grades']) * 100 : 0 ?>%; min-height: 20px; max-height: 200px; border-radius: 4px;"></div>
                    <div class="distribution-label">
                        <strong><?= $reportData['stats']['poor_count'] ?></strong>
                        <small>Poor (<80)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- By Semester Analysis -->
        <?php if (!empty($reportData['by_semester'])): ?>
        <div class="semester-analysis" style="margin-bottom: 2rem;">
            <h4>Performance by Semester</h4>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>School Year</th>
                            <th>Students</th>
                            <th>Total Grades</th>
                            <th>Average</th>
                            <th>Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['by_semester'] as $sem): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sem['semester'] ?: 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($sem['school_year'] ?: 'N/A') ?></td>
                            <td><?= number_format($sem['student_count']) ?></td>
                            <td><?= number_format($sem['grade_count']) ?></td>
                            <td>
                                <span class="grade-badge grade-<?= getGradeLevel($sem['avg_grade']) ?>">
                                    <?= number_format($sem['avg_grade'], 1) ?>
                                </span>
                            </td>
                            <td><?= number_format($sem['min_grade'], 1) ?> - <?= number_format($sem['max_grade'], 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- By Subject Analysis -->
        <?php if (!empty($reportData['by_subject'])): ?>
        <div class="subject-analysis">
            <h4>Performance by Subject</h4>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Students</th>
                            <th>Total Grades</th>
                            <th>Average</th>
                            <th>Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['by_subject'] as $subj): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($subj['subject']) ?></strong></td>
                            <td><?= number_format($subj['student_count']) ?></td>
                            <td><?= number_format($subj['grade_count']) ?></td>
                            <td>
                                <span class="grade-badge grade-<?= getGradeLevel($subj['avg_grade']) ?>">
                                    <?= number_format($subj['avg_grade'], 1) ?>
                                </span>
                            </td>
                            <td><?= number_format($subj['min_grade'], 1) ?> - <?= number_format($subj['max_grade'], 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($reportType === 'student_list' && !empty($reportData)): ?>
    <!-- Student List Report -->
    <div class="report-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
        <div class="section-header">
            <h3><i class="fas fa-users"></i> Student Grade Performance</h3>
            <p>Showing <?= count($reportData) ?> students with grades</p>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Username</th>
                        <th>Total Grades</th>
                        <th>Average</th>
                        <th>Range</th>
                        <th>Grade Distribution</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $student): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($student['full_name']) ?></strong></td>
                        <td>@<?= htmlspecialchars($student['username']) ?></td>
                        <td><?= number_format($student['total_grades']) ?></td>
                        <td>
                            <span class="grade-badge grade-<?= getGradeLevel($student['average_grade']) ?>">
                                <?= number_format($student['average_grade'], 1) ?>
                            </span>
                        </td>
                        <td>
                            <?= number_format($student['lowest_grade'], 1) ?> - 
                            <?= number_format($student['highest_grade'], 1) ?>
                        </td>
                        <td>
                            <div class="grade-distribution-mini">
                                <span class="mini-badge excellent" title="Excellent">
                                    <?= $student['excellent_count'] ?>
                                </span>
                                <span class="mini-badge good" title="Good">
                                    <?= $student['good_count'] ?>
                                </span>
                                <span class="mini-badge fair" title="Fair">
                                    <?= $student['fair_count'] ?>
                                </span>
                                <span class="mini-badge poor" title="Poor">
                                    <?= $student['poor_count'] ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <a href="/GLC_AIMS/registrar/student_profile.php?id=<?= $student['id'] ?>" 
                               class="btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'detailed_grades' && !empty($reportData)): ?>
    <!-- Detailed Grades Report -->
    <div class="report-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
        <div class="section-header">
            <h3><i class="fas fa-list"></i> Detailed Grade Report</h3>
            <p>Showing <?= count($reportData) ?> grade entries</p>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Semester</th>
                        <th>School Year</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $grade): ?>
                    <tr>
                        <td>
                            <div class="student-info-mini">
                                <strong><?= htmlspecialchars($grade['full_name']) ?></strong>
                                <small>@<?= htmlspecialchars($grade['username']) ?></small>
                            </div>
                        </td>
                        <td><strong><?= htmlspecialchars($grade['subject']) ?></strong></td>
                        <td>
                            <span class="grade-badge grade-<?= getGradeLevel($grade['grade']) ?>">
                                <?= number_format($grade['grade'], 1) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($grade['semester'] ?: 'N/A') ?></td>
                        <td><?= htmlspecialchars($grade['school_year'] ?: 'N/A') ?></td>
                        <td><?= date('M j, Y', strtotime($grade['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'transcript' && !empty($reportData['student'])): ?>
    <!-- Student Transcript -->
    <div class="transcript-report" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
        <div class="transcript-header" style="text-align: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--primary-blue);">
            <h2 style="color: var(--primary-blue); margin-bottom: 0.5rem;">OFFICIAL TRANSCRIPT</h2>
            <h3 style="margin-bottom: 1rem;"><?= htmlspecialchars($reportData['student']['full_name']) ?></h3>
            <p style="color: var(--text-light);">
                Student ID: <?= htmlspecialchars($reportData['student']['username']) ?><br>
                Generated: <?= date('F j, Y') ?>
            </p>
        </div>
        
        <?php if (!empty($reportData['grades_by_semester'])): ?>
            <?php foreach ($reportData['grades_by_semester'] as $semester): ?>
                <div class="transcript-semester" style="margin-bottom: 2rem;">
                    <div class="semester-title" style="background: var(--primary-blue); color: white; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
                        <strong><?= htmlspecialchars($semester['semester'] ?: 'N/A') ?> - <?= htmlspecialchars($semester['school_year'] ?: 'N/A') ?></strong>
                        <span style="float: right;">GPA: <?= number_format($semester['semester_gpa'], 2) ?></span>
                    </div>
                    
                    <table class="transcript-table" style="width: 100%; margin-bottom: 1rem;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb;">Subject</th>
                                <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #e5e7eb;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $semesterGrades = array_filter($reportData['all_grades'], function($g) use ($semester) {
                                return $g['semester'] === $semester['semester'] && $g['school_year'] === $semester['school_year'];
                            });
                            ?>
                            <?php foreach ($semesterGrades as $grade): ?>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <?= htmlspecialchars($grade['subject']) ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #e5e7eb;">
                                    <span class="grade-badge grade-<?= getGradeLevel($grade['grade']) ?>">
                                        <?= number_format($grade['grade'], 1) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #e5e7eb;">
                                    <?= date('M j, Y', strtotime($grade['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <!-- Overall Summary -->
            <div class="transcript-summary" style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid var(--primary-blue);">
                <div class="summary-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                    <div>
                        <strong>Total Subjects</strong><br>
                        <?= count($reportData['all_grades']) ?>
                    </div>
                    <div>
                        <strong>Overall GPA</strong><br>
                        <?php 
                        $overallGPA = count($reportData['all_grades']) > 0 ? 
                            array_sum(array_column($reportData['all_grades'], 'grade')) / count($reportData['all_grades']) : 0;
                        echo number_format($overallGPA, 2);
                        ?>
                    </div>
                    <div>
                        <strong>Academic Standing</strong><br>
                        <?php
                        if ($overallGPA >= 90) echo "Excellent";
                        elseif ($overallGPA >= 85) echo "Good";
                        elseif ($overallGPA >= 80) echo "Fair";
                        else echo "Needs Improvement";
                        ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>No Academic Records</h3>
                <p>This student has no grades recorded for the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- No Data State -->
    <div class="empty-state" style="background: white; border-radius: 12px; padding: 3rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); text-align: center;">
        <i class="fas fa-chart-bar" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
        <h3>No Data Available</h3>
        <p>No grades found matching your current filter criteria. Try adjusting your filters or check if students have grades recorded.</p>
        <a href="/GLC_AIMS/registrar/upload_grades.php" class="btn btn-primary" style="margin-top: 1rem;">
            <i class="fas fa-plus"></i> Add Grades
        </a>
    </div>
<?php endif; ?>

<?php
function getGradeLevel($grade) {
    if ($grade >= 90) return 'excellent';
    if ($grade >= 85) return 'good';
    if ($grade >= 80) return 'fair';
    return 'poor';
}
?>

<script>
function toggleStudentSelect() {
    const reportType = document.getElementById('report_type').value;
    const studentSelect = document.getElementById('student-select');
    
    if (reportType === 'transcript') {
        studentSelect.style.display = 'block';
        document.getElementById('student_id').required = true;
    } else {
        studentSelect.style.display = 'none';
        document.getElementById('student_id').required = false;
    }
}

// Print styles for transcript
const printStyles = `
    @media print {
        .page-header, .filters-section, .header-actions, .btn, .btn-sm {
            display: none !important;
        }
        
        .transcript-report {
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 1rem !important;
        }
        
        .transcript-table {
            page-break-inside: avoid;
        }
        
        body {
            font-size: 12pt;
            line-height: 1.4;
        }
    }
`;

// Add print styles to head
const styleSheet = document.createElement('style');
styleSheet.innerText = printStyles;
document.head.appendChild(styleSheet);
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
    
    .header-actions {
        display: flex;
        gap: 1rem;
        flex-shrink: 0;
    }
    
    .filters-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .filters-form .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
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
    
    .form-group select,
    .form-group input {
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
    }
    
    .form-group select:focus,
    .form-group input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .report-section {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .section-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .section-header h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 1rem;
        border-left: 4px solid var(--accent-yellow);
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
    
    .distribution-chart {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .distribution-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .distribution-bar {
        width: 100%;
        margin-bottom: 0.5rem;
        border-radius: 4px;
    }
    
    .distribution-label strong {
        display: block;
        font-size: 1.2rem;
        color: var(--text-dark);
    }
    
    .distribution-label small {
        color: var(--text-light);
        font-size: 0.8rem;
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
        vertical-align: middle;
    }
    
    .data-table tr:hover {
        background: #f9fafb;
    }
    
    .grade-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
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
    
    .grade-distribution-mini {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }
    
    .mini-badge {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        text-align: center;
        line-height: 20px;
        font-size: 0.7rem;
        font-weight: bold;
        color: white;
    }
    
    .mini-badge.excellent { background: var(--success); }
    .mini-badge.good { background: var(--light-blue); }
    .mini-badge.fair { background: var(--warning); }
    .mini-badge.poor { background: var(--error); }
    
    .student-info-mini strong {
        display: block;
        font-size: 1rem;
        color: var(--text-dark);
    }
    
    .student-info-mini small {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        margin-bottom: 0.5rem;
        color: var(--text-dark);
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
    
    .btn:hover, .btn-sm:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        text-decoration: none;
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
        --background: #f9fafb;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .header-content {
            flex-direction: column;
            gap: 1rem;
        }
        
        .filters-form .filter-row {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .distribution-chart {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .header-actions {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .distribution-chart {
            grid-template-columns: 1fr;
        }
        
        .data-table {
            font-size: 0.85rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .grade-distribution-mini {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .report-section {
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
        
        .stats-card {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>