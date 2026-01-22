<?php
require_once __DIR__ . '/../db_connect/db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `user_notifications` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`user_id` int(11) NOT NULL,
`type` enum('deposit','level_up','order_complete','system') NOT NULL,
`title` varchar(100) NOT NULL,
`message` text NOT NULL,
`is_read` tinyint(1) DEFAULT 0,
`created_at` timestamp NOT NULL DEFAULT current_timestamp(),
PRIMARY KEY (`id`),
KEY `user_id` (`user_id`),
CONSTRAINT `notif_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->exec($sql);
    echo "Table 'user_notifications' created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
