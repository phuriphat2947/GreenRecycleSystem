<?php
require_once "../db_connect/db_connect.php";

echo "Check 1: Database Connection OK\n";

try {
    // Check Orders
    $stmt = $conn->query("SELECT id, user_id, status, total_weight, created_at, updated_at FROM orders ORDER BY id DESC LIMIT 5");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Recent Orders:\n";
    print_r($orders);

    if (count($orders) > 0) {
        $oid = $orders[0]['id'];
        // Check Items
        $stmt2 = $conn->query("SELECT * FROM order_items WHERE order_id = $oid");
        echo "Items for Order #$oid:\n";
        print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
