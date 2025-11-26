<?php
/**
 * System Settings Page
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'max_borrow_limit' => intval($_POST['max_borrow_limit'] ?? 5),
        'borrow_duration_days' => intval($_POST['borrow_duration_days'] ?? 14),
        'fine_per_day' => floatval($_POST['fine_per_day'] ?? 5.00),
        'reservation_duration_days' => intval($_POST['reservation_duration_days'] ?? 3),
        'library_name' => sanitize($_POST['library_name'] ?? ''),
        'library_email' => sanitize($_POST['library_email'] ?? ''),
        'library_phone' => sanitize($_POST['library_phone'] ?? '')
    ];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $pdo->commit();
        $success = 'Settings updated successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get all settings
$settingsStmt = $pdo->query("SELECT * FROM settings ORDER BY setting_key");
$settingsData = $settingsStmt->fetchAll();
$settings = [];
foreach ($settingsData as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$pageTitle = 'System Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-gear"></i> System Settings</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <h5 class="mb-3">Borrowing Rules</h5>
                    <div class="mb-3">
                        <label for="max_borrow_limit" class="form-label">Maximum Borrow Limit</label>
                        <input type="number" class="form-control" id="max_borrow_limit" name="max_borrow_limit" 
                               min="1" required value="<?php echo $settings['max_borrow_limit']; ?>">
                        <small class="text-muted">Maximum number of books a user can borrow at once</small>
                    </div>
                    <div class="mb-3">
                        <label for="borrow_duration_days" class="form-label">Borrow Duration (Days)</label>
                        <input type="number" class="form-control" id="borrow_duration_days" name="borrow_duration_days" 
                               min="1" required value="<?php echo $settings['borrow_duration_days']; ?>">
                        <small class="text-muted">Number of days a book can be borrowed</small>
                    </div>
                    <div class="mb-3">
                        <label for="fine_per_day" class="form-label">Fine per Day ($)</label>
                        <input type="number" class="form-control" id="fine_per_day" name="fine_per_day" 
                               step="0.01" min="0" required value="<?php echo $settings['fine_per_day']; ?>">
                        <small class="text-muted">Fine amount per day for overdue books</small>
                    </div>
                    <div class="mb-3">
                        <label for="reservation_duration_days" class="form-label">Reservation Duration (Days)</label>
                        <input type="number" class="form-control" id="reservation_duration_days" name="reservation_duration_days" 
                               min="1" required value="<?php echo $settings['reservation_duration_days']; ?>">
                        <small class="text-muted">Number of days a reservation is valid</small>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Library Information</h5>
                    <div class="mb-3">
                        <label for="library_name" class="form-label">Library Name</label>
                        <input type="text" class="form-control" id="library_name" name="library_name" 
                               required value="<?php echo htmlspecialchars($settings['library_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="library_email" class="form-label">Library Email</label>
                        <input type="email" class="form-control" id="library_email" name="library_email" 
                               required value="<?php echo htmlspecialchars($settings['library_email']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="library_phone" class="form-label">Library Phone</label>
                        <input type="text" class="form-control" id="library_phone" name="library_phone" 
                               value="<?php echo htmlspecialchars($settings['library_phone']); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Database Backup Section -->
        <div class="card shadow mt-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-database"></i> Database Backup</h5>
            </div>
            <div class="card-body">
                <p>To backup your database, use phpMyAdmin or run the following command:</p>
                <code>mysqldump -u root -p library_management > backup.sql</code>
                <div class="mt-3">
                    <a href="<?php echo BASE_URL; ?>admin/backup_database.php" class="btn btn-warning">
                        <i class="bi bi-download"></i> Download Database Backup (SQL)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

