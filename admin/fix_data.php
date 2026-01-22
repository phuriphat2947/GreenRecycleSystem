<?php
require_once "../db_connect/db_connect.php";
try {
    // Check if Order 10 exists
    $stmt = $conn->query("SELECT id FROM orders WHERE id = 10");
    if ($stmt->fetch()) {
        // Insert dummy item
        $sql = "INSERT INTO order_items (order_id, waste_type_id, weight, actual_weight, price_at_time, subtotal)
                VALUES (10, 1, 10.0, 10.0, 5.00, 50.00)";
        $conn->exec($sql);
        echo "Inserted dummy item for Order #10 successfully.";
    } else {
        echo "Order #10 not found. Checking for any completed order...";
        $stmt = $conn->query("SELECT id FROM orders WHERE status='completed' LIMIT 1");
        $oid = $stmt->fetchColumn();
        if ($oid) {
            $sql = "INSERT INTO order_items (order_id, waste_type_id, weight, actual_weight, price_at_time, subtotal)
                VALUES ($oid, 1, 10.0, 10.0, 5.00, 50.00)";
            $conn->exec($sql);
            echo "Inserted dummy item for Order #$oid successfully.";
        } else {
            echo "No completed orders found to fix.";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
