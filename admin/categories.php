<?php
/**
 * Manage Categories
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$success = '';
$error = '';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success = 'Category added successfully!';
            } catch (PDOException $e) {
                $error = 'Category name already exists.';
            }
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = intval($_POST['category_id']);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $success = 'Category updated successfully!';
            } catch (PDOException $e) {
                $error = 'Category name already exists.';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Category deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Cannot delete category. It may be in use by books.';
    }
}

// Get edit category
$editCategory = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $editCategory = $stmt->fetch();
}

// Get all categories
$categories = $pdo->query("SELECT c.*, COUNT(b.id) as book_count FROM categories c LEFT JOIN books b ON c.id = b.category_id GROUP BY c.id ORDER BY c.name")->fetchAll();

$pageTitle = 'Manage Categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-tags"></i> Manage Categories</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Add/Edit Form -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="<?php echo $editCategory ? 'edit_category' : 'add_category'; ?>" value="1">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo htmlspecialchars($editCategory['description'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $editCategory ? 'check' : 'plus'; ?>"></i> 
                        <?php echo $editCategory ? 'Update' : 'Add'; ?> Category
                    </button>
                    <?php if ($editCategory): ?>
                        <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
                
                <hr>
                
                <!-- Categories List -->
                <h5>All Categories</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Books</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></td>
                                    <td><span class="badge bg-info"><?php echo $cat['book_count']; ?></span></td>
                                    <td>
                                        <a href="?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="?delete=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this category?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
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

