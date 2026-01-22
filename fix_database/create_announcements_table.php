<?php
require_once "db_connect/db_connect.php";

$sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    icon VARCHAR(100) DEFAULT 'fas fa-bullhorn',
    type VARCHAR(20) DEFAULT 'info',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->exec($sql);
    echo "Table 'announcements' created successfully.";

    // Seed some data
    $seed = "INSERT INTO announcements (message, icon, type, status) VALUES 
    ('🌱 คุณ A เพิ่งรีไซเคิลขวดพลาสติก 5 กก.', 'fas fa-seedling', 'success', 'active'),
    ('🎉 ยินดีต้อนรับสมาชิกใหม่ GreenHero เข้าสู่ครอบครัว GreenDigital', 'fas fa-user-plus', 'info', 'active'),
    ('♻️ เป้าหมายชุมชนเดือนนี้: 5,000 กก. (เหลืออีก 800 กก.)', 'fas fa-bullseye', 'warning', 'active'),
    ('📢 โปรโมชั่น: รับแต้ม x2 เมื่อขายกระดาษลัง วันนี้เท่านั้น!', 'fas fa-bullhorn', 'danger', 'active'),
    ('🏆 คุณ TopRank ขึ้นเป็นอันดับ 1 ของสัปดาห์นี้', 'fas fa-trophy', 'warning', 'active')";

    $conn->exec($seed);
    echo " Seed data inserted.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
