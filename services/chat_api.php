<?php
session_start();
require_once "../db_connect/db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- USER ACTIONS ---

if ($action == 'send') {
    // User sending message to Admin
    $msg = trim($_POST['message'] ?? '');
    if (!empty($msg)) {
        try {
            $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender_type, message, created_at) VALUES (:uid, 'user', :msg, NOW())");
            $stmt->execute([':uid' => $user_id, ':msg' => $msg]);

            // REMOVED AUTO-REPLY: We now have a real admin panel.
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} elseif ($action == 'fetch') {
    // User fetching their own chat
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    try {
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE user_id = :uid AND id > :lid AND status = 'active' ORDER BY id ASC");
        $stmt->execute([':uid' => $user_id, ':lid' => $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'messages' => $messages]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action == 'end_chat') {
    // Both User and Admin can end chat
    // If Admin calls, they must provide user_id. If User calls, use session user_id.

    $target_uid = $user_id; // Default to self
    if ($role === 'admin' && isset($_POST['user_id'])) {
        $target_uid = intval($_POST['user_id']);
    }

    try {
        // Archive all active messages for this user
        $stmt = $conn->prepare("UPDATE chat_messages SET status = 'archived' WHERE user_id = :uid AND status = 'active'");
        $stmt->execute([':uid' => $target_uid]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    // --- ADMIN ACTIONS ---
} elseif ($role === 'admin') {

    if ($action == 'get_users') {
        // List all users who have chatted, ordered by latest message
        try {
            // Only count active messages for unread
            $sql = "SELECT u.id, u.username, u.profile_image, 
                           MAX(m.created_at) as last_msg_time,
                           (SELECT message FROM chat_messages WHERE user_id = u.id AND status = 'active' ORDER BY id DESC LIMIT 1) as last_msg_text,
                           (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id AND sender_type = 'user' AND is_read = 0 AND status = 'active') as unread_count
                    FROM users u
                    JOIN chat_messages m ON u.id = m.user_id
                    WHERE m.status = 'active'
                    GROUP BY u.id
                    ORDER BY last_msg_time DESC";
            $stmt = $conn->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get_conversation') {
        // Admin fetching specific user's chat
        $target_uid = intval($_GET['target_user_id']);
        $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

        try {
            // Mark as read
            if ($last_id == 0) {
                $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = :uid AND sender_type = 'user' AND status = 'active'")->execute([':uid' => $target_uid]);
            }

            $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE user_id = :uid AND id > :lid AND status = 'active' ORDER BY id ASC");
            $stmt->execute([':uid' => $target_uid, ':lid' => $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'messages' => $messages]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'admin_send') {
        // Admin replying to user
        $target_uid = intval($_POST['target_user_id']);
        $msg = trim($_POST['message'] ?? '');

        if (!empty($msg)) {
            try {
                $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, admin_id, sender_type, message, created_at) VALUES (:uid, :aid, 'admin', :msg, NOW())");
                $stmt->execute([':uid' => $target_uid, ':aid' => $user_id, ':msg' => $msg]);
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Admin Action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Action']);
}
