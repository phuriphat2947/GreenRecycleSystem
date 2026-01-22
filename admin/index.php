<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Admin Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'dashboard';

// --- Analytics Queries ---
try {
    // 1. Today's Stats
    $stmt = $conn->prepare("SELECT SUM(total_amount) as income, SUM(total_weight) as weight, COUNT(*) as orders 
                            FROM orders WHERE status='completed' AND DATE(updated_at) = CURDATE()");
    $stmt->execute();
    $today = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Yesterday's Stats
    $stmt = $conn->prepare("SELECT SUM(total_amount) as income, SUM(total_weight) as weight, COUNT(*) as orders 
                            FROM orders WHERE status='completed' AND DATE(updated_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $stmt->execute();
    $yesterday = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. This Month's Stats
    $stmt = $conn->prepare("SELECT SUM(total_amount) as income, SUM(total_weight) as weight, COUNT(*) as orders 
                            FROM orders WHERE status='completed' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");
    $stmt->execute();
    $month = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Last Month's Stats
    $stmt = $conn->prepare("SELECT SUM(total_amount) as income, SUM(total_weight) as weight, COUNT(*) as orders 
                            FROM orders WHERE status='completed' AND MONTH(updated_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(updated_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stmt->execute();
    $last_month = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. This Year's Stats
    $stmt = $conn->prepare("SELECT SUM(total_amount) as income, SUM(total_weight) as weight, COUNT(*) as orders 
                            FROM orders WHERE status='completed' AND YEAR(updated_at) = YEAR(CURDATE())");
    $stmt->execute();
    $year = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Action Needed (Waiting Payment)
    $stmt_act = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'user_confirmed'");
    $action_needed_count = $stmt_act->fetchColumn();

    // 6. Waste Breakdown (This Month) - Top 5
    $stmt = $conn->prepare("SELECT wt.name, wt.image, SUM(oi.actual_weight) as weight, SUM(oi.subtotal) as amount 
                            FROM order_items oi 
                            JOIN orders o ON oi.order_id = o.id 
                            JOIN waste_types wt ON oi.waste_type_id = wt.id 
                            WHERE o.status='completed' AND MONTH(o.updated_at) = MONTH(CURDATE()) AND YEAR(o.updated_at) = YEAR(CURDATE())
                            GROUP BY wt.id 
                            ORDER BY weight DESC 
                            LIMIT 5");
    $stmt->execute();
    $waste_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. General User Stats
    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $total_users = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Helper: Safety check for nulls
function val($val)
{
    return $val ? $val : 0;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        @media (max-width: 900px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table-custom th,
        .table-custom td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .table-custom th {
            color: #888;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .progress-bg {
            background: #eee;
            border-radius: 4px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>📊 ภาพรวมและสถิติ (Analytics)</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">

 
            <h3 style="margin-bottom: 1rem; color: var(--secondary);">สรุปยอดวันนี้ (Today's Real-time)</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-success" style="background: #2ecc71;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3>฿<?php echo number_format(val($today['income']), 2); ?></h3>
                        <p>รายรับวันนี้</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-info" style="background: #3498db;">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format(val($today['weight']), 1); ?> kg</h3>
                        <p>น้ำหนักขยะวันนี้</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-warning" style="background: #f1c40f;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format(val($today['orders'])); ?></h3>
                        <p>ออเดอร์ที่สำเร็จ</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-primary" style="background: #9b59b6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($total_users); ?></h3>
                        <p>สมาชิกทั้งหมด</p>
                    </div>
                </div>

 
                <?php if ($action_needed_count > 0): ?>
                    <a href="orders.php?status=user_confirmed" class="stat-card" style="text-decoration:none; border:2px solid #e74c3c;">
                        <div class="stat-icon" style="background: #e74c3c; color: white;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-info">
                            <h3 style="color:#e74c3c;"><?php echo $action_needed_count; ?></h3>
                            <p style="color:#c0392b; font-weight:bold;">รออนุมัติจ่ายเงิน!</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

 
            <div class="analytics-grid">

  
                <div class="stat-card" style="display: block;">
                    <div class="card-header">
                        <h4>📈 สรุปรายได้และปริมาณขยะ (Financial Report)</h4>
                        <span style="font-size:0.8rem; color:#888;">ข้อมูลเปรียบเทียบ</span>
                    </div>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ช่วงเวลา (Period)</th>
                                <th>รายได้ (Income)</th>
                                <th>น้ำหนักรวม (Weight)</th>
                                <th>ออเดอร์ (Orders)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>วันนี้ (Today)</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">฿<?php echo number_format(val($today['income']), 2); ?></td>
                                <td><?php echo number_format(val($today['weight']), 1); ?> kg</td>
                                <td><?php echo number_format(val($today['orders'])); ?></td>
                            </tr>
                            <tr>
                                <td>เมื่อวาน (Yesterday)</td>
                                <td>฿<?php echo number_format(val($yesterday['income']), 2); ?></td>
                                <td><?php echo number_format(val($yesterday['weight']), 1); ?> kg</td>
                                <td><?php echo number_format(val($yesterday['orders'])); ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" style="background:#f9f9f9; height:5px; padding:0;"></td>
                            </tr>
                            <tr>
                                <td><strong>เดือนนี้ (This Month)</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">฿<?php echo number_format(val($month['income']), 2); ?></td>
                                <td><?php echo number_format(val($month['weight']), 1); ?> kg</td>
                                <td><?php echo number_format(val($month['orders'])); ?></td>
                            </tr>
                            <tr>
                                <td>เดือนที่แล้ว (Last Month)</td>
                                <td>฿<?php echo number_format(val($last_month['income']), 2); ?></td>
                                <td><?php echo number_format(val($last_month['weight']), 1); ?> kg</td>
                                <td><?php echo number_format(val($last_month['orders'])); ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" style="background:#f9f9f9; height:5px; padding:0;"></td>
                            </tr>
                            <tr>
                                <td><strong>ปีนี้ (This Year)</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">฿<?php echo number_format(val($year['income']), 2); ?></td>
                                <td><?php echo number_format(val($year['weight']), 1); ?> kg</td>
                                <td><?php echo number_format(val($year['orders'])); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

   
                <div class="stat-card" style="display: block;">
                    <div class="card-header">
                        <h4>♻️ สัดส่วนขยะเดือนนี้ (Top Waste Types)</h4>
                        <span style="font-size:0.8rem; color:#888;">เรียงตามน้ำหนัก</span>
                    </div>
                    <?php if (count($waste_stats) > 0): ?>
                        <?php
                        $max_weight = $waste_stats[0]['weight']; // For progress bar calc
                        ?>
                        <?php foreach ($waste_stats as $waste): ?>
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ($waste['image']): ?>
                                            <img src="../assets/images/uploads/<?php echo $waste['image']; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-recycle" style="color: #aaa;"></i>
                                        <?php endif; ?>
                                        <span style="font-weight: 500; font-size: 0.95rem;"><?php echo htmlspecialchars($waste['name']); ?></span>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: bold; font-size: 0.9rem;"><?php echo number_format($waste['weight'], 1); ?> kg</div>
                                        <div style="font-size: 0.8rem; color: #888;">฿<?php echo number_format($waste['amount'], 0); ?></div>
                                    </div>
                                </div>
                                <div class="progress-bg">
                                    <div class="progress-fill" style="width: <?php echo ($waste['weight'] / $max_weight) * 100; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding: 2rem; color: #aaa;">
                            <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>ไม่มีข้อมูลการรับซื้อในเดือนนี้</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

</body>

</html>