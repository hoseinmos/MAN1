<?php
// get_recent_issues.php - API برای دریافت مشکلات اخیر برای ویجت داشبورد
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
    // دریافت 5 مشکل اخیر
    $sql = "SELECT ti.id, ti.description, ti.status, ti.priority, ti.created_at, t.terminal_number as terminal_code
            FROM terminal_issues ti
            JOIN terminals t ON ti.terminal_id = t.id
            ORDER BY ti.created_at DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $issues = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // تبدیل تاریخ به شمسی
            $row['created_at'] = convert_to_jalali($row['created_at']);
            $issues[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'issues' => $issues
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