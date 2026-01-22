<?php
session_start();
require_once "../db_connect/db_connect.php";

// Fetch user data if logged in (for navbar)
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, membership_level, profile_image FROM users WHERE id = :id");
    $stmt->execute([':id' => $uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือการใช้งาน (User Manual) - GreenDigital Recycle</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            color: #333;
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
        }

        .manual-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .home-btn {
            position: absolute;
            top: 40px;
            left: 40px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }

        .home-btn:hover {
            color: #27ae60;
        }

        .manual-header {
            text-align: center;
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 3px solid #f0fdf4;
        }

        .manual-header h1 {
            color: #2c3e50;
            margin: 10px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .manual-header p {
            color: #7f8c8d;
            font-size: 1.2rem;
        }

        .manual-header .icon {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 15px;
        }

        /* TOC Grid */
        .toc-container {
            background: #f8fcf9;
            border: 1px solid #e0f2f1;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 60px;
        }

        .toc-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .toc-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #555;
            border: 1px solid #eee;
            transition: all 0.2s;
            font-weight: 500;
        }

        .toc-link i {
            color: #27ae60;
            width: 20px;
            text-align: center;
        }

        .toc-link:hover {
            transform: translateY(-3px);
            border-color: #27ae60;
            box-shadow: 0 4px 10px rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        /* Content Sections */
        .manual-section {
            margin-bottom: 70px;
            scroll-margin-top: 80px;
        }

        .section-title {
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            /* Fully rounded */
            display: inline-block;
            font-size: 1.5rem;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .section-title i {
            margin-right: 10px;
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        h3 {
            color: #2c3e50;
            border-left: 4px solid #f39c12;
            padding-left: 15px;
            margin: 30px 0 20px;
            font-size: 1.4rem;
        }

        p {
            line-height: 1.8;
            color: #555;
            margin-bottom: 15px;
        }

        .highlight-box {
            background: #fff8e1;
            border-left: 4px solid #f1c40f;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .highlight-box.tip {
            background: #e8f8f5;
            border-left-color: #2ecc71;
        }

        .highlight-box.alert {
            background: #fdecea;
            border-left-color: #e74c3c;
        }

        .highlight-box i {
            font-size: 1.5rem;
            margin-top: 3px;
        }

        .highlight-box.tip i {
            color: #27ae60;
        }

        .highlight-box.alert i {
            color: #c0392b;
        }

        .highlight-box i {
            color: #f39c12;
        }

        /* Default warning */

        /* Step Process */
        .process-steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        .step-item {
            display: flex;
            gap: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            align-items: flex-start;
        }

        .step-number {
            background: #34495e;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .step-content h4 {
            margin: 0 0 5px;
            color: #333;
        }

        .step-content p {
            margin: 0;
            font-size: 0.95rem;
            color: #666;
        }

        /* Tables */
        .user-levels-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .user-levels-table th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: left;
        }

        .user-levels-table td {
            padding: 15px;
            background: white;
            border-bottom: 1px solid #eee;
        }

        .user-levels-table tr:last-child td {
            border-bottom: none;
        }

        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #27ae60;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            transition: all 0.3s;
            z-index: 999;
            opacity: 0.8;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            opacity: 1;
        }

        @media (max-width: 768px) {
            .manual-container {
                padding: 20px;
                margin: 20px;
            }

            .toc-grid {
                grid-template-columns: 1fr;
            }

            .step-item {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="manual-container">

        <a href="../index.php" class="home-btn"><i class="fas fa-arrow-left"></i> กลับหน้าหลัก</a>

        <div class="manual-header">
            <div class="icon"><i class="fas fa-book-reader"></i></div>
            <h1>คู่มือการใช้งาน (User Manual)</h1>
            <p>"GreenDigital Recycle: เปลี่ยนขยะให้เป็นทรัพย์ ง่ายๆ ที่บ้านคุณ"</p>
        </div>

        <div class="toc-container">
            <div class="toc-title"><i class="fas fa-list-ul"></i> สารบัญหมวดหมู่</div>
            <div class="toc-grid">
                <a href="#register" class="toc-link"><i class="fas fa-user-plus"></i> 1. การสมัครและเข้าใช้งาน</a>
                <a href="#dashboard" class="toc-link"><i class="fas fa-columns"></i> 2. รู้จักหน้า Dashboard</a>
                <a href="#selling" class="toc-link"><i class="fas fa-truck"></i> 3. วิธีขายขยะ (สำคัญ)</a>
                <a href="#wallet" class="toc-link"><i class="fas fa-wallet"></i> 4. การถอนเงินรายได้</a>
                <a href="#gamification" class="toc-link"><i class="fas fa-trophy"></i> 5. ระบบเลเวลและสิทธิพิเศษ</a>
                <a href="#rewards" class="toc-link"><i class="fas fa-gift"></i> 6. การแลกของรางวัล</a>
                <a href="#profile" class="toc-link"><i class="fas fa-id-card"></i> 7. จัดการข้อมูลส่วนตัว</a>
            </div>
        </div>

        <!-- Section 1 -->
        <div id="register" class="manual-section">
            <div class="section-title"><i class="fas fa-user-plus"></i> 1. การสมัครและเข้าใช้งาน</div>

            <h3>การสมัครสมาชิกใหม่ (Register)</h3>
            <div class="process-steps">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>เข้าสู่หน้าสมัคร</h4>
                        <p>กดปุ่ม "Sign Up" หรือ "สมัครสมาชิก" จากหน้าแรกของเว็บไซต์</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>กรอกข้อมูลส่วนตัว</h4>
                        <p>ตั้งชื่อผู้ใช้ (Username), อีเมลที่ใช้งานจริง, และรหัสผ่าน (อย่างน้อย 6 ตัวอักษร)</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>เริ่มใช้งานทันที</h4>
                        <p>เมื่อสมัครสำเร็จ ระบบจะล็อกอินให้อัตโนมัติ ท่านจะเริ่มที่ระดับ <strong>Seedling (ต้นกล้า)</strong> ทันที</p>
                    </div>
                </div>
            </div>

            <div class="highlight-box tip">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>Tips:</strong> ควรใช้อีเมลจริง เพราะใช้สำหรับกู้รหัสผ่านมาในกรณีลืม Password
                </div>
            </div>
        </div>

        <!-- Section 2 -->
        <div id="dashboard" class="manual-section">
            <div class="section-title"><i class="fas fa-chart-line"></i> 2. รู้จักหน้า Dashboard</div>

            <p>หน้า Dashboard คือศูนย์กลางข้อมูลของคุณ ประกอบด้วยส่วนสำคัญดังนี้:</p>

            <div class="process-steps">
                <div class="step-item">
                    <div class="step-number"><i class="fas fa-wallet" style="color:#f1c40f"></i></div>
                    <div class="step-content">
                        <h4>กระเป๋าเงิน (Wallet)</h4>
                        <p>แสดงยอดเงินรายได้จากการขายขยะ หน่วยเป็น "บาท" สามารถกดถอนเข้าบัญชีธนาคารได้</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number"><i class="fas fa-recycle" style="color:#2ecc71"></i></div>
                    <div class="step-content">
                        <h4>Weight & Carbon</h4>
                        <p>แสดงน้ำหนักขยะรวมที่ท่านช่วยโลกไป และค่า Carbon Credit ที่ลดได้ (ใช้สำหรับกิจกรรมพิเศษ)</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number"><i class="fas fa-bullhorn" style="color:#3498db"></i></div>
                    <div class="step-content">
                        <h4>ข่าวสารและราคากลาง</h4>
                        <p>แถบราคาขยะวันนี้ (Pricing) และข่าวสารกิจกรรมต่างๆ จะวิ่งอยู่ด้านบนเสมอ</p>
                    </div>
                </div>
            </div>

            <div class="highlight-box">
                <i class="fas fa-bell"></i>
                <div>
                    <strong>การแจ้งเตือน (Notification):</strong> หากมีรายการขายที่คนขับรถส่งงานแล้ว จะมีแจ้งเตือนสีแดง เพื่อให้ท่านกด "ยืนยัน" รับเงิน
                </div>
            </div>
        </div>

        <!-- Section 3 -->
        <div id="selling" class="manual-section">
            <div class="section-title"><i class="fas fa-truck-loading"></i> 3. วิธีขายขยะ (สำคัญ)</div>

            <p>เรามีบริการรับซื้อขยะ 2 รูปแบบ เลือกตามความสะดวกของท่าน:</p>

            <h3>3.1 เรียกรถรับถึงที่ (Pickup Service)</h3>
            <p>เหมาะสำหรับท่านที่มีขยะจำนวนมาก หรือไม่สะดวกเดินทาง</p>
            <div class="highlight-box alert">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>เงื่อนไข:</strong> ต้องมีน้ำหนักรวมขั้นต่ำ <strong>10 กิโลกรัม</strong> ขึ้นไป รถจึงจะออกไปรับ
                </div>
            </div>
            <div class="process-steps">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>กดปุ่ม "เรียกรถรับ"</h4>
                        <p>เลือกเมนูรูป <strong>รถบรรทุก</strong> จากแถบด้านบน</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>ระบุวันเวลาและสถานที่</h4>
                        <p>เลือกวันที่และช่วงเวลาที่ท่านอยู่บ้าน จากนั้นกดปุ่ม <strong>ปักหมุด GPS</strong> เพื่อระบุพิกัดที่แม่นยำ</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>เลือกประเภทและถ่ายรูป</h4>
                        <p>เลือกประเภทขยะที่มี และ<strong>ถ่ายรูปกองขยะ</strong> แนบมาด้วย เพื่อให้คนขับประเมินขนาดรถที่ต้องใช้</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>รอการยืนยัน</h4>
                        <p>สถานะจะเปลี่ยนเป็น <code>Pending</code> -> เมื่อคนขับรับงานจะเป็น <code>Accepted</code> -> และเมื่อรถมารับจะเป็น <code>Completed</code></p>
                    </div>
                </div>
            </div>

            <h3>3.2 นำไปส่งเอง (Walk-in)</h3>
            <p>เหมาะสำหรับท่านที่สะดวก หรือมีขยะจำนวนน้อย</p>
            <div class="highlight-box tip">
                <i class="fas fa-star"></i>
                <div>
                    <strong>โบนัสพิเศษ:</strong> การนำส่งเอง (Walk-in) จะได้รับราคาพิเศษเพิ่ม <strong>+20%</strong> สำหรับขยะบางประเภท!
                </div>
            </div>
        </div>

        <!-- Section 4 -->
        <div id="wallet" class="manual-section">
            <div class="section-title"><i class="fas fa-coins"></i> 4. การถอนเงินรายได้</div>

            <h3>ขั้นตอนการถอนเงินเข้าบัญชี</h3>
            <div class="process-steps">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>เข้าเมนูถอนเงิน</h4>
                        <p>กดที่ไอคอนกระเป๋าเงิน หรือยอดเงินใน Dashboard</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>ผูกบัญชีธนาคาร (ครั้งแรก)</h4>
                        <p>เลือกธนาคารและกรอกเลขบัญชี <strong>ชื่อบัญชีต้องตรงกับชื่อที่ลงทะเบียนยืนยันตัวตน</strong></p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>ระบุจำนวนและยืนยัน</h4>
                        <p>ใส่จำนวนเงินที่ต้องการถอน (ไม่มีขั้นต่ำ) และกดยืนยัน</p>
                    </div>
                </div>
            </div>
            <p><em>*เงินจะเข้าบัญชีภายใน 24 ชั่วโมงทำการ</em></p>
        </div>

        <!-- Section 5 -->
        <div id="gamification" class="manual-section">
            <div class="section-title"><i class="fas fa-crown"></i> 5. ระบบเลเวลและสิทธิพิเศษ</div>

            <p>GreenDigital ใช้ระบบ <strong>"ยิ่งขาย ยิ่งได้ราคาดี"</strong> โดยวัดจากน้ำหนักรวมสะสม (Lifetime Weight) ของท่าน</p>

            <table class="user-levels-table">
                <thead>
                    <tr>
                        <th width="25%">ระดับ (Class)</th>
                        <th width="15%">สัญลักษณ์</th>
                        <th width="30%">เงื่อนไข (น้ำหนักรวม)</th>
                        <th>สิทธิพิเศษ (Bonus)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Seedling</strong> (ต้นกล้า)</td>
                        <td style="text-align:center; font-size:1.5rem;">🌱</td>
                        <td>0 - 99 kg</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td><strong>Guardian</strong> (ผู้พิทักษ์)</td>
                        <td style="text-align:center; font-size:1.5rem;">🛡️</td>
                        <td>100 - 499 kg</td>
                        <td style="color:#27ae60; font-weight:bold;">+5% ราคารับซื้อเพิ่ม</td>
                    </tr>
                    <tr>
                        <td><strong>Titan</strong> (ไททัน)</td>
                        <td style="text-align:center; font-size:1.5rem;">👑</td>
                        <td>500+ kg ขึ้นไป</td>
                        <td style="color:#f1c40f; font-weight:bold;">+10% ราคารับซื้อเพิ่ม</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 6 -->
        <div id="rewards" class="manual-section">
            <div class="section-title"><i class="fas fa-gifts"></i> 6. การแลกของรางวัล</div>

            <p>นอกจากการขายได้เงินแล้ว ท่านยังสามารถนำ <strong>Points (หรือน้ำหนักสะสม)</strong> มาแลกของรางวัลพิเศษได้</p>
            <ul>
                <li>เข้าเมนู "แลกรางวัล" (Redeem)</li>
                <li>เลือกของรางวัลที่ต้องการ (เช่น คูปองส่วนลด, แก้วน้ำรักษ์โลก, บริจาคการกุศล)</li>
                <li>กด "แลกทันที" ระบบจะตัดแต้มและจัดส่งของรางวัลตามที่อยู่ที่ระบุไว้</li>
            </ul>
        </div>

        <!-- Section 7 -->
        <div id="profile" class="manual-section">
            <div class="section-title"><i class="fas fa-user-edit"></i> 7. จัดการข้อมูลส่วนตัว</div>

            <div class="highlight-box alert">
                <i class="fas fa-id-card"></i>
                <div>
                    <strong>การยืนยันตัวตน (KYC):</strong> เพื่อความปลอดภัยทางการเงิน ท่านจำเป็นต้องอัปโหลดภาพบัตรประชาชนในหน้าโปรไฟล์ ก่อนทำการถอนเงินครั้งแรก
                </div>
            </div>

            <p>ท่านสามารถแก้ไขรูปโปรไฟล์, เบอร์โทรศัพท์, และที่อยู่จัดส่งของรางวัลได้ที่เมนู <strong>"Profile"</strong> มุมขวาบน</p>
        </div>

        <div style="text-align: center; margin-top: 80px; padding-top: 20px; border-top: 1px solid #eee; color: #999;">
            <p>GreenDigital Recycle &copy; <?php echo date("Y"); ?> - For a Better World</p>
        </div>

    </div>

    <a href="#" class="back-to-top" title="กลับขึ้นด้านบน"><i class="fas fa-arrow-up"></i></a>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>