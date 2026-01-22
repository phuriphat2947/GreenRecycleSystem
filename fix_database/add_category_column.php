<?php
require_once "../db_connect/db_connect.php";

try {
    // 1. Add Column if not exists
    $check = $conn->query("SHOW COLUMNS FROM waste_types LIKE 'category'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE waste_types ADD COLUMN category ENUM('metal', 'paper', 'plastic', 'glass', 'electronic', 'other') DEFAULT 'other' AFTER name");
        echo "Added 'category' column.<br>";
    }

    // 2. Update Categories based on names
    $updates = [
        'metal' => ['เหล็ก', 'สังกะสี', 'ทองแดง', 'ทองเหลือง', 'อลูมิเนียม', 'ตะกั่ว', 'สแตนเลส', 'คอมแอร์', 'มอเตอร์', 'แอร์', 'แบต'],
        'paper' => ['กระดาษ', 'กล่องนม'],
        'plastic' => ['พลาสติก', 'ขวดขุ่น', 'PET', 'ถุง', 'ท่อ', 'สายไฟ', 'สายยาง', 'อาครีลิค', 'CD'],
        'glass' => ['แก้ว', 'ขวดเบียร์', 'ขวดเหล้า'],
        'electronic' => ['ตู้เย็น', 'เครื่องซักผ้า', 'จอคอม'],
        'other' => ['เสื้อผ้า']
    ];

    foreach ($updates as $cat => $keywords) {
        foreach ($keywords as $kw) {
            $sql = "UPDATE waste_types SET category = :cat WHERE name LIKE :kw";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':cat' => $cat, ':kw' => "%$kw%"]);
        }
    }

    echo "Categories updated successfully.<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
