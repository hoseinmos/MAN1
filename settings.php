<?php
// settings.php - صفحه تنظیمات برای تغییر نام کاربری و مشاهده گزارش‌ها
include 'config.php';
include 'jdf.php'; // اضافه کردن فایل jdf.php برای تاریخ شمسی

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی تایم‌اوت نشست (30 دقیقه)
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id'];
$user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "کاربر مهمان";

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

// متغیرها برای پیام‌ها
$success_message = "";
$error_message = "";

// اگر فرم تغییر نام کاربری ارسال شده باشد
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_code'])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $current_code = sanitize_input($_POST["current_code"]);
        $new_code = sanitize_input($_POST["new_code"]);
        $confirm_code = sanitize_input($_POST["confirm_code"]);
        
        // بررسی کد فعلی
        if($current_code != $_SESSION['access_code']) {
            $error_message = "نام کاربری فعلی اشتباه است.";
        }
        // بررسی اینکه کد جدید و تکرار آن یکسان باشند
        elseif($new_code != $confirm_code) {
            $error_message = "نام کاربری جدید و تکرار آن باید یکسان باشند.";
        }
        // بررسی طول نام کاربری جدید
        elseif(strlen($new_code) < 4) {
            $error_message = "نام کاربری جدید باید حداقل 4 کاراکتر باشد.";
        }
        else {
            // به‌روزرسانی نام کاربری در پایگاه داده
            $sql = "UPDATE access_codes SET code = ? WHERE id = ?";
            if($conn && $stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $new_code, $user_id);
                
                if($stmt->execute()) {
                    // به‌روزرسانی نام کاربری در نشست
                    $_SESSION['access_code'] = $new_code;
                    
                    // ثبت تغییر نام کاربری در لاگ
                    $log_sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) VALUES (?, 'change_code', ?, NOW())";
                    if($log_stmt = $conn->prepare($log_sql)) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("is", $user_id, $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $success_message = "نام کاربری با موفقیت تغییر یافت.";
                } else {
                    $error_message = "خطا در به‌روزرسانی نام کاربری.";
                }
                
                $stmt->close();
            } else {
                $error_message = "خطا در اتصال به پایگاه داده.";
            }
        }
    }
}

// دریافت لاگ ورودهای اخیر
$login_logs = array();
if($conn) {
    $sql = "SELECT ip_address, success, attempt_time 
            FROM login_logs 
            WHERE access_code = ? 
            ORDER BY attempt_time DESC 
            LIMIT 10";
    
    if($stmt = $conn->prepare($sql)) {
        $access_code = $_SESSION['access_code'];
        $stmt->bind_param("s", $access_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            // تبدیل تاریخ به شمسی
            $row['attempt_time'] = convert_to_jalali($row['attempt_time']);
            $login_logs[] = $row;
        }
        
        $stmt->close();
    }
}

// تولید توکن CSRF
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات حساب کاربری</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            padding: 2rem 0;
            display: block;
        }
        
        .settings-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            font-weight: bold;
        }
        
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
        }
        
        .tab-content {
            background-color: #f8f9fa;
            border-radius: 0 0 10px 10px;
            padding: 2rem;
        }
        
        .log-success {
            color: #28a745;
        }
        
        .log-failed {
            color: #dc3545;
        }
        
        .page-header {
            margin-bottom: 2rem;
            border-bottom: 2px solid rgba(0,0,0,0.1);
            padding-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h2>تنظیمات حساب کاربری</h2>
                    <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
                </div>
                
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="settings-container">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">اطلاعات حساب</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">امنیت</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="false">گزارش‌ها</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="myTabContent">
                        <!-- اطلاعات حساب -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">اطلاعات کاربر</h5>
                                            <p><strong>نوع کاربر:</strong> <?php echo htmlspecialchars($user_description); ?></p>
                                            <p><strong>شناسه کاربری:</strong> <?php echo $user_id; ?></p>
                                            <p><strong>آخرین ورود:</strong> <?php echo isset($_SESSION['login_time']) ? convert_to_jalali($_SESSION['login_time']) : ''; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">اطلاعات سیستم</h5>
                                            <p><strong>آدرس IP:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                                            <p><strong>مرورگر:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></p>
                                            <p><strong>زمان فعلی سرور:</strong> <?php echo convert_to_jalali(date('Y-m-d H:i:s')); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- تنظیمات امنیتی -->
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                            <h5>تغییر نام کاربری</h5>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label for="current_code" class="form-label">نام کاربری فعلی:</label>
                                    <input type="password" class="form-control" id="current_code" name="current_code" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_code" class="form-label">نام کاربری جدید:</label>
                                    <input type="password" class="form-control" id="new_code" name="new_code" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_code" class="form-label">تکرار نام کاربری جدید:</label>
                                    <input type="password" class="form-control" id="confirm_code" name="confirm_code" required>
                                </div>
                                
                                <button type="submit" name="change_code" class="btn btn-primary">تغییر نام کاربری</button>
                            </form>
                            
                            <hr>
                            
                            <h5>نکات امنیتی</h5>
                            <ul class="list-group">
                                <li class="list-group-item">نام کاربری خود را به صورت منظم تغییر دهید.</li>
                                <li class="list-group-item">از نام کاربری طولانی و پیچیده استفاده کنید.</li>
                                <li class="list-group-item">در پایان کار، حتماً از سیستم خارج شوید.</li>
                                <li class="list-group-item">اطلاعات ورود خود را با دیگران به اشتراک نگذارید.</li>
                            </ul>
                        </div>
                        
                        <!-- گزارش‌ها -->
                        <div class="tab-pane fade" id="logs" role="tabpanel" aria-labelledby="logs-tab">
                            <h5>گزارش ورودهای اخیر</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>زمان</th>
                                            <th>آدرس IP</th>
                                            <th>وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($login_logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['attempt_time']; ?></td>
                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                <td class="<?php echo $log['success'] ? 'log-success' : 'log-failed'; ?>">
                                                    <?php echo $log['success'] ? 'موفق' : 'ناموفق'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if(empty($login_logs)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">هیچ گزارشی یافت نشد.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>