<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Admin Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$driver_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$driver_id) {
    header("Location: users.php");
    exit();
}

// Fetch Driver Info
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'driver'");
    $stmt->execute([':id' => $driver_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        die("Driver not found.");
    }

    // Fetch Job Stats
    $stats_sql = "SELECT 
                    COUNT(*) as total_jobs, 
                    SUM(total_weight) as total_weight, 
                    SUM(total_amount) as total_amount 
                  FROM orders 
                  WHERE driver_id = :id AND status = 'completed'";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute([':id' => $driver_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch All Jobs
    $jobs_sql = "SELECT o.*, u.username as customer_name 
                 FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 WHERE o.driver_id = :id 
                 ORDER BY o.pickup_date DESC, o.pickup_time DESC";
    $jobs_stmt = $conn->prepare($jobs_sql);
    $jobs_stmt->execute([':id' => $driver_id]);
    $jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ประวัติงานคนขับ - <?php echo htmlspecialchars($driver['username']); ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2><i class="fas fa-truck"></i> ประวัติงาน: <?php echo htmlspecialchars($driver['username']); ?></h2>
            </div>
            <div class="header-tools">
                <a href="users.php?role=driver" style="color: var(--text-light);"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            </div>
        </header>

        <main class="content-wrapper">

 
            <div style="background: white; padding: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <img src="<?php echo ($driver['profile_image'] == 'default_avatar.png') ? 'https://via.placeholder.com/80' : '../assets/images/uploads/' . $driver['profile_image']; ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                <div>
                    <h3 style="margin: 0; color: var(--secondary);"><?php echo htmlspecialchars($driver['username']); ?></h3>
                    <p style="margin: 5px 0; color: #666;">ID: #<?php echo $driver['id']; ?> | Email: <?php echo htmlspecialchars($driver['email']); ?></p>
                    <span style="background: #17a2b8; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">DRIVER</span>
                </div>
            </div>

      
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="stat-card" style="background:white; padding:1.5rem; border-radius:12px; text-align:center; box-shadow:var(--shadow);">
                    <i class="fas fa-clipboard-check" style="font-size:2rem; color:var(--primary); margin-bottom:10px;"></i>
                    <h3 style="font-size: 2rem; margin:0;"><?php echo number_format($stats['total_jobs']); ?></h3>
                    <p style="color:#666;">งานที่สำเร็จ</p>
                </div>
                <div class="stat-card" style="background:white; padding:1.5rem; border-radius:12px; text-align:center; box-shadow:var(--shadow);">
                    <i class="fas fa-weight-hanging" style="font-size:2rem; color:#f39c12; margin-bottom:10px;"></i>
                    <h3 style="font-size: 2rem; margin:0;"><?php echo number_format($stats['total_weight'], 1); ?> kg</h3>
                    <p style="color:#666;">น้ำหนักรวม</p>
                </div>
                <div class="stat-card" style="background:white; padding:1.5rem; border-radius:12px; text-align:center; box-shadow:var(--shadow);">
                    <i class="fas fa-coins" style="font-size:2rem; color:#27ae60; margin-bottom:10px;"></i>
                    <h3 style="font-size: 2rem; margin:0;">฿<?php echo number_format($stats['total_amount'], 2); ?></h3>
                    <p style="color:#666;">ยอดเงินหมุนเวียน</p>
                </div>
            </div>

   
            <div style="background: white; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden;">
                <h3 style="padding: 1rem; margin: 0; background: var(--light-bg); border-bottom: 1px solid #eee;">รายการงานทั้งหมด</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9f9f9; text-align: left;">
                            <th style="padding: 1rem;">Order ID</th>
                            <th style="padding: 1rem;">ลูกค้า</th>
                            <th style="padding: 1rem;">วันที่นัดรับ</th>
                            <th style="padding: 1rem;">วันที่จบงาน</th>
                            <th style="padding: 1rem;">สถานะ</th>
                            <th style="padding: 1rem; text-align: right;">น้ำหนัก / ยอดเงิน</th>
                            <th style="padding: 1rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($jobs) > 0): ?>
                            <?php foreach ($jobs as $job): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 1rem; font-weight: bold;">#<?php echo $job['id']; ?></td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars($job['customer_name']); ?></td>
                                    <td style="padding: 1rem;">
                                        <?php echo date('d/m/Y', strtotime($job['pickup_date'])); ?>
                                        <span style="font-size:0.8rem; color:#888;"><?php echo date('H:i', strtotime($job['pickup_time'])); ?></span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo ($job['status'] == 'completed') ? date('d/m/Y H:i', strtotime($job['updated_at'])) : '-'; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; 
                                            background: <?php echo $job['status'] == 'completed' ? '#d4edda' : ($job['status'] == 'cancelled' ? '#f8d7da' : '#fff3cd'); ?>;
                                            color: <?php echo $job['status'] == 'completed' ? '#155724' : ($job['status'] == 'cancelled' ? '#721c24' : '#856404'); ?>;">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <?php if ($job['status'] == 'completed'): ?>
                                            <div><?php echo number_format($job['total_weight'], 1); ?> kg</div>
                                            <div style="font-weight: bold; color: var(--primary);">฿<?php echo number_format($job['total_amount'], 2); ?></div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <a href="order_process.php?id=<?php echo $job['id']; ?>" style="color: var(--info);"><i class="fas fa-eye"></i> ดู</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 2rem; text-align: center;">ไม่พบประวัติงาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</body>

</html>