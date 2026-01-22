<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    header("Location: orders.php");
    exit();
}

// Fetch Order & User
try {
    $stmt = $conn->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Items
    $stmt_items = $conn->prepare("SELECT oi.*, wt.name as waste_name, wt.price_per_kg as current_price FROM order_items oi JOIN waste_types wt ON oi.waste_type_id = wt.id WHERE oi.order_id = :id");
    $stmt_items->bindParam(':id', $id);
    $stmt_items->execute();
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Fetch All Drivers
    $stmt_drivers = $conn->prepare("SELECT id, username FROM users WHERE role = 'driver' AND status = 'active'");
    $stmt_drivers->execute();
    $drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$msg = "";

// Handle Form Action
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'update_status') {
        $new_status = $_POST['status'];
        header("Location: order_process.php?id=$id&msg=updated");
        exit();
    } elseif ($action == 'assign_driver') {
        $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;
        $conn->prepare("UPDATE orders SET driver_id = :did, status = 'accepted' WHERE id = :id")->execute([':did' => $driver_id, ':id' => $id]);
        header("Location: order_process.php?id=$id&msg=assigned");
        exit();
    } elseif ($action == 'complete_order') {
        // Finalize weights and pay
        $actual_weights = $_POST['weights']; // Array [item_id => weight]
        $total_weight = 0;
        $total_amount = 0;

        require_once "../services/GamificationService.php";
        $gamification = new GamificationService($conn);

        // SECURITY: Verify Status is 'user_confirmed' before paying!
        $chk_st = $conn->prepare("SELECT status FROM orders WHERE id=:id");
        $chk_st->execute([':id' => $id]);
        $current_st = $chk_st->fetchColumn();

        if ($current_st !== 'user_confirmed') {
            die("Error: User has not confirmed this order yet. Cannot process payment.");
        }

        try {
            $conn->beginTransaction();

            foreach ($actual_weights as $item_id => $weight) {
                // Fetch price
                $p_stmt = $conn->prepare("SELECT price_at_time FROM order_items WHERE id = :id");
                $p_stmt->execute([':id' => $item_id]);
                $price = $p_stmt->fetchColumn();

                $subtotal = $weight * $price;

                $conn->prepare("UPDATE order_items SET weight = :w1, actual_weight = :w2, subtotal = :s WHERE id = :id")
                    ->execute([':w1' => $weight, ':w2' => $weight, ':s' => $subtotal, ':id' => $item_id]);

                $total_weight += $weight;
                $total_amount += $subtotal;

                // --- Carbon Constraint ---
                // Fetch carbon_per_kg for this item
                $c_stmt = $conn->prepare("SELECT carbon_per_kg FROM waste_types WHERE id = (SELECT waste_type_id FROM order_items WHERE id = :id)");
                $c_stmt->execute([':id' => $item_id]);
                $c_factor = $c_stmt->fetchColumn() ?: 0.5; // Default 0.5 if null
                $carbon_saved = $weight * $c_factor;

                // Update item with carbon saved? Schema doesn't have it on item level yet, 
                // but we need to sum it for the user. We can just sum it here to a variable.
                if (!isset($total_carbon_saved)) $total_carbon_saved = 0;
                $total_carbon_saved += $carbon_saved;
            }

            // --- Gamification Bonus Logic ---
            // 1. Get User Level
            $u_stmt = $conn->prepare("SELECT membership_level FROM users WHERE id = :uid");
            $u_stmt->execute([':uid' => $order['user_id']]);
            $user_level = $u_stmt->fetchColumn() ?: 'seedling';

            // 2. Calculate Bonus
            $bonus_pct = $gamification->getBonusPercentage($user_level);
            $bonus_amount = $total_amount * $bonus_pct;
            $final_amount = $total_amount + $bonus_amount; // Add bonus to payout

            // Update Order (Save bonus somewhere? Ideally yes, but schema didn't specify 'bonus_amount' column. 
            // We can add it later or just bake into total_amount with a note, but better to be precise. 
            // For now, let's execute query with final amount and maybe add a transaction log note)

            $conn->prepare("UPDATE orders SET status = 'completed', total_weight = :tw, total_amount = :ta WHERE id = :id")
                ->execute([':tw' => $total_weight, ':ta' => $final_amount, ':id' => $id]);

            // --- Update User XP/Level & Carbon ---
            // Credit Carbon: total_carbon_saved = total_carbon_saved + new_carbon
            $conn->prepare("UPDATE users SET total_carbon_saved = total_carbon_saved + :nc WHERE id = :uid")
                ->execute([':nc' => $total_carbon_saved, ':uid' => $order['user_id']]);

            // Exp Logic
            $new_level = $gamification->addExperience($order['user_id'], $total_weight);

            // NOTIFICATION: Order Complete + Points
            $notif_xp = "INSERT INTO user_notifications (user_id, type, title, message) VALUES (:uid, 'order_complete', :title, :msg)";
            $conn->prepare($notif_xp)->execute([
                ':uid' => $order['user_id'],
                ':title' => 'คำสั่งซื้อสำเร็จ',
                ':msg' => "ขายขยะสำเร็จ " . $total_weight . " kg ได้รับคะแนน XP และช่วยลดคาร์บอน " . $total_carbon_saved . " kgCO2e"
            ]);

            // Create Wallet Transaction (ONLY IF NOT CASH)
            // Valid Payment Methods: 'transfer' (Wallet), 'cash' (Physical)
            if ($order['payment_method'] === 'transfer') {
                $bal_stmt = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
                $bal_stmt->execute([':uid' => $order['user_id']]);
                $current_bal = $bal_stmt->fetchColumn() ?: 0;

                $new_bal = $current_bal + $final_amount;

                $desc = "รายได้ Order #$id";
                if ($bonus_amount > 0) {
                    $desc .= " (รวมโบนัส " . ucfirst($user_level) . " +" . number_format($bonus_amount, 2) . ")";
                }

                $sql_trans = "INSERT INTO wallet_transactions (user_id, order_id, type, amount, balance_after, description) VALUES (:uid, :oid, 'income', :amt, :bal, :desc)";
                $conn->prepare($sql_trans)->execute([
                    ':uid' => $order['user_id'],
                    ':oid' => $id,
                    ':amt' => $final_amount,
                    ':bal' => $new_bal,
                    ':desc' => $desc
                ]);

                // NOTIFICATION: Money Deposit
                $notif_money = "INSERT INTO user_notifications (user_id, type, title, message) VALUES (:uid, 'deposit', :title, :msg)";
                $conn->prepare($notif_money)->execute([
                    ':uid' => $order['user_id'],
                    ':title' => 'เงินเข้าวอลเล็ท',
                    ':msg' => "ได้รับเงิน ฿" . number_format($final_amount, 2) . " จาก Order #$id"
                ]);
            }

            $conn->commit();
            header("Location: orders.php?filter=completed");
            exit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            die("❌ SYSTEM ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ดำเนินการ Order #<?php echo $id; ?> - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>คำขอรับขยะ #<?php echo $id; ?></h2>
            </div>
            <div class="header-tools">
                <a href="orders.php" style="color: var(--text-light);"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            </div>
        </header>

        <main class="content-wrapper">

            <!-- Order Info -->
            <div style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                    <div>
                        <h3 style="color: var(--secondary);">ผู้แจ้ง: <?php echo htmlspecialchars($order['username']); ?></h3>
                        <p style="color: #666;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['pickup_address']); ?></p>
                        <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $order['latitude']; ?>,<?php echo $order['longitude']; ?>" target="_blank" style="display: inline-block; margin-top: 5px; color: var(--primary); font-size: 0.9rem; text-decoration: none;">
                                <i class="fas fa-map-marked-alt"></i> ดูตำแหน่งบนแผนที่
                            </a>
                        <?php endif; ?>
                        <p style="color: #666; margin-top: 5px;"><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($order['pickup_date'])); ?> <?php echo date('H:i', strtotime($order['pickup_time'])); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 1.2rem; font-weight: 600; color: var(--primary);"><?php echo ucfirst($order['status']); ?></span>

                        <?php if (!empty($order['request_image'])): ?>
                            <div style="margin-top: 15px;">
                                <img src="../assets/images/uploads/<?php echo $order['request_image']; ?>"
                                    style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; cursor: pointer;"
                                    onclick="window.open(this.src, '_blank')">
                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">รูปภาพกองขยะ</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                    <form method="POST" style="margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1rem;">
                        <input type="hidden" name="action" value="update_status">
                        <label>เปลี่ยนสถานะ (Admin Override):</label>
                        <select name="status" style="padding: 0.5rem; border-radius: 4px;">
                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending (รอดำเนินการ)</option>
                            <option value="accepted" <?php echo $order['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted (กำลังไปรับ)</option>
                            <option value="waiting_confirm" <?php echo $order['status'] == 'waiting_confirm' ? 'selected' : ''; ?>>Waiting Confirm (รอการยืนยัน)</option>
                            <option value="user_confirmed" <?php echo $order['status'] == 'user_confirmed' ? 'selected' : ''; ?>>User Confirmed (ยืนยันแล้ว)</option>
                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled (ยกเลิก)</option>
                        </select>
                        <button type="submit" style="background: #95a5a6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px;">บันทึก</button>
                    </form>
                <?php endif; ?>

                <!-- Assign Driver -->
                <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                    <form method="POST" style="margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1rem;">
                        <input type="hidden" name="action" value="assign_driver">
                        <label><i class="fas fa-truck"></i> มอบหมายคนขับ (Assign Driver):</label>
                        <div style="display:flex; gap:10px; margin-top:5px;">
                            <select name="driver_id" class="form-control" style="flex:1;">
                                <option value="">-- เลือกคนขับ --</option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $order['driver_id'] == $d['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-primary" style="white-space:nowrap;">
                                บันทึก & รับงาน
                            </button>
                        </div>
                        <small style="color:#666;">* การเลือกคนขับจะเปลี่ยนสถานะเป็น Accepted อัตโนมัติ</small>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Items & Completion -->
            <?php if (in_array($order['status'], ['accepted', 'completed', 'waiting_confirm', 'user_confirmed'])): ?>
                <div style="background: white; padding: 1.5rem; border-radius: 12px;">
                    <h3>รายการขยะ & คำนวณยอดเงิน</h3>

                    <form method="POST">
                        <input type="hidden" name="action" value="complete_order">

                        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                            <thead>
                                <tr style="background: #f9f9f9;">
                                    <th style="padding: 10px; text-align: left;">ประเภท</th>
                                    <th style="padding: 10px; text-align: right;">ราคา/กก.</th>
                                    <th style="padding: 10px; text-align: center;">น้ำหนักที่แจ้ง (กก.)</th>
                                    <th style="padding: 10px; text-align: center;">น้ำหนักจริง (กก.)</th>
                                    <th style="padding: 10px; text-align: right;">รวมเป็นเงิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 10px;"><?php echo $item['waste_name']; ?></td>
                                        <td style="padding: 10px; text-align: right;"><?php echo number_format($item['price_at_time'], 2); ?></td>
                                        <td style="padding: 10px; text-align: center; color: #888;"><?php echo number_format($item['weight'], 1); ?></td>
                                        <td style="padding: 10px; text-align: center;">
                                            <?php if ($order['status'] == 'completed'): ?>
                                                <span style="columns: var(--primary); font-weight: bold;">
                                                    <?php echo number_format(($item['actual_weight'] > 0) ? $item['actual_weight'] : $item['weight'], 1); ?>
                                                </span>
                                            <?php else: ?>
                                                <input type="number" step="0.1" name="weights[<?php echo $item['id']; ?>]" value="<?php echo ($item['actual_weight'] > 0) ? $item['actual_weight'] : $item['weight']; ?>" style="width: 80px; text-align: center; padding: 5px; border: 1px solid #ddd; border-radius: 4px; font-weight: bold; color: var(--primary);">
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px; text-align: right; font-weight: 600;">
                                            <?php echo number_format($item['subtotal'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (in_array($order['status'], ['completed', 'user_confirmed'])): ?>
                                <tfoot>
                                    <tr style="background: #e8f5e9;">
                                        <td colspan="3" style="padding: 15px; text-align: right; font-weight: bold;">สุทธิ:</td>
                                        <td style="padding: 15px; text-align: center; font-weight: bold;"><?php echo number_format($order['total_weight'], 1); ?> kg</td>
                                        <td style="padding: 15px; text-align: right; font-weight: bold; color: var(--primary); font-size: 1.2rem;">฿<?php echo number_format($order['total_amount'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>

                        <?php if ($order['status'] == 'user_confirmed'): ?>
                            <div style="margin-top: 2rem; padding: 20px; background: #e8f8f5; border: 2px solid #2ecc71; border-radius: 12px; text-align: center;">
                                <h3 style="color: #27ae60; margin-top: 0;"><i class="fas fa-check-circle"></i> ลูกค้ายืนยันความถูกต้องแล้ว</h3>

                                <?php if ($order['weighing_proof_image']): ?>
                                    <div style="margin: 15px 0;">
                                        <p style="margin-bottom: 5px; font-weight: bold;">ภาพหลักฐานการชั่ง (จากคนขับ)</p>
                                        <img src="../assets/images/uploads/<?php echo $order['weighing_proof_image']; ?>" style="max-height: 200px; border-radius: 8px; border: 1px solid #ddd; cursor: pointer;" onclick="window.open(this.src)">
                                    </div>
                                <?php endif; ?>

                                <p style="color: #555; margin-bottom: 20px;">กรุณาตรวจสอบก่อนกดอนุมัติ ยอดเงินจะเข้า Wallet ของลูกค้าทันที</p>

                                <button type="submit" id="btn-complete" onclick="confirmComplete(event)" style="background: #27ae60; color: white; border: none; padding: 1rem 3rem; border-radius: 50px; font-size: 1.2rem; cursor: pointer; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4); transition: all 0.3s;">
                                    <i class="fas fa-hand-holding-usd"></i> อนุมัติการจ่ายเงิน (Approve Payment)
                                </button>
                            </div>
                        <?php elseif ($order['status'] == 'waiting_confirm'): ?>
                            <div style="margin-top: 2rem; padding: 20px; background: #fff3cd; border: 2px solid #f1c40f; border-radius: 12px; text-align: center;">
                                <h3 style="color: #d35400; margin-top: 0;"><i class="fas fa-clock"></i> รอลูกค้ายืนยัน (Waiting for User to Confirm)</h3>

                                <?php if ($order['weighing_proof_image']): ?>
                                    <div style="margin: 15px 0;">
                                        <p style="margin-bottom: 5px; font-weight: bold;">คนขับส่งหลักฐานแล้ว (Driver Submitted Proof)</p>
                                        <img src="../assets/images/uploads/<?php echo $order['weighing_proof_image']; ?>" style="max-height: 200px; border-radius: 8px; border: 1px solid #ddd; cursor: pointer;" onclick="window.open(this.src)">
                                    </div>
                                <?php endif; ?>

                                <p style="color: #7f8c8d;">ระบบกำลังรอลูกค้ากด "ยืนยันความถูกต้อง" ในแอปของลูกค้า<br>เมื่อลูกค้ายืนยันแล้ว ปุ่มอนุมัติการจ่ายเงินจะปรากฏขึ้น</p>
                            </div>
                        <?php elseif ($order['status'] == 'accepted'): ?>
                            <div style="margin-top:2rem; text-align:center; color:#e67e22; background:#fff3cd; padding:15px; border-radius:8px;">
                                <i class="fas fa-exclamation-circle"></i> รอคนขับส่งงานให้ลูกค้าตรวจสอบ
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        // --- Real-time Status Check ---
        const currentStatus = "<?php echo $order['status']; ?>";
        const orderId = "<?php echo $id; ?>";

        // Only poll if we are waiting for something to happen
        if (currentStatus === 'waiting_confirm' || currentStatus === 'user_confirmed') {
            setInterval(() => {
                fetch(`../api/check_status.php?id=${orderId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status && data.status !== currentStatus) {
                            location.reload();
                        }
                    })
                    .catch(e => console.error("Polling error:", e));
            }, 5000); // Check every 5s
        }
    </script>
    <script>
        // Calculate Total on Load (Optional)
        // ... existing scripts usually go here or auto-calc based on inputs ...

        function confirmComplete(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ยืนยันการทำรายการ?',
                text: "ระบบจะทำการโอนเงินเข้า Wallet ของลูกค้าทันทีและปิดงานนี้",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยันโอนเงิน',
                cancelButtonText: 'ตรวจสอบก่อน'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the specific form wrapping this button. 
                    // Since the button is inside the form, we can find closest form.
                    e.target.closest('form').submit();
                }
            })
        }
    </script>
</body>

</html>