<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Code - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .qr-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .qr-card {
            background: white;
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            color: var(--text-dark);
            max-width: 400px;
            width: 100%;
        }

        .qr-code-img {
            width: 250px;
            height: 250px;
            background: #f0f0f0;

            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div class="qr-container">
        <a href="scan_hub.php" style="color:white; position:absolute; top:20px; left:20px; font-size:1.5rem;"><i
                class="fas fa-arrow-left"></i></a>

        <div class="qr-card" style="transform: scale(1.5);">
            <h2 style="margin-bottom:5px;">My QR Code</h2>
            <p style="color:#888; margin-bottom:2rem;">สแกนประเมินความพึงพอใจ</p>

            <div class="qr-code-img">


                <img style="width: 300px;" src="../assets/images/แบบประเมิณ.png" alt="">


            </div>


            <a
                href="https://docs.google.com/forms/d/e/1FAIpQLScLeF2Ok9xurvXyYcj2ljz71rk3N1qli65wDn7ybytj3J1JZA/viewform?usp=dialog">คลิก
                เพื่อประเมินความพึงพอใจ</a>


        </div>
    </div>
</body>

</html>