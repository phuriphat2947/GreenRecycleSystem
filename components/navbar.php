<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../db_connect/db_connect.php";

// Ensure we have user data if not already set by the parent page
if (!isset($user_name) || !isset($wallet_balance)) {
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        try {
            // Fetch Basic Info & Gamification Stats
            if (!isset($user_data)) {
                $stmt = $conn->prepare("SELECT username, membership_level, total_recycled_weight, spent_recycled_weight, total_carbon_saved, spent_carbon_saved, profile_image FROM users WHERE id = :id");
                $stmt->execute([':id' => $uid]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            require_once __DIR__ . '/../services/GamificationService.php';
            $gamification_nav = new GamificationService($conn);

            $user_name = $user_data['username'] ?? 'User';
            $user_level = $user_data['membership_level'] ?? 'seedling';

            // Available Calculations
            $total_weight = $user_data['total_recycled_weight'] ?? 0.00;
            $spent_weight = $user_data['spent_recycled_weight'] ?? 0.00;
            $avail_weight = $total_weight - $spent_weight;

            $total_carbon = $user_data['total_carbon_saved'] ?? 0.00;
            $spent_carbon = $user_data['spent_carbon_saved'] ?? 0.00;
            $avail_carbon = $total_carbon - $spent_carbon;

            $pid = $user_data['profile_image'] ?? 'default_avatar.png';
            $profile_image = (!empty($pid) && $pid != 'default_avatar.png') ? $pid : 'default_avatar.png';

            // Get Badge
            $badge_nav = $gamification_nav->getBadgeDetails($user_level);

            // Wallet
            if (!isset($wallet_balance)) {
                $bal_stmt = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
                $bal_stmt->execute([':uid' => $uid]);
                $wallet_balance = $bal_stmt->fetchColumn() ?: 0.00;
            }
        } catch (Exception $e) {
            // Fallback
            $user_name = "User";
            $wallet_balance = 0.00;
            $avail_weight = 0.0;
            $avail_carbon = 0.0;
            $profile_image = 'default_avatar.png';
        }
    }
}

// FIX: Safe defaults for guests or if DB fails
if (!isset($wallet_balance)) $wallet_balance = 0.00;
if (!isset($avail_weight)) $avail_weight = 0.0;
if (!isset($avail_carbon)) $avail_carbon = 0.0;
if (!isset($user_name)) $user_name = "Guest";
if (!isset($profile_image)) $profile_image = "default_avatar.png";

if (!isset($badge_nav)) {
    $badge_nav = [
        'name' => 'Member',
        'icon' => 'fas fa-user',
        'color' => '#666'
    ];
}

// Try to fetch specific badge if level exists (Logged in)
if (isset($user_level) && isset($_SESSION['user_id'])) {
    try {
        if (!class_exists('GamificationService')) {
            require_once __DIR__ . '/../services/GamificationService.php';
        }
        $gm_svc = isset($gamification) ? $gamification : (isset($gamification_nav) ? $gamification_nav : new GamificationService($conn));
        $badge_nav = $gm_svc->getBadgeDetails($user_level);
    } catch (Exception $e) {
    }
}
?>
<nav class="navbar">
    <a href="homepage.php" class="logo">Green<span>Digital</span></a>


    <!-- Navbar Quick Actions -->
    <div class="nav-actions">
        <a href="request_pickup.php" class="nav-action-btn" title="เรียกรถรับขยะ">
            <i class="fas fa-truck-pickup"></i>
            <span>เรียกรถ</span>
        </a>
        <a href="scan_hub.php" class="nav-action-btn" title="สแกน QR Code">
            <i class="fas fa-qrcode"></i>
            <span>สแกน</span>
        </a>
        <a href="redeem.php" class="nav-action-btn" title="ร้านค้าแลกแต้ม">
            <i class="fas fa-store"></i>
            <span>แลกรางวัล</span>
        </a>
        <a href="prices.php" class="nav-action-btn" title="เช็คราคาขยะ">
            <i class="fas fa-tags"></i>
            <span>เช็คราคา</span>
        </a>

        <?php

        $noti_count = 0;
        if (isset($_SESSION['user_id'])) {
            try {
                $n_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'waiting_confirm'");
                $n_stmt->execute([':uid' => $_SESSION['user_id']]);
                $noti_count = $n_stmt->fetchColumn();
            } catch (Exception $e) {
            }
        }
        ?>
        <!-- Notification Bell -->
        <div class="nav-action-btn" id="notification-btn" title="การแจ้งเตือน" style="position: relative; cursor: pointer;">
            <i class="fas fa-bell" id="bell-icon"></i>
            <span>แจ้งเตือน</span>
            <span id="notif-badge" style="display:none; position: absolute; top: -5px; right: 5px; background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.7rem; border: 2px solid white; font-weight: bold;">0</span>

            <!-- Dropdown -->
            <div id="notification-dropdown" class="notification-dropdown" style="display:none;">
                <div class="notif-header">การแจ้งเตือน</div>
                <div id="notif-list" class="notif-list">
                    <div style="padding:20px; text-align:center; color:#999;">กำลังโหลด...</div>
                </div>
                <a href="notifications.php" class="notif-footer">ดูทั้งหมด</a>
            </div>
        </div>

        <style>
            .notification-dropdown {
                position: absolute;
                top: 70px;
                left: -120px;
                /* Adjust for mobile/desktop */
                width: 320px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(15px);
                border-radius: 16px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
                z-index: 9999;
                border: 1px solid rgba(255, 255, 255, 0.8);
                animation: slideDown 0.3s ease;
            }

            @media (max-width: 768px) {
                .notification-dropdown {
                    position: fixed;
                    top: 60px;
                    left: 5%;
                    width: 90%;
                }
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .notif-header {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
                font-weight: 600;
                color: #2c3e50;
                background: rgba(46, 204, 113, 0.1);
            }

            .notif-list {
                max-height: 300px;
                overflow-y: auto;
            }

            .notif-item {
                padding: 12px 15px;
                border-bottom: 1px solid #f9f9f9;
                display: flex;
                gap: 12px;
                cursor: pointer;
                transition: background 0.2s;
                align-items: flex-start;
                text-align: left;
            }

            .notif-item:hover {
                background: #f0fdf4;
            }

            .notif-item.unread {
                background: #e8f8f5;
                font-weight: 500;
            }

            .notif-item .icon-circle {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background: #eee;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .notif-item .content {
                flex: 1;
            }

            .notif-item .title {
                font-size: 0.9rem;
                color: #333;
                margin-bottom: 2px;
            }

            .notif-item .msg {
                font-size: 0.8rem;
                color: #666;
                line-height: 1.3;
            }

            .notif-item .time {
                font-size: 0.7rem;
                color: #aaa;
                margin-top: 4px;
            }

            .notif-footer {
                display: block;
                padding: 10px;
                text-align: center;
                border-top: 1px solid #eee;
                color: #27ae60;
                font-size: 0.85rem;
                text-decoration: none;
                font-weight: 500;
            }

            .notif-footer:hover {
                background: #f9f9f9;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const btn = document.getElementById('notification-btn');
                const dropdown = document.getElementById('notification-dropdown');
                const badge = document.getElementById('notif-badge');
                const list = document.getElementById('notif-list');
                const bellIcon = document.getElementById('bell-icon');

                // Toggle Dropdown
                btn.addEventListener('click', function(e) {
                    if (e.target.closest('.notification-dropdown')) return; // Don't close if clicking inside
                    e.preventDefault();
                    if (dropdown.style.display === 'none') {
                        dropdown.style.display = 'block';
                        markVisibleAsRead(); // Logic to mark read visually or api call
                    } else {
                        dropdown.style.display = 'none';
                    }
                });

                // Close when clicking outside
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target)) {
                        dropdown.style.display = 'none';
                    }
                });

                // Fetch Notifications
                function fetchNotifications() {
                    fetch('../api/get_notifications.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update Badge
                                if (data.unread_count > 0) {
                                    badge.style.display = 'block';
                                    badge.innerText = data.unread_count;
                                    bellIcon.style.color = '#e74c3c';
                                    bellIcon.style.animation = 'swing 1s infinite alternate';
                                } else {
                                    badge.style.display = 'none';
                                    bellIcon.style.color = '';
                                    bellIcon.style.animation = '';
                                }

                                // Render List
                                if (data.notifications.length === 0) {
                                    list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">ไม่มีการแจ้งเตือน</div>';
                                } else {
                                    let html = '';
                                    data.notifications.forEach(n => {
                                        const iconColor = n.type === 'deposit' ? '#f1c40f' : (n.type === 'order_complete' ? '#2ecc71' : '#3498db');
                                        const iconClass = n.type === 'deposit' ? 'fa-wallet' : (n.type === 'order_complete' ? 'fa-check-circle' : 'fa-info-circle');
                                        const isUnread = n.is_read == 0 ? 'unread' : '';

                                        html += `
                                            <div class="notif-item ${isUnread}" onclick="markOneRead(${n.id})">
                                                <div class="icon-circle" style="background:${iconColor}20; color:${iconColor}">
                                                    <i class="fas ${iconClass}"></i>
                                                </div>
                                                <div class="content">
                                                    <div class="title">${n.title}</div>
                                                    <div class="msg">${n.message}</div>
                                                    <div class="time">${new Date(n.created_at).toLocaleString('th-TH')}</div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                    list.innerHTML = html;
                                }
                            }
                        })
                        .catch(err => console.error('Notif Error:', err));
                }

                // Initial Fetch & Poll
                fetchNotifications();
                setInterval(fetchNotifications, 15000); // Check every 15s

                // Function to mark individual read
                window.markOneRead = function(id) {
                    fetch('../api/read_notification.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            id: id
                        })
                    }).then(() => fetchNotifications());
                }

                function markVisibleAsRead() {
                    // Optional: Mark all as read when opening? Or just let user click.
                    // For now, let's just keep badges until clicked or "Mark All Read" button (not implemented)
                    // But common pattern is to clear badge on open.
                    // Let's NOT clear badge automatically, keep it persistent until interaction.
                }
            });
        </script>
        <a href="user_manual.php" class="nav-action-btn" title="คู่มือการใช้งาน" style="color:#8e44ad;">
            <i class="fas fa-book"></i>
            <span>คู่มือ</span>
        </a>
        <a href="history.php" class="nav-action-btn" title="ประวัติกิจกรรม" style="color:var(--secondary);">
            <i class="fas fa-history"></i>
            <span>ประวัติ</span>
        </a>
        <a href="support.php" class="nav-action-btn" title="ช่วยเหลือ & ติดต่อ" style="color:#f39c12;">
            <i class="fas fa-question-circle"></i>
            <span>ช่วยเหลือ</span>
        </a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="../admin/index.php" class="nav-action-btn" title="Admin Panel" style="color: var(--primary);">
                <i class="fas fa-user-shield"></i>
                <span>Admin</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="user-menu">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-stats">

                <!-- Wallet -->
                <div class="nav-stat-item" title="ยอดเงินสะสม">
                    <div>
                        <i class="fas fa-wallet" style="color:#f1c40f;"></i>
                        <span class="nav-stat-value">฿<?php echo number_format($wallet_balance, 2); ?></span>
                    </div>
                </div>

                <!-- Avail Weight -->
                <div class="nav-stat-item" title="คะแนนรีไซเคิลคงเหลือ">
                    <a href="history.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-recycle" style="color:#2ecc71;"></i>
                        <span class="nav-stat-value"><?php echo number_format($avail_weight, 1); ?> kg</span>
                    </a>
                </div>

                <!-- Avail Carbon -->
                <div class="nav-stat-item" title="Carbon Credit">
                    <a href="#" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-cloud" style="color:#3498db;"></i>
                        <span class="nav-stat-value"><?php echo number_format($avail_carbon, 1); ?></span>
                    </a>
                </div>

                <a href="profile.php" class="user-profile-link">
                    <div class="user-info">
                        <div class="text-info">
                            <span class="user-name">สวัสดีคุณ <?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-level" style="color: <?php echo $badge_nav['color']; ?>; font-weight: bold;">
                                <i class="<?php echo $badge_nav['icon']; ?>"></i> <?php echo $badge_nav['name']; ?>
                            </span>
                        </div>
                        <img src="<?php echo ($profile_image == 'default_avatar.png') ? 'https://via.placeholder.com/45' : '../assets/images/uploads/' . $profile_image; ?>" alt="Profile" class="nav-profile-img">
                    </div>
                </a>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
        <?php else: ?>
            <a href="../login.php" class="nav-action-btn" style="background:var(--primary); color:white; padding:8px 20px; border-radius:50px; text-decoration:none;">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </a>
            <a href="../register.php" class="nav-action-btn" style="background:transparent; border:1px solid var(--primary); color:var(--primary); padding:8px 20px; border-radius:50px; text-decoration:none; margin-left:10px;">
                สมัครสมาชิก
            </a>
        <?php endif; ?>
    </div>
</nav>