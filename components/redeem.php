<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// 1. Get Balances
function getWalletBalance($conn, $uid)
{
    $stmt = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
    $stmt->execute([':uid' => $uid]);
    return $stmt->fetchColumn() ?: 0.00;
}
$wallet_balance = getWalletBalance($conn, $user_id);

$stmt_u = $conn->prepare("SELECT total_recycled_weight, spent_recycled_weight, total_carbon_saved, spent_carbon_saved FROM users WHERE id = :uid");
$stmt_u->execute([':uid' => $user_id]);
$u_data = $stmt_u->fetch(PDO::FETCH_ASSOC);

$total_weight = $u_data['total_recycled_weight'] ?? 0;
$spent_weight = $u_data['spent_recycled_weight'] ?? 0;
$avail_weight = $total_weight - $spent_weight;

$total_carbon = $u_data['total_carbon_saved'] ?? 0;
$spent_carbon = $u_data['spent_carbon_saved'] ?? 0;
$avail_carbon = $total_carbon - $spent_carbon;

// Handle Redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_id'])) {
    $reward_id = $_POST['redeem_id'];

    try {
        $conn->beginTransaction();
        $stmt_r = $conn->prepare("SELECT * FROM rewards WHERE id = :id AND status = 'active' AND stock > 0");
        $stmt_r->execute([':id' => $reward_id]);
        $reward = $stmt_r->fetch(PDO::FETCH_ASSOC);

        if ($reward) {
            // Check Affordability
            $cp = floatval($reward['points_cost']);
            $cw = floatval($reward['weight_cost']);
            $cc = floatval($reward['carbon_cost']);

            $can = true;
            if ($wallet_balance < $cp) $can = false;
            if ($avail_weight < $cw) $can = false;
            if ($avail_carbon < $cc) $can = false;

            if ($can) {
                // Deduct Money
                if ($cp > 0) {
                    $new_balance = $wallet_balance - $cp;
                    $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description) VALUES (:uid, 'income', :amt, :bal, :desc)")
                        ->execute([':uid' => $user_id, ':amt' => -$cp, ':bal' => $new_balance, ':desc' => "Redeemed: " . $reward['name']]);
                    $wallet_balance = $new_balance;
                }

                // Deduct Weight
                if ($cw > 0) {
                    $conn->prepare("UPDATE users SET spent_recycled_weight = spent_recycled_weight + :c WHERE id = :uid")->execute([':c' => $cw, ':uid' => $user_id]);
                    $avail_weight -= $cw;
                }

                // Deduct Carbon
                if ($cc > 0) {
                    $conn->prepare("UPDATE users SET spent_carbon_saved = spent_carbon_saved + :c WHERE id = :uid")->execute([':c' => $cc, ':uid' => $user_id]);
                    $avail_carbon -= $cc;
                }

                // Stock & Record
                $conn->prepare("UPDATE rewards SET stock = stock - 1 WHERE id = :id")->execute([':id' => $reward_id]);
                $conn->prepare("INSERT INTO reward_redemptions (user_id, reward_id, points_used, status) VALUES (:uid, :rid, :pts, 'pending')")
                    ->execute([':uid' => $user_id, ':rid' => $reward_id, ':pts' => $cp]); // points_used just tracks money cost for now

                $conn->commit();
                $message = "แลกสำเร็จ! รอดำเนินการ";
            } else {
                $error = "ยอดเงิน/แต้ม ไม่พอสำหรับแลกรางวัลนี้";
            }
        } else {
            $error = "สินค้าหมด";
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$rewards = $conn->query("SELECT * FROM rewards WHERE status = 'active' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร้านค้าแลกแต้ม - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .store-header {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
        }

        .balance-container {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            justify-content: center;
        }

        .balance-card {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
            border: 1px solid #f0f0f0;
        }

        .b-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .type-point .b-icon {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
        }

        .type-weight .b-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .type-carbon .b-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 25px;
            padding: 0 20px 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .reward-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }

        .reward-card:hover {
            transform: translateY(-5px);
        }

        .reward-img-box {
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .reward-img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
        }

        .reward-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .reward-title {
            margin: 0 0 5px;
            font-size: 1.1rem;
            color: #2c3e50;
        }

        .reward-desc {
            font-size: 0.9rem;
            color: #95a5a6;
            margin-bottom: 15px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cost-row {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: bold;
            font-size: 1rem;
            margin-bottom: 3px;
        }

        .c-point {
            color: #f39c12;
        }

        .c-weight {
            color: #27ae60;
        }

        .c-carbon {
            color: #2980b9;
        }

        .btn-redeem {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            background: #2c3e50;
            color: white;
            margin-top: 10px;
        }

        .btn-redeem:hover {
            background: #34495e;
        }

        .btn-redeem:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
        }
    </style>
</head>

