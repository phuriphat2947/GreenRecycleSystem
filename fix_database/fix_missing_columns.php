<?php
require_once "db_connect/db_connect.php";

echo "<h2>Database Repair Tool - Checking Schema...</h2>";

function addColumnIfNotExists($conn, $table, $column, $definition)
{
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $sql = "ALTER TABLE $table ADD $column $definition";
            $conn->exec($sql);
            echo "<div style='color:green'>[SUCCESS] Added column '$column' to table '$table'.</div>";
        } else {
            echo "<div style='color:gray'>[INFO] Column '$column' already exists in table '$table'.</div>";
        }
    } catch (PDOException $e) {
        echo "<div style='color:red'>[ERROR] Failed to add '$column': " . $e->getMessage() . "</div>";
    }
}

// 1. Check Users Table (KYC & Bank)
echo "<h3>Checking 'users' table...</h3>";
addColumnIfNotExists($conn, 'users', 'id_card_number', 'VARCHAR(20) NULL COMMENT "National ID Card Number 13 digits"');
addColumnIfNotExists($conn, 'users', 'id_card_image', 'VARCHAR(255) NULL COMMENT "Filename of ID Card photo"');
addColumnIfNotExists($conn, 'users', 'kyc_status', "ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified' COMMENT 'Identity verification status'");
addColumnIfNotExists($conn, 'users', 'kyc_reject_reason', 'TEXT NULL COMMENT "Reason for rejection if any"');
addColumnIfNotExists($conn, 'users', 'bank_name', 'VARCHAR(100) NULL COMMENT "Bank Name"');
addColumnIfNotExists($conn, 'users', 'bank_account', 'VARCHAR(50) NULL COMMENT "Account Number"');
addColumnIfNotExists($conn, 'users', 'bank_account_name', 'VARCHAR(100) NULL COMMENT "Account Name"');

// 2. Check Orders Table (Extra verification fields)
echo "<h3>Checking 'orders' table...</h3>";
addColumnIfNotExists($conn, 'orders', 'order_type', "ENUM('pickup', 'walkin') DEFAULT 'pickup'");
addColumnIfNotExists($conn, 'orders', 'latitude', 'DECIMAL(10, 8) NULL');
addColumnIfNotExists($conn, 'orders', 'longitude', 'DECIMAL(11, 8) NULL');
addColumnIfNotExists($conn, 'orders', 'payment_method', "ENUM('cash', 'transfer') DEFAULT 'cash'");
addColumnIfNotExists($conn, 'orders', 'is_verified_by_user', 'TINYINT(1) DEFAULT 0');

// 3. Create Withdrawals Table if missing
echo "<h3>Checking 'withdrawals' table...</h3>";
try {
    $conn->query("SELECT 1 FROM withdrawals LIMIT 1");
    echo "<div style='color:gray'>[INFO] Table 'withdrawals' already exists.</div>";
} catch (PDOException $e) {
    $sql = "CREATE TABLE IF NOT EXISTS withdrawals (
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
    $conn->exec($sql);
    echo "<div style='color:green'>[SUCCESS] Created table 'withdrawals'.</div>";
}

echo "<hr><h2 style='color:green'>Database Check Complete!</h2>";
echo "<p>You can now go back to <a href='components/profile.php'>Profile Page</a> and try saving again.</p>";
