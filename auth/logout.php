<?php
/**
 * User Logout
 */
// Set custom session save path before starting
$sessionPath = __DIR__ . '/../sessions/';
if (!file_exists($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_writable($sessionPath) || is_writable(dirname($sessionPath))) {
    ini_set('session.save_path', $sessionPath);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, 
        $params['path'], 
        $params['domain'], 
        $params['secure'], 
        $params['httponly']
    );
}

// Destroy the session
@session_destroy();

// Get base URL for redirect (more reliable method)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = $protocol . $host . $scriptDir . '/';
// Normalize the URL (remove double slashes except after protocol)
$baseUrl = preg_replace('#([^:])//+#', '$1/', $baseUrl);

// Redirect to login page
header('Location: ' . $baseUrl . 'auth/login.php');
exit();

