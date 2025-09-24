<?php
// save_enrollment.php - Fixed version with proper success handling
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/enrolleesdb.php';
require_once __DIR__ . '/data/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: enrollment.php");
    exit();
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    $_SESSION['enrollment_error'] = [
        'type' => 'error',
        'message' => 'Invalid security token. Please refresh the page and try again.'
    ];
    header("Location: enrollment.php");
    exit();
}

// Rate limiting check - use defined constants or defaults
$rateLimitFormSubmit = defined('RATE_LIMIT_LOGIN') ? RATE_LIMIT_LOGIN : 5;
$rateLimitTimeWindow = defined('RATE_LIMIT_TIME_WINDOW') ? RATE_LIMIT_TIME_WINDOW : 300;
if (!Security::checkRateLimit('enrollment_submit', $rateLimitFormSubmit, $rateLimitTimeWindow)) {
    $_SESSION['enrollment_error'] = [
        'type' => 'error',
        'message' => 'Too many submission attempts. Please wait and try again later.'
    ];
    header("Location: enrollment.php");
    exit();
}

try {
    // Sanitize and validate text inputs
    $data = [];
    $data['firstName'] = Security::sanitizeInput($_POST['firstName'] ?? '');
    $data['middleName'] = Security::sanitizeInput($_POST['middleName'] ?? '');
    $data['lastName'] = Security::sanitizeInput($_POST['lastName'] ?? '');
    $data['birthDate'] = Security::sanitizeInput($_POST['birthDate'] ?? '');
    $data['gender'] = Security::sanitizeInput($_POST['gender'] ?? '');
    $data['civilStatus'] = Security::sanitizeInput($_POST['civilStatus'] ?? '');
    $data['nationality'] = Security::sanitizeInput($_POST['nationality'] ?? '');
    $data['religion'] = Security::sanitizeInput($_POST['religion'] ?? '');

    $data['email'] = Security::sanitizeInput($_POST['email'] ?? '', 'email');
    $data['phone'] = Security::sanitizeInput($_POST['phone'] ?? '');
    $data['address'] = Security::sanitizeInput($_POST['address'] ?? '');
    
    // Fix field name mismatch - check both variants
    $data['parentGuardianContact'] = Security::sanitizeInput($_POST['ParentGuardianContact'] ?? $_POST['parentGuardianContact'] ?? '');
    $data['parentGuardianPhone'] = Security::sanitizeInput($_POST['ParentGuardianPhone'] ?? $_POST['parentGuardianPhone'] ?? '');
    $data['relationship'] = Security::sanitizeInput($_POST['relationship'] ?? '');

    $data['program'] = Security::sanitizeInput($_POST['program'] ?? '');
    $data['enrollmentType'] = Security::sanitizeInput($_POST['enrollmentType'] ?? '');
    $data['lastSchool'] = Security::sanitizeInput($_POST['lastSchool'] ?? '');
    $data['yearGraduated'] = Security::sanitizeInput($_POST['yearGraduated'] ?? '', 'int');
    $data['gpa'] = Security::sanitizeInput($_POST['gpa'] ?? '', 'float');

    // Debug logging in development
    if (APP_ENV === 'development') {
        error_log("Enrollment data received: " . json_encode($data));
    }

    // Validate required fields
    $requiredFields = [
        'firstName' => 'First Name',
        'lastName' => 'Last Name',
        'birthDate' => 'Date of Birth',
        'gender' => 'Gender',
        'civilStatus' => 'Civil Status',
        'nationality' => 'Nationality',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'address' => 'Address',
        'parentGuardianContact' => 'Parent/Guardian Contact',
        'parentGuardianPhone' => 'Parent/Guardian Phone',
        'relationship' => 'Relationship',
        'program' => 'Program',
        'enrollmentType' => 'Enrollment Type',
        'lastSchool' => 'Last School Attended'
    ];

    $errors = [];
    foreach ($requiredFields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = "$label is required";
        }
    }

    // Email validation
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address format";
    }

    // Check if email already exists
    if (!empty($data['email'])) {
        try {
            if (EnrollmentDB::emailExists($data['email'])) {
                $errors[] = "The email address you entered is already registered. Please use a different email address.";
            }
        } catch (Exception $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            // Continue processing - don't fail just because of email check
        }
    }

    // Validate year graduated
    if (!empty($data['yearGraduated'])) {
        $currentYear = date('Y');
        if ($data['yearGraduated'] < 1990 || $data['yearGraduated'] > $currentYear) {
            $errors[] = "Year graduated must be between 1990 and $currentYear";
        }
    }

    // Validate GPA
    if (!empty($data['gpa'])) {
        if ($data['gpa'] < 1 || $data['gpa'] > 5) {
            $errors[] = "GPA must be between 1.0 and 5.0";
        }
    }

    // If there are validation errors, return to form with errors
    if (!empty($errors)) {
        $_SESSION['enrollment_error'] = [
            'type' => 'error',
            'message' => implode('. ', $errors)
        ];
        $_SESSION['enrollment_old_values'] = $_POST;
        header("Location: enrollment.php");
        exit();
    }

    // Handle file uploads
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }

    // Function to securely upload files
    function uploadFile($fileInputName, $uploadDir) {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$fileInputName];
        $fileSize = $file['size'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        
        // File size limits
        $maxSize = ($fileInputName === 'photo') ? 2 * 1024 * 1024 : 5 * 1024 * 1024; // 2MB for photo, 5MB for others
        
        if ($fileSize > $maxSize) {
            throw new Exception("File size for $fileInputName exceeds maximum allowed size");
        }

        // Get file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Invalid file type for $fileInputName. Only PDF, JPG, and PNG files are allowed");
        }

        // Generate secure filename
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $targetPath)) {
            return $targetPath;
        } else {
            throw new Exception("Failed to upload $fileInputName");
        }
    }

    // Required files
    $requiredFiles = ['birthCert', 'diploma', 'transcript', 'goodMoral', 'photo', 'medical'];
    $uploadedFiles = [];
    $fileErrors = [];

    foreach ($requiredFiles as $fileField) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            $fileErrors[] = ucfirst(str_replace(['Cert', 'goodMoral'], ['Certificate', 'Good Moral Certificate'], $fileField)) . " is required";
        }
    }

    if (!empty($fileErrors)) {
        $_SESSION['enrollment_error'] = [
            'type' => 'error',
            'message' => implode('. ', $fileErrors)
        ];
        $_SESSION['enrollment_old_values'] = $_POST;
        header("Location: enrollment.php");
        exit();
    }

    // Upload files
    try {
        foreach ($requiredFiles as $fileField) {
            $uploadedFiles[$fileField] = uploadFile($fileField, $uploadDir);
        }
    } catch (Exception $e) {
        $_SESSION['enrollment_error'] = [
            'type' => 'error',
            'message' => 'File upload error: ' . $e->getMessage()
        ];
        $_SESSION['enrollment_old_values'] = $_POST;
        header("Location: enrollment.php");
        exit();
    }

    // Add uploaded file paths to data
    foreach ($uploadedFiles as $field => $path) {
        $data[$field] = $path;
    }

    // Debug logging
    if (APP_ENV === 'development') {
        error_log("About to insert enrollment data: " . json_encode($data));
    }

    // Insert enrollment data
    $result = EnrollmentDB::insertEnrollment($data);

    if ($result['success']) {
        // CRITICAL: Clear session data BEFORE redirecting to success page
        // This prevents the success page from being overridden by form logic
        unset($_SESSION['enrollment_old_values']);
        unset($_SESSION['enrollment_error']);
        unset($_SESSION['agreed']); // Clear agreement status
        
        // Log successful enrollment
        if (APP_ENV === 'development') {
            error_log("Enrollment successful, redirecting to success page");
        }
        
        // FIXED: Redirect to success page with proper parameter
        // Using absolute redirect to ensure clean state
        $successUrl = "enrollment.php?success=1";
        
        // Add cache-busting parameter to ensure fresh page load
        $successUrl .= "&t=" . time();
        
        // Ensure clean redirect without any session interference
        session_write_close(); // Force session data to be written
        
        header("Location: " . $successUrl);
        exit();
    } else {
        $_SESSION['enrollment_error'] = [
            'type' => 'error',
            'message' => $result['message'] ?? 'An error occurred while processing your enrollment. Please try again.'
        ];
        $_SESSION['enrollment_old_values'] = $_POST;
        header("Location: enrollment.php");
        exit();
    }

} catch (Exception $e) {
    error_log("Enrollment submission error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $_SESSION['enrollment_error'] = [
        'type' => 'error',
        'message' => APP_ENV === 'development' ? $e->getMessage() : 'An unexpected error occurred. Please try again later.'
    ];
    $_SESSION['enrollment_old_values'] = $_POST;
    header("Location: enrollment.php");
    exit();
}
?>