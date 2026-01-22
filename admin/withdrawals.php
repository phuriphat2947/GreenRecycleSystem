<?php
session_start();
require_once "../db_connect/db_connect.php";

// Check Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include Payment Service
require_once "../payment/SimulationGateway.php";

$current_page = 'withdrawals';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['approve_manual'])) {
            $id = $_POST['approve_id'];
            // Manual: Just update status to APPROVED
            $conn->prepare("UPDATE withdrawals SET status='approved', updated_at=NOW() WHERE id=:id")
                ->execute([':id' => $id]);
        } elseif (isset($_POST['approve_auto'])) {
            $id = $_POST['approve_id'];

            // Fetch withdrawal details
            $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $wd = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wd) {
                // Initialize Gateway (Simulation)
                $gateway = new SimulationGateway();
                $result = $gateway->transfer($wd['bank_name'], $wd['bank_account'], $wd['amount'], $wd['id']);

                if ($result['success']) {
                    // Update Status + Save Transaction ID
                    // (Assuming we might add a transaction_ref column later, for now just approve)
                    $conn->prepare("UPDATE withdrawals SET status='approved', updated_at=NOW() WHERE id=:id")
                        ->execute([':id' => $id]);

                    echo "<script>alert('✅ " . $result['message'] . "');</script>";
                } else {
                    echo "<script>alert('❌ Transfer Failed: " . $result['message'] . "');</script>";
                }
            }
        } elseif (isset($_POST['reject_id'])) {
            $id = $_POST['reject_id'];
            $reason = $_POST['reason'];
            $user_id = $_POST['user_id'];
            $amount = floatval($_POST['amount']);

            $conn->beginTransaction();

            // 1. Mark Rejected
            $conn->prepare("UPDATE withdrawals SET status='rejected', reject_reason=:r, updated_at=NOW() WHERE id=:id")
                ->execute([':r' => $reason, ':id' => $id]);

            // 2. Refund Wallet
            // Get last balance
            $stmt = $conn->prepare("SELECT balance_after FROM wallet_transactions WHERE user_id=:uid ORDER BY id DESC LIMIT 1");
            $stmt->execute([':uid' => $user_id]);
            $last_bal = $stmt->fetchColumn() ?: 0;
            $new_bal = $last_bal + $amount;

            $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description) VALUES (:uid, 'refund', :amt, :bal, :desc)")
                ->execute([
                    ':uid' => $user_id,
                    ':amt' => $amount,
                    ':bal' => $new_bal,
                    ':desc' => "คืนเงินจากการปฏิเสธการถอน #$id"
                ]);

            $conn->commit();
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// Fetch Pending
try {
    $stmt = $conn->query("SELECT w.*, u.username, u.profile_image 
                          FROM withdrawals w 
                          JOIN users u ON w.user_id = u.id 
                          WHERE w.status = 'pending' 
                          ORDER BY w.created_at ASC");
    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch History (Last 20)
    $stmt_hist = $conn->query("SELECT w.*, u.username 
                               FROM withdrawals w 
                               JOIN users u ON w.user_id = u.id 
                               WHERE w.status != 'pending' 
                               ORDER BY w.updated_at DESC LIMIT 20");
    $history_list = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการถอนเงิน - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bank-box {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
            margin: 10px 0;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85rem;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }

        .btn-approve {
            background: var(--primary);
            color: white;
        }

        .btn-reject {
            background: var(--danger);
            color: white;
        }
    </style>
    <script>
        function openRejectModal(id, userId, amount) {
            document.getElementById('reject_id').value = id;
            document.getElementById('reject_user_id').value = userId;
            document.getElementById('reject_amount').value = amount;
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
    </script>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2>💸 จัดการการถอนเงิน (Withdrawals)</h2>
            </div>
            <div class="header-tools">
                <div class="admin-profile">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <img src="../assets/images/logo.png" alt="Admin" class="admin-avatar">
                </div>
            </div>
        </header>

        <main class="content-wrapper">

            <h3 style="margin-bottom: 1rem; color: var(--warning);">⏳ รอดำเนินการ (Pending)</h3>

            <?php if (empty($pending_list)): ?>
                <div style="background: white; padding: 2rem; text-align: center; border-radius: 12px; color: #aaa; margin-bottom: 2rem;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>ไม่มีรายการรอตรวจสอบ</p>
                </div>
            <?php else: ?>
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($pending_list as $item): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); position: relative; overflow: hidden; border-top: 4px solid var(--warning);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="../assets/images/uploads/<?php echo $item['profile_image']; ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <h4 style="margin: 0;"><?php echo htmlspecialchars($item['username']); ?></h4>
                                        <span style="font-size: 0.8rem; color: #888;">Request #<?php echo $item['id']; ?></span>
                                    </div>
                                </div>
                                <div style="font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                                    ฿<?php echo number_format($item['amount'], 2); ?>
                                </div>
                            </div>

                            <div class="bank-box">
                                <small style="color: #666; display: block; margin-bottom: 5px;">โอนเข้าบัญชี:</small>
                                <div style="font-weight: 600; color: var(--secondary);">
                                    <?php echo htmlspecialchars($item['bank_name']); ?>
                                </div>
                                <div style="font-size: 1.1rem; letter-spacing: 1px;">
                                    <?php echo htmlspecialchars($item['bank_account']); ?>
                                </div>
                                <div style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($item['bank_account_name']); ?>
                                </div>
                            </div>

                            <div style="font-size: 0.8rem; color: #aaa; margin-bottom: 15px;">
                                <i class="far fa-clock"></i> แจ้งเมื่อ: <?php echo date('d M Y H:i', strtotime($item['created_at'])); ?>
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn-sm btn-approve" name="approve_manual" style="width: 100%; border-radius: 4px 4px 0 0;">
                                    <i class="fas fa-check"></i> โอนเอง (Manual)
                                </button>
                                <button type="submit" class="btn-sm" name="approve_auto" style="width: 100%; background: #2f3542; color: #fff; margin-top: 2px; border-radius: 0 0 4px 4px;">
                                    <i class="fas fa-robot"></i> โอนอัตโนมัติ (Auto)
                                </button>
                                </form>
                                <button onclick="openRejectModal('<?php echo $item['id']; ?>', '<?php echo $item['user_id']; ?>', '<?php echo $item['amount']; ?>')" class="btn-sm btn-reject" style="flex: 1;">
                                    <i class="fas fa-times"></i> ปฏิเสธ
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 style="margin: 2rem 0 1rem; color: var(--secondary);">📝 ประวัติการทำรายการล่าสุด</h3>
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">User</th>
                            <th style="padding: 10px;">Amount</th>
                            <th style="padding: 10px;">Bank</th>
                            <th style="padding: 10px;">Status</th>
                            <th style="padding: 10px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_list as $h): ?>
                            <tr style="border-bottom: 1px solid #f5f5f5;">
                                <td style="padding: 10px;">#<?php echo $h['id']; ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($h['username']); ?></td>
                                <td style="padding: 10px; font-weight: bold;">฿<?php echo number_format($h['amount'], 2); ?></td>
                                <td style="padding: 10px;">
                                    <?php echo htmlspecialchars($h['bank_name']); ?> - <?php echo htmlspecialchars($h['bank_account']); ?>
                                </td>
                                <td style="padding: 10px;">
                                    <?php if ($h['status'] == 'approved'): ?>
                                        <span style="color: var(--success);"><i class="fas fa-check-circle"></i> Anroved</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger);"><i class="fas fa-times-circle"></i> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px; color: #888;"><?php echo date('d/m/y H:i', strtotime($h['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:12px; width:90%; max-width:400px;">
            <h3>ระบุเหตุผลที่ปฏิเสธ</h3>
            <form method="POST">
                <input type="hidden" name="reject_id" id="reject_id">
                <input type="hidden" name="user_id" id="reject_user_id">
                <input type="hidden" name="amount" id="reject_amount">

                <textarea name="reason" style="width:100%; padding:10px; margin:15px 0; border:1px solid #ddd; border-radius:8px;" rows="3" placeholder="เช่น เลขบัญชีไม่ถูกต้อง" required></textarea>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeRejectModal()" style="padding:8px 15px; border:none; background:#eee; cursor:pointer; border-radius:4px;">ยกเลิก</button>
                    <button type="submit" class="btn-reject" style="padding:8px 15px; border:none; cursor:pointer; border-radius:4px;">ยืนยันปฏิเสธ</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>