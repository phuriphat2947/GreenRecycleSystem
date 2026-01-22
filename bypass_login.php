<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['username'] = 'gn_user';
$_SESSION['role'] = 'user';
header("Location: components/homepage.php");
exit();
