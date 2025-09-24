<?php
// admin/import_users.php - Import users from CSV
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);
require __DIR__ . "/../shared/header.php";

$message = '';
$messageType = '';
$importResults = null;

// Handle CSV upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $importResults = processUserImport($_FILES['csv_file']);
    $message = $importResults['message'];
    $messageType = $importResults['success'] ? 'success' : 'error';
}

function processUserImport($file) {
    try {
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed: ' . $file['error']];
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
        }
        
        // Check file extension
        $fileInfo = pathinfo($file['name']);
        if (strtolower($fileInfo['extension']) !== 'csv') {
            return ['success' => false, 'message' => 'Invalid file type. Only CSV files are allowed.'];
        }
        
        // Read and parse CSV
        $csvData = [];
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            // Read header row
            $headers = fgetcsv($handle);
            
            if (!$headers) {
                fclose($handle);
                return ['success' => false, 'message' => 'CSV file appears to be empty or invalid.'];
            }
            
            // Normalize headers (remove spaces, convert to lowercase)
            $normalizedHeaders = array_map(function($header) {
                return strtolower(str_replace([' ', '_'], '', trim($header)));
            }, $headers);
            
            // Required fields mapping
            $requiredFields = [
                'username' => ['username', 'user', 'login'],
                'email' => ['email', 'emailaddress', 'mail'],
                'firstname' => ['firstname', 'fname', 'first'],
                'lastname' => ['lastname', 'lname', 'last', 'surname'],
                'role' => ['role', 'usertype', 'type']
            ];
            
            // Find column indices for required fields
            $columnMap = [];
            foreach ($requiredFields as $field => $variations) {
                $found = false;
                foreach ($variations as $variation) {
                    $index = array_search($variation, $normalizedHeaders);
                    if ($index !== false) {
                        $columnMap[$field] = $index;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    fclose($handle);
                    return ['success' => false, 'message' => "Required column '$field' not found in CSV. Expected variations: " . implode(', ', $variations)];
                }
            }
            
            // Optional password column
            $passwordIndex = array_search('password', $normalizedHeaders);
            if ($passwordIndex !== false) {
                $columnMap['password'] = $passwordIndex;
            }
            
            // Read data rows
            $rowNumber = 1;
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowNumber++;
                
                if (count($data) < count($headers)) {
                    continue; // Skip incomplete rows
                }
                
                $csvData[] = [
                    'row' => $rowNumber,
                    'username' => trim($data[$columnMap['username']] ?? ''),
                    'email' => trim($data[$columnMap['email']] ?? ''),
                    'first_name' => trim($data[$columnMap['firstname']] ?? ''),
                    'last_name' => trim($data[$columnMap['lastname']] ?? ''),
                    'role' => trim($data[$columnMap['role']] ?? ''),
                    'password' => trim($data[$columnMap['password']] ?? '') ?: generateRandomPassword()
                ];
            }
            fclose($handle);
        } else {
            return ['success' => false, 'message' => 'Unable to read CSV file.'];
        }
        
        if (empty($csvData)) {
            return ['success' => false, 'message' => 'No valid data rows found in CSV file.'];
        }
        
        // Get available roles for validation
        $availableRoles = fetchAll("SELECT id, role FROM roles");
        $roleMap = [];
        foreach ($availableRoles as $role) {
            $roleMap[strtolower($role['role'])] = $role['id'];
        }
        
        // Process imports
        $results = [
            'total' => count($csvData),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'created_users' => []
        ];
        
        foreach ($csvData as $userData) {
            $result = importSingleUser($userData, $roleMap);
            
            if ($result['success']) {
                $results['successful']++;
                $results['created_users'][] = $userData['username'];
            } else {
                $results['failed']++;
                $results['errors'][] = "Row {$userData['row']}: " . $result['error'];
            }
        }
        
        // Log import activity
        ActivityLogger::log($_SESSION['user_id'], 'IMPORT_USERS', 'users', null, null, [
            'filename' => $file['name'],
            'total_rows' => $results['total'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ]);
        
        return [
            'success' => true,
            'message' => "Import completed. {$results['successful']} users created, {$results['failed']} failed.",
            'results' => $results
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Import error: ' . $e->getMessage()];
    }
}

