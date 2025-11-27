<?php
// marketing_list.php - صفحه لیست بازاریابی‌ها
include 'config.php';
include 'jdf.php';

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
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

// متغیرهای مورد نیاز
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION["user_description"] === "مدیر سیستم");

// متغیرها برای صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$limit = 10; // تعداد رکورد در هر صفحه
$offset = ($page - 1) * $limit;

// فیلترهای جستجو
$name_filter = isset($_GET['name']) ? sanitize_input($_GET['name']) : '';
$national_code_filter = isset($_GET['national_code']) ? sanitize_input($_GET['national_code']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// بررسی عملیات حذف
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // بررسی دسترسی به حذف (فقط مدیر یا ایجادکننده)
    $check_sql = "SELECT created_by FROM marketing WHERE id = ?";
    if($check_stmt = $conn->prepare($check_sql)) {
        $check_stmt->bind_param("i", $delete_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $created_by = $row['created_by'];
            
            // اگر کاربر مدیر یا ایجادکننده است
            if($is_admin || $created_by == $user_id) {
                // حذف بازاریابی
                $delete_sql = "DELETE FROM marketing WHERE id = ?";
                if($delete_stmt = $conn->prepare($delete_sql)) {
                    $delete_stmt->bind_param("i", $delete_id);
                    
                    if($delete_stmt->execute()) {
                        // حذف پوشه و فایل‌های مربوطه
                        $upload_dir = 'uploads/marketing/' . $delete_id;
                        if(file_exists($upload_dir)) {
                            // حذف همه فایل‌های داخل پوشه
                            $files = glob($upload_dir . '/*');
                            foreach($files as $file) {
                                if(is_file($file)) {
                                    unlink($file);
                                }
                            }
                            // حذف خود پوشه
                            rmdir($upload_dir);
                        }
                        
                        // ثبت در لاگ
                        $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                                   VALUES (?, 'delete_marketing', ?, ?, NOW())";
                        
                        if($log_stmt = $conn->prepare($log_sql)) {
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $log_stmt->bind_param("iis", $user_id, $delete_id, $ip);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        
                        $_SESSION['success'] = "بازاریابی با موفقیت حذف شد.";
                    } else {
                        $_SESSION['error'] = "خطا در حذف بازاریابی: " . $delete_stmt->error;
                    }
                    
                    $delete_stmt->close();
                } else {
                    $_SESSION['error'] = "خطا در آماده‌سازی دستور SQL: " . $conn->error;
                }
            } else {
                $_SESSION['error'] = "شما اجازه حذف این بازاریابی را ندارید.";
            }
        } else {
            $_SESSION['error'] = "بازاریابی مورد نظر یافت نشد.";
        }
        
        $check_stmt->close();
    } else {
        $_SESSION['error'] = "خطا در بررسی دسترسی: " . $conn->error;
    }
    
    // هدایت به همین صفحه بدون پارامتر حذف
    $redirect_url = 'marketing_list.php';
    if(!empty($name_filter) || !empty($national_code_filter) || !empty($status_filter)) {
        $redirect_url .= '?';
        $params = [];
        if(!empty($name_filter)) $params[] = "name=" . urlencode($name_filter);
        if(!empty($national_code_filter)) $params[] = "national_code=" . urlencode($national_code_filter);
        if(!empty($status_filter)) $params[] = "status=" . urlencode($status_filter);
        $redirect_url .= implode('&', $params);
    }
    
    header("Location: $redirect_url");
    exit;
}

// دریافت تعداد کل بازاریابی‌ها با در نظر گرفتن فیلترها
$count_sql = "SELECT COUNT(*) as total FROM marketing WHERE 1=1";
$count_params = [];
$count_types = "";

// اعمال فیلترهای شمارش
if(!empty($name_filter)) {
    $count_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $name_search = "%$name_filter%";
    $count_params[] = $name_search;
    $count_params[] = $name_search;
    $count_params[] = $name_search;
    $count_types .= "sss";
}

if(!empty($national_code_filter)) {
    $count_sql .= " AND national_code LIKE ?";
    $count_params[] = "%$national_code_filter%";
    $count_types .= "s";
}

if(!empty($status_filter)) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
    $count_types .= "s";
}

// اگر کاربر مدیر نیست، فقط بازاریابی‌های خودش را ببیند
if(!$is_admin) {
    $count_sql .= " AND created_by = ?";
    $count_params[] = $user_id;
    $count_types .= "i";
}

