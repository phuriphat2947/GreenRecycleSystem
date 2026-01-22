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

// Fetch User Wallet & Bank Info
try {
    // Wallet Balance
    $stmt_bal = $conn->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = :uid");
    $stmt_bal->execute([':uid' => $user_id]);
    $wallet_balance = $stmt_bal->fetchColumn() ?: 0.00;

    // Bank Info
    $stmt_user = $conn->prepare("SELECT bank_name, bank_account, bank_account_name FROM users WHERE id = :uid");
    $stmt_user->execute([':uid' => $user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Handle Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw_amount'])) {
    $amount = floatval($_POST['withdraw_amount']);
    $bank_name = $_POST['bank_name'];
    $bank_account = $_POST['bank_account'];
    $account_name = $_POST['bank_account_name'];

    if ($amount <= 0) {
        $error = "ยอดเงินต้องมากกว่า 0";
    } elseif ($amount < 100) {
        $error = "ถอนขั้นต่ำ 100 บาท";
    } elseif ($amount > $wallet_balance) {
        $error = "ยอดเงินในกระเป๋าไม่พอ (มีอยู่ ฿" . number_format($wallet_balance, 2) . ")";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Insert Withdrawal Request
            $stmt_req = $conn->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, bank_account, bank_account_name, status) 
                                        VALUES (:uid, :amt, :bn, :ba, :ban, 'pending')");
            $stmt_req->execute([
                ':uid' => $user_id,
                ':amt' => $amount,
                ':bn' => $bank_name,
                ':ba' => $bank_account,
                ':ban' => $account_name
            ]);
            $withdraw_id = $conn->lastInsertId();

            // 2. Deduct Wallet Balance (Lock funds)
            $new_balance = $wallet_balance - $amount;
            $stmt_tx = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description) 
                                       VALUES (:uid, 'withdraw', :amt, :bal, :desc)");
            $stmt_tx->execute([
                ':uid' => $user_id,
                ':amt' => -$amount, // Negative for deduction
                ':bal' => $new_balance,
                ':desc' => "Withdrawal Request #$withdraw_id"
            ]);

            // 3. Update User Bank Info (Save for next time)
            $stmt_upd = $conn->prepare("UPDATE users SET bank_name = :bn, bank_account = :ba, bank_account_name = :ban WHERE id = :uid");
            $stmt_upd->execute([':bn' => $bank_name, ':ba' => $bank_account, ':ban' => $account_name, ':uid' => $user_id]);

            // 4. Notify Admin
            $conn->prepare("INSERT INTO admin_notifications (type, message, related_id) VALUES ('withdrawal', :msg, :rid)")
                ->execute([':msg' => "New Withdrawal: ฿$amount by User #$user_id", ':rid' => $withdraw_id]);

            $conn->commit();
            header("Location: history.php?msg=withdraw_success");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Transaction Failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งถอนเงิน - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .withdraw-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: 500px;
            margin: 20px auto;
        }

        .balance-box {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
        }

        .balance-val {
            font-size: 2rem;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-family: 'Prompt', sans-serif;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #219150;
        }

        .error-msg {
            color: #e74c3c;
            background: #fce4e4;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container" style="padding-top: 20px;">
        <div class="withdraw-card">
            <h2 style="text-align: center; margin-bottom: 20px; color: var(--text-dark);">
                <i class="fas fa-hand-holding-usd"></i> แจ้งถอนเงิน
            </h2>

            <?php if ($error): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="balance-box">
                <div>ยอดเงินที่ถอนได้</div>
                <div class="balance-val">฿<?php echo number_format($wallet_balance, 2); ?></div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">ธนาคาร</label>
                    <select name="bank_name" class="form-control" required>
                        <option value="">-- เลือกธนาคาร --</option>
                        <option value="KBANK" <?php echo ($user_info['bank_name'] == 'KBANK') ? 'selected' : ''; ?>>กสิกรไทย (KBANK)</option>
                        <option value="SCB" <?php echo ($user_info['bank_name'] == 'SCB') ? 'selected' : ''; ?>>ไทยพาณิชย์ (SCB)</option>
                        <option value="KTB" <?php echo ($user_info['bank_name'] == 'KTB') ? 'selected' : ''; ?>>กรุงไทย (KTB)</option>
                        <option value="BBL" <?php echo ($user_info['bank_name'] == 'BBL') ? 'selected' : ''; ?>>กรุงเทพ (BBL)</option>
                        <option value="TTB" <?php echo ($user_info['bank_name'] == 'TTB') ? 'selected' : ''; ?>>ทหารไทยธนชาต (TTB)</option>
                        <option value="GSB" <?php echo ($user_info['bank_name'] == 'GSB') ? 'selected' : ''; ?>>ออมสิน (GSB)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">เลขที่บัญชี</label>
                    <input type="text" name="bank_account" class="form-control" placeholder="123-4-56789-0" value="<?php echo htmlspecialchars($user_info['bank_account'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">ชื่อบัญชี</label>
                    <input type="text" name="bank_account_name" class="form-control" placeholder="ชื่อ-นามสกุล (ตรงกับบัตร ปชช.)" value="<?php echo htmlspecialchars($user_info['bank_account_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">จำนวนเงินที่ต้องการถอน (บาท)</label>
                    <input type="number" name="withdraw_amount" class="form-control" placeholder="ขั้นต่ำ 100" min="100" max="<?php echo $wallet_balance; ?>" step="0.01" required>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> ยืนยันการถอนเงิน</button>
            </form>

            <div style="text-align: center; margin-top: 15px;">
                <a href="homepage.php" style="color: #666; text-decoration: none;">ย้อนกลับ</a>
            </div>
        </div>
    </div>
</body>

</html>