function importSingleUser($userData, $roleMap) {
    try {
        // Validate required fields
        $required = ['username', 'email', 'first_name', 'last_name', 'role'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }
        
        // Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email format: {$userData['email']}"];
        }
        
        // Validate role
        $roleLower = strtolower($userData['role']);
        if (!isset($roleMap[$roleLower])) {
            return ['success' => false, 'error' => "Invalid role: {$userData['role']}. Available roles: " . implode(', ', array_keys($roleMap))];
        }
        
        // Check if user already exists
        $existing = fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", 
                            [$userData['username'], $userData['email']]);
        if ($existing) {
            return ['success' => false, 'error' => "User already exists with username '{$userData['username']}' or email '{$userData['email']}'"];
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $result = executeUpdate("
            INSERT INTO users (username, email, first_name, last_name, role_id, password, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ", [
            $userData['username'],
            $userData['email'],
            $userData['first_name'],
            $userData['last_name'],
            $roleMap[$roleLower],
            $hashedPassword
        ]);
        
        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Database insert failed'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// Get available roles for display
$roles = fetchAll("SELECT * FROM roles ORDER BY id");
?>

<div class="page-header">
    <h1><i class="fas fa-file-upload"></i> Import Users</h1>
    <p>Bulk import users from CSV file</p>
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

<!-- Import Results -->
<?php if ($importResults && $importResults['success'] && isset($importResults['results'])): ?>
<div class="dashboard-section" style="margin-bottom: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-chart-bar"></i> Import Results</h3>
    </div>
    
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-file-csv"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $importResults['results']['total'] ?></div>
                <div class="stats-label">Total Rows</div>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--success); color: white;">
                <i class="fas fa-check"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $importResults['results']['successful'] ?></div>
                <div class="stats-label">Successful</div>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--error); color: white;">
                <i class="fas fa-times"></i>
            </div>
            <div class="stats-content">
                <div class="stats-value"><?= $importResults['results']['failed'] ?></div>
                <div class="stats-label">Failed</div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($importResults['results']['created_users'])): ?>
    <div style="margin-bottom: 1rem;">
        <h4 style="color: var(--success); margin-bottom: 0.5rem;">Successfully Created Users:</h4>
        <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--success);">
            <?= implode(', ', array_slice($importResults['results']['created_users'], 0, 20)) ?>
            <?php if (count($importResults['results']['created_users']) > 20): ?>
                <span style="color: var(--text-light);"> ... and <?= count($importResults['results']['created_users']) - 20 ?> more</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($importResults['results']['errors'])): ?>
    <div>
        <h4 style="color: var(--error); margin-bottom: 0.5rem;">Import Errors:</h4>
        <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--error); max-height: 200px; overflow-y: auto;">
            <?php foreach (array_slice($importResults['results']['errors'], 0, 10) as $error): ?>
                <div style="margin-bottom: 0.5rem; font-size: 0.9rem;"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <?php if (count($importResults['results']['errors']) > 10): ?>
                <div style="color: var(--text-light); font-style: italic;">... and <?= count($importResults['results']['errors']) - 10 ?> more errors</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CSV Format Guide -->
