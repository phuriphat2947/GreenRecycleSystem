<?php
require_once "db_connect/db_connect.php";

try {
    // 1. Convert Table Charset
    $conn->exec("ALTER TABLE announcements CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Table charset converted to utf8mb4.\n";

    // 2. Truncate (Clear bad data)
    $conn->exec("TRUNCATE TABLE announcements");
    echo "Old data cleared.\n";

    // 3. Re-insert with explicit charset
    $conn->exec("SET NAMES utf8mb4");

    $seed = "INSERT INTO announcements (message, icon, type, status) VALUES 
    ('🌱 คุณ A เพิ่งรีไซเคิลขวดพลาสติก 5 กก.', 'fas fa-seedling', 'success', 'active'),
    ('🎉 ยินดีต้อนรับสมาชิกใหม่ GreenHero เข้าสู่ครอบครัว GreenDigital', 'fas fa-user-plus', 'info', 'active'),
    ('♻️ เป้าหมายชุมชนเดือนนี้: 5,000 กก. (เหลืออีก 800 กก.)', 'fas fa-bullseye', 'warning', 'active'),
    ('📢 โปรโมชั่น: รับแต้ม x2 เมื่อขายกระดาษลัง วันนี้เท่านั้น!', 'fas fa-bullhorn', 'danger', 'active'),
    ('🏆 คุณ TopRank ขึ้นเป็นอันดับ 1 ของสัปดาห์นี้', 'fas fa-trophy', 'warning', 'active')";

    $conn->exec($seed);
    echo "Data re-seeded with correct encoding.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
