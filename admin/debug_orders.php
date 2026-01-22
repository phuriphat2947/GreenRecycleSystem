<?php
require_once "../db_connect/db_connect.php";
try {
    $stmt = $conn->query("SELECT status, COUNT(*) as count, MIN(updated_at) as min_date, MAX(updated_at) as max_date FROM orders GROUP BY status");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Order Statuses:\n";
    foreach ($rows as $row) {
        echo "Status: " . $row['status'] . " | Count: " . $row['count'] . " | Range: " . $row['min_date'] . " - " . $row['max_date'] . "\n";
    }

    echo "\nChecking Order Items for 'completed' orders:\n";
    $stmt2 = $conn->query("SELECT o.id, COUNT(oi.id) as item_count, SUM(oi.actual_weight) as total_actual_weight 
                           FROM orders o 
                           LEFT JOIN order_items oi ON o.id = oi.order_id 
                           WHERE o.status = 'completed' 
                           GROUP BY o.id");
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $itm) {
        echo "Order #" . $itm['id'] . " | Items: " . $itm['item_count'] . " | Total Actual Wgt: " . $itm['total_actual_weight'] . "\n";
    }
    echo "\nChecking Total Order Items:\n";
    $stmt3 = $conn->query("SELECT COUNT(*) as total FROM order_items");
    $total = $stmt3->fetchColumn();
    echo "Total rows in order_items: " . $total . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
