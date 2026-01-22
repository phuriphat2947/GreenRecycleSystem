<?php
session_start();
require_once "../db_connect/db_connect.php";

// Fetch Waste Types
try {
    $stmt = $conn->query("SELECT * FROM waste_types ORDER BY category DESC, id ASC");
    $waste_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $waste_types = [];
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการราคารับซื้อ - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 80px;
        }

        .price-table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .category-header {
            background: #f8f9fa;
            padding: 15px 20px;
            font-weight: bold;
            color: var(--primary);
            font-size: 1.1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-row {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .price-row:last-child {
            border-bottom: none;
        }

        .price-row:hover {
            background: #fcfcfc;
        }

        .item-icon {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-right: 15px;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: bold;
            color: #2c3e50;
            font-size: 1rem;
        }

        .item-desc {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .price-tags {
            display: flex;
            gap: 15px;
            text-align: right;
        }

        .price-badge {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            min-width: 100px;
        }

        .price-val {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .price-label {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .p-pickup {
            color: var(--primary);
        }

        .p-walkin {
            color: #e67e22;
        }

        @media (max-width: 768px) {
            .price-tags {
                flex-direction: column;
                gap: 5px;
            }

            .price-badge {
                align-items: flex-end;
                min-width: auto;
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="page-container">
        <div class="header-section" style="margin-bottom: 30px; text-align: center;">
            <h1 class="header-title"><i class="fas fa-tags" style="color: var(--primary);"></i> ตารางราคารับซื้อสินค้า</h1>
            <p class="header-subtitle">อัพเดทราคาล่าสุด ตรวจสอบได้ทั้งราคาหน้าโรงงานและบริการรับถึงบ้าน</p>
        </div>

        <?php
        $current_cat = '';
        $cat_icons = [
            'metal' => 'fa-cogs',
            'paper' => 'fa-box',
            'plastic' => 'fa-wine-bottle',
            'glass' => 'fa-wine-glass',
            'electronic' => 'fa-plug',
            'other' => 'fa-recycle'
        ];
        $cat_labels = [
            'metal' => 'โลหะ (Metal)',
            'paper' => 'กระดาษ (Paper)',
            'plastic' => 'พลาสติก (Plastic)',
            'glass' => 'แก้ว (Glass)',
            'electronic' => 'อิเล็กทรอนิกส์ (E-Waste)',
            'other' => 'อื่นๆ'
        ];

        // Group data first
        $grouped = [];
        foreach ($waste_types as $type) {
            $c = $type['category'] ?? 'other';
            $grouped[$c][] = $type;
        }

        foreach ($grouped as $cat => $items):
            $icon = $cat_icons[$cat] ?? 'fa-recycle';
            $label = $cat_labels[$cat] ?? ucfirst($cat);
        ?>
            <div class="price-table-card">
                <div class="category-header">
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
                </div>
                <?php foreach ($items as $item):
                    $img = !empty($item['image']) ? '../assets/images/uploads/' . $item['image'] : '../assets/images/logo.png';

                    // Fallback Logic for Image (Same as homepage)
                    if (empty($item['image']) || !file_exists('../assets/images/uploads/' . $item['image'])) {
                        $n = strtolower($item['name']);
                        if (strpos($n, 'paper') !== false) $img = 'https://cdn-icons-png.flaticon.com/512/2541/2541988.png';
                        else if (strpos($n, 'plastic') !== false) $img = 'https://cdn-icons-png.flaticon.com/512/2541/2541991.png';
                        else if (strpos($n, 'glass') !== false) $img = 'https://cdn-icons-png.flaticon.com/512/2541/2541993.png';
                        else if (strpos($n, 'metal') !== false || strpos($n, 'can') !== false) $img = 'https://cdn-icons-png.flaticon.com/512/2541/2541995.png';
                        else $img = 'https://cdn-icons-png.flaticon.com/512/9321/9321877.png';
                    }

                    $p_pickup = $item['pickup_price_per_kg'] > 0 ? $item['pickup_price_per_kg'] : $item['price_per_kg'] * 0.8;
                    $p_walkin = $item['price_per_kg'];
                ?>
                    <div class="price-row">
                        <img src="<?php echo $img; ?>" alt="icon" class="item-icon">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-desc"><?php echo htmlspecialchars($item['description'] ?? '-'); ?></div>
                        </div>
                        <div class="price-tags">
                            <div class="price-badge">
                                <span class="price-val p-pickup"><?php echo number_format($p_pickup, 2); ?></span>
                                <span class="price-label">รถรับ (Pickup)</span>
                            </div>
                            <div class="price-badge">
                                <span class="price-val p-walkin"><?php echo number_format($p_walkin, 2); ?></span>
                                <span class="price-label">นำส่งเอง (Walk-in)</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

    </div>

    <?php include 'footer.php'; ?>
</body>

</html>