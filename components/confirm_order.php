<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Auth
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$order_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$order_id) {
    header("Location: profile.php");
    exit();
}

// Fetch Order Info with Items
try {
    $stmt = $conn->prepare("SELECT o.*, u.username as driver_name, u.profile_image as driver_image 
                            FROM orders o 
                            JOIN users u ON o.driver_id = u.id 
                            WHERE o.id = :id AND o.user_id = :uid");
    $stmt->execute([':id' => $order_id, ':uid' => $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['status'] != 'waiting_confirm') {
        echo "<script>alert('Order not valid for confirmation.'); window.location.href='profile.php';</script>";
        exit();
    }

    $stmt_items = $conn->prepare("SELECT oi.*, wt.name as waste_name 
                                  FROM order_items oi 
                                  JOIN waste_types wt ON oi.waste_type_id = wt.id 
                                  WHERE oi.order_id = :id");
    $stmt_items->execute([':id' => $order['id']]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Confirmation or Dispute
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm'])) {
        try {
            $conn->beginTransaction();

            // 1. Order Status -> 'user_confirmed' (Money NOT credited yet)
            $conn->prepare("UPDATE orders SET status = 'user_confirmed', is_verified_by_user = 1, user_confirm_timestamp = NOW() WHERE id = :id")
                ->execute([':id' => $order_id]);

            // 2. Notify Admin
            $conn->prepare("INSERT INTO admin_notifications (type, message, related_id, created_at) VALUES ('order', :msg, :oid, NOW())")
                ->execute([
                    ':msg' => "Order #$order_id Confirmed by User. Waiting for Admin Payment Approval.",
                    ':oid' => $order_id
                ]);

            $conn->commit();
            header("Location: profile.php?msg=confirmed");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            die("❌ System Error: " . $e->getMessage());
        }
    } elseif (isset($_POST['dispute'])) {
        $reason = $_POST['dispute_reason'];
        $conn->prepare("UPDATE orders SET status = 'disputed' WHERE id = :id")->execute([':id' => $order_id]);

        // Notify Admin of Dispute
        $conn->prepare("INSERT INTO admin_notifications (type, message, related_id, created_at) VALUES ('report', :msg, :oid, NOW())")
            ->execute([
                ':msg' => "DISPUTE: Order #$order_id reported by User. Reason: $reason",
                ':oid' => $order_id
            ]);

        header("Location: profile.php?msg=disputed");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยัน Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .proof-img {
            width: 100%;
            border-radius: 12px;
            border: 4px solid #fff;
            box-shadow: var(--shadow-md);
            margin: 15px 0;
            max-height: 400px;
            object-fit: contain;
            background: #2c3e50;
        }
    </style>
    <script>
        function showDispute() {
            document.getElementById('dispute-box').style.display = 'block';
            document.getElementById('btn-group').style.display = 'none';
        }

        function hideDispute() {
            document.getElementById('dispute-box').style.display = 'none';
            document.getElementById('btn-group').style.display = 'block';
        }
    </script>
</head>

<body>

    <nav class="navbar">
        <a href="homepage.php" class="logo">Green<span>Digital</span></a>
        <div class="user-menu">
            <a href="history.php" class="btn-logout" style="background: transparent; color: var(--secondary); border: 1px solid var(--secondary);">
                <i class="fas fa-arrow-left"></i> กลับ
            </a>
        </div>
    </nav>

    <div class="dashboard-container" style="max-width: 600px;">

        <div class="profile-container" style="text-align: center;">

            <div style="width: 80px; height: 80px; background: #e8f5e9; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2.5rem;">
                <i class="fas fa-clipboard-check"></i>
            </div>

            <h2 class="section-title" style="justify-content: center; margin-bottom: 10px; border: none;">ตรวจสอบและยืนยัน</h2>
            <p style="color: var(--text-light);">Order #<?php echo $order_id; ?> • โดย Driver <?php echo htmlspecialchars($order['driver_name']); ?></p>

            <div style="margin-top: 30px; text-align: left;">
                <h3 style="font-size: 1.1rem; color: var(--secondary); margin-bottom: 15px;">1. หลักฐานจากคนขับ (Proof of Weight)</h3>
                <?php if ($order['weighing_proof_image']): ?>
                    <img src="../assets/images/uploads/<?php echo $order['weighing_proof_image']; ?>" class="proof-img" onclick="window.open(this.src)">
                    <div style="text-align:center; font-size:0.8rem; color:#95a5a6;"><i class="fas fa-search-plus"></i> แตะเพื่อดูรูปใหญ่</div>
                <?php else: ?>
                    <div class="alert alert-danger" style="text-align:center;">
                        <i class="fas fa-exclamation-triangle"></i> ไม่พบรูปถ่ายหลักฐาน
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 30px; text-align: left;">
                <h3 style="font-size: 1.1rem; color: var(--secondary); margin-bottom: 15px;">2. รายการที่ชั่งได้ (Actual Items)</h3>
                <div style="background: var(--light-bg); border-radius: 12px; padding: 20px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <?php foreach ($items as $item): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:12px 0; color: var(--secondary); font-weight: 500;">
                                    <?php echo htmlspecialchars($item['waste_name']); ?>
                                </td>
                                <td style="padding:12px 0; text-align:right; color: var(--text-light); font-size: 0.9rem;">
                                    <?php echo $item['actual_weight']; ?> kg x ฿<?php echo $item['price_at_time']; ?>
                                </td>
                                <td style="padding:12px 0; text-align:right; font-weight:bold; color: var(--secondary);">
                                    ฿<?php echo number_format($item['subtotal'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="border-top:2px dashed #ccc;">
                            <td colspan="2" style="padding-top:20px; font-weight:bold; font-size: 1.1rem;">ยอดเงินที่ได้รับสุทธิ</td>
                            <td style="padding-top:20px; text-align:right; font-weight:800; font-size:1.8rem; color:var(--primary);">
                                ฿<?php echo number_format($order['total_amount'], 2); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <form method="POST" style="margin-top: 40px;">
                <div id="btn-group">
                    <button type="submit" name="confirm" class="btn-save" style="width: 100%; font-size: 1.2rem; padding: 15px; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);" onclick="return confirm('ยืนยันข้อมูลถูกต้อง? หลังจากนี้ Admin จะทำการตรวจสอบและโอนเงินเข้า Wallet');">
                        <i class="fas fa-check-circle"></i> ยืนยันข้อมูลถูกต้อง
                    </button>

                    <button type="button" onclick="showDispute()" style="background:none; border:none; color: #e74c3c; margin-top:20px; cursor:pointer; font-weight: 500; text-decoration: underline;">
                        ข้อมูลไม่ถูกต้อง? แย้งข้อมูล (Dispute)
                    </button>
                </div>

                <div id="dispute-box" style="display:none; text-align:center; background: #fff5f5; padding: 20px; border-radius: 12px; border: 1px solid #ffcdd2;">
                    <h4 style="color: #c0392b; margin-bottom: 10px;">แจ้งข้อมูลไม่ถูกต้อง</h4>
                    <textarea name="dispute_reason" rows="3" placeholder="ระบุเหตุผล เช่น น้ำหนักไม่ตรง, ยอดเงินผิด, รูปไม่ชัดเจน" style="width:100%; padding:15px; border:1px solid #ffcdd2; border-radius:8px; margin-bottom:15px; font-family: inherit;"></textarea>

                    <button type="submit" name="dispute" style="background: #c0392b; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold;">
                        ยืนยันการแจ้งปัญหา
                    </button>
                    <div style="margin-top: 15px;">
                        <button type="button" onclick="hideDispute()" style="background:none; border:none; color:#666; cursor:pointer;">ยกเลิก</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</body>

</html>