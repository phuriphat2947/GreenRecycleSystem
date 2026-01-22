<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'content';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$item = [
    'title' => '',
    'body' => '',
    'status' => 'draft',
    'start_date' => '',
    'end_date' => '',
    'image' => ''
];

// Fetch if Editing
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM contents WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) $item = $fetched;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $body = trim($_POST['body']);
    $status = $_POST['status'];
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    // Image Upload
    $image = $item['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = "news_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], "../assets/images/uploads/" . $new_name)) {
                $image = $new_name;
            }
        }
    }

    if ($id) {
        $sql = "UPDATE contents SET title = :title, body = :body, status = :status, start_date = :sd, end_date = :ed, image = :img WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO contents (title, body, status, start_date, end_date, image) VALUES (:title, :body, :status, :sd, :ed, :img)";
        $stmt = $conn->prepare($sql);
    }

    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':body', $body);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':sd', $start_date);
    $stmt->bindParam(':ed', $end_date);
    $stmt->bindParam(':img', $image);

    if ($stmt->execute()) {
        header("Location: content.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวสาร'; ?> - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2><?php echo $id ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวใหม่'; ?></h2>
            </div>
            <div class="header-tools">
                <a href="content.php" style="color: var(--text-light); text-decoration: none;"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            </div>
        </header>

        <main class="content-wrapper">
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow); max-width: 800px; margin: 0 auto;">
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">หัวข้อข่าว</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">รายละเอียด</label>
                        <textarea name="body" rows="6" required style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($item['body']); ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">รูปภาพปก</label>
                            <input type="file" name="image" accept="image/*" style="width: 100%;">
                            <?php if ($item['image']): ?>
                                <img src="../assets/images/uploads/<?php echo $item['image']; ?>" style="margin-top: 10px; height: 100px; border-radius: 8px;">
                            <?php endif; ?>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">สถานะ</label>
                            <select name="status" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                                <option value="draft" <?php echo $item['status'] == 'draft' ? 'selected' : ''; ?>>แบบร่าง (Draft)</option>
                                <option value="published" <?php echo $item['status'] == 'published' ? 'selected' : ''; ?>>เผยแพร่ (Published)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">วันที่เริ่มแสดง (Option)</label>
                            <input type="datetime-local" name="start_date" value="<?php echo $item['start_date']; ?>" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">วันที่สิ้นสุด (Option)</label>
                            <input type="datetime-local" name="end_date" value="<?php echo $item['end_date']; ?>" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                    </div>

                    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 1rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer;">บันทึกข้อมูล</button>
                </form>
            </div>
        </main>
    </div>

</body>

</html>