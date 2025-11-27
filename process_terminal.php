<?php
// process_terminal.php - پردازش عملیات مربوط به پایانه‌ها (اصلاح شده)
include 'config.php';
include 'jdf.php'; // اضافه کردن فایل jdf.php برای تبدیل تاریخ به شمسی

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

// بررسی عملیات درخواستی
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'search':
        // جستجو در پایانه‌ها
        searchTerminals();
        break;
        
    case 'getDetails':
        // دریافت جزئیات پایانه
        getTerminalDetails();
        break;
        
    case 'reportIssue':
        // ثبت مشکل برای پایانه
        reportTerminalIssue();
        break;
        
    case 'getStats':
        // دریافت آمار کلی
        getStatistics();
        break;
        
    default:
        // عملیات نامعتبر
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'عملیات نامعتبر'
        ]);
        break;
}

/**
 * جستجو در پایانه‌ها
 */
function searchTerminals() {
    global $conn;
    
    // بررسی روش درخواست
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        outputJSON(false, 'روش درخواست نامعتبر است.');
        return;
    }
    
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        outputJSON(false, 'خطای امنیتی: دسترسی غیرمجاز');
        return;
    }
    
    // دریافت پارامترهای جستجو
    $terminal_code = isset($_POST['terminal_code']) ? sanitize_input($_POST['terminal_code']) : '';
    $merchant_name = isset($_POST['merchant_name']) ? sanitize_input($_POST['merchant_name']) : '';
    $terminal_model = isset($_POST['terminal_model']) ? sanitize_input($_POST['terminal_model']) : '';
    
    // بررسی اعتبار پارامترهای جستجو - حداقل یکی از فیلدهای اصلی باید پر شده باشد
    if (empty($terminal_code) && empty($merchant_name)) {
        outputJSON(false, 'لطفاً حداقل یکی از فیلدهای شماره پایانه یا نام فروشگاه را وارد کنید.');
        return;
    }
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) {
        outputJSON(false, 'خطا در اتصال به پایگاه داده.');
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user_id = $_SESSION['user_id'];
    $user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";
    
    // ساخت پرس‌وجو
    $sql = "SELECT * FROM terminals WHERE 1=1";
    $params = [];
    $types = "";
    
    // جستجو بر اساس کد پایانه (terminal_number)
    if (!empty($terminal_code)) {
        $sql .= " AND terminal_number LIKE ?";
        $params[] = "%$terminal_code%";
        $types .= "s";
    }
    
    // جستجو بر اساس نام فروشگاه (store_name)
    if (!empty($merchant_name)) {
        $sql .= " AND store_name LIKE ?";
        $params[] = "%$merchant_name%";
        $types .= "s";
    }
    
    // جستجو بر اساس مدل دستگاه (device_model)
    if (!empty($terminal_model) && $terminal_model !== 'همه') {
        $sql .= " AND device_model = ?";
        $params[] = $terminal_model;
        $types .= "s";
    }
    
    // محدود کردن نتایج براساس نوع کاربر
    // فقط مدیر سیستم همه پایانه‌ها را می‌بیند، سایر کاربران فقط پایانه‌های مربوط به خود را می‌بینند
    if ($user_description !== "مدیر سیستم") {
        $sql .= " AND support_person = ?";
        $params[] = $user_description;
        $types .= "s";
    }
    
    $sql .= " ORDER BY id DESC LIMIT 50";
    
    // اجرای پرس‌وجو
    $results = [];
    
    try {
        if ($stmt = $conn->prepare($sql)) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // تبدیل تاریخ‌ها به شمسی اگر وجود داشته باشند
                if (isset($row['installation_date'])) {
                    $row['installation_date'] = convert_to_jalali($row['installation_date']);
                }
                
                $results[] = $row;
            }
            
            $stmt->close();
            
            // برگرداندن نتایج
            outputJSON(true, '', ['results' => $results]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        error_log("SQL Error in searchTerminals: " . $e->getMessage());
        outputJSON(false, 'خطا در اجرای جستجو: ' . $e->getMessage());
    }
}

/**
 * دریافت جزئیات پایانه
 */
