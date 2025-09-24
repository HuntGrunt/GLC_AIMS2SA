<?php
// data/db.php
// Enhanced database connection with environment variables and error handling

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $isConnected = false;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            // Build DSN from environment variables
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            // PDO options for security and performance
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
            ];
            
            // Create PDO connection
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->isConnected = true;
            
            // Log successful connection in development
            if (APP_ENV === 'development' && EnvLoader::getBool('SQL_DEBUG', false)) {
                error_log("Database connected successfully to: " . DB_HOST . "/" . DB_NAME);
            }
            
        } catch (PDOException $e) {
            $this->isConnected = false;
            
            // Log the error
            error_log("Database connection failed: " . $e->getMessage());
            
            // In development, show detailed error
            if (APP_ENV === 'development') {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        if (!$this->isConnected) {
            $this->connect();
        }
        return $this->connection;
    }
    
    public function isConnected() {
        return $this->isConnected;
    }
    
    public function reconnect() {
        $this->isConnected = false;
        $this->connect();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization (must be public in PHP 8+)
    public function __wakeup() {
        throw new Exception("Cannot unserialize Database instance");
    }
}

// Get database connection instances
$db = Database::getInstance()->getConnection();

// Also create mysqli connection for legacy compatibility
$con = null;
try {
    $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$con) {
        throw new Exception("MySQLi connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($con, DB_CHARSET);
    
    if (APP_ENV === 'development' && EnvLoader::getBool('SQL_DEBUG', false)) {
        error_log("MySQLi connection established successfully");
    }
    
} catch (Exception $e) {
    error_log("MySQLi connection failed: " . $e->getMessage());
    if (APP_ENV === 'development') {
        die("MySQLi connection failed: " . $e->getMessage());
    }
}

// Enhanced helper function for safe queries with better error handling
function executeQuery($query, $params = []) {
    global $db;
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Log queries in development if SQL_DEBUG is enabled
        if (APP_ENV === 'development' && EnvLoader::getBool('SQL_DEBUG', false)) {
            error_log("SQL Query executed: $query");
            if (!empty($params)) {
                error_log("SQL Params: " . json_encode($params));
            }
        }
        
        return $stmt;
        
    } catch (PDOException $e) {
        // Log the error with context
        error_log("SQL Query failed: " . $e->getMessage());
        error_log("SQL Query: $query");
        error_log("SQL Params: " . json_encode($params));
        
        // In development, show the error
        if (APP_ENV === 'development') {
            throw new Exception("Database query failed: " . $e->getMessage() . "\nQuery: $query");
        }
        
        return false;
    }
}

// Helper function to get single record with enhanced error handling
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    
    if ($stmt === false) {
        if (APP_ENV === 'development') {
            error_log("fetchOne failed for query: $query");
        }
        return false;
    }
    
    $result = $stmt->fetch();
    return $result !== false ? $result : false;
}

// Helper function to get multiple records with enhanced error handling
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    
    if ($stmt === false) {
        if (APP_ENV === 'development') {
            error_log("fetchAll failed for query: $query");
        }
        return [];
    }
    
    return $stmt->fetchAll();
}

// Helper function for INSERT/UPDATE/DELETE operations with enhanced error handling
function executeUpdate($query, $params = []) {
    $stmt = executeQuery($query, $params);
    
    if ($stmt === false) {
        if (APP_ENV === 'development') {
            error_log("executeUpdate failed for query: $query");
        }
        return false;
    }
    
    return $stmt->rowCount();
}

// Helper function to get last insert ID with error handling
function getLastInsertId() {
    global $db;
    
    try {
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Failed to get last insert ID: " . $e->getMessage());
        return false;
    }
}

// Helper function for transactions
function beginTransaction() {
    global $db;
    
    try {
        return $db->beginTransaction();
    } catch (PDOException $e) {
        error_log("Failed to begin transaction: " . $e->getMessage());
        return false;
    }
}

// Helper function to commit transaction
function commitTransaction() {
    global $db;
    
    try {
        return $db->commit();
    } catch (PDOException $e) {
        error_log("Failed to commit transaction: " . $e->getMessage());
        return false;
    }
}

// Helper function to rollback transaction
function rollbackTransaction() {
    global $db;
    
    try {
        return $db->rollback();
    } catch (PDOException $e) {
        error_log("Failed to rollback transaction: " . $e->getMessage());
        return false;
    }
}

// Enhanced helper function for safe queries with pagination
function fetchPaginated($query, $params = [], $page = 1, $perPage = 20) {
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Add LIMIT clause to query
    $paginatedQuery = $query . " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $results = fetchAll($paginatedQuery, $params);
    
    // Get total count for pagination info
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
    $countResult = fetchOne($countQuery, array_slice($params, 0, -2)); // Remove LIMIT params
    $total = $countResult ? $countResult['total'] : 0;
    
    return [
        'data' => $results,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
            'has_next' => $page < ceil($total / $perPage),
            'has_prev' => $page > 1
        ]
    ];
}

// Helper function to escape values for legacy mysqli queries (when needed)
function escapeString($value) {
    global $con;
    
    if ($con && is_string($value)) {
        return mysqli_real_escape_string($con, $value);
    }
    
    return $value;
}

// Database health check function
function checkDatabaseHealth() {
    global $db, $con;
    
    $health = [
        'pdo_connected' => false,
        'mysqli_connected' => false,
        'can_query' => false,
        'message' => 'Database health check failed'
    ];
    
    try {
        // Test PDO connection
        if ($db) {
            $stmt = $db->query("SELECT 1");
            if ($stmt) {
                $health['pdo_connected'] = true;
                $health['can_query'] = true;
            }
        }
        
        // Test MySQLi connection
        if ($con) {
            $result = mysqli_query($con, "SELECT 1");
            if ($result) {
                $health['mysqli_connected'] = true;
                mysqli_free_result($result);
            }
        }
        
        if ($health['pdo_connected'] && $health['can_query']) {
            $health['message'] = 'Database connections healthy';
        }
        
    } catch (Exception $e) {
        error_log("Database health check failed: " . $e->getMessage());
        $health['message'] = 'Database health check error: ' . $e->getMessage();
    }
    
    return $health;
}

// Auto-test database connection on include (only in development)
if (APP_ENV === 'development' && EnvLoader::getBool('SQL_DEBUG', false)) {
    $health = checkDatabaseHealth();
    if (!$health['pdo_connected']) {
        error_log("WARNING: PDO database connection issue detected");
    }
    if (!$health['mysqli_connected']) {
        error_log("WARNING: MySQLi database connection issue detected");
    }
}