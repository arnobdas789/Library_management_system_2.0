<?php
/**
 * Approve Borrow/Return Requests
 */
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$success = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $action = $_POST['action'] ?? ''; // approve or reject
    $admin_id = $_SESSION['user_id'];
    
    if ($record_id && $action) {
        try {
            $pdo->beginTransaction();
            
            // Get the borrow record
            $stmt = $pdo->prepare("
                SELECT br.*, b.title, b.available_copies, u.username, u.email
                FROM borrow_records br
                JOIN books b ON br.book_id = b.id
                JOIN users u ON br.user_id = u.id
                WHERE br.id = ? AND br.approval_status = 'pending'
            ");
            $stmt->execute([$record_id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                throw new Exception('Record not found or already processed');
            }
            
            if ($action === 'approve') {
                if ($record['request_type'] === 'borrow' || empty($record['request_type'])) {
                    // Approve borrow request
                    // Check if book is still available
                    if ($record['available_copies'] <= 0) {
                        throw new Exception('Book is no longer available');
                    }
                    
                    // Update borrow record
                    $stmt = $pdo->prepare("
                        UPDATE borrow_records 
                        SET approval_status = 'approved', 
                            approved_by = ?, 
                            approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_id, $record_id]);
                    
                    // Update book availability
                    $stmt = $pdo->prepare("
                        UPDATE books 
                        SET available_copies = available_copies - 1,
                            status = CASE WHEN available_copies - 1 = 0 THEN 'borrowed' ELSE 'available' END
                        WHERE id = ?
                    ");
                    $stmt->execute([$record['book_id']]);
                    
                    // Create notification for user
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id) 
                        VALUES (?, 'borrow_approved', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $record['user_id'],
                        'Borrow Request Approved',
                        "Your borrow request for '" . $record['title'] . "' has been approved. Due date: " . formatDate($record['due_date']),
                        $record_id
                    ]);
                    
                    $success = 'Borrow request approved successfully!';
                    
                } elseif ($record['request_type'] === 'return') {
                    // Approve return request
                    // Calculate fine if overdue
                    $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($record['due_date'])) / 86400);
                    $fineAmount = $daysOverdue * floatval(getSetting('fine_per_day', 5.00));
                    
                    // Update borrow record
                    $stmt = $pdo->prepare("
                        UPDATE borrow_records 
                        SET status = 'returned',
                            return_date = CURDATE(),
                            approval_status = 'approved',
                            fine_amount = ?,
                            approved_by = ?,
                            approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$fineAmount, $admin_id, $record_id]);
                    
                    // Update book availability
                    $stmt = $pdo->prepare("
                        UPDATE books 
                        SET available_copies = available_copies + 1,
                            status = CASE WHEN available_copies + 1 > 0 THEN 'available' ELSE status END
                        WHERE id = ?
                    ");
                    $stmt->execute([$record['book_id']]);
                    
                    // Create notification for user
                    $fineMsg = $fineAmount > 0 ? " Fine: $" . number_format($fineAmount, 2) : "";
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id) 
                        VALUES (?, 'return_approved', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $record['user_id'],
                        'Return Request Approved',
                        "Your return request for '" . $record['title'] . "' has been approved." . $fineMsg,
                        $record_id
                    ]);
                    
                    $success = 'Return request approved successfully!';
                }
                
            } elseif ($action === 'reject') {
                // Reject request
                $stmt = $pdo->prepare("
                    UPDATE borrow_records 
                    SET approval_status = 'rejected',
                        approved_by = ?,
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$admin_id, $record_id]);
                
                // Create notification for user
                $notifType = ($record['request_type'] === 'return') ? 'return_rejected' : 'borrow_rejected';
                $notifTitle = ($record['request_type'] === 'return') ? 'Return Request Rejected' : 'Borrow Request Rejected';
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, related_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $record['user_id'],
                    $notifType,
                    $notifTitle,
                    "Your " . ($record['request_type'] === 'return' ? 'return' : 'borrow') . " request for '" . $record['title'] . "' has been rejected. Please contact the library for more information.",
                    $record_id
                ]);
                
                $success = 'Request rejected successfully!';
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error processing request: ' . $e->getMessage();
        }
    }
}

// Get pending requests
$filter = $_GET['filter'] ?? 'all'; // all, borrow, return

$whereClause = "br.approval_status = 'pending'";
if ($filter === 'borrow') {
    $whereClause .= " AND (br.request_type = 'borrow' OR br.request_type IS NULL)";
} elseif ($filter === 'return') {
    $whereClause .= " AND br.request_type = 'return'";
}

$stmt = $pdo->prepare("
    SELECT br.*, b.title, b.author, b.image_path, u.username, u.full_name, u.email,
           DATEDIFF(CURDATE(), br.due_date) as days_overdue
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    WHERE $whereClause
    ORDER BY br.created_at ASC
");
$stmt->execute();
$pendingRequests = $stmt->fetchAll();

$pageTitle = 'Approve Requests';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4"><i class="bi bi-check-circle"></i> Approve Requests</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                        All Requests
                    </a>
                    <a href="?filter=borrow" class="btn btn-<?php echo $filter === 'borrow' ? 'primary' : 'outline-primary'; ?>">
                        Borrow Requests
                    </a>
                    <a href="?filter=return" class="btn btn-<?php echo $filter === 'return' ? 'primary' : 'outline-primary'; ?>">
                        Return Requests
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Pending Requests -->
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($pendingRequests)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No pending requests.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request Type</th>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo ($req['request_type'] === 'return' || strpos($req['notes'] ?? '', 'RETURN_REQUESTED') !== false) ? 'success' : 'primary'; ?>">
                                                <?php echo ($req['request_type'] === 'return' || strpos($req['notes'] ?? '', 'RETURN_REQUESTED') !== false) ? 'Return' : 'Borrow'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo !empty($req['image_path']) ? BASE_URL . 'uploads/' . $req['image_path'] : BASE_URL . 'assets/images/default_book.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($req['title']); ?>"
                                                     style="width: 40px; height: 60px; object-fit: cover; margin-right: 10px;"
                                                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($req['title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($req['author']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($req['full_name']); ?></strong><br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($req['username']); ?></small>
                                        </td>
                                        <td><?php echo formatDate($req['borrow_date']); ?></td>
                                        <td>
                                            <?php echo formatDate($req['due_date']); ?>
                                            <?php if ($req['days_overdue'] > 0): ?>
                                                <br><small class="text-danger"><?php echo $req['days_overdue']; ?> days overdue</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($req['days_overdue'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $req['days_overdue']; ?> days</span>
                                                <br><small class="text-danger">Fine: $<?php echo number_format($req['days_overdue'] * floatval(getSetting('fine_per_day', 5.00)), 2); ?></small>
                                            <?php else: ?>
                                                <span class="text-success">On time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="record_id" value="<?php echo $req['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Are you sure you want to approve this request?');">
                                                    <i class="bi bi-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="record_id" value="<?php echo $req['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Are you sure you want to reject this request?');">
                                                    <i class="bi bi-x"></i> Reject
                                                </button>
                                            </form>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