function getTerminalDetails() {
    global $conn;
    
    // بررسی پارامترهای ورودی
    $terminal_id = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;
    
    if ($terminal_id <= 0) {
        outputJSON(false, 'شناسه پایانه نامعتبر است.');
        return;
    }
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) {
        outputJSON(false, 'خطا در اتصال به پایگاه داده.');
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user_id = $_SESSION['user_id'];
    $user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";
    
    try {
        // دریافت اطلاعات پایانه با اعمال محدودیت دسترسی برای کاربران غیر مدیر
        $sql = "SELECT *, 
                pm_description, 
                missing_documents, 
                returned_documents 
                FROM terminals WHERE id = ?";
                
        // اگر کاربر مدیر سیستم نیست، فقط پایانه‌های مربوط به خودش را ببیند
        if ($user_description !== "مدیر سیستم") {
            $sql .= " AND support_person = ?";
        }
        
        $sql .= " LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {
            if ($user_description === "مدیر سیستم") {
                $stmt->bind_param("i", $terminal_id);
            } else {
                $stmt->bind_param("is", $terminal_id, $user_description);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                outputJSON(false, 'پایانه مورد نظر یافت نشد یا شما به آن دسترسی ندارید.');
                return;
            }
            
            $terminal = $result->fetch_assoc();
            $stmt->close();
            
            // تبدیل تاریخ‌ها به شمسی
            if (isset($terminal['installation_date'])) {
                $terminal['installation_date'] = convert_to_jalali($terminal['installation_date']);
            }
            
            // دریافت تاریخچه مشکلات
            $sql = "SELECT ti.id, ti.description, ti.priority, ti.status, 
                          ti.created_at, u.description as reporter_name
                    FROM terminal_issues ti 
                    LEFT JOIN access_codes u ON ti.created_by = u.id
                    WHERE ti.terminal_id = ? 
                    ORDER BY ti.created_at DESC";
            
            $issues = [];
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $terminal_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    // تبدیل تاریخ‌ها به شمسی
                    $row['created_at'] = convert_to_jalali($row['created_at']);
                    
                    if (isset($row['resolved_at']) && !empty($row['resolved_at'])) {
                        $row['resolved_at'] = convert_to_jalali($row['resolved_at']);
                    }
                    
                    $issues[] = $row;
                }
                
                $stmt->close();
            }
            
            // افزودن تاریخچه مشکلات به اطلاعات پایانه
            $terminal['issues'] = $issues;
            
            // برگرداندن نتایج
            outputJSON(true, '', ['terminal' => $terminal]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        error_log("SQL Error in getTerminalDetails: " . $e->getMessage());
        outputJSON(false, 'خطا در دریافت اطلاعات پایانه: ' . $e->getMessage());
    }
}

/**
 * ثبت مشکل برای پایانه
 */
