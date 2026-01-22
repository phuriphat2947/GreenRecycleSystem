<?php
session_start();
require_once '../db_connect/db_connect.php';

// Check Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'announcements'; // Set active sidebar item

// Handle Add/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $msg = $_POST['message'];
        $icon = $_POST['icon'];
        $type = $_POST['type'];

        $stmt = $conn->prepare("INSERT INTO announcements (message, icon, type) VALUES (:msg, :icon, :type)");
        $stmt->execute([':msg' => $msg, ':icon' => $icon, ':type' => $type]);
        $success = "เพิ่มประกาศเรียบร้อยแล้ว";
    } elseif (isset($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $success = "ลบประกาศเรียบร้อยแล้ว";
    }
}

// Fetch All
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการข่าวสาร (News Ticker) - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: var(--light-bg);
            margin-left: 250px;
            /* Width of sidebar */
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--secondary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            color: #f39c12;
            /* Warning color for Announcements icon specifically, or Primary */
        }

        /* Card Styles */
        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .card-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary);
        }

        /* Form Controls */
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eef2f7;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #fcfdfe;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 184, 148, 0.1);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }

        /* Table Styles */
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            text-align: left;
            padding: 15px;
            color: var(--text-light);
            font-weight: 600;
            border-bottom: 2px solid #f0f0f0;
        }

        .custom-table td {
            padding: 15px;
            border-bottom: 1px solid #f9f9f9;
            vertical-align: middle;
        }

        .custom-table tr:hover td {
            background-color: #f8fff9;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        /* Icon Preview */
        .icon-box {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <div class="admin-layout">
 
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร (News Ticker)</h1>
                <p style="color: #666; margin-top: 5px;">เพิ่มประกาศ ข้อความวิ่ง หรือโปรโมชั่นสั้นๆ เพื่อแจ้งเตือนสมาชิก</p>
            </div>

            <?php if (isset($success)): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border:1px solid #c3e6cb;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
 
            <div class="admin-card">
                <div class="card-header">เพิ่มประกาศใหม่</div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>ข้อความประกาศ</label>
                            <input type="text" name="message" class="form-control" required placeholder="เช่น โปรโมชั่นพิเศษ! รับแต้ม x2...">
                        </div>
                        <div class="form-group">
                            <label>ไอคอน (FontAwesome)</label>
                            <input type="text" name="icon" class="form-control" value="fas fa-bullhorn" placeholder="เช่น fas fa-bullhorn">
                        </div>
                        <div class="form-group">
                            <label>ประเภท (สี)</label>
                            <select name="type" class="form-control" style="cursor: pointer;">
                                <option value="info">🔵 Info (ฟ้า)</option>
                                <option value="success">🟢 Success (เขียว)</option>
                                <option value="warning">🟡 Warning (เหลือง)</option>
                                <option value="danger">🔴 Danger (แดง)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_announcement" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> ลงประกาศ
                    </button>
                </form>
            </div>
 
            <div class="admin-card">
                <div class="card-header">รายการประกาศปัจจุบัน</div>
                <div style="overflow-x: auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th width="80">Icon</th>
                                <th>ข้อความ</th>
                                <th width="150">ประเภท</th>
                                <th width="120">วันที่ลง</th>
                                <th width="100">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($announcements as $a): ?>
                                <tr>
                                    <td>
                                        <div class="icon-box">
                                            <i class="<?php echo $a['icon']; ?>"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($a['message']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $a['type']; ?>">
                                            <?php echo ucfirst($a['type']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.9rem; color: #888;">
                                        <?php echo date('d/m/Y', strtotime($a['created_at'])); ?>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('คุณแน่ใจว่าต้องการลบประกาศนี้?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" style="background:none; border:none; color:#e74c3c; cursor:pointer; font-size:1.1rem;" title="ลบ">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($announcements) == 0): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 30px; color: #999;">
                                        ยังไม่มีประกาศในขณะนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>

</html>