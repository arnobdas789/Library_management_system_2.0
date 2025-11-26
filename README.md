Smart Library Management System – Arnob Library Portal

A full-stack PHP web application for managing library operations including borrowing, reservations, user administration, and reporting.

Features

User

Register, login, and manage profile

Browse and search books by category

Borrow and reserve books

View borrowing history

Post reviews and ratings (after borrowing)

Admin/Owner

Dashboard with charts and statistics

Add, edit, and remove books with image upload

Manage categories and users

Monthly reports with CSV export

System settings for borrowing rules

Database backup download

Technology Stack

PHP 7.4+, MySQL 5.7+

HTML5, CSS3, JavaScript (ES6)

Bootstrap 5.3, Chart.js 4.4

Apache (XAMPP recommended)

Project Structure
Library_management_system/
├── admin/ # Admin pages and features
├── auth/ # Authentication
├── pages/ # User features
├── includes/ # Layout components
├── config/ # Database config
├── database/ # SQL schema
├── assets/ # CSS, JS, images
├── uploads/ # Book images
└── index.php

Setup Instructions

Install XAMPP and start Apache + MySQL

Create a database library_management in phpMyAdmin

Import database/schema.sql

Configure database credentials in config/config.php

Place the project folder in htdocs

Visit: http://localhost/Library_management_system/

Default Admin Login

Username: admin

Password: admin123 (change after login)

Security

Password hashing using password_hash()

PDO prepared statements for SQL injection protection

HTML escaping to prevent XSS

Role-based access control

Troubleshooting

Ensure database credentials are correct

Check write permission on uploads/

Enable mod_rewrite if routing errors occur

License

Created for educational and development purposes. Free to modify and distribute.
