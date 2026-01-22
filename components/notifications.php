<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get Waiting Confirm Orders
$stmt = $conn->prepare("SELECT o.*, u.username as driver_name 
                        FROM orders o 
                        LEFT JOIN users u ON o.driver_id = u.id 
                        WHERE o.user_id = :uid AND o.status = 'waiting_confirm' 
                        ORDER BY o.updated_at DESC");
$stmt->execute([':uid' => $user_id]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การแจ้งเตือน - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notify-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            border-left: 5px solid #bdc3c7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notify-card.urgent {
            border-left-color: #e74c3c;
            background: #fff5f5;
        }

        .notify-content h3 {
            margin: 0 0 5px;
            font-size: 1rem;
        }

        .notify-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .notify-action {
            margin-left: 15px;
        }

        .btn-confirm {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-block;
            box-shadow: 0 4px 6px rgba(231, 76, 60, 0.2);
            animation: pulse-btn 2s infinite;
        }

        @keyframes pulse-btn {
            0% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="homepage.php" class="logo">Green<span>Digital</span></a>
        <div class="user-menu">
            <a href="homepage.php" class="btn-logout" style="background: transparent; color: var(--secondary); border: 1px solid var(--secondary);">
                <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
    </nav>

    <div class="dashboard-container" style="max-width: 800px; margin: 30px auto;">
        <h2 class="section-title"><i class="fas fa-bell"></i> การแจ้งเตือน (Notifications)</h2>

        <?php if (count($alerts) > 0): ?>
            <div style="margin-bottom: 20px; color: #e74c3c; font-weight: bold;">
                คุณมี <?php echo count($alerts); ?> รายการที่ต้องดำเนินการ
            </div>

            <?php foreach ($alerts as $alert): ?>
                <div class="notify-card urgent">
                    <div class="notify-content">
                        <h3 style="color: #c0392b;">🔴 กรุณาตรวจสอบและยืนยันงาน (Order #<?php echo $alert['id']; ?>)</h3>
                        <p>คนขับ <strong><?php echo htmlspecialchars($alert['driver_name']); ?></strong> ส่งงานแล้วเมื่อ <?php echo date('d M H:i', strtotime($alert['updated_at'])); ?></p>
                    </div>
                    <div class="notify-action">
                        <a href="confirm_order.php?id=<?php echo $alert['id']; ?>" class="btn-confirm">
                            ตรวจสอบทันที <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
        <?php endif; ?>

        <!-- General Notifications Section -->
        <?php
        // Fetch General Notifications
        $gen_stmt = $conn->prepare("SELECT * FROM user_notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20");
        $gen_stmt->execute([':uid' => $user_id]);
        $gen_notifs = $gen_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <h3 style="color: var(--secondary); margin-bottom: 20px;">ประวัติการแจ้งเตือน</h3>

        <?php if (count($gen_notifs) > 0): ?>
            <?php foreach ($gen_notifs as $noti): ?>
                <?php
                $iconClass = 'fa-info-circle';
                $iconColor = '#3498db';
                $bgClass = '';

                if ($noti['type'] == 'deposit' || $noti['type'] == 'money') {
                    $iconClass = 'fa-wallet';
                    $iconColor = '#f1c40f';
                } elseif ($noti['type'] == 'order_complete') {
                    $iconClass = 'fa-check-circle';
                    $iconColor = '#2ecc71';
                }

                if ($noti['is_read'] == 0) {
                    $bgClass = 'background: #f0fdf4; border-left: 5px solid #2ecc71;';
                } else {
                    $bgClass = 'background: white; border-left: 5px solid #bdc3c7; opacity: 0.8;';
                }
                ?>
                <div class="notify-card" style="<?php echo $bgClass; ?> padding: 15px;">
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $iconColor; ?>20; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas <?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>;"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 5px; color: #333;"><?php echo htmlspecialchars($noti['title']); ?></h4>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($noti['message']); ?></p>
                            <div style="font-size: 0.75rem; color: #aaa; margin-top: 5px;">
                                <?php echo date('d M Y, H:i', strtotime($noti['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; color: #95a5a6;">
                <p>ไม่มีประวัติการแจ้งเตือน</p>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>