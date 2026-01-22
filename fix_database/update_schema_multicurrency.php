<?php
require_once "db_connect/db_connect.php";

echo "<h2>Starting Schema Update for Multi-Currency...</h2>";

try {
    // 1. users table
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN spent_recycled_weight DECIMAL(12,2) DEFAULT 0.00 AFTER total_recycled_weight");
        echo "Added 'spent_recycled_weight' to users.<br>";
    } catch (Exception $e) {
        echo "Column 'spent_recycled_weight' might already exist.<br>";
    }

    try {
        $conn->exec("ALTER TABLE users ADD COLUMN total_carbon_saved DECIMAL(12,2) DEFAULT 0.00 AFTER spent_recycled_weight");
        echo "Added 'total_carbon_saved' to users.<br>";
    } catch (Exception $e) {
        echo "Column 'total_carbon_saved' might already exist.<br>";
    }

    try {
        $conn->exec("ALTER TABLE users ADD COLUMN spent_carbon_saved DECIMAL(12,2) DEFAULT 0.00 AFTER total_carbon_saved");
        echo "Added 'spent_carbon_saved' to users.<br>";
    } catch (Exception $e) {
        echo "Column 'spent_carbon_saved' might already exist.<br>";
    }

    // 2. waste_types table
    try {
        $conn->exec("ALTER TABLE waste_types ADD COLUMN carbon_per_kg DECIMAL(10,2) DEFAULT 0.50 AFTER pickup_price_per_kg");
        echo "Added 'carbon_per_kg' to waste_types.<br>";
    } catch (Exception $e) {
        echo "Column 'carbon_per_kg' might already exist.<br>";
    }

    // 3. rewards table
    try {
        $conn->exec("ALTER TABLE rewards ADD COLUMN redeem_type ENUM('point', 'weight', 'carbon') DEFAULT 'point' AFTER stock");
        echo "Added 'redeem_type' to rewards.<br>";
    } catch (Exception $e) {
        echo "Column 'redeem_type' might already exist.<br>";
    }

    echo "<h3>Schema Update Completed Successfully!</h3>";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
