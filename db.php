<?php
/**
 * Database Connection and Security Configuration
 * 
 * This file handles:
 * - Database connection
 * - CSRF token generation and validation
 * - Input sanitization
 * - Error logging
 * - Session security configuration
 * 
 * @version 1.0
 * @author Abdullah Al Hossain
 * @date 2025-12-18
 */

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Session security settings
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only send over HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cache_expire', 60);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

// Database credentials (use environment variables in production)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'gmail_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');

// Error logging configuration
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/error.log');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// ============================================================================
// ERROR LOGGING FUNCTIONS
// ============================================================================

/**
 * Initialize logging directory
 */
function initializeLogging() {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    // Create .htaccess to protect log files
    $htaccess = LOG_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}

/**
 * Log messages with different severity levels
 * 
 * @param string $message The message to log
 * @param string $level The severity level (DEBUG, INFO, WARNING, ERROR)
 * @param array $context Additional context data
 * @return bool True if logged successfully
 */
function logMessage($message, $level = 'INFO', $context = []) {
    initializeLogging();
    
    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'ANONYMOUS';
    $ipAddress = getClientIP();
    
    // Build context string
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = ' | Context: ' . json_encode($context);
    }
    
    $logEntry = "[{$timestamp}] [{$level}] [User: {$userId}] [IP: {$ipAddress}] {$message}{$contextStr}\n";
    
    return error_log($logEntry, 3, LOG_FILE);
}

/**
 * Get client IP address
 * 
 * @return string The client's IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

$mysqli = null;

/**
 * Establish database connection
 * 
 * @return mysqli|false Connection object or false on failure
 */
function getDBConnection() {
    global $mysqli;
    
    if ($mysqli !== null) {
        return $mysqli;
    }
    
    try {
        $mysqli = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            DB_PORT
        );
        
        if ($mysqli->connect_error) {
            logMessage(
                'Database connection failed: ' . $mysqli->connect_error,
                'ERROR'
            );
            die('Database connection failed. Please try again later.');
        }
        
        // Set charset
        if (!$mysqli->set_charset(DB_CHARSET)) {
            logMessage(
                'Charset setting failed: ' . $mysqli->error,
                'WARNING'
            );
        }
        
        logMessage('Database connection established', 'DEBUG');
        
        return $mysqli;
    } catch (Exception $e) {
        logMessage('Database connection exception: ' . $e->getMessage(), 'ERROR');
        die('Database connection failed. Please try again later.');
    }
}

/**
 * Close database connection
 */
function closeDBConnection() {
    global $mysqli;
    if ($mysqli !== null) {
        $mysqli->close();
        $mysqli = null;
    }
}

/**
 * Execute a prepared statement
 * 
 * @param string $query The SQL query with placeholders
 * @param array $params The parameters for the query
 * @param string $types The types of parameters (s=string, i=integer, d=double, b=blob)
 * @return mysqli_result|bool Result object or false on failure
 */
