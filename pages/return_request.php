<?php
/**
 * Return Request Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

$record_id = intval($_GET['record_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$record_id) {
    header('Location: ' . BASE_URL . 'pages/my_history.php');
    exit();
}

// Get borrow record
$stmt = $pdo->prepare("
    SELECT br.*, b.title, b.author, b.image_path
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE br.id = ? AND br.user_id = ? AND br.status = 'borrowed' AND br.approval_status = 'approved'
");
$stmt->execute([$record_id, $user_id]);
$record = $stmt->fetch();

if (!$record) {
    echo "<script>alert('Invalid return request.'); window.location.href='" . BASE_URL . "pages/my_history.php';</script>";
    exit();
}

// Process return request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update borrow record to pending return
        $stmt = $pdo->prepare("
            UPDATE borrow_records 
            SET request_type = 'return', 
                approval_status = 'pending',
                notes = CONCAT(COALESCE(notes, ''), ' RETURN_REQUESTED')
            WHERE id = ?
        ");
        $stmt->execute([$record_id]);
        
        // Create notification for admin
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'general', ?, ?, ?)");
        $adminUsers = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($adminUsers as $admin) {
            $stmt->execute([
                $admin['id'],
                'New Return Request',
                "User " . htmlspecialchars($_SESSION['username']) . " has requested to return: " . htmlspecialchars($record['title']),
                $record_id
            ]);
        }
        
        $pdo->commit();
        
        echo "<script>alert('Return request submitted successfully! Waiting for admin approval.'); window.location.href='" . BASE_URL . "pages/my_history.php';</script>";
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Error submitting return request. Please try again.'); window.location.href='" . BASE_URL . "pages/my_history.php';</script>";
        exit();
    }
}

$pageTitle = 'Return Book';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-arrow-return-left"></i> Return Book</h4>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo !empty($record['image_path']) ? BASE_URL . 'uploads/' . $record['image_path'] : BASE_URL . 'assets/images/default_book.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($record['title']); ?>"
                             style="width: 80px; height: 120px; object-fit: cover; margin-right: 15px;"
                             onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
                        <div>
                            <h5><?php echo htmlspecialchars($record['title']); ?></h5>
                            <p class="text-muted mb-0">by <?php echo htmlspecialchars($record['author']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="bi bi-info-circle"></i> Return Request</h6>
                    <p class="mb-2">Your return request will be sent to the admin for approval. You will be notified once it's approved.</p>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> Borrow Information</h6>
                    <ul class="mb-0">
                        <li><strong>Borrow Date:</strong> <?php echo formatDate($record['borrow_date']); ?></li>
                        <li><strong>Due Date:</strong> <?php echo formatDate($record['due_date']); ?></li>
                        <?php 
                        $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($record['due_date'])) / 86400);
                        if ($daysOverdue > 0): 
                        ?>
                            <li class="text-danger"><strong>Days Overdue:</strong> <?php echo $daysOverdue; ?> days</li>
                            <li class="text-danger"><strong>Estimated Fine:</strong> $<?php echo number_format($daysOverdue * floatval(getSetting('fine_per_day', 5.00)), 2); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <form method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Submit Return Request
                        </button>
                        <a href="<?php echo BASE_URL; ?>pages/my_history.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

