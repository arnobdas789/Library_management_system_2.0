<?php
/**
 * Add/Edit Book Form
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$book_id = intval($_GET['id'] ?? 0);
$book = null;
$success = '';
$error = '';

if ($book_id) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    if (!$book) {
        header('Location: ' . BASE_URL . 'admin/owner_dashboard.php');
        exit();
    }
}

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $author = sanitize($_POST['author'] ?? '');
    $isbn = sanitize($_POST['isbn'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $total_copies = intval($_POST['total_copies'] ?? 1);
    $published_year = !empty($_POST['published_year']) ? intval($_POST['published_year']) : null;
    $publisher = sanitize($_POST['publisher'] ?? '');
    $language = sanitize($_POST['language'] ?? 'English');
    
    if (empty($title) || empty($author)) {
        $error = 'Title and author are required.';
    } else {
        try {
            // Handle image upload
            $image_path = $book['image_path'] ?? 'default_book.jpg';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOAD_PATH;
                $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                    $targetPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        // Delete old image if exists
                        if ($book && $book['image_path'] && $book['image_path'] !== 'default_book.jpg') {
                            $oldPath = UPLOAD_PATH . $book['image_path'];
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $image_path = $newFileName;
                    }
                }
            }
            
            if ($book_id) {
                // Update existing book
                $available_copies = $book['available_copies'];
                $diff = $total_copies - $book['total_copies'];
                $available_copies = max(0, $available_copies + $diff);
                
                $stmt = $pdo->prepare("
                    UPDATE books SET 
                        title = ?, author = ?, isbn = ?, category_id = ?, 
                        description = ?, image_path = ?, total_copies = ?, 
                        available_copies = ?, published_year = ?, publisher = ?, language = ?,
                        status = CASE WHEN available_copies > 0 THEN 'available' ELSE status END
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $author, $isbn ?: null, $category_id ?: null,
                    $description, $image_path, $total_copies, $available_copies,
                    $published_year, $publisher, $language, $book_id
                ]);
                $success = 'Book updated successfully!';
            } else {
                // Insert new book
                $stmt = $pdo->prepare("
                    INSERT INTO books (title, author, isbn, category_id, description, image_path, 
                                     total_copies, available_copies, published_year, publisher, language)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $author, $isbn ?: null, $category_id ?: null,
                    $description, $image_path, $total_copies, $total_copies,
                    $published_year, $publisher, $language
                ]);
                $success = 'Book added successfully!';
                $book_id = $pdo->lastInsertId();
            }
            
            // Refresh book data
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Error saving book: ' . $e->getMessage();
        }
    }
}

$pageTitle = $book ? 'Edit Book' : 'Add New Book';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-<?php echo $book ? 'pencil' : 'plus-circle'; ?>"></i> 
                    <?php echo $book ? 'Edit Book' : 'Add New Book'; ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="author" class="form-label">Author <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="author" name="author" required
                                       value="<?php echo htmlspecialchars($book['author'] ?? ''); ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn"
                                           value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="0">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"
                                                    <?php echo ($book['category_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="total_copies" class="form-label">Total Copies <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                           min="1" required value="<?php echo $book['total_copies'] ?? 1; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="published_year" class="form-label">Published Year</label>
                                    <input type="number" class="form-control" id="published_year" name="published_year"
                                           min="1000" max="<?php echo date('Y'); ?>"
                                           value="<?php echo $book['published_year'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="language" class="form-label">Language</label>
                                    <input type="text" class="form-control" id="language" name="language"
                                           value="<?php echo htmlspecialchars($book['language'] ?? 'English'); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="publisher" class="form-label">Publisher</label>
                                <input type="text" class="form-control" id="publisher" name="publisher"
                                       value="<?php echo htmlspecialchars($book['publisher'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="image" class="form-label">Book Cover Image</label>
                                <?php if ($book && $book['image_path']): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo BASE_URL . 'uploads/' . $book['image_path']; ?>" 
                                             alt="Current cover" 
                                             class="img-thumbnail" 
                                             style="max-width: 100%; height: 200px; object-fit: cover;"
                                             onerror="this.src='<?php echo BASE_URL; ?>assets/images/default_book.jpg'">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">Accepted formats: JPG, PNG, GIF (Max 5MB)</small>
                            </div>
                            <?php if ($book): ?>
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Available Copies:</strong> <?php echo $book['available_copies']; ?><br>
                                        <strong>Status:</strong> <?php echo ucfirst($book['status']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?php echo $book ? 'Update' : 'Add'; ?> Book
                        </button>
                        <a href="<?php echo BASE_URL; ?>admin/owner_dashboard.php" class="btn btn-secondary">
                            Cancel
                        </a>
                        <?php if ($book): ?>
                            <a href="<?php echo BASE_URL; ?>admin/delete_book.php?id=<?php echo $book_id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to delete this book?');">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- List of Books -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Books</h5>
            </div>
            <div class="card-body">
                <?php
                $allBooks = $pdo->query("
                    SELECT b.*, c.name as category_name 
                    FROM books b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    ORDER BY b.created_at DESC 
                    LIMIT 20
                ")->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allBooks as $b): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($b['title']); ?></td>
                                    <td><?php echo htmlspecialchars($b['author']); ?></td>
                                    <td><?php echo htmlspecialchars($b['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo $b['available_copies']; ?>/<?php echo $b['total_copies']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $b['status'] === 'available' ? 'success' : ($b['status'] === 'borrowed' ? 'primary' : 'warning'); ?>">
                                            <?php echo ucfirst($b['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
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

