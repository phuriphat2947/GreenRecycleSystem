<?php
session_start();
require_once "./db_connect/db_connect.php";

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_msg = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } elseif ($password !== $confirm_password) {
        $error_msg = "รหัสผ่านไม่ตรงกัน";
    } elseif (strlen($password) < 6) {
        $error_msg = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error_msg = "อีเมลนี้ถูกใช้งานแล้ว";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $level = 'Seedling';

            try {
                $sql = "INSERT INTO users (username, email, password, role, level) VALUES (:username, :email, :password, :role, :level)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':level', $level);

                if ($stmt->execute()) {
                    // Get the ID of the newly registered user
                    $new_user_id = $conn->lastInsertId();

                    // Create Admin Notification
                    $notif_msg = "มีสมาชิกใหม่: " . $username;
                    $notif_sql = "INSERT INTO admin_notifications (type, message, related_id) VALUES ('new_user', :msg, :rid)";
                    $notif_stmt = $conn->prepare($notif_sql);
                    $notif_stmt->bindParam(':msg', $notif_msg);
                    $notif_stmt->bindParam(':rid', $new_user_id);
                    $notif_stmt->execute();

                    $success_msg = "สมัครสมาชิกสำเร็จ! กำลังพาท่านไปหน้าเข้าสู่ระบบ...";
                    header("refresh:2;url=login.php");
                } else {
                    $error_msg = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
                }
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - GreenDigital Recycle</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>

    <div class="auth-container">

        <div class="auth-header">
            <h2>สมัครสมาชิก</h2>
            <p>เข้าร่วมเป็นส่วนหนึ่งของการรักษ์โลกกับเรา</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="registerForm" novalidate>

            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" name="username" id="username" class="form-control" required placeholder="กรุณากรอกชื่อผู้ใช้">
            </div>

            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="กรุณากรอกอีเมล">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="กรุณากรอกรหัสผ่าน">
            </div>

            <div class="form-group">
                <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="กรุณากรอกรหัสผ่านอีกครั้ง">
            </div>

            <button type="submit" class="btn-auth">สมัครสมาชิก</button>
        </form>

        <div class="auth-footer">
            <p>มีบัญชีผู้ใช้แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
            <p><a href="index.php">กลับหน้าหลัก</a></p>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>

</html>