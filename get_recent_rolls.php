<?php
// get_recent_rolls.php - API برای دریافت رول‌های اخیر برای ویجت داشبورد
include 'config.php';
include 'jdf.php';

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

// بررسی اینکه آیا کاربر مدیر است
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مدیر سیستم"){
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطای دسترسی: فقط مدیر سیستم می‌تواند به این اطلاعات دسترسی داشته باشد.'
    ]);
    exit;
}

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در اتصال به پایگاه داده.'
    ]);
    exit;
}

try {
    // دریافت 5 رول اخیر
    $sql = "SELECT pr.id, pr.quantity, pr.delivery_date, pr.created_at, 
                   t.terminal_number as terminal_code, t.store_name, u.description as user_name
            FROM paper_rolls pr
            JOIN terminals t ON pr.terminal_id = t.id
            JOIN access_codes u ON pr.user_id = u.id
            ORDER BY pr.created_at DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $rolls = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // تبدیل تاریخ به شمسی
            $row['delivery_date'] = convert_to_jalali($row['delivery_date']);
            $row['created_at'] = convert_to_jalali($row['created_at']);
            $rolls[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'rolls' => $rolls
        ]);
    } else {
        throw new Exception("خطا در اجرای کوئری: " . $conn->error);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()
    ]);
}
?>