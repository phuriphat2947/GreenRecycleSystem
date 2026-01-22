<?php
require_once 'db_connect/db_connect.php';

try {
    // 1. Add Column if not exists
    $cols = $conn->query("SHOW COLUMNS FROM orders LIKE 'weighing_proof_image'")->fetchAll();
    if (count($cols) == 0) {
        $conn->exec("ALTER TABLE orders ADD COLUMN weighing_proof_image VARCHAR(255) NULL AFTER request_image");
        echo "Column 'weighing_proof_image' added successfully.\n";
    } else {
        echo "Column 'weighing_proof_image' already exists.\n";
    }

    // 2. Force Update Order #13 to 'waiting_confirm' for testing
    // Check if Order #13 exists
    $stmt = $conn->query("SELECT id FROM orders WHERE id = 13");
    if ($stmt->fetch()) {
        $conn->exec("UPDATE orders SET status = 'waiting_confirm' WHERE id = 13");
        echo "Order #13 status updated to 'waiting_confirm'.\n";
    } else {
        echo "Order #13 not found.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
