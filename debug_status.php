<?php
require_once "db_connect/db_connect.php";

// 1. Fix NULL or Empty Status
$sql = "UPDATE orders SET status = 'waiting_confirm' WHERE status IS NULL OR status = ''";
$stmt = $conn->prepare($sql);
$stmt->execute();
$count = $stmt->rowCount();

if ($count > 0) {
    echo "<h1>✅ REPAIRED $count BROKEN ORDERS</h1>";
    echo "<p>Orders with empty status have been set to 'waiting_confirm'.</p>";
} else {
    echo "<h1>No broken orders found (Empty Status).</h1>";
}

// 2. Check All Waiting Confirms
$stmt = $conn->query("SELECT id, user_id, status, updated_at FROM orders WHERE status = 'waiting_confirm'");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Orders Waiting for User Confirmation (" . count($orders) . "):</h2>";
echo "<pre>";
print_r($orders);
echo "</pre>";

echo "<hr>";
echo "<a href='admin/orders.php' style='font-size:20px; padding:10px; background:blue; color:white;'>Go to Admin Orders</a>";
