<?php
/**
 * Manage Users Page
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($user_id && $action) {
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User approved successfully!';
                    break;
                case 'block':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User blocked successfully!';
                    break;
                case 'unblock':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User unblocked successfully!';
                    break;
                case 'reset_password':
                    $new_password = password_hash('password123', PASSWORD_DEFAULT); // Default password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_password, $user_id]);
                    $success = 'Password reset successfully! Default password: password123';
                    break;
                case 'change_role':
                    $new_role = sanitize($_POST['new_role'] ?? 'user');
                    if (in_array($new_role, ['user', 'admin', 'owner'])) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$new_role, $user_id]);
                        $success = 'User role updated successfully!';
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Error performing action: ' . $e->getMessage();
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = sanitize($_GET['search'] ?? '');

// Build query
$whereConditions = ["role != 'owner' OR id = " . $_SESSION['user_id']];
$params = [];

if ($filter === 'pending') {
    $whereConditions[] = "status = 'pending'";
} elseif ($filter === 'active') {
    $whereConditions[] = "status = 'active'";
} elseif ($filter === 'blocked') {
    $whereConditions[] = "status = 'blocked'";
}

if (!empty($search)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

// Get users
$stmt = $pdo->prepare("SELECT * FROM users WHERE $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4"><i class="bi bi-people"></i> Manage Users</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Filters and Search -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" placeholder="Search by username, email, or name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="filter">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="blocked" <?php echo $filter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Member Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="change_role">
                                            <select name="new_role" class="form-select form-select-sm d-inline-block" style="width: auto;" 
                                                    onchange="this.form.submit()">
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <?php if (isOwner()): ?>
                                                    <option value="owner" <?php echo $user['role'] === 'owner' ? 'selected' : ''; ?>>Owner</option>
                                                <?php endif; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['status'] === 'active' ? 'success' : 
                                                ($user['status'] === 'blocked' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($user['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-check"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'active'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="block">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to block this user?');">
                                                        <i class="bi bi-x-circle"></i> Block
                                                    </button>
                                                </form>
                                            <?php elseif ($user['status'] === 'blocked'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="unblock">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-unlock"></i> Unblock
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <button type="submit" class="btn btn-warning btn-sm"
                                                        onclick="return confirm('Reset password to default (password123)?');">
                                                    <i class="bi bi-key"></i> Reset Password
                                                </button>
                                            </form>
                                        </div>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

