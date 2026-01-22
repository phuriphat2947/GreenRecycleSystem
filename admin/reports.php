<?php
session_start();
require_once "../db_connect/db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_page = 'reports';

// --- Filtering Param ---
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// --- Helper Functions for Green Impact ---
function getCO2Factor($wasteName)
{
    // Factor = kg CO2e reduced per kg of waste recycled
    if (stripos($wasteName, 'plastic') !== false || stripos($wasteName, 'พลาสติก') !== false) return 1.5;
    if (stripos($wasteName, 'glass') !== false || stripos($wasteName, 'แก้ว') !== false) return 0.3;
    if (stripos($wasteName, 'paper') !== false || stripos($wasteName, 'กระดาษ') !== false) return 1.0;
    if (stripos($wasteName, 'metal') !== false || stripos($wasteName, 'เหล็ก') !== false || stripos($wasteName, 'โลหะ') !== false) return 4.0;
    return 1.0; // Default
}

// --- Fetch Data ---

// 1. Members Data
try {
    // Filter by join date
    $sql_users = "SELECT id, username, email, role, membership_level, total_recycled_weight, created_at 
                  FROM users 
                  WHERE DATE(created_at) BETWEEN :sdate AND :edate
                  ORDER BY id ASC";
    $stmt_users = $conn->prepare($sql_users);
    $stmt_users->execute([':sdate' => $start_date, ':edate' => $end_date]);
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// 2. Content Data
try {
    $sql_content = "SELECT id, title, type, status, created_at 
                    FROM contents 
                    WHERE DATE(created_at) BETWEEN :sdate AND :edate 
                    ORDER BY created_at DESC";
    $stmt_content = $conn->prepare($sql_content);
    $stmt_content->execute([':sdate' => $start_date, ':edate' => $end_date]);
    $contents = $stmt_content->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contents = [];
}

// 3. Green Impact Data
// Calculate total weight per waste type filtered by date
$impact_data = [];
$total_co2_saved = 0;
$total_energy_saved = 0;

// For Chart Data
$chart_waste_labels = [];
$chart_waste_weights = [];
$chart_waste_co2 = [];

try {
    $sql_impact = "SELECT wt.name, SUM(oi.actual_weight) as total_weight 
                   FROM order_items oi 
                   JOIN orders o ON oi.order_id = o.id 
                   JOIN waste_types wt ON oi.waste_type_id = wt.id
                   WHERE o.status = 'completed' 
                   AND DATE(o.updated_at) BETWEEN :sdate AND :edate
                   GROUP BY wt.name";
    $stmt_impact = $conn->prepare($sql_impact);
    $stmt_impact->execute([':sdate' => $start_date, ':edate' => $end_date]);
    $rows = $stmt_impact->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $weight = floatval($row['total_weight']);
        $co2_factor = getCO2Factor($row['name']);
        $energy_factor = 4.0;

        $co2_saved = $weight * $co2_factor;
        $energy_saved = $weight * $energy_factor;

        $total_co2_saved += $co2_saved;
        $total_energy_saved += $energy_saved;

        $impact_data[] = [
            'type' => $row['name'],
            'weight' => $weight,
            'co2' => $co2_saved,
            'energy' => $energy_saved
        ];

        // Prepare Chart Data
        $chart_waste_labels[] = $row['name'];
        $chart_waste_weights[] = $weight;
        $chart_waste_co2[] = $co2_saved;
    }
} catch (PDOException $e) {
    // ignore
}

// 4. Monthly Trend Data (Last 12 Months) - for Trend Chart
// Just aggregate by month for the selected range might be clearer
// Let's do Daily Trend for the selected range
$trend_labels = [];
$trend_data = [];
try {
    $sql_trend = "SELECT DATE(updated_at) as date, SUM(total_weight) as weight 
                  FROM orders 
                  WHERE status = 'completed' AND DATE(updated_at) BETWEEN :sdate AND :edate
                  GROUP BY DATE(updated_at) ORDER BY date ASC";
    $stmt_trend = $conn->prepare($sql_trend);
    $stmt_trend->execute([':sdate' => $start_date, ':edate' => $end_date]);
    $trend_rows = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trend_rows as $tr) {
        $trend_labels[] = date('d/m', strtotime($tr['date']));
        $trend_data[] = $tr['weight'];
    }
} catch (PDOException $e) {
}

