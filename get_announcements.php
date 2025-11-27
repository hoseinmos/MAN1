<?php
// get_announcements.php - API برای دریافت اطلاعیه‌های فعال

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

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در اتصال به پایگاه داده.'
    ]);
    exit;
}

try {
    // دریافت اطلاعیه‌های فعال که منقضی نشده‌اند
    $sql = "SELECT a.id, a.title, a.content, a.importance, a.created_at, u.description as author_name
            FROM announcements a
            LEFT JOIN access_codes u ON a.created_by = u.id
            WHERE a.active = 1 
            AND (a.expire_date IS NULL OR a.expire_date >= CURDATE())
            ORDER BY 
                CASE a.importance
                    WHEN 'critical' THEN 1
                    WHEN 'important' THEN 2
                    ELSE 3
                END,
                a.created_at DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $announcements = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // تبدیل تاریخ به شمسی
            $row['created_at'] = convert_to_jalali($row['created_at']);
            $announcements[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'announcements' => $announcements
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