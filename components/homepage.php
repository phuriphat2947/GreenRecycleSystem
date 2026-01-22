<?php
session_start();
require_once "../db_connect/db_connect.php"; // Ensure DB connection is available

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user data including profile image
try {
    // Fetch user data including profile image
    $stmt = $conn->prepare("SELECT username, membership_level, profile_image FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update session data to ensure consistency
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['level'] = $user_data['membership_level'];
    $profile_image = !empty($user_data['profile_image']) ? $user_data['profile_image'] : 'default_avatar.png';
} catch (PDOException $e) {
    // Fallback in case of error
    $profile_image = './assets/images/logo.png';
}

// Calculate Wallet Balance
try {
    $bal_stmt = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
    $bal_stmt->execute([':uid' => $_SESSION['user_id']]);
    $wallet_balance = $bal_stmt->fetchColumn() ?: 0.00;
} catch (PDOException $e) {
    $wallet_balance = 0.00;
}

// Calculate User Stats (Total - Spent)
try {
    // Get Lifetime Totals and Spent Amounts
    $stats_stmt = $conn->prepare("SELECT total_recycled_weight, spent_recycled_weight, total_carbon_saved, spent_carbon_saved FROM users WHERE id = :uid");
    $stats_stmt->execute([':uid' => $_SESSION['user_id']]);
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Lifetime Totals (For Levels/Badges)
    $lifetime_weight = $user_stats['total_recycled_weight'] ?? 0;

    // Available Balances (For Redemption/Display)
    $spent_weight = $user_stats['spent_recycled_weight'] ?? 0;
    $avail_weight = $lifetime_weight - $spent_weight;

    $lifetime_carbon = $user_stats['total_carbon_saved'] ?? 0;
    $spent_carbon = $user_stats['spent_carbon_saved'] ?? 0;
    $avail_carbon = $lifetime_carbon - $spent_carbon;
} catch (PDOException $e) {
    $lifetime_weight = 0;
    $avail_weight = 0;
    $lifetime_carbon = 0;
    $avail_carbon = 0;
}

// Check for Orders Waiting for Confirmation
$pending_confirms = [];
try {
    $conf_stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = :uid AND status = 'waiting_confirm'");
    $conf_stmt->execute([':uid' => $_SESSION['user_id']]);
    $pending_confirms = $conf_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Fetch Active Orders
$active_orders = [];
try {
    $track_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = :uid AND status IN ('pending', 'accepted', 'waiting_confirm', 'user_confirmed') ORDER BY created_at DESC");
    $track_stmt->execute([':uid' => $_SESSION['user_id']]);
    $active_orders = $track_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$user_name = $_SESSION['username'];
$user_level = $_SESSION['level'] ?? 'Seedling';

// --- LOGIC: Gamification & Level Service ---
require_once __DIR__ . '/../services/GamificationService.php';
$gamification = new GamificationService($conn);

// 1. Auto-Correct Level (If DB is outdated)
// 1. Auto-Correct Level (If DB is outdated)
$calculated_level = $gamification->calculateLevel($lifetime_weight);

// Normalize for comparison
$current_db_level = strtolower($user_data['membership_level'] ?? 'seedling');
$calc_norm = strtolower($calculated_level);

// 8. Smart Reward Recommendation (Goal Setting)
// Find a reward that the user can ALMOST afford (e.g., needs 1-20% more) to motivate them
$target_reward = null;
$target_diff = 0;
$target_type = ''; // 'money', 'point', 'gold'

try {
    // Fetch active rewards
    $r_stmt = $conn->query("SELECT * FROM rewards WHERE status = 'active' AND stock > 0 ORDER BY points_cost DESC");
    $all_rewards = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

    $closest_diff = 999999;
    $best_affordable = null;

    foreach ($all_rewards as $r) {
        $cost = floatval($r['points_cost']);
        if ($cost > $wallet_balance) {
            $diff = $cost - $wallet_balance;
            // If this reward is reachable (diff is small enough, e.g., within 500 baht)
            if ($diff < $closest_diff) {
                $closest_diff = $diff;
                $target_reward = $r;
                $target_diff = $diff;
                $target_type = 'money';
            }
        } else {
            // Track best affordable (first one since we ordered by cost DESC)
            if (!$best_affordable) {
                $best_affordable = $r;
            }
        }
    }

    // Fallback: If no target found (can afford all), show the best affordable one
    if (!$target_reward && $best_affordable) {
        $target_reward = $best_affordable;
        $target_diff = 0; // Already affordable
        $target_type = 'redeemable';
    }
} catch (Exception $e) {
}

// 9. Recent Community Activity (Social Proof)
// Show last 3 completed recycling orders
$recent_activities = [];
try {
    $act_sql = "SELECT o.total_weight, o.updated_at, u.username, o.id 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    WHERE o.status = 'completed' 
                    ORDER BY o.updated_at DESC LIMIT 5";
    $recent_activities = $conn->query($act_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// 10. Daily Tip (If not already set)
if (!isset($daily_tip)) {
    $tips = [
        "แยกฝาขวดก่อนทิ้ง ช่วยลดขั้นตอนการรีไซเคิลได้มหาศาล!",
        "กระดาษเปื้อนน้ำมัน ไม่สามารถรีไซเคิลได้นะ",
        "ซองขนมวิบวับ เป็นขยะกำพร้า รีไซเคิลยาก แต่ทำเชื้อเพลิงได้",
        "ล้างขวดพลาสติกนิดหน่อยช่วยลดกลิ่นและแมลงได้ดีเยี่ยม"
    ];
    $daily_tip = $tips[array_rand($tips)];
}

if ($current_db_level !== $calc_norm) {
    // UPDATE DATABASE IMMEDIATELY
    try {
        $upd = $conn->prepare("UPDATE users SET membership_level = :lvl WHERE id = :id");
        $upd->execute([':lvl' => $calculated_level, ':id' => $_SESSION['user_id']]);

        // Refresh Session & Local Var
        $_SESSION['level'] = $calculated_level;
        $user_level = ucfirst($calculated_level);

        // Refresh user_data array to keep it consistent
        $user_data['membership_level'] = $calculated_level;
    } catch (Exception $e) {
        // Silent fail or log
    }
} else {
    $user_level = ucfirst($user_data['membership_level']);
}

// 2. Level Progress Calculation
$progress_data = $gamification->calculateProgress($lifetime_weight);
$next_level_name = $progress_data['next_level'];
$next_level_target = $progress_data['target'];
$progress_percent = $progress_data['percent'];

// --- NEW: Engagement Features Data ---

// 1. Community Goal (Fake goal for now if DB is small, or real SUM)
try {
    $comm_stmt = $conn->query("SELECT SUM(total_recycled_weight) as total FROM users");
    $community_total = $comm_stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $community_total = 0;
}
$community_goal = 5000; // Target 5,000 kg
$community_percent = min(100, ($community_total / $community_goal) * 100);

// 2. Leaderboard (Top 3)
$leaderboard = [];
try {
    $lead_stmt = $conn->query("SELECT username, total_recycled_weight, profile_image, membership_level FROM users ORDER BY total_recycled_weight DESC LIMIT 3");
    $leaderboard = $lead_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}



// 4. Random Eco-Tip
$eco_tips = [
    "รู้หรือไม่? รีไซเคิลกระป๋องอลูมิเนียม 1 ใบ ประหยัดไฟดูทีวีได้ 3 ชั่วโมง!",
    "ขวดแก้วสามารถรีไซเคิลได้ 100% และวนซ้ำได้ไม่รู้จบ",
    "การแยกขยะเปียกออกจากขยะแห้ง ช่วยลดก๊าซมีเทนในบ่อฝังกลบได้มหาศาล",
    "ฝาขวดพลาสติก ควรแยกออกจากตัวขวดก่อนทิ้ง เพราะเป็นพลาสติกคนละชนิดกัน"
];
$daily_tip = $eco_tips[array_rand($eco_tips)];

// Fetch Waste Types for Pricing Grid
try {
    $price_stmt = $conn->query("SELECT * FROM waste_types ORDER BY category DESC, id ASC");
    $waste_types = $price_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $waste_types = [];
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GreenDigital Recycle</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <script src="https://code.jquery.com/jquery-4.0.0.js"
        integrity="sha256-9fsHeVnKBvqh3FB2HYu7g2xseAZ5MlN6Kz/qnkASV8U=" crossorigin="anonymous"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Pricing Grid Styles for Dashboard */
        /* Converted to Horizontal Slider */
        .price-grid {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            margin-bottom: 40px;
            padding: 10px 5px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .price-grid::-webkit-scrollbar {
            display: none;
        }

        .price-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #f0f0f0;
            transition: transform 0.2s;
            min-width: 160px;
            /* Fixed width for slider */
            width: 160px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .price-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary, #27ae60);
        }

        .price-card img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .price-card h3 {
            font-size: 0.9rem;
            margin: 0 0 5px;
            color: #333;
            white-space: normal;
            /* Allow wrapping */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            /* Show max 2 lines */
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
            height: 2.4em;
            /* Fixed height for alignment */
            width: 100%;
        }

        .price-card .price {
            font-size: 1rem;
            font-weight: bold;
            color: var(--primary, #27ae60);
        }

        /* Category Tabs */
        .category-tabs-container {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 5px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .category-tabs-container::-webkit-scrollbar {
            display: none;
        }

        .cat-tab {
            white-space: nowrap;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cat-tab.active {
            background: var(--primary, #27ae60);
            color: white;
            border-color: var(--primary, #27ae60);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }

        /* Slider Controls */
        .slider-controls-price {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: -10px;
            margin-bottom: 10px;
        }

        .p-btn {
            background: #eee;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            color: #555;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .p-btn:hover {
            background: #ddd;
        }

        .cat-tab:hover:not(.active) {
            background: #f8f9fa;
        }
    </style>
</head>

<body>

    <!-- Navigation Bar -->
    <?php include 'navbar.php'; ?>

    <!-- News Ticker -->
    <div class="news-ticker">
        <span class="news-label">ข่าวสารล่าสุด</span>
        <div class="ticker-content">
            <?php
            // Fetch Live Feed from DB
            $activities = [];
            try {
                // 1. Fetch from announcements table (Admin Controlled)
                $anno_stmt = $conn->query("SELECT * FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
                $announcements = $anno_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($announcements as $a) {
                    $color = '#2ecc71'; // Default success
                    if ($a['type'] == 'info')
                        $color = '#3498db';
                    if ($a['type'] == 'warning')
                        $color = '#f1c40f';
                    if ($a['type'] == 'danger')
                        $color = '#e74c3c';

                    $activities[] = "<i class='" . htmlspecialchars($a['icon']) . "' style='color:" . $color . ";'></i> " . htmlspecialchars($a['message']);
                }

                // 2. Fetch Recent Activities (Auto-generated from Orders) if not enough manual announcements
                if (count($activities) < 3) {
                    $act_sql = "SELECT o.total_weight, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'completed' ORDER BY o.updated_at DESC LIMIT 3";
                    $recent_acts = $conn->query($act_sql)->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($recent_acts as $ract) {
                        $activities[] = "<i class='fas fa-recycle' style='color:#27ae60;'></i> คุณ " . htmlspecialchars($ract['username']) . " ขายขยะ " . $ract['total_weight'] . " kg";
                    }
                }

                // Fallback if empty
                if (empty($activities)) {
                    $activities[] = "<i class='fas fa-leaf'></i> ยินดีต้อนรับสู่ GreenDigital Recycle";
                }
            } catch (Exception $e) {
                $activities[] = "Welcome to GreenDigital";
            }

            echo implode(" &nbsp;&nbsp;|&nbsp;&nbsp; ", $activities);
            ?>
        </div>
    </div>

    <!-- LIVE ACTIVITY MARQUEE -->
    <?php
    $marquee_items = [];
    try {
        // 1. Fetch Completed Orders (Sold Waste)
        $mq_sql = "SELECT u.username, u.profile_image, u.membership_level, 
                          o.total_weight as value, o.updated_at as time_action, 
                          'order' as type 
                   FROM orders o 
                   JOIN users u ON o.user_id = u.id 
                   WHERE o.status = 'completed' 
                   ORDER BY o.updated_at DESC LIMIT 10";
        $orders = $conn->query($mq_sql)->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Redemptions (Redeemed Rewards)
        $red_sql = "SELECT u.username, u.profile_image, u.membership_level, 
                           r.name as value, rr.created_at as time_action, 
                           'redeem' as type 
                    FROM reward_redemptions rr 
                    JOIN users u ON rr.user_id = u.id 
                    JOIN rewards r ON rr.reward_id = r.id
                    ORDER BY rr.created_at DESC LIMIT 10";
        $redemptions = $conn->query($red_sql)->fetchAll(PDO::FETCH_ASSOC);

        // 3. Merge and Sort
        $marquee_items = array_merge($orders, $redemptions);
        usort($marquee_items, function ($a, $b) {
            return strtotime($b['time_action']) - strtotime($a['time_action']); // Newest first
        });

        // Limit to 15 items total to keep it fresh
        $marquee_items = array_slice($marquee_items, 0, 15);
    } catch (Exception $e) {
    }
    ?>

    <?php if (!empty($marquee_items)): ?>
        <style>
            .live-feed-container {
                background: linear-gradient(90deg, #f0fdf4, #ffffff, #f0fdf4);
                border-bottom: 1px solid #e1e1e1;
                padding: 10px 0;
                overflow: hidden;
                position: relative;
                white-space: nowrap;
                height: 60px;
                display: flex;
                align-items: center;
            }

            .live-feed-track {
                display: inline-flex;
                animation: scroll-left 40s linear infinite;
                /* Slower for readability */
            }

            .live-feed-track:hover {
                animation-play-state: paused;
            }

            .feed-card {
                display: inline-flex;
                align-items: center;
                background: white;
                padding: 5px 15px 5px 5px;
                border-radius: 30px;
                margin-right: 30px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                border: 1px solid rgba(46, 204, 113, 0.2);
                transition: transform 0.2s;
            }

            .feed-card:hover {
                transform: scale(1.05);
                border-color: #2ecc71;
            }

            .feed-avatar {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #ddd;
                margin-right: 10px;
            }

            .feed-avatar.type-order {
                border-color: #2ecc71;
            }

            .feed-avatar.type-redeem {
                border-color: #f1c40f;
            }

            .feed-info {
                display: flex;
                flex-direction: column;
                line-height: 1.2;
            }

            .feed-name {
                font-size: 0.85rem;
                font-weight: bold;
                color: #333;
            }

            .feed-detail {
                font-size: 0.75rem;
                color: #666;
            }

            @keyframes scroll-left {
                0% {
                    transform: translateX(100vw);
                }

                100% {
                    transform: translateX(-100%);
                }
            }
        </style>

        <div class="live-feed-container">
            <div class="live-feed-track">
                <?php foreach ($marquee_items as $item): ?>
                    <?php
                    $m_img = !empty($item['profile_image']) ? '../assets/images/uploads/' . $item['profile_image'] : 'https://via.placeholder.com/35';
                    $time_diff = time() - strtotime($item['time_action']);
                    $time_ago = ($time_diff < 3600) ? floor($time_diff / 60) . " นาทีที่แล้ว" : (($time_diff < 86400) ? floor($time_diff / 3600) . " ชม. ที่แล้ว" : "เมื่อวานนี้");

                    if ($item['type'] == 'order') {
                        $icon = '<i class="fas fa-recycle" style="color:#2ecc71;"></i>';
                        $action_text = "ขายขยะ <b>" . $item['value'] . " kg</b>";
                        $border_class = "type-order";
                    } else {
                        $icon = '<i class="fas fa-gift" style="color:#f39c12;"></i>';
                        $action_text = "แลก <b>" . htmlspecialchars($item['value']) . "</b>";
                        $border_class = "type-redeem";
                    }
                    ?>
                    <div class="feed-card">
                        <img src="<?php echo $m_img; ?>" class="feed-avatar <?php echo $border_class; ?>"
                            onerror="this.src='https://via.placeholder.com/35'">
                        <div class="feed-info">
                            <span class="feed-name">
                                <?php echo htmlspecialchars($item['username']); ?>
                            </span>
                            <span class="feed-detail">
                                <?php echo $icon; ?> <?php echo $action_text; ?>
                                <span style="font-size:0.7em; color:#999; margin-left:5px;">(<?php echo $time_ago; ?>)</span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ACTION NEEDED ALERT -->
    <?php if (count($pending_confirms) > 0): ?>
        <div
            style="background: #c0392b; color: white; padding: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4); animation: pulse-red 2s infinite;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <i class="fas fa-bell" style="font-size: 2rem; color: #fff; animation: swing 1s infinite alternate;"></i>
                <div>
                    <strong style="font-size: 1.2rem;">🔴 แจ้งเตือน: มีรายการที่ต้องยืนยัน! (Action Required)</strong>
                    <div style="font-size: 0.95rem; opacity: 0.9; margin-top: 5px;">คนขับส่งงานแล้ว
                        กรุณาตรวจสอบยอดเงินและกดยืนยันเพื่อรับเงินเข้ากระเป๋า</div>
                </div>
            </div>
            <div>
                <?php foreach ($pending_confirms as $pc): ?>
                    <a href="confirm_order.php?id=<?php echo $pc['id']; ?>" class="nav-action-btn"
                        style="background: white; color: #c0392b; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1rem; margin-left: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        ยืนยัน Order #<?php echo $pc['id']; ?> <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <style>
                @keyframes pulse-red {
                    0% {
                        box-shadow: 0 0 0 0 rgba(192, 57, 43, 0.7);
                    }

                    70% {
                        box-shadow: 0 0 0 10px rgba(192, 57, 43, 0);
                    }

                    100% {
                        box-shadow: 0 0 0 0 rgba(192, 57, 43, 0);
                    }
                }

                @keyframes swing {
                    0% {
                        transform: rotate(0deg);
                    }

                    100% {
                        transform: rotate(30deg);
                    }
                }
            </style>
        </div>
    <?php endif; ?>

    <!-- Main Dashboard Container -->
    <div class="dashboard-container">
        <!-- News Slider -->
        <div class="news-slider-container">
            <div class="slider-wrapper">
                <?php

                try {
                    $news_sql = "SELECT * FROM contents WHERE status = 'published' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY created_at DESC LIMIT 5";
                    $news_stmt = $conn->query($news_sql);
                    $news_items = $news_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($news_items) > 0) {
                        $first = true;
                        foreach ($news_items as $news) {
                            $active_class = $first ? 'active' : '';
                            $img_path = !empty($news['image']) ? '../assets/images/uploads/' . $news['image'] : 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80';

                            echo '<div class="slide ' . $active_class . '">';
                            echo '<img src="' . htmlspecialchars($img_path) . '" alt="' . htmlspecialchars($news['title']) . '">';
                            echo '<div class="slide-caption">';
                            echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                            echo '<p>' . htmlspecialchars(strip_tags(mb_strimwidth($news['body'], 0, 100, "..."))) . '</p>';
                            echo '</div>';
                            echo '</div>';
                            $first = false;
                        }
                    } else {
                        echo '<div class="slide active">';
                        echo '<img src="https://images.unsplash.com/photo-1542601906990-b4d3fb7d5b43?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Welcome">';
                        echo '<div class="slide-caption">';
                        echo '<h3>ยินดีต้อนรับสู่ GreenDigital</h3>';
                        echo '<p>ร่วมรักษ์โลกไปกับเรา</p>';
                        echo '</div>';
                        echo '</div>';
                    }
                } catch (PDOException $e) {
                    echo "Error loading news.";
                }
                ?>
            </div>

            <button class="slider-btn prev-btn"><i class="fas fa-chevron-left"></i></button>
            <button class="slider-btn next-btn"><i class="fas fa-chevron-right"></i></button>

            <div class="slider-dots">
                <?php
                if (isset($news_items) && count($news_items) > 0) {
                    for ($i = 0; $i < count($news_items); $i++) {
                        $active_dot = ($i == 0) ? 'active' : '';
                        echo '<span class="dot ' . $active_dot . '" onclick="currentSlide(' . ($i + 1) . ')"></span>';
                    }
                } else {
                    echo '<span class="dot active" onclick="currentSlide(1)"></span>';
                }
                ?>
            </div>
        </div>
        <!-- 1. Hero Greeting Section -->
        <div class="hero-greeting-card">
            <div class="greeting-text">
                <h1>สวัสดีคุณ <?php echo htmlspecialchars($user_name); ?> <i class="fa-solid fa-face-grin-hearts"></i>
                </h1>
                <p>วันนี้คุณช่วยโลกให้น่าอยู่ขึ้น "1 ขั้นตอน" แล้วนะ</p>
                <div style="display:flex; gap:10px;">
                    <a href="redeem.php" class="hero-btn" style="color:#e67e22;"><i class="fas fa-gift"></i>
                        แลกรางวัล</a>
                    <a href="#impact" class="hero-btn" style="color:#2ecc71; background:#f0fdf4;"><i
                            class="fas fa-leaf"></i> ดูคะแนนรักษ์โลก</a>
                </div>
            </div>

            <div class="hero-stats">
                <div class="hero-stat-item">
                    <span class="hero-stat-value">฿<?php echo number_format($wallet_balance, 2); ?></span>
                    <span class="hero-stat-label">ยอดเงินสะสม</span>
                </div>
                <div class="hero-stat-item">
                    <span class="hero-stat-value"><?php echo number_format($avail_weight, 1); ?> kg</span>
                    <span class="hero-stat-label">ขยะรีไซเคิล</span>
                </div>
            </div>

            <!-- User Profile Image -->
            <div class="hero-profile-wrapper">
                <img src="<?php echo ($profile_image == 'default_avatar.png') ? 'https://via.placeholder.com/150' : '../assets/images/uploads/' . $profile_image; ?>"
                    class="hero-profile-img" alt="Profile Image">
            </div>

            <!-- MOVED: Level Progress (Integrated into Hero safely) -->
            <?php if ($next_level_target): ?>
                <div class="hero-level-progress"
                    style="position: absolute; bottom: 30px; left: 40px; right: 40px; z-index: 2;">
                    <div
                        style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:5px; color: white;">
                        <div>
                            <h3 style="margin:0; font-size:1rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);"><i
                                    class="fas fa-trophy" style="color:#f1c40f;"></i> ระดับ:
                                <strong><?php echo ucfirst($user_level); ?></strong>
                            </h3>
                        </div>
                        <div style="font-weight:bold; font-size: 0.9rem;">
                            <?php echo number_format($progress_percent, 0); ?>%
                        </div>
                    </div>
                    <div
                        style="width:100%; background:rgba(255,255,255,0.3); border-radius:50px; height:6px; overflow:hidden;">
                        <div
                            style="width:<?php echo $progress_percent; ?>%; background: white; height:100%; border-radius:50px; box-shadow: 0 0 5px rgba(255,255,255,0.8);">
                        </div>
                    </div>
                    <p style="margin:5px 0 0; font-size:0.8rem; opacity: 0.95; color: white;">
                        อีก <strong><?php echo number_format($next_level_target - $lifetime_weight, 1); ?> kg</strong>
                        &#8594; <span style="font-weight:bold; color: #fff;"><?php echo $next_level_name; ?></span>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Quick Action Grid -->
        <div class="quick-action-grid">
            <a href="request_pickup.php" class="action-card action-pickup">
                <div class="action-icon-circle"><i class="fas fa-truck-pickup"></i></div>
                <span class="action-label">เรียกรถรับขยะ</span>
            </a>
            <a href="prices.php" class="action-card action-price">
                <div class="action-icon-circle"><i class="fas fa-tags"></i></div>
                <span class="action-label">เช็คราคาขยะรีไซเคิล</span>
            </a>
            <a href="redeem.php" class="action-card action-redeem">
                <div class="action-icon-circle"><i class="fas fa-gift"></i></div>
                <span class="action-label">แลกของรางวัล</span>
            </a>
            <a href="withdrawal.php" class="action-card action-wallet">
                <div class="action-icon-circle"><i class="fas fa-wallet"></i></div>
                <span class="action-label">ถอนเงิน</span>
            </a>
            <a href="support.php" class="action-card action-support"
                style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color:white;">
                <div class="action-icon-circle" style="background:rgba(255,255,255,0.2); color:white;"><i
                        class="fas fa-headset"></i></div>
                <span class="action-label" style="color:white;">ช่วยเหลือ</span>
            </a>
        </div>

        <!-- 2.5 Community Goal (New) -->
        <div class="community-goal-section">
            <div class="community-header">
                <h3><i class="fas fa-users" style="color:#e67e22;"></i> พลังรวมชาว Green Digital <i
                        class="fas fa-globe-asia" style="color:#3498db;"></i></h3>
                <span><?php echo number_format($community_total); ?> / <?php echo number_format($community_goal); ?>
                    kg</span>
            </div>
            <div class="goal-progress-container">
                <div class="goal-progress-bar" style="width: <?php echo $community_percent; ?>%;">
                    <?php echo number_format($community_percent, 1); ?>%
                </div>
            </div>
        </div>

        <!-- 2.6 Engagement Grid (New) -->
        <div class="engagement-grid">
            <!-- Left: Leaderboard -->
            <div class="leaderboard-card">
                <h4 style="margin-bottom:15px; color:var(--secondary);"><i class="fas fa-trophy"
                        style="color:#f1c40f;"></i> Top 3 ผู้พิทักษ์โลก</h4>
                <div class="leaderboard-list">
                    <?php $rank = 1; ?>
                    <?php foreach ($leaderboard as $leader): ?>
                        <div class="leader-item">
                            <span class="leader-rank rank-<?php echo $rank; ?>">#<?php echo $rank; ?></span>
                            <img src="<?php echo !empty($leader['profile_image']) ? '../assets/images/uploads/' . $leader['profile_image'] : 'https://via.placeholder.com/45'; ?>"
                                class="leader-img" alt="User">
                            <div class="leader-info">
                                <span class="leader-name"><?php echo htmlspecialchars($leader['username']); ?></span>
                                <span
                                    style="font-size:0.8rem; color:#999;"><?php echo ucfirst($leader['membership_level'] ?? 'User'); ?></span>
                            </div>
                            <span class="leader-score"><?php echo number_format($leader['total_recycled_weight']); ?>
                                kg</span>
                        </div>
                        <?php $rank++; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Mission, Goal & Activity -->
            <div class="side-column">

                <!-- 1. Target Reward Card (New) -->
                <?php if ($target_reward): ?>
                    <div class="common-card goal-card"
                        style="background: linear-gradient(135deg, #fff, #f3e5f5); border: 1px solid #e1bee7;">
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <div>
                                <?php if ($target_type == 'redeemable'): ?>
                                    <h4 style="margin:0; color:#27ae60;"><i class="fas fa-gift"></i> แลกได้เลย!</h4>
                                    <p style="font-size:0.85rem; margin:5px 0; color:#555;">
                                        ยอดสะสมเพียงพอสำหรับ:
                                    </p>
                                <?php else: ?>
                                    <h4 style="margin:0; color:#8e44ad;"><i class="fas fa-bullseye"></i> เป้าหมายต่อไป</h4>
                                    <p style="font-size:0.85rem; margin:5px 0; color:#555;">
                                        ขาดอีก <strong>฿<?php echo number_format($target_diff, 0); ?></strong> แลกได้:
                                    </p>
                                <?php endif; ?>
                                <div style="font-weight:bold; color:#2c3e50; font-size:1rem;">
                                    <?php echo htmlspecialchars($target_reward['name']); ?>
                                </div>
                            </div>
                            <img src="<?php echo !empty($target_reward['image']) ? '../assets/images/uploads/' . $target_reward['image'] : 'https://cdn-icons-png.flaticon.com/512/2829/2829824.png'; ?>"
                                style="width:50px; height:50px; object-fit:contain;">
                        </div>
                        <div style="margin-top:10px;">
                            <a href="redeem.php"
                                style="font-size:0.8rem; text-decoration:none; color:#8e44ad; font-weight:600;">ดูของรางวัลทั้งหมด
                                <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 2. Live Activity Feed (New) -->
                <div class="common-card activity-feed-card">
                    <h4 style="margin:0 0 15px; font-size:1rem; color:#2c3e50;"><i class="fas fa-bolt"
                            style="color:#f1c40f;"></i> กิจกรรมล่าสุด</h4>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $act): ?>
                            <div class="activity-item"
                                style="display:flex; gap:10px; margin-bottom:12px; align-items:center; border-bottom:1px solid #f0f0f0; padding-bottom:8px;">
                                <div
                                    style="width:35px; height:35px; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#2ecc71;">
                                    <i class="fas fa-recycle" style="font-size:0.8rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-size:0.85rem;">
                                        <strong><?php echo htmlspecialchars($act['username']); ?></strong> ขายขยะ
                                    </div>
                                    <div style="font-size:0.75rem; color:#999;">
                                        <?php echo number_format($act['total_weight'], 1); ?> kg &bull;
                                        <?php echo date('H:i', strtotime($act['updated_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_activities)): ?>
                            <p style="color:#999; font-size:0.9rem; text-align:center;">ยังไม่มีกิจกรรมเร็วๆ นี้</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Eco Tip -->
                <div class="common-card tip-card">
                    <div style="display:flex; gap:15px; align-items:flex-start;">
                        <i class="far fa-lightbulb" style="font-size:1.5rem;"></i>
                        <div>
                            <h4 style="margin:0 0 5px;">เกร็ดความรู้</h4>
                            <p style="font-size:0.9rem; margin:0; opacity:0.9; line-height:1.4;">
                                "<?php echo $daily_tip; ?>"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Impact Section -->
        <div class="impact-section" id="impact">
            <div class="impact-header">
                <h2><i class="fas fa-globe-asia" style="color:#2ecc71;"></i> ผลงานรักษ์โลกของคุณ</h2>
                <p>การแยกขยะของคุณ สร้างการเปลี่ยนแปลงที่ยิ่งใหญ่ได้!</p>
            </div>
            <!-- Modified: Show only Available Weight and Carbon Credit -->
            <div class="impact-grid" style="grid-template-columns: repeat(2, 1fr);">
                <!-- Card 1: Available Weight -->
                <div class="impact-item">
                    <i class="fas fa-recycle impact-icon" style="color: #27ae60;"></i>
                    <span class="impact-value"><?php echo number_format($avail_weight, 1); ?> kg</span>
                    <span class="impact-desc">น้ำหนักคงเหลือ (ใช้แลก)</span>
                </div>

                <!-- Card 2: Carbon Credit -->
                <div class="impact-item">
                    <i class="fas fa-leaf impact-icon" style="color: #2ecc71;"></i>
                    <span class="impact-value"><?php echo number_format($avail_carbon, 1); ?> Credit</span>
                    <span class="impact-desc">Carbon Credit (คงเหลือ)</span>
                </div>
            </div>
        </div>

        <!-- Active Orders Tracking (Existing) -->
        <?php if (count($active_orders) > 0): ?>
            <div
                style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0; color:#333;"><i class="fas fa-shipping-fast" style="color:var(--primary);"></i>
                    สถานะคำขอของคุณ</h3>
                <?php foreach ($active_orders as $bo): ?>
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding:15px 0;">
                        <div>
                            <div style="font-weight:bold;">Order #<?php echo $bo['id']; ?></div>
                            <div style="font-size:0.9rem; color:#666;">
                                <?php echo date('d M Y, H:i', strtotime($bo['pickup_date'] . ' ' . $bo['pickup_time'])); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <?php
                            $status_color = '#999';
                            $status_text = $bo['status'];
                            switch ($bo['status']) {
                                case 'pending':
                                    $status_color = '#f39c12';
                                    $status_text = 'รอคนขับรับงาน';
                                    break;
                                case 'accepted':
                                    $status_color = '#3498db';
                                    $status_text = 'คนขับกำลังเดินทาง';
                                    break;
                                case 'waiting_confirm':
                                    $status_color = '#e74c3c';
                                    $status_text = 'รอคุณยืนยัน (Action Needed)';
                                    break;
                                case 'user_confirmed':
                                    $status_color = '#2ecc71';
                                    $status_text = 'ยืนยันแล้ว (รอเงินเข้า)';
                                    break;
                            }
                            ?>
                            <span
                                style="display:inline-block; padding:5px 10px; border-radius:15px; background:<?php echo $status_color; ?>; color:white; font-size:0.85rem;">
                                <?php echo $status_text; ?>
                            </span>
                            <?php if ($bo['status'] == 'waiting_confirm'): ?>
                                <div style="margin-top:5px;">
                                    <a href="confirm_order.php?id=<?php echo $bo['id']; ?>"
                                        style="color:var(--primary); font-weight:bold; text-decoration:underline;">
                                        ตรวจสอบและยืนยัน
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>





        <!-- Pricing Section -->
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 class="section-title" id="price-section" style="margin-bottom:10px;">ราคารับซื้อขยะวันนี้ ♻️</h2>
            <div class="slider-controls-price">
                <button class="p-btn" onclick="scrollPrice(-200)"><i class="fas fa-chevron-left"></i></button>
                <button class="p-btn" onclick="scrollPrice(200)"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <!-- Category Filter Tabs -->
        <div class="category-tabs-container">
            <button type="button" class="cat-tab active" onclick="filterCategory('all')">ทั้งหมด</button>
            <button type="button" class="cat-tab" onclick="filterCategory('paper')"><i class="fas fa-box"></i>
                กระดาษ</button>
            <button type="button" class="cat-tab" onclick="filterCategory('plastic')"><i class="fas fa-wine-bottle"></i>
                พลาสติก</button>
            <button type="button" class="cat-tab" onclick="filterCategory('metal')"><i class="fas fa-cogs"></i>
                โลหะ</button>
            <button type="button" class="cat-tab" onclick="filterCategory('glass')"><i class="fas fa-wine-glass"></i>
                แก้ว</button>
            <button type="button" class="cat-tab" onclick="filterCategory('electronic')"><i class="fas fa-plug"></i>
                e-Waste</button>
            <button type="button" class="cat-tab" onclick="filterCategory('other')"><i class="fas fa-ellipsis-h"></i>
                อื่นๆ</button>
        </div>

        <div class="price-grid">
            <?php foreach ($waste_types as $type): ?>
                <?php
                $cat = $type['category'] ?? 'other';
                // Show Pickup Price
                $price = $type['pickup_price_per_kg'] > 0 ? $type['pickup_price_per_kg'] : $type['price_per_kg'];

                // Fallback Logic for Image
                $image_filename = $type['image'] ?? '';
                $server_path = __DIR__ . '/../assets/images/uploads/' . $image_filename;

                if (!empty($image_filename) && file_exists($server_path)) {
                    $display_img = '../assets/images/uploads/' . $image_filename;
                } else {
                    // Fallback Icons based on Category/Name
                    $n = strtolower($type['name']);
                    if (strpos($n, 'paper') !== false || strpos($n, 'กระดาษ') !== false)
                        $display_img = 'https://cdn-icons-png.flaticon.com/512/2541/2541988.png';
                    else if (strpos($n, 'plastic') !== false || strpos($n, 'พลาสติก') !== false)
                        $display_img = 'https://cdn-icons-png.flaticon.com/512/2541/2541991.png';
                    else if (strpos($n, 'glass') !== false || strpos($n, 'แก้ว') !== false || strpos($n, 'ขวด') !== false)
                        $display_img = 'https://cdn-icons-png.flaticon.com/512/2541/2541993.png';
                    else if (strpos($n, 'metal') !== false || strpos($n, 'can') !== false || strpos($n, 'โลหะ') !== false || strpos($n, 'เหล็ก') !== false || strpos($n, 'อลูมิเนียม') !== false)
                        $display_img = 'https://cdn-icons-png.flaticon.com/512/2541/2541995.png';
                    else
                        $display_img = 'https://cdn-icons-png.flaticon.com/512/9321/9321877.png';
                }
                ?>
                <div class="price-card" data-category="<?php echo $cat; ?>">
                    <img src="<?php echo $display_img; ?>" alt="<?php echo htmlspecialchars($type['name']); ?>">
                    <h3><?php echo htmlspecialchars($type['name']); ?></h3>
                    <div class="price"><?php echo number_format($price, 2); ?> ฿/กก.</div>
                </div>
            <?php endforeach; ?>
        </div>


        <h2 class="section-title">สถิติการรีไซเคิล</h2>
        <div class="charts-grid">

            <div class="chart-card">
                <h4><i class="fas fa-chart-bar"></i> กิจกรรมรายสัปดาห์</h4>
                <div style="position: relative; height: 300px;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h4><i class="fas fa-chart-pie"></i> ประเภทขยะที่แยกได้</h4>
                <div style="position: relative; height: 300px;">
                    <canvas id="compositionChart"></canvas>
                </div>
            </div>
        </div>



    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Dashboard Scripts -->
    <script>
        const priceGrid = document.querySelector('.price-grid');
        let scrollSpeed = 0.8; // Increased for better visibility
        let isPaused = false;
        let animationId;
        let originalContentWidth = 0;

        // 1. Clone items for seamless infinite scroll
        function cloneItems() {
            // Remove existing clones first
            const existingClones = document.querySelectorAll('.price-card.clone');
            existingClones.forEach(el => el.remove());

            const items = Array.from(priceGrid.children);
            if (items.length === 0) return;

            // Calculate precise width of original content
            // We sum (offsetWidth + column-gap) for each item
            const style = window.getComputedStyle(priceGrid);
            const gap = parseFloat(style.columnGap) || parseFloat(style.gap) || 15; // Default 15px from CSS

            originalContentWidth = 0;
            items.forEach(item => {
                originalContentWidth += item.offsetWidth + gap;
            });

            // Clone items
            // We clone enough to ensure we can scroll at least one full width
            items.forEach(item => {
                const clone = item.cloneNode(true);
                clone.classList.add('clone'); // Mark as clone
                priceGrid.appendChild(clone);
            });

            // Double buffering if content is small (optional, but good for wide screens)
            if (originalContentWidth < window.innerWidth) {
                items.forEach(item => {
                    const clone = item.cloneNode(true);
                    clone.classList.add('clone');
                    priceGrid.appendChild(clone);
                });
            }
        }

        // 2. Continuous Scroll Animation
        function animateScroll() {
            if (!isPaused && originalContentWidth > 0) {
                priceGrid.scrollLeft += scrollSpeed;

                // Seamless Loop Logic
                // When we have scrolled past the entire original set, snap back by subtracting that exact width
                if (priceGrid.scrollLeft >= originalContentWidth) {
                    priceGrid.scrollLeft -= originalContentWidth;
                }
            }
            animationId = requestAnimationFrame(animateScroll);
        }

        // Manual Scroll Buttons
        function scrollPrice(offset) {
            priceGrid.scrollBy({
                left: offset,
                behavior: 'smooth'
            });
        }

        // Init
        if (priceGrid) {
            // Run after slight delay to ensure layout is stable
            setTimeout(() => {
                cloneItems();
                animateScroll();
            }, 100);

            // Pause interactions
            priceGrid.addEventListener('mouseenter', () => isPaused = true);
            priceGrid.addEventListener('mouseleave', () => isPaused = false);
            priceGrid.addEventListener('touchstart', () => isPaused = true);
            priceGrid.addEventListener('touchend', () => setTimeout(() => isPaused = false, 2000));
        }

        // Category Filtering
        function filterCategory(cat) {
            // Update Tab UI
            document.querySelectorAll('.cat-tab').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.cat-tab').classList.add('active');

            // Apply filter to ALL items (originals + clones)
            let items = document.querySelectorAll('.price-card');

            items.forEach(item => {
                if (cat === 'all' || item.dataset.category === cat) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });

            // Reset scroll position to avoid getting stuck in empty space
            if (priceGrid) priceGrid.scrollLeft = 0;
        }
    </script>
    <script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

    <!-- Chat Widget -->
    <?php include 'chat_widget.php'; ?>
</body>

</html>