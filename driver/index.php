<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check if Driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit();
}

$driver_id = $_SESSION['user_id'];

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_job'])) {
    try {
        $order_id = $_POST['order_id'];
        $reason = $_POST['cancel_reason'];

        // Verify ownership
        $check = $conn->prepare("SELECT id FROM orders WHERE id = :id AND driver_id = :did");
        $check->execute([':id' => $order_id, ':did' => $driver_id]);

        if ($check->rowCount() > 0) {
            $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $order_id]);

            // Notify Admin
            $conn->prepare("INSERT INTO admin_notifications (type, message, related_id, created_at) VALUES ('order', :msg, :oid, NOW())")
                ->execute([
                    ':msg' => "Order #$order_id Cancelled by Driver. Reason: $reason",
                    ':oid' => $order_id
                ]);

            header("Location: index.php?msg=cancelled");
            exit();
        }
    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
    }
}

// Fetch Assigned Jobs (Active)
try {
    $sql = "SELECT o.*, u.username as customer_name, u.phone as customer_phone 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.driver_id = :driver_id 
            AND o.status IN ('pending', 'accepted', 'waiting_confirm', 'user_confirmed') 
            ORDER BY o.pickup_date ASC, o.pickup_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':driver_id' => $driver_id]);
    $active_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch History Jobs (Completed/Cancelled)
    $sql_hist = "SELECT o.*, u.username as customer_name 
                 FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 WHERE o.driver_id = :driver_id 
                 AND o.status IN ('completed', 'cancelled') 
                 ORDER BY o.updated_at DESC LIMIT 20";
    $stmt_hist = $conn->prepare($sql_hist);
    $stmt_hist->execute([':driver_id' => $driver_id]);
    $history_jobs = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - GreenDigital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/driver.css?v=<?php echo time(); ?>">
    <style>
        /* Table Override for Desktop/Tablet */
        .table-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin: 20px auto;
            max-width: 1200px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            /* Force scroll on small screens */
        }

        .premium-table thead {
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            color: white;
        }

        .premium-table th {
            padding: 15px;
            text-align: left;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .premium-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
            vertical-align: middle;
        }

        .premium-table tr:last-child td {
            border-bottom: none;
        }

        .premium-table tr:hover {
            background-color: #f9f9f9;
        }

        /* Responsive Scroll */
        .responsive-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 20px;
        }

        /* Status Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .bg-pending {
            background: #fff3cd;
            color: #856404;
        }

        .bg-accepted {
            background: #d4edda;
            color: #155724;
        }

        .bg-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .bg-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-green {
            background: #2ecc71;
            color: white;
        }

        .btn-green:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .btn-blue {
            background: #3498db;
            color: white;
        }

        .btn-red {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .btn-red:hover {
            background: rgba(231, 76, 60, 0.2);
        }

        /* Container Limit */
        .main-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
    </style>
</head>

