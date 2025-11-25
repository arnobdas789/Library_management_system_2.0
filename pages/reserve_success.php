<?php
/**
 * Reserve Success Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

$record_id = intval($_GET['record_id'] ?? 0);

if ($record_id) {
    $stmt = $pdo->prepare("
        SELECT br.*, b.title, b.author 
        FROM borrow_records br 
        JOIN books b ON br.book_id = b.id 
        WHERE br.id = ? AND br.user_id = ?
    ");
    $stmt->execute([$record_id, $_SESSION['user_id']]);
    $record = $stmt->fetch();
} else {
    $record = null;
}

$pageTitle = 'Reservation Success';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow border-warning">
            <div class="card-header bg-warning text-dark text-center">
                <h3 class="mb-0"><i class="bi bi-bookmark-check-fill"></i> Book Reserved Successfully!</h3>
            </div>
            <div class="card-body text-center">
                <?php if ($record): ?>
                    <div class="mb-4">
                        <i class="bi bi-bookmark text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h5><?php echo htmlspecialchars($record['title']); ?></h5>
                    <p class="text-muted">by <?php echo htmlspecialchars($record['author']); ?></p>
                    <hr>
                    <div class="text-start">
                        <p><strong>Reservation Date:</strong> <?php echo formatDate($record['borrow_date']); ?></p>
                        <p><strong>Expires On:</strong> <?php echo formatDate($record['due_date']); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-warning">Reserved</span></p>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> You will be notified when this book becomes available for borrowing.
                    </div>
                <?php else: ?>
                    <p>Reservation confirmed!</p>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>pages/my_history.php" class="btn btn-primary">
                        <i class="bi bi-clock-history"></i> View My History
                    </a>
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary">
                        <i class="bi bi-house"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

