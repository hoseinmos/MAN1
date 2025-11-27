<?php
// verify.php - اصلاح شده برای بررسی نام کاربری
include 'config.php';

// بررسی اگر فرم ارسال شده است
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "خطای امنیتی: دسترسی غیرمجاز";
        header("location: index.php");
        exit;
    }
    
    $access_code = sanitize_input($_POST["access_code"]);
    $username = sanitize_input($_POST["username"]);
    $captcha_input = sanitize_input($_POST["captcha_input"]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // بررسی تعداد تلاش‌های ناموفق
    $failed_attempts = check_failed_attempts($ip_address);
    if ($failed_attempts >= 5) {
        $_SESSION['error'] = "به دلیل تلاش‌های ناموفق زیاد، دسترسی شما موقتاً مسدود شده است. لطفاً 15 دقیقه دیگر تلاش کنید.";
        header("location: index.php");
        exit;
    }
    
    // بررسی کپچا
    if(!isset($_SESSION['captcha']) || $captcha_input != $_SESSION['captcha']) {
        // ثبت تلاش ناموفق
        log_login_attempt($access_code, $ip_address, 0);
        
        $_SESSION['error'] = "کد امنیتی نادرست است.";
        header("location: index.php");
        exit;
    }
    
    // بررسی کد دسترسی و نام کاربری با استفاده از پایگاه داده
    $sql = "SELECT id, code, description FROM access_codes WHERE code = ? AND username = ? AND active = 1 LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $access_code, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user_info = $result->fetch_assoc();
            
            // ثبت ورود موفق
            log_login_attempt($access_code, $ip_address, 1);
            
            // اضافه کردن ثبت اطلاعات دستگاه
            include 'device_detector.php';
            $device_info = get_device_info();
            
            // بررسی آیا این دستگاه قبلاً ثبت شده است
            $check_device_sql = "SELECT id, login_count FROM user_devices 
                                WHERE user_id = ? AND device_name = ? AND browser = ? AND os = ?";
            
            if($check_device_stmt = $conn->prepare($check_device_sql)) {
                $check_device_stmt->bind_param("isss", 
                    $user_info['id'], 
                    $device_info['device_name'], 
                    $device_info['browser'], 
                    $device_info['os']
                );
                
                $check_device_stmt->execute();
                $check_result = $check_device_stmt->get_result();
                
                if($check_result->num_rows > 0) {
                    // دستگاه موجود است، به‌روزرسانی
                    $device_row = $check_result->fetch_assoc();
                    $device_id = $device_row['id'];
                    $login_count = $device_row['login_count'] + 1;
                    
                    $update_sql = "UPDATE user_devices 
                                  SET login_count = ?, last_login = NOW(), ip_address = ? 
                                  WHERE id = ?";
                    
                    if($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("isi", 
                            $login_count, 
                            $device_info['ip_address'], 
                            $device_id
                        );
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                } else {
                    // دستگاه جدید است، ثبت کنیم
                    $insert_sql = "INSERT INTO user_devices 
                                  (user_id, device_type, device_name, browser, os, ip_address, first_login, last_login) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    
                    if($insert_stmt = $conn->prepare($insert_sql)) {
                        $insert_stmt->bind_param("isssss", 
                            $user_info['id'], 
                            $device_info['device_type'], 
                            $device_info['device_name'], 
                            $device_info['browser'], 
                            $device_info['os'], 
                            $device_info['ip_address']
                        );
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                }
                
                $check_device_stmt->close();
            }
            
            // نشانه‌گذاری کاربر به عنوان احراز هویت شده
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user_info['id'];
            $_SESSION['access_code'] = $access_code;
            $_SESSION['username'] = $username;
            $_SESSION['user_description'] = $user_info['description'];
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            // ذخیره نشست در پایگاه داده
            update_session_activity();
            
            // تولید توکن CSRF جدید
            generate_csrf_token();
        
            // هدایت به داشبورد
            header("location: dashboard.php");
            exit;
        } else {
            // ثبت تلاش ناموفق
            log_login_attempt($access_code, $ip_address, 0);
            
            $_SESSION['error'] = "کد دسترسی یا نام کاربری نادرست است.";
            header("location: index.php");
            exit;
        }
        
        $stmt->close();
    } else {
        // ثبت تلاش ناموفق
        log_login_attempt($access_code, $ip_address, 0);
        
        $_SESSION['error'] = "خطا در بررسی اعتبار: " . $conn->error;
        header("location: index.php");
        exit;
    }
} else {
    // اگر مستقیماً به این صفحه دسترسی پیدا کرده باشند
    header("location: index.php");
    exit;
}
?>