<div class="dashboard-section" style="margin-bottom: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-info-circle"></i> CSV Format Requirements</h3>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
            <h4 style="color: var(--primary-blue); margin-bottom: 1rem;">Required Columns</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue);">
                        <th style="padding: 0.8rem; text-align: left; border: 1px solid var(--border-gray);">Column Name</th>
                        <th style="padding: 0.8rem; text-align: left; border: 1px solid var(--border-gray);">Alternatives</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);"><code>username</code></td>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);">user, login</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);"><code>email</code></td>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);">email_address, mail</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);"><code>first_name</code></td>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);">firstname, fname, first</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);"><code>last_name</code></td>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);">lastname, lname, last, surname</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);"><code>role</code></td>
                        <td style="padding: 0.8rem; border: 1px solid var(--border-gray);">user_type, type</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 1rem;">
                <h5 style="color: var(--text-dark);">Optional Column:</h5>
                <p style="margin: 0.5rem 0;"><code>password</code> - If not provided, random passwords will be generated</p>
            </div>
        </div>
        
        <div>
            <h4 style="color: var(--primary-blue); margin-bottom: 1rem;">Available Roles</h4>
            <div style="background: var(--light-yellow); padding: 1rem; border-radius: 8px;">
                <?php foreach ($roles as $role): ?>
                    <div style="margin-bottom: 0.5rem;">
                        <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $role['role'])) ?>">
                            <?= htmlspecialchars($role['role']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 1rem;">
                <h5 style="color: var(--text-dark);">Important Notes:</h5>
                <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: var(--text-dark);">
                    <li>Role names are case-insensitive</li>
                    <li>Email addresses must be valid</li>
                    <li>Usernames and emails must be unique</li>
                    <li>Maximum file size: 5MB</li>
                    <li>All imported users will be active by default</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="dashboard-section">
    <div class="section-header">
        <h3><i class="fas fa-upload"></i> Upload CSV File</h3>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="importForm">
        <div style="margin-bottom: 2rem;">
            <label style="display: block; margin-bottom: 1rem; font-weight: 600; color: var(--text-dark);">
                Select CSV File *
            </label>
            
            <div class="file-upload-area" onclick="document.getElementById('csv_file').click()" style="border: 2px dashed var(--border-gray); border-radius: 12px; padding: 3rem 2rem; text-align: center; cursor: pointer; transition: all 0.3s ease; background: var(--light-blue);">
                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary-blue); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Click to select CSV file</h4>
                <p style="color: var(--text-light); margin: 0;">Or drag and drop your file here</p>
                <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">Maximum file size: 5MB</small>
            </div>
            
            <input type="file" 
                   id="csv_file" 
                   name="csv_file" 
                   accept=".csv" 
                   required 
                   style="display: none;"
                   onchange="updateFileDisplay(this)">
            
            <div id="fileDisplay" style="margin-top: 1rem; display: none;">
                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: white; border-radius: 8px; border: 1px solid var(--border-gray);">
                    <i class="fas fa-file-csv" style="font-size: 2rem; color: var(--success);"></i>
                    <div style="flex: 1;">
                        <div id="fileName" style="font-weight: 600; color: var(--text-dark);"></div>
                        <div id="fileSize" style="color: var(--text-light); font-size: 0.9rem;"></div>
                    </div>
                    <button type="button" onclick="clearFile()" style="background: var(--light-gray); border: none; padding: 0.5rem; border-radius: 50%; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="/GLC_AIMS/admin/users.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <button type="submit" class="btn-primary" id="submitBtn" disabled>
                <i class="fas fa-upload"></i> Import Users
            </button>
        </div>
    </form>
</div>

<!-- Sample CSV Download -->
<div class="dashboard-section" style="margin-top: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-download"></i> Sample CSV Template</h3>
    </div>
    
    <p style="margin-bottom: 1rem; color: var(--text-dark);">
        Download a sample CSV template to ensure your file follows the correct format:
    </p>
    
    <button onclick="downloadSampleCSV()" class="btn-primary">
        <i class="fas fa-download"></i> Download Sample CSV
    </button>
</div>

<script>
// Prevent conflicts with header.php JavaScript
window.headerScriptLoaded = true;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Override any existing updateFileDisplay function from header
    window.updateFileDisplay = function(input) {
        // Only proceed if this is the file input we care about
        if (!input || input.id !== 'csv_file') return;
        
        console.log('CSV updateFileDisplay called'); // Debug log
        
        const fileDisplay = document.getElementById('fileDisplay');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const submitBtn = document.getElementById('submitBtn');
        
        // Check if all required elements exist
        if (!fileDisplay || !fileName || !fileSize || !submitBtn) {
            console.error('Required DOM elements not found');
            return;
        }
        
        if (input && input.files && input.files[0]) {
            const file = input.files[0];
            
            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a CSV file.');
                clearFileInput(input);
                return;
            }
            
            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum 5MB allowed.');
                clearFileInput(input);
                return;
            }
            
            try {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileDisplay.style.display = 'block';
                submitBtn.disabled = false;
                
                updateUploadAreaStyle(true);
            } catch (error) {
                console.error('Error updating file display:', error);
            }
        } else {
            resetFileDisplay();
        }
    };
    
    initializeFileUpload();
    initializeAlerts();
});

