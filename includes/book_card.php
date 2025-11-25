<?php
/**
 * Reusable Book Card Component
 * Displays book information in a card format
 */
function renderBookCard($book, $showActions = true) {
    global $pdo;
    
    // Get average rating
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE book_id = ?");
    $stmt->execute([$book['id']]);
    $ratingData = $stmt->fetch();
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $reviewCount = $ratingData['review_count'];
    
    // Get category name
    $categoryName = 'Uncategorized';
    if ($book['category_id']) {
        $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $catStmt->execute([$book['category_id']]);
        $category = $catStmt->fetch();
        if ($category) {
            $categoryName = $category['name'];
        }
    }
    
    $imagePath = !empty($book['image_path']) ? BASE_URL . 'uploads/' . $book['image_path'] : BASE_URL . 'assets/images/default_book.jpg';
    $isAvailable = $book['available_copies'] > 0 && $book['status'] === 'available';
    ?>
    <div class="col-md-4 col-lg-3 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-img-wrapper" style="height: 250px; overflow: hidden; background: #f0f0f0;">
                <img src="<?php echo $imagePath; ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($book['title']); ?>"
                     style="object-fit: cover; width: 100%; height: 100%;"
                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
            </div>
            <div class="card-body d-flex flex-column">
                <h6 class="card-title mb-2" style="min-height: 40px;">
                    <?php echo htmlspecialchars($book['title']); ?>
                </h6>
                <p class="text-muted small mb-2">
                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($book['author']); ?>
                </p>
                <p class="text-muted small mb-2">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($categoryName); ?>
                </p>
                <?php if ($avgRating > 0): ?>
                    <div class="mb-2">
                        <span class="text-warning">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="bi bi-star<?php echo $i < round($avgRating) ? '-fill' : ''; ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <small class="text-muted">(<?php echo $avgRating; ?>/5 - <?php echo $reviewCount; ?> reviews)</small>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <?php if ($isAvailable): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Available (<?php echo $book['available_copies']; ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="bi bi-x-circle"></i> Not Available
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mt-auto">
                    <a href="<?php echo BASE_URL; ?>pages/book_detail.php?id=<?php echo $book['id']; ?>" 
                       class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-eye"></i> View Details
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

