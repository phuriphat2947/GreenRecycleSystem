<?php
session_start();
require_once "../db_connect/db_connect.php";

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'add' || $action == 'edit') {
        $name = $_POST['name'];
        $desc = $_POST['description'];
        $cost_point = !empty($_POST['points_cost']) ? $_POST['points_cost'] : 0;
        $cost_weight = !empty($_POST['weight_cost']) ? $_POST['weight_cost'] : 0;
        $cost_carbon = !empty($_POST['carbon_cost']) ? $_POST['carbon_cost'] : 0;
        $stock = $_POST['stock'];
        $status = $_POST['status'];

        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../assets/images/uploads/";
            $image = time() . "_" . basename($_FILES["image"]["name"]);
            move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image);
        }

        if ($action == 'add') {
            $sql = "INSERT INTO rewards (name, description, points_cost, weight_cost, carbon_cost, stock, status";
            if ($image) $sql .= ", image";
            $sql .= ") VALUES (:name, :desc, :cp, :cw, :cc, :stock, :status";
            if ($image) $sql .= ", :img";
            $sql .= ")";

            $stmt = $conn->prepare($sql);
            $params = [':name' => $name, ':desc' => $desc, ':cp' => $cost_point, ':cw' => $cost_weight, ':cc' => $cost_carbon, ':stock' => $stock, ':status' => $status];
            if ($image) $params[':img'] = $image;
            $stmt->execute($params);
        } else { // Edit
            $id = $_POST['id'];
            $sql = "UPDATE rewards SET name = :name, description = :desc, points_cost = :cp, weight_cost = :cw, carbon_cost = :cc, stock = :stock, status = :status";
            if ($image) $sql .= ", image = :img";
            $sql .= " WHERE id = :id";

            $stmt = $conn->prepare($sql);
            $params = [':name' => $name, ':desc' => $desc, ':cp' => $cost_point, ':cw' => $cost_weight, ':cc' => $cost_carbon, ':stock' => $stock, ':status' => $status, ':id' => $id];
            if ($image) $params[':img'] = $image;
            $stmt->execute($params);
        }
        header("Location: rewards.php?msg=saved");
        exit();
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM rewards WHERE id = :id")->execute([':id' => $id]);
        header("Location: rewards.php?msg=deleted");
        exit();
    }
}

