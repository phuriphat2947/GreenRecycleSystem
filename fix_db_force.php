<?php
// fix_db_force.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db_connect/db_connect.php";

echo "Attempting to fix database schema...\n";

function addColumn($conn, $table, $colDef)
{
    try {
        $conn->exec("ALTER TABLE $table ADD COLUMN $colDef");
        echo "[SUCCESS] Added to $table: $colDef\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), '1060') !== false) {
            echo "[INFO] Column already exists in $table ($colDef)\n";
        } else {
            echo "[ERROR] Failed to alter $table: " . $e->getMessage() . "\n";
        }
    }
}

// 1. Users
addColumn($conn, 'users', "spent_recycled_weight DECIMAL(12,2) DEFAULT 0.00 AFTER total_recycled_weight");
addColumn($conn, 'users', "total_carbon_saved DECIMAL(12,2) DEFAULT 0.00 AFTER spent_recycled_weight");
addColumn($conn, 'users', "spent_carbon_saved DECIMAL(12,2) DEFAULT 0.00 AFTER total_carbon_saved");

// 2. Waste Types
addColumn($conn, 'waste_types', "carbon_per_kg DECIMAL(10,2) DEFAULT 0.50 AFTER pickup_price_per_kg");

// 3. Rewards
addColumn($conn, 'rewards', "redeem_type ENUM('point', 'weight', 'carbon') DEFAULT 'point' AFTER stock");

echo "\nDone. Please refresh the page.\n";
