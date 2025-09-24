<?php
// data/env_loader.php
// Simple and secure environment variable loader for AIMS system

class EnvLoader {
    private static $loaded = false;
    
    /**
     * Load environment variables from .env file
     * @param string $path Path to .env file
     * @throws Exception If .env file is not found or not readable
     */
    public static function load($path = null) {
        // Prevent loading multiple times
        if (self::$loaded) {
            return;
        }
        
        // Default path to .env file in project root
        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }
        
        // Check if file exists and is readable
        if (!file_exists($path)) {
            throw new Exception(".env file not found at: $path");
        }
        
        if (!is_readable($path)) {
            throw new Exception(".env file is not readable: $path");
        }
        
        // Read and parse the .env file
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new Exception("Failed to read .env file: $path");
        }
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Only set if not already set (allows server environment to override)
                if (!array_key_exists($name, $_ENV) && !getenv($name)) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with default fallback
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Get boolean environment variable
     * @param string $key Environment variable name
     * @param bool $default Default value
     * @return bool
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Get integer environment variable
     * @param string $key Environment variable name
     * @param int $default Default value
     * @return int
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
    
    /**
     * Check if environment variable exists and is not empty
     * @param string $key Environment variable name
     * @return bool
     */
    public static function has($key) {
        $value = self::get($key);
        return $value !== null && $value !== '';
    }
    
    /**
     * Validate required environment variables
     * @param array $requiredVars Array of required variable names
     * @throws Exception If any required variable is missing
     */
    public static function validateRequired($requiredVars = []) {
        $missing = [];
        
        foreach ($requiredVars as $var) {
            if (!self::has($var)) {
                $missing[] = $var;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Missing required environment variables: " . implode(', ', $missing));
        }
    }
}

// Auto-load environment variables when this file is included
try {
    EnvLoader::load();
} catch (Exception $e) {
    error_log("Environment loading failed: " . $e->getMessage());
    
    // In development, show the error
    if (EnvLoader::get('APP_ENV', 'production') === 'development') {
        die("Environment Error: " . $e->getMessage());
    } else {
        die("Configuration error. Please contact the administrator.");
    }
}
?>