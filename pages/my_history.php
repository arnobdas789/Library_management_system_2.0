<?php
/**
 * User Borrow History Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all'; // all, borrowed, returned, overdue

// Build query based on filter
$whereClause = "user_id = ?";
$params = [$user_id];

if ($filter === 'borrowed') {
    $whereClause .= " AND status = 'borrowed'";
} elseif ($filter === 'returned') {
    $whereClause .= " AND status = 'returned'";
} elseif ($filter === 'overdue') {
    $whereClause .= " AND status = 'borrowed' AND due_date < CURDATE()";
}

$stmt = $pdo->prepare("
    SELECT br.*, b.title, b.author, b.image_path,
           DATEDIFF(CURDATE(), br.due_date) as days_overdue
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE $whereClause
    ORDER BY br.created_at DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN status = 'borrowed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
        SUM(fine_amount) as total_fines
    FROM borrow_records
    WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch();

$pageTitle = 'My History';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4"><i class="bi bi-clock-history"></i> My Borrowing History</h2>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Books</h5>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5>Currently Borrowed</h5>
                        <h3><?php echo $stats['borrowed']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>Returned</h5>
                        <h3><?php echo $stats['returned']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5>Overdue</h5>
                        <h3><?php echo $stats['overdue']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                        All Records
                    </a>
                    <a href="?filter=borrowed" class="btn btn-<?php echo $filter === 'borrowed' ? 'primary' : 'outline-primary'; ?>">
                        Currently Borrowed
                    </a>
                    <a href="?filter=returned" class="btn btn-<?php echo $filter === 'returned' ? 'primary' : 'outline-primary'; ?>">
                        Returned
                    </a>
                    <a href="?filter=overdue" class="btn btn-<?php echo $filter === 'overdue' ? 'primary' : 'outline-primary'; ?>">
                        Overdue
                    </a>
                </div>
            </div>
        </div>
        
        <!-- History Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($records)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No records found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo !empty($record['image_path']) ? BASE_URL . 'uploads/' . $record['image_path'] : BASE_URL . 'assets/images/default_book.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($record['title']); ?>"
                                                     style="width: 50px; height: 70px; object-fit: cover; margin-right: 10px;"
                                                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($record['title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['author']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo formatDate($record['borrow_date']); ?></td>
                                        <td>
                                            <?php echo formatDate($record['due_date']); ?>
                                            <?php if ($record['status'] === 'borrowed' && $record['days_overdue'] > 0): ?>
                                                <br><small class="text-danger"><?php echo $record['days_overdue']; ?> days overdue</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $record['return_date'] ? formatDate($record['return_date']) : '-'; ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'secondary';
                                            if ($record['status'] === 'borrowed') {
                                                $badgeClass = $record['days_overdue'] > 0 ? 'danger' : 'primary';
                                            } elseif ($record['status'] === 'returned') {
                                                $badgeClass = 'success';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                            <?php if (strpos($record['notes'] ?? '', 'RESERVED') !== false): ?>
                                                <br><span class="badge bg-warning mt-1">Reserved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['fine_amount'] > 0): ?>
                                                <span class="text-danger">$<?php echo number_format($record['fine_amount'], 2); ?></span>
                                            <?php else: ?>
                                                $0.00
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>pages/book_detail.php?id=<?php echo $record['book_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($stats['total_fines'] > 0): ?>
            <div class="alert alert-warning mt-3">
                <strong>Total Fines:</strong> $<?php echo number_format($stats['total_fines'], 2); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

