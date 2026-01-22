<?php
session_start();
require_once "../db_connect/db_connect.php";

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// ... (Action Logic remains same, see previous block for logic if needed, but here we keep it) ...
// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $redemption_id = $_POST['redemption_id'];

    if ($action == 'approve') {
        $conn->prepare("UPDATE reward_redemptions SET status = 'completed' WHERE id = :id")->execute([':id' => $redemption_id]);
        header("Location: redemptions.php?msg=approved");
        exit();
    } elseif ($action == 'reject') {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT user_id, points_used FROM reward_redemptions WHERE id = :id");
            $stmt->execute([':id' => $redemption_id]);
            $redemption = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($redemption) {
                $conn->prepare("UPDATE reward_redemptions SET status = 'cancelled' WHERE id = :id")->execute([':id' => $redemption_id]);

                // Refund Logic
                $uid = $redemption['user_id'];
                $points = $redemption['points_used'];
                $stmt_bal = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
                $stmt_bal->execute([':uid' => $uid]);
                $curr_bal = $stmt_bal->fetchColumn() ?: 0;
                $new_bal = $curr_bal + $points;

                $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description) VALUES (:uid, 'refund', :amt, :bal, :desc)")
                    ->execute([':uid' => $uid, ':amt' => $points, ':bal' => $new_bal, ':desc' => "คืนแต้ม (Rejected) #$redemption_id"]);

                // Stock Return
                $stmt_rid = $conn->prepare("SELECT reward_id FROM reward_redemptions WHERE id = :id");
                $stmt_rid->execute([':id' => $redemption_id]);
                $rid = $stmt_rid->fetchColumn();
                if ($rid) $conn->prepare("UPDATE rewards SET stock = stock + 1 WHERE id = :rid")->execute([':rid' => $rid]);

                $conn->commit();
                header("Location: redemptions.php?msg=rejected");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollBack();
            die("Error: " . $e->getMessage());
        }
    }
}

