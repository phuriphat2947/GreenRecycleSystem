<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'notifications';

// Mark all as read
try {
    $conn->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
} catch (PDOException $e) {
    // Ignore error
}

// Fetch Notifications
try {
    // Assuming 'link' or 'order_id' column exists? 
    // Let's modify the query to be standard first.
    $stmt = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 50");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

function getNotificationLink($type, $msg)
{
    // 1. Order Related
    if ($type == 'new_order') {
        // Pattern: "Order #123 Completed..." or "มีคำร้องขอรับขยะใหม่ Order #123"
        // Regex to catch # followed by digits anywhere in the string
        if (preg_match('/#(\d+)/', $msg, $matches)) {
            return "order_process.php?id=" . $matches[1];
        }
        return "orders.php";
    }

    // 2. KYC Submission
    if ($type == 'kyc_submission') {
        return "users.php?role=user";
    }

    // 3. New User
    if ($type == 'new_user') {
        return "users.php";
    }

    // Default fallback
    return "#";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>การแจ้งเตือน - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>การแจ้งเตือน</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">

            <div class="stats-grid" style="display: block;">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php $link = getNotificationLink($notif['type'], $notif['message']); ?>
                        <a href="<?php echo $link; ?>" style="text-decoration: none; color: inherit;">
                            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 1rem; border-left: 5px solid var(--primary); display: flex; align-items: flex-start; gap: 1rem; transition: transform 0.2s;">
                                <div style="background: var(--light-bg); padding: 1rem; border-radius: 50%;">
                                    <?php if ($notif['type'] == 'new_user'): ?>
                                        <i class="fas fa-user-plus" style="color: var(--primary);"></i>
                                    <?php elseif ($notif['type'] == 'new_order'): ?>
                                        <i class="fas fa-box" style="color: var(--info);"></i>
                                    <?php elseif ($notif['type'] == 'kyc_submission'): ?>
                                        <i class="fas fa-id-card" style="color: var(--warning);"></i>
                                    <?php else: ?>
                                        <i class="fas fa-bell" style="color: var(--warning);"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin-bottom: 0.2rem; color: var(--secondary);"><?php echo htmlspecialchars($notif['message']); ?></h4>
                                    <span style="font-size: 0.8rem; color: var(--text-light);"><i class="far fa-clock"></i> <?php echo date('d M Y H:i', strtotime($notif['created_at'])); ?></span>
                                    <div style="margin-top:5px; font-size:0.85rem; color:var(--primary); font-weight:600;">คลิกเพื่อดูรายละเอียด <i class="fas fa-arrow-right"></i></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-light); background: white; border-radius: 12px;">ไม่มีการแจ้งเตือนใหม่</p>
                <?php endif; ?>
            </div>

        </main>
    </div>

</body>

</html>