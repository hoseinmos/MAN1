<?php
// test_repair.php - برای تست مشکلات بخش تعمیرات
include 'config.php';
include 'jdf.php';

// نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
error_reporting(E_ALL);

// شروع HTML
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست مدیریت تعمیرات</title>
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; padding: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>تست مدیریت تعمیرات</h1>
    
    <?php
    // تست 1: بررسی Session
    echo "<div class='test-result'>";
    echo "<h3>تست 1: بررسی Session</h3>";
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        echo "<p class='success'>✓ کاربر لاگین شده</p>";
        echo "<p>نام کاربر: " . ($_SESSION["user_description"] ?? "نامشخص") . "</p>";
        echo "<p>ID کاربر: " . ($_SESSION["user_id"] ?? "نامشخص") . "</p>";
    } else {
        echo "<p class='error'>✗ کاربر لاگین نیست</p>";
    }
    echo "</div>";

    // تست 2: بررسی دیتابیس
    echo "<div class='test-result'>";
    echo "<h3>تست 2: بررسی اتصال دیتابیس</h3>";
    if ($conn) {
        echo "<p class='success'>✓ اتصال به دیتابیس برقرار است</p>";
        
        // بررسی جدول device_repairs
        $test_query = "SHOW TABLES LIKE 'device_repairs'";
        $result = $conn->query($test_query);
        if ($result->num_rows > 0) {
            echo "<p class='success'>✓ جدول device_repairs وجود دارد</p>";
        } else {
            echo "<p class='error'>✗ جدول device_repairs وجود ندارد</p>";
        }
        
        // بررسی جدول repair_history
        $test_query = "SHOW TABLES LIKE 'repair_history'";
        $result = $conn->query($test_query);
        if ($result->num_rows > 0) {
            echo "<p class='success'>✓ جدول repair_history وجود دارد</p>";
        } else {
            echo "<p class='error'>✗ جدول repair_history وجود ندارد</p>";
        }
    } else {
        echo "<p class='error'>✗ اتصال به دیتابیس برقرار نیست</p>";
    }
    echo "</div>";

    // تست 3: بررسی دسترسی به مسئول تعمیرات
    echo "<div class='test-result'>";
    echo "<h3>تست 3: بررسی دسترسی مسئول تعمیرات</h3>";
    if (isset($_SESSION["user_description"]) && $_SESSION["user_description"] === "مسئول تعمیرات") {
        echo "<p class='success'>✓ کاربر مسئول تعمیرات است</p>";
    } else {
        echo "<p class='error'>✗ کاربر مسئول تعمیرات نیست</p>";
    }
    echo "</div>";

    // تست 4: بررسی داده‌های ایستا
    echo "<div class='test-result'>";
    echo "<h3>تست 4: جستجوی رکوردهای در انتظار</h3>";
    if ($conn) {
        $sql = "SELECT * FROM device_repairs WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5";
        $result = $conn->query($sql);
        
        if ($result === false) {
            echo "<p class='error'>خطا در اجرای کوئری: " . $conn->error . "</p>";
        } else {
            echo "<p class='success'>✓ کوئری اجرا شد</p>";
            echo "<p>تعداد رکوردها: " . $result->num_rows . "</p>";
            
            if ($result->num_rows > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>سریال</th><th>وضعیت</th><th>تاریخ</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['terminal_serial'] . "</td>";
                    echo "<td>" . $row['status'] . "</td>";
                    echo "<td>" . $row['created_at'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='info'>هیچ رکوردی در انتظار یافت نشد</p>";
            }
        }
    }
    echo "</div>";

    // تست 5: تست AJAX
    echo "<div class='test-result'>";
    echo "<h3>تست 5: تست درخواست AJAX به process_repair.php</h3>";
    echo "<button onclick='testAjax()'>تست AJAX</button>";
    echo "<div id='ajax-result'></div>";
    echo "</div>";
    ?>

    <script>
    function testAjax() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'process_repair.php?action=get_pending_repairs', true);
        xhr.onload = function() {
            var result = document.getElementById('ajax-result');
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    result.innerHTML = "<p class='success'>✓ پاسخ دریافت شد:</p><pre>" + 
                                     JSON.stringify(response, null, 2) + "</pre>";
                } catch(e) {
                    result.innerHTML = "<p class='error'>✗ خطا در پارس JSON:</p><pre>" + 
                                     xhr.responseText + "</pre>";
                }
            } else {
                result.innerHTML = "<p class='error'>✗ خطا در ارتباط: " + xhr.status + "</p>";
            }
        };
        xhr.onerror = function() {
            document.getElementById('ajax-result').innerHTML = 
                "<p class='error'>✗ خطا در ارسال درخواست</p>";
        };
        xhr.send();
    }
    </script>

    <hr>
    
    <h2>فایل‌های مورد نیاز</h2>
    <ul>
        <?php
        $required_files = [
            'process_repair.php',
            'repair_management.php',
            'repair.js',
            'config.php',
            'jdf.php'
        ];
        
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                echo "<li class='success'>✓ $file موجود است</li>";
            } else {
                echo "<li class='error'>✗ $file موجود نیست</li>";
            }
        }
        ?>
    </ul>

    <h2>اطلاعات سرور</h2>
    <ul>
        <li>PHP Version: <?php echo phpversion(); ?></li>
        <li>MySQL Connection: <?php echo ($conn ? 'Connected' : 'Not Connected'); ?></li>
        <li>Session Status: <?php echo (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'); ?></li>
    </ul>
</body>
</html>