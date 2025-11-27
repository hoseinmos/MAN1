<?php
// get_user_activities.php - API برای دریافت فعالیت‌های اخیر کاربر
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
    $user_id = $_SESSION['user_id'];
    
    // دریافت 10 فعالیت اخیر کاربر
    $sql = "SELECT ul.*, 
                  CASE 
                      WHEN ul.action = 'report_issue' THEN (
                          SELECT CONCAT('پایانه: ', t.terminal_number, ' - ', SUBSTRING(ti.description, 1, 50))
                          FROM terminal_issues ti 
                          JOIN terminals t ON ti.terminal_id = t.id
                          WHERE ti.id = ul.action_id
                      )
                      WHEN ul.action = 'update_issue' THEN (
                          SELECT CONCAT('پایانه: ', t.terminal_number, ' - ویرایش وضعیت به: ', ti.status)
                          FROM terminal_issues ti 
                          JOIN terminals t ON ti.terminal_id = t.id
                          WHERE ti.id = ul.action_id
                      )
                      WHEN ul.action = 'submit_roll' THEN (
                          SELECT CONCAT('پایانه: ', t.terminal_number, ' - تعداد: ', pr.quantity, ' رول')
                          FROM paper_rolls pr 
                          JOIN terminals t ON pr.terminal_id = t.id
                          WHERE pr.id = ul.action_id
                      )
                      ELSE NULL
                  END as details
            FROM user_logs ul
            WHERE ul.user_id = ?
            ORDER BY ul.log_time DESC
            LIMIT 2";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $activities = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // تبدیل تاریخ به شمسی
            $row['log_time'] = convert_to_jalali($row['log_time']);
            $activities[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'activities' => $activities
        ]);
    } else {
        throw new Exception("خطا در اجرای کوئری: " . $conn->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()
    ]);
}
?>