// 5. Financial Data (Income vs Expense)
$financial_data = [];
try {
    // Expense = Total Amount Paid to User
    // Income (Simulated) = Weight * Market Price (assume 20% margin over base price)
    $sql_finance = "SELECT DATE(o.updated_at) as date, 
                           COUNT(o.id) as total_orders,
                           SUM(o.total_weight) as total_weight,
                           SUM(o.total_amount) as total_expense
                    FROM orders o 
                    WHERE o.status = 'completed' AND DATE(o.updated_at) BETWEEN :sdate AND :edate
                    GROUP BY DATE(o.updated_at) 
                    ORDER BY date DESC";
    $stmt_fin = $conn->prepare($sql_finance);
    $stmt_fin->execute([':sdate' => $start_date, ':edate' => $end_date]);
    $fin_rows = $stmt_fin->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fin_rows as $row) {
        // Estimate Income (20% Profit Margin assumption)
        $estimated_income = $row['total_expense'] * 1.2;
        $profit = $estimated_income - $row['total_expense'];

        $financial_data[] = [
            'date' => $row['date'],
            'orders' => $row['total_orders'],
            'weight' => $row['total_weight'],
            'expense' => $row['total_expense'],
            'income' => $estimated_income,
            'profit' => $profit
        ];
    }
} catch (PDOException $e) {
    // ignore
}


