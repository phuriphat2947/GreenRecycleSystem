<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'waste_types';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$item = [
    'name' => '',
    'category' => 'other',
    'description' => '',
    'price_per_kg' => '',
    'pickup_price_per_kg' => '',
    'image' => ''
];

// Fetch if Editing
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM waste_types WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) $item = $fetched;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $price = $_POST['price_per_kg'];
    $pickup_price = $_POST['pickup_price_per_kg'];

    // Image Upload
    $image = $item['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = "waste_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], "../assets/images/uploads/" . $new_name)) {
                $image = $new_name;
            }
        }
    }

    if ($id) {
        $sql = "UPDATE waste_types SET name = :name, category = :cat, description = :desc, price_per_kg = :price, pickup_price_per_kg = :pickup_price, image = :img WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO waste_types (name, category, description, price_per_kg, pickup_price_per_kg, image) VALUES (:name, :cat, :desc, :price, :pickup_price, :img)";
        $stmt = $conn->prepare($sql);
    }

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':cat', $category);
    $stmt->bindParam(':desc', $description);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':pickup_price', $pickup_price);
    $stmt->bindParam(':img', $image);

    if ($stmt->execute()) {
        header("Location: waste_types.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'แก้ไขข้อมูลขยะ' : 'เพิ่มประเภทขยะ'; ?> - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2><?php echo $id ? 'แก้ไขข้อมูลขยะ' : 'เพิ่มประเภทขยะใหม่'; ?></h2>
            </div>
            <div class="header-tools">
                <a href="waste_types.php" style="color: var(--text-light); text-decoration: none;"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            </div>
        </header>

        <main class="content-wrapper">
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow); max-width: 800px; margin: 0 auto;">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">ชื่อประเภทขยะ</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required placeholder="เช่น ขวดพลาสติกใส, กระป๋อง" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">หมวดหมู่</label>
                            <select name="category" required style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                                <?php
                                $cats = ['metal' => 'โลหะ (Metal)', 'paper' => 'กระดาษ (Paper)', 'plastic' => 'พลาสติก (Plastic)', 'glass' => 'แก้ว (Glass)', 'electronic' => 'อิเล็กทรอนิกส์ (E-Waste)', 'other' => 'อื่นๆ (Other)'];
                                foreach ($cats as $val => $label) {
                                    $sel = ($item['category'] == $val) ? 'selected' : '';
                                    echo "<option value='$val' $sel>$label</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">ราคารับซื้อหน้าร้าน (Walk-in)</label>
                            <input type="number" step="0.01" name="price_per_kg" value="<?php echo htmlspecialchars($item['price_per_kg']); ?>" required placeholder="0.00" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">ราคารับถึงบ้าน (Pickup Service)</label>
                            <input type="number" step="0.01" name="pickup_price_per_kg" value="<?php echo htmlspecialchars($item['pickup_price_per_kg'] ?? ($item['price_per_kg'] * 0.8)); ?>" required placeholder="0.00" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; background: #e8f5e9;">
                            <small style="color: #666;">*ปกติจะถูกกว่าราคาหน้าร้าน (แนะนำ 80%)</small>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">คำอธิบายเพิ่มเติม</label>
                        <textarea name="description" rows="4" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">รูปภาพประกอบ</label>
                        <input type="file" name="image" accept="image/*" style="width: 100%;">
                        <?php if ($item['image']): ?>
                            <img src="../assets/images/uploads/<?php echo $item['image']; ?>" style="margin-top: 10px; height: 100px; border-radius: 8px; object-fit: cover;">
                        <?php endif; ?>
                    </div>

                    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 1rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer;">บันทึกข้อมูล</button>
                </form>
            </div>
        </main>
    </div>

</body>

</html>