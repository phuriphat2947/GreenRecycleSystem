<div class="admin-sidebar">
    <div class="sidebar-header">
        Admin <span>Panel</span>
    </div>

    <ul class="sidebar-menu">
        <span style="margin-left: 10px; color: #56fd96ff;font-size:.7rem;">ข้อมูลภาพรวม</span>
        <li>
            <a href="index.php" class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> ภาพรวม
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> รายงานผล
            </a>
        </li>

        <hr style="margin: .2rem;">
        <span style=" margin-left: 10px; color: #56fd96ff; font-size:.7rem;">จัดการข้อมูล</span>


        <li>
            <a href="users.php" class="<?php echo ($current_page == 'users' && (!isset($_GET['role']) || $_GET['role'] == 'user')) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> สมาชิก
            </a>
        </li>
        <li>
            <a href="users.php?role=driver" class="<?php echo ($current_page == 'users' && isset($_GET['role']) && $_GET['role'] == 'driver') ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> คนขับรถ
            </a>
        </li>
        <li>
            <a href="waste_types.php" class="<?php echo ($current_page == 'waste_types') ? 'active' : ''; ?>">
                <i class="fas fa-recycle"></i> เพิ่มรายการขยะรับซื้อ
            </a>
        </li>
        <hr style="margin: .2rem;">
        <span style="margin-left: 10px; color: #56fd96ff;font-size:.7rem;">จัดการข่าวประชาสัมพันธ์</span>

        <li>
            <a href="content.php" class="<?php echo ($current_page == 'content') ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i> ข่าวประชาสัมพันธ์
            </a>
        </li>
        <li>
            <a href="announcements.php" class="<?php echo ($current_page == 'announcements') ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> ข่าวตัววิ่ง
            </a>
        </li>
        <hr style="margin: .2rem;">
        <span style="margin-left: 10px; color: #56fd96ff;font-size:.7rem;">จัดการรายการ</span>
        <li>
            <?php
            // Fetch Unread Count
            $unread_count = 0;
            if (isset($conn)) {
                try {
                    $stmt_n = $conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
                    $unread_count = $stmt_n->fetchColumn();
                } catch (PDOException $e) {
                }
            }
            ?>
            <a href="notifications.php" class="<?php echo ($current_page == 'notifications') ? 'active' : ''; ?>" style="display:flex; justify-content:space-between; align-items:center; position:relative;">
                <span><i class="fas fa-bell"></i> การแจ้งเตือน</span>
                <?php if ($unread_count > 0): ?>
                    <span style="background:#ff4757; color:white; font-size:0.8rem; font-weight:bold; padding:2px 8px; border-radius:50px; box-shadow:0 2px 5px rgba(255,71,87,0.4); min-width:24px; text-align:center;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="orders.php" class="<?php echo ($current_page == 'orders') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i> รายการรับซื้อ
            </a>
        </li>
        <li>
            <a href="withdrawals.php" class="<?php echo ($current_page == 'withdrawals') ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-usd"></i> การถอนเงิน
            </a>
        </li>
        <hr style="margin: .2rem;">
        <span style="margin-left: 10px; color: #56fd96ff;font-size:.7rem;">จัดการของรางวัลพิเศษ</span>
        <li>
            <a href="rewards.php" class="<?php echo ($current_page == 'rewards') ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i> ของรางวัล
            </a>
        </li>
        <li>
            <a href="redemptions.php" class="<?php echo ($current_page == 'redemptions') ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i> รายการแลกแต้ม
            </a>
        </li>




        <hr style="margin: .2rem;">
        <span style="margin-left: 10px; color: #56fd96ff;font-size:.7rem;">ติดต่อสอบถามปัญหา</span>
        <li class="<?php echo ($current_page == 'chat') ? 'active' : ''; ?>">
            <a href="chat.php">
                <i class="fas fa-comments"></i> Chat Support
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">
        <ul class="sidebar-menu">
            <li>
                <a href="../components/homepage.php">
                    <i class="fas fa-desktop"></i> หน้าเว็บไซต์
                </a>
            </li>
            <li>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </li>
        </ul>
    </div>
</div>