<?php
// admin_update_database.php - صفحه مدیریتی برای ثبت به‌روزرسانی دیتابیس
include 'config.php';
include 'jdf.php';

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی اینکه آیا کاربر مدیر است
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مدیر سیستم"){
    header("location: dashboard.php");
    exit;
}

// بررسی تایم‌اوت نشست (30 دقیقه)
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

$message = '';
$success = false;

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

// دریافت تاریخ آخرین به‌روزرسانی
$last_update = '';
$last_description = '';

if($conn) {
    $sql = "SELECT * FROM system_updates 
            WHERE update_type = 'database' 
            ORDER BY update_time DESC 
            LIMIT 1";
    
    $result = $conn->query($sql);
    if($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_update = convert_to_jalali($row['update_time']);
        $last_description = $row['description'];
    }
}

// اگر فرم ثبت به‌روزرسانی ارسال شده است
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_update'])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : "به‌روزرسانی دستی";
        
        $sql = "INSERT INTO system_updates (update_type, update_time, description, updated_by) 
                VALUES ('database', NOW(), ?, ?)";
        
        if($stmt = $conn->prepare($sql)) {
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("si", $description, $user_id);
            
            if($stmt->execute()) {
                $success = true;
                $message = "زمان به‌روزرسانی دیتابیس با موفقیت ثبت شد.";
                
                // ثبت در لاگ
                $log_sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) 
                            VALUES (?, 'database_update', ?, NOW())";
                
                if($log_stmt = $conn->prepare($log_sql)) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("is", $user_id, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                // به‌روزرسانی متغیرهای نمایش
                $last_update = convert_to_jalali(date('Y-m-d H:i:s'));
                $last_description = $description;
                
            } else {
                $message = "خطا در ثبت زمان به‌روزرسانی: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $message = "خطا در آماده‌سازی دستور SQL: " . $conn->error;
        }
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
    <title>ثبت به‌روزرسانی دیتابیس</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dashboard-page">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-database me-2"></i> ثبت به‌روزرسانی دیتابیس</h3>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($message)): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($last_update)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>آخرین به‌روزرسانی:</strong> <?php echo $last_update; ?>
                                <?php if(!empty($last_description)): ?>
                                    <div class="mt-2 small">
                                        <strong>توضیحات:</strong> <?php echo htmlspecialchars($last_description); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">توضیحات به‌روزرسانی:</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="توضیحات مربوط به این به‌روزرسانی را وارد کنید..."></textarea>
                                <div class="form-text">
                                    <i class="fas fa-lightbulb me-1 text-warning"></i>
                                    می‌توانید توضیح دهید که چه تغییراتی در این به‌روزرسانی اعمال شده است.
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                توجه: این عملیات فقط زمان به‌روزرسانی را در سیستم ثبت می‌کند و تغییری در دیتابیس ایجاد نمی‌کند.
                                <br>
                                لطفاً پس از انجام به‌روزرسانی واقعی دیتابیس، این فرم را تکمیل کنید.
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-1"></i> بازگشت به داشبورد
                                </a>
                                <button type="submit" name="register_update" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> ثبت به‌روزرسانی
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول تاریخچه به‌روزرسانی‌ها -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h4><i class="fas fa-history me-2"></i> تاریخچه به‌روزرسانی‌ها</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>توضیحات</th>
                                        <th>توسط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if($conn) {
                                        $history_sql = "SELECT su.*, u.description as user_name
                                                        FROM system_updates su
                                                        LEFT JOIN access_codes u ON su.updated_by = u.id
                                                        WHERE su.update_type = 'database'
                                                        ORDER BY su.update_time DESC
                                                        LIMIT 10";
                                        
                                        $history_result = $conn->query($history_sql);
                                        if($history_result && $history_result->num_rows > 0) {
                                            while($row = $history_result->fetch_assoc()) {
                                                echo '<tr>';
                                                echo '<td>' . convert_to_jalali($row['update_time']) . '</td>';
                                                echo '<td>' . htmlspecialchars($row['description']) . '</td>';
                                                echo '<td>' . htmlspecialchars($row['user_name'] ?? 'سیستم') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="3" class="text-center">تاریخچه‌ای یافت نشد.</td></tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center">خطا در اتصال به دیتابیس.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
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