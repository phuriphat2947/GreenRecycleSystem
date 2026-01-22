<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// Fetch User Data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Wallet Balance
    $stmt_bal = $conn->prepare("SELECT balance_after FROM wallet_transactions WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $stmt_bal->execute([':uid' => $user_id]);
    $wallet_balance = $stmt_bal->fetchColumn() ?: 0.00;

    // Fetch Pending Confirmations (Anti-Fraud)
    $stmt_pending = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'waiting_confirm'");
    $stmt_pending->execute([':uid' => $user_id]);
    $pending_count = $stmt_pending->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);

        // KYC Data
        $id_card_number = trim($_POST['id_card_number']);
        $bank_name = $_POST['bank_name'];
        $bank_account = trim($_POST['bank_account']);
        $bank_account_name = trim($_POST['bank_account_name']);

        $current_status = $user['kyc_status'] ?? 'unverified';
        $new_status = $current_status;

        // Handle Profile Image
        $profile_image = $user['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_filename = "user_" . $user_id . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], "../assets/images/uploads/" . $new_filename)) {
                    $profile_image = $new_filename;
                }
            }
        }

        // Handle ID Card Image
        $id_card_image = $user['id_card_image'];
        if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['id_card_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_id_filename = "idcard_" . $user_id . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['id_card_image']['tmp_name'], "../assets/images/uploads/" . $new_id_filename)) {
                    $id_card_image = $new_id_filename;
                    if ($current_status != 'verified') $new_status = 'pending';
                }
            }
        }

        // Logic check: if critical info changed, revert to pending
        // Logic check: if critical info changed, revert to pending
        // If critical info changed, force revert to pending regardless of current status
        if (
            $id_card_number != $user['id_card_number'] ||
            $bank_account != $user['bank_account'] ||
            $bank_name != $user['bank_name'] ||
            $bank_account_name != $user['bank_account_name']
        ) {
            $new_status = 'pending';
        }

        // Also if name changed significantly? (Optional, but let's stick to bank/id)

        try {
            $sql = "UPDATE users SET 
                    username = :username, 
                    phone = :phone,
                    address = :address, 
                    profile_image = :profile_image,
                    id_card_number = :id_card_number,
                    id_card_image = :id_card_image,
                    bank_name = :bank_name,
                    bank_account = :bank_account,
                    bank_account_name = :bank_account_name,
                    kyc_status = :kyc_status
                    WHERE id = :id";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':phone' => $phone,
                ':address' => $address,
                ':profile_image' => $profile_image,
                ':id_card_number' => $id_card_number,
                ':id_card_image' => $id_card_image,
                ':bank_name' => $bank_name,
                ':bank_account' => $bank_account,
                ':bank_account_name' => $bank_account_name,
                ':kyc_status' => $new_status,
                ':id' => $user_id
            ]);

            $_SESSION['username'] = $username;
            $msg = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $msg_type = "success";

            // Refresh
            $user = array_merge($user, $_POST);
            $user['profile_image'] = $profile_image;
            $user['id_card_image'] = $id_card_image;
            $user['kyc_status'] = $new_status;
        } catch (PDOException $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโปรไฟล์ - GreenDigital</title>
    <!-- CSS and HEAD content from original file -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .profile-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .profile-img-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }

        .profile-img-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary);
        }

        .upload-overlay {
            position: absolute;
            bottom: 37px !important;
            right: -7vh;
            /* left: 38px !important; */
            background: var(--primary);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            z-index: 999;
        }

        .upload-overlay:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .form-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
        }

        .wallet-card {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            padding: 25px;
            border-radius: 15px;
            color: white;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
    </style>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">

        <!-- Anti-Fraud Alert -->
        <?php if ($pending_count > 0): ?>
            <div class="alert alert-warning" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>มี <?php echo $pending_count; ?> รายการรอการตรวจสอบ!</strong>
                        <div style="font-size:0.9rem;">กรุณายืนยันยอดเงินเพื่อให้เครดิตเข้ากระเป๋า</div>
                    </div>
                </div>
                <?php
                $stmt_first = $conn->prepare("SELECT id FROM orders WHERE user_id = :uid AND status = 'waiting_confirm' LIMIT 1");
                $stmt_first->execute([':uid' => $user_id]);
                $first_id = $stmt_first->fetchColumn();
                ?>
                <a href="confirm_order.php?id=<?php echo $first_id; ?>" class="btn-save" style="width:auto; padding:8px 20px; font-size:0.9rem; background:white; color:#856404; border:1px solid #856404;">ตรวจสอบเลย</a>
            </div>
        <?php endif; ?>

        <!-- Wallet Section -->
        <div class="wallet-card">
            <div style="font-size:1.1rem; opacity:0.9;">Green Wallet Balance</div>
            <div style="font-size:3rem; font-weight:700; margin:10px 0;">฿<?php echo number_format($wallet_balance, 2); ?></div>
            <a href="withdrawal.php" style="display:inline-block; background:rgba(255,255,255,0.2); color:white; padding:8px 20px; border-radius:50px; text-decoration:none; border:1px solid rgba(255,255,255,0.4); transition:0.3s;">
                <i class="fas fa-hand-holding-usd"></i> แจ้งถอนเงิน
            </a>
        </div>

        <div class="profile-container">
            <h2 style="margin-bottom:20px; text-align:center;">ตั้งค่าบัญชีผู้ใช้</h2>

            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <!-- Profile Image -->
                <div class="profile-img-wrapper">
                    <?php
                    $img_path = (!empty($user['profile_image']) && $user['profile_image'] != 'default_avatar.png')
                        ? "../assets/images/uploads/" . $user['profile_image']
                        : "https://via.placeholder.com/150";
                    ?>
                    <img src="<?php echo $img_path; ?>" id="previewImg" class="profile-img-preview">
                    <label for="profile_image" class="upload-overlay"><i class="fas fa-camera"></i></label>
                    <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display:none;" onchange="previewFile()">
                </div>

                <div class="form-section">
                    <h3>ข้อมูลทั่วไป</h3>
                    <div class="form-group">
                        <label>ชื่อผู้ใช้</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>เบอร์โทรศัพท์ <span style="color:red;">*</span></label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="08xxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label>อีเมล</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#f9f9f9;">
                    </div>
                    <div class="form-group">
                        <label>ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- KYC Section -->
                <div class="form-section">
                    <h3>ยืนยันตัวตน (KYC)</h3>
                    <div class="alert" style="background:#eef9fd; color:#0c5460; font-size:0.9rem;">
                        สถานะ:
                        <?php
                        $st = $user['kyc_status'] ?? 'unverified';
                        $badges = ['unverified' => 'ยังไม่ยืนยัน', 'pending' => 'รอตรวจสอบ', 'verified' => 'อนุมัติแล้ว', 'rejected' => 'ไม่ผ่าน'];
                        echo "<strong>" . $badges[$st] . "</strong>";
                        ?>
                    </div>

                    <?php if ($st == 'rejected'): ?>
                        <div class="alert alert-danger">
                            สาเหตุ: <?php echo htmlspecialchars($user['kyc_reject_reason']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>เลขบัตรประชาชน</label>
                        <input type="text" name="id_card_number" class="form-control" value="<?php echo htmlspecialchars($user['id_card_number'] ?? ''); ?>" placeholder="13 หลัก" required <?php echo ($st == 'verified') ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label>รูปถ่ายบัตรประชาชน</label>
                        <?php if (!empty($user['id_card_image'])): ?>
                            <div style="margin-bottom:10px;"><a href="../assets/images/uploads/<?php echo $user['id_card_image']; ?>" target="_blank">ดูรูปปัจจุบัน</a></div>
                        <?php endif; ?>
                        <?php if ($st != 'verified'): ?>
                            <input type="file" name="id_card_image" class="form-control">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bank Section -->

                <div class="form-section">
                    <h3>บัญชีรับเงิน</h3>
                    <div class="form-group">
                        <label>ธนาคาร</label>
                        <select name="bank_name" class="form-control" required>
                            <option value="">-- เลือก --</option>
                            <?php
                            $banks = ["กสิกรไทย", "ไทยพาณิชย์", "กรุงเทพ", "กรุงไทย", "กรุงศรี", "ออมสิน"];
                            foreach ($banks as $b) {
                                $sel = ($user['bank_name'] == $b) ? 'selected' : '';
                                echo "<option value='$b' $sel>$b</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>เลขบัญชี</label>
                        <input type="text" name="bank_account" class="form-control" value="<?php echo htmlspecialchars($user['bank_account'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>ชื่อบัญชี</label>
                        <input type="text" name="bank_account_name" class="form-control" value="<?php echo htmlspecialchars($user['bank_account_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div style="margin-top:30px;">
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fas fa-save"></i> บันทึกข้อมูล
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        function previewFile() {
            const preview = document.getElementById('previewImg');
            const file = document.getElementById('profile_image').files[0];
            const reader = new FileReader();
            reader.onloadend = function() {
                preview.src = reader.result;
            }
            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>

</html>