<?php
require_once 'db_connect/db_connect.php';

try {
    // 1. Rewards Table
    $sql_rewards = "CREATE TABLE IF NOT EXISTS rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT 'Reward Name',
        description TEXT NULL,
        points_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cost in Wallet Balance',
        image VARCHAR(255) DEFAULT 'default_reward.png',
        stock INT DEFAULT 0 COMMENT 'Available Stock',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql_rewards);
    echo "Table 'rewards' created successfully.<br>";

    // Seed Rewards
    $conn->exec("INSERT INTO rewards (name, description, points_cost, image, stock) VALUES 
        ('ถุงผ้ารักษ์โลก', 'ถุงผ้าดิบอย่างดี ลาย Green Digital', 50.00, 'reward_bag.png', 100),
        ('แก้วน้ำเก็บความเย็น', 'แก้วสแตนเลส เก็บความเย็นได้ 12 ชม.', 150.00, 'reward_cup.png', 50),
        ('คูปองส่วนลด 20 บาท', 'ใช้ลดค่าบริการขนส่งครั้งถัดไป', 20.00, 'reward_coupon.png', 999), 
        ('เมล็ดพันธุ์ผักสวนครัว', 'ชุดเมล็ดพันธุ์ผักสลัด 5 ชนิด', 30.00, 'reward_seed.png', 200)
        ON DUPLICATE KEY UPDATE name=name");
    echo "Seeded 'rewards' data.<br>";

    // 2. Chat Messages Table
    $sql_chat = "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'The user involved',
        admin_id INT NULL COMMENT 'Admin who replied (optional)',
        sender_type ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql_chat);
    echo "Table 'chat_messages' created successfully.<br>";

    // 3. Reward Redemptions (History)
    $sql_redemptions = "CREATE TABLE IF NOT EXISTS reward_redemptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reward_id INT NULL,
        points_used DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql_redemptions);
    echo "Table 'reward_redemptions' created successfully.<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
