-- Smart Library Management System Database Schema
-- Created for Arnob Library Portal

-- Create database
CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin', 'owner') DEFAULT 'user',
    status ENUM('active', 'blocked', 'pending') DEFAULT 'pending',
    membership_status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Books table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(50) UNIQUE,
    category_id INT,
    description TEXT,
    image_path VARCHAR(255) DEFAULT 'default_book.jpg',
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    status ENUM('available', 'borrowed', 'reserved', 'maintenance') DEFAULT 'available',
    published_year YEAR,
    publisher VARCHAR(255),
    language VARCHAR(50) DEFAULT 'English',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Borrow records table
CREATE TABLE IF NOT EXISTS borrow_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_book_review (user_id, book_id)
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('max_borrow_limit', '5', 'Maximum number of books a user can borrow at once'),
('borrow_duration_days', '14', 'Number of days a book can be borrowed'),
('fine_per_day', '5.00', 'Fine amount per day for overdue books'),
('reservation_duration_days', '3', 'Number of days a reservation is valid'),
('library_name', 'Arnob Library Portal', 'Name of the library'),
('library_email', 'admin@arnoblibrary.com', 'Library contact email'),
('library_phone', '+1234567890', 'Library contact phone');

-- Insert default admin/owner user (password: admin123 - should be changed in production)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@arnoblibrary.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'owner', 'active');

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Fiction', 'Fictional novels and stories'),
('Non-Fiction', 'Non-fictional books and biographies'),
('Science', 'Science and technology books'),
('History', 'Historical books and documents'),
('Literature', 'Classic and modern literature'),
('Education', 'Educational and reference books');

-- Insert sample books
INSERT INTO books (title, author, isbn, category_id, description, total_copies, available_copies, published_year, publisher) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', 1, 'A classic American novel about the Jazz Age', 3, 3, 1925, 'Scribner'),
('To Kill a Mockingbird', 'Harper Lee', '978-0-06-112008-4', 1, 'A gripping tale of racial injustice', 2, 2, 1960, 'J.B. Lippincott & Co.'),
('1984', 'George Orwell', '978-0-452-28423-4', 1, 'A dystopian social science fiction novel', 4, 4, 1949, 'Secker & Warburg'),
('A Brief History of Time', 'Stephen Hawking', '978-0-553-10953-5', 3, 'A popular science book about cosmology', 2, 2, 1988, 'Bantam Books'),
('Sapiens', 'Yuval Noah Harari', '978-0-06-231609-7', 2, 'A brief history of humankind', 3, 3, 2011, 'Harper');

