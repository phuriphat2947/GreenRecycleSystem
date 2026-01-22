<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'waste_types';

// Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM waste_types WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: waste_types.php?msg=deleted");
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $error_message = "ไม่สามารถลบรายการนี้ได้ เนื่องจากมีการทำรายการซื้อขายไปแล้ว (ข้อมูลเชื่อมโยงกัน) \\n\\nแนะนำให้กด \"แก้ไข\" แล้วเปลี่ยนชื่อเป็น [ยกเลิก] แทนครับ";
        } else {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Waste Types
try {
    $stmt = $conn->query("SELECT * FROM waste_types ORDER BY id ASC");
    $waste_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการประเภทขยะ - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>จัดการประเภทขยะและราคา</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">

            <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                <a href="waste_manage.php" style="background: var(--primary); color: white; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus"></i> เพิ่มประเภทขยะ
                </a>
            </div>

            <div class="stats-grid">
                <?php if (count($waste_types) > 0): ?>
                    <?php foreach ($waste_types as $type): ?>
                        <div class="stat-card" style="display: block; padding: 0; overflow: hidden; height: auto;">
                            <!-- Image Section -->
                            <div style="height: 150px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <?php if ($type['image']): ?>
                                    <img src="../assets/images/uploads/<?php echo $type['image']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-recycle" style="font-size: 3rem; color: #ddd;"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div style="padding: 1.2rem;">
                                <?php
                                $cat_colors = [
                                    'metal' => ['bg' => '#e3f2fd', 'c' => '#1976d2', 'l' => 'โลหะ'],
                                    'paper' => ['bg' => '#efebe9', 'c' => '#795548', 'l' => 'กระดาษ'],
                                    'plastic' => ['bg' => '#fff3e0', 'c' => '#f57c00', 'l' => 'พลาสติก'],
                                    'glass' => ['bg' => '#e0f2f1', 'c' => '#00897b', 'l' => 'แก้ว'],
                                    'electronic' => ['bg' => '#f3e5f5', 'c' => '#8e24aa', 'l' => 'e-Waste'],
                                    'other' => ['bg' => '#f5f5f5', 'c' => '#616161', 'l' => 'อื่นๆ']
                                ];
                                $c_info = $cat_colors[$type['category']] ?? $cat_colors['other'];
                                ?>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-size: 0.75rem; background:<?php echo $c_info['bg']; ?>; color:<?php echo $c_info['c']; ?>; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                        <?php echo $c_info['l']; ?>
                                    </span>
                                </div>
                                <h4 style="margin-bottom: 0.5rem; color: var(--secondary); font-size: 1.2rem;"><?php echo htmlspecialchars($type['name']); ?></h4>
                                <div style="margin-bottom: 1rem;">
                                    <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary);">
                                        <?php echo number_format($type['price_per_kg'], 2); ?> <small style="font-size: 0.8rem; font-weight: 400; color: #666;">(Walk-in)</small>
                                    </div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #2ecc71;">
                                        <?php echo number_format($type['pickup_price_per_kg'] ?? ($type['price_per_kg'] * 0.8), 2); ?> <small style="font-size: 0.8rem; font-weight: 400; color: #666;">(Pickup)</small>
                                    </div>
                                    <span style="font-size: 0.8rem; color: var(--text-light);"> บาท/กก.</span>
                                </div>
                                <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem; min-height: 40px;">
                                    <?php echo !empty($type['description']) ? htmlspecialchars($type['description']) : '-'; ?>
                                </p>

                                <div style="display: flex; gap: 10px; border-top: 1px solid #eee; padding-top: 1rem;">
                                    <a href="waste_manage.php?id=<?php echo $type['id']; ?>" style="flex: 1; text-align: center; color: var(--primary); text-decoration: none; font-weight: 600; padding: 0.5rem; border: 1px solid var(--primary); border-radius: 6px;">แก้ไข</a>
                                    <a href="#" onclick="confirmDelete(<?php echo $type['id']; ?>)" style="flex: 1; text-align: center; color: var(--danger); text-decoration: none; font-weight: 600; padding: 0.5rem; border: 1px solid var(--danger); border-radius: 6px;">ลบ</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-light); background: white; border-radius: 12px;">ยังไม่มีข้อมูลประเภทขยะ</p>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "คุณต้องการลบประเภทขยะนี้หรือไม่? หากลบแล้วจะไม่สามารถกู้คืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `waste_types.php?action=delete&id=${id}`;
                }
            })
        }

        // Check for URL parameters for notifications
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'deleted') {
                Swal.fire(
                    'ลบสำเร็จ!',
                    'ประเภทขยะถูกลบเรียบร้อยแล้ว',
                    'success'
                ).then(() => {
                    // Clean URL
                    window.history.replaceState(null, null, window.location.pathname);
                });
            }
        }

        <?php if (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถลบได้',
                text: '<?php echo $error_message; ?>',
                footer: '<a href="waste_types.php">กลับหน้าหลัก</a>'
            });
        <?php endif; ?>
    </script>

</body>

</html>