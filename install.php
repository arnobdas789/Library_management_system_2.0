<?php
/**
 * Installation Script
 * Run this file once to setup the database and initial configuration
 */

// Check if already installed
if (file_exists('config/installed.flag')) {
    die('System is already installed. Delete config/installed.flag to reinstall.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">📚 Library Management System - Installation</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $step = $_GET['step'] ?? 1;
                        $errors = [];
                        $success = false;
                        
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
                            // Database configuration
                            $db_host = $_POST['db_host'] ?? 'localhost';
                            $db_user = $_POST['db_user'] ?? 'root';
                            $db_pass = $_POST['db_pass'] ?? '';
                            $db_name = $_POST['db_name'] ?? 'library_management';
                            $base_url = $_POST['base_url'] ?? 'http://localhost/Library_management_system/';
                            
                            // Test database connection
                            try {
                                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                // Create database if not exists
                                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
                                $pdo->exec("USE `$db_name`");
                                
                                // Read and execute SQL file
                                $sql = file_get_contents('database/schema.sql');
                                $sql = str_replace('CREATE DATABASE IF NOT EXISTS library_management;', '', $sql);
                                $sql = str_replace('USE library_management;', '', $sql);
                                
                                $statements = explode(';', $sql);
                                foreach ($statements as $statement) {
                                    $statement = trim($statement);
                                    if (!empty($statement)) {
                                        $pdo->exec($statement);
                                    }
                                }
                                
                                // Create config file
                                $configContent = "<?php
// Database Configuration
define('DB_HOST', '$db_host');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('DB_NAME', '$db_name');
define('BASE_URL', '$base_url');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    \$pdo = new PDO(
        \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    die(\"Database connection failed: \" . \$e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset(\$_SESSION['user_id']);
}

function isAdmin() {
    return isset(\$_SESSION['role']) && (\$_SESSION['role'] === 'admin' || \$_SESSION['role'] === 'owner');
}

function isOwner() {
    return isset(\$_SESSION['role']) && \$_SESSION['role'] === 'owner';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function requireOwner() {
    requireLogin();
    if (!isOwner()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function getSetting(\$key, \$default = '') {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare(\"SELECT setting_value FROM settings WHERE setting_key = ?\");
        \$stmt->execute([\$key]);
        \$result = \$stmt->fetch();
        return \$result ? \$result['setting_value'] : \$default;
    } catch (PDOException \$e) {
        return \$default;
    }
}

function sanitize(\$data) {
    return htmlspecialchars(strip_tags(trim(\$data)));
}

function formatDate(\$date) {
    return date('M d, Y', strtotime(\$date));
}
?>";
                                
                                // Write config file
                                file_put_contents('config/config.php', $configContent);
                                
                                // Create directories
                                if (!file_exists('uploads')) {
                                    mkdir('uploads', 0777, true);
                                }
                                if (!file_exists('assets/images')) {
                                    mkdir('assets/images', 0777, true);
                                }
                                
                                // Create installed flag
                                file_put_contents('config/installed.flag', date('Y-m-d H:i:s'));
                                
                                $success = true;
                            } catch (PDOException $e) {
                                $errors[] = 'Database error: ' . $e->getMessage();
                            }
                        }
                        
                        if ($success):
                        ?>
                            <div class="alert alert-success">
                                <h4>✅ Installation Successful!</h4>
                                <p>The database has been created and configured successfully.</p>
                                <hr>
                                <p><strong>Default Admin Credentials:</strong></p>
                                <ul>
                                    <li>Username: <code>admin</code></li>
                                    <li>Password: <code>admin123</code></li>
                                </ul>
                                <p class="text-danger"><strong>⚠️ Please change the admin password after first login!</strong></p>
                                <a href="<?php echo $base_url ?? BASE_URL; ?>index.php" class="btn btn-primary">Go to Homepage</a>
                            </div>
                        <?php elseif ($step == 2): ?>
                            <h4>Step 2: Database Configuration</h4>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <p><?php echo htmlspecialchars($error); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Database User</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                                </div>
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass">
                                </div>
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" value="library_management" required>
                                </div>
                                <div class="mb-3">
                                    <label for="base_url" class="form-label">Base URL</label>
                                    <input type="url" class="form-control" id="base_url" name="base_url" value="http://localhost/Library_management_system/" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Install</button>
                            </form>
                        <?php else: ?>
                            <h4>Step 1: System Requirements Check</h4>
                            <?php
                            $requirements = [
                                'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                                'PDO Extension' => extension_loaded('pdo'),
                                'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
                                'Session Extension' => extension_loaded('session'),
                                'GD Extension (for images)' => extension_loaded('gd'),
                                'config/ directory writable' => is_writable('config') || (!file_exists('config') && is_writable('.')),
                                'uploads/ directory writable' => is_writable('uploads') || (!file_exists('uploads') && is_writable('.')),
                            ];
                            
                            $allPassed = true;
                            foreach ($requirements as $req => $passed) {
                                if (!$passed) $allPassed = false;
                                echo '<div class="mb-2">';
                                echo $passed ? '✅' : '❌';
                                echo ' ' . $req;
                                echo '</div>';
                            }
                            ?>
                            <hr>
                            <?php if ($allPassed): ?>
                                <div class="alert alert-success">
                                    All requirements met! You can proceed to installation.
                                </div>
                                <a href="?step=2" class="btn btn-primary">Next Step →</a>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    Please fix the issues above before proceeding.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

