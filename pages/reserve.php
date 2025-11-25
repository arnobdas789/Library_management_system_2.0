<?php
/**
 * Book Reservation Page
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

// Check if book is already available
if ($book['available_copies'] > 0 && $book['status'] === 'available') {
    header('Location: ' . BASE_URL . 'pages/borrow.php?book_id=' . $book_id);
    exit();
}

// Check if user already reserved this book
$existingReserve = $pdo->prepare("SELECT id FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
$existingReserve->execute([$user_id, $book_id]);
if ($existingReserve->fetch()) {
    echo "<script>alert('You have already reserved/borrowed this book.'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
    exit();
}

// Process reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reservationDuration = intval(getSetting('reservation_duration_days', 3));
        $reserveDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime("+$reservationDuration days"));
        
        // Create reservation record (using borrow_records table with special handling)
        $stmt = $pdo->prepare("INSERT INTO borrow_records (user_id, book_id, borrow_date, due_date, status, notes) VALUES (?, ?, ?, ?, 'borrowed', 'RESERVED')");
        $stmt->execute([$user_id, $book_id, $reserveDate, $expiryDate]);
        
        // Update book status to reserved if no copies available
        if ($book['available_copies'] == 0) {
            $stmt = $pdo->prepare("UPDATE books SET status = 'reserved' WHERE id = ?");
            $stmt->execute([$book_id]);
        }
        
        echo "<script>alert('Book reserved successfully! Reservation expires on: $expiryDate'); window.location.href='" . BASE_URL . "pages/reserve_success.php?record_id=" . $pdo->lastInsertId() . "';</script>";
        exit();
    } catch (PDOException $e) {
        echo "<script>alert('Error reserving book. Please try again.'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
        exit();
    }
}

$pageTitle = 'Reserve Book';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="bi bi-bookmark"></i> Reserve Book</h4>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                    <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="bi bi-info-circle"></i> Reservation Information</h6>
                    <ul class="mb-0">
                        <li>This book is currently not available</li>
                        <li>Reservation Duration: <?php echo getSetting('reservation_duration_days', 3); ?> days</li>
                        <li>Reservation Expires: <?php echo date('M d, Y', strtotime('+' . getSetting('reservation_duration_days', 3) . ' days')); ?></li>
                        <li>You will be notified when the book becomes available</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-check-circle"></i> Confirm Reservation
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

