<?php
// test_roll_confirmation.php - تست سیستم تایید رول کاغذ
session_start();

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>تست سیستم تایید رول</title>
    <style>
        body { font-family: Tahoma, Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>تست سیستم تایید رول کاغذ</h1>";

// 1. بررسی session
echo "<h3>1. بررسی Session:</h3>";
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    echo "<p class='success'>✓ وارد شده‌اید</p>";
    echo "<p>ID کاربر: " . ($_SESSION["user_id"] ?? "نامشخص") . "</p>";
    echo "<p>نام کاربر: " . ($_SESSION["user_description"] ?? "نامشخص") . "</p>";
} else {
    echo "<p class='error'>✗ وارد نشده‌اید</p>";
}

// 2. بررسی وجود فایل‌ها
echo "<h3>2. بررسی وجود فایل‌ها:</h3>";
$files = [
    'process_roll_confirmation.php',
    'dashboard.js',
    'config.php',
    'jdf.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ {$file} موجود است</p>";
    } else {
        echo "<p class='error'>✗ {$file} موجود نیست</p>";
    }
}

// 3. بررسی اتصال به دیتابیس
echo "<h3>3. بررسی اتصال به دیتابیس:</h3>";
if (file_exists('config.php')) {
    include 'config.php';
    
    if ($conn) {
        echo "<p class='success'>✓ اتصال به دیتابیس برقرار است</p>";
        
        // بررسی جدول roll_assignments
        $table_check = $conn->query("SHOW TABLES LIKE 'roll_assignments'");
        if ($table_check && $table_check->num_rows > 0) {
            echo "<p class='success'>✓ جدول roll_assignments موجود است</p>";
            
            // بررسی تخصیص‌های تایید نشده برای کاربر
            if (isset($_SESSION["user_id"])) {
                $user_id = $_SESSION["user_id"];
                
                $sql = "SELECT COUNT(*) as count FROM roll_assignments WHERE user_id = ? AND confirmed = 0";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    echo "<p class='info'>تعداد تخصیص‌های تایید نشده شما: " . $row['count'] . "</p>";
                    $stmt->close();
                }
                
                // نمایش چند تخصیص اخیر
                $sql = "SELECT a.*, u.description as assigned_by_name 
                        FROM roll_assignments a 
                        LEFT JOIN access_codes u ON a.assigned_by = u.id 
                        WHERE a.user_id = ? 
                        ORDER BY a.created_at DESC 
                        LIMIT 5";
                
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    echo "<h4>5 تخصیص اخیر شما:</h4>";
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>ID</th><th>تعداد</th><th>تاریخ تخصیص</th><th>توسط</th><th>وضعیت</th></tr>";
                    
                    while ($row = $result->fetch_assoc()) {
                        $status = $row['confirmed'] ? '<span class="success">تایید شده</span>' : '<span class="error">در انتظار تایید</span>';
                        echo "<tr>";
                        echo "<td>{$row['id']}</td>";
                        echo "<td>{$row['quantity']}</td>";
                        echo "<td>{$row['assign_date']}</td>";
                        echo "<td>{$row['assigned_by_name']}</td>";
                        echo "<td>{$status}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    $stmt->close();
                }
            }
        } else {
            echo "<p class='error'>✗ جدول roll_assignments موجود نیست</p>";
        }
    } else {
        echo "<p class='error'>✗ اتصال به دیتابیس برقرار نیست</p>";
    }
} else {
    echo "<p class='error'>✗ فایل config.php یافت نشد</p>";
}

// 4. تست درخواست‌های API
echo "<h3>4. تست درخواست‌های API:</h3>";
echo "<p><a href='process_roll_confirmation.php?action=get_pending_assignments' target='_blank'>تست get_pending_assignments</a></p>";
echo "<p><a href='process_roll_confirmation.php?action=get_confirmed_assignments' target='_blank'>تست get_confirmed_assignments</a></p>";

// 5. نمایش محتوای جدول
if (isset($conn) && $conn) {
    echo "<h3>5. ساختار جدول roll_assignments:</h3>";
    $structure = $conn->query("DESCRIBE roll_assignments");
    if ($structure) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>{$value}</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "</body></html>";
?>