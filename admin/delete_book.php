<?php
/**
 * Delete Book
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

$book_id = intval($_GET['id'] ?? 0);

if ($book_id) {
    try {
        // Get book info for image deletion
        $stmt = $pdo->prepare("SELECT image_path FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        // Delete book
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        
        // Delete image if exists
        if ($book && $book['image_path'] && $book['image_path'] !== 'default_book.jpg') {
            $imagePath = UPLOAD_PATH . $book['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        header('Location: ' . BASE_URL . 'admin/book_form.php?success=Book deleted successfully');
    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . 'admin/book_form.php?error=Error deleting book');
    }
} else {
    header('Location: ' . BASE_URL . 'admin/book_form.php');
}
exit();

