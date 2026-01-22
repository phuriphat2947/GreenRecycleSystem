<?php
require_once 'db_connect/db_connect.php';

// Data from Image (Pickup Price)
// Pricing Rule: Walk-in = Pickup * 1.10
$waste_data = [
    // Iron
    ['name' => 'เหล็กหนา/รวม', 'pickup' => 4.2, 'cat' => 'metal'],
    ['name' => 'กระป๋องสังกะสี', 'pickup' => 3.4, 'cat' => 'metal'],
    ['name' => 'สังกะสีแผ่น', 'pickup' => 2.8, 'cat' => 'metal'],
    ['name' => 'เหล็กหล่อ', 'pickup' => 1.7, 'cat' => 'metal'],
    ['name' => 'เหล็กเส้น/ลวด', 'pickup' => 1.3, 'cat' => 'metal'],

    // Paper
    ['name' => 'กระดาษลังน้ำตาล', 'pickup' => 2.2, 'cat' => 'paper'],
    ['name' => 'กระดาษขาวดำ', 'pickup' => 4.0, 'cat' => 'paper'],
    ['name' => 'กระดาษย่อย/เล่ม', 'pickup' => 1.2, 'cat' => 'paper'],
    ['name' => 'กระดาษหนังสือพิมพ์', 'pickup' => 4.4, 'cat' => 'paper'],
    ['name' => 'กล่องนม', 'pickup' => 5.4, 'cat' => 'paper'],

    // Plastic
    ['name' => 'พลาสติกรวม', 'pickup' => 3.3, 'cat' => 'plastic'],
    ['name' => 'พลาสติกรวมเล็ก', 'pickup' => 1.8, 'cat' => 'plastic'],
    ['name' => 'พลาสติกรวมดำ', 'pickup' => 2.0, 'cat' => 'plastic'],
    ['name' => 'พลาสติกกรอบ', 'pickup' => 2.7, 'cat' => 'plastic'],
    ['name' => 'พลาสติกกรอบติดเหล็ก', 'pickup' => 2.5, 'cat' => 'plastic'],
    ['name' => 'ขวดขุ่น (HDPE)', 'pickup' => 10.3, 'cat' => 'plastic'],
    ['name' => 'PET ใส', 'pickup' => 5.3, 'cat' => 'plastic'],
    ['name' => 'PET ใส (บด/สี)', 'pickup' => 6.3, 'cat' => 'plastic'],
    ['name' => 'PET สี', 'pickup' => 0.6, 'cat' => 'plastic'],
    ['name' => 'ถุงรวมสะอาด', 'pickup' => 4.1, 'cat' => 'plastic'],
    ['name' => 'ถุงซัก', 'pickup' => 1.5, 'cat' => 'plastic'],
    ['name' => 'ท่อ PVC ฟ้า', 'pickup' => 2.3, 'cat' => 'plastic'],
    ['name' => 'ท่อ PVC เทา/เหลือง', 'pickup' => 1.6, 'cat' => 'plastic'],
    ['name' => 'เปลือกสายไฟ/สายยาง', 'pickup' => 1.7, 'cat' => 'plastic'],
    ['name' => 'อาครีลิค', 'pickup' => 6.5, 'cat' => 'plastic'],
    ['name' => 'แผ่น CD', 'pickup' => 6.0, 'cat' => 'plastic'],
    ['name' => 'ถุงวิบวับ', 'pickup' => 10.0, 'cat' => 'plastic'],

    // Glass
    ['name' => 'แก้วแดง', 'pickup' => 0.4, 'cat' => 'glass'],
    ['name' => 'แก้วขาว', 'pickup' => 0.9, 'cat' => 'glass'],
    ['name' => 'แก้วเขียว', 'pickup' => 0.7, 'cat' => 'glass'],
    ['name' => 'แก้วรวม', 'pickup' => 0.3, 'cat' => 'glass'],
    ['name' => 'ขวดเบียร์ลีโอ', 'pickup' => 7.9, 'cat' => 'glass'],
    ['name' => 'ขวดเบียร์ช้าง (ใหม่)', 'pickup' => 9.4, 'cat' => 'glass'],
    ['name' => 'ขวดเหล้าขาวใหญ่', 'pickup' => 7.9, 'cat' => 'glass'],
    ['name' => 'ขวดเหล้าขาวเล็ก', 'pickup' => 10.9, 'cat' => 'glass'],
    ['name' => 'ขวดเบียร์สิงห์คอยาว', 'pickup' => 4.5, 'cat' => 'glass'],

    // Metals (Non-Ferrous)
    ['name' => 'ทองแดง 1', 'pickup' => 240.5, 'cat' => 'metal'],
    ['name' => 'ทองแดง 2', 'pickup' => 231.7, 'cat' => 'metal'],
    ['name' => 'ทองแดง 3', 'pickup' => 222.1, 'cat' => 'metal'],
    ['name' => 'ทองแดง 4', 'pickup' => 218.1, 'cat' => 'metal'],
    ['name' => 'ทองแดง 5', 'pickup' => 196.5, 'cat' => 'metal'],
    ['name' => 'ทองเหลืองหนา', 'pickup' => 147.7, 'cat' => 'metal'],
    ['name' => 'ทองเหลืองบาง', 'pickup' => 136.5, 'cat' => 'metal'],
    ['name' => 'ทองเหลืองติดเหล็ก', 'pickup' => 86.9, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมหนา', 'pickup' => 32.3, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมบาง', 'pickup' => 32.9, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมอัลลอย', 'pickup' => 26.3, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมกระป๋อง', 'pickup' => 40.1, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมกระป๋อง (บิด/เหยียบ)', 'pickup' => 41.1, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมจิ้บ', 'pickup' => 13.1, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมติดเหล็ก (<30%)', 'pickup' => 12.5, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมฝาจุกแกะ (M100)', 'pickup' => 25.1, 'cat' => 'metal'],
    ['name' => 'อลูมิเนียมล้อแม็ก', 'pickup' => 45.5, 'cat' => 'metal'],
    ['name' => 'หม้อน้ำ/ใส่อลูมิเนียม', 'pickup' => 27.5, 'cat' => 'metal'],
    ['name' => 'ตะกั่วแข็ง', 'pickup' => 30.5, 'cat' => 'metal'],
    ['name' => 'ตะกั่วอ่อน', 'pickup' => 29.9, 'cat' => 'metal'],
    ['name' => 'สแตนเลส (ดูดไม่ติด)', 'pickup' => 17.3, 'cat' => 'metal'],
    ['name' => 'คอมแอร์/มอเตอร์', 'pickup' => 9.1, 'cat' => 'metal'],
    ['name' => 'แอร์ (รวม)', 'pickup' => 15.0, 'cat' => 'metal'],
    ['name' => 'แบตขาว', 'pickup' => 15.6, 'cat' => 'metal'],
    ['name' => 'แบตดำ (ใหญ่)', 'pickup' => 11.8, 'cat' => 'metal'],
    ['name' => 'แบตเล็ก', 'pickup' => 14.2, 'cat' => 'metal'],

    // Others
    ['name' => 'ตู้เย็น/เครื่องซักผ้า', 'pickup' => 150, 'cat' => 'electronic'],
    ['name' => 'จอคอม 14-15 นิ้ว', 'pickup' => 20, 'cat' => 'electronic'],
    ['name' => 'จอคอม 21 นิ้ว+', 'pickup' => 30, 'cat' => 'electronic'],
    ['name' => 'เสื้อผ้ามือสอง', 'pickup' => 0.3, 'cat' => 'cloth'],
];

