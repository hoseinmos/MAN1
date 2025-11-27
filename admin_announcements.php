<?php
// admin_announcements.php - صفحه مدیریت اطلاعیه‌ها (فقط برای مدیر)
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

// اگر فرم افزودن یا ویرایش اطلاعیه ارسال شده باشد
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_announcement'])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitize_input($_POST['content']) : '';
        $importance = isset($_POST['importance']) ? sanitize_input($_POST['importance']) : 'normal';
        $active = isset($_POST['active']) ? 1 : 0;
        $expire_date = isset($_POST['expire_date']) && !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
        
        // بررسی اعتبار داده‌ها
        if(empty($title)) {
            $error_message = "عنوان اطلاعیه نمی‌تواند خالی باشد.";
        } elseif(empty($content)) {
            $error_message = "متن اطلاعیه نمی‌تواند خالی باشد.";
        } else {
            // اگر ID وجود داشته باشد، اطلاعیه را به‌روزرسانی می‌کنیم
            if($id > 0) {
                $sql = "UPDATE announcements SET title = ?, content = ?, importance = ?, active = ?, expire_date = ?, updated_at = NOW() WHERE id = ?";
                
                if($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sssisi", $title, $content, $importance, $active, $expire_date, $id);
                    
                    if($stmt->execute()) {
                        $success_message = "اطلاعیه با موفقیت به‌روزرسانی شد.";
                    } else {
                        $error_message = "خطا در به‌روزرسانی اطلاعیه: " . $stmt->error;
                    }
                    
                    $stmt->close();
                } else {
                    $error_message = "خطا در آماده‌سازی درخواست: " . $conn->error;
                }
            } else {
                // اطلاعیه جدید ایجاد می‌کنیم
                $sql = "INSERT INTO announcements (title, content, importance, active, created_by, expire_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                if($stmt = $conn->prepare($sql)) {
                    $user_id = $_SESSION['user_id'];
                    $stmt->bind_param("sssiss", $title, $content, $importance, $active, $user_id, $expire_date);
                    
                    if($stmt->execute()) {
                        $success_message = "اطلاعیه جدید با موفقیت ایجاد شد.";
                        
                        // ثبت در لاگ
                        $log_sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) 
                                    VALUES (?, 'create_announcement', ?, NOW())";
                        
                        if($log_stmt = $conn->prepare($log_sql)) {
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $log_stmt->bind_param("is", $user_id, $ip);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    } else {
                        $error_message = "خطا در ایجاد اطلاعیه: " . $stmt->error;
                    }
                    
                    $stmt->close();
                } else {
                    $error_message = "خطا در آماده‌سازی درخواست: " . $conn->error;
                }
            }
        }
    }
}

// حذف اطلاعیه
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    $sql = "DELETE FROM announcements WHERE id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            $success_message = "اطلاعیه با موفقیت حذف شد.";
            
            // ثبت در لاگ
            $log_sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) 
                        VALUES (?, 'delete_announcement', ?, NOW())";
            
            if($log_stmt = $conn->prepare($log_sql)) {
                $user_id = $_SESSION['user_id'];
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("is", $user_id, $ip);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            $error_message = "خطا در حذف اطلاعیه: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = "خطا در آماده‌سازی درخواست: " . $conn->error;
    }
}

// دریافت لیست اطلاعیه‌ها
$announcements = [];
if($conn) {
    $sql = "SELECT a.*, u.description as author_name 
            FROM announcements a
            LEFT JOIN access_codes u ON a.created_by = u.id
            ORDER BY a.created_at DESC";
            
    $result = $conn->query($sql);
    if($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // تبدیل تاریخ‌ها به شمسی
            $row['created_at'] = convert_to_jalali($row['created_at']);
            if(!empty($row['updated_at'])) {
                $row['updated_at'] = convert_to_jalali($row['updated_at']);
            }
            if(!empty($row['expire_date'])) {
                // تبدیل تاریخ انقضا به شمسی
                $date_parts = explode('-', $row['expire_date']);
                $row['expire_date_jalali'] = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2], '/');
            }
            
            $announcements[] = $row;
        }
    }
}

// تولید توکن CSRF
$csrf_token = generate_csrf_token();

