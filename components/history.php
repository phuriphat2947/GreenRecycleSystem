<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

 
$sql = "SELECT o.*, d.username as driver_name, d.email as driver_contact 
        FROM orders o 
        LEFT JOIN users d ON o.driver_id = d.id 
        WHERE o.user_id = :uid";
if ($filter == 'pending') {
    $sql .= " AND (o.status = 'pending' OR o.status = 'waiting_confirm')";
} elseif ($filter == 'accepted') {
    $sql .= " AND o.status = 'accepted'";
} elseif ($filter == 'completed') {
    $sql .= " AND o.status = 'completed'";
}
$sql .= " ORDER BY o.created_at DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order_id'])) {
    $cancel_id = $_POST['cancel_order_id'];
 
    $check = $conn->prepare("SELECT id FROM orders WHERE id = :id AND user_id = :uid AND status = 'pending'");
    $check->execute([':id' => $cancel_id, ':uid' => $_SESSION['user_id']]);
    if ($check->rowCount() > 0) {
        $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $cancel_id]);
        header("Location: history.php"); // Updated header
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติกิจกรรม - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .history-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            position: relative;
            border-left: 5px solid transparent;
        }

        .history-card.status-pending {
            border-left-color: var(--warning);
        }

        .history-card.status-completed {
            border-left-color: var(--primary);
        }

        .history-card.status-cancelled {
            border-left-color: var(--danger);
        }

        .history-card.status-waiting_confirm {
            border-left-color: #f1c40f;
            /* Yellow/Warning */
            background: #fffbef;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-id {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--secondary);
        }

        .order-date {
            font-size: 0.9rem;
            color: #888;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending .status-badge {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed .status-badge {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled .status-badge {
            background: #f8d7da;
            color: #721c24;
        }

        .history-details {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .detail-item {
            font-size: 0.9rem;
        }

        .detail-item i {
            width: 20px;
            color: var(--primary);
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            border: 1px solid var(--primary);
            border-radius: 20px;
            color: var(--primary);
            text-decoration: none;
            margin-right: 0.5rem;
            transition: all 0.3s;
        }

        .btn-filter.active,
        .btn-filter:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="homepage.php" class="logo">Green<span>Digital</span></a>
        <div class="user-menu">
            <a href="homepage.php" class="btn-logout" style="background: transparent; color: var(--secondary); border: 1px solid var(--secondary);">กลับหน้าหลัก</a>
        </div>
    </nav>

    <div class="dashboard-container" style="max-width: 1000px; margin: 2rem auto;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 class="section-title" style="margin-bottom: 0;">📜 ประวัติกิจกรรม</h2>
            <div>
                <a href="history.php?filter=all" class="btn-filter <?php echo $filter == 'all' ? 'active' : ''; ?>">ทั้งหมด</a>
                <a href="history.php?filter=pending" class="btn-filter <?php echo $filter == 'pending' ? 'active' : ''; ?>">รอดำเนินการ</a>
                <a href="history.php?filter=accepted" class="btn-filter <?php echo $filter == 'accepted' ? 'active' : ''; ?>">กำลังเดินทาง</a>
                <a href="history.php?filter=completed" class="btn-filter <?php echo $filter == 'completed' ? 'active' : ''; ?>">เสร็จสิ้น</a>
            </div>
        </div>

        <?php if (count($orders) > 0): ?>
            <?php foreach ($orders as $order): ?>
                <div class="history-card status-<?php echo $order['status']; ?>">
                    <div class="history-header">
                        <div>
                            <div class="order-id">Order #<?php echo $order['id']; ?></div>
                            <div class="order-date">สร้างเมื่อ: <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="status-badge">
                            <?php
                            switch ($order['status']) {
                                case 'pending':
                                    echo '<span style="color:#d35400;">รอการเข้ารับ</span>';
                                    break;
                                case 'accepted':
                                    echo '<span style="color:#2980b9;">กำลังดำเนินการ</span>';
                                    break;
                                case 'waiting_confirm':
                                    echo '<span style="background:#e74c3c; color:white; padding:5px 15px; border-radius:15px; animation:blink 1s infinite;">🔴 รอคุณยืนยัน (Action Required)</span>';
                                    break;
                                case 'user_confirmed':
                                    echo '<span style="color:#27ae60;">รออนุมัติเงิน</span>';
                                    break;
                                case 'completed':
                                    echo '<span style="color:green;">เสร็จสิ้น</span>';
                                    break;
                                case 'cancelled':
                                    echo '<span style="color:red;">ยกเลิก</span>';
                                    break;
                            }
                            ?>
                            <style>
                                @keyframes blink {
                                    50% {
                                        opacity: 0.7;
                                    }
                                }
                            </style>
                        </div>
                    </div>

                    <div class="history-details">
                        <div class="detail-item">
                            <i class="far fa-calendar-alt"></i> นัดหมาย:
                            <strong><?php echo date('d M Y', strtotime($order['pickup_date'])); ?></strong>
                            เวลา <strong><?php echo date('H:i', strtotime($order['pickup_time'])); ?></strong>
                        </div>

                        <?php if ($order['driver_id']): ?>
                            <div class="detail-item">
                                <i class="fas fa-truck"></i> คนขับ:
                                <strong style="color:var(--info);"><?php echo htmlspecialchars($order['driver_name']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['status'] == 'completed'): ?>
                            <div class="detail-item">
                                <i class="fas fa-weight-hanging"></i> น้ำหนักจริง:
                                <strong><?php echo number_format($order['total_weight'], 1); ?> kg</strong>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-coins"></i> ได้รับ:
                                <strong style="color: var(--primary);">฿<?php echo number_format($order['total_amount'], 2); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['status'] == 'waiting_confirm'): ?>
                            <div style="flex:1; text-align:right;">
                                <a href="confirm_order.php?id=<?php echo $order['id']; ?>" class="btn-filter" style="background:#e74c3c; color:white; border:none; padding: 12px 25px; font-size: 1rem; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4); border-radius: 50px; animation: pulse 2s infinite;">
                                    <i class="fas fa-check-circle"></i> ตรวจสอบและยืนยัน (กดที่นี่!)
                                </a>
                                <style>
                                    @keyframes pulse {
                                        0% {
                                            transform: scale(1);
                                            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
                                        }

                                        70% {
                                            transform: scale(1.05);
                                            box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
                                        }

                                        100% {
                                            transform: scale(1);
                                            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
                                        }
                                    }
                                </style>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px; color: #999;">
                <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>ยังไม่มีประวัติกิจกรรม</p>
                <a href="request_pickup.php" style="display: inline-block; margin-top: 1rem; color: var(--primary);">เริ่มเรียกรถรับขยะเลย!</a>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>