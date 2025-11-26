<?php
/**
 * Database Backup Utility
 */
require_once __DIR__ . '/../config/config.php';
requireOwner();

// Simple database backup (for small databases)
// For production, use mysqldump command

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="library_backup_' . date('Y-m-d_H-i-s') . '.sql"');

// Get all tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "-- Table: $table\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    // Get create table statement
    $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
    echo $createTable['Create Table'] . ";\n\n";
    
    // Get table data
    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = $pdo->quote($value);
                }
            }
            echo "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
        }
        echo "\n";
    }
}

