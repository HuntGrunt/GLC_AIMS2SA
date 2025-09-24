<?php
// registrar/upload_files.php - Enhanced File Upload Interface with Searchable Dropdown
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Registrar', 'Super Admin']);
require __DIR__ . "/../shared/header.php";

// Initialize variables
$message = '';
$messageType = '';
$selectedUsername = $_GET['username'] ?? '';

// Ensure upload directory exists
$uploadDir = __DIR__ . "/../uploads";
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $fileCategory = $_POST['file_category'] ?? 'general';
    
    if (empty($username)) {
        $message = 'Please select a student username.';
        $messageType = 'error';
    } elseif (empty($_FILES['file']['name'])) {
        $message = 'Please select a file to upload.';
        $messageType = 'error';
    } else {
        // Verify student exists
        $student = fetchOne("SELECT id, first_name, last_name FROM users WHERE username = ? AND role_id = 4", [$username]);
        
        if (!$student) {
            $message = 'Student username not found.';
            $messageType = 'error';
        } else {
            $file = $_FILES['file'];
            $originalName = basename($file['name']);
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // Validate file type
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'txt'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                $message = 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions);
                $messageType = 'error';
            } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                $message = 'File size too large. Maximum size is 10MB.';
                $messageType = 'error';
            } else {
                // Generate safe filename
                $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
                $targetPath = $uploadDir . '/' . $safeName;
                $relativePath = '/GLC_AIMS/uploads/' . $safeName;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Save to database
                    $result = executeUpdate(
                        "INSERT INTO student_files (user_id, file_name, file_path, file_size, file_category, description, uploaded_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$student['id'], $originalName, $relativePath, $file['size'], $fileCategory, $description]
                    );
                    
                    if ($result) {
                        $message = "File '{$originalName}' uploaded successfully for {$student['first_name']} {$student['last_name']}.";
                        $messageType = 'success';
                        // Clear form after successful submission
                        $selectedUsername = '';
                    } else {
                        $message = 'Failed to save file information to database.';
                        $messageType = 'error';
                        unlink($targetPath); // Clean up uploaded file
                    }
                } else {
                    $message = 'Failed to upload file. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get all active students for dropdown with file statistics
$students = fetchAll("
    SELECT id, username, first_name, last_name, 
           CONCAT(first_name, ' ', last_name) as full_name,
           (SELECT COUNT(*) FROM student_files WHERE user_id = users.id) as file_count,
           (SELECT SUM(file_size) FROM student_files WHERE user_id = users.id) as total_file_size
    FROM users 
    WHERE role_id = 4 AND is_active = 1 
    ORDER BY last_name, first_name
");

// Get recent uploads for this session
$recentUploads = fetchAll("
    SELECT sf.*, u.first_name, u.last_name, u.username,
           ROUND(sf.file_size / 1024, 2) as file_size_kb
    FROM student_files sf
    JOIN users u ON sf.user_id = u.id
    WHERE DATE(sf.uploaded_at) = CURDATE()
    ORDER BY sf.uploaded_at DESC
    LIMIT 10
");

// Get upload statistics
$uploadStats = fetchOne("
    SELECT 
        COUNT(*) as total_files,
        COUNT(DISTINCT user_id) as students_with_files,
        SUM(file_size) as total_size,
        COUNT(CASE WHEN DATE(uploaded_at) = CURDATE() THEN 1 END) as today_uploads
    FROM student_files sf
    JOIN users u ON sf.user_id = u.id
    WHERE u.role_id = 4
");

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

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'file-pdf',
        'jpg' => 'file-image', 'jpeg' => 'file-image', 'png' => 'file-image', 'gif' => 'file-image',
        'doc' => 'file-word', 'docx' => 'file-word',
        'xls' => 'file-excel', 'xlsx' => 'file-excel',
        'ppt' => 'file-powerpoint', 'pptx' => 'file-powerpoint',
        'txt' => 'file-alt'
    ];
    return $icons[$extension] ?? 'file';
}
?>

<div class="page-header">
        <div class="header-text">
            <h1><i class="fas fa-cloud-upload-alt"></i> Upload Student Files</h1>
            <p>Upload and manage student documents, credentials, and files</p>
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
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($uploadStats['total_files'] ?? 0) ?></div>
            <div class="stats-label">Total Files</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($uploadStats['students_with_files'] ?? 0) ?></div>
            <div class="stats-label">Students with Files</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-hdd"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= formatFileSize($uploadStats['total_size'] ?? 0) ?></div>
            <div class="stats-label">Total Storage</div>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--info); color: white;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($uploadStats['today_uploads'] ?? 0) ?></div>
            <div class="stats-label">Today's Uploads</div>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="upload-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 2rem;">
    <div class="section-header" style="margin-bottom: 2rem;">
        <h3><i class="fas fa-upload"></i> Upload New File</h3>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Select a student and upload their documents or credentials.</p>
    </div>
    
    <form method="POST" enctype="multipart/form-data" class="upload-form">
        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i> Select Student *
                </label>
                <div class="searchable-select-container">
                    <input type="hidden" name="username" id="username" value="<?= htmlspecialchars($selectedUsername) ?>">
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
                                         data-value="<?= htmlspecialchars($student['username']) ?>"
                                         data-files="<?= $student['file_count'] ?>"
                                         data-size="<?= formatFileSize($student['total_file_size'] ?? 0) ?>"
                                         onclick="selectStudent(this)">
                                        <div class="option-main">
                                            <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                            <small>@<?= htmlspecialchars($student['username']) ?></small>
                                        </div>
                                        <div class="option-stats">
                                            <?= $student['file_count'] ?> files • <?= formatFileSize($student['total_file_size'] ?? 0) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="student-info" class="student-info-display" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #f8fafc; border-radius: 6px; font-size: 0.9rem;">
                    <span id="student-files-count"></span> files uploaded • Total size: <span id="student-total-size"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="file_category" class="form-label">
                    <i class="fas fa-tags"></i> File Category *
                </label>
                <select name="file_category" id="file_category" class="form-input form-select" required>
                    <option value="transcript">Academic Transcript</option>
                    <option value="certificate">Certificate</option>
                    <option value="id">ID Document</option>
                    <option value="medical">Medical Record</option>
                    <option value="financial">Financial Document</option>
                    <option value="general">General Document</option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="description" class="form-label">
                <i class="fas fa-align-left"></i> Description (Optional)
            </label>
            <input type="text" name="description" id="description" class="form-input" 
                   placeholder="Brief description of the file...">
        </div>
        
        <div class="form-group" style="margin-bottom: 2rem;">
            <label for="file" class="form-label">
                <i class="fas fa-paperclip"></i> Choose File *
            </label>
            <div class="file-upload-area" style="border: 2px dashed var(--border-gray); border-radius: 10px; padding: 2rem; text-align: center; background: #fafafa; transition: all 0.3s ease;">
                <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.txt" 
                       required style="display: none;" onchange="updateFileName(this)">
                <div class="upload-prompt">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary-blue); margin-bottom: 1rem;"></i>
                    <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Click to upload or drag and drop</h4>
                    <p style="color: var(--text-light); font-size: 0.9rem;">PDF, Images, Word documents (Max: 10MB)</p>
                    <button type="button" class="btn btn-secondary" style="margin-top: 1rem;" onclick="document.getElementById('file').click()">
                        <i class="fas fa-folder-open"></i> Browse Files
                    </button>
                </div>
                <div class="file-selected" style="display: none;">
                    <i class="fas fa-file" style="font-size: 2rem; color: var(--success); margin-bottom: 1rem;"></i>
                    <p class="selected-file-name" style="font-weight: 600; color: var(--success);"></p>
                    <button type="button" class="btn-link" onclick="clearFile()" style="color: var(--text-light); font-size: 0.9rem;">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-start;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload File
            </button>
            <button type="reset" class="btn btn-secondary" onclick="clearForm()">
                <i class="fas fa-redo"></i> Reset Form
            </button>
        </div>
    </form>