?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงานผล (Reports) - GreenDigital Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f4f6f9;
        }

        .content-wrapper {
            padding: 2rem;
            overflow-y: auto;
        }

        .report-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filter-box {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .btn-filter {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }

        .nav-item {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
        }

        .nav-item:hover {
            color: var(--primary);
            background: #f0f9f0;
        }

        .nav-item.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.4s;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Chart Layout */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
        }

        /* DataTables Custom */
        table.dataTable thead th {
            background-color: var(--secondary);
            color: white;
        }

        .dt-buttons .dt-button {
            background: var(--primary) !important;
            color: white !important;
            border: none !important;
            border-radius: 4px !important;
            font-size: 0.9rem !important;
            margin-bottom: 10px;
        }

        .dt-buttons .dt-button:hover {
            background: #27ae60 !important;
            opacity: 0.9;
        }

        .impact-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .impact-card {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .impact-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        .impact-card h3 {
            margin: 0;
            font-size: 2rem;
        }

        .impact-card p {
            margin: 5px 0 0;
            font-size: 1rem;
            opacity: 0.9;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-title">
                <h2><i class="fas fa-chart-line"></i> รายงานผล (Reports)</h2>
                <span class="admin-subtitle">Green Digital & สถิติระบบ</span>
            </div>
        </header>

        <main class="content-wrapper">

            <!-- Filter Section -->
            <form method="GET" class="filter-box">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> กรองข้อมูล:</label>
                </div>
                <div class="filter-group">
                    เริ่ม <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    ถึง <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> ค้นหา</button>
            </form>

            <div class="report-card">
                <div class="nav-tabs">
                    <div class="nav-item active" onclick="openTab(event, 'tab-green')"><i class="fas fa-globe-asia"></i> Green Digital Impact</div>
                    <div class="nav-item" onclick="openTab(event, 'tab-members')"><i class="fas fa-users"></i> สมาชิก (Members)</div>
                    <div class="nav-item" onclick="openTab(event, 'tab-content')"><i class="fas fa-newspaper"></i> เนื้อหา (Content)</div>
                    <div class="nav-item" onclick="openTab(event, 'tab-finance')"><i class="fas fa-coins"></i> การเงิน (Financial)</div>
                </div>

                <!-- TAB 1: GREEN IMPACT -->
                <div id="tab-green" class="tab-pane active">
                    <div class="impact-box">
                        <div class="impact-card">
                            <i class="fas fa-cloud"></i>
                            <h3><?php echo number_format($total_co2_saved, 1); ?> kg</h3>
                            <p>ก๊าซคาร์บอนที่ลดได้ (CO2)</p>
                        </div>
                        <div class="impact-card" style="background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%);">
                            <i class="fas fa-bolt"></i>
                            <h3><?php echo number_format($total_energy_saved, 1); ?> kWh</h3>
                            <p>พลังงานที่ประหยัดได้ (Energy)</p>
                        </div>
                        <div class="impact-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                            <i class="fas fa-recycle"></i>
                            <h3><?php echo count($impact_data); ?> Types</h3>
                            <p>ประเภทขยะที่หมุนเวียน</p>
                        </div>
                    </div>

                    <!-- Charts Area -->
                    <div class="charts-container">
                        <div class="chart-box">
                            <h4><i class="fas fa-pie-chart"></i> สัดส่วนการลด CO2 ตามประเภทขยะ</h4>
                            <canvas id="co2Chart"></canvas>
                        </div>
                        <div class="chart-box">
                            <h4><i class="fas fa-chart-line"></i> แนวโน้มการรีไซเคิล (Selected Period)</h4>
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                    <h3 style="border-left: 5px solid var(--primary); padding-left: 10px; margin-bottom: 15px;">รายละเอียดการลดผลกระทบ (Breakdown)</h3>
                    <table id="table-green" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ประเภทขยะ</th>
                                <th>น้ำหนักรวม (kg)</th>
                                <th>ลด CO2 (kgCO2e)</th>
                                <th>ประหยัดไฟ (kWh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($impact_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                                    <td><?php echo number_format($row['weight'], 2); ?></td>
                                    <td style="color: green; font-weight: bold;"><?php echo number_format($row['co2'], 2); ?></td>
                                    <td><?php echo number_format($row['energy'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB 2: MEMBERS -->
                <div id="tab-members" class="tab-pane">
                    <h3 style="border-left: 5px solid var(--info); padding-left: 10px; margin-bottom: 15px;">รายชื่อสมาชิกทั้งหมด</h3>
                    <table id="table-members" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Level</th>
                                <th>Recycled (kg)</th>
                                <th>Join Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($user['role'] == 'admin') ? 'danger' : (($user['role'] == 'driver') ? 'warning' : 'success'); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($user['membership_level'] ?? '-'); ?></td>
                                    <td><?php echo number_format($user['total_recycled_weight'], 1); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB 3: CONTENT -->
                <div id="tab-content" class="tab-pane">
                    <h3 style="border-left: 5px solid var(--secondary); padding-left: 10px; margin-bottom: 15px;">รายงานเนื้อหาในระบบ</h3>
                    <table id="table-content" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>หัวข้อ (Title)</th>
                                <th>ประเภท</th>
                                <th>สถานะ</th>
                                <th>วันที่สร้าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contents as $content): ?>
                                <tr>
                                    <td><?php echo $content['id']; ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td><?php echo ucfirst($content['type']); ?></td>
                                    <td>
                                        <span style="color: <?php echo ($content['status'] == 'published') ? 'green' : 'gray'; ?>; font-weight: bold;">
                                            <?php echo ucfirst($content['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($content['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB 4: FINANCIAL -->
                <div id="tab-finance" class="tab-pane">
                    <h3 style="border-left: 5px solid #f1c40f; padding-left: 10px; margin-bottom: 15px;">รายงานรายรับ-รายจ่าย (Income & Expenses)</h3>
                    <div class="alert" style="background: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                        <i class="fas fa-info-circle"></i> <b>หมายเหตุ:</b> รายรับ (Income) เป็นการประมาณการมูลค่าตลาด (Mark-up 20%) จากยอดรับซื้อจริง
                    </div>
                    <table id="table-finance" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>วันที่ (Date)</th>
                                <th>จำนวนรายการ</th>
                                <th>น้ำหนักรวม (kg)</th>
                                <th>รายจ่าย (ซื้อขยะ)</th>
                                <th>รายรับ (ประมาณการ)</th>
                                <th>กำไรสุทธิ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financial_data as $fin): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($fin['date'])); ?></td>
                                    <td><?php echo number_format($fin['orders']); ?></td>
                                    <td><?php echo number_format($fin['weight'], 2); ?></td>
                                    <td style="color: #c0392b;"><?php echo number_format($fin['expense'], 2); ?> ฿</td>
                                    <td style="color: #27ae60;"><?php echo number_format($fin['income'], 2); ?> ฿</td>
                                    <td style="font-weight: bold; color: <?php echo ($fin['profit'] >= 0) ? '#27ae60' : '#c0392b'; ?>;">
                                        <?php echo number_format($fin['profit'], 2); ?> ฿
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="3" style="text-align: right;">รวมทั้งหมด:</td>
                                <td style="color: #c0392b;">-</td>
                                <td style="color: #27ae60;">-</td>
                                <td>-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>

        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <script>
        // Tab Linking
        function openTab(evt, tabName) {
            $('.tab-pane').removeClass('active');
            $('.nav-item').removeClass('active');
            $('#' + tabName).addClass('active');
            $(evt.currentTarget).addClass('active');
        }

        $(document).ready(function() {
            // Config for all tables
            const exportConfig = {
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel',
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> พิมพ์ / บันทึกเป็น PDF (Print/PDF)',
                        title: '',
                        customize: function(win) {
                            var now = new Date();
                            var thaidate = new Intl.DateTimeFormat('th-TH', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            }).format(now);

                            // Force Block Layout
                            $(win.document.body).css({
                                'display': 'block',
                                'font-family': 'Sarabun, sans-serif',
                                'background': 'white',
                                'margin': '0',
                                'padding': '20px'
                            });

                            // Government Memo Header
                            var headerContent = `
                                <div style="display: block; width: 100%; text-align: center; margin-bottom: 20px;">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c9/Emblem_of_Thailand.svg/200px-Emblem_of_Thailand.svg.png" style="width: 60px; height: auto; margin-bottom: 10px;">
                                    <div style="font-weight: bold; font-size: 22pt; margin-bottom: 5px;">บันทึกข้อความ</div>
                                </div>
                                <div style="display: block; width: 100%; font-size: 16pt; margin-bottom: 20px; line-height: 1.6; border-bottom: 1px solid #000; padding-bottom: 10px;">
                                    <b>ส่วนราชการ</b> โครงการ GreenDigital Recycle System<br>
                                    <b>ที่</b> GD-${now.getFullYear()}/${now.getMonth() + 1} &nbsp;&nbsp;&nbsp;&nbsp;
                                    <b>วันที่</b> ${thaidate}<br>
                                    <b>เรื่อง</b> รายงานสรุปผลการดำเนินงาน (Simple Report)
                                </div>
                            `;

                            $(win.document.body).prepend(headerContent);

                            // Clean Minimal Styling
                            $(win.document.body).find('h1').remove();

                            // Table Styling
                            var table = $(win.document.body).find('table');
                            table.addClass('compact');
                            table.css({
                                'font-size': '14pt',
                                'width': '100%',
                                'border-collapse': 'collapse',
                                'margin-bottom': '20px'
                            });

                            table.find('th, td').css({
                                'border': '1px solid #333',
                                'padding': '8px',
                                'color': '#000',
                                'vertical-align': 'middle'
                            });

                            table.find('th').css({
                                'background-color': '#f0f0f0',
                                'font-weight': 'bold',
                                'text-align': 'center'
                            });

                            // Font & Print Settings
                            $(win.document.head).append('<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">');
                            $(win.document.head).append(`<style>
                                @page { size: A4; margin: 1.5cm; }
                                body { font-family: 'Sarabun', sans-serif !important; -webkit-print-color-adjust: exact; }
                                .footer-signatures {
                                    margin-top: 50px;
                                    display: flex;
                                    justify-content: space-between;
                                    page-break-inside: avoid;
                                    width: 100%;
                                }
                                .sig-block {
                                    text-align: center;
                                    width: 32%;
                                }
                                .sig-line {
                                    border-bottom: 1px dotted #000;
                                    width: 90%;
                                    margin: 30px auto 10px auto;
                                    height: 1px;
                                }
                            </style>`);

                            // Add Signature Footer
                            var footerContent = `
                                <div class="footer-signatures">
                                    <div class="sig-block">
                                        <div style="height: 40px;"></div>
                                        ลงชื่อ......................................................<br>
                                        (......................................................)<br>
                                        <b>ผู้จัดทำรายงาน</b><br>
                                        วันที่ ......../......../............
                                    </div>
                                    <div class="sig-block">
                                        <div style="height: 40px;"></div>
                                        ลงชื่อ......................................................<br>
                                        (......................................................)<br>
                                        <b>ผู้ตรวจสอบ</b><br>
                                        วันที่ ......../......../............
                                    </div>
                                    <div class="sig-block">
                                        <div style="height: 40px;"></div>
                                        ลงชื่อ......................................................<br>
                                        (......................................................)<br>
                                        <b>ผู้อนุมัติ</b><br>
                                        วันที่ ......../......../............
                                    </div>
                                </div>
                                <div style="text-align: center; margin-top: 30px; font-size: 10pt; color: #888;">
                                    ระบบรายงานอัตโนมัติ GreenDigital | สั่งพิมพ์เมื่อ ${new Date().toLocaleString('th-TH')}
                                </div>
                            `;
                            $(win.document.body).append(footerContent);
                        }
                    }
                ],
                pageLength: 10,
                language: {
                    search: "ค้นหา (Search):",
                    paginate: {
                        next: "ถัดไป",
                        previous: "ก่อนหน้า"
                    },
                    info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ"
                }
            };

            $('#table-green').DataTable(exportConfig);
            $('#table-members').DataTable(exportConfig);
            $('#table-content').DataTable(exportConfig);
            $('#table-finance').DataTable(exportConfig);

            // --- CHARTS CONFIG ---
            // Data from PHP
            const wasteLabels = <?php echo json_encode($chart_waste_labels); ?>;
            const wasteCO2 = <?php echo json_encode($chart_waste_co2); ?>;
            const trendLabels = <?php echo json_encode($trend_labels); ?>;
            const trendData = <?php echo json_encode($trend_data); ?>;

            // 1. CO2 Breakdown (Doughnut)
            new Chart(document.getElementById('co2Chart'), {
                type: 'doughnut',
                data: {
                    labels: wasteLabels,
                    datasets: [{
                        data: wasteCO2,
                        backgroundColor: ['#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // 2. Trend Chart (Line)
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'น้ำหนักขยะที่รับซื้อ (kg)',
                        data: trendData,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>