// Fetch Redemptions
$stmt = $conn->query("
    SELECT rr.*, u.username, r.name as reward_name, r.image as reward_image 
    FROM reward_redemptions rr
    JOIN users u ON rr.user_id = u.id
    LEFT JOIN rewards r ON rr.reward_id = r.id
    ORDER BY rr.id DESC
");
$redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการแลกของรางวัล - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .data-table {
            border-collapse: separate;
            border-spacing: 0 10px;
            /* Row spacing */
        }

        .data-table thead th {
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            color: #bdc3c7;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .data-table tbody tr {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
        }

        .data-table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .data-table td {
            padding: 20px 15px;
            vertical-align: middle;
            border-top: 1px solid #f8f9fa;
            border-bottom: 1px solid #f8f9fa;
        }

        .data-table td:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            border-left: 1px solid #f8f9fa;
        }

        .data-table td:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            border-right: 1px solid #f8f9fa;
        }

        .reward-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .reward-info img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            background: #f1f2f6;
            padding: 5px;
            border-radius: 8px;
        }

        .reward-info span {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-info {
            font-weight: 500;
            color: #34495e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .points-badge {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }

        .status-pending {
            background: #ffeaa7;
            color: #d35400;
            border: 1px solid #fdcb6e;
        }

        .status-completed {
            background: #dff9fb;
            color: #130f40;
            border: 1px solid #7ed6df;
        }

        .status-cancelled {
            background: #fab1a0;
            color: #c0392b;
            border: 1px solid #ff7675;
        }

        .action-buttons button {
            transition: all 0.2s;
            opacity: 0.9;
        }

        .action-buttons button:hover {
            opacity: 1;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>รายการแลกของรางวัล (Redemption Requests)</h2>
                <p style="color: #7f8c8d; font-size: 0.9rem;">ตรวจสอบและอนุมัติคำขอแลกแต้มของสมาชิก</p>
            </div>
        </header>

        <main class="content-wrapper">

            <?php if ($msg == 'approved'): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; display:flex; gap:10px; align-items:center;"><i class="fas fa-check-circle"></i> อนุมัติคำขอสำเร็จ</div>
            <?php elseif ($msg == 'rejected'): ?>
                <div style="background: #fad7a0; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; display:flex; gap:10px; align-items:center;"><i class="fas fa-undo"></i> ปฏิเสธและคืนแต้มสำเร็จ</div>
            <?php endif; ?>

            <div style="background: transparent;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ผู้ใช้งาน (User)</th>
                            <th>ของรางวัล (Reward)</th>
                            <th>แต้มที่ใช้</th>
                            <th>วันที่แลก</th>
                            <th style="text-align:center;">สถานะ</th>
                            <th style="text-align:center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($redemptions as $item): ?>
                            <tr>
                                <td style="color: #95a5a6; font-size: 0.9rem;">#<?php echo $item['id']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <i class="fas fa-user-circle" style="color:#bdc3c7; font-size:1.2rem;"></i>
                                        <?php echo htmlspecialchars($item['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="reward-info">
                                        <?php
                                        $img_src = "";
                                        if ($item['reward_image']) {
                                            $img_path = "../assets/images/uploads/" . $item['reward_image'];
                                            if (file_exists($img_path)) {
                                                $img_src = $img_path;
                                            } else {
                                                if ($item['reward_image'] == 'reward_bag.png') $img_src = "https://cdn-icons-png.flaticon.com/512/2829/2829824.png";
                                                elseif ($item['reward_image'] == 'reward_cup.png') $img_src = "https://cdn-icons-png.flaticon.com/512/1902/1902724.png";
                                                elseif ($item['reward_image'] == 'reward_coupon.png') $img_src = "https://cdn-icons-png.flaticon.com/512/2089/2089363.png";
                                                elseif ($item['reward_image'] == 'reward_seed.png') $img_src = "https://cdn-icons-png.flaticon.com/512/628/628324.png";
                                                else $img_src = "https://via.placeholder.com/45";
                                            }
                                        }
                                        ?>
                                        <?php if ($img_src): ?>
                                            <img src="<?php echo $img_src; ?>">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($item['reward_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="points-badge">-<?php echo number_format($item['points_used']); ?></span></td>
                                <td style="color: #7f8c8d; font-size:0.9rem;"><?php echo date('d/m/y H:i', strtotime($item['created_at'])); ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <?php if ($item['status'] == 'pending') echo '<i class="fas fa-clock"></i> รอตรวจสอบ';
                                        elseif ($item['status'] == 'completed') echo '<i class="fas fa-check"></i> สำเร็จ';
                                        else echo '<i class="fas fa-times"></i> ยกเลิก'; ?>
                                    </span>
                                </td>
                                <td style="text-align:center;" class="action-buttons">
                                    <?php if ($item['status'] == 'pending'): ?>
                                        <form method="POST" id="approve-form-<?php echo $item['id']; ?>" style="display:inline;" onsubmit="submitRedemptionAction(event, 'approve-form-<?php echo $item['id']; ?>', 'ยืนยันอนุมัติ?', 'success')">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="redemption_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" style="background:#27ae60; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;"><i class="fas fa-check"></i> อนุมัติ</button>
                                        </form>

                                        <form method="POST" id="reject-form-<?php echo $item['id']; ?>" style="display:inline; margin-left:5px;" onsubmit="submitRedemptionAction(event, 'reject-form-<?php echo $item['id']; ?>', 'ยืนยันปฏิเสธ? (แต้มจะถูกคืน)', 'warning')">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="redemption_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" style="background:white; color:#e74c3c; border:1px solid #e74c3c; padding:6px 15px; border-radius:20px; font-size:0.85rem; cursor:pointer;" onmouseover="this.style.background='#e74c3c'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#e74c3c'">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="color:#dcdcdc; font-size:1.5rem;">&bull;</span>
                                        <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function submitRedemptionAction(e, formId, title, icon) {
            e.preventDefault();
            Swal.fire({
                title: title,
                text: "ดำเนินการกับคำขอนี้",
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: icon === 'success' ? '#27ae60' : '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            })
        }
    </script>
</body>

</html>