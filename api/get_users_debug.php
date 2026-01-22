<?php
require_once "../db_connect/db_connect.php";
$stmt = $conn->query("SELECT id, username, role FROM users LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users);
