<?php
// در بالای فایل dashboard.php بعد از include های موجود
include 'config.php';
include 'jdf.php';
include 'server_time.php'; // فایل جدیدی که ایجاد کردیم

// اضافه کردن این خط برای دریافت زمان سرور
$server_time = getMySQLJalaliDateTime();

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی تایم‌اوت نشست (30 دقیقه)
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// دریافت اطلاعات کاربر از نشست
$user_id = $_SESSION['user_id'];
$user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "کاربر مهمان";
$login_time = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : date('Y-m-d H:i:s');

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

// دریافت آخرین تاریخ به‌روزرسانی دیتابیس
$last_update_date = '';
$last_update_query = "SELECT update_time FROM system_updates 
                     WHERE update_type = 'database' 
                     ORDER BY update_time DESC 
                     LIMIT 1";

if ($conn) {
    $result = $conn->query($last_update_query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_update_date = convert_to_jalali($row['update_time']);
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>داشبورد مدیریت</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="mobile-styles.css">
    <style>
        .update-notification {
            background-color: #f8f9fa;
            border-right: 4px solid #17a2b8;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .update-notification i {
            color: #17a2b8;
            margin-left: 0.75rem;
            font-size: 1.25rem;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- حالت بارگذاری -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-container">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
            <p>لطفاً منتظر بمانید...</p>
        </div>
    </div>

    <div class="container dashboard-container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="welcome-card welcome-animation">
                    <div class="card-header text-center">
                        <h2>به پنل مدیریت خوش آمدید</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-8 col-sm-12">
                                <div class="time-display" id="time-display" data-server-time="<?php echo $server_time; ?>">
                                    <?php echo $server_time; ?>
                                </div>
                                
                                <div class="user-info">
                                    <h4><i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user_description); ?></h4>
                                    <p class="text-muted">آخرین ورود: <span id="login-time"><?php echo convert_to_jalali($login_time); ?></span></p>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-12 text-md-end text-center mt-md-0 mt-3">
                                <div class="dashboard-actions">
                                    <a href="settings.php" class="btn btn-info me-2 mb-2 mb-md-0">
                                        <i class="fas fa-cog me-1"></i> تنظیمات
                                    </a>
                                    <a href="logout.php" class="btn logout-btn">
                                        <i class="fas fa-sign-out-alt me-1"></i> خروج
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- کامپوننت اطلاعیه‌ها -->
                        <?php include 'announcements_component.php'; ?>
                        
                        <?php if(!empty($last_update_date)): ?>
                        <!-- نمایش تاریخ آخرین به‌روزرسانی دیتابیس -->
                        <div class="update-notification">
                            <i class="fas fa-sync-alt"></i>
                            <div>
                                <strong>آخرین به‌روزرسانی دیتابیس:</strong> <?php echo $last_update_date; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- بخش جستجو -->
                        <div class="search-section mb-4">
                            <h3 class="section-title mb-3"><i class="fas fa-search me-2"></i> جستجوی اطلاعات پایانه</h3>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <form id="searchForm" class="mb-3 needs-validation" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4 col-sm-6 col-12">
                                                <label for="terminal_code" class="form-label">شماره پایانه بانک</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                                    <input type="text" class="form-control" id="terminal_code" name="terminal_code" placeholder="شماره پایانه را وارد کنید...">
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-sm-6 col-12">
                                                <label for="merchant_name" class="form-label">نام فروشگاه</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                                                    <input type="text" class="form-control" id="merchant_name" name="merchant_name" placeholder="نام فروشگاه را وارد کنید...">
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-sm-12 col-12">
                                                <label for="terminal_model" class="form-label">مدل دستگاه</label>
                                                <select class="form-select" id="terminal_model" name="terminal_model">
                                                    <option value="">همه</option>
                                                    <option value="PAX-S80">PAX-S80</option>
                                                    <option value="VERIFONE-VX675">VERIFONE-VX675</option>
                                                    <option value="INGENICO-ICT220">INGENICO-ICT220</option>
                                                    <option value="PAX-S90">PAX-S90</option>
                                                </select>
                                            </div>
                                            <div class="col-12 text-center">
                                                <button type="submit" class="btn btn-primary px-4">
                                                    <i class="fas fa-search me-2"></i> جستجو
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <div id="searchResults" class="mt-4">
                                        <!-- نتایج جستجو اینجا نمایش داده می‌شود -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- بخش‌های دیگر (مدیر یا کاربر عادی) -->
                        <?php if(isset($_SESSION["user_description"]) && $_SESSION["user_description"] === "مدیر سیستم"): ?>
                            
                            
                            <!-- نمایش ویجت‌های داشبورد برای مدیر -->
                            <div class="row">
                                <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                    <div class="dashboard-widget">
                                        <div class="dashboard-widget-header">
                                            <span><i class="fas fa-exclamation-triangle me-2"></i> مشکلات اخیر</span>
                                            <a href="admin_issues.php" class="text-primary small">مشاهده همه</a>
                                        </div>
                                        <div class="dashboard-widget-body">
                                            <div id="recent-issues-loading" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">در حال بارگذاری...</span>
                                                </div>
                                            </div>
                                            <div id="recent-issues-container" style="display: none;">
                                                <!-- محتوا با جاوااسکریپت پر می‌شود -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                    <div class="dashboard-widget">
                                        <div class="dashboard-widget-header">
                                            <span><i class="fas fa-receipt me-2"></i> رول‌های اخیر</span>
                                            <a href="admin_roll_report.php" class="text-primary small">مشاهده همه</a>
                                        </div>
                                        <div class="dashboard-widget-body">
                                            <div id="recent-rolls-loading" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">در حال بارگذاری...</span>
                                                </div>
                                            </div>
                                            <div id="recent-rolls-container" style="display: none;">
                                                <!-- محتوا با جاوااسکریپت پر می‌شود -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اضافه کردن دکمه به بخش مدیر سیستم در dashboard.php -->
                            <div class="admin-actions d-flex flex-wrap justify-content-center justify-content-md-end mt-3">
                                <!-- دکمه ثبت به‌روزرسانی دیتابیس -->
                                <a href="admin_update_database.php" class="btn btn-info me-2 mb-2">
                                    <i class="fas fa-database me-1"></i> ثبت به‌روزرسانی دیتابیس
                                </a>
                                <!-- دکمه مدیریت اطلاعیه‌ها -->
                                <a href="admin_announcements.php" class="btn btn-warning me-2 mb-2">
                                    <i class="fas fa-bullhorn me-1"></i> مدیریت اطلاعیه‌ها
                                </a>
                                <!-- دکمه‌های دیگر -->
                                <a href="admin_roll_report.php" class="btn btn-success me-2 mb-2">
                                    <i class="fas fa-receipt me-1"></i> گزارش جامع رول‌ها
                                </a>
                                <a href="admin_issues.php" class="btn btn-primary mb-2">
                                    <i class="fas fa-tasks me-1"></i> مدیریت مشکلات
                                </a>
                                &nbsp;&nbsp; <a href="device_management.php" class="btn btn-info me-2 mb-2">
                                    <i class="fas fa-mobile-alt me-1"></i> مدیریت دستگاه‌ها
                                </a>
                                <a href="user_management.php" class="btn btn-info me-2 mb-2">
                                    <i class="fa-duotone fa-solid fa-users"></i>  تغییر رمز و مدیریت کاربران
                                </a>
                            </div>
                            <!-- دکمه‌های اصلی بازاریابی -->
                            <div class="row mb-4">
                                <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                    <a href="marketing.php" class="btn btn-success btn-lg w-100 py-3 shadow-sm">
                                        <i class="fas fa-plus-circle me-2"></i> ثبت بازاریابی جدید
                                    </a>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                    <a href="marketing_list.php" class="btn btn-secondary btn-lg w-100 py-3 shadow-sm">
                                        <i class="fas fa-handshake me-2"></i> مدیریت بازاریابی‌ها
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- دکمه ثبت بازاریابی جدید (برای همه کاربران) -->
                            <div class="text-center mb-4">
                                <a href="marketing.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-plus-circle me-2"></i> ثبت بازاریابی جدید
                                </a>
                            </div>
                            
<!-- بخش مدیریت انبار و تعمیرات -->
<div class="row mb-4">
    <div class="col-12">
        <h3 class="section-title mb-3"><i class="fas fa-boxes me-2"></i> مدیریت انبار و تعمیرات</h3>
        <div class="row g-3">
            <?php if(isset($_SESSION["user_description"]) && $_SESSION["user_description"] === "مسئول انبار"): ?>
                <!-- منوی مسئول انبار -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-primary mb-3">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <h5 class="card-title">مدیریت انبار</h5>
                            <p class="card-text text-muted">تخصیص رول کاغذ به کاربران، مشاهده سوابق و گزارش‌گیری</p>
                            <a href="warehouse_management.php" class="btn btn-primary mt-2">
                                <i class="fas fa-arrow-right me-2"></i> ورود به بخش
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION["user_description"]) && $_SESSION["user_description"] === "مسئول تعمیرات"): ?>
                <!-- منوی مسئول تعمیرات -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-warning mb-3">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h5 class="card-title">مدیریت تعمیرات</h5>
                            <p class="card-text text-muted">مدیریت درخواست‌های تعمیر، ثبت فرآیند تعمیر و گزارش‌گیری</p>
                            <a href="repair_management.php" class="btn btn-warning mt-2">
                                <i class="fas fa-arrow-right me-2"></i> ورود به بخش
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- منوی گزارش دستگاه خراب - برای همه کاربران -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle bg-danger mb-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5 class="card-title">گزارش دستگاه خراب</h5>
                        <p class="card-text text-muted">ثبت و پیگیری گزارش خرابی دستگاه‌ها و آداپتورها</p>
                        <a href="device_report.php" class="btn btn-danger mt-2">
                            <i class="fas fa-arrow-right me-2"></i> ورود به بخش
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- بخش تایید رول کاغذ - برای کاربران عادی -->
<?php if(!isset($_SESSION["user_description"]) || 
         ($_SESSION["user_description"] !== "مدیر سیستم" && 
          $_SESSION["user_description"] !== "مسئول انبار" && 
          $_SESSION["user_description"] !== "مسئول تعمیرات")): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>تایید دریافت رول کاغذ</h5>
        </div>
        <div class="card-body">
            <div id="pendingAssignments">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                    <p class="mt-2">در حال بررسی تخصیص‌های تایید نشده...</p>
                </div>
            </div>
            
            <div class="mt-4">
                <h6 class="mb-3">آخرین تخصیص‌های تایید شده</h6>
                <div id="confirmedAssignments">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                        <p class="mt-2">در حال بارگذاری...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>






                            <!-- بخش آمار و فعالیت‌های کاربر (بخش جدید) -->
                            <h3 class="section-title mb-3 mt-4"><i class="fas fa-chart-bar me-2"></i> آمار و فعالیت‌های شما</h3>

                            <!-- نمایش آمار کاربر -->
                            <div class="row mb-4">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card bg-primary text-white">
                                        <div class="stat-icon">
                                            <i class="fas fa-receipt"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>کل پی ام شده</h4>
                                            <h2 id="user-total-rolls">-</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card bg-success text-white">
                                        <div class="stat-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>پی‌ام‌های امروز</h4>
                                            <h2 id="user-today-pms">-</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card bg-warning text-white">
                                        <div class="stat-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>مشکلات ثبت شده</h4>
                                            <h2 id="user-total-issues">-</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card bg-danger text-white">
                                        <div class="stat-icon">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>مشکلات باز</h4>
                                            <h2 id="user-open-issues">-</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- آخرین فعالیت‌های کاربر -->
                            <h3 class="section-title mb-3"><i class="fas fa-history me-2"></i> آخرین فعالیت‌های شما</h3>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div id="user-activities-loading" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </div>
                                    <div id="user-activities-container" style="display: none;">
                                        <!-- محتوا با جاوااسکریپت پر می‌شود -->
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- دکمه بازگشت به بالا -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <!-- مودال نمایش اطلاعات پایانه -->
    <div class="modal fade" id="terminalDetailsModal" tabindex="-1" aria-labelledby="terminalDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="terminalDetailsModalLabel"><i class="fas fa-credit-card me-2"></i> اطلاعات پایانه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body" id="terminalDetailsContent">
                    <!-- محتوای مودال از طریق AJAX بارگذاری می‌شود -->
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="setTimeout(function() { window.location.reload(); }, 300);">بستن</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script src="dashboard.js"></script> <!-- فایل جاوااسکریپت اختصاصی داشبورد -->
    <script src="mobile-script.js" defer></script> <!-- اسکریپت مخصوص موبایل -->
    <script>
    // کد جاوااسکریپت اضافی برای بهبود تجربه کاربری
    document.addEventListener('DOMContentLoaded', function() {
        // نمایش حالت بارگذاری اولیه
        showLoadingOverlay();
        
        // مخفی کردن حالت بارگذاری پس از 1 ثانیه
        setTimeout(function() {
            hideLoadingOverlay();
        }, 800);
        
        // بارگذاری ویجت‌های داشبورد
        loadDashboardWidgets();
        
        // نمایش/مخفی کردن دکمه بازگشت به بالا
        window.addEventListener('scroll', function() {
            const backToTopBtn = document.getElementById('backToTop');
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
        
        // رویداد کلیک روی دکمه بازگشت به بالا
        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
    
    // نمایش حالت بارگذاری
    function showLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('show');
        }
    }
    
    // مخفی کردن حالت بارگذاری
    function hideLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('show');
        }
    }
    
    // بارگذاری ویجت‌های داشبورد
    function loadDashboardWidgets() {
        // بارگذاری مشکلات اخیر (فقط برای مدیر)
        const recentIssuesContainer = document.getElementById('recent-issues-container');
        if (recentIssuesContainer) {
            fetch('get_recent_issues.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('recent-issues-loading').style.display = 'none';
                    
                    if (data.success) {
                        let html = '';
                        
                        if (data.issues.length === 0) {
                            html = '<div class="alert alert-info">هیچ مشکلی یافت نشد.</div>';
                        } else {
                            html = '<ul class="list-group list-group-flush">';
                            
                            data.issues.forEach(issue => {
                                let statusClass = '';
                                switch(issue.status) {
                                    case 'open': statusClass = 'text-danger'; break;
                                    case 'in_progress': statusClass = 'text-warning'; break;
                                    case 'resolved': statusClass = 'text-success'; break;
                                    case 'closed': statusClass = 'text-secondary'; break;
                                }
                                
                                html += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                        <div class="d-flex align-items-center mb-2 mb-md-0 w-10">
                                            <span class="badge bg-primary me-2">${issue.terminal_code}</span>
                                            <span class="text-truncate">${issue.description.substring(0, 50)}${issue.description.length > 50 ? '...' : ''}</span>
                                        </div>
                                        <span class="${statusClass} mt-2 mt-md-0">${getStatusText(issue.status)}</span>
                                    </li>
                                `;
                            });
                            
                            html += '</ul>';
                        }
                        
                        recentIssuesContainer.innerHTML = html;
                        recentIssuesContainer.style.display = 'block';
                    } else {
                        recentIssuesContainer.innerHTML = '<div class="alert alert-warning">خطا در بارگذاری مشکلات اخیر</div>';
                        recentIssuesContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('recent-issues-loading').style.display = 'none';
                    recentIssuesContainer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری مشکلات اخیر</div>';
                    recentIssuesContainer.style.display = 'block';
                });
        }
        
        // بارگذاری رول‌های اخیر (فقط برای مدیر)
        const recentRollsContainer = document.getElementById('recent-rolls-container');
        if (recentRollsContainer) {
            fetch('get_recent_rolls.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('recent-rolls-loading').style.display = 'none';
                    
                    if (data.success) {
                        let html = '';
                        
                        if (data.rolls.length === 0) {
                            html = '<div class="alert alert-info">هیچ رولی یافت نشد.</div>';
                        } else {
                            html = '<ul class="list-group list-group-flush">';
                            
                            data.rolls.forEach(roll => {
                                html += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                        <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                                            <span class="badge bg-primary me-2 mb-1">${roll.terminal_code}</span>
                                            <span class="badge bg-info rounded-pill me-2 mb-1">${roll.quantity}</span>
                                            <span class="text-truncate">${roll.store_name}</span>
                                        </div>
                                        <small class="text-muted mt-1 mt-md-0">${roll.delivery_date}</small>
                                    </li>
                                `;
                            });
                            
                            html += '</ul>';
                        }
                        
                        recentRollsContainer.innerHTML = html;
                        recentRollsContainer.style.display = 'block';
                    } else {
                        recentRollsContainer.innerHTML = '<div class="alert alert-warning">خطا در بارگذاری رول‌های اخیر</div>';
                        recentRollsContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('recent-rolls-loading').style.display = 'none';
                    recentRollsContainer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری رول‌های اخیر</div>';
                    recentRollsContainer.style.display = 'block';
                });
        }
        
        // بارگذاری آمار کاربر
        loadUserStats();
        
        // بارگذاری فعالیت‌های کاربر
        const userActivitiesContainer = document.getElementById('user-activities-container');
        if (userActivitiesContainer) {
            fetch('get_user_activities.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('user-activities-loading').style.display = 'none';
                    
                    if (data.success) {
                        let html = '';
                        
                        if (data.activities.length === 0) {
                            html = '<div class="alert alert-info">هیچ فعالیتی یافت نشد.</div>';
                        } else {
                            html = '<div class="issue-timeline">';
                            
                            data.activities.forEach(activity => {
                                let iconClass = '';
                                let actionText = '';
                                
                                switch(activity.action) {
                                    case 'report_issue':
                                        iconClass = 'fas fa-exclamation-triangle text-warning';
                                        actionText = 'گزارش مشکل جدید';
                                        break;
                                    case 'update_issue':
                                        iconClass = 'fas fa-edit text-info';
                                        actionText = 'به‌روزرسانی مشکل';
                                        break;
                                    case 'submit_roll':
                                        iconClass = 'fas fa-receipt text-success';
                                        actionText = 'ثبت رول جدید';
                                        break;
                                    case 'login':
                                        iconClass = 'fas fa-sign-in-alt text-primary';
                                        actionText = 'ورود به سیستم';
                                        break;
                                    case 'logout':
                                        iconClass = 'fas fa-sign-out-alt text-danger';
                                        actionText = 'خروج از سیستم';
                                        break;
                                    case 'add_marketing':
                                        iconClass = 'fas fa-plus-circle text-success';
                                        actionText = 'ثبت بازاریابی جدید';
                                        break;
                                    case 'update_marketing':
                                        iconClass = 'fas fa-edit text-info';
                                        actionText = 'ویرایش بازاریابی';
                                        break;
                                    case 'update_marketing_status':
                                        iconClass = 'fas fa-check-circle text-primary';
                                        actionText = 'تغییر وضعیت بازاریابی';
                                        break;
                                    default:
                                        iconClass = 'fas fa-history text-secondary';
                                        actionText = activity.action;
                                }
                                
                                html += `
                                    <div class="issue-timeline-item">
                                        <div class="issue-timeline-item-content">
                                            <div class="issue-timeline-item-time">
                                                <i class="${iconClass} me-2"></i>
                                                ${activity.log_time}
                                            </div>
                                            <div>
                                                <strong>${actionText}</strong>
                                                ${activity.details ? `<p class="mb-0 mt-1 text-break">${activity.details}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                        }
                        
                        userActivitiesContainer.innerHTML = html;
                        userActivitiesContainer.style.display = 'block';
                    } else {
                        userActivitiesContainer.innerHTML = '<div class="alert alert-warning">خطا در بارگذاری فعالیت‌ها</div>';
                        userActivitiesContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('user-activities-loading').style.display = 'none';
                    userActivitiesContainer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری فعالیت‌ها</div>';
                    userActivitiesContainer.style.display = 'block';
                });
        }
        
        // بارگذاری بخش تایید رول کاغذ برای کاربران عادی
        loadPendingAssignments();
        loadConfirmedAssignments();
        
        // بارگذاری جدیدترین آخرین به‌روزرسانی دیتابیس با AJAX
        fetch('get_last_update.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.update_info) {
                    // اگر به‌روزرسانی جدیدتری وجود دارد، المان را به‌روز کنید
                    const updateElement = document.querySelector('.update-notification');
                    if (updateElement) {
                        const dateElement = updateElement.querySelector('strong').nextSibling;
                        if (dateElement) {
                            dateElement.textContent = ' ' + data.update_info.update_time_jalali;
                        }
                    } else {
                        // اگر المان وجود ندارد، یک المان جدید ایجاد و به صفحه اضافه کنید
                        const searchSection = document.querySelector('.search-section');
                        if (searchSection) {
                            const newUpdateElement = document.createElement('div');
                            newUpdateElement.className = 'update-notification';
                            newUpdateElement.innerHTML = `
                                <i class="fas fa-sync-alt"></i>
                                <div>
                                    <strong>آخرین به‌روزرسانی دیتابیس:</strong> ${data.update_info.update_time_jalali}
                                </div>
                            `;
                            searchSection.parentNode.insertBefore(newUpdateElement, searchSection);
                        }
                    }
                }
            })
            .catch(error => {
                console.error('خطا در دریافت آخرین به‌روزرسانی دیتابیس:', error);
            });
    }
    
    // بارگذاری آمار کاربر
    function loadUserStats() {
        // دریافت المان‌های آمار
        const userTotalRollsElement = document.getElementById('user-total-rolls');
        const userTodayPmsElement = document.getElementById('user-today-pms');
        const userTotalIssuesElement = document.getElementById('user-total-issues');
        const userOpenIssuesElement = document.getElementById('user-open-issues');
        
        // بررسی وجود المان‌ها
        if (!userTotalRollsElement || !userTodayPmsElement || !userTotalIssuesElement || !userOpenIssuesElement) {
            return; // اگر المان‌ها وجود ندارند، خارج شو
        }
        
        // دریافت آمار از سرور
        fetch('get_user_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // نمایش آمار با انیمیشن
                    animateNumber(userTotalRollsElement, data.stats.totalRolls);
                    animateNumber(userTodayPmsElement, data.stats.todayPMs);
                    animateNumber(userTotalIssuesElement, data.stats.totalIssues);
                    animateNumber(userOpenIssuesElement, data.stats.openIssues);
                } else {
                    console.error('خطا در دریافت آمار کاربر:', data.message);
                    // نمایش پیام خطا در المان‌ها
                    userTotalRollsElement.textContent = '-';
                    userTodayPmsElement.textContent = '-';
                    userTotalIssuesElement.textContent = '-';
                    userOpenIssuesElement.textContent = '-';
                }
            })
            .catch(error => {
                console.error('خطا در دریافت آمار کاربر:', error);
                // نمایش پیام خطا در المان‌ها
                userTotalRollsElement.textContent = '-';
                userTodayPmsElement.textContent = '-';
                userTotalIssuesElement.textContent = '-';
                userOpenIssuesElement.textContent = '-';
            });
    }
    
    // تبدیل وضعیت به متن فارسی
    function getStatusText(status) {
        switch(status) {
            case 'open': return 'باز';
            case 'in_progress': return 'در حال بررسی';
            case 'resolved': return 'حل شده';
            case 'closed': return 'بسته شده';
            default: return status;
        }
    }
    
    // انیمیشن برای نمایش اعداد
    function animateNumber(element, finalValue) {
        if (!element) return;
        
        const duration = 1000; // مدت زمان انیمیشن به میلی‌ثانیه
        const startTime = performance.now();
        const startValue = 0;
        
        function updateNumber(currentTime) {
            const elapsedTime = currentTime - startTime;
            const progress = Math.min(elapsedTime / duration, 1);
            
            const currentValue = Math.floor(progress * finalValue);
            element.textContent = currentValue;
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            }
        }
        
        requestAnimationFrame(updateNumber);
    }
    
    // بارگذاری تخصیص‌های تایید نشده
    function loadPendingAssignments() {
        const pendingAssignmentsContainer = document.getElementById('pendingAssignments');
        
        if (!pendingAssignmentsContainer) return;
        
        fetch('process_roll_confirmation.php?action=get_pending_assignments')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.assignments.length === 0) {
                        pendingAssignmentsContainer.innerHTML = `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                هیچ تخصیص تایید نشده‌ای وجود ندارد.
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.assignments.length} تخصیص رول کاغذ در انتظار تایید شما می‌باشد.
                        </div>
                    `;
                    
                    html += `<div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>تعداد</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>توسط</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    data.assignments.forEach(assignment => {
                        html += `
                            <tr>
                                <td>
                                    <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                                </td>
                                <td>${assignment.assign_date}</td>
                                <td>${assignment.assigned_by_name || '-'}</td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="confirmAssignment(${assignment.id})">
                                        <i class="fas fa-check me-1"></i> تایید دریافت
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                            </tbody>
                        </table>
                    </div>`;
                    
                    pendingAssignmentsContainer.innerHTML = html;
                } else {
                    pendingAssignmentsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            ${data.message || 'خطا در بارگذاری تخصیص‌ها'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                pendingAssignmentsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        خطا در ارتباط با سرور
                    </div>
                `;
                console.error('خطا:', error);
            });
    }
    
    // بارگذاری تخصیص‌های تایید شده
    function loadConfirmedAssignments() {
        const confirmedAssignmentsContainer = document.getElementById('confirmedAssignments');
        
        if (!confirmedAssignmentsContainer) return;
        
        fetch('process_roll_confirmation.php?action=get_confirmed_assignments&limit=5')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.assignments.length === 0) {
                        confirmedAssignmentsContainer.innerHTML = `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                هیچ سابقه‌ای یافت نشد.
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>تعداد</th>
                                        <th>تاریخ تخصیص</th>
                                        <th>تاریخ تایید</th>
                                        <th>توسط</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.assignments.forEach(assignment => {
                        html += `
                            <tr>
                                <td>
                                    <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                                </td>
                                <td>${assignment.assign_date}</td>
                                <td>${assignment.confirm_date}</td>
                                <td>${assignment.assigned_by_name || '-'}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                            </tbody>
                        </table>
                    </div>`;
                    
                    confirmedAssignmentsContainer.innerHTML = html;
                } else {
                    confirmedAssignmentsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            ${data.message || 'خطا در بارگذاری تخصیص‌ها'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                confirmedAssignmentsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        خطا در ارتباط با سرور
                    </div>
                `;
                console.error('خطا:', error);
            });
    }
    
    // تایید تخصیص رول
    function confirmAssignment(assignmentId) {
        if (!confirm('آیا از تایید دریافت این رول کاغذ اطمینان دارید؟')) {
            return;
        }
        
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        
        // ارسال درخواست تایید
        fetch('process_roll_confirmation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `assignment_id=${assignmentId}&csrf_token=${csrfToken}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                
                // بارگذاری مجدد داده‌ها
                loadPendingAssignments();
                loadConfirmedAssignments();
            } else {
                alert(data.message || 'خطا در تایید تخصیص');
            }
        })
        .catch(error => {
            alert('خطا در ارتباط با سرور');
            console.error('خطا:', error);
        });
    }
    </script>
    

