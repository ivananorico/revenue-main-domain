<?php
/**
 * Database Configuration for Business Tax and Regulatory Fee Payment System
 * Update these settings according to your MySQL server configuration
 */

// CORS Headers for cross-origin requests
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'gov_revenue'); // Change this to your database name
define('DB_USER', 'root'); // Change this to your MySQL username
define('DB_PASS', ''); // Change this to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'Business Tax & Regulatory Fee System');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Database Connection Class
 * Provides secure PDO connection with prepared statements
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a prepared statement with parameters
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function prepare($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Get last inserted ID
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
}

/**
 * Utility Functions
 */

/**
 * Send JSON response
 * @param mixed $data Response data
 * @param int $httpCode HTTP status code
 */
function sendResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 * @param string $message Error message
 * @param int $httpCode HTTP status code
 */
function sendError($message, $httpCode = 400) {
    sendResponse(['success' => false, 'message' => $message], $httpCode);
}

/**
 * Send success response
 * @param mixed $data Response data
 * @param string $message Success message
 */
function sendSuccess($data = null, $message = 'Success') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendResponse($response);
}

/**
 * Validate authentication
 * @return bool
 */
function isAuthenticated() {
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // For development/testing purposes, create a session if none exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = 'admin';
        $_SESSION['full_name'] = 'System Admin';
    }
    
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Validate required parameters
 * @param array $params Parameters to validate
 * @param array $required Required parameter names
 * @return bool
 */
function validateRequired($params, $required) {
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            return false;
        }
    }
    return true;
}

/**
 * Sanitize input data
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Log audit trail
 * @param string $action Action performed
 * @param string $table Table affected
 * @param int $recordId Record ID
 * @param array $oldValues Old values (optional)
 * @param array $newValues New values (optional)
 */
function logAudit($action, $table = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $db = Database::getInstance();
        $userId = getCurrentUserId();
        
        $meta = [
            'table' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $sql = "INSERT INTO audit_logs (user_id, action, meta) VALUES (?, ?, ?)";
        
        $db->prepare($sql, [
            $userId,
            $action,
            json_encode($meta)
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Format currency
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Format date
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Session will be started by isAuthenticated() function when needed
?>
