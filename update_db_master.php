<?php
require_once "./db_connect/db_connect.php";

echo "<h2>Starting Master Plan Database Migration...</h2>";

try {
    // 1. Users: Bank Details
    echo "Checking 'bank_name' in 'users'...<br>";
    try {
        $conn->query("SELECT bank_name FROM users LIMIT 1");
        echo "- 'bank_name' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE users ADD COLUMN bank_name VARCHAR(100) NULL COMMENT 'Bank Name for Withdrawal'");
        $conn->exec("ALTER TABLE users ADD COLUMN bank_account VARCHAR(50) NULL COMMENT 'Account Number'");
        $conn->exec("ALTER TABLE users ADD COLUMN bank_account_name VARCHAR(100) NULL COMMENT 'Account Holder Name'");
        echo "- Added Bank Details columns to 'users'.<br>";
    }

    // 2. Waste Types: Dual Pricing (Keeping price_per_kg as Base/Pickup Price)
    echo "Checking 'price_walkin' in 'waste_types'...<br>";
    try {
        $conn->query("SELECT price_walkin FROM waste_types LIMIT 1");
        echo "- 'price_walkin' exists.<br>";
    } catch (PDOException $e) {
        // Add price_walkin
        $conn->exec("ALTER TABLE waste_types ADD COLUMN price_walkin DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER price_per_kg");
        // Initialize walkin price to be 20% higher than base price
        $conn->exec("UPDATE waste_types SET price_walkin = price_per_kg * 1.20");
        echo "- Added 'price_walkin' to 'waste_types' and set to +20%.<br>";
    }

    // 3. Orders: New Columns for Workflow
    echo "Checking 'order_type' in 'orders'...<br>";
    try {
        $conn->query("SELECT order_type FROM orders LIMIT 1");
        echo "- 'order_type' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE orders ADD COLUMN order_type ENUM('pickup', 'walkin') DEFAULT 'pickup' AFTER id");
        $conn->exec("ALTER TABLE orders ADD COLUMN payment_method ENUM('cash', 'transfer') NULL AFTER total_amount");
        $conn->exec("ALTER TABLE orders ADD COLUMN payment_proof VARCHAR(255) NULL AFTER payment_method");
        $conn->exec("ALTER TABLE orders ADD COLUMN is_verified_by_user TINYINT(1) DEFAULT 0 COMMENT 'User confirmed receipt' AFTER payment_proof");
        $conn->exec("ALTER TABLE orders ADD COLUMN driver_cash_out DECIMAL(10,2) NULL COMMENT 'Cash paid by driver' AFTER is_verified_by_user");
        echo "- Updated 'orders' with new workflow columns.<br>";
    }

    // 4. Order Items: Actual Weight
    echo "Checking 'actual_weight' in 'order_items'...<br>";
    try {
        $conn->query("SELECT actual_weight FROM order_items LIMIT 1");
        echo "- 'actual_weight' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE order_items ADD COLUMN actual_weight DECIMAL(10, 2) NULL AFTER weight");
        echo "- Added 'actual_weight' to 'order_items'.<br>";
    }

    // 5. Withdrawals Table
    echo "Creating 'withdrawals' table...<br>";
    $sql_withdraw = "CREATE TABLE IF NOT EXISTS withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        bank_account VARCHAR(50) NOT NULL,
        bank_account_name VARCHAR(100) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        slip_image VARCHAR(255) NULL,
        reject_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql_withdraw);
    echo "- 'withdrawals' table created.<br>";

    // Auto-update users with mock bank details for testing if empty? No, let them fill it.

    // 6. Strategic Pricing (Pickup Price)
    echo "Checking 'pickup_price_per_kg' in 'waste_types'...<br>";
    try {
        $conn->query("SELECT pickup_price_per_kg FROM waste_types LIMIT 1");
        echo "- 'pickup_price_per_kg' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE waste_types ADD COLUMN pickup_price_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price_per_kg");
        // Update default to 80%
        $conn->exec("UPDATE waste_types SET pickup_price_per_kg = price_per_kg * 0.8");
        echo "- Added 'pickup_price_per_kg' (Default 80%).<br>";
    }

    // 7. Request Image (Orders)
    echo "Checking 'request_image' in 'orders'...<br>";
    try {
        $conn->query("SELECT request_image FROM orders LIMIT 1");
        echo "- 'request_image' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE orders ADD COLUMN request_image VARCHAR(255) NULL AFTER pickup_address");
        echo "- Added 'request_image' to orders.<br>";
    }

    // 8. Eco-Warrior System (Gamification)
    echo "Checking 'total_recycled_weight' in 'users'...<br>";
    try {
        $conn->query("SELECT total_recycled_weight FROM users LIMIT 1");
        echo "- 'total_recycled_weight' exists.<br>";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE users ADD COLUMN total_recycled_weight DECIMAL(12, 2) DEFAULT 0.00 AFTER role");
        $conn->exec("ALTER TABLE users ADD COLUMN membership_level ENUM('seedling', 'guardian', 'titan') DEFAULT 'seedling' AFTER total_recycled_weight");
        echo "- Added 'total_recycled_weight' and 'membership_level' to users.<br>";
    }

    echo "<h3>Master Plan Database Migration Completed!</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Error: " . $e->getMessage() . "</h3>";
}
