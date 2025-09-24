<?php
// data/enrolleesdb.php - Updated to handle both old and new table structures
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class EnrollmentDB {
    private static $instance = null;
    private $connection;
    private static $tableStructure = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $db;
        $this->connection = $db;
        self::checkTableStructure();
    }
    
    public function getConnection() {
        global $db;
        return $db;
    }
    
    // Check what columns exist in the table
    private static function checkTableStructure() {
        if (self::$tableStructure !== null) {
            return self::$tableStructure;
        }
        
        try {
            $columns = fetchAll("DESCRIBE enrollment");
            self::$tableStructure = array_column($columns, 'Field');
            
            if (APP_ENV === 'development') {
                error_log("Enrollment table columns: " . implode(', ', self::$tableStructure));
            }
            
        } catch (Exception $e) {
            error_log("Could not check table structure: " . $e->getMessage());
            self::$tableStructure = []; // Empty array if table doesn't exist
        }
        
        return self::$tableStructure;
    }
    
    // Check if email already exists in enrollment table
    public static function emailExists($email) {
        try {
            $result = fetchOne("SELECT id FROM enrollment WHERE email = ? LIMIT 1", [$email]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            return false;
        }
    }
    
    // Insert enrollment with flexible column handling
    public static function insertEnrollment($data) {
        try {
            beginTransaction();
            
            // Get current table structure
            $availableColumns = self::checkTableStructure();
            
            // Define all possible columns and their values
            $allColumns = [
                'firstName' => $data['firstName'],
                'middleName' => $data['middleName'],
                'lastName' => $data['lastName'],
                'birthDate' => $data['birthDate'],
                'gender' => $data['gender'],
                'civilStatus' => $data['civilStatus'],
                'nationality' => $data['nationality'],
                'religion' => $data['religion'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'parentGuardianContact' => $data['parentGuardianContact'],
                'parentGuardianPhone' => $data['parentGuardianPhone'],
                'relationship' => $data['relationship'],
                'program' => $data['program'],
                'enrollmentType' => $data['enrollmentType'],
                'lastSchool' => $data['lastSchool'],
                'yearGraduated' => $data['yearGraduated'],
                'gpa' => $data['gpa'],
                'birthCert' => $data['birthCert'] ?? null,
                'diploma' => $data['diploma'] ?? null,
                'transcript' => $data['transcript'] ?? null,
                'goodMoral' => $data['goodMoral'] ?? null,
                'photo' => $data['photo'] ?? null,
                'medical' => $data['medical'] ?? null
            ];
            
            // Add optional columns if they exist in the table
            if (in_array('status', $availableColumns)) {
                $allColumns['status'] = 'pending';
            }
            if (in_array('ip_address', $availableColumns)) {
                $allColumns['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            }
            if (in_array('user_agent', $availableColumns)) {
                $allColumns['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            }
            
            // Filter columns to only include those that exist in the table
            $columnsToInsert = [];
            $valuesToInsert = [];
            $placeholders = [];
            
            foreach ($allColumns as $column => $value) {
                if (in_array($column, $availableColumns)) {
                    $columnsToInsert[] = $column;
                    $valuesToInsert[] = $value;
                    $placeholders[] = '?';
                }
            }
            
            // Add created_at if it exists
            if (in_array('created_at', $availableColumns)) {
                $columnsToInsert[] = 'created_at';
                $placeholders[] = 'NOW()';
                // Don't add to valuesToInsert since we're using NOW()
            }
            
            // Build the SQL
            $sql = "INSERT INTO enrollment (" . 
                   implode(', ', $columnsToInsert) . 
                   ") VALUES (" . 
                   implode(', ', $placeholders) . 
                   ")";
            
            // Debug logging in development
            if (APP_ENV === 'development') {
                error_log("Executing enrollment insert SQL: " . $sql);
                error_log("With parameters: " . json_encode($valuesToInsert));
            }
            
            $result = executeUpdate($sql, $valuesToInsert);
            
            if ($result) {
                $enrollmentId = getLastInsertId();
                commitTransaction();
                
                // Log the enrollment
                if (defined('ENABLE_ACTIVITY_LOGGING') && ENABLE_ACTIVITY_LOGGING) {
                    error_log("New enrollment created: ID $enrollmentId, Email: {$data['email']}");
                }
                
                return ['success' => true, 'id' => $enrollmentId];
            } else {
                rollbackTransaction();
                error_log("Failed to insert enrollment - executeUpdate returned false");
                return ['success' => false, 'message' => 'Failed to save enrollment data'];
            }
            
        } catch (Exception $e) {
            rollbackTransaction();
            error_log("Enrollment insertion failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            if (APP_ENV === 'development') {
                return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            } else {
                return ['success' => false, 'message' => 'An error occurred while processing your enrollment. Please try again.'];
            }
        }
    }
    
    // Get enrollment by ID
    public static function getEnrollmentById($id) {
        return fetchOne("SELECT * FROM enrollment WHERE id = ?", [$id]);
    }
    
    // Get enrollment by email
    public static function getEnrollmentByEmail($email) {
        return fetchOne("SELECT * FROM enrollment WHERE email = ?", [$email]);
    }
    
    // Update enrollment status (if status column exists)
    public static function updateEnrollmentStatus($id, $status, $notes = null) {
        $availableColumns = self::checkTableStructure();
        
        if (in_array('status', $availableColumns)) {
            if (in_array('status_notes', $availableColumns) && in_array('updated_at', $availableColumns)) {
                $sql = "UPDATE enrollment SET status = ?, status_notes = ?, updated_at = NOW() WHERE id = ?";
                return executeUpdate($sql, [$status, $notes, $id]);
            } else {
                $sql = "UPDATE enrollment SET status = ? WHERE id = ?";
                return executeUpdate($sql, [$status, $id]);
            }
        } else {
            error_log("Cannot update status - status column does not exist");
            return false;
        }
    }
    
    // Get enrollments with pagination
    public static function getEnrollments($page = 1, $perPage = 20, $filters = []) {
        $availableColumns = self::checkTableStructure();
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status']) && in_array('status', $availableColumns)) {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['program'])) {
            $whereClause .= " AND program = ?";
            $params[] = $filters['program'];
        }
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (firstName LIKE ? OR lastName LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $orderBy = in_array('created_at', $availableColumns) ? 'created_at DESC' : 'id DESC';
        $sql = "SELECT * FROM enrollment $whereClause ORDER BY $orderBy";
        
        return fetchPaginated($sql, $params, $page, $perPage);
    }
}

// Backward compatibility - provide the connection for legacy code
$conn = EnrollmentDB::getInstance()->getConnection();

// Try to create/update enrollment table with proper structure
$createOrUpdateTable = function() {
    try {
        // First, check if table exists
        $tableExists = fetchOne("SHOW TABLES LIKE 'enrollment'");
        
        if (!$tableExists) {
            // Create new table with all columns
            $createTableSQL = "
            CREATE TABLE enrollment (
                id INT AUTO_INCREMENT PRIMARY KEY,
                firstName VARCHAR(100) NOT NULL,
                middleName VARCHAR(100),
                lastName VARCHAR(100) NOT NULL,
                birthDate DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                civilStatus ENUM('single', 'married', 'divorced', 'widowed') NOT NULL,
                nationality VARCHAR(100) NOT NULL,
                religion VARCHAR(100),
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(20) NOT NULL,
                address TEXT NOT NULL,
                parentGuardianContact VARCHAR(200) NOT NULL,
                parentGuardianPhone VARCHAR(20) NOT NULL,
                relationship ENUM('parent', 'grandparent', 'guardian', 'sibling', 'spouse', 'other') NOT NULL,
                program VARCHAR(200) NOT NULL,
                enrollmentType ENUM('new', 'transfer', 'returning') NOT NULL,
                lastSchool VARCHAR(200) NOT NULL,
                yearGraduated YEAR,
                gpa DECIMAL(3,2),
                birthCert VARCHAR(500),
                diploma VARCHAR(500),
                transcript VARCHAR(500),
                goodMoral VARCHAR(500),
                photo VARCHAR(500),
                medical VARCHAR(500),
                status ENUM('pending', 'approved', 'rejected', 'enrolled') DEFAULT 'pending',
                status_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_program (program),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            executeQuery($createTableSQL);
            if (APP_ENV === 'development') {
                error_log("New enrollment table created successfully with all columns");
            }
        } else {
            if (APP_ENV === 'development') {
                error_log("Enrollment table exists, checking structure...");
            }
            
            // Table exists, check if we need to add missing columns
            $columns = fetchAll("DESCRIBE enrollment");
            $existingColumns = array_column($columns, 'Field');
            
            $requiredColumns = [
                'ip_address' => 'VARCHAR(45)',
                'user_agent' => 'TEXT',
                'status' => "ENUM('pending', 'approved', 'rejected', 'enrolled') DEFAULT 'pending'",
                'status_notes' => 'TEXT',
                'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ];
            
            foreach ($requiredColumns as $column => $definition) {
                if (!in_array($column, $existingColumns)) {
                    try {
                        $alterSQL = "ALTER TABLE enrollment ADD COLUMN $column $definition";
                        executeQuery($alterSQL);
                        if (APP_ENV === 'development') {
                            error_log("Added missing column: $column");
                        }
                    } catch (Exception $e) {
                        if (APP_ENV === 'development') {
                            error_log("Could not add column $column: " . $e->getMessage());
                        }
                        // Continue even if we can't add the column
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Failed to create/update enrollment table: " . $e->getMessage());
        
        // Try alternative method with mysqli if PDO fails
        try {
            global $con;
            if ($con) {
                $result = mysqli_query($con, "SHOW TABLES LIKE 'enrollment'");
                if (!$result || mysqli_num_rows($result) == 0) {
                    $createTableSQL = "
                    CREATE TABLE enrollment (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        firstName VARCHAR(100) NOT NULL,
                        middleName VARCHAR(100),
                        lastName VARCHAR(100) NOT NULL,
                        birthDate DATE NOT NULL,
                        gender ENUM('male', 'female', 'other') NOT NULL,
                        civilStatus ENUM('single', 'married', 'divorced', 'widowed') NOT NULL,
                        nationality VARCHAR(100) NOT NULL,
                        religion VARCHAR(100),
                        email VARCHAR(255) NOT NULL UNIQUE,
                        phone VARCHAR(20) NOT NULL,
                        address TEXT NOT NULL,
                        parentGuardianContact VARCHAR(200) NOT NULL,
                        parentGuardianPhone VARCHAR(20) NOT NULL,
                        relationship ENUM('parent', 'grandparent', 'guardian', 'sibling', 'spouse', 'other') NOT NULL,
                        program VARCHAR(200) NOT NULL,
                        enrollmentType ENUM('new', 'transfer', 'returning') NOT NULL,
                        lastSchool VARCHAR(200) NOT NULL,
                        yearGraduated YEAR,
                        gpa DECIMAL(3,2),
                        birthCert VARCHAR(500),
                        diploma VARCHAR(500),
                        transcript VARCHAR(500),
                        goodMoral VARCHAR(500),
                        photo VARCHAR(500),
                        medical VARCHAR(500)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    
                    mysqli_query($con, $createTableSQL);
                    if (APP_ENV === 'development') {
                        error_log("Created basic enrollment table using mysqli fallback");
                    }
                }
            }
        } catch (Exception $e2) {
            error_log("Failed to create enrollment table with mysqli fallback: " . $e2->getMessage());
        }
    }
};

// Execute table creation/update
$createOrUpdateTable();

// Verify table exists and show structure in development
if (APP_ENV === 'development') {
    try {
        $result = fetchOne("SHOW TABLES LIKE 'enrollment'");
        if ($result) {
            $structure = fetchAll("DESCRIBE enrollment");
            error_log("Final enrollment table structure: " . json_encode($structure));
        } else {
            error_log("WARNING: Enrollment table still does not exist after creation attempt!");
        }
    } catch (Exception $e) {
        error_log("Could not verify enrollment table: " . $e->getMessage());
    }
}
?>