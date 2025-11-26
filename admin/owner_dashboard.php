<?php
/**
 * Owner/Admin Dashboard
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$pageTitle = 'Owner Dashboard';

// Get statistics
$stats = [
    'total_books' => $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'available_books' => $pdo->query("SELECT COUNT(*) FROM books WHERE status = 'available' AND available_copies > 0")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'")->fetchColumn(),
    'total_borrows' => $pdo->query("SELECT COUNT(*) FROM borrow_records")->fetchColumn(),
    'active_borrows' => $pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status = 'borrowed'")->fetchColumn(),
    'overdue_borrows' => $pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status = 'borrowed' AND due_date < CURDATE()")->fetchColumn(),
    'total_reviews' => $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'total_fines' => $pdo->query("SELECT SUM(fine_amount) FROM borrow_records")->fetchColumn() ?: 0
];

// Get recent borrows
$recentBorrows = $pdo->query("
    SELECT br.*, b.title, b.author, u.username, u.full_name
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    ORDER BY br.created_at DESC
    LIMIT 10
")->fetchAll();

// Get overdue books
$overdueBooks = $pdo->query("
    SELECT br.*, b.title, b.author, u.username, u.full_name,
           DATEDIFF(CURDATE(), br.due_date) as days_overdue
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    WHERE br.status = 'borrowed' AND br.due_date < CURDATE()
    ORDER BY br.due_date ASC
    LIMIT 10
")->fetchAll();

// Get monthly borrow data for chart
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(borrow_date, '%Y-%m') as month, COUNT(*) as count
    FROM borrow_records
    WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-speedometer2"></i> Owner Dashboard</h2>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5><i class="bi bi-book"></i> Total Books</h5>
                <h2><?php echo $stats['total_books']; ?></h2>
                <small>Available: <?php echo $stats['available_books']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5><i class="bi bi-people"></i> Total Users</h5>
                <h2><?php echo $stats['total_users']; ?></h2>
                <small>Active: <?php echo $stats['active_users']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5><i class="bi bi-bookmark-check"></i> Total Borrows</h5>
                <h2><?php echo $stats['total_borrows']; ?></h2>
                <small>Active: <?php echo $stats['active_borrows']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5><i class="bi bi-exclamation-triangle"></i> Overdue</h5>
                <h2><?php echo $stats['overdue_borrows']; ?></h2>
                <small>Total Fines: $<?php echo number_format($stats['total_fines'], 2); ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>admin/book_form.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Book
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-secondary">
                        <i class="bi bi-tags"></i> Manage Categories
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/manage_users.php" class="btn btn-info">
                        <i class="bi bi-people"></i> Manage Users
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-text"></i> View Reports
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/settings.php" class="btn btn-warning">
                        <i class="bi bi-gear"></i> System Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Monthly Borrows Chart -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Borrows (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyBorrowsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Overdue Books -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue Books</h5>
            </div>
            <div class="card-body">
                <?php if (empty($overdueBooks)): ?>
                    <p class="text-muted">No overdue books.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueBooks as $book): ?>
                                    <tr>
                                        <td>
                                            <small><strong><?php echo htmlspecialchars($book['title']); ?></strong></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($book['full_name']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $book['days_overdue']; ?> days</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Borrows -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Borrows</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>User</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBorrows as $borrow): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrow['title']); ?></td>
                                    <td><?php echo htmlspecialchars($borrow['full_name']); ?></td>
                                    <td><?php echo formatDate($borrow['borrow_date']); ?></td>
                                    <td><?php echo formatDate($borrow['due_date']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $borrow['status'] === 'borrowed' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst($borrow['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Borrows Chart
const monthlyData = <?php echo json_encode($monthlyData); ?>;
const ctx = document.getElementById('monthlyBorrowsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
            label: 'Borrows',
            data: monthlyData.map(d => d.count),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

