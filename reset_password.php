<?php
require_once "./db_connect/db_connect.php";

$msg = "";
$msg_type = "";
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Token not found");
}

// Validate Token
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expiry > NOW()");
$stmt->bindParam(':token', $token);
$stmt->execute();
$valid_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$valid_user) {
    $msg = "ลิงก์รีเซ็ตรหัสผ่านหมดอายุหรือไม่ถูกต้อง";
    $msg_type = "danger";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password === $confirm) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expiry = NULL WHERE id = :id");
        $update->bindParam(':password', $hash);
        $update->bindParam(':id', $valid_user['id']);

        if ($update->execute()) {
            $msg = "เปลี่ยนรหัสผ่านสำเร็จ! <a href='login.php'>คลิกเพื่อเข้าสู่ระบบ</a>";
            $msg_type = "success";
            // Invalidate token immediately handled by setting NULL above
        } else {
            $msg = "เกิดข้อผิดพลาดในการอัปเดต";
            $msg_type = "danger";
        }
    } else {
        $msg = "รหัสผ่านไม่ตรงกัน";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ตั้งรหัสผ่านใหม่ - GreenDigital</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>ตั้งรหัสผ่านใหม่</h2>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($valid_user && !strpos($msg, 'สำเร็จ')): ?>
            <form method="post">
                <div class="form-group">
                    <label>รหัสผ่านใหม่</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn-auth">เปลี่ยนรหัสผ่าน</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>