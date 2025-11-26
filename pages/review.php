<?php
/**
 * Submit Review Page
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = intval($_POST['book_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = sanitize($_POST['comment'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if (!$book_id || $rating < 1 || $rating > 5 || empty($comment)) {
        echo "<script>alert('Please fill in all fields correctly.'); window.history.back();</script>";
        exit();
    }
    
    // Check if user has borrowed this book
    $borrowCheck = $pdo->prepare("SELECT id FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'returned'");
    $borrowCheck->execute([$user_id, $book_id]);
    if (!$borrowCheck->fetch()) {
        echo "<script>alert('You can only review books you have borrowed and returned.'); window.history.back();</script>";
        exit();
    }
    
    // Check if user already reviewed
    $reviewCheck = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND book_id = ?");
    $reviewCheck->execute([$user_id, $book_id]);
    if ($reviewCheck->fetch()) {
        echo "<script>alert('You have already reviewed this book.'); window.history.back();</script>";
        exit();
    }
    
    // Insert review
    try {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, book_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $book_id, $rating, $comment]);
        
        echo "<script>alert('Review submitted successfully!'); window.location.href='" . BASE_URL . "pages/book_detail.php?id=$book_id';</script>";
        exit();
    } catch (PDOException $e) {
        echo "<script>alert('Error submitting review. Please try again.'); window.history.back();</script>";
        exit();
    }
} else {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

