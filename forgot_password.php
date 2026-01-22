<?php
session_start();
require_once "db_connect/db_connect.php";

$msg = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $token = bin2hex(random_bytes(32)); // Generate unique token
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expires in 1 hour

        $update = $conn->prepare("UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE email = :email");
        $update->bindParam(':token', $token);
        $update->bindParam(':expiry', $expiry);
        $update->bindParam(':email', $email);

        if ($update->execute()) {
            // SIMULATION: Cannot send real email in localhost XAMPP easily without config
            // We will display the link directly for testing purposes.
            $reset_link = "http://localhost/Greendigital_Recycle_web/reset_password.php?token=" . $token;

            $msg = "ระบบได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว (จำลอง): <br><a href='$reset_link'>คลิกที่นี่เพื่อรีเซ็ตรหัสผ่าน</a>";
            $msg_type = "success";
        } else {
            $msg = "เกิดข้อผิดพลาดในการสร้างโทเค็น";
            $msg_type = "danger";
        }
    } else {
        $msg = "ไม่พบอีเมลนี้ในระบบ";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ลืมรหัสผ่าน - GreenDigital</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>ลืมรหัสผ่าน</h2>
            <p>กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>อีเมล</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn-auth">ส่งลิงก์กู้คืนรหัสผ่าน</button>
        </form>

        <div class="auth-footer">
            <p><a href="login.php">กลับหน้าเข้าสู่ระบบ</a></p>
        </div>
    </div>
</body>

</html>