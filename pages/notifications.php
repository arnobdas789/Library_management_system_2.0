<?php
/**
 * Notifications Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Mark notification as read
if (isset($_GET['read'])) {
    $notif_id = intval($_GET['read']);
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    } catch (PDOException $e) {
        // Table might not exist
    }
    header('Location: ' . BASE_URL . 'pages/notifications.php');
    exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Table might not exist
    }
    header('Location: ' . BASE_URL . 'pages/notifications.php');
    exit();
}

// Get all notifications
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}

// Get unread count
try {
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$user_id]);
    $unreadCount = $unreadStmt->fetchColumn();
} catch (PDOException $e) {
    $unreadCount = 0;
}

$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell"></i> Notifications</h2>
            <?php if ($unreadCount > 0): ?>
                <a href="?read_all=1" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check-all"></i> Mark All as Read
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No notifications.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item <?php echo $notif['is_read'] == 0 ? 'list-group-item-primary' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 <?php echo $notif['is_read'] == 0 ? 'fw-bold' : ''; ?>">
                                <?php echo htmlspecialchars($notif['title']); ?>
                            </h5>
                            <small><?php echo formatDate($notif['created_at']); ?></small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <?php if ($notif['is_read'] == 0): ?>
                            <a href="?read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-check"></i> Mark as Read
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