try {
    // 1. Clear old data (Try/Catch in case of FK constraint)
    try {
        $conn->exec("DELETE FROM waste_types");
        echo "Cleared old waste types.<br>";
        // Reset Auto Increment
        $conn->exec("ALTER TABLE waste_types AUTO_INCREMENT = 1");
    } catch (PDOException $e) {
        echo "Could not clear all old types (FK Constraint). Will insert new ones.<br>";
    }

    // 2. Insert New Data
    $sql = "INSERT INTO waste_types (name, pickup_price_per_kg, price_walkin, price_per_kg, image) VALUES (:name, :pickup, :walkin, :base_price, :img)";
    $stmt = $conn->prepare($sql);

    foreach ($waste_data as $item) {
        $walkin_price = $item['pickup'] * 1.10; // +10% Logic

        // Simple Image assignment based on Category
        $img = 'recycle_default.png';
        if ($item['cat'] == 'paper') $img = 'cardboard.png';
        if ($item['cat'] == 'plastic') $img = 'plastic.png';
        if ($item['cat'] == 'glass') $img = 'glass.png';
        if ($item['cat'] == 'metal') $img = 'metal.png';

        // Check duplicate
        $check = $conn->prepare("SELECT id FROM waste_types WHERE name = :name");
        $check->execute([':name' => $item['name']]);
        if ($check->rowCount() == 0) {
            $stmt->execute([
                ':name' => $item['name'],
                ':pickup' => $item['pickup'],
                ':walkin' => $walkin_price,
                ':base_price' => $walkin_price,
                ':img' => $img
            ]);
            echo "Added: " . $item['name'] . " (Pickup: {$item['pickup']}, Walkin: {$walkin_price})<br>";
        }
    }

    echo "<h1>Update Complete!</h1>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
