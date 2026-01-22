<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Admin Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'users';
$msg = "";
$msg_type = "";

// Handle Actions (Suspend/Activate/KYC)
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        $new_status = ($action == 'suspend') ? 'suspended' : 'active';

        try {
            $stmt = $conn->prepare("UPDATE users SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $new_status);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $msg = ($action == 'suspend') ? "ระงับสิทธิ์ผู้ใช้งานเรียบร้อยแล้ว" : "ยกเลิกการระงับสิทธิ์เรียบร้อยแล้ว";
                $msg_type = "success";
            }
        } catch (PDOException $e) { /* ... */
        }
    }
}
// Handle Reject Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reject'])) {
    $user_id = $_POST['reject_user_id'];
    $reason = trim($_POST['reject_reason']);
    if (!empty($user_id) && !empty($reason)) {
        try {
            $stmt = $conn->prepare("UPDATE users SET kyc_status = 'rejected', kyc_reject_reason = :reason WHERE id = :id");
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $msg = "ปฏิเสธ KYC เรียบร้อยแล้ว (ระบุเหตุผล: $reason)";
                $msg_type = "warning";
            }
        } catch (PDOException $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}



// Handle Edit User (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $edit_id = $_POST['edit_user_id'];
    $edit_username = trim($_POST['edit_username'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_id_card = trim($_POST['edit_id_card'] ?? '');
    $edit_bank_name = $_POST['edit_bank_name'] ?? '';
    $edit_bank_account = trim($_POST['edit_bank_account'] ?? '');
    $edit_bank_account_name = trim($_POST['edit_bank_account_name'] ?? '');

    $edit_kyc_status = $_POST['edit_kyc_status'] ?? 'unverified';
    $edit_role = $_POST['edit_role'] ?? 'user';

    try {
        $sql = "UPDATE users SET 
                username = :username, 
                email = :email, 
                id_card_number = :id_card,
                bank_name = :bank_name, 
                bank_account = :bank_account, 
                bank_account_name = :bank_account_name,
                role = :role,
                kyc_status = :kyc_status";

        // If Verifying, clear any previous reject reason
        if ($edit_kyc_status == 'verified') {
            $sql .= ", kyc_reject_reason = NULL";
        }

        $sql .= " WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $edit_username);
        $stmt->bindParam(':email', $edit_email);
        $stmt->bindParam(':id_card', $edit_id_card);
        $stmt->bindParam(':bank_name', $edit_bank_name);
        $stmt->bindParam(':bank_account', $edit_bank_account);
        $stmt->bindParam(':bank_account_name', $edit_bank_account_name);
        $stmt->bindParam(':role', $edit_role);
        $stmt->bindParam(':kyc_status', $edit_kyc_status);
        $stmt->bindParam(':id', $edit_id);

        if ($stmt->execute()) {
            $msg = "แก้ไขข้อมูลสมาชิกเรียบร้อยแล้ว";
            $msg_type = "success";
        }
    } catch (PDOException $e) {
        $msg = "Error updating user: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// Handle Approve (GET)
if (isset($_GET['kyc_action']) && $_GET['kyc_action'] == 'approve') {
    $user_id = $_GET['id'];
    try {
        $stmt = $conn->prepare("UPDATE users SET kyc_status = 'verified', kyc_reject_reason = NULL WHERE id = :id");
        $stmt->bindParam(':id', $user_id);
        if ($stmt->execute()) {
            $msg = "อนุมัติ KYC เรียบร้อยแล้ว";
            $msg_type = "success";
        }
    } catch (PDOException $e) { /* ... */
    }
}

// Search Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

$sql = "SELECT * FROM users WHERE role != 'admin'"; 

if (!empty($role_filter)) {
    $sql .= " AND role = :role";
}

// --- จุดที่แก้ไข (เปลี่ยน :search เป็น :s1, :s2, :s3, :s4) ---
if (!empty($search)) {
    $sql .= " AND (username LIKE :s1 OR email LIKE :s2 OR citizen_id LIKE :s3 OR id_card_number LIKE :s4)";
}
// --------------------------------------------------------

$sql .= " ORDER BY role ASC, CASE WHEN kyc_status = 'pending' THEN 0 ELSE 1 END, created_at DESC";

try {
    $stmt = $conn->prepare($sql);
    
    if (!empty($role_filter)) {
        $stmt->bindParam(':role', $role_filter);
    }
    
    // --- จุดที่แก้ไข (Bind ค่าซ้ำให้ครบทุกตัว) ---
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':s1', $search_param);
        $stmt->bindParam(':s2', $search_param);
        $stmt->bindParam(':s3', $search_param);
        $stmt->bindParam(':s4', $search_param);
    }
    // ------------------------------------------

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .badge-kyc {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-kyc.pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-kyc.verified {
            background: #d4edda;
            color: #155724;
        }

        .badge-kyc.unverified {
            background: #e2e3e5;
            color: #383d41;
        }

        .badge-kyc.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            background: white;
            border-radius: 8px;
            text-align: center;
        }

        .modal img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>จัดการสมาชิก & ยืนยันตัวตน (KYC)</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">
            <?php if ($msg): ?>
                <div style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background: <?php echo $msg_type == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $msg_type == 'success' ? '#155724' : '#721c24'; ?>;">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <!-- Tools Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: white; padding: 1rem; border-radius: 12px; box-shadow: var(--shadow);">
                <form method="GET" style="display: flex; gap: 10px; width: 70%;">
                    <select name="role" style="padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; outline: none; background: #f9f9f9;">
                        <option value="">ทั้งหมด (All Roles)</option>
                        <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>สมาชิก (User)</option>
                        <option value="driver" <?php echo $role_filter == 'driver' ? 'selected' : ''; ?>>คนขับ (Driver)</option>
                    </select>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหาชื่อ, อีเมล หรือเลขบัตร..." style="flex: 1; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; outline: none;">
                    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <a href="users.php" style="background: var(--secondary); color: white; text-decoration: none; padding: 0.8rem 1rem; border-radius: 8px;">ล้างค่า</a>
                </form>
            </div>

            <!-- Users Table -->
            <div style="background: white; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: var(--light-bg); border-bottom: 2px solid #eee;">
                        <tr>
                            <th style="padding: 1rem; text-align: left;">ID / User</th>
                            <th style="padding: 1rem; text-align: left;">KYC Data</th>
                            <th style="padding: 1rem; text-align: left;">Bank Info</th>
                            <th style="padding: 1rem; text-align: center;">Status</th>
                            <th style="padding: 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                       <?php if (!empty($users) && count($users) > 0): ?>
                            <?php foreach ($users as $u): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?php echo ($u['profile_image'] == 'default_avatar.png') ? 'https://via.placeholder.com/40' : '../assets/images/uploads/' . $u['profile_image']; ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <div>
                                                <div style="font-weight: 600; color: var(--secondary);"><?php echo htmlspecialchars($u['username']); ?>
                                                    <?php if ($u['role'] == 'driver'): ?>
                                                        <span style="background:#17a2b8; color:white; font-size:0.7rem; padding:2px 6px; border-radius:4px;">DRIVER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #888;">#<?php echo $u['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div style="font-size: 0.9rem;">Email: <?php echo htmlspecialchars($u['email']); ?></div>
                                        <div style="font-size: 0.9rem;">ID Card: <?php echo !empty($u['id_card_number']) ? $u['id_card_number'] : '-'; ?></div>
                                        <?php if (!empty($u['id_card_image'])): ?>
                                            <button onclick="viewImage('../assets/images/uploads/<?php echo $u['id_card_image']; ?>')" style="margin-top:5px; font-size: 0.8rem; cursor: pointer; border:1px solid #ddd; background:#f9f9f9; padding:2px 8px; border-radius:4px;"><i class="fas fa-image"></i> ดูรูปบัตร</button>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem;">
                                        <?php if (!empty($u['bank_account'])): ?>
                                            <div><?php echo $u['bank_name']; ?></div>
                                            <div style="font-weight:600;"><?php echo $u['bank_account']; ?></div>
                                            <div style="color:#666;"><?php echo $u['bank_account_name']; ?></div>
                                        <?php else: ?>
                                            <span style="color:#ccc;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php
                                        $k = $u['kyc_status'] ?? 'unverified';
                                        echo "<div style='margin-bottom:5px'><span class='badge-kyc $k'>" . ucfirst($k) . "</span></div>";
                                        ?>
                                        <?php if ($u['status'] == 'active'): ?>
                                            <small style="color: green;">Active</small>
                                        <?php else: ?>
                                            <small style="color: red;">Suspended</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <div style="display: flex; justify-content: flex-end; gap: 5px; flex-wrap: wrap; width: 140px; float: right;">
                                            <!-- History for Driver -->
                                            <?php if ($u['role'] == 'driver'): ?>
                                                <a href="driver_history.php?id=<?php echo $u['id']; ?>" class="btn-sm" style="background:#6f42c1; color:white; padding:5px 10px; border-radius:4px; text-decoration:none; display: block; width: 100%; text-align:center; margin-bottom: 2px;">
                                                    <i class="fas fa-history"></i> ประวัติงาน
                                                </a>
                                            <?php endif; ?>

                                            <!-- Edit Button -->
                                            <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="btn-sm" style="background:#ffc107; color:#212529; padding:5px 10px; border-radius:4px; border:none; cursor:pointer; display: block; width: 100%; margin-bottom: 2px;">
                                                <i class="fas fa-edit"></i> แก้ไข
                                            </button>


                                            <?php if ($u['kyc_status'] == 'pending'): ?>
                                                <a href="#" onclick="confirmAction('users.php?kyc_action=approve&id=<?php echo $u['id']; ?>', 'ยืนยันอนุมัติ KYC?', 'success')" class="btn-sm" style="background:#28a745; color:white; padding:5px 10px; border-radius:4px; text-decoration:none; display: block; width: 100%; text-align:center; margin-bottom: 2px;">อนุมัติ</a>
                                                <button onclick="openRejectModal(<?php echo $u['id']; ?>)" class="btn-sm" style="background:#dc3545; color:white; padding:5px 10px; border-radius:4px; border:none; cursor:pointer; display: block; width: 100%;">ปฏิเสธ</button>
                                            <?php endif; ?>

                                            <?php if ($u['status'] == 'active'): ?>
                                                <a href="#" onclick="confirmAction('users.php?action=suspend&id=<?php echo $u['id']; ?>', 'ยืนยันการระงับผู้ใช้?', 'warning')" title="Suspend" style="color: #e74c3c; font-size: 1.1rem; margin-top:5px;"><i class="fas fa-ban"></i></a>
                                            <?php else: ?>
                                                <a href="#" onclick="confirmAction('users.php?action=activate&id=<?php echo $u['id']; ?>', 'ยืนยันการคืนสิทธิ์ผู้ใช้?', 'question')" title="Activate" style="color: #2ecc71; font-size: 1.1rem; margin-top:5px;"><i class="fas fa-check-circle"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 2rem;">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Image Model -->
    <div id="imgModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('imgModal').style.display='none'">&times;</span>
            <h3>หลักฐานบัตรประชาชน</h3>
            <img id="modalImg" src="">
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: left;">
            <span class="close" onclick="document.getElementById('rejectModal').style.display='none'">&times;</span>
            <h3 style="margin-bottom: 1rem; color: #dc3545;">ปฏิเสธการยืนยันตัวตน</h3>
            <form method="POST">
                <input type="hidden" name="reject_user_id" id="reject_user_id">
                <div style="margin-bottom: 1rem;">
                    <label>ระบุเหตุผลที่ไม่อนุมัติ:</label>
                    <textarea name="reject_reason" class="form-control" rows="4" required placeholder="เช่น รูปบัตรไม่ชัดเจน, ชื่อไม่ตรงกับบัญชีธนาคาร" style="width: 100%; border: 1px solid #ddd; padding: 10px; margin-top: 5px;"></textarea>
                </div>
                <button type="submit" name="submit_reject" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%;">บันทึกผลการตรวจสอบ</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->


    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content" style="max-width: 500px; text-align: left;">
            <span class="close" onclick="document.getElementById('editUserModal').style.display='none'">&times;</span>
            <h3 style="margin-bottom: 1rem; color: #ffc107;"><i class="fas fa-edit"></i> แก้ไขข้อมูลสมาชิก</h3>
            <form method="POST">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <input type="hidden" name="edit_user" value="1">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Username</label>
                        <input type="text" name="edit_username" id="edit_username" class="form-control" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Email</label>
                        <input type="email" name="edit_email" id="edit_email" class="form-control" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <label>บทบาท (Role)</label>
                    <select name="edit_role" id="edit_role" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <option value="user">User (สมาชิกทั่วไป)</option>
                        <option value="driver">Driver (คนขับรถ)</option>
                        <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <label>สถานะ KYC</label>
                    <select name="edit_kyc_status" id="edit_kyc_status" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <option value="unverified">Unverified (ยังไม่ยืนยัน)</option>
                        <option value="pending">Pending (รอตรวจสอบ)</option>
                        <option value="verified">Verified (ยืนยันแล้ว)</option>
                        <option value="rejected">Rejected (ปฏิเสธ)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <label>เลขบัตรประชาชน</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="edit_id_card" id="edit_id_card" class="form-control" maxlength="13" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <button type="button" id="btn_view_id_modal" class="btn-sm" style="background:#17a2b8; color:white; border:none; border-radius:4px; padding:0 10px; cursor:pointer; display:none;">
                            <i class="fas fa-image"></i> ดูรูป
                        </button>
                    </div>
                </div>

                <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
                <h4>ข้อมูลธนาคาร</h4>

                <div class="form-group" style="margin-bottom:10px;">
                    <label>ธนาคาร</label>
                    <select name="edit_bank_name" id="edit_bank_name" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <option value="">-- เลือก --</option>
                        <?php
                        $banks = ["กสิกรไทย", "ไทยพาณิชย์", "กรุงเทพ", "กรุงไทย", "กรุงศรี", "ทหารไทยธนชาต", "ออมสิน"];
                        foreach ($banks as $b) {
                            echo "<option value='$b'>$b</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>เลขที่บัญชี</label>
                        <input type="text" name="edit_bank_account" id="edit_bank_account" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>ชื่อบัญชี</label>
                        <input type="text" name="edit_bank_account_name" id="edit_bank_account_name" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                </div>

                <button type="submit" style="background: #ffc107; color: #212529; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%; font-weight: bold; margin-top:10px;">บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <script>
        function viewImage(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imgModal').style.display = 'block';
        }

        function openRejectModal(uid) {
            document.getElementById('reject_user_id').value = uid;
            document.getElementById('rejectModal').style.display = 'block';
        }


        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_id_card').value = user.id_card_number;
            document.getElementById('edit_bank_name').value = user.bank_name;
            document.getElementById('edit_bank_account').value = user.bank_account;
            document.getElementById('edit_bank_account_name').value = user.bank_account_name;
            document.getElementById('edit_role').value = user.role || 'user';
            document.getElementById('edit_kyc_status').value = user.kyc_status || 'unverified';

            // Show 'View ID' button if image exists
            var btnView = document.getElementById('btn_view_id_modal');
            if (user.id_card_image) {
                btnView.style.display = 'block';
                btnView.onclick = function() { // Safe closure
                    viewImage('../assets/images/uploads/' + user.id_card_image);
                };
            } else {
                btnView.style.display = 'none';
            }

            document.getElementById('editUserModal').style.display = 'block';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>