function clearFileInput(input) {
    if (input) {
        input.value = '';
    }
    resetFileDisplay();
}

function resetFileDisplay() {
    const fileDisplay = document.getElementById('fileDisplay');
    const submitBtn = document.getElementById('submitBtn');
    
    if (fileDisplay) {
        fileDisplay.style.display = 'none';
    }
    
    if (submitBtn) {
        submitBtn.disabled = true;
    }
    
    updateUploadAreaStyle(false);
}

function clearFile() {
    const fileInput = document.getElementById('csv_file');
    clearFileInput(fileInput);
}

function updateUploadAreaStyle(hasFile) {
    const uploadArea = document.querySelector('.file-upload-area');
    if (!uploadArea) return;
    
    if (hasFile) {
        uploadArea.style.borderColor = 'var(--success)';
        uploadArea.style.background = 'rgba(16, 185, 129, 0.1)';
    } else {
        uploadArea.style.borderColor = 'var(--border-gray)';
        uploadArea.style.background = 'var(--light-blue)';
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function downloadSampleCSV() {
    const csvContent = 'username,email,first_name,last_name,role,password\n' +
                      'john.doe,john.doe@example.com,John,Doe,Student,password123\n' +
                      'jane.smith,jane.smith@example.com,Jane,Smith,Student,mypassword\n' +
                      'prof.wilson,prof.wilson@example.com,Robert,Wilson,Faculty,profpass\n' +
                      'admin.user,admin@example.com,Admin,User,Admin,adminpass123';
    
    try {
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sample_users_import.csv';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error downloading sample CSV:', error);
        alert('Error downloading sample file. Please try again.');
    }
}

function initializeFileUpload() {
    const uploadArea = document.querySelector('.file-upload-area');
    const fileInput = document.getElementById('csv_file');
    
    if (!uploadArea || !fileInput) {
        console.error('Upload area or file input not found');
        return;
    }
    
    // Add drag and drop functionality
    setupDragAndDrop(uploadArea, fileInput);
}

function setupDragAndDrop(uploadArea, fileInput) {
    let dragCounter = 0;
    
    uploadArea.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter++;
        this.style.borderColor = 'var(--primary-blue)';
        this.style.background = 'rgba(59, 130, 246, 0.1)';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter--;
        if (dragCounter === 0) {
            this.style.borderColor = 'var(--border-gray)';
            this.style.background = 'var(--light-blue)';
        }
    });
    
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter = 0;
        
        this.style.borderColor = 'var(--border-gray)';
        this.style.background = 'var(--light-blue)';
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].name.toLowerCase().endsWith('.csv')) {
            try {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fileInput.files = dt.files;
                updateFileDisplay(fileInput);
            } catch (error) {
                console.error('Error handling dropped file:', error);
                // Fallback
                const file = files[0];
                if (file.size <= 5 * 1024 * 1024) {
                    const fileName = document.getElementById('fileName');
                    const fileSize = document.getElementById('fileSize');
                    const fileDisplay = document.getElementById('fileDisplay');
                    const submitBtn = document.getElementById('submitBtn');
                    
                    if (fileName && fileSize && fileDisplay && submitBtn) {
                        fileName.textContent = file.name;
                        fileSize.textContent = formatFileSize(file.size);
                        fileDisplay.style.display = 'block';
                        submitBtn.disabled = false;
                        updateUploadAreaStyle(true);
                    }
                } else {
                    alert('File size too large. Maximum 5MB allowed.');
                }
            }
        } else {
            alert('Please select a CSV file.');
        }
    });
}

function initializeAlerts() {
    const alert = document.getElementById('messageAlert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert && alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }, 8000);
    }
}
</script>

<style>
.file-upload-area:hover {
    border-color: var(--primary-blue) !important;
    background: rgba(59, 130, 246, 0.1) !important;
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

.role-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
}

.role-student {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.role-faculty {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
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
</style>

<?php require __DIR__ . "/../shared/footer.php"; ?>