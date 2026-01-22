<?php
require_once "../db_connect/db_connect.php";

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $conn->prepare("SELECT status, is_verified_by_user FROM orders WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        echo json_encode([
            'status' => $order['status'],
            'is_verified_by_user' => (bool)$order['is_verified_by_user']
        ]);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
