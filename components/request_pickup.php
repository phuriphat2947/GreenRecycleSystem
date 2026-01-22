<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch Waste Types
try {
    $stmt = $conn->query("SELECT * FROM waste_types ORDER BY category DESC, id ASC");
    $waste_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch User Status for Gatekeeping
try {
    $stmt = $conn->prepare("SELECT kyc_status, address, phone FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $kyc_status = $user_data['kyc_status'] ?? 'unverified';
    $user_addr = $user_data['address'];
    $user_phone = $user_data['phone'];

    if ($kyc_status != 'verified') {
        echo "<script>alert('กรุณายืนยันตัวตน (KYC) ให้เรียบร้อยก่อนทำการเรียกรถ'); window.location='profile.php';</script>";
        exit();
    }

    if (empty($user_phone)) {
        echo "<script>alert('กรุณาระบุเบอร์โทรศัพท์ในหน้าโปรไฟล์ เพื่อให้เจ้าหน้าที่ติดต่อรับของได้'); window.location='profile.php';</script>";
        exit();
    }
} catch (PDOException $e) { /* Ignore */
}


// Handle Form Submission
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pickup_date = $_POST['pickup_date'];
    $pickup_time = $_POST['pickup_time'];
    $address = trim($_POST['address']);
    $lat = isset($_POST['lat']) ? $_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? $_POST['lng'] : null;
    $order_type = isset($_POST['order_type']) ? $_POST['order_type'] : 'pickup';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    $selected_items = isset($_POST['items']) ? $_POST['items'] : []; // Array of weights keyed by waste_type_id

    // Walk-in doesn't need address address or GPS? Actually schema requires, but maybe we can put dummy or shop address.
    // For now, if walkin, address might be empty or default.
    if ($order_type == 'walkin') {
        $address = "GreenDigital HQ (Walk-in)";
    }

    if (empty($pickup_date) || empty($pickup_time)) {
        $msg = "กรุณากรอกวันที่และเวลาให้ครบถ้วน";
    } elseif ($order_type == 'pickup' && empty($address)) {
        $msg = "กรุณาระบุสถานที่รับขยะ";
    } elseif (count($selected_items) == 0) {
        $msg = "กรุณาระบุประเภทขยะและน้ำหนักอย่างน้อย 1 รายการ";
    } elseif (!isset($_FILES['request_image']) || $_FILES['request_image']['error'] != 0) {
        $msg = "กรุณาถ่ายรูปขยะที่จะขาย";
    } else {
        try {
            // Upload Image
            $image_name = "";
            if (isset($_FILES['request_image'])) {
                $ext = pathinfo($_FILES['request_image']['name'], PATHINFO_EXTENSION);
                $image_name = "req_" . time() . "_" . $_SESSION['user_id'] . "." . $ext;
                move_uploaded_file($_FILES['request_image']['tmp_name'], "../assets/images/uploads/" . $image_name);
            }

            $conn->beginTransaction();

            // 1. Create Order
            $sql = "INSERT INTO orders (user_id, status, pickup_date, pickup_time, pickup_address, latitude, longitude, order_type, payment_method, request_image) 
                    VALUES (:uid, 'pending', :date, :time, :addr, :lat, :lng, :type, :pm, :img)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':uid', $_SESSION['user_id']);
            $stmt->bindParam(':date', $pickup_date);
            $stmt->bindParam(':time', $pickup_time);
            $stmt->bindParam(':addr', $address);
            $stmt->bindParam(':lat', $lat);
            $stmt->bindParam(':lng', $lng);
            $stmt->bindParam(':type', $order_type);
            $stmt->bindParam(':pm', $payment_method);
            $stmt->bindParam(':img', $image_name);
            $stmt->execute();
            $order_id = $conn->lastInsertId();

            // 2. Insert Items
            $has_items = false;
            foreach ($selected_items as $type_id => $weight) {
                if ($weight > 0) {
                    $has_items = true;
                    // Get current price based on Type
                    // Get current price based on Type
                    $p_stmt = $conn->prepare("SELECT price_per_kg, pickup_price_per_kg FROM waste_types WHERE id = :id");
                    $p_stmt->bindParam(':id', $type_id);
                    $p_stmt->execute();
                    $prices = $p_stmt->fetch(PDO::FETCH_ASSOC);

                    // Logic: Walk-in uses 'Standard Price', Pickup uses 'Pickup Price'
                    $current_price = ($order_type == 'walkin') ? $prices['price_per_kg'] : ($prices['pickup_price_per_kg'] > 0 ? $prices['pickup_price_per_kg'] : $prices['price_per_kg'] * 0.8);

                    $item_sql = "INSERT INTO order_items (order_id, waste_type_id, weight, price_at_time, subtotal) VALUES (:oid, :wid, :w, :p, :sub)";
                    $item_stmt = $conn->prepare($item_sql);
                    $subtotal = $weight * $current_price; // Estimated subtotal

                    $item_stmt->bindParam(':oid', $order_id);
                    $item_stmt->bindParam(':wid', $type_id);
                    $item_stmt->bindParam(':w', $weight);
                    $item_stmt->bindParam(':p', $current_price);
                    $item_stmt->bindParam(':sub', $subtotal);
                    $item_stmt->execute();
                }
            }

            if ($has_items) {
                // 3. Notify Admin
                $notif_msg = "มีคำร้องขอรับขยะใหม่ Order #" . $order_id;
                $conn->exec("INSERT INTO admin_notifications (type, message, related_id) VALUES ('order', '$notif_msg', $order_id)");

                $conn->commit();
                $msg = "success";
            } else {
                $conn->rollBack();
                $msg = "กรุณาระบุน้ำหนักขยะให้ถูกต้อง";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บริการรับซื้อขยะ - GreenDigital</title>
    <!-- Use Dashboard CSS for consistent theme -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            --primary-gradient: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        body {
            background-color: #f0f3f8;
            background-image: radial-gradient(circle at 10% 20%, rgba(46, 204, 113, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(52, 152, 219, 0.05) 0%, transparent 20%);
        }

        .page-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 15px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .header-subtitle {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .form-card {
            background: var(--glass-bg);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: var(--glass-border);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .step-number {
            width: 35px;
            height: 35px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }

        .step-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--secondary);
            margin: 0;
        }

        /* Tabs Redesign */
        .service-tabs {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .tab-btn {
            flex: 1;
            padding: 1.5rem;
            border: 2px solid transparent;
            border-radius: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }

        .tab-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .tab-btn.active {
            border-color: #2ecc71;
            background: #f0fdf4;
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.15);
        }

        .tab-btn i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
            color: #bdc3c7;
            transition: color 0.3s;
        }

        .tab-btn.active i {
            color: #2ecc71;
        }

        .tab-btn h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .tab-btn p {
            margin: 8px 0 0;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        /* Waste Grid Redesign */
        .waste-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1.5rem;
        }

        .waste-item {
            background: white;
            border: 2px solid #f1f2f6;
            border-radius: 16px;
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .waste-item:hover {
            border-color: #a8e6cf;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .waste-item.selected {
            background-color: #f0fdf4;
            border-color: #2ecc71;
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.1);
        }

        .waste-item img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s;
        }

        .waste-item:hover img {
            transform: scale(1.1);
        }

        .waste-name {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .waste-input {
            margin-top: 15px;
            display: none;
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .price-badge {
            background: #e8f8f5;
            color: #1abc9c;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        /* Map & Inputs */
        #map {
            height: 350px;
            border-radius: 12px;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.05);
            border: 2px solid #fff;
        }

        .form-control {
            border: 1px solid #dfe6e9;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            background: #fdfdfd;
        }

        .form-control:focus {
            background: white;
            border-color: #2ecc71;
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.1);
            outline: none;
        }

        /* Submit Button */
        .btn-submit {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1.2rem;
            width: 100%;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(46, 204, 113, 0.35);
        }

        .radio-card-group {
            display: flex;
            gap: 15px;
        }

        .radio-card {
            flex: 1;
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .radio-card.selected {
            border-color: #3498db;
            background: #f0f7fb;
        }

        .radio-card i {
            font-size: 1.5rem;
        }

        /* Category Tabs */
        .category-tabs-container {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 5px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            /* Firefox */
        }

        .category-tabs-container::-webkit-scrollbar {
            display: none;
            /* Chrome/Safari */
        }

        .cat-tab {
            white-space: nowrap;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cat-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }

        .cat-tab:hover:not(.active) {
            background: #f8f9fa;
        }

        .hidden {
            display: none;
        }
    </style>
</head>

<body>

    <nav class="navbar" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <a href="homepage.php" class="logo">Green<span>Digital</span></a>
        <div class="user-menu">
            <a href="homepage.php" style="text-decoration: none; color: #7f8c8d; font-weight: 500;"><i class="fas fa-arrow-left"></i> กลับหน้าหลัก</a>
        </div>
    </nav>

    <div class="page-container">

        <div class="header-section">
            <h1 class="header-title"><i class="fas fa-truck-loading" style="color: var(--primary);"></i> บริการรับซื้อขยะ</h1>
            <p class="header-subtitle">เปลี่ยนขยะรีไซเคิลให้เป็นรายได้ ง่ายๆ เพียงปลายนิ้ว</p>
        </div>

        <?php if ($msg == "success"): ?>
            <div class="form-card" style="text-align: center; border-color: #2ecc71;">
                <div style="width: 80px; height: 80px; background: #d4edda; color: #155724; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-check" style="font-size: 2.5rem;"></i>
                </div>
                <h2 style="color: #2c3e50; margin-bottom: 10px;">บันทึกคำขอเรียบร้อยแล้ว</h2>
                <p style="color: #7f8c8d; margin-bottom: 30px;">เจ้าหน้าที่จะทำการตรวจสอบและติดต่อกลับโดยเร็วที่สุด</p>

                <div style="display: flex; justify-content: center; gap: 15px;">
                    <a href="homepage.php" class="btn-primary" style="text-decoration: none; padding: 12px 30px; border-radius: 50px;">กลับหน้าหลัก</a>
                    <a href="history.php" class="btn-secondary" style="text-decoration: none; padding: 12px 30px; border-radius: 50px; background: #ecf0f1; color: #2c3e50;">ดูประวัติ</a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($msg): ?>
                <div style="background: #fee2e2; color: #c0392b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border-left: 5px solid #c0392b; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="orderForm" onsubmit="return validateForm()" enctype="multipart/form-data">

                <!-- Service Type Tabs -->
                <div class="service-tabs">
                    <div class="tab-btn active" onclick="switchTab('pickup')" id="tab-pickup">
                        <i class="fas fa-truck-pickup"></i>
                        <h3>รถเข้ารับ (Pickup)</h3>
                        <p>ขั้นต่ำ 10 กก. | สะดวกสบาย</p>
                    </div>
                    <div class="tab-btn" onclick="switchTab('walkin')" id="tab-walkin">
                        <i class="fas fa-walking"></i>
                        <h3>นำไปส่งเอง (Walk-in)</h3>
                        <p>ไม่มีขั้นต่ำ | <strong style="color: #e67e22;">ราคาพิเศษ +20%</strong></p>
                    </div>
                </div>

                <input type="hidden" name="order_type" id="order_type" value="pickup">

                <!-- Step 1: Items -->
                <div class="form-card">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <h3 class="step-title">เลือกประเภทและระบุน้ำหนัก</h3>
                    </div>

                    <!-- Category Filter Tabs -->
                    <div class="category-tabs-container">
                        <button type="button" class="cat-tab active" onclick="filterCategory('all')">ทั้งหมด</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('paper')"><i class="fas fa-box"></i> กระดาษ</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('plastic')"><i class="fas fa-wine-bottle"></i> พลาสติก</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('metal')"><i class="fas fa-cogs"></i> โลหะ</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('glass')"><i class="fas fa-wine-glass"></i> แก้ว</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('electronic')"><i class="fas fa-plug"></i> e-Waste</button>
                        <button type="button" class="cat-tab" onclick="filterCategory('other')"><i class="fas fa-ellipsis-h"></i> อื่นๆ</button>
                    </div>

                    <div class="waste-grid">
                        <?php foreach ($waste_types as $type): ?>
                            <?php
                            $cat = $type['category'] ?? 'other';
                            $pickup_price = isset($type['pickup_price_per_kg']) && $type['pickup_price_per_kg'] > 0
                                ? $type['pickup_price_per_kg']
                                : ($type['price_per_kg'] * 0.8);

                            $img_path = '../assets/images/uploads/' . $type['image'];
                            $display_img = (!empty($type['image']) && file_exists($img_path)) ? $img_path : null;
                            if (!$display_img) {
                                // Fallback logic preserved
                                $n = strtolower($type['name']);
                                if (strpos($n, 'paper') !== false) $display_img = 'https://cdn-icons-png.flaticon.com/512/2541/2541988.png';
                                else $display_img = 'https://cdn-icons-png.flaticon.com/512/9321/9321877.png';
                            }
                            ?>
                            <div class="waste-item" onclick="toggleItem(this)"
                                data-category="<?php echo $cat; ?>"
                                data-price-pickup="<?php echo $pickup_price; ?>"
                                data-price-walkin="<?php echo $type['price_per_kg']; ?>">
                                <img src="<?php echo $display_img; ?>" alt="icon">
                                <div class="waste-name"><?php echo $type['name']; ?></div>
                                <span class="price-badge price-tag"><?php echo number_format($pickup_price, 2); ?> ฿/กก.</span>

                                <div class="waste-input" onclick="event.stopPropagation()">
                                    <input type="number" step="0.1" name="items[<?php echo $type['id']; ?>]"
                                        class="form-control" placeholder="ระบุน้ำหนัก (กก.)"
                                        oninput="calcTotalWeight()" style="text-align: center;">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="background: #f8f9fa; border-radius: 12px; padding: 15px; margin-top: 25px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #636e72;"><i class="fas fa-balance-scale"></i> น้ำหนักรวมประมาณการ:</span>
                        <div style="text-align: right;">
                            <span id="total-weight-display" style="font-size: 1.5rem; font-weight: bold; color: var(--primary-dark);">0.0</span> <span style="font-weight: 500;">กก.</span>
                            <div id="min-warning" style="color: #e74c3c; font-size: 0.85rem; display: none; margin-top: 5px;"><i class="fas fa-exclamation-triangle"></i> ต้องไม่ต่ำกว่า 10 กก.</div>
                        </div>
                    </div>

                    <div style="margin-top: 25px;">
                        <label style="font-weight: 600; margin-bottom: 10px; display: block; color: var(--secondary);">📸 ถ่ายรูปกองขยะ (บังคับ)</label>
                        <div style="border: 2px dashed #bdc3c7; border-radius: 12px; padding: 20px; text-align: center; background: #fff; cursor: pointer;" onclick="document.getElementById('imgInput').click()">
                            <i class="fas fa-camera" style="font-size: 2rem; color: #bdc3c7; margin-bottom: 10px;"></i>
                            <p style="margin: 0; color: #7f8c8d;">แตะเพื่อถ่ายรูปหรือเลือกรูปภาพ</p>
                            <input type="file" id="imgInput" name="request_image" accept="image/*" capture="environment" style="display: none;" onchange="previewImage(this)" required>
                        </div>
                        <div id="image-preview" style="margin-top: 10px; display: none;">
                            <img id="preview-img" src="" style="max-height: 200px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Location -->
                <div class="form-card">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <h3 class="step-title" id="location-header">ข้อมูลการรับ</h3>
                    </div>

                    <!-- Pickup Map -->
                    <div id="pickup-content">
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input type="text" id="location-search" placeholder="ค้นหาสถานที่..." class="form-control" style="flex: 1;">
                                <button type="button" onclick="searchLocation()" style="background: var(--secondary); color: white; border: none; padding: 0 15px; border-radius: 8px;"><i class="fas fa-search"></i></button>
                                <button type="button" onclick="getLocation()" style="background: #3498db; color: white; border: none; padding: 0 15px; border-radius: 8px;"><i class="fas fa-location-arrow"></i></button>
                            </div>
                            <div id="map"></div>
                            <input type="hidden" name="lat" id="lat">
                            <input type="hidden" name="lng" id="lng">
                            <p style="text-align: center; font-size: 0.85rem; color: #7f8c8d; margin-top: 10px;">ลากหมุดเพื่อระบุตำแหน่งที่ชัดเจน</p>
                        </div>
                        <div class="form-group">
                            <label>รายละเอียดที่อยู่เพิ่มเติม</label>
                            <textarea name="address" rows="2" class="form-control" placeholder="บ้านเลขที่, ซอย, จุดสังเกต..."><?php echo htmlspecialchars($user_addr); ?></textarea>
                        </div>
                    </div>

                    <!-- Walkin Info -->
                    <div id="walkin-content" class="hidden">
                        <div style="background: #f0fdf4; padding: 2rem; border-radius: 12px; text-align: center; border: 2px dashed #2ecc71;">
                            <img src="https://cdn-icons-png.flaticon.com/512/2942/2942544.png" style="width: 80px; margin-bottom: 15px;">
                            <h4 style="color: #27ae60; margin: 0 0 10px;">GreenDigital Head Quarter</h4>
                            <p style="color: #636e72;">วิทยาลัยการอาชีพร้อยเอ็ด<br>เปิดทำการ: จันทร์ - ศุกร์ (08:30 - 16:30)</p>
                            <a href="https://www.google.com/maps/place/%E0%B8%A7%E0%B8%B4%E0%B8%97%E0%B8%A2%E0%B8%B2%E0%B8%A5%E0%B8%B1%E0%B8%A2%E0%B8%81%E0%B8%B2%E0%B8%A3%E0%B8%AD%E0%B8%B2%E0%B8%8A%E0%B8%B5%E0%B8%9E%E0%B8%A3%E0%B9%89%E0%B8%AD%E0%B8%A2%E0%B9%80%E0%B8%AD%E0%B9%87%E0%B8%94/@16.0482922,103.6567979,17z/data=!3m1!4b1!4m6!3m5!1s0x3117fdb58785e3a5:0x1b7714834b82cb20!8m2!3d16.0482922!4d103.6593728!16s%2Fg%2F11dyxzkffj?entry=ttu&g_ep=EgoyMDI2MDExOS4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="btn-secondary" style="display: inline-block; padding: 8px 15px; margin-top: 10px; border-radius: 20px; font-size: 0.9rem; text-decoration: none; background: white; border: 1px solid #ddd; color: #2c3e50;"><i class="fas fa-map-marked-alt"></i> นำทาง</a>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                        <div>
                            <label style="font-weight: 500; margin-bottom: 5px; display: block;">วันที่สะดวก</label>
                            <input type="date" name="pickup_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label style="font-weight: 500; margin-bottom: 5px; display: block;">เวลา</label>
                            <input type="time" name="pickup_time" class="form-control" required>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment -->
                <div class="form-card">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <h3 class="step-title">ช่องทางรับเงิน</h3>
                    </div>

                    <div class="radio-card-group">
                        <label class="radio-card selected" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="cash" checked style="accent-color: #27ae60;">
                            <div style="width: 40px; height: 40px; background: #e8f8f5; color: #2ecc71; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #2c3e50;">เงินสด (Cash)</div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;">รับเงินทันทีที่หน้างาน</div>
                            </div>
                        </label>

                        <label class="radio-card" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="transfer" style="accent-color: #3498db;">
                            <div style="width: 40px; height: 40px; background: #ebf5fb; color: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #2c3e50;">เข้าวอลเล็ท (Green Wallet)</div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;">สะสมในระบบ ถอนเข้าบัญชีได้</div>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>ยืนยันคำขอรับบริการ</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

            </form>
        <?php endif; ?>

    </div>

    <script>
        // Init Map
        var map = L.map('map').setView([13.7563, 100.5018], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        var marker = L.marker([13.7563, 100.5018], {
            draggable: true
        }).addTo(map);

        function updatePosition(lat, lng) {
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
        }
        marker.on('dragend', function(e) {
            updatePosition(marker.getLatLng().lat, marker.getLatLng().lng);
        });

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    map.setView([pos.coords.latitude, pos.coords.longitude], 16);
                    marker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
                    updatePosition(pos.coords.latitude, pos.coords.longitude);
                });
            }
        }
        updatePosition(13.7563, 100.5018); // Default fallback

        // Tab Switching
        let currentMode = 'pickup';

        function switchTab(mode) {
            currentMode = mode;
            document.getElementById('order_type').value = mode;

            // Tab Styles
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + mode).classList.add('active');

            // Content Visibility
            if (mode === 'pickup') {
                document.getElementById('pickup-content').classList.remove('hidden');
                document.getElementById('walkin-content').classList.add('hidden');
                document.getElementById('location-header').innerText = 'ข้อมูลการรับ (Pickup Location)';
                setTimeout(() => map.invalidateSize(), 100); // Fix map render issue
            } else {
                document.getElementById('pickup-content').classList.add('hidden');
                document.getElementById('walkin-content').classList.remove('hidden');
                document.getElementById('location-header').innerText = 'สถานที่นำส่ง (Drop-off Location)';
            }

            // Price Update
            document.querySelectorAll('.waste-item').forEach(item => {
                let price = (mode === 'pickup') ? item.dataset.pricePickup : item.dataset.priceWalkin;
                item.querySelector('.price-badge').innerText = parseFloat(price).toFixed(2) + ' ฿/กก.';
            });
            calcTotalWeight();
        }

        // Category Filtering
        function filterCategory(cat) {
            // Update Tab UI
            document.querySelectorAll('.cat-tab').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.cat-tab').classList.add('active');

            let items = document.querySelectorAll('.waste-item');
            items.forEach(item => {
                if (cat === 'all' || item.dataset.category === cat) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Selection Logic
        function selectPayment(el) {
            document.querySelectorAll('.radio-card').forEach(c => {
                c.classList.remove('selected');
                c.querySelector('input').checked = false;
            });
            el.classList.add('selected');
            el.querySelector('input').checked = true;
        }

        function toggleItem(el) {
            el.classList.toggle('selected');
            let inputDiv = el.querySelector('.waste-input');
            if (el.classList.contains('selected')) {
                inputDiv.style.display = 'block';
                inputDiv.querySelector('input').focus();
            } else {
                inputDiv.style.display = 'none';
                inputDiv.querySelector('input').value = '';
                calcTotalWeight();
            }
        }

        function calcTotalWeight() {
            let total = 0;
            document.querySelectorAll('input[name^="items"]').forEach(inp => {
                let val = parseFloat(inp.value);
                if (!isNaN(val)) total += val;
            });
            document.getElementById('total-weight-display').innerText = total.toFixed(1);

            let warn = document.getElementById('min-warning');
            if (currentMode === 'pickup' && total < 10 && total > 0) {
                warn.style.display = 'block';
            } else {
                warn.style.display = 'none';
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function validateForm() {
            let total = 0;
            document.querySelectorAll('input[name^="items"]').forEach(inp => {
                if (!isNaN(parseFloat(inp.value))) total += parseFloat(inp.value);
            });

            if (total <= 0) {
                alert("กรุณาระบุรายการขยะอย่างน้อย 1 รายการ");
                return false;
            }
            if (currentMode === 'pickup' && total < 10) {
                alert("บริการรถเข้ารับ ต้องมีน้ำหนักขั้นต่ำ 10 กก. ครับ");
                return false;
            }
            return true;
        }

        async function searchLocation() {
            let q = document.getElementById('location-search').value;
            if (!q) return;
            try {
                let res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${q}`);
                let data = await res.json();
                if (data.length > 0) {
                    let lat = parseFloat(data[0].lat),
                        lon = parseFloat(data[0].lon);
                    map.setView([lat, lon], 16);
                    marker.setLatLng([lat, lon]);
                    updatePosition(lat, lon);
                }
            } catch (e) {
                alert("ค้นหาไม่พบ");
            }
        }
    </script>
</body>

</html>