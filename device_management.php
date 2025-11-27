<?php
// device_management.php - مدیریت دستگاه‌های کاربران
include 'config.php';

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی اینکه آیا کاربر مدیر سیستم است
if($_SESSION["user_description"] !== "مدیر سیستم"){
    $_SESSION['error'] = "شما دسترسی لازم برای این صفحه را ندارید.";
    header("location: dashboard.php");
    exit;
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// متغیرهای مورد نیاز
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$error_message = '';
$success_message = '';

// حذف دستگاه
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $device_id = (int)$_GET['delete'];
    
    $delete_sql = "DELETE FROM user_devices WHERE id = ?";
    if($delete_stmt = $conn->prepare($delete_sql)) {
        $delete_stmt->bind_param("i", $device_id);
        
        if($delete_stmt->execute()) {
            $success_message = "دستگاه مورد نظر با موفقیت حذف شد.";
        } else {
            $error_message = "خطا در حذف دستگاه: " . $delete_stmt->error;
        }
        
        $delete_stmt->close();
    }
}

// دریافت لیست دستگاه‌ها
$devices = [];
$sql = "SELECT ud.*, ac.description as username 
        FROM user_devices ud 
        LEFT JOIN access_codes ac ON ud.user_id = ac.id";

// اگر کاربر خاصی انتخاب شده باشد
if($user_id > 0) {
    $sql .= " WHERE ud.user_id = ?";
}

$sql .= " ORDER BY ud.last_login DESC";

if($stmt = $conn->prepare($sql)) {
    if($user_id > 0) {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
    
    $stmt->close();
}

// دریافت لیست کاربران برای فیلتر
$users = [];
$user_sql = "SELECT id, description FROM access_codes WHERE active = 1 ORDER BY description";
if($user_stmt = $conn->prepare($user_sql)) {
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    while($user_row = $user_result->fetch_assoc()) {
        $users[] = $user_row;
    }
    
    $user_stmt->close();
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
</head>
<body class="dashboard-page">
    <div class="container dashboard-container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="welcome-card welcome-animation">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-mobile-alt me-2"></i> مدیریت دستگاه‌های کاربران</h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به داشبورد
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if(!empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($success_message)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- فیلتر کاربران -->
                        <div class="filter-form mb-4">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label for="user_id" class="form-label">فیلتر بر اساس کاربر:</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="0">همه کاربران</option>
                                        <?php foreach($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['description']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i> اعمال فیلتر
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- جدول دستگاه‌ها -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">شناسه</th>
                                        <th scope="col">کاربر</th>
                                        <th scope="col">نوع دستگاه</th>
                                        <th scope="col">نام دستگاه</th>
                                        <th scope="col">مرورگر</th>
                                        <th scope="col">سیستم عامل</th>
                                        <th scope="col">آخرین IP</th>
                                        <th scope="col">تعداد ورود</th>
                                        <th scope="col">آخرین ورود</th>
                                        <th scope="col">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($devices)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">هیچ دستگاهی یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($devices as $device): ?>
                                            <tr>
                                                <td><?php echo $device['id']; ?></td>
                                                <td><?php echo htmlspecialchars($device['username']); ?></td>
                                                <td>
                                                    <?php 
                                                        $icon = 'desktop';
                                                        if($device['device_type'] == 'Mobile' || $device['device_type'] == 'Android Phone' || $device['device_type'] == 'iPhone') {
                                                            $icon = 'mobile-alt';
                                                        } elseif($device['device_type'] == 'Tablet' || $device['device_type'] == 'Android Tablet' || $device['device_type'] == 'iPad') {
                                                            $icon = 'tablet-alt';
                                                        }
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                                <td><?php echo htmlspecialchars($device['browser']); ?></td>
                                                <td><?php echo htmlspecialchars($device['os']); ?></td>
                                                <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                                <td><?php echo $device['login_count']; ?></td>
                                                <td><?php echo $device['last_login']; ?></td>
                                                <td>
                                                    <a href="device_management.php?delete=<?php echo $device['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این دستگاه اطمینان دارید؟');">
                                                        <i class="fas fa-trash"></i> حذف
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
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>