// دریافت اطلاعیه برای ویرایش
$edit_announcement = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    
    foreach($announcements as $announcement) {
        if($announcement['id'] == $edit_id) {
            $edit_announcement = $announcement;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت اطلاعیه‌ها</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="mobile-styles.css">
<script src="mobile-script.js" defer></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-announcements {
            padding: 2rem 0;
        }
        
        .importance-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .importance-normal {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .importance-important {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .importance-critical {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container admin-announcements">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-bullhorn"></i> مدیریت اطلاعیه‌ها</h2>
                        <a href="dashboard.php" class="btn btn-light"><i class="fas fa-arrow-right"></i> بازگشت به داشبورد</a>
                    </div>
                    <div class="card-body">
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
                        
                        <!-- فرم افزودن/ویرایش اطلاعیه -->
                        <div class="card mb-4">
                            <div class="card-header bg-<?php echo $edit_announcement ? 'warning' : 'success'; ?>">
                                <h5 class="mb-0 text-white">
                                    <i class="fas fa-<?php echo $edit_announcement ? 'edit' : 'plus'; ?>"></i>
                                    <?php echo $edit_announcement ? 'ویرایش اطلاعیه' : 'افزودن اطلاعیه جدید'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <?php if($edit_announcement): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_announcement['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="title" class="form-label">عنوان اطلاعیه:</label>
                                            <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['title']) : ''; ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="importance" class="form-label">اهمیت:</label>
                                            <select class="form-select" id="importance" name="importance">
                                                <option value="normal" <?php echo ($edit_announcement && $edit_announcement['importance'] == 'normal') ? 'selected' : ''; ?>>عادی</option>
                                                <option value="important" <?php echo ($edit_announcement && $edit_announcement['importance'] == 'important') ? 'selected' : ''; ?>>مهم</option>
                                                <option value="critical" <?php echo ($edit_announcement && $edit_announcement['importance'] == 'critical') ? 'selected' : ''; ?>>بحرانی</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">متن اطلاعیه:</label>
                                        <textarea class="form-control" id="content" name="content" rows="5" required><?php echo $edit_announcement ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="expire_date" class="form-label">تاریخ انقضا (اختیاری):</label>
                                            <input type="date" class="form-control" id="expire_date" name="expire_date" value="<?php echo $edit_announcement && !empty($edit_announcement['expire_date']) ? $edit_announcement['expire_date'] : ''; ?>">
                                            <div class="form-text">در صورت عدم تعیین، اطلاعیه منقضی نمی‌شود.</div>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="active" name="active" <?php echo ($edit_announcement && $edit_announcement['active'] == 1) || !$edit_announcement ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="active">
                                                    فعال (نمایش داده شود)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <?php if($edit_announcement): ?>
                                            <a href="admin_announcements.php" class="btn btn-secondary">انصراف</a>
                                        <?php endif; ?>
                                        <button type="submit" name="submit_announcement" class="btn btn-<?php echo $edit_announcement ? 'warning' : 'success'; ?>">
                                            <i class="fas fa-save me-1"></i>
                                            <?php echo $edit_announcement ? 'به‌روزرسانی اطلاعیه' : 'ثبت اطلاعیه'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- جدول اطلاعیه‌ها -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">لیست اطلاعیه‌ها</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>عنوان</th>
                                                <th>اهمیت</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ انقضا</th>
                                                <th>نویسنده</th>
                                                <th>تاریخ ایجاد</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($announcements)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">هیچ اطلاعیه‌ای ثبت نشده است.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($announcements as $index => $announcement): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                                        <td>
                                                            <?php 
                                                                $importance_class = '';
                                                                $importance_text = '';
                                                                
                                                                switch($announcement['importance']) {
                                                                    case 'normal':
                                                                        $importance_class = 'importance-normal';
                                                                        $importance_text = 'عادی';
                                                                        break;
                                                                    case 'important':
                                                                        $importance_class = 'importance-important';
                                                                        $importance_text = 'مهم';
                                                                        break;
                                                                    case 'critical':
                                                                        $importance_class = 'importance-critical';
                                                                        $importance_text = 'بحرانی';
                                                                        break;
                                                                }
                                                            ?>
                                                            <span class="importance-badge <?php echo $importance_class; ?>">
                                                                <?php echo $importance_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if($announcement['active']): ?>
                                                                <span class="badge bg-success">فعال</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">غیرفعال</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                                if(!empty($announcement['expire_date_jalali'])) {
                                                                    echo $announcement['expire_date_jalali'];
                                                                    
                                                                    // بررسی منقضی شدن
                                                                    $today = date('Y-m-d');
                                                                    if($announcement['expire_date'] < $today) {
                                                                        echo ' <span class="badge bg-danger">منقضی شده</span>';
                                                                    }
                                                                } else {
                                                                    echo '-';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($announcement['author_name']); ?></td>
                                                        <td><?php echo $announcement['created_at']; ?></td>
                                                        <td>
                                                            <a href="admin_announcements.php?edit=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="admin_announcements.php?delete=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این اطلاعیه اطمینان دارید؟');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
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
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>