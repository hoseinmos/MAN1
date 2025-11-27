<?php
// get_user_stats.php - دریافت آمار کاربر
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

// دریافت اطلاعات کاربر فعلی
$user_id = $_SESSION['user_id'];
$user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";

// تبدیل تاریخ امروز به فرمت SQL
$today = date('Y-m-d');

// آرایه برای ذخیره آمار کاربر
$stats = [
    'totalRolls' => 0,             // تعداد کل رول‌های ثبت شده توسط کاربر
    'todayPMs' => 0,               // تعداد پی‌ام‌های امروز (بر اساس رول‌های ثبت شده)
    'totalIssues' => 0,            // تعداد کل مشکلات ثبت شده توسط کاربر
    'openIssues' => 0              // تعداد مشکلات باز
];

try {
    // بررسی اتصال به پایگاه داده
    if(!$conn) {
        throw new Exception("خطا در اتصال به پایگاه داده");
    }
    
    // 1. دریافت تعداد کل رول‌های ثبت شده توسط کاربر
    $total_rolls_sql = "SELECT COUNT(*) as total FROM paper_rolls WHERE user_id = ?";
    if($stmt = $conn->prepare($total_rolls_sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()) {
            $stats['totalRolls'] = $row['total'];
        }
        $stmt->close();
    }
    
    // 2. دریافت تعداد پی‌ام‌های امروز (بر اساس رول‌های ثبت شده)
    $today_pms_sql = "SELECT COUNT(*) as total FROM paper_rolls 
                     WHERE user_id = ? AND DATE(delivery_date) = ?";
    
    if($stmt = $conn->prepare($today_pms_sql)) {
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()) {
            $stats['todayPMs'] = $row['total'];
        }
        $stmt->close();
    }
    
    // 3. دریافت تعداد کل مشکلات ثبت شده توسط کاربر
    $total_issues_sql = "SELECT COUNT(*) as total FROM terminal_issues WHERE created_by = ?";
    if($stmt = $conn->prepare($total_issues_sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()) {
            $stats['totalIssues'] = $row['total'];
        }
        $stmt->close();
    }
    
    // 4. دریافت تعداد مشکلات باز برای پایانه‌های مربوط به کاربر
    // اگر کاربر مدیر سیستم است، همه مشکلات باز را می‌بیند
    // در غیر این صورت، فقط مشکلات باز پایانه‌های خودش را می‌بیند
    if($user_description === "مدیر سیستم") {
        $open_issues_sql = "SELECT COUNT(*) as total FROM terminal_issues 
                           WHERE status = 'open'";
        
        if($stmt = $conn->prepare($open_issues_sql)) {
            $stmt->execute();
            $result = $stmt->get_result();
            if($row = $result->fetch_assoc()) {
                $stats['openIssues'] = $row['total'];
            }
            $stmt->close();
        }
    } else {
        // برای کاربران عادی، فقط مشکلات باز پایانه‌های مربوط به خودشان را می‌بینند
        $open_issues_sql = "SELECT COUNT(*) as total FROM terminal_issues ti
                           JOIN terminals t ON ti.terminal_id = t.id
                           WHERE ti.status = 'open' AND t.support_person = ?";
        
        if($stmt = $conn->prepare($open_issues_sql)) {
            $stmt->bind_param("s", $user_description);
            $stmt->execute();
            $result = $stmt->get_result();
            if($row = $result->fetch_assoc()) {
                $stats['openIssues'] = $row['total'];
            }
            $stmt->close();
        }
    }
    
    // پاسخ موفقیت‌آمیز
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch(Exception $e) {
    // پاسخ خطا
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت آمار: ' . $e->getMessage()
    ]);
}
?>