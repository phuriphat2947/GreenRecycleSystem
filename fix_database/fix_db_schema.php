<?php
require_once "db_connect/db_connect.php";

echo "<h1>🔍 Database Schema Diagnosis</h1>";

// 1. Check Current ENUM
$stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
$type = $col['Type'];

echo "Current Column Type: <code>$type</code><br><br>";

// 2. Fix if needed
if (strpos($type, 'waiting_confirm') === false) {
    echo "❌ Missing 'waiting_confirm'! Fixing...<br>";
    try {
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'accepted', 'waiting_confirm', 'user_confirmed', 'completed', 'cancelled', 'disputed') DEFAULT 'pending'";
        $conn->exec($sql);
        echo "✅ ALTER TABLE executed successfully.<br>";

        // Check again
        $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "New Column Type: <code>" . $col['Type'] . "</code><br>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
} else {
    echo "✅ Schema is already correct (contains 'waiting_confirm').<br>";
}

// 3. Fix Missing Columns
echo "<br><h3>Checking Columns...</h3>";
$columns_to_check = [
    'user_confirm_timestamp' => "datetime DEFAULT NULL",
    'is_verified_by_user' => "tinyint(1) DEFAULT 0",
    'weighing_proof_image' => "varchar(255) DEFAULT NULL COMMENT 'Driver proof photo'"
];

foreach ($columns_to_check as $col_name => $col_def) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE '$col_name'");
        if ($stmt->rowCount() == 0) {
            echo "❌ Missing '$col_name'! Adding...<br>";
            $conn->exec("ALTER TABLE orders ADD COLUMN $col_name $col_def");
            echo "✅ Added '$col_name' successfully.<br>";
        } else {
            echo "✅ Column '$col_name' exists.<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Error adding '$col_name': " . $e->getMessage() . "<br>";
    }
}
echo "<br><b>Done.</b>";
