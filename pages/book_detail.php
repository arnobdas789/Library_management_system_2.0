<?php
/**
 * Book Detail Page with Reviews
 */
require_once __DIR__ . '/../config/config.php';

$book_id = intval($_GET['id'] ?? 0);

if (!$book_id) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Get book details
$stmt = $pdo->prepare("SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Get average rating and review count
$ratingStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE book_id = ?");
$ratingStmt->execute([$book_id]);
$ratingData = $ratingStmt->fetch();
$avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
$reviewCount = $ratingData['review_count'];

// Get reviews
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.username, u.full_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.book_id = ? 
    ORDER BY r.created_at DESC
");
$reviewsStmt->execute([$book_id]);
$reviews = $reviewsStmt->fetchAll();

// Check if user can review (has borrowed this book)
$canReview = false;
if (isLoggedIn()) {
    $borrowCheck = $pdo->prepare("SELECT id FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'returned'");
    $borrowCheck->execute([$_SESSION['user_id'], $book_id]);
    $canReview = $borrowCheck->fetch() !== false;
    
    // Check if user already reviewed
    $reviewCheck = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND book_id = ?");
    $reviewCheck->execute([$_SESSION['user_id'], $book_id]);
    if ($reviewCheck->fetch()) {
        $canReview = false; // Already reviewed
    }
}

$isAvailable = $book['available_copies'] > 0 && $book['status'] === 'available';
$imagePath = !empty($book['image_path']) ? BASE_URL . 'uploads/' . $book['image_path'] : BASE_URL . 'assets/images/default_book.jpg';

$pageTitle = $book['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <img src="<?php echo $imagePath; ?>" 
                 class="card-img-top" 
                 alt="<?php echo htmlspecialchars($book['title']); ?>"
                 style="height: 400px; object-fit: cover;"
                 onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h2>
                <p class="text-muted">
                    <i class="bi bi-person"></i> <strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?>
                </p>
                <p class="text-muted">
                    <i class="bi bi-tag"></i> <strong>Category:</strong> <?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?>
                </p>
                <?php if ($book['isbn']): ?>
                    <p class="text-muted">
                        <i class="bi bi-upc"></i> <strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?>
                    </p>
                <?php endif; ?>
                <?php if ($book['published_year']): ?>
                    <p class="text-muted">
                        <i class="bi bi-calendar"></i> <strong>Published:</strong> <?php echo $book['published_year']; ?>
                    </p>
                <?php endif; ?>
                <?php if ($book['publisher']): ?>
                    <p class="text-muted">
                        <i class="bi bi-building"></i> <strong>Publisher:</strong> <?php echo htmlspecialchars($book['publisher']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($avgRating > 0): ?>
                    <div class="mb-3">
                        <span class="text-warning fs-4">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="bi bi-star<?php echo $i < round($avgRating) ? '-fill' : ''; ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <span class="ms-2"><?php echo $avgRating; ?>/5 (<?php echo $reviewCount; ?> reviews)</span>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No ratings yet</p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <?php if ($isAvailable): ?>
                        <span class="badge bg-success fs-6">
                            <i class="bi bi-check-circle"></i> Available (<?php echo $book['available_copies']; ?> copies)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6">
                            <i class="bi bi-x-circle"></i> Not Available
                        </span>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <h5>Description</h5>
                <p><?php echo nl2br(htmlspecialchars($book['description'] ?? 'No description available.')); ?></p>
                
                <hr>
                
                <?php if (isLoggedIn()): ?>
                    <div class="d-flex gap-2">
                        <?php if ($isAvailable): ?>
                            <a href="<?php echo BASE_URL; ?>pages/borrow.php?book_id=<?php echo $book_id; ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-book"></i> Borrow Book
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>pages/reserve.php?book_id=<?php echo $book_id; ?>" 
                               class="btn btn-warning">
                                <i class="bi bi-bookmark"></i> Reserve Book
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Login to Borrow
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reviews Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-chat-left-text"></i> Reviews (<?php echo $reviewCount; ?>)</h4>
            </div>
            <div class="card-body">
                <?php if ($canReview): ?>
                    <div class="mb-4">
                        <h5>Write a Review</h5>
                        <form method="POST" action="<?php echo BASE_URL; ?>pages/review.php">
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <div class="mb-3">
                                <label for="rating" class="form-label">Rating</label>
                                <select class="form-select" id="rating" name="rating" required>
                                    <option value="">Select rating</option>
                                    <option value="5">5 - Excellent</option>
                                    <option value="4">4 - Very Good</option>
                                    <option value="3">3 - Good</option>
                                    <option value="2">2 - Fair</option>
                                    <option value="1">1 - Poor</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Comment</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Submit Review
                            </button>
                        </form>
                    </div>
                    <hr>
                <?php endif; ?>
                
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet. Be the first to review!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                    <small class="text-muted">(@<?php echo htmlspecialchars($review['username']); ?>)</small>
                                </div>
                                <div>
                                    <span class="text-warning">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i < $review['rating'] ? '-fill' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                </div>
                            </div>
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> <?php echo formatDate($review['created_at']); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

