<?php
/**
 * Borrow Success Page
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

$pageTitle = 'Borrow Success';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow border-success">
            <div class="card-header bg-success text-white text-center">
                <h3 class="mb-0"><i class="bi bi-check-circle-fill"></i> Book Borrowed Successfully!</h3>
            </div>
            <div class="card-body text-center">
                <?php if ($record): ?>
                    <div class="mb-4">
                        <i class="bi bi-book text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h5><?php echo htmlspecialchars($record['title']); ?></h5>
                    <p class="text-muted">by <?php echo htmlspecialchars($record['author']); ?></p>
                    <hr>
                    <div class="text-start">
                        <p><strong>Borrow Date:</strong> <?php echo formatDate($record['borrow_date']); ?></p>
                        <p><strong>Due Date:</strong> <?php echo formatDate($record['due_date']); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-primary">Borrowed</span></p>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> Please return the book on or before the due date to avoid fines.
                    </div>
                <?php else: ?>
                    <p>Borrowing confirmed!</p>
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

