<?php
session_start();
require_once "db_connect/db_connect.php";

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role == 'admin') {
        header("Location: admin/index.php");
    } elseif ($role == 'driver') {
        header("Location: driver/index.php");
    } else {
        header("Location: components/homepage.php");
    }
    exit();
}

$user_count = 0;
$user_avatars = [];
try {

    $stmt_u = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $user_count = $stmt_u->fetchColumn();

    $stmt_av = $conn->query("SELECT profile_image FROM users WHERE role = 'user' AND profile_image IS NOT NULL AND profile_image != '' AND profile_image != 'default_avatar.png' ORDER BY created_at DESC LIMIT 3");
    $user_avatars = $stmt_av->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenDigital - Recycle for Future</title>
    <?php require_once "db_connect/db_connect.php"; ?>

    <?php

    ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', 'Prompt', sans-serif;
        }

        body {
            overflow-x: hidden;
            background: #000;
        }

        /* Hero Section */
        .hero {
            position: relative;
            height: 100vh;
            width: 100%;
            background: url('assets/img_indexPreview/1.png') no-repeat center center/cover;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0 10%;
            transition: background-image 1.5s ease-in-out;
        }

        /* Overlay */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.7) 100%);
            z-index: 1;
        }

        /* Navbar */
        .navbar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 30px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 40px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: white;
        }

        .social-icons {
            display: flex;
            gap: 20px;
            color: white;
            align-items: center;
        }

        .social-icons i {
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .social-icons i:hover {
            opacity: 1;
        }

        /* Hero Content */
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            max-width: 600px;
        }

        .trusted-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .trusted-users {
            display: flex;
        }

        .trusted-users img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
            margin-left: -10px;
        }

        .trusted-users img:first-child {
            margin-left: 0;
        }

        h1 {
            font-size: 5rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 20px;
            letter-spacing: -2px;
        }

        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 40px;
            max-width: 500px;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            color: #1a1a1a;
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .cta-btn:hover {
            transform: scale(1.05);
            color: #ffffff;
            background-color: rgba(109, 255, 140, 0.9);
        }

        /* Floating Controls (Bottom Right) */
        .controls {
            position: absolute;
            bottom: 40px;
            right: 40px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .control-btn {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 3.5rem;
            }

            .navbar {
                padding: 20px;
            }

            .nav-links {
                display: none;
            }

            .hero {
                padding: 0 5%;
                justify-content: center;
                text-align: center;
            }

            .hero-content {
                align-items: center;
                display: flex;
                flex-direction: column;
            }

            .controls {
                bottom: 20px;
                right: 20px;
            }


        }


        #Picture-Welcome {
            position: absolute;
            top: 24%;
            right: 7vw;
            width: 50vw;
            height: 30vw;
            object-fit: contain;
            z-index: 1;
            display: none;
            align-items: center;
            justify-content: center;
        }

        #Picture-Welcome>img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="logout.php" class="logo">GreenDigital</a>

        <div class="social-icons" style="transform: scale(1.5);">
            <i class="fab fa-instagram"></i>
            <i class="fab fa-twitter"></i>
            <i class="fab fa-facebook-f"></i>
        </div>
    </nav>

    <section class="hero" id="heroSection">

        <div class="hero-content">

            <?php
            $user_count = 0;
            try {
                $stmt_u = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
                $user_count = $stmt_u->fetchColumn();
            } catch (PDOException $e) {
            }
            ?>
            <div class="trusted-badge">
                <?php if (!empty($user_avatars)): ?>
                    <div class="trusted-users">
                        <?php foreach ($user_avatars as $av): ?>
                            <img src="assets/images/uploads/<?php echo htmlspecialchars($av); ?>" alt="User" onerror="this.style.display='none'">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <span <?php echo empty($user_avatars) ? '' : 'style="margin-left: 10px;"'; ?>>
                    Trusted by <strong><?php echo $user_count; ?></strong> Eco Warriors
                </span>
            </div>

            <h1>WELCOME<br> GREEN DIGITAL</h1>

            <p>
                ขับเคลื่อนด้วยนวัตกรรม เพื่อตอบโจทย์ผู้ที่มองหาอะไรที่มากกว่าแค่ถังขยะ "เชื่อมต่อสังคมที่ยั่งยืน คืนอากาศบริสุทธิ์ พร้อมเพิ่มมูลค่าให้ขยะในมือคุณ" มาร่วมเป็นส่วนหนึ่งของการปฏิวัติ GreenDigital ได้แล้ววันนี้
            </p>

            <a href="login.php" class="cta-btn">
                เข้าสู่เว็ปไซต์รับซื้อขยะ <i class="fas fa-arrow-right"></i>
            </a>
            <a href="components/user_manual.php" class="cta-btn" style="background: transparent; border: 2px solid white; color: white; margin-left: 10px;">
                <i class="fas fa-book"></i> คู่มือการใช้งาน
            </a>
        </div>


    </section>



    <section id="Picture-Welcome">

        <img src="./assets/images/welcome.png" alt="">


    </section>


    <script>
        const images = [
            'assets/img_indexPreview/1.png',
            'assets/img_indexPreview/2.png',
            'assets/img_indexPreview/3.png',
            'assets/img_indexPreview/4.png',
            'assets/img_indexPreview/5.png'
        ];

        let currentIndex = 0;
        const heroSection = document.getElementById('heroSection');

        function changeBackground() {
            currentIndex = (currentIndex + 1) % images.length;
            const imageUrl = images[currentIndex];
            heroSection.style.backgroundImage = `url('${imageUrl}')`;
        }

        setInterval(changeBackground, 5000);

        images.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    </script>
</body>

</html>