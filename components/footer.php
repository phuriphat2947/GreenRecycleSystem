<style>
    .site-footer {
        background-color: #1a1a1a;
        color: #ecf0f1;
        padding: 50px 0 20px;
        margin-top: 60px;
        font-family: 'Prompt', sans-serif;
        position: relative;
        z-index: 10;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
    }

    .footer-col h3 {
        color: #2ecc71;
        font-size: 1.2rem;
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
    }

    .footer-col h3::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -5px;
        width: 30px;
        height: 3px;
        background: #2ecc71;
        border-radius: 2px;
    }

    .footer-about p {
        color: #bdc3c7;
        line-height: 1.6;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    .footer-links {
        list-style: none;
        padding: 0;
    }

    .footer-links li {
        margin-bottom: 12px;
    }

    .footer-links a {
        color: #bdc3c7;
        text-decoration: none;
        transition: color 0.3s, padding-left 0.3s;
        display: inline-block;
    }

    .footer-links a:hover {
        color: #2ecc71;
        padding-left: 5px;
    }

    .footer-contact li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 15px;
        color: #bdc3c7;
        font-size: 0.95rem;
    }

    .footer-contact box-icon,
    .footer-contact i {
        color: #2ecc71;
        margin-top: 3px;
    }

    .footer-bottom {
        border-top: 1px solid #333;
        margin-top: 40px;
        padding-top: 20px;
        text-align: center;
        color: #7f8c8d;
        font-size: 0.85rem;
    }

    .social-links {
        margin-top: 15px;
    }

    .social-links a {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        background: #333;
        color: #fff;
        border-radius: 50%;
        margin: 0 5px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .social-links a:hover {
        background: #2ecc71;
        transform: translateY(-3px);
    }
</style>

<footer class="site-footer">
    <div class="footer-container">
        <!-- Column 1: About -->
        <div class="footer-col footer-about">
            <h3>GreenDigital</h3>
            <p>
                เปลี่ยนขยะให้เป็นมูลค่า เชื่อมต่อสังคมที่ยั่งยืนด้วยเทคโนโลยี
                เรามุ่งมั่นที่จะสร้างโลกที่สะอาดขึ้นผ่านการรีไซเคิลที่โปร่งใสและตรวจสอบได้
            </p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-line"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>

        <!-- Column 2: Quick Links -->
        <div class="footer-col">
            <h3>เมนูลัด</h3>
            <ul class="footer-links">
                <li><a href="homepage.php"><i class="fas fa-angle-right"></i> หน้าหลัก</a></li>
                <li><a href="request_pickup.php"><i class="fas fa-angle-right"></i> เรียกรถรับขยะ</a></li>
                <li><a href="redeem.php"><i class="fas fa-angle-right"></i> แลกของรางวัล</a></li>
                <li><a href="history.php"><i class="fas fa-angle-right"></i> ประวัติการใช้งาน</a></li>
                <li><a href="support.php"><i class="fas fa-angle-right"></i> ช่วยเหลือ & ติดต่อ</a></li>
            </ul>
        </div>

        <!-- Column 3: Contact -->
        <div class="footer-col">
            <h3>ติดต่อเรา</h3>
            <ul class="footer-links footer-contact">
                <li>
                    <i class="fas fa-map-marker-alt"></i>
                    <span>วิทยาลัยการอาชีพร้อยเอ็ด</span>
                </li>
                <li>
                    <i class="fas fa-phone-alt"></i>
                    <span>02-123-4567 (ทุกวัน 08:00 - 18:00)</span>
                </li>
                <li>
                    <i class="fas fa-envelope"></i>
                    <span>support@greendigital.com</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> GreenDigital Recycle System. All Rights Reserved.
    </div>
</footer>