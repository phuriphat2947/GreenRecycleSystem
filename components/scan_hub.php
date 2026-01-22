<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Fetch basic user info for navbar
$stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'default_avatar.png';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Hub - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .scan-container {
            min-height: 80vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }

        .scan-header {
            margin-bottom: 3rem;
        }

        .scan-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .scan-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            width: 100%;
            max-width: 800px;
        }

        .scan-card {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-dark);
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .scan-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .scan-icon-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #f0fdf4;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .scan-card:hover .scan-icon-wrapper {
            background: var(--primary);
            color: white;
        }

        .scan-icon-wrapper i {
            font-size: 3rem;
            color: var(--primary);
            transition: all 0.3s;
        }

        .scan-card:hover .scan-icon-wrapper i {
            color: white;
        }

        .scan-card h3 {
            font-size: 1.5rem;
            margin: 0;
        }

        .scan-card p {
            color: var(--text-light);
            font-size: 1rem;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .scan-container {
                padding: 1rem;
            }

            .scan-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar (Simplified) -->
    <nav class="navbar">
        <a href="homepage.php" class="logo">Green<span>Digital</span></a>
        <a href="homepage.php" class="nav-action-btn">
            <i class="fas fa-arrow-left"></i> <span>กลับหน้าหลัก</span>
        </a>
    </nav>

    <div class="scan-container">
        <div class="scan-header">
            <h1>เลือกโหมดสแกน</h1>
            <p style="color: #666; font-size: 1.1rem;">เลือกฟีเจอร์ที่คุณต้องการใช้งาน</p>
        </div>

        <div class="scan-options-grid">
            <!-- Option 1: My QR Code -->
            <a href="my_qr.php" class="scan-card">
                <div class="scan-icon-wrapper">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3>My QR Code</h3>
                <p>แสดง QR Code<br>เพื่อประเมินความพึงพอใจของ ผู้ใช้งานเว็ปไซต์</p>
            </a>

            <!-- Option 2: Scan Product -->
            <a href="#" class="scan-card" onclick="alert('ฟีเจอร์สแกนสินค้ากำลังพัฒนาครับ!'); return false;">
                <div class="scan-icon-wrapper">
                    <i class="fas fa-barcode"></i>
                </div>
                <h3>Scan Product</h3>
                <p>สแกนบาร์โค้ดขยะรีไซเคิล<br>เพื่อตรวจสอบราคาและประเภท</p>
            </a>
        </div>
    </div>

</body>

</html>