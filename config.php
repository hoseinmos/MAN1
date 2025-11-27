<?php
// config.php - فایل پیکربندی بهبود یافته
// فقط در محیط توسعه خطاها نمایش داده شود
$dev_mode = true;
if ($dev_mode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// شروع نشست با تنظیمات امنیتی بالاتر
if (session_status() == PHP_SESSION_NONE) {
    // تنظیمات امنیتی نشست
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    
    // در محیط تولید از HTTPS استفاده کنید
    if (!$dev_mode && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// تابع تولید توکن CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// تنظیمات پایگاه داده
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // رمز عبور پایگاه داده را وارد کنید
define('DB_NAME', 'secure_login');

// اتصال به پایگاه داده
$conn = null;

try {
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // تنظیم charset برای پشتیبانی از زبان فارسی
    mysqli_set_charset($conn, "utf8mb4");
    
    // بررسی وضعیت اتصال
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }
    
} catch (Exception $e) {
    // در صورت خطا در اتصال، آن را ذخیره می‌کنیم و در فایل لاگ ثبت می‌کنیم
    logError("خطا در اتصال به پایگاه داده: " . $e->getMessage());
    
    // فقط برای کاربر پیام ساده نمایش داده می‌شود
    if (!isset($_SESSION['db_error'])) {
        $_SESSION['db_error'] = "مشکلی در سیستم رخ داده است. لطفاً بعداً تلاش کنید.";
    }
}

// تابع ثبت خطا
function logError($message) {
    $file = 'error_log.txt';
    $current = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($file, $current, FILE_APPEND);
}

// تابع امن برای پاکسازی ورودی‌ها
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    return $data;
}

// تابع بررسی کد دسترسی در پایگاه داده
function validate_access_code($code) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    $sql = "SELECT id, code, description FROM access_codes WHERE code = ? AND active = 1 LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            return $row;
        }
        
        $stmt->close();
    }
    
    return false;
}

// تابع ثبت لاگ ورود
function log_login_attempt($code, $ip, $success) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    $sql = "INSERT INTO login_logs (access_code, ip_address, success, attempt_time) VALUES (?, ?, ?, NOW())";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $code, $ip, $success);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    
    return false;
}

// تابع بررسی تعداد تلاش‌های ناموفق
function check_failed_attempts($ip) {
    global $conn;
    
    if (!$conn) {
        return 0;
    }
    
    // بررسی تعداد تلاش‌های ناموفق در 15 دقیقه اخیر
    $sql = "SELECT COUNT(*) as attempts FROM login_logs 
            WHERE ip_address = ? AND success = 0 AND 
            attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['attempts'];
    }
    
    return 0;
}

// تابع به‌روزرسانی فعالیت نشست
function update_session_activity() {
    global $conn;
    $session_id = session_id();
    
    if (!$conn) {
        $_SESSION['last_activity'] = time();
        return;
    }
    
    // ابتدا بررسی می‌کنیم آیا این نشست در پایگاه داده وجود دارد
    $check_sql = "SELECT id FROM sessions WHERE session_id = ?";
    if ($check_stmt = $conn->prepare($check_sql)) {
        $check_stmt->bind_param("s", $session_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($check_result->num_rows == 0) {
            // اگر نشست وجود ندارد، آن را ایجاد می‌کنیم
            $insert_sql = "INSERT INTO sessions (session_id, user_id, ip_address, last_activity) 
                          VALUES (?, ?, ?, NOW())";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                $ip = $_SERVER['REMOTE_ADDR'];
                $insert_stmt->bind_param("sis", $session_id, $user_id, $ip);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        } else {
            // اگر نشست وجود دارد، آن را به‌روزرسانی می‌کنیم
            $update_sql = "UPDATE sessions SET last_activity = NOW() WHERE session_id = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("s", $session_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    }
    
    $_SESSION['last_activity'] = time();
}

// تابع بررسی تایم‌اوت نشست
function check_session_timeout($timeout = 1800) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // اگر زمان مشخص شده گذشته باشد، نشست را پاک می‌کنیم
        session_unset();
        session_destroy();
        
        // هدایت کاربر به صفحه ورود
        header("location: index.php?timeout=1");
        exit;
    }
}
?>