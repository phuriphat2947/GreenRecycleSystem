<?php
// fix_db_hybrid.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "db_connect/db_connect.php";

echo "Adding Hybrid Cost columns...\n";

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

// Add Hybrid Costs
addColumn($conn, 'rewards', "weight_cost DECIMAL(10,2) DEFAULT 0.00 AFTER points_cost");
addColumn($conn, 'rewards', "carbon_cost DECIMAL(10,2) DEFAULT 0.00 AFTER weight_cost");

echo "\nDone. Hybrid Schema Applied.\n";