$total_records = 0;
if($conn) {
    if($count_stmt = $conn->prepare($count_sql)) {
        if(!empty($count_params)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
        
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        
        if($count_row = $count_result->fetch_assoc()) {
            $total_records = $count_row['total'];
        }
        
        $count_stmt->close();
    }
}

$total_pages = ceil($total_records / $limit);

// دریافت لیست بازاریابی‌ها با اعمال فیلترها و صفحه‌بندی
$marketing_list = [];
if($conn) {
    $sql = "SELECT m.*, 
            u_created.description as created_by_name, 
            u_updated.description as updated_by_name
            FROM marketing m
            LEFT JOIN access_codes u_created ON m.created_by = u_created.id
            LEFT JOIN access_codes u_updated ON m.updated_by = u_updated.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // اعمال فیلترها
    if(!empty($name_filter)) {
        $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
        $name_search = "%$name_filter%";
        $params[] = $name_search;
        $params[] = $name_search;
        $params[] = $name_search;
        $types .= "sss";
    }
    
    if(!empty($national_code_filter)) {
        $sql .= " AND m.national_code LIKE ?";
        $params[] = "%$national_code_filter%";
        $types .= "s";
    }
    
    if(!empty($status_filter)) {
        $sql .= " AND m.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    // اگر کاربر مدیر نیست، فقط بازاریابی‌های خودش را ببیند
    if(!$is_admin) {
        $sql .= " AND m.created_by = ?";
        $params[] = $user_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    if($stmt = $conn->prepare($sql)) {
        if(!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            // تبدیل تاریخ‌ها به شمسی
            $row['created_at'] = convert_to_jalali($row['created_at']);
            if(!empty($row['updated_at'])) {
                $row['updated_at'] = convert_to_jalali($row['updated_at']);
            }
            if(!empty($row['birth_date'])) {
                $birth_date = explode('-', $row['birth_date']);
                $row['birth_date_jalali'] = gregorian_to_jalali($birth_date[0], $birth_date[1], $birth_date[2], '/');
            }
            
            $marketing_list[] = $row;
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
    <title>لیست بازاریابی‌ها</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="marketing.css">
</head>
<body class="dashboard-page">
    <div class="container marketing-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-list me-2"></i> لیست بازاریابی‌ها</h2>
                        <div>
                            <a href="marketing.php" class="btn btn-success me-2">
                                <i class="fas fa-plus me-1"></i> ثبت بازاریابی جدید
                            </a>
                            <a href="dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به داشبورد
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- فرم فیلتر -->
                        <div class="filter-form mb-4">
                            <form method="GET" action="marketing_list.php" class="row g-3">
                                <div class="col-md-3">
                                    <label for="name" class="form-label">نام و نام خانوادگی:</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name_filter); ?>" placeholder="جستجو...">
                                </div>
                                <div class="col-md-3">
                                    <label for="national_code" class="form-label">کد ملی:</label>
                                    <input type="text" class="form-control" id="national_code" name="national_code" value="<?php echo htmlspecialchars($national_code_filter); ?>" placeholder="جستجو...">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">وضعیت:</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">همه</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>در انتظار بررسی</option>
                                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>تأیید شده</option>
                                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i> اعمال فیلتر
                                    </button>
                                    <a href="marketing_list.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> حذف فیلتر
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- جدول بازاریابی‌ها -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام و نام خانوادگی</th>
                                        <th>کد ملی</th>
                                        <th>نام فروشگاه</th>
                                        <th>موبایل</th>
                                        <th>وضعیت</th>
                                        <th>تاریخ ثبت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($marketing_list)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">هیچ بازاریابی‌ای یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($marketing_list as $index => $marketing): ?>
                                            <tr>
                                                <td><?php echo $offset + $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($marketing['first_name'] . ' ' . $marketing['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($marketing['national_code']); ?></td>
                                                <td><?php echo htmlspecialchars($marketing['store_name']); ?></td>
                                                <td><?php echo htmlspecialchars($marketing['mobile']); ?></td>
                                                <td>
                                                    <?php if($marketing['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">در انتظار بررسی</span>
                                                    <?php elseif($marketing['status'] == 'approved'): ?>
                                                        <span class="badge bg-success">تأیید شده</span>
                                                    <?php elseif($marketing['status'] == 'rejected'): ?>
                                                        <span class="badge bg-danger">رد شده</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $marketing['created_at']; ?></td>
                                                <td>
                                                    <a href="marketing_view.php?id=<?php echo $marketing['id']; ?>" class="btn btn-sm btn-info me-1">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="marketing.php?id=<?php echo $marketing['id']; ?>" class="btn btn-sm btn-warning me-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($is_admin || $marketing['created_by'] == $user_id): ?>
                                                        <a href="marketing_list.php?delete=<?php echo $marketing['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این بازاریابی اطمینان دارید؟');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- صفحه‌بندی -->
                        <?php if($total_pages > 1): ?>
                            <nav aria-label="صفحه‌بندی" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($name_filter) ? '&name='.urlencode($name_filter) : ''; ?><?php echo !empty($national_code_filter) ? '&national_code='.urlencode($national_code_filter) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" aria-label="قبلی">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1'.(!empty($name_filter) ? '&name='.urlencode($name_filter) : '').(!empty($national_code_filter) ? '&national_code='.urlencode($national_code_filter) : '').(!empty($status_filter) ? '&status='.urlencode($status_filter) : '').'">1</a></li>';
                                        if($start_page > 2) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                    }
                                    
                                    for($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item '.($i == $page ? 'active' : '').'"><a class="page-link" href="?page='.$i.(!empty($name_filter) ? '&name='.urlencode($name_filter) : '').(!empty($national_code_filter) ? '&national_code='.urlencode($national_code_filter) : '').(!empty($status_filter) ? '&status='.urlencode($status_filter) : '').'">'.$i.'</a></li>';
                                    }
                                    
                                    if($end_page < $total_pages) {
                                        if($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.(!empty($name_filter) ? '&name='.urlencode($name_filter) : '').(!empty($national_code_filter) ? '&national_code='.urlencode($national_code_filter) : '').(!empty($status_filter) ? '&status='.urlencode($status_filter) : '').'">'.$total_pages.'</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($name_filter) ? '&name='.urlencode($name_filter) : ''; ?><?php echo !empty($national_code_filter) ? '&national_code='.urlencode($national_code_filter) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" aria-label="بعدی">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>