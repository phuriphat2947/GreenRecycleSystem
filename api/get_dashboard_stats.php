<?php
session_start();
require_once "../db_connect/db_connect.php";


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [];

try {

    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $thai_days = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
    $weekly_data = array_fill(0, 7, 0);

    // 1. Weekly Activity
    // Use proper date filtering for "Current Week" or "Last 7 Days"
    // Here using Last 7 Days rolling
    $stmt = $conn->prepare("
        SELECT WEEKDAY(updated_at) as wk_day, SUM(total_weight) as total 
        FROM orders 
        WHERE user_id = :uid 
          AND status IN ('completed', 'user_confirmed') 
          AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY WEEKDAY(updated_at)
    ");
    $stmt->execute([':uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $weekly_data[$row['wk_day']] = (float) $row['total'];
    }

    $response['weekly_activity'] = [
        'labels' => $thai_days,
        'data' => $weekly_data
    ];
} catch (PDOException $e) {
    $response['weekly_activity'] = ['error' => $e->getMessage()];
}

try {

    // 2. Waste Composition
    // Changed 'weight' to 'actual_weight' based on confirm_order.php schema
    $stmt = $conn->prepare("
        SELECT wt.name, SUM(oi.actual_weight) as total_weight
        FROM order_items oi
        JOIN waste_types wt ON oi.waste_type_id = wt.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = :uid AND o.status IN ('completed', 'user_confirmed')
        GROUP BY wt.name
    ");
    $stmt->execute([':uid' => $user_id]);
    $comp_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];

    if (count($comp_rows) > 0) {
        foreach ($comp_rows as $row) {
            $labels[] = $row['name'];
            $data[] = (float) $row['total_weight'];
        }
    }

    $response['waste_composition'] = [
        'labels' => $labels,
        'data' => $data
    ];
} catch (Exception $e) {
    // Fallback if table doesn't exist or other error
    $response['waste_composition'] = [
        'labels' => [],
        'data' => [],
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