<body style="background: #fdfdfd;">
    <?php include 'navbar.php'; ?>

    <div class="store-header">
        <h1 style="text-align: center; color: #2c3e50; margin-bottom: 20px;"><i class="fas fa-store"></i> ศูนย์แลกของรางวัล</h1>
        <div class="balance-container">
            <div class="balance-card type-point">
                <div class="b-icon"><i class="fas fa-wallet"></i></div>
                <div><small>เงิน</small><br><b>฿<?php echo number_format($wallet_balance, 2); ?></b></div>
            </div>
            <div class="balance-card type-weight">
                <div class="b-icon"><i class="fas fa-recycle"></i></div>
                <div><small>คะแนนรีไซเคิล</small><br><b><?php echo number_format($avail_weight, 1); ?> kg</b></div>
            </div>
            <div class="balance-card type-carbon">
                <div class="b-icon"><i class="fas fa-cloud"></i></div>
                <div><small>คะแนนคาร์บอน</small><br><b><?php echo number_format($avail_carbon, 1); ?></b></div>
            </div>
        </div>
    </div>

    <!-- Hidden vars for JS -->
    <?php if ($message): ?> <div id="server-msg" data-type="success" data-text="<?php echo addslashes($message); ?>" style="display:none;"></div> <?php endif; ?>
    <?php if ($error): ?> <div id="server-msg" data-type="error" data-text="<?php echo addslashes($error); ?>" style="display:none;"></div> <?php endif; ?>

    <div class="rewards-grid">
        <?php foreach ($rewards as $item):
            $cp = floatval($item['points_cost']);
            $cw = floatval($item['weight_cost']);
            $cc = floatval($item['carbon_cost']);

            $can = true;
            if ($wallet_balance < $cp) $can = false;
            if ($avail_weight < $cw) $can = false;
            if ($avail_carbon < $cc) $can = false;

            // Image Fallback
            $img_src = "../assets/images/logo.png";
            if (!empty($item['image']) && file_exists("../assets/images/uploads/" . $item['image'])) {
                $img_src = "../assets/images/uploads/" . $item['image'];
            } else {
                if ($item['image'] == 'reward_bag.png') $img_src = "https://cdn-icons-png.flaticon.com/512/2829/2829824.png";
                elseif ($item['image'] == 'reward_cup.png') $img_src = "https://cdn-icons-png.flaticon.com/512/1902/1902724.png";
                elseif ($item['image'] == 'reward_coupon.png') $img_src = "https://cdn-icons-png.flaticon.com/512/2089/2089363.png";
                elseif ($item['image'] == 'reward_seed.png') $img_src = "https://cdn-icons-png.flaticon.com/512/628/628324.png";
            }
        ?>
            <div class="reward-card">
                <div class="reward-img-box">
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="reward-img">
                </div>
                <div class="reward-content">
                    <h3 class="reward-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="reward-desc"><?php echo htmlspecialchars($item['description']); ?></p>

                    <div style="margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:8px;">
                        <small style="color:#777; display:block; margin-bottom:5px;">ราคาแลก (Cost):</small>
                        <?php if ($cp > 0): ?> <div class="cost-row c-point"><i class="fas fa-coins" style="width:20px;"></i> ฿<?php echo number_format($cp); ?></div> <?php endif; ?>
                        <?php if ($cw > 0): ?> <div class="cost-row c-weight"><i class="fas fa-recycle" style="width:20px;"></i> <?php echo number_format($cw); ?> kg</div> <?php endif; ?>
                        <?php if ($cc > 0): ?> <div class="cost-row c-carbon"><i class="fas fa-cloud" style="width:20px;"></i> <?php echo number_format($cc); ?> Credit</div> <?php endif; ?>
                        <?php if ($cp == 0 && $cw == 0 && $cc == 0): ?> <div class="cost-row" style="color:#e74c3c;">ฟรี (Free)</div> <?php endif; ?>
                    </div>

                    <div style="font-size:0.8rem; color:#999; margin-bottom:5px; text-align:right;">เหลือ <?php echo $item['stock']; ?> ชิ้น</div>

                    <form method="POST" id="redeem-form-<?php echo $item['id']; ?>">
                        <input type="hidden" name="redeem_id" value="<?php echo $item['id']; ?>">
                        <button type="button" class="btn-redeem" <?php echo (!$can || $item['stock'] <= 0) ? 'disabled' : ''; ?>
                            onclick="confirmRedeem('<?php echo htmlspecialchars($item['name']); ?>', 'redeem-form-<?php echo $item['id']; ?>')">
                            <?php if ($item['stock'] <= 0) echo "สินค้าหมด";
                            elseif (!$can) echo "แต้มไม่พอ";
                            else echo "แลกของรางวัล"; ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align: center; margin-bottom: 40px;">
        <a href="homepage.php" style="color: #666; text-decoration: none;">ย้อนกลับไปหน้าหลัก</a>
    </div>

    <script>
        function confirmRedeem(rewardName, formId) {
            Swal.fire({
                title: 'ยืนยันการแลก?',
                text: "คุณต้องการแลก '" + rewardName + "' ใช่หรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2c3e50',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, แลกเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            })
        }

        // Show Server Messages
        const msgDiv = document.getElementById('server-msg');
        if (msgDiv) {
            const type = msgDiv.dataset.type;
            const text = msgDiv.dataset.text;
            Swal.fire({
                icon: type,
                title: type === 'success' ? 'สำเร็จ!' : 'ข้อผิดพลาด',
                text: text,
                confirmButtonColor: '#2c3e50'
            });
        }
    </script>
</body>

</html>