// Fetch Rewards
$stmt = $conn->query("SELECT * FROM rewards ORDER BY id DESC");
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการของรางวัล - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding: 20px 0;
        }

        .reward-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            border: 1px solid #f0f0f0;
        }

        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .reward-img-wrapper {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .reward-img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
            transition: transform 0.3s;
        }

        .reward-card:hover .reward-img {
            transform: scale(1.1);
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .status-active {
            background: #2ecc71;
            color: white;
        }

        .status-inactive {
            background: #e74c3c;
            color: white;
        }

        .reward-content {
            padding: 20px;
        }

        .reward-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .reward-desc {
            font-size: 0.9rem;
            color: #7f8c8d;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 15px;
        }

        .reward-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .reward-points {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2ecc71;
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-card {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn-edit {
            background: #f1c40f;
            color: #fff;
        }

        .btn-edit:hover {
            background: #f39c12;
        }

        .btn-delete {
            background: #ff7675;
            color: #fff;
        }

        .btn-delete:hover {
            background: #d63031;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            /* Increased Z-Index significantly */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border: none;
            width: 50%;
            border-radius: 15px;
            position: relative;
            /* Ensure it stays on top */
            z-index: 10000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.2s;
            color: #2d3436;
            /* Force dark text color */
            background-color: #fff;
            /* Force white background */
            pointer-events: auto;
            /* Ensure clickable */
            user-select: text;
            /* Ensure selectable */
        }


        /* Ensure Button Styles Load */
        .btn-primary {
            background: #2ecc71 !important;
            color: white !important;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(46, 204, 113, 0.2);
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #27ae60 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(46, 204, 113, 0.3);
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>จัดการของรางวัล (Reward Store)</h2>
                <p style="color: #7f8c8d; font-size: 0.9rem;">บริหารจัดการสินค้าสำหรับแลกแต้ม</p>
            </div>
            <button onclick="openModal('add')" class="btn-primary" style="padding: 10px 20px; font-size: 1rem; box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);"><i class="fas fa-plus"></i> เพิ่มรางวัลใหม่</button>
        </header>

        <main class="content-wrapper">
            <?php if ($msg == 'saved'): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 25px; border-radius: 8px; border-left: 5px solid #2ecc71; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle"></i> บันทึกข้อมูลสำเร็จ
                </div>
            <?php elseif ($msg == 'deleted'): ?>
                <div style="background: #fad7a0; color: #856404; padding: 15px; margin-bottom: 25px; border-radius: 8px; border-left: 5px solid #f39c12; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-trash"></i> ลบข้อมูลสำเร็จ
                </div>
            <?php endif; ?>

            <div class="rewards-grid">
                <?php foreach ($rewards as $r): ?>
                    <div class="reward-card">
                        <div class="reward-img-wrapper">
                            <?php
                            $img = "../assets/images/uploads/" . $r['image'];
                            if (empty($r['image']) || !file_exists($img)) {
                                // Smart Fallbacks
                                if ($r['image'] == 'reward_bag.png') $img = "https://cdn-icons-png.flaticon.com/512/2829/2829824.png";
                                elseif ($r['image'] == 'reward_cup.png') $img = "https://cdn-icons-png.flaticon.com/512/1902/1902724.png";
                                elseif ($r['image'] == 'reward_coupon.png') $img = "https://cdn-icons-png.flaticon.com/512/2089/2089363.png";
                                elseif ($r['image'] == 'reward_seed.png') $img = "https://cdn-icons-png.flaticon.com/512/628/628324.png";
                                else $img = "https://via.placeholder.com/300x200?text=No+Image";
                            }
                            ?>
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($r['name']); ?>" class="reward-img">
                            <span class="status-badge <?php echo $r['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $r['status']; ?>
                            </span>
                        </div>
                        <div class="reward-content">
                            <h3 class="reward-title"><?php echo htmlspecialchars($r['name']); ?></h3>
                            <p class="reward-desc"><?php echo htmlspecialchars($r['description']); ?></p>

                            <div class="reward-meta" style="flex-direction:column; align-items:flex-start; gap:5px;">
                                <?php if ($r['points_cost'] > 0): ?>
                                    <span class="reward-points" style="color:#f39c12; font-size:1rem;"><i class="fas fa-coins"></i> <?php echo number_format($r['points_cost']); ?> ฿</span>
                                <?php endif; ?>
                                <?php if ($r['weight_cost'] > 0): ?>
                                    <span class="reward-points" style="color:#27ae60; font-size:1rem;"><i class="fas fa-recycle"></i> <?php echo number_format($r['weight_cost']); ?> kg</span>
                                <?php endif; ?>
                                <?php if ($r['carbon_cost'] > 0): ?>
                                    <span class="reward-points" style="color:#3498db; font-size:1rem;"><i class="fas fa-cloud"></i> <?php echo number_format($r['carbon_cost']); ?> C</span>
                                <?php endif; ?>

                                <span class="reward-stock" style="margin-top:5px;"><i class="fas fa-box"></i> เหลือ <?php echo $r['stock']; ?></span>
                            </div>

                            <div class="card-actions">
                                <button onclick='editReward(<?php echo json_encode($r); ?>)' class="btn-card btn-edit"><i class="fas fa-edit"></i> แก้ไข</button>
                                <form method="POST" style="flex:1;" id="delete-form-<?php echo $r['id']; ?>" onsubmit="submitDeleteReward(event, <?php echo $r['id']; ?>)">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn-card btn-delete" style="width:100%"><i class="fas fa-trash-alt"></i> ลบ</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="rewardModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle" style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px;">เพิ่มรางวัลใหม่</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="rewardId">

                <div class="form-group">
                    <label>ชื่อรางวัล</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="เช่น กระเป๋าผ้าลดโลกร้อน" required>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description" id="description" class="form-control" rows="3" placeholder="รายละเอียดของรางวัล..."></textarea>
                </div>

                <div class="form-group">
                    <label>กำหนดราคาแลก (Mixed Hybrid Cost)</label>
                    <div style="display:flex; gap:10px; margin-bottom:5px;">
                        <input type="number" step="0.01" name="points_cost" id="points_cost" class="form-control" placeholder="0 = ฟรี">
                        <input type="number" step="0.01" name="weight_cost" id="weight_cost" class="form-control" placeholder="0 = ฟรี">
                        <input type="number" step="0.01" name="carbon_cost" id="carbon_cost" class="form-control" placeholder="0 = ฟรี">
                    </div>
                    <div style="display:flex; gap:10px; font-size:0.8rem; color:#666; padding:0 5px;">
                        <div style="flex:1;"><i class="fas fa-coins" style="color:#f39c12;"></i> ฿ Point (บาท)</div>
                        <div style="flex:1;"><i class="fas fa-recycle" style="color:#27ae60;"></i> ♻️ Weight (กก.)</div>
                        <div style="flex:1;"><i class="fas fa-cloud" style="color:#3498db;"></i> ☁️ Carbon (Credit)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>จำนวนในสต็อก</label>
                    <input type="number" name="stock" id="stock" class="form-control" placeholder="0" required>
                </div>

                <div class="form-group">
                    <label>รูปภาพสินค้า</label>
                    <input type="file" name="image" class="form-control" style="padding: 10px;">
                    <small style="color: #7f8c8d;">รองรับไฟล์ jpg, png, webp (ปล่อยว่างหากไม่ต้องการแก้ไขรูป)</small>
                </div>

                <div class="form-group">
                    <label>สถานะ</label>
                    <select name="status" id="status" class="form-control">
                        <option value="active">Active (เปิดให้แลก)</option>
                        <option value="inactive">Inactive (ปิดปรับปรุง)</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; padding: 12px; font-size: 1.1rem; margin-top: 10px;">บันทึกข้อมูล</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode) {
            document.getElementById('rewardModal').style.display = 'block';
            if (mode == 'add') {
                document.getElementById('modalTitle').innerText = 'เพิ่มรางวัลใหม่';
                document.getElementById('formAction').value = 'add';
                document.getElementById('rewardId').value = '';
                document.getElementById('name').value = '';
                document.getElementById('description').value = '';
                document.getElementById('points_cost').value = '';
                document.getElementById('weight_cost').value = '';
                document.getElementById('carbon_cost').value = '';
                document.getElementById('stock').value = '';
                document.getElementById('status').value = 'active';
            }
        }

        function editReward(data) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'แก้ไขรางวัล';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('rewardId').value = data.id;
            document.getElementById('name').value = data.name;
            document.getElementById('description').value = data.description;
            document.getElementById('points_cost').value = data.points_cost;
            document.getElementById('weight_cost').value = data.weight_cost || 0;
            document.getElementById('carbon_cost').value = data.carbon_cost || 0;
            document.getElementById('stock').value = data.stock;
            document.getElementById('status').value = data.status;
        }

        function closeModal() {
            document.getElementById('rewardModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('rewardModal')) {
                closeModal();
            }
        }
        // SweetAlert2 Delete Confirmation
        function submitDeleteReward(e, id) {
            e.preventDefault();
            Swal.fire({
                title: 'ยืนยันการลบรางวัล?',
                text: "คุณต้องการลบรางวัลนี้ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + id).submit();
                }
            });
        }
    </script>

</body>

</html>