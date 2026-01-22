<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'orders';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

// Build Query
$sql = "SELECT o.*, u.username, u.citizen_id, d.username as driver_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN users d ON o.driver_id = d.id 
        WHERE 1=1";

if ($filter != 'all') {
    if ($filter == 'pending') {
        $sql .= " AND o.status = 'pending'";
    } elseif ($filter == 'accepted') {
        $sql .= " AND o.status = 'accepted'";
    } elseif ($filter == 'waiting_confirm') {
        $sql .= " AND o.status = 'waiting_confirm'";
    } elseif ($filter == 'topay') {
        $sql .= " AND o.status = 'user_confirmed'";
    } elseif ($filter == 'completed') {
        $sql .= " AND o.status = 'completed'";
    } elseif ($filter == 'cancelled') {
        $sql .= " AND o.status = 'cancelled'";
    }
}
$sql .= " ORDER BY o.pickup_date ASC, o.created_at ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการคำขอรับขยะ - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-btn {
            padding: 0.5rem 1rem;
            margin-right: 5px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            color: #555;
            background: #eee;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>จัดการคำขอรับขยะ (Orders)</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">

            <div style="margin-bottom: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <a href="orders.php?filter=pending" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">รอคนขับรับงาน</a>
                    <a href="orders.php?filter=accepted" class="filter-btn <?php echo $filter == 'accepted' ? 'active' : ''; ?>" style="background-color: #27ae60; color: white;">กำลังไปรับ</a>
                    <a href="orders.php?filter=waiting_confirm" class="filter-btn <?php echo $filter == 'waiting_confirm' ? 'active' : ''; ?>" style="background-color: #f1c40f; color: black;">รอยืนยัน</a>
                    <a href="orders.php?filter=topay" class="filter-btn <?php echo $filter == 'topay' ? 'active' : ''; ?>" style="background-color: #e74c3c; color: white;">รอโอนเงิน</a>
                    <a href="orders.php?filter=completed" class="filter-btn <?php echo $filter == 'completed' ? 'active' : ''; ?>">เสร็จสิ้น</a>
                    <a href="orders.php?filter=cancelled" class="filter-btn <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">ยกเลิก</a>
                    <a href="orders.php?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">ทั้งหมด</a>
                </div>
            </div>

            <div style="background: white; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: var(--light-bg); border-bottom: 2px solid #eee;">
                        <tr>
                            <th style="padding: 1rem; text-align: left;">Order ID</th>
                            <th style="padding: 1rem; text-align: left;">ลูกค้า</th>
                            <th style="padding: 1rem; text-align: left;">วันนัดรับ</th>
                            <th style="padding: 1rem; text-align: left;">สถานที่</th>
                            <th style="padding: 1rem; text-align: center;">ชำระเงิน</th>
                            <th style="padding: 1rem; text-align: left;">คนขับ (Driver)</th>
                            <th style="padding: 1rem; text-align: center;">สถานะ</th>
                            <th style="padding: 1rem; text-align: right;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $o): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 1rem; font-weight: 600;">#<?php echo $o['id']; ?></td>
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($o['username']); ?></div>
                                        <div style="font-size: 0.8rem; color: #888;"><?php echo $o['citizen_id']; ?></div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($o['pickup_date'])); ?></div>
                                        <div style="font-size: 0.9rem; color: #888;"><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($o['pickup_time'])); ?></div>
                                    </td>
                                    <td style="padding: 1rem; max-width: 250px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($o['pickup_address']); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php if ($o['payment_method'] == 'transfer'): ?>
                                            <span style="color: #3498db; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-wallet"></i> Wallet</span>
                                        <?php else: ?>
                                            <span style="color: #27ae60; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-money-bill-wave"></i> Cash</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php if (!empty($o['driver_name'])): ?>
                                            <div style="font-weight: 600; color:var(--primary);"><i class="fas fa-truck"></i> <?php echo htmlspecialchars($o['driver_name']); ?></div>
                                        <?php else: ?>
                                            <span style="color: #aaa; font-style: italic;">- ยังไม่ระบุ -</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php
                                        $st = $o['status'];
                                        if ($st == 'pending') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #e67e22; color: white;">Pending (รอคนขับ)</span>';
                                        } elseif ($st == 'waiting_confirm') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #f1c40f; color: black; font-weight:bold;">Waiting Confirm</span>';
                                        } elseif ($st == 'user_confirmed') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #3498db; color: white;">User Confirmed</span>';
                                        } elseif ($st == 'accepted') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #27ae60; color: white;">Accepted (กำลังรับ)</span>';
                                        } elseif ($st == 'completed') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #2ecc71; color: white;">Completed</span>';
                                        } elseif ($st == 'cancelled') {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #e74c3c; color: white;">Cancelled</span>';
                                        } elseif (empty($st)) {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #95a5a6; color: white;">Details Incomplete (NULL)</span>';
                                        } else {
                                            echo '<span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; background: #eee;">' . ucfirst($st) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <a href="order_process.php?id=<?php echo $o['id']; ?>" style="color: white; background: var(--primary); padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem;">
                                            <i class="fas fa-edit"></i> จัดการ
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: #aaa;">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

</body>

</html>