</div>

<!-- Recent Uploads -->
<?php if (!empty($recentUploads)): ?>
<div class="recent-uploads" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <h3><i class="fas fa-history"></i> Recent Uploads (Today)</h3>
    </div>
    
    <div class="uploads-list">
        <?php foreach ($recentUploads as $upload): ?>
            <div class="upload-item" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border-bottom: 1px solid #eee; transition: background 0.2s ease;">
                <div class="file-icon" style="width: 40px; height: 40px; border-radius: 8px; background: var(--light-blue); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fas fa-<?= getFileIcon($upload['file_name']) ?>"></i>
                </div>
                <div class="file-details" style="flex: 1; min-width: 0;">
                    <h4 style="margin: 0; font-size: 1rem; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($upload['file_name']) ?>
                    </h4>
                    <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-light);">
                        <strong><?= htmlspecialchars($upload['first_name'] . ' ' . $upload['last_name']) ?></strong>
                        (@<?= htmlspecialchars($upload['username']) ?>)
                        • <?= number_format($upload['file_size_kb'], 1) ?> KB
                        • <?= date('g:i A', strtotime($upload['uploaded_at'])) ?>
                    </p>
                </div>
                <div class="file-actions">
                    <a href="<?= htmlspecialchars($upload['file_path']) ?>" target="_blank" 
                       class="btn-sm btn-primary" title="View File">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="/GLC_AIMS/registrar/manage_students.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> View All Files
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
        const searchInput = dropdown.querySelector('input');
        setTimeout(() => searchInput.focus(), 100);
    }
}

