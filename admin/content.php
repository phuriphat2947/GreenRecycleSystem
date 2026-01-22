<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'content';

// Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM contents WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: content.php?msg=deleted");
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Fetch Contents
try {
    $stmt = $conn->query("SELECT * FROM contents ORDER BY created_at DESC");
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการข่าวสาร - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>จัดการข่าวสารและการประชาสัมพันธ์</h2>
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
                <a href="content_manage.php" style="background: var(--primary); color: white; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus"></i> เพิ่มข่าวใหม่
                </a>
            </div>

            <div class="stats-grid">
                <?php if (count($contents) > 0): ?>
                    <?php foreach ($contents as $news): ?>
                        <div class="stat-card" style="display: block; padding: 0; overflow: hidden; height: auto;">
                            <div style="height: 150px; background: #eee;">
                                <?php if ($news['image']): ?>
                                    <img src="../assets/images/uploads/<?php echo $news['image']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #aaa;">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.75rem; color: var(--text-light);"><?php echo date('d M Y', strtotime($news['created_at'])); ?></span>
                                    <span style="font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; background: <?php echo $news['status'] == 'published' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $news['status'] == 'published' ? '#155724' : '#721c24'; ?>;">
                                        <?php echo ucfirst($news['status']); ?>
                                    </span>
                                </div>
                                <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem; color: var(--secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($news['title']); ?></h4>
                                <p style="font-size: 0.9rem; color: var(--text-light); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 1rem;">
                                    <?php echo htmlspecialchars(strip_tags($news['body'])); ?>
                                </p>
                                <div style="display: flex; gap: 10px; border-top: 1px solid #eee; padding-top: 1rem;">
                                    <a href="content_manage.php?id=<?php echo $news['id']; ?>" style="flex: 1; text-align: center; color: var(--primary); text-decoration: none; font-weight: 600; padding: 0.5rem; border: 1px solid var(--primary); border-radius: 6px;">แก้ไข</a>
                                    <a href="#" onclick="confirmDeleteContent(<?php echo $news['id']; ?>)" style="flex: 1; text-align: center; color: var(--danger); text-decoration: none; font-weight: 600; padding: 0.5rem; border: 1px solid var(--danger); border-radius: 6px;">ลบ</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-light);">ยังไม่มีข่าวสารในระบบ</p>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        function confirmDeleteContent(id) {
            Swal.fire({
                title: 'ยืนยันการลบเนื้อหา?',
                text: "คุณต้องการลบข่าว/เนื้อหานี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `content.php?action=delete&id=${id}`;
                }
            })
        }
    </script>
</body>

</html>