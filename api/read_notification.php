<?php
header('Content-Type: application/json');
session_start();
require_once '../db_connect/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        try {
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $data['id'], ':uid' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else { // Mark all as read
        try {
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = :uid");
            $stmt->execute([':uid' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
