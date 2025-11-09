<?php

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Home';
$search = sanitize($_GET['search'] ?? '');
$category_id = intval($_GET['category'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Get all categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Build query for books
$whereConditions = ["status != 'maintenance'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(title LIKE ? OR author LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($category_id > 0) {
    $whereConditions[] = "category_id = ?";
    $params[] = $category_id;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE $whereClause");
$countStmt->execute($params);
$totalBooks = $countStmt->fetchColumn();
$totalPages = ceil($totalBooks / $perPage);

// Get books
$stmt = $pdo->prepare("SELECT * FROM books WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get featured books (available books with highest ratings)
$featuredStmt = $pdo->query("
    SELECT b.*, AVG(r.rating) as avg_rating 
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    WHERE b.status = 'available' AND b.available_copies > 0
    GROUP BY b.id 
    ORDER BY avg_rating DESC, b.created_at DESC 
    LIMIT 6
");
$featuredBooks = $featuredStmt->fetchAll();

include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/book_card.php';
?>

<!-- Hero Section -->
<div class="hero-section bg-primary text-white py-5 mb-4 rounded">
    <div class="text-center">
        <h1 class="display-4"><i class="bi bi-book"></i> Welcome to Arnob Library Portal</h1>
        <p class="lead">Discover, Borrow, and Explore a World of Knowledge</p>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Search Books</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search by title, author, or description..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Featured Books Section -->
<?php if (empty($search) && $category_id == 0): ?>
    <div class="mb-5">
        <h3 class="mb-3"><i class="bi bi-star-fill text-warning"></i> Featured Books</h3>
        <div class="row">
            <?php foreach ($featuredBooks as $book): ?>
                <?php renderBookCard($book); ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- All Books Section -->
<div>
    <h3 class="mb-3">
        <?php if ($search || $category_id): ?>
            <i class="bi bi-list"></i> Search Results (<?php echo $totalBooks; ?> books found)
        <?php else: ?>
            <i class="bi bi-book"></i> All Books
        <?php endif; ?>
    </h3>
    
    <?php if (empty($books)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No books found. Try adjusting your search criteria.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($books as $book): ?>
                <?php renderBookCard($book); ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

