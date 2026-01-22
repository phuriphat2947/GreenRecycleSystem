<?php
require_once "db_connect/db_connect.php";
try {
    $stmt = $conn->query("DESCRIBE waste_types");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $cols);
} catch (Exception $e) {
    echo $e->getMessage();
}
