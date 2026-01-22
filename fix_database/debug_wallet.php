<?php
require_once "db_connect/db_connect.php";

$order_id = 69;

echo "<h1>🔍 Debug Wallet for Order #$order_id</h1>";

// 1. Check Order
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("❌ Order #$order_id NOT FOUND.");
}

echo "<h3>1. Order Details</h3>";
echo "Status: <b>{$order['status']}</b><br>";
echo "User ID: <b>{$order['user_id']}</b><br>";
echo "Total Amount: <b>{$order['total_amount']}</b><br>";

// 2. Check Transactions
echo "<h3>2. Wallet Transactions (User ID: {$order['user_id']})</h3>";
$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = :uid");
$stmt->execute([':uid' => $order['user_id']]);
$trans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($trans) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Type</th><th>Amount</th><th>Desc</th><th>Time</th></tr>";
    foreach ($trans as $t) {
        echo "<tr>
            <td>{$t['id']}</td>
            <td>{$t['type']}</td>
            <td>{$t['amount']}</td>
            <td>{$t['description']}</td>
            <td>{$t['created_at']}</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "❌ No transactions found for this user.";
}

// 3. Check Order Specific Transaction
$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE order_id = :oid");
$stmt->execute([':oid' => $order_id]);
$order_trans = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>3. Transaction for this Order</h3>";
if ($order_trans) {
    echo "✅ Transaction EXISTS.<br>";
    print_r($order_trans);
} else {
    echo "❌ Transaction MISSING for Order #$order_id.<br>";
}
