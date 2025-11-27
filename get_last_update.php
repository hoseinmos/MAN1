<?php
// get_last_update.php - API برای دریافت آخرین به‌روزرسانی دیتابیس
include 'config.php';
include 'jdf.php'; // برای تبدیل تاریخ به شمسی

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطای دسترسی: لطفاً ابتدا وارد شوید.'
    ]);
    exit;
}

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// تابع کمکی برای تبدیل تاریخ میلادی به شمسی
function convert_to_jalali($date_time) {
    if(empty($date_time)) return '';
    
    // تبدیل تاریخ و زمان از فرمت YYYY-MM-DD HH:MM:SS
    $datetime_parts = explode(' ', $date_time);
    $date_parts = explode('-', $datetime_parts[0]);
    
    // تبدیل به شمسی
    $jalali_date = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2], '/');
    
    // فرمت نهایی: تاریخ شمسی + زمان
    return $jalali_date . ' ' . (isset($datetime_parts[1]) ? $datetime_parts[1] : '');
}

// بررسی اتصال به پایگاه داده
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در اتصال به پایگاه داده.'
    ]);
    exit;
}

try {
    // دریافت آخرین به‌روزرسانی دیتابیس
    $sql = "SELECT * FROM system_updates 
            WHERE update_type = 'database' 
            ORDER BY update_time DESC 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $update_info = $result->fetch_assoc();
        
        // تبدیل تاریخ به شمسی
        $update_info['update_time_jalali'] = convert_to_jalali($update_info['update_time']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'update_info' => $update_info
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'اطلاعات به‌روزرسانی یافت نشد.'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()
    ]);
}
?>