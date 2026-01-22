<?php
require_once "../db_connect/db_connect.php";
try {
    $conn->exec("UPDATE orders SET updated_at = NOW() WHERE id = 79");
    echo "Updated Order #79 to NOW.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
