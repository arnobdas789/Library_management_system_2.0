<?php
/**
 * Reports Page
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$month = $_GET['month'] ?? date('Y-m');
$export = $_GET['export'] ?? '';

// Get monthly statistics
$monthlyStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_borrows,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'borrowed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
        SUM(fine_amount) as total_fines
    FROM borrow_records
    WHERE DATE_FORMAT(borrow_date, '%Y-%m') = ?
");
$monthlyStats->execute([$month]);
$stats = $monthlyStats->fetch();

// Get borrow records for the month
$records = $pdo->prepare("
    SELECT br.*, b.title, b.author, u.username, u.full_name, u.email
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    WHERE DATE_FORMAT(br.borrow_date, '%Y-%m') = ?
    ORDER BY br.borrow_date DESC
");
$records->execute([$month]);
$monthlyRecords = $records->fetchAll();

// Get top borrowed books
$topBooks = $pdo->query("
    SELECT b.title, b.author, COUNT(br.id) as borrow_count
    FROM books b
    JOIN borrow_records br ON b.id = br.book_id
    GROUP BY b.id
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll();

// Get top users
$topUsers = $pdo->query("
    SELECT u.username, u.full_name, COUNT(br.id) as borrow_count
    FROM users u
    JOIN borrow_records br ON u.id = br.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll();

// Export to CSV
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="library_report_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Book Title', 'Author', 'User', 'Borrow Date', 'Due Date', 'Return Date', 'Status', 'Fine']);
    
    foreach ($monthlyRecords as $record) {
        fputcsv($output, [
            $record['title'],
            $record['author'],
            $record['full_name'],
            $record['borrow_date'],
            $record['due_date'],
            $record['return_date'] ?: 'Not Returned',
            $record['status'],
            $record['fine_amount']
        ]);
    }
    
    fclose($output);
    exit();
}

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4"><i class="bi bi-file-earmark-text"></i> Reports</h2>
        
        <!-- Month Selector -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="month" class="form-label">Select Month</label>
                        <input type="month" class="form-control" id="month" name="month" 
                               value="<?php echo $month; ?>" max="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="?month=<?php echo $month; ?>&export=csv" class="btn btn-success">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Borrows</h5>
                        <h2><?php echo $stats['total_borrows']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>Returned</h5>
                        <h2><?php echo $stats['returned']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5>Active</h5>
                        <h2><?php echo $stats['active']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5>Overdue</h5>
                        <h2><?php echo $stats['overdue']; ?></h2>
                        <small>Fines: $<?php echo number_format($stats['total_fines'], 2); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Monthly Records -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Borrow Records (<?php echo date('F Y', strtotime($month . '-01')); ?>)</h5>
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
                                        <th>Fine</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyRecords as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['title']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($record['author']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                            <td><?php echo formatDate($record['borrow_date']); ?></td>
                                            <td><?php echo formatDate($record['due_date']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $record['status'] === 'borrowed' ? 'primary' : 'success'; ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($record['fine_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Books and Users -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Top Borrowed Books</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <?php foreach ($topBooks as $book): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($book['author']); ?></small>
                                    <span class="badge bg-info"><?php echo $book['borrow_count']; ?> times</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Top Users</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <?php foreach ($topUsers as $user): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                    <span class="badge bg-success"><?php echo $user['borrow_count']; ?> books</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