<body>

    <!-- Premium Header -->
    <header class="driver-header">
        <div class="header-top">
            <div class="brand"><i class="fas fa-leaf"></i> Green<span>Driver</span></div>
            <div class="profile-pill">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
        </div>
        <div style="font-size:1.2rem; font-weight:600; margin-bottom:5px;">
            Dashboard (Table View)
        </div>
    </header>

    <!-- Floating Tabs -->
    <div class="tab-container" style="justify-content: center; display: flex;">
        <div class="glass-tabs" style="width: auto; padding: 5px 10px;">
            <div id="btn-active" class="tab-btn active" onclick="switchTab('active')" style="width: 150px;">
                <i class="fas fa-tasks"></i> งานที่ต้องทำ
            </div>
            <div id="btn-history" class="tab-btn" onclick="switchTab('history')" style="width: 150px;">
                <i class="fas fa-history"></i> ประวัติ
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-container">

        <!-- ACTIVE TABLE -->
        <div id="list-active" class="tab-content fade-in-up">
            <div class="responsive-wrapper">
                <div class="table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>ลูกค้า</th>
                                <th>เวลานัดหมาย</th>
                                <th>สถานที่</th>
                                <th>สถานะ</th>
                                <th>การชำระ</th>
                                <th style="text-align:center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_jobs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 40px; color: #999;">
                                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; display:block;"></i>
                                        ไม่มีงานค้างอยู่ในขณะนี้
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_jobs as $job): ?>
                                    <?php
                                    $status_class = 'bg-pending';
                                    if ($job['status'] == 'accepted') $status_class = 'bg-accepted';
                                    if ($job['status'] == 'user_confirmed') $status_class = 'bg-accepted';
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo $job['id']; ?></strong></td>
                                        <td>
                                            <div style="font-weight:600; font-size:1.1rem;"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                            <?php if (!empty($job['customer_phone'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="btn-sm btn-blue" style="margin-top:5px; padding: 8px 15px;">
                                                    <i class="fas fa-phone-volume"></i> <?php echo htmlspecialchars($job['customer_phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#999; font-size:0.9rem;">(ไม่มีเบอร์โทร)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size:1rem;"><?php echo date('d/m/Y', strtotime($job['pickup_date'])); ?></div>
                                            <div style="color:#27ae60; font-weight:bold; font-size:1.1rem;"><?php echo date('H:i', strtotime($job['pickup_time'])); ?> น.</div>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size:1rem;">
                                                <i class="fas fa-map-marker-alt" style="color:#e74c3c;"></i>
                                                <?php echo htmlspecialchars($job['pickup_address']); ?>
                                            </div>
                                            <?php if ($job['latitude'] && $job['longitude']): ?>
                                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $job['latitude']; ?>,<?php echo $job['longitude']; ?>" target="_blank" class="btn-sm btn-blue" style="margin-top:5px; background:#3498db; color:white; display:inline-flex;">
                                                    <i class="fas fa-location-arrow"></i> นำทาง
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>" style="font-size:0.9rem;">
                                                <?php echo strtoupper($job['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($job['payment_method'] == 'transfer' || $job['payment_method'] == 'wallet'): ?>
                                                <span style="color:#27ae60; font-weight:600; font-size:0.95rem;"><i class="fas fa-wallet"></i> Wallet</span>
                                            <?php else: ?>
                                                <span style="color:#7f8c8d; font-size:0.95rem;"><i class="fas fa-money-bill-wave"></i> เงินสด</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="display:flex; flex-direction: column; gap:8px;">
                                                <a href="job_action.php?order_id=<?php echo $job['id']; ?>" class="btn-sm btn-green" style="justify-content:center; padding: 10px; font-size: 1rem;">
                                                    <i class="fas fa-play"></i> เริ่มงาน
                                                </a>
                                                <button onclick="openCancelModal('<?php echo $job['id']; ?>')" class="btn-sm btn-red" style="justify-content:center; padding: 8px;">
                                                    <i class="fas fa-times"></i> ยกเลิก
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- HISTORY TABLE -->
        <div id="list-history" class="tab-content" style="display:none;">
            <div class="responsive-wrapper">
                <div class="table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>ลูกค้า</th>
                                <th>เสร็จสิ้นเมื่อ</th>
                                <th>น้ำหนักรวม</th>
                                <th>ยอดเงิน</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history_jobs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 30px;">ไม่พบประวัติงาน</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history_jobs as $job): ?>
                                    <tr>
                                        <td>#<?php echo $job['id']; ?></td>
                                        <td><?php echo htmlspecialchars($job['customer_name']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($job['updated_at'])); ?></td>
                                        <td><b><?php echo $job['total_weight']; ?> kg</b></td>
                                        <td style="color:#27ae60; font-weight:bold;">฿<?php echo number_format($job['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $job['status'] == 'completed' ? 'bg-completed' : 'bg-cancelled'; ?>">
                                                <?php echo strtoupper($job['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Glass Bottom Nav -->
    <nav class="glass-nav">
        <a href="index.php" class="nav-item active">
            <i class="fas fa-truck-loading"></i>
        </a>
        <a href="../logout.php" class="nav-item" style="color:#e74c3c;">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </nav>

    <!-- Modal Logic -->
    <div id="cancel-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; align-items:center; justify-content:center;">
        <div class="modal-box" style="background:white; width:90%; max-width:320px; text-align:center;">
            <i class="fas fa-exclamation-triangle" style="font-size:3rem; color:#e74c3c; margin-bottom:15px;"></i>
            <h3 style="margin:0 0 10px 0;">ยืนยันการยกเลิก</h3>
            <form method="POST">
                <input type="hidden" name="order_id" id="cancel-order-id">
                <textarea name="cancel_reason" rows="3" placeholder="เหตุผล..." style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;" required></textarea>
                <div style="display:flex; gap:10px; justify-content:center;">
                    <button type="button" onclick="closeCancelModal()" class="btn-sm" style="background:#eee;">ปิด</button>
                    <button type="submit" name="cancel_job" class="btn-sm btn-red" style="background:#e74c3c; color:white;">ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('fade-in-up'));

            if (tab === 'active') {
                document.getElementById('btn-active').classList.add('active');
                const content = document.getElementById('list-active');
                content.style.display = 'block';
                content.classList.add('fade-in-up');
            } else {
                document.getElementById('btn-history').classList.add('active');
                const content = document.getElementById('list-history');
                content.style.display = 'block';
                content.classList.add('fade-in-up');
            }
        }

        function openCancelModal(id) {
            document.getElementById('cancel-order-id').value = id;
            document.getElementById('cancel-modal').style.display = 'flex';
        }

        function closeCancelModal() {
            document.getElementById('cancel-modal').style.display = 'none';
        }
    </script>

</body>

</html>