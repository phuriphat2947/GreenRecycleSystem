<?php
// api/debug_check.php
// This script checks for common issues causing JSON/Chart failures

// 1. Enable Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 API Debug Tool</h1>";
echo "<p>Checking environment...</p>";

// 2. Check for Whitespace Injection (Premature Output)
ob_start();
require_once "../db_connect/db_connect.php";
$output = ob_get_contents();
ob_end_clean();

if (strlen($output) > 0) {
    echo "<div style='background:#ffcccc; padding:10px; border:1px solid red; margin-bottom:10px;'>";
    echo "<h3>❌ CRITICAL ERROR: Premature Output Detected!</h3>";
    echo "<p>The file <code>db_connect.php</code> (or an included file) is outputting text/whitespace before it should.</p>";
    echo "<p><strong>Output length:</strong> " . strlen($output) . " bytes</p>";
    echo "<p><strong>Content (wrapped in quotes):</strong> '<pre>" . htmlspecialchars($output) . "</pre>'</p>";
    echo "<p>This breaks JSON responses. Check for spaces before <code>&lt;?php</code> or after <code>?&gt;</code> in your included files.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#ccffcc; padding:10px; border:1px solid green; margin-bottom:10px;'>";
    echo "<h3>✅ CLEAN: No premature output detected from db_connect.php</h3>";
    echo "</div>";
}

// 3. Test Database Connection
echo "<h3>Testing Database...</h3>";
if (isset($conn)) {
    echo "<p style='color:green;'>✅ \$conn object exists.</p>";
    try {
        $stmt = $conn->query("SELECT 1");
        echo "<p style='color:green;'>✅ Database query successful.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Database query failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red;'>❌ \$conn object NOT found.</p>";
}

// 4. Test Session
echo "<h3>Testing Session...</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color:green;'>✅ Session is active.</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p style='color:green;'>✅ User Logged In (ID: " . $_SESSION['user_id'] . ")</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ No User ID in session. (You might need to login first)</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Session not started.</p>";
}

// 5. Test Stats Logic (Simulating get_dashboard_stats.php)
echo "<h3>Testing Statistics Logic...</h3>";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    try {
        // Weekly Data
        $stmt = $conn->prepare("
            SELECT WEEKDAY(created_at) as wk_day, SUM(total_weight) as total 
            FROM orders 
            WHERE user_id = :uid 
            AND status = 'completed' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY WEEKDAY(created_at)
        ");
        $stmt->execute([':uid' => $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>✅ Weekly Data Query: Found " . count($rows) . " rows.</p>";

        // Composition Data
        $stmt = $conn->prepare("
            SELECT wt.name, SUM(oi.weight) as total_weight
            FROM order_items oi
            JOIN waste_types wt ON oi.waste_type_id = wt.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = :uid AND o.status = 'completed'
            GROUP BY wt.name
        ");
        $stmt->execute([':uid' => $user_id]);
        $comp_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>✅ Composition Data Query: Found " . count($comp_rows) . " rows.</p>";

    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Data Logic Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Skipping data test (Not logged in).</p>";
}

echo "<hr>";
echo "<p><em>Run this script in your browser: <code>/api/debug_check.php</code></em></p>";
?>