<!-- این کد را در انتهای فایل dashboard.php قبل از </body> قرار دهید -->
<script>
function loadPendingAssignments() {
    const container = document.getElementById('pendingAssignments');
    if (!container) return;
    
    // نمایش loading
    container.innerHTML = '<div class="text-center py-3">در حال بارگذاری...</div>';
    
    // استفاده از jQuery ajax برای سازگاری بهتر
    $.ajax({
        url: 'process_roll_confirmation.php?action=get_pending_assignments',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Data received:', data);
            
            if (data.success && data.assignments && data.assignments.length > 0) {
                let html = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.assignments.length} تخصیص رول کاغذ در انتظار تایید شما می‌باشد.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>تعداد</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>توسط</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.assignments.forEach(item => {
                    html += `
                        <tr>
                            <td>
                                <span class="badge bg-info rounded-pill">${item.quantity}</span>
                            </td>
                            <td>${item.assign_date}</td>
                            <td>${item.assigned_by_name || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="confirmAssignment(${item.id})">
                                    <i class="fas fa-check me-1"></i> تایید دریافت
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        هیچ تخصیص تایید نشده‌ای وجود ندارد.
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', status, error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
        }
    });
}

function loadConfirmedAssignments() {
    const container = document.getElementById('confirmedAssignments');
    if (!container) return;
    
    // نمایش loading
    container.innerHTML = '<div class="text-center py-3">در حال بارگذاری...</div>';
    
    // استفاده از jQuery ajax برای سازگاری بهتر
    $.ajax({
        url: 'process_roll_confirmation.php?action=get_confirmed_assignments&limit=5',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Confirmed data received:', data);
            
            if (data.success && data.assignments && data.assignments.length > 0) {
                let html = `
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>تعداد</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>تاریخ تایید</th>
                                    <th>توسط</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.assignments.forEach(item => {
                    html += `
                        <tr>
                            <td>
                                <span class="badge bg-info rounded-pill">${item.quantity}</span>
                            </td>
                            <td>${item.assign_date}</td>
                            <td>${item.confirm_date || '-'}</td>
                            <td>${item.assigned_by_name || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        هیچ سابقه‌ای یافت نشد.
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', status, error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
        }
    });
}

function confirmAssignment(id) {
    if (!confirm('آیا از تایید این تخصیص اطمینان دارید؟')) return;
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    $.ajax({
        url: 'process_roll_confirmation.php',
        type: 'POST',
        data: {
            action: 'confirm_assignment',
            assignment_id: id,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(data) {
            alert(data.message || 'عملیات انجام شد');
            // رفرش داده‌ها
            loadPendingAssignments();
            loadConfirmedAssignments();
        },
        error: function(xhr, status, error) {
            alert('خطا در ارسال درخواست');
            console.error('Error:', status, error);
        }
    });
}

// بارگذاری اولیه داده‌ها
$(document).ready(function() {
    console.log('Loading roll assignments...');
    loadPendingAssignments();
    loadConfirmedAssignments();
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
</body>
</html>