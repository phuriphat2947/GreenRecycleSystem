<?php
header('Content-Type: application/json');
session_start();
require_once '../db_connect/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];

    // Fetch unread count
    $stmt_count = $conn->prepare("SELECT COUNT(*) as unread FROM user_notifications WHERE user_id = :uid AND is_read = 0");
    $stmt_count->execute([':uid' => $user_id]);
    $unread_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['unread'];

    // Fetch latest 10 notifications
    $stmt = $conn->prepare("SELECT * FROM user_notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([':uid' => $user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
