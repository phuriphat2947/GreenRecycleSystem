<?php
// Set session cookie to expire when browser is closed (Default behavior)
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params(0);
    session_start();
}
date_default_timezone_set('Asia/Bangkok');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "greendigital_extended";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- Auto Login (Remember Me) ---
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        // Check DB
        $stmt_ck = $conn->prepare("SELECT id, username, role, level FROM users WHERE remember_token = :tk");
        $stmt_ck->execute([':tk' => $token]);
        if ($stmt_ck->rowCount() > 0) {
            $user_auto = $stmt_ck->fetch(PDO::FETCH_ASSOC);
            // Restore Session
            $_SESSION['user_id'] = $user_auto['id'];
            $_SESSION['username'] = $user_auto['username'];
            $_SESSION['role'] = $user_auto['role'];
            $_SESSION['level'] = $user_auto['level']; // Ensure level is restored for Eco-Warrior
        }
    }
    // --------------------------------
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
