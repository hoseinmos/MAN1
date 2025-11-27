<?php
// track_issues.php - صفحه پیگیری مشکلات پایانه‌ها
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
$user_description = isset($_SESSION['user_description']) ? $_SESSION['user_description'] : "";

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

// دریافت پارامتر terminal_code اگر ارسال شده باشد
$terminal_code = isset($_GET['terminal_code']) ? sanitize_input($_GET['terminal_code']) : '';

// متغیرها برای نتایج جستجو
$issues = array();
$has_search = false;
$terminal_info = null;

// اگر فرم جستجو ارسال شده یا کد پایانه در URL وجود داشته باشد
if($_SERVER["REQUEST_METHOD"] == "POST" || !empty($terminal_code)) {
    $has_search = true;
    
    // اگر فرم ارسال شده، کد پایانه را از آن بگیرید
    if($_SERVER["REQUEST_METHOD"] == "POST") {
        if(isset($_POST['terminal_code'])) {
            $terminal_code = sanitize_input($_POST['terminal_code']);
        }
    }
    
    // بررسی اتصال به پایگاه داده
    if($conn) {
        // ابتدا اطلاعات پایانه را دریافت کنید
        $terminal_sql = "SELECT id, terminal_number, store_name, device_model, support_person, status 
                        FROM terminals 
                        WHERE terminal_number LIKE ?";
        
        // اگر کاربر مدیر سیستم نیست، فقط پایانه‌های مربوط به خودش را ببیند
        if ($user_description !== "مدیر سیستم") {
            $terminal_sql .= " AND support_person = ?";
        }
        
        $terminal_sql .= " LIMIT 1";
        
        $terminal_stmt = null;
        if($terminal_stmt = $conn->prepare($terminal_sql)) {
            $search_term = "%$terminal_code%";
            
            if ($user_description === "مدیر سیستم") {
                $terminal_stmt->bind_param("s", $search_term);
            } else {
                $terminal_stmt->bind_param("ss", $search_term, $user_description);
            }
            
            $terminal_stmt->execute();
            $terminal_result = $terminal_stmt->get_result();
            
            if($terminal_result->num_rows > 0) {
                $terminal_info = $terminal_result->fetch_assoc();
                
                // سپس مشکلات مربوط به این پایانه را دریافت کنید
                $issues_sql = "SELECT ti.*, 
                               u_created.description as reporter_name,
                               u_updated.description as resolver_name
                               FROM terminal_issues ti
                               LEFT JOIN terminals t ON ti.terminal_id = t.id
                               LEFT JOIN access_codes u_created ON ti.created_by = u_created.id
                               LEFT JOIN access_codes u_updated ON ti.updated_by = u_updated.id
                               WHERE t.id = ?";
                
                // برای کاربران غیر مدیر، فقط مشکلات پایانه‌های خودشان را ببینند
                if ($user_description !== "مدیر سیستم") {
                    $issues_sql .= " AND t.support_person = ?";
                }
                
                $issues_sql .= " ORDER BY ti.created_at DESC";
                
                $issues_stmt = null;
                if($issues_stmt = $conn->prepare($issues_sql)) {
                    if ($user_description === "مدیر سیستم") {
                        $issues_stmt->bind_param("i", $terminal_info['id']);
                    } else {
                        $issues_stmt->bind_param("is", $terminal_info['id'], $user_description);
                    }
                    
                    $issues_stmt->execute();
                    $issues_result = $issues_stmt->get_result();
                    
                    while($row = $issues_result->fetch_assoc()) {
                        // تبدیل تاریخ‌ها به شمسی
                        $row['created_at'] = convert_to_jalali($row['created_at']);
                        
                        if(!empty($row['updated_at'])) {
                            $row['updated_at'] = convert_to_jalali($row['updated_at']);
                        }
                        
                        if(!empty($row['resolved_at'])) {
                            $row['resolved_at'] = convert_to_jalali($row['resolved_at']);
                        }
                        
                        $issues[] = $row;
                    }
                    
                    // بستن statement مربوط به مشکلات
                    $issues_stmt->close();
                }
            }
            
            // بستن statement مربوط به پایانه
            $terminal_stmt->close();
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
    <title>پیگیری مشکلات پایانه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .track-issues-container {
            padding: 2rem 0;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-open {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-in_progress {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-closed {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .priority-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .priority-low {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .priority-medium {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .priority-high {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .priority-critical {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .terminal-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .comments-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border-right: 4px solid #6c757d;
        }
        
        .issue-card {
            margin-bottom: 1.5rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .issue-card:hover {
            transform: translateY(-5px);
        }
        
        .issue-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(0,0,0,0.03);
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container track-issues-container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <h2>پیگیری مشکلات پایانه</h2>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
                    </a>
                </div>
                
                <!-- فرم جستجو -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label for="terminal_code" class="form-label">کد پایانه:</label>
                                    <input type="text" class="form-control" id="terminal_code" name="terminal_code" value="<?php echo htmlspecialchars($terminal_code); ?>" placeholder="کد پایانه را وارد کنید..." required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> جستجو
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if($has_search && empty($terminal_info)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> هیچ پایانه‌ای با کد "<?php echo htmlspecialchars($terminal_code); ?>" یافت نشد یا شما به آن دسترسی ندارید.
                    </div>
                <?php endif; ?>
                
                <?php if($terminal_info): ?>
                    <!-- اطلاعات پایانه -->
                    <div class="terminal-info">
                        <h4 class="mb-3">اطلاعات پایانه</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>کد پایانه:</strong> <?php echo htmlspecialchars($terminal_info['terminal_number']); ?></p>
                                <p><strong>نام فروشگاه:</strong> <?php echo htmlspecialchars($terminal_info['store_name']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>مدل دستگاه:</strong> <?php echo htmlspecialchars($terminal_info['device_model'] ?? '-'); ?></p>
                                <p><strong>پشتیبان:</strong> <?php echo htmlspecialchars($terminal_info['support_person'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- لیست مشکلات -->
                    <h4 class="mb-3">مشکلات ثبت شده</h4>
                    
                    <?php if(empty($issues)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> هیچ مشکلی برای این پایانه ثبت نشده است.
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="issuesAccordion">
                            <?php foreach($issues as $index => $issue): ?>
                                <?php
                                    // تعیین کلاس وضعیت
                                    $status_class = "";
                                    switch($issue['status']) {
                                        case 'open': $status_class = 'status-open'; break;
                                        case 'in_progress': $status_class = 'status-in_progress'; break;
                                        case 'resolved': $status_class = 'status-resolved'; break;
                                        case 'closed': $status_class = 'status-closed'; break;
                                    }
                                    
                                    // تعیین متن وضعیت
                                    $status_text = "";
                                    switch($issue['status']) {
                                        case 'open': $status_text = 'باز'; break;
                                        case 'in_progress': $status_text = 'در حال بررسی'; break;
                                        case 'resolved': $status_text = 'حل شده'; break;
                                        case 'closed': $status_text = 'بسته شده'; break;
                                        default: $status_text = $issue['status'];
                                    }
                                    
                                    // تعیین کلاس اولویت
                                    $priority_class = "";
                                    switch($issue['priority']) {
                                        case 'low': $priority_class = 'priority-low'; break;
                                        case 'medium': $priority_class = 'priority-medium'; break;
                                        case 'high': $priority_class = 'priority-high'; break;
                                        case 'critical': $priority_class = 'priority-critical'; break;
                                    }
                                    
                                    // تعیین متن اولویت
                                    $priority_text = "";
                                    switch($issue['priority']) {
                                        case 'low': $priority_text = 'کم'; break;
                                        case 'medium': $priority_text = 'متوسط'; break;
                                        case 'high': $priority_text = 'زیاد'; break;
                                        case 'critical': $priority_text = 'بحرانی'; break;
                                        default: $priority_text = $issue['priority'];
                                    }
                                ?>
                                <div class="accordion-item issue-card">
                                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <div>
                                                    #<?php echo $issue['id']; ?> - 
                                                    <span class="text-muted"><?php echo $issue['created_at']; ?></span>
                                                </div>
                                                <div>
                                                    <span class="<?php echo $priority_class; ?> me-2"><?php echo $priority_text; ?></span>
                                                    <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#issuesAccordion">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <h5>شرح مشکل:</h5>
                                                <p><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <h6>ثبت کننده:</h6>
                                                <p><?php echo htmlspecialchars($issue['reporter_name'] ?? 'نامشخص'); ?></p>
                                            </div>
                                            
                                            <?php if(!empty($issue['comments']) && ($issue['status'] == 'resolved' || $issue['status'] == 'closed')): ?>
                                                <div class="comments-section">
                                                    <h5>توضیحات حل/بستن مشکل:</h5>
                                                    <p><?php echo nl2br(htmlspecialchars($issue['comments'])); ?></p>
                                                    
                                                    <?php if(!empty($issue['resolver_name'])): ?>
                                                        <div class="text-muted mt-2">
                                                            <small>توسط: <?php echo htmlspecialchars($issue['resolver_name']); ?> در تاریخ <?php echo $issue['updated_at']; ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>