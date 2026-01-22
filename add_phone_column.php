<?php
require_once "db_connect/db_connect.php";

try {
    $conn->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
    echo "Column 'phone' added successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
