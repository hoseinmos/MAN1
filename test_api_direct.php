<?php
// test_api_direct.php - تست مستقیم خروجی API
session_start();
header('Content-Type: text/html; charset=utf-8');

// تنظیم session برای تست
$_SESSION["loggedin"] = true;
$_SESSION["user_id"] = 2;
$_SESSION["user_description"] = "حسین مصطفائی فر";
$_SESSION["csrf_token"] = "test_token";

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>تست API</title>
    <style>
        body { font-family: Tahoma; padding: 20px; }
        pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; white-space: pre-wrap; }
        .test-link { display: block; margin: 10px 0; padding: 10px; background: #eee; }
    </style>
</head>
<body>
    <h1>تست مستقیم API</h1>";

// تست 1: include مستقیم و فراخوانی با GET
echo "<h3>تست 1: فراخوانی مستقیم get_pending_assignments</h3>";
$_GET['action'] = 'get_pending_assignments';
ob_start();
include 'process_roll_confirmation.php';
$output = ob_get_clean();
echo "<pre>خروجی API:<br>" . htmlspecialchars($output) . "</pre>";

// تست 2: با CURL
echo "<h3>تست 2: فراخوانی با CURL</h3>";
$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/process_roll_confirmation.php?action=get_pending_assignments';
echo "<p>URL: " . htmlspecialchars($url) . "</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: " . $http_code . "</p>";
echo "<pre>پاسخ:<br>" . htmlspecialchars($response) . "</pre>";

// تست 3: لینک مستقیم
echo "<h3>تست 3: لینک‌های مستقیم</h3>";
echo "<div class='test-link'><a href='process_roll_confirmation.php?action=get_pending_assignments' target='_blank'>کلیک کنید: get_pending_assignments</a></div>";
echo "<div class='test-link'><a href='process_roll_confirmation.php?action=get_confirmed_assignments' target='_blank'>کلیک کنید: get_confirmed_assignments</a></div>";

echo "</body></html>";
?>