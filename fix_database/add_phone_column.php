<?php
require_once "../db_connect/db_connect.php";

try {
    $conn->exec("ALTER TABLE users ADD COLUMN phone varchar(20) AFTER email");
    echo "Added phone column successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column 'phone' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
