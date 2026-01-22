<?php
require_once "db_connect/db_connect.php";

try {
    // Add status column if not exists
    $sql = "ALTER TABLE chat_messages ADD COLUMN status ENUM('active', 'archived') DEFAULT 'active' AFTER is_read";
    $conn->exec($sql);
    echo "Column 'status' added successfully.";
} catch (PDOException $e) {
    echo "Error (might already exist): " . $e->getMessage();
}
