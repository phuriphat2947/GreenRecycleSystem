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
    <title>Help & Support - GreenDigital</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--text-dark);
            border-left: 5px solid var(--primary);
            padding-left: 15px;
        }

        /* FAQ Styles */
        .faq-container {
            margin-bottom: 40px;
        }

        .faq-item {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s;
        }

        .faq-question {
            padding: 20px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            color: var(--text-dark);
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-question i {
            transition: transform 0.3s;
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease-out;
            background: #fdfdfd;
            border-top: 1px solid #eee;
            color: #666;
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 500px;
            /* Arbitrary high height */
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* Contact Cards */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .contact-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .contact-card:hover {
            transform: translateY(-5px);
        }

        .contact-icon {
            width: 70px;
            height: 70px;
            background: #e8f5e9;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }

        .contact-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .contact-text {
            color: #666;
            margin-bottom: 20px;
        }

        .contact-btn {
            display: inline-block;
            padding: 10px 25px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }

        .contact-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>

    <?php include "navbar.php"; ?>

    <div class="container" style="margin-top: 40px;">

        <h1 style="text-align:center; margin-bottom:40px; font-weight:600; color:var(--text-dark);">
            ศูนย์ช่วยเหลือ & ติดต่อเรา
        </h1>

        <!-- FAQ Section -->
        <div class="section-title"><i class="fas fa-question-circle"></i> คำถามที่พบบ่อย (FAQ)</div>
        <div class="faq-container">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ฉันจะเรียกรถรับขยะได้อย่างไร?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    คุณสามารถกดที่ปุ่ม <strong>"เรียกรถ"</strong> บนแถบเมนูด้านบน หรือหน้าแรก กรอกข้อมูลที่อยู่และนัดหมายเวลา เพื่อให้คนขับของเราเข้าไปรับขยะถึงหน้าบ้านคุณ
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ขยะประเภทไหนบ้างที่เรารับซื้อ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    เรารับซื้อ พลาสติกใส (PET), กระป๋องอลูมิเนียม, กระดาษลัง, และขวดแก้ว โดยราคาจะอิงตามตลาดกลางและมีโบนัสพิเศษตามระดับสมาชิกของคุณ
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ฉันจะได้รับเงินทางไหน?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    เงินจะถูกโอนเข้าสู่ <strong>Digital Wallet</strong> ในระบบทันทีที่รายการสำเร็จ คุณสามารถนำไปแลกของรางวัล หรือกด <strong>ถอนเงิน</strong> เข้าบัญชีธนาคารของคุณได้ตลอดเวลา
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    Carbon Credit คืออะไร?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    ทุกครั้งที่คุณรีไซเคิล คุณจะได้รับแต้มคาร์บอนเครดิต ซึ่งแสดงถึงปริมาณ CO2 ที่คุณช่วยลดโลกร้อน แต้มนี้สามารถสะสมเพื่อเลื่อนระดับสมาชิกและแลกของรางวัลพิเศษได้
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="section-title"><i class="fas fa-headset"></i> ช่องทางการติดต่อ</div>
        <div class="contact-grid">
            <div class="contact-card">
                <div class="contact-icon"><i class="fab fa-line"></i></div>
                <div class="contact-title">Line Official</div>
                <div class="contact-text">สอบถามปัญหา แจ้งเรื่องร้องเรียน Chat สดกับเจ้าหน้าที่</div>
                <a href="#" class="contact-btn">Add Line: @GreenDigital</a>
            </div>

            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="contact-title">Call Center</div>
                <div class="contact-text">ติดต่อด่วน โทรหาเราได้ทุกวัน (08:00 - 18:00)</div>
                <a href="tel:02-123-4567" class="contact-btn">02-123-4567</a>
            </div>

            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                <div class="contact-title">Email Support</div>
                <div class="contact-text">ส่งเอกสาร หรือข้อเสนอแนะทางอีเมล</div>
                <a href="mailto:support@greendigital.com" class="contact-btn">support@greendigital.com</a>
            </div>

            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="contact-title">Head Office</div>
                <div class="contact-text">123 Green Tower, Sukhumvit Road, Bangkok 10110</div>
                <a href="https://maps.google.com" target="_blank" class="contact-btn">เปิดแผนที่</a>
            </div>
        </div>

    </div>

    <?php include "footer.php"; ?>
    <?php include "chat_widget.php"; ?>

    <script>
        function toggleFaq(element) {
            const item = element.parentElement;
            item.classList.toggle('active');
        }
    </script>
</body>

</html>