function executeQuery($query, $params = [], $types = '') {
    $conn = getDBConnection();
    
    if (!$conn) {
        logMessage('Failed to get database connection', 'ERROR');
        return false;
    }
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            logMessage('Query preparation failed: ' . $conn->error, 'ERROR', ['query' => $query]);
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            logMessage('Query execution failed: ' . $stmt->error, 'ERROR', ['query' => $query]);
            $stmt->close();
            return false;
        }
        
        return $stmt;
    } catch (Exception $e) {
        logMessage('Database query exception: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Get a single row from query result
 * 
 * @param string $query The SQL query
 * @param array $params The parameters
 * @param string $types The parameter types
 * @return array|null Associative array or null if no results
 */
function getRow($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    
    if (!$stmt) {
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row;
}

/**
 * Get multiple rows from query result
 * 
 * @param string $query The SQL query
 * @param array $params The parameters
 * @param string $types The parameter types
 * @return array Array of associative arrays
 */
function getRows($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    
    if (!$stmt) {
        return [];
    }
    
    $result = $stmt->get_result();
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $stmt->close();
    return $rows;
}

// ============================================================================
// INPUT SANITIZATION FUNCTIONS
// ============================================================================

/**
 * Sanitize string input
 * 
 * @param string $input The input to sanitize
 * @return string Sanitized string
 */
function sanitizeString($input) {
    if (!is_string($input)) {
        return '';
    }
    
    // Remove leading/trailing whitespace
    $input = trim($input);
    
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Escape HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Sanitize email input
 * 
 * @param string $email The email to sanitize
 * @return string Sanitized email
 */
function sanitizeEmail($email) {
    $email = trim(strtolower($email));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    
    return $email;
}

/**
 * Sanitize integer input
 * 
 * @param mixed $input The input to sanitize
 * @return int Sanitized integer
 */
function sanitizeInt($input) {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize URL input
 * 
 * @param string $url The URL to sanitize
 * @return string Sanitized URL
 */
function sanitizeURL($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Sanitize array of inputs
 * 
 * @param array $data The array to sanitize
 * @param array $types The types for each key (string, email, int, url)
 * @return array Sanitized array
 */
function sanitizeArray($data, $types = []) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        $type = $types[$key] ?? 'string';
        
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value, $types);
        } else {
            switch ($type) {
                case 'email':
                    $sanitized[$key] = sanitizeEmail($value);
                    break;
                case 'int':
                case 'integer':
                    $sanitized[$key] = sanitizeInt($value);
                    break;
                case 'url':
                    $sanitized[$key] = sanitizeURL($value);
                    break;
                case 'string':
                default:
                    $sanitized[$key] = sanitizeString($value);
            }
        }
    }
    
    return $sanitized;
}

// ============================================================================
// CSRF TOKEN FUNCTIONS
// ============================================================================

/**
 * Generate CSRF token
 * 
 * @return string The generated token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
        
        logMessage('CSRF token generated', 'DEBUG');
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token from session
 * 
 * @return string|null The CSRF token or null if not set
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Verify CSRF token
 * 
 * @param string $token The token to verify
 * @param int $maxAge Maximum age of token in seconds (default 3600)
 * @return bool True if token is valid
 */
function verifyCSRFToken($token, $maxAge = 3600) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        logMessage('CSRF token verification failed: Empty token', 'WARNING');
        return false;
    }
    
    // Check token value
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        logMessage('CSRF token verification failed: Invalid token', 'WARNING', ['ip' => getClientIP()]);
        return false;
    }
    
    // Check token age
    $tokenTime = $_SESSION['csrf_token_time'] ?? 0;
    if (time() - $tokenTime > $maxAge) {
        logMessage('CSRF token verification failed: Token expired', 'WARNING');
        return false;
    }
    
    logMessage('CSRF token verified successfully', 'DEBUG');
    return true;
}

/**
 * Regenerate CSRF token (use after sensitive operations)
 * 
 * @return string The new token
 */
function regenerateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
    
    logMessage('CSRF token regenerated', 'DEBUG');
    
    return $_SESSION['csrf_token'];
}

// ============================================================================
// SESSION SECURITY FUNCTIONS
// ============================================================================

/**
 * Initialize session security
 */
function initializeSessionSecurity() {
    // Regenerate session ID on login
    if (!isset($_SESSION['session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['session_initialized'] = true;
        $_SESSION['created_at'] = time();
        
        logMessage('Session initialized with security settings', 'DEBUG');
    }
}

/**
 * Validate session
 * 
 * @param int $maxLifetime Maximum session lifetime in seconds (default 3600)
 * @return bool True if session is valid
 */
function validateSession($maxLifetime = 3600) {
    // Check if session is initialized
    if (!isset($_SESSION['session_initialized'])) {
        return false;
    }
    
    // Check session creation time
    $createdAt = $_SESSION['created_at'] ?? 0;
    if (time() - $createdAt > $maxLifetime) {
        logMessage('Session validation failed: Session expired', 'WARNING');
        destroySession();
        return false;
    }
    
    // Check user agent (optional, can cause issues with some proxies)
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logMessage('Session validation failed: User agent mismatch', 'WARNING');
        destroySession();
        return false;
    }
    
    // Store user agent on first validation
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    return true;
}

/**
 * Destroy session securely
 */
function destroySession() {
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
    
    logMessage('Session destroyed', 'INFO');
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && validateSession();
}

/**
 * Get logged-in user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getUserID() {
    return isUserLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Set user session data after login
 * 
 * @param int $userId The user ID
 * @param array $userData Additional user data to store
 */
function setUserSession($userId, $userData = []) {
    initializeSessionSecurity();
    
    $_SESSION['user_id'] = (int) $userId;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = getClientIP();
    
    // Store additional user data
    foreach ($userData as $key => $value) {
        $_SESSION[$key] = $value;
    }
    
    // Regenerate session ID on login
    session_regenerate_id(true);
    
    logMessage('User session set', 'INFO', ['user_id' => $userId]);
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Initialize logging on page load
initializeLogging();

// Initialize session security
initializeSessionSecurity();

// Log page access
logMessage('Page accessed: ' . $_SERVER['REQUEST_URI'], 'DEBUG', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => getClientIP()
]);

// Generate CSRF token for forms
generateCSRFToken();

// Register shutdown function to close database connection
register_shutdown_function('closeDBConnection');

?>
