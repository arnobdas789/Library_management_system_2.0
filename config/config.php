<?php
/**
 * Database Configuration File
 * Smart Library Management System
 */

// Set custom session save path (within project directory)
define('SESSION_PATH', __DIR__ . '/../sessions/');

// Create session directory if it doesn't exist
if (!file_exists(SESSION_PATH)) {
    @mkdir(SESSION_PATH, 0777, true);
    
    // Create .htaccess file to protect session files from direct access
    $htaccessContent = "Order Deny,Allow\nDeny from all";
    @file_put_contents(SESSION_PATH . '.htaccess', $htaccessContent);
}

// Set session save path before starting session
if (session_status() === PHP_SESSION_NONE) {
    // Use custom session path if directory is writable
    if (is_writable(SESSION_PATH) || is_writable(dirname(SESSION_PATH))) {
        ini_set('session.save_path', SESSION_PATH);
    }
    
    // Set session cookie parameters for security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Start session with error suppression and custom handling
    @session_start();
    
    // If session start failed, try to fix permissions and retry
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Clear any existing session data
        $_SESSION = array();
        
        // Try to start session again
        if (!@session_start()) {
            // If still failing, try using default path but handle gracefully
            error_log("Session start failed. Check directory permissions: " . SESSION_PATH);
        }
    }
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_management');

// Application configuration
define('BASE_URL', 'http://localhost/Library_management_system/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Database connection using PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin or owner
 */
function isAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'owner');
}

/**
 * Check if user is owner
 */
function isOwner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

/**
 * Require admin access - redirect if not admin/owner
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

/**
 * Require owner access - redirect if not owner
 */
function requireOwner() {
    requireLogin();
    if (!isOwner()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

/**
 * Get setting value from database
 */
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Format date
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Calculate days between dates
 */
function daysBetween($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

?>

