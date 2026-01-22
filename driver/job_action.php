<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$msg = "";
$msg_type = "";

if (!$order_id) {
    header("Location: index.php");
    exit();
}

// Fetch Order Info
try {
    $stmt = $conn->prepare("SELECT o.*, u.username, u.profile_image 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            WHERE o.id = :id AND o.driver_id = :did");
    $stmt->execute([':id' => $order_id, ':did' => $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or not assigned to you.");
    }

    // Fetch Items
    $stmt_items = $conn->prepare("SELECT oi.*, wt.name as waste_name, wt.image as waste_image 
                                  FROM order_items oi 
                                  JOIN waste_types wt ON oi.waste_type_id = wt.id 
                                  WHERE oi.order_id = :id");
    $stmt_items->execute([':id' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    // Fetch All Waste Types for "Add Item" feature
    $stmt_types = $conn->query("SELECT * FROM waste_types");
    $waste_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if POST is empty (File Upload Limit Exceeded)
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        die("<div style='padding:20px; color:red; text-align:center;'>
                <h1>❌ Error: File Too Large!</h1>
                <p>รูปภาพที่คุณอัปโหลดมีขนาดใหญ่เกินไป (Max: $max_size)</p>
                <p>กรุณาลดขนาดรูปภาพ หรือถ่ายใหม่ความละเอียดต่ำลง แล้วลองส่งอีกครั้ง</p>
                <button onclick='history.back()'>กลับไปแก้ไข</button>
             </div>");
    }

    if (isset($_POST['cancel_job'])) {
        try {
            $reason = $_POST['cancel_reason'];
            $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $order_id]);

            // Notify Admin
            $conn->prepare("INSERT INTO admin_notifications (type, message, related_id, created_at) VALUES ('order', :msg, :oid, NOW())")
                ->execute([
                    ':msg' => "Order #$order_id Cancelled by Driver. Reason: $reason",
                    ':oid' => $order_id
                ]);

            header("Location: index.php?msg=cancelled");
            exit();
        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }
}

// Handle Form Submission (Finish Job โดย Driver)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finish_job'])) {

    // 1. รับค่าและจัดการไฟล์
    $actual_weights = $_POST['actual_weight'] ?? [];
    $new_items = $_POST['new_items'] ?? [];
    $payment_proof = null;

    if (isset($_FILES['weighing_proof']) && $_FILES['weighing_proof']['error'] == 0) {
        $ext = pathinfo($_FILES['weighing_proof']['name'], PATHINFO_EXTENSION);
        $new_name = "proof_" . $order_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['weighing_proof']['tmp_name'], "../assets/images/uploads/" . $new_name)) {
            $payment_proof = $new_name;
        }
    }

    try {
        $conn->beginTransaction();

        require_once "../services/GamificationService.php";
        $gamification = new GamificationService($conn);

        $grand_total_weight = 0;
        $grand_total_amount = 0;

        // 2. อัปเดตรายการเดิม/ลบรายการที่ Driver ไม่เอา
        foreach ($items as $item) {
            if (isset($actual_weights[$item['id']])) {
                $w = floatval($actual_weights[$item['id']]);
                $price = $item['price_at_time'];
                $subtotal = $w * $price;
                $grand_total_weight += $w;
                $grand_total_amount += $subtotal;

                $conn->prepare("UPDATE order_items SET actual_weight = :w, subtotal = :s WHERE id = :id")
                    ->execute([':w' => $w, ':s' => $subtotal, ':id' => $item['id']]);
            } else {
                $conn->prepare("DELETE FROM order_items WHERE id = :id")->execute([':id' => $item['id']]);
            }
        }

        // 3. เพิ่มรายการใหม่ (ถ้ามี)
        if (!empty($new_items)) {
            $stmt_insert = $conn->prepare("INSERT INTO order_items (order_id, waste_type_id, weight, actual_weight, price_at_time, subtotal) VALUES (:oid, :type, :w_est, :w_act, :price, :sub)");
            foreach ($new_items as $ni) {
                $w = floatval($ni['weight']);
                $p = floatval($ni['price']);
                $sub = $w * $p;
                $grand_total_weight += $w;
                $grand_total_amount += $sub;
                $stmt_insert->execute([
                    ':oid' => $order_id,
                    ':type' => $ni['type_id'],
                    ':w_est' => $w,
                    ':w_act' => $w, // Set actual same as checked weight for new items
                    ':price' => $p,
                    ':sub' => $sub
                ]);
            }
        }

        // 4. คำนวณ Bonus
        $u_stmt = $conn->prepare("SELECT membership_level FROM users WHERE id = :uid");
        $u_stmt->execute([':uid' => $order['user_id']]);
        $user_level = $u_stmt->fetchColumn() ?: 'seedling';
        $bonus_pct = $gamification->getBonusPercentage($user_level);
        $final_payout = $grand_total_amount + ($grand_total_amount * $bonus_pct);

        // 5. เปลี่ยนสถานะเป็น waiting_confirm (ส่งให้ User ตรวจสอบ)
        // 5. เปลี่ยนสถานะเป็น waiting_confirm (ส่งให้ User ตรวจสอบ)
        $sql_order = "UPDATE orders SET 
                      status = 'waiting_confirm', 
                      total_weight = :tw, 
                      total_amount = :ta";

        // Only update image if new one uploaded
        $params = [
            ':tw' => $grand_total_weight,
            ':ta' => $final_payout,
            ':id' => $order_id
        ];

        if ($payment_proof) {
            $sql_order .= ", weighing_proof_image = :wpi";
            $params[':wpi'] = $payment_proof;
        }

        $sql_order .= " WHERE id = :id";

        $conn->prepare($sql_order)->execute($params);

        $conn->commit();
        header("Location: index.php?msg=submitted");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
} // ปิด if POST

?>



<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Job Action - Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="../assets/css/driver.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Responsive Fixes */
        body {
            background-color: var(--bg-color);
            padding-bottom: 80px;
            /* Space for fixed buttons/nav */
        }

        .main-container {
            max-width: 800px;
            /* Limit width on desktop */
            margin: 0 auto;
            padding: 15px;
        }

        .driver-header {
            position: sticky;
            top: 0;
            z-index: 99;
            border-radius: 0 0 20px 20px;
            margin-bottom: 10px;
        }

        .job-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 20px 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--primary);
        }

        /* Improved Item Row */
        .item-row {
            border-left: 4px solid var(--primary);
            transition: transform 0.2s;
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #eee;
        }

        .input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .input-wrapper {
            flex: 1;
        }

        .input-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 4px;
            display: block;
        }

        .custom-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
            transition: all 0.3s;
        }

        .custom-input:focus {
            border-color: var(--primary);
            outline: none;
            background: #f0fdf4;
        }

        .subtotal-box {
            text-align: right;
            min-width: 100px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 10px;
        }

        /* Large Action Buttons */
        .btn-large {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-large:active {
            transform: scale(0.98);
        }

        /* Modal Enhancements */
        .modal-content {
            background: white;
            width: 90%;
            max-width: 450px;
            border-radius: 20px;
            overflow: hidden;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .waste-list-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background 0.2s;
        }

        .waste-list-item:active {
            background: #f0fdf4;
        }

        /* Total Section Sticky Bottom (Mobile Friendly) */
        .summary-card {
            background: linear-gradient(135deg, var(--primary), #27ae60);
            color: white;
            text-align: center;
        }
    </style>

    <script>
        function calculateTotal() {
            let total = 0;
            const items = document.querySelectorAll('.item-row');
            items.forEach(row => {
                const weight = parseFloat(row.querySelector('.input-weight').value) || 0;
                const price = parseFloat(row.dataset.price);
                const subtotal = weight * price;
                row.querySelector('.subtotal-display').innerText = subtotal.toFixed(2);
                total += subtotal;
            });

            // Apply Bonus
            const bonusPercent = parseFloat(document.getElementById('bonus-percent').value) || 0;
            const bonusAmount = total * bonusPercent;
            const grandTotal = total + bonusAmount;

            document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
            document.getElementById('bonus-display').innerText = bonusAmount.toFixed(2);
            document.getElementById('total-initial').innerText = total.toFixed(2);
            document.getElementById('hidden-total-amount').value = grandTotal.toFixed(2);
        }

        let newItemIndex = 0;

        function deleteItem(btn) {
            if (confirm('ต้องการลบรายการนี้ใช่หรือไม่?')) {
                const row = btn.closest('.item-row');
                row.remove();
                calculateTotal();
            }
        }

        function openAddModal() {
            document.getElementById('add-item-modal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('add-item-modal').style.display = 'none';
        }

        function addNewItem(id, name, price, image) {
            const container = document.getElementById('items-container');
            const div = document.createElement('div');
            div.className = 'job-card item-row';
            div.dataset.price = price;

            const imgPath = image ? `../assets/images/uploads/${image}` : 'https://via.placeholder.com/60';

            div.innerHTML = `
                <div class="item-details">
                    <img src="${imgPath}" class="item-image">
                    <div style="flex:1;">
                        <h4 style="margin:0; font-size:1rem; color:var(--text-dark);">${name}</h4>
                        <span style="font-size:0.8rem; color:var(--primary); font-weight:600;"><i class="fas fa-plus-circle"></i> รายการเพิ่มใหม่</span>
                        <div style="font-size:0.9rem; color:#666; margin-top:2px;">ราคา: ${parseFloat(price).toFixed(2)} ฿/kg</div>
                    </div>
                    <button type="button" onclick="deleteItem(this)" style="background:rgba(231, 76, 60, 0.1); color:var(--danger); width:35px; height:35px; border-radius:50%; border:none;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                
                <div class="input-group">
                    <div class="input-wrapper">
                        <label class="input-label">น้ำหนัก (kg)</label>
                        <input type="hidden" name="new_items[${newItemIndex}][type_id]" value="${id}">
                        <input type="hidden" name="new_items[${newItemIndex}][price]" value="${price}">
                        <input type="number" step="0.1" name="new_items[${newItemIndex}][weight]" class="input-weight custom-input"
                            placeholder="0.0" oninput="calculateTotal()" required>
                    </div>
                    <div class="subtotal-box">
                        <label class="input-label" style="text-align:right;">รวม (บาท)</label>
                        <div style="font-weight:bold; color:var(--primary); font-size:1.1rem;">
                            <span class="subtotal-display">0.00</span> ฿
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(div);
            newItemIndex++;
            closeAddModal();
        }

        function previewProof(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('proof-preview').src = e.target.result;
                    document.getElementById('proof-preview').style.display = 'block';
                    document.getElementById('upload-placeholder').style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</head>

<body>

    <header class="driver-header">
        <div class="header-top">
            <a href="index.php" style="color:white; font-size:1.2rem;"><i class="fas fa-chevron-left"></i> กลับ</a>
            <div style="font-weight:600;">Order #<?php echo $order_id; ?></div>
            <button onclick="openCancelModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; padding:5px 10px; border-radius:15px; font-size:0.8rem;">
                <i class="fas fa-times"></i> ยกเลิก
            </button>
        </div>
    </header>

    <div class="main-container">

        <!-- Customer Profile -->
        <div class="job-card" style="display:flex; align-items:center; gap:15px; padding:15px;">
            <img src="../assets/images/uploads/<?php echo $order['profile_image']; ?>"
                style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--primary);">
            <div>
                <h3 style="margin:0; font-size:1.1rem;"><?php echo htmlspecialchars($order['username']); ?></h3>
                <span style="font-size:0.9rem; color:#666;">ลูกค้า (Customer)</span>
            </div>
            <div style="margin-left:auto;">
                <!-- Phone icon strictly visually here, functionality in index -->
                <i class="fas fa-user-check" style="color:var(--primary); font-size:1.5rem;"></i>
            </div>
        </div>

        <?php if (in_array($order['status'], ['waiting_confirm', 'user_confirmed', 'completed'])): ?>
            <!-- READ ONLY SUCCESS STATE -->
            <div class="job-card" style="text-align: center; padding: 40px 20px;">
                <div style="width:80px; height:80px; background:#d4edda; color:#155724; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <i class="fas fa-check" style="font-size: 3rem;"></i>
                </div>
                <h2 style="color: var(--secondary); margin-bottom:10px;">ส่งข้อมูลเรียบร้อย</h2>

                <?php if ($order['status'] == 'completed'): ?>
                    <p style="color:#27ae60;">งานเสร็จสมบูรณ์</p>
                <?php elseif ($order['status'] == 'user_confirmed'): ?>
                    <p style="color:#2980b9;">รอดำเนินการชำระเงิน</p>
                <?php else: ?>
                    <p style="color:#f39c12;">รอลูกค้ากดยืนยันในแอป</p>
                <?php endif; ?>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: left;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-weight:bold; color:#666;">
                        <span>น้ำหนักรวม</span>
                        <span><?php echo $order['total_weight']; ?> kg</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:bold; color:var(--primary);">
                        <span>ยอดสุทธิ</span>
                        <span>฿<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <a href="index.php" class="btn-large" style="background:var(--primary); color:white; text-decoration:none;">
                    กลับหน้าหลัก
                </a>
            </div>

        <?php else: ?>
            <!-- ACTIVE FORM -->

            <?php if (!empty($order['request_image'])): ?>
                <div class="job-card" style="padding:10px; cursor:pointer;" onclick="window.open('../assets/images/uploads/<?php echo $order['request_image']; ?>', '_blank')">
                    <div style="border-radius:12px; overflow:hidden; position:relative; height:180px;">
                        <img src="../assets/images/uploads/<?php echo $order['request_image']; ?>" style="width:100%; height:100%; object-fit:cover;">
                        <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.5); color:white; padding:8px 15px; font-size:0.9rem;">
                            <i class="fas fa-camera"></i> รูปจากลูกค้า
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="jobForm">

                <h3 class="section-title"><i class="fas fa-balance-scale"></i> 1. ลงรายการขยะ</h3>

                <div id="items-container">
                    <?php foreach ($items as $item): ?>
                        <div class="job-card item-row" data-price="<?php echo $item['price_at_time']; ?>">
                            <div class="item-details">
                                <img src="<?php echo ($item['waste_image']) ? '../assets/images/uploads/' . $item['waste_image'] : 'https://via.placeholder.com/60'; ?>" class="item-image">
                                <div style="flex:1;">
                                    <h4 style="margin:0; font-size:1rem; color:var(--text-dark);"><?php echo $item['waste_name']; ?></h4>
                                    <div style="font-size:0.9rem; color:#666; margin-top:2px;">ราคา: <?php echo number_format($item['price_at_time'], 2); ?> ฿/kg</div>
                                </div>
                                <button type="button" onclick="deleteItem(this)" style="background:rgba(231, 76, 60, 0.1); color:var(--danger); width:35px; height:35px; border-radius:50%; border:none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>

                            <div class="input-group">
                                <div class="input-wrapper">
                                    <label class="input-label">น้ำหนัก (แจ้ง: <?php echo $item['weight']; ?>)</label>
                                    <input type="number" step="0.1" name="actual_weight[<?php echo $item['id']; ?>]" class="input-weight custom-input"
                                        value="<?php echo ($item['actual_weight'] > 0) ? $item['actual_weight'] : $item['weight']; ?>"
                                        oninput="calculateTotal()" required>
                                </div>
                                <div class="subtotal-box">
                                    <label class="input-label" style="text-align:right;">รวม (บาท)</label>
                                    <div style="font-weight:bold; color:var(--primary); font-size:1.1rem;">
                                        <span class="subtotal-display"><?php echo number_format($item['weight'] * $item['price_at_time'], 2); ?></span> ฿
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="openAddModal()" class="btn-large" style="background:white; border:2px dashed var(--primary); color:var(--primary); margin-bottom:25px;">
                    <i class="fas fa-plus-circle"></i> เพิ่มรายการ
                </button>

                <!-- Total Summary -->
                <?php
                // Recalculate bonus logic from earlier
                require_once "../services/GamificationService.php";
                $gamification_view = new GamificationService($conn);
                $lvl_stmt = $conn->prepare("SELECT membership_level FROM users WHERE id = :uid");
                $lvl_stmt->execute([':uid' => $order['user_id']]);
                $u_lvl = $lvl_stmt->fetchColumn() ?: 'seedling';
                $bonus_p = $gamification_view->getBonusPercentage($u_lvl);
                $badge = $gamification_view->getBadgeDetails($u_lvl);
                ?>
                <input type="hidden" id="bonus-percent" value="<?php echo $bonus_p; ?>">

                <div class="job-card summary-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; opacity:0.9;">
                        <span><i class="<?php echo $badge['icon']; ?>"></i> <?php echo $badge['name']; ?> (Bonus +<?php echo $bonus_p * 100; ?>%)</span>
                        <span style="font-size:0.9rem;">ยอดก่อนโบนัส: <span id="total-initial">0.00</span></span>
                    </div>
                    <div style="font-size:3rem; font-weight:700; line-height:1; margin:10px 0;">
                        ฿<span id="grand-total">0.00</span>
                    </div>
                    <div style="font-size:0.9rem; background:rgba(255,255,255,0.2); display:inline-block; padding:3px 10px; border-radius:20px;">
                        โบนัส: +<span id="bonus-display">0.00</span> บาท
                    </div>
                    <input type="hidden" id="hidden-total-amount" name="total_amount">
                </div>

                <h3 class="section-title"><i class="fas fa-camera"></i> 2. หลักฐานการชั่ง</h3>
                <div class="job-card">
                    <input type="file" name="weighing_proof" id="proof-input" accept="image/*" style="display:none;" onchange="previewProof(this)" required capture="environment">
                    <div onclick="document.getElementById('proof-input').click()"
                        style="border:2px dashed #bdc3c7; border-radius:12px; min-height:150px; display:flex; align-items:center; justify-content:center; cursor:pointer; background:#f9f9f9; position:relative;">

                        <div id="upload-placeholder" style="text-align:center; color:#7f8c8d;">
                            <i class="fas fa-camera" style="font-size:2rem; margin-bottom:10px;"></i>
                            <div>คลิกเพื่อถ่ายรูปตราชั่ง</div>
                            <div style="font-size:0.8rem; color:var(--danger); margin-top:5px;">* จำเป็นต้องแนบ</div>
                        </div>
                        <img id="proof-preview" style="width:100%; max-height:300px; object-fit:contain; border-radius:12px; display:none;">

                        <div style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.6); color:white; padding:5px 10px; border-radius:20px; font-size:0.8rem;">
                            <i class="fas fa-edit"></i> เปลี่ยนรูป
                        </div>
                    </div>
                </div>

                <div class="job-card" style="background:#eef9fd; border:none;">
                    <p style="margin:0; font-size:0.9rem; color:#0c5460; display:flex; gap:10px;">
                        <i class="fas fa-info-circle" style="margin-top:3px;"></i>
                        <span>ลูกค้าต้องกด <b>"ยืนยัน"</b> ในแอปของลูกค้าเอง ยอดเงินถึงจะเข้าสู่ระบบ Wallet</span>
                    </p>
                </div>

                <div style="position:fixed; bottom:0; left:0; width:100%; background:white; padding:15px; box-shadow:0 -5px 15px rgba(0,0,0,0.05); z-index:100;">
                    <div style="max-width:800px; margin:0 auto;">
                        <button type="submit" name="finish_job" class="btn-large" style="background:var(--primary); color:white; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);">
                            <i class="fas fa-paper-plane"></i> ส่งงาน (Submit)
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <!-- Modals -->
    <div id="add-item-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:flex-end; padding-bottom:0;">
        <div class="modal-content" style="border-radius:20px 20px 0 0; width:100%; max-width:100%; animation: slideUp 0.3s ease-out;">
            <div class="modal-header">
                <h3 style="margin:0;">เลือกประเภทขยะ</h3>
                <button onclick="closeAddModal()" style="border:none; background:none; font-size:1.5rem;"><i class="fas fa-times"></i></button>
            </div>
            <div style="overflow-y:auto; max-height:60vh; padding-bottom:20px;">
                <?php foreach ($waste_types as $type): ?>
                    <?php
                    $safe_name = addslashes(htmlspecialchars($type['name']));
                    $price = isset($type['pickup_price_per_kg']) && $type['pickup_price_per_kg'] > 0
                        ? $type['pickup_price_per_kg']
                        : ($type['price_per_kg'] * 0.8);
                    ?>
                    <div class="waste-list-item" onclick="addNewItem('<?php echo $type['id']; ?>', '<?php echo $safe_name; ?>', '<?php echo $price; ?>', '<?php echo $type['image']; ?>')">
                        <img src="../assets/images/uploads/<?php echo $type['image']; ?>" style="width:45px; height:45px; border-radius:10px; object-fit:cover;">
                        <div style="flex:1;">
                            <div style="font-weight:600; color:var(--text-dark);"><?php echo htmlspecialchars($type['name']); ?></div>
                            <div style="font-size:0.9rem; color:var(--primary); font-weight:600;"><?php echo number_format($price, 2); ?> ฿/kg</div>
                        </div>
                        <i class="fas fa-plus-circle" style="color:#bdc3c7; font-size:1.2rem;"></i>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="cancel-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; padding:20px;">
        <div class="job-card" style="width:100%; max-width:350px; text-align:center;">
            <div style="width:60px; height:60px; background:#fcecea; color:#e74c3c; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                <i class="fas fa-exclamation-triangle" style="font-size:1.5rem;"></i>
            </div>
            <h3 style="margin:0 0 10px;">ยกเลิกงานนี้?</h3>
            <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">การยกเลิกจะมีผลทันทีและไม่สามารถเรียกคืนได้</p>
            <form method="POST">
                <textarea name="cancel_reason" rows="3" placeholder="ระบุเหตุผลที่ยกเลิก..." style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;" required></textarea>
                <button type="submit" name="cancel_job" class="btn-large" style="background:#e74c3c; color:white; padding:12px;">ยืนยันการยกเลิก</button>
                <button type="button" onclick="closeCancelModal()" style="background:none; border:none; color:#666; margin-top:15px; width:100%; cursor:pointer;">ปิดหน้าต่าง</button>
            </form>
        </div>
    </div>

    <script>
        function openCancelModal() {
            document.getElementById('cancel-modal').style.display = 'flex';
        }

        function closeCancelModal() {
            document.getElementById('cancel-modal').style.display = 'none';
        }

        // Initialize Calculation on Load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });

        // Animation for Mobile Modal
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);

        // Polling (Simplified)
        const orderId = "<?php echo $order_id; ?>";
        const currentStatus = "<?php echo $job['status']; ?>";

        setInterval(() => {
            // Optional: Keep simple check or just rely on user refresh to save battery/data
        }, 10000);
    </script>
</body>

</html>