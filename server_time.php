<?php
// server_time.php - توابع مربوط به زمان سرور

// تابع دریافت زمان دقیق ایران از پایگاه داده
function getServerTime() {
    global $conn;
    
    if (!$conn) {
        return date('Y-m-d H:i:s'); // بازگشت زمان سرور در صورت عدم اتصال به دیتابیس
    }
    
    $sql = "SELECT NOW() as server_time";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['server_time'];
    }
    
    return date('Y-m-d H:i:s'); // بازگشت زمان سرور در صورت خطا
}

// تبدیل به تاریخ شمسی با استفاده از تابع موجود convert_to_jalali
function getMySQLJalaliDateTime() {
    // دریافت زمان از سرور
    $server_time = getServerTime();
    
    // فراخوانی تابع تبدیل که قبلاً در پروژه شما تعریف شده است
    return convert_to_jalali($server_time);
}
?>