function selectStudent(option) {
    const username = option.dataset.value;
    const studentName = option.querySelector('strong').textContent;
    const userHandle = option.querySelector('small').textContent;
    const files = option.dataset.files;
    const size = option.dataset.size;
    
    // Update hidden input
    document.getElementById('username').value = username;
    
    // Update display
    document.getElementById('student-selected-text').innerHTML = 
        `<strong>${studentName}</strong> <small>${userHandle}</small>`;
    
    // Update student info
    document.getElementById('student-files-count').textContent = files;
    document.getElementById('student-total-size').textContent = size;
    document.getElementById('student-info').style.display = 'block';
    
    // Close dropdown
    toggleDropdown('student-select');
}

function filterStudents(searchTerm) {
    const options = document.getElementById('student-options');
    const studentOptions = options.querySelectorAll('.select-option');
    
    studentOptions.forEach(option => {
        const name = option.querySelector('strong').textContent.toLowerCase();
        const username = option.querySelector('small').textContent.toLowerCase();
        
        if (name.includes(searchTerm.toLowerCase()) || username.includes(searchTerm.toLowerCase())) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

function updateFileName(input) {
    const fileUploadArea = input.closest('.file-upload-area');
    const uploadPrompt = fileUploadArea.querySelector('.upload-prompt');
    const fileSelected = fileUploadArea.querySelector('.file-selected');
    const fileName = fileUploadArea.querySelector('.selected-file-name');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        uploadPrompt.style.display = 'none';
        fileSelected.style.display = 'block';
        fileUploadArea.style.borderColor = 'var(--success)';
        fileUploadArea.style.background = 'rgba(16, 185, 129, 0.1)';
    }
}

function clearFile() {
    const fileInput = document.getElementById('file');
    const fileUploadArea = fileInput.closest('.file-upload-area');
    const uploadPrompt = fileUploadArea.querySelector('.upload-prompt');
    const fileSelected = fileUploadArea.querySelector('.file-selected');
    
    fileInput.value = '';
    uploadPrompt.style.display = 'block';
    fileSelected.style.display = 'none';
    fileUploadArea.style.borderColor = 'var(--border-gray)';
    fileUploadArea.style.background = '#fafafa';
}

function clearForm() {
    // Clear student selection
    document.getElementById('username').value = '';
    document.getElementById('student-selected-text').textContent = 'Choose a student...';
    document.getElementById('student-info').style.display = 'none';
    
    // Clear file
    clearFile();
    
    // Reset other fields
    document.getElementById('file_category').value = 'transcript';
    document.getElementById('description').value = '';
    
    // Close dropdown if open
    if (isDropdownOpen) {
        toggleDropdown('student-select');
    }
}

function formatFileSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' bytes';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const studentSelect = document.getElementById('student-select');
    if (!studentSelect.contains(e.target) && isDropdownOpen) {
        toggleDropdown('student-select');
    }
});

// Drag and drop functionality
const fileUploadArea = document.querySelector('.file-upload-area');
const fileInput = document.getElementById('file');

fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.style.borderColor = 'var(--primary-blue)';
    fileUploadArea.style.background = 'rgba(30, 64, 175, 0.1)';
});

fileUploadArea.addEventListener('dragleave', () => {
    fileUploadArea.style.borderColor = 'var(--border-gray)';
    fileUploadArea.style.background = '#fafafa';
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.style.borderColor = 'var(--border-gray)';
    fileUploadArea.style.background = '#fafafa';
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        updateFileName(fileInput);
    }
});

fileUploadArea.addEventListener('click', (e) => {
    if (!e.target.closest('button') && !e.target.closest('.btn-link')) {
        fileInput.click();
    }
});

// Auto-focus and initialization
document.addEventListener('DOMContentLoaded', function() {
    const username = document.getElementById('username').value;
    if (username) {
        const option = document.querySelector(`[data-value="${username}"]`);
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
    }
    
    .header-text p {
        color: var(--text-light);
        font-size: 1.1rem;
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
    
    .option-stats {
        font-size: 0.8rem;
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
    
    .section-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-dark);
        font-size: 0.95rem;
    }
    
    .form-label i {
        margin-right: 0.5rem;
        color: var(--primary-blue);
    }
    
    .form-input {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 2px solid var(--border-gray);
        border-radius: 10px;
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
    
    .file-upload-area:hover {
        border-color: var(--primary-blue) !important;
        background: rgba(30, 64, 175, 0.05) !important;
    }
    
    .upload-item:hover {
        background: #f9fafb;
    }
    
    .upload-item:last-child {
        border-bottom: none;
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
    
    .btn-link {
        background: none;
        border: none;
        color: var(--text-light);
        text-decoration: none;
        cursor: pointer;
        font-size: 0.9rem;
        padding: 0;
    }
    
    .btn-link:hover {
        color: var(--primary-blue);
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
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            gap: 1rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .upload-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .select-dropdown {
            max-height: 250px;
        }
        
        .select-options {
            max-height: 200px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .upload-section, .recent-uploads {
            padding: 1rem;
        }
        
        .file-upload-area {
            padding: 1.5rem 1rem;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>