<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Auth
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = 'profile'; // Keep profile menu active

// Fetch User Info (Wallet Bal, Bank Info)
try {
    // Get latest wallet balance
    $stmt_bal = $conn->prepare("SELECT balance_after FROM wallet_transactions WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $stmt_bal->execute([':uid' => $user_id]);
    $wallet_balance = $stmt_bal->fetchColumn() ?: 0.00;

    // Get Bank Info
    $stmt_user = $conn->prepare("SELECT bank_name, bank_account, bank_account_name FROM users WHERE id = :uid");
    $stmt_user->execute([':uid' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Get Recent Withdrawals
    $stmt_hist = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt_hist->execute([':uid' => $user_id]);
    $withdrawals = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Handle Withdrawal Request
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount']);

    if ($amount > $wallet_balance) {
        $msg = "ยอดเงินในกระเป๋าไม่เพียงพอ";
        $msg_type = "danger";
    } elseif ($amount < 100) {
        $msg = "ถอนขั้นต่ำ 100 บาท";
        $msg_type = "danger";
    } elseif (empty($user['bank_account']) || empty($user['bank_account_name'])) {
        $msg = "กรุณาระบุข้อมูลบัญชีธนาคารในหน้า <a href='profile.php'>โปรไฟล์</a> ก่อนทำรายการ";
        $msg_type = "danger";
    } else {
        // Process
        try {
            $conn->beginTransaction();

            // 1. Create Withdrawal Record
            $conn->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, bank_account, bank_account_name, status) VALUES (:uid, :amt, :bn, :ba, :ban, 'pending')")
                ->execute([
                    ':uid' => $user_id,
                    ':amt' => $amount,
                    ':bn' => $user['bank_name'],
                    ':ba' => $user['bank_account'],
                    ':ban' => $user['bank_account_name']
                ]);
            $wd_id = $conn->lastInsertId();

            // 2. Deduct Wallet (Debit) - Held
            $new_bal = $wallet_balance - $amount;
            $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description) VALUES (:uid, 'withdraw', :amt, :bal, :desc)")
                ->execute([
                    ':uid' => $user_id,
                    ':amt' => -$amount, // Negative for withdraw
                    ':bal' => $new_bal,
                    ':desc' => "ถอนเงินเข้าบัญชี (รอดำเนินการ) #$wd_id"
                ]);

            // 3. Admin Notification
            $conn->prepare("INSERT INTO admin_notifications (type, message, related_id, created_at) VALUES ('withdrawal', :msg, :rid, NOW())")
                ->execute([
                    ':msg' => "New Withdrawal Request: ฿" . number_format($amount, 2),
                    ':rid' => $wd_id
                ]);

            $conn->commit();
            $msg = "แจ้งถอนเงินเรียบร้อยแล้ว กรุณารอตรวจสอบ";
            $msg_type = "success";

            // Refund balance for display
            $wallet_balance = $new_bal;
        } catch (Exception $e) {
            $conn->rollBack();
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ถอนเงิน (Withdraw) - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 80px 0 40px;
            color: white;
            text-align: center;
        }

        .dashboard-container {
            max-width: 800px;
            margin: -30px auto 40px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .balance-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Prompt', sans-serif;
        }

        .withdraw-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
        }

        .withdraw-btn:hover {
            background: var(--primary-dark);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .history-table th {
            color: #888;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-pending {
            color: var(--warning);
            background: #fff3cd;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .status-approved {
            color: var(--success);
            background: #d4edda;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .status-rejected {
            color: var(--danger);
            background: #f8d7da;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <header class="page-header">
        <div class="container">
            <h1>แจ้งถอนเงิน (Withdrawal)</h1>
            <p>ถอนรายได้จากกระเป๋าเงินของคุณเข้าบัญชีธนาคาร</p>
        </div>
    </header>

    <div class="dashboard-container">

        <div class="balance-card">
            <h3 style="color:#888; font-weight:400; margin-bottom:10px;">ยอดเงินในกระเป๋า (Wallet Balance)</h3>
            <div style="font-size:3rem; font-weight:600; color:var(--primary);">฿<?php echo number_format($wallet_balance, 2); ?></div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom:2rem;">
            <h3 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">ทำรายการถอนเงิน</h3>

            <form method="POST">
                <div class="form-group">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">บัญชีธนาคารที่จะรับเงิน:</label>
                    <div style="background:#f9f9f9; padding:15px; border-radius:8px; display:flex; gap:15px; align-items:center;">
                        <i class="fas fa-university" style="font-size:1.5rem; color:#888;"></i>
                        <div>
                            <?php if ($user['bank_account']): ?>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($user['bank_name']); ?></div>
                                <div><?php echo htmlspecialchars($user['bank_account']); ?></div>
                                <div style="font-size:0.9rem; color:#666;"><?php echo htmlspecialchars($user['bank_account_name']); ?></div>
                            <?php else: ?>
                                <span style="color:var(--danger);">ยังไม่ได้ระบุบัญชีธนาคาร</span> <a href="profile.php">แก้ไข</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">ระบุยอดเงินที่ต้องการถอน (บาท):</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="100" max="<?php echo $wallet_balance; ?>" placeholder="ขั้นต่ำ 100 บาท" required>
                </div>

                <button type="submit" name="withdraw" class="withdraw-btn" <?php echo ($wallet_balance < 100 || empty($user['bank_account'])) ? 'disabled style="background:#ccc; cursor:not-allowed;"' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> ยืนยันการแจ้งถอน
                </button>
            </form>
        </div>

        <!-- History -->
        <h3 style="margin-bottom: 15px;">ประวัติการถอนเงินล่าสุด</h3>
        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x:auto;">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>จำนวนเงิน</th>
                        <th>ธนาคาร</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($withdrawals)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:20px; color:#aaa;">ไม่มีประวัติการถอนเงิน</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($withdrawals as $w): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($w['created_at'])); ?></td>
                                <td style="font-weight:600;">฿<?php echo number_format($w['amount'], 2); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($w['bank_name']); ?>
                                    <br><span style="font-size:0.8rem; color:#999;"><?php echo htmlspecialchars($w['bank_account']); ?></span>
                                </td>
                                <td>
                                    <span class="status-<?php echo $w['status']; ?>"><?php echo strtoupper($w['status']); ?></span>
                                    <?php if ($w['reject_reason']): ?>
                                        <div style="font-size:0.8rem; color:var(--danger); margin-top:5px;"><?php echo htmlspecialchars($w['reject_reason']); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($msg): ?>
                Swal.fire({
                    icon: '<?php echo $msg_type == "success" ? "success" : "error"; ?>',
                    title: '<?php echo $msg_type == "success" ? "สำเร็จ" : "ข้อผิดพลาด"; ?>',
                    html: '<?php echo $msg; ?>',
                    confirmButtonColor: '#2ecc71',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    <?php if ($msg_type == "success"): ?>
                        window.location.href = 'withdrawal.php'; // Refresh to clear form/prevent resubmit
                    <?php endif; ?>
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>