function reportTerminalIssue() {
    global $conn;
    
    // بررسی روش درخواست
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        outputJSON(false, 'روش درخواست نامعتبر است.');
        return;
    }
    
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        outputJSON(false, 'خطای امنیتی: دسترسی غیرمجاز');
        return;
    }
    
    // دریافت پارامترهای ورودی
    $terminal_id = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0;
    $description = isset($_POST['issue_description']) ? sanitize_input($_POST['issue_description']) : '';
    $priority = isset($_POST['issue_priority']) ? sanitize_input($_POST['issue_priority']) : 'medium';
    
    // بررسی اعتبار داده‌ها
    if ($terminal_id <= 0) {
        outputJSON(false, 'شناسه پایانه نامعتبر است.');
        return;
    }
    
    if (empty($description)) {
        outputJSON(false, 'لطفاً شرح مشکل را وارد کنید.');
        return;
    }
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) {
        outputJSON(false, 'خطا در اتصال به پایگاه داده.');
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user_id = $_SESSION['user_id'];
    $user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";
    
    try {
        // بررسی وجود پایانه و دسترسی کاربر به آن
        $check_sql = "SELECT id FROM terminals WHERE id = ?";
        
        // اگر کاربر مدیر سیستم نیست، فقط پایانه‌های مربوط به خودش را بررسی کند
        if ($user_description !== "مدیر سیستم") {
            $check_sql .= " AND support_person = ?";
        }
        
        $check_sql .= " LIMIT 1";
        
        if ($check_stmt = $conn->prepare($check_sql)) {
            if ($user_description === "مدیر سیستم") {
                $check_stmt->bind_param("i", $terminal_id);
            } else {
                $check_stmt->bind_param("is", $terminal_id, $user_description);
            }
            
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $check_stmt->close();
                outputJSON(false, 'پایانه مورد نظر یافت نشد یا شما به آن دسترسی ندارید.');
                return;
            }
            
            $check_stmt->close();
        } else {
            throw new Exception($conn->error);
        }
        
        // ثبت مشکل
        $sql = "INSERT INTO terminal_issues (terminal_id, description, priority, status, created_by, created_at) 
                VALUES (?, ?, ?, 'open', ?, NOW())";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issi", $terminal_id, $description, $priority, $user_id);
            
            if ($stmt->execute()) {
                $issue_id = $stmt->insert_id;
                $stmt->close();
                
                // دریافت اطلاعات کاربر ثبت کننده
                $user_info = [];
                $user_sql = "SELECT description FROM access_codes WHERE id = ? LIMIT 1";
                if ($user_stmt = $conn->prepare($user_sql)) {
                    $user_stmt->bind_param("i", $user_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    if ($user_row = $user_result->fetch_assoc()) {
                        $user_info = $user_row;
                    }
                    $user_stmt->close();
                }
                
                // ثبت در لاگ
                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                            VALUES (?, 'report_issue', ?, ?, NOW())";
                
                if ($log_stmt = $conn->prepare($log_sql)) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iis", $user_id, $issue_id, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                outputJSON(true, 'مشکل با موفقیت ثبت شد.', [
                    'issue_id' => $issue_id,
                    'user_name' => $user_info['description'] ?? 'نامشخص'
                ]);
            } else {
                throw new Exception($stmt->error);
            }
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        error_log("SQL Error in reportTerminalIssue: " . $e->getMessage());
        outputJSON(false, 'خطا در ثبت مشکل: ' . $e->getMessage());
    }
}

/**
 * دریافت آمار کلی
 */
function getStatistics() {
    global $conn;
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) {
        outputJSON(false, 'خطا در اتصال به پایگاه داده.');
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user_id = $_SESSION['user_id'];
    $user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";
    
    $stats = [
        'totalTerminals' => 0,
        'totalMerchants' => 0,
        'totalIssues' => 0
    ];
    
    try {
        // برای مدیر سیستم، آمار کل را نمایش می‌دهیم
        if ($user_description === "مدیر سیستم") {
            // تعداد پایانه‌ها
            $sql = "SELECT COUNT(*) as total FROM terminals";
            $result = $conn->query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['totalTerminals'] = $row['total'];
            }
            
            // تعداد پذیرندگان منحصر به فرد
            $sql = "SELECT COUNT(DISTINCT store_name) as total FROM terminals";
            $result = $conn->query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['totalMerchants'] = $row['total'];
            }
            
            // تعداد مشکلات
            $sql = "SELECT COUNT(*) as total FROM terminal_issues";
            $result = $conn->query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['totalIssues'] = $row['total'];
            }
        } else {
            // برای کاربران عادی، فقط آمار پایانه‌های مربوط به خودشان را نمایش می‌دهیم
            
            // تعداد پایانه‌ها
            $sql = "SELECT COUNT(*) as total FROM terminals WHERE support_person = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $user_description);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stats['totalTerminals'] = $row['total'];
                }
                $stmt->close();
            }
            
            // تعداد پذیرندگان منحصر به فرد
            $sql = "SELECT COUNT(DISTINCT store_name) as total FROM terminals WHERE support_person = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $user_description);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stats['totalMerchants'] = $row['total'];
                }
                $stmt->close();
            }
            
            // تعداد مشکلات مربوط به پایانه‌های این کاربر
            $sql = "SELECT COUNT(*) as total FROM terminal_issues ti 
                    JOIN terminals t ON ti.terminal_id = t.id
                    WHERE t.support_person = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $user_description);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stats['totalIssues'] = $row['total'];
                }
                $stmt->close();
            }
        }
        
        // برگرداندن نتایج
        outputJSON(true, '', $stats);
    } catch (Exception $e) {
        error_log("SQL Error in getStatistics: " . $e->getMessage());
        outputJSON(false, 'خطا در دریافت آمار: ' . $e->getMessage());
    }
}

/**
 * ارسال پاسخ JSON
 */
function outputJSON($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit;
}
?>