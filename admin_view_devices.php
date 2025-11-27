<?php
// admin_view_devices.php - صفحه مشاهده دستگاه‌های کاربران (فقط برای مدیر)
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

// دریافت فیلترهای جستجو
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$device_type_filter = isset($_GET['device_type']) ? sanitize_input($_GET['device_type']) : '';

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$limit = 20; // تعداد رکورد در هر صفحه
$offset = ($page - 1) * $limit;

// دریافت تعداد کل رکوردها با اعمال فیلترها
$count_sql = "SELECT COUNT(*) as total FROM user_devices ud 
             LEFT JOIN access_codes ac ON ud.user_id = ac.id
             WHERE 1=1";

if($user_filter > 0) {
    $count_sql .= " AND ud.user_id = $user_filter";
}

if(!empty($device_type_filter)) {
    $count_sql .= " AND ud.device_type = '$device_type_filter'";
}

$total_records = 0;
$result = $conn->query($count_sql);
if($result && $row = $result->fetch_assoc()) {
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $limit);

// دریافت لیست دستگاه‌ها
$devices = [];
$sql = "SELECT ud.*, ac.description as user_name
        FROM user_devices ud
        LEFT JOIN access_codes ac ON ud.user_id = ac.id
        WHERE 1=1";

if($user_filter > 0) {
    $sql .= " AND ud.user_id = $user_filter";
}

if(!empty($device_type_filter)) {
    $sql .= " AND ud.device_type = '$device_type_filter'";
}

$sql .= " ORDER BY ud.last_login DESC LIMIT $offset, $limit";

$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    // تبدیل تاریخ‌ها به شمسی
    $row['first_login'] = convert_to_jalali($row['first_login']);
    $row['last_login'] = convert_to_jalali($row['last_login']);
    $devices[] = $row;
}

// دریافت لیست کاربران برای فیلتر
$users = [];
$user_sql = "SELECT id, description FROM access_codes ORDER BY description";
$user_result = $conn->query($user_sql);
while($user_row = $user_result->fetch_assoc()) {
    $users[] = $user_row;
}

// تولید توکن CSRF
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دستگاه‌های کاربران</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .device-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .device-mobile {
            color: #1a73e8;
        }
        
        .device-tablet {
            color: #28a745;
        }
        
        .device-desktop {
            color: #6c757d;
        }
        
        .filter-form {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-mobile-alt me-2"></i> مدیریت دستگاه‌های کاربران</h2>
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-arrow-right me-1"></i> بازگشت به داشبورد
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- فیلتر -->
                        <div class="filter-form mb-4">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label for="user_id" class="form-label">کاربر:</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="0">همه کاربران</option>
                                        <?php foreach($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['description']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="device_type" class="form-label">نوع دستگاه:</label>
                                    <select class="form-select" id="device_type" name="device_type">
                                        <option value="">همه</option>
                                        <option value="Mobile" <?php echo $device_type_filter == 'Mobile' ? 'selected' : ''; ?>>موبایل</option>
                                        <option value="Tablet" <?php echo $device_type_filter == 'Tablet' ? 'selected' : ''; ?>>تبلت</option>
                                        <option value="Desktop" <?php echo $device_type_filter == 'Desktop' ? 'selected' : ''; ?>>دسکتاپ</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i> اعمال فیلتر
                                    </button>
                                    <a href="admin_view_devices.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> حذف فیلتر
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- جدول نمایش دستگاه‌ها -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>کاربر</th>
                                        <th>نوع دستگاه</th>
                                        <th>نام دستگاه</th>
                                        <th>مرورگر</th>
                                        <th>سیستم عامل</th>
                                        <th>آخرین IP</th>
                                        <th>تعداد ورود</th>
                                        <th>آخرین ورود</th>
                                        <th>اولین ورود</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($devices)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">هیچ دستگاهی یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($devices as $index => $device): ?>
                                            <tr>
                                                <td><?php echo $offset + $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($device['user_name']); ?></td>
                                                <td>
                                                    <?php 
                                                        $icon_class = 'device-desktop';
                                                        $icon = 'desktop';
                                                        
                                                        if(stripos($device['device_type'], 'Mobile') !== false) {
                                                            $icon_class = 'device-mobile';
                                                            $icon = 'mobile-alt';
                                                        } elseif(stripos($device['device_type'], 'Tablet') !== false) {
                                                            $icon_class = 'device-tablet';
                                                            $icon = 'tablet-alt';
                                                        }
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?> device-icon <?php echo $icon_class; ?>"></i>
                                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                                <td><?php echo htmlspecialchars($device['browser']); ?></td>
                                                <td><?php echo htmlspecialchars($device['os']); ?></td>
                                                <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                                <td><?php echo $device['login_count']; ?></td>
                                                <td><?php echo $device['last_login']; ?></td>
                                                <td><?php echo $device['first_login']; ?></td>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $user_filter > 0 ? '&user_id='.$user_filter : ''; ?><?php echo !empty($device_type_filter) ? '&device_type='.$device_type_filter : ''; ?>" aria-label="قبلی">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' 
                                            . ($user_filter > 0 ? '&user_id='.$user_filter : '') 
                                            . (!empty($device_type_filter) ? '&device_type='.$device_type_filter : '') 
                                            . '">1</a></li>';
                                        if($start_page > 2) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                    }
                                    
                                    for($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">'
                                            . '<a class="page-link" href="?page=' . $i 
                                            . ($user_filter > 0 ? '&user_id='.$user_filter : '') 
                                            . (!empty($device_type_filter) ? '&device_type='.$device_type_filter : '') 
                                            . '">' . $i . '</a>'
                                            . '</li>';
                                    }
                                    
                                    if($end_page < $total_pages) {
                                        if($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages 
                                            . ($user_filter > 0 ? '&user_id='.$user_filter : '') 
                                            . (!empty($device_type_filter) ? '&device_type='.$device_type_filter : '') 
                                            . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $user_filter > 0 ? '&user_id='.$user_filter : ''; ?><?php echo !empty($device_type_filter) ? '&device_type='.$device_type_filter : ''; ?>" aria-label="بعدی">
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