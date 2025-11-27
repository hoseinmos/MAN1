<?php
// update_database_time.php - این فایل را در اسکریپت به‌روزرسانی اتوماتیک دیتابیس خود استفاده کنید

// اتصال به دیتابیس
include 'config.php';

// بررسی اتصال
if(!$conn) {
    die("خطا در اتصال به پایگاه داده: " . mysqli_connect_error());
}

// به‌روزرسانی زمان آخرین به‌روزرسانی دیتابیس
$description = "به‌روزرسانی دوره‌ای دیتابیس"; // می‌توانید توضیحات را تغییر دهید
$sql = "INSERT INTO system_updates (update_type, update_time, description) 
        VALUES ('database', NOW(), ?)";

if($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $description);
    
    if($stmt->execute()) {
        echo "تاریخ به‌روزرسانی با موفقیت ثبت شد.\n";
    } else {
        echo "خطا در ثبت تاریخ به‌روزرسانی: " . $stmt->error . "\n";
    }
    
    $stmt->close();
} else {
    echo "خطا در آماده‌سازی دستور SQL: " . $conn->error . "\n";
}

$conn->close();
?>