<?php
/**
 * Book Borrowing Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

$book_id = intval($_GET['book_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$book_id) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Get book details
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Check if book is available
if ($book['available_copies'] <= 0 || $book['status'] !== 'available') {
    echo "<script>alert('This book is not available for borrowing.'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
    exit();
}

// Check user's current borrow count
$maxLimit = intval(getSetting('max_borrow_limit', 5));
$currentBorrows = $pdo->prepare("SELECT COUNT(*) FROM borrow_records WHERE user_id = ? AND status = 'borrowed'");
$currentBorrows->execute([$user_id]);
$borrowCount = $currentBorrows->fetchColumn();

if ($borrowCount >= $maxLimit) {
    echo "<script>alert('You have reached the maximum borrowing limit ($maxLimit books). Please return some books first.'); window.location.href='" . BASE_URL . "pages/my_history.php';</script>";
    exit();
}

// Check if user already borrowed this book
$existingBorrow = $pdo->prepare("SELECT id FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
$existingBorrow->execute([$user_id, $book_id]);
if ($existingBorrow->fetch()) {
    echo "<script>alert('You have already borrowed this book.'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
    exit();
}

// Process borrowing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $borrowDuration = intval(getSetting('borrow_duration_days', 14));
        $borrowDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime("+$borrowDuration days"));
        
        // Create borrow record
        $stmt = $pdo->prepare("INSERT INTO borrow_records (user_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
        $stmt->execute([$user_id, $book_id, $borrowDate, $dueDate]);
        
        // Update book availability
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1, status = CASE WHEN available_copies - 1 = 0 THEN 'borrowed' ELSE 'available' END WHERE id = ?");
        $stmt->execute([$book_id]);
        
        $pdo->commit();
        
        echo "<script>alert('Book borrowed successfully! Due date: $dueDate'); window.location.href='" . BASE_URL . "pages/borrow_success.php?record_id=" . $pdo->lastInsertId() . "';</script>";
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Error borrowing book. Please try again.'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
        exit();
    }
}

$pageTitle = 'Borrow Book';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-book"></i> Borrow Book</h4>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                    <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> Borrowing Information</h6>
                    <ul class="mb-0">
                        <li>Borrow Duration: <?php echo getSetting('borrow_duration_days', 14); ?> days</li>
                        <li>Due Date: <?php echo date('M d, Y', strtotime('+' . getSetting('borrow_duration_days', 14) . ' days')); ?></li>
                        <li>Fine per day (if overdue): $<?php echo getSetting('fine_per_day', 5.00); ?></li>
                        <li>Your current borrows: <?php echo $borrowCount; ?>/<?php echo $maxLimit; ?></li>
                    </ul>
                </div>
                
                <form method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Confirm Borrow
                        </button>
                        <a href="<?php echo BASE_URL; ?>pages/book_detail.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

