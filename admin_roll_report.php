<?php
// admin_roll_report.php - گزارش جامع مصرف رول‌های پایانه‌ها (فقط برای مدیر)
include 'config.php';
include 'jdf.php'; // اضافه کردن فایل jdf.php

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

// دریافت صفحه فعلی برای صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$limit = 15; // تعداد رکورد در هر صفحه
$offset = ($page - 1) * $limit;

// دریافت فیلترهای جستجو
$terminal_code_filter = isset($_GET['terminal_code']) ? sanitize_input($_GET['terminal_code']) : '';
$merchant_name_filter = isset($_GET['merchant_name']) ? sanitize_input($_GET['merchant_name']) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// آمار کلی
$total_rolls = 0;
$total_terminals = 0;
$total_users = 0;

// دریافت تعداد کل رکوردها بر اساس فیلترها
if($conn) {
    $count_sql = "SELECT COUNT(*) as total 
                 FROM paper_rolls pr 
                 LEFT JOIN terminals t ON pr.terminal_id = t.id
                 LEFT JOIN access_codes u ON pr.user_id = u.id
                 WHERE 1=1";
    
    $count_params = array();
    $count_types = "";
    
    // اعمال فیلترها
    if(!empty($terminal_code_filter)) {
        $count_sql .= " AND t.terminal_number LIKE ?";
        $count_params[] = "%$terminal_code_filter%";
        $count_types .= "s";
    }
    
    if(!empty($merchant_name_filter)) {
        $count_sql .= " AND t.store_name LIKE ?";
        $count_params[] = "%$merchant_name_filter%";
        $count_types .= "s";
    }
    
    if($user_filter > 0) {
        $count_sql .= " AND pr.user_id = ?";
        $count_params[] = $user_filter;
        $count_types .= "i";
    }
    
    if(!empty($date_from)) {
        $count_sql .= " AND DATE(pr.delivery_date) >= ?";
        $count_params[] = $date_from;
        $count_types .= "s";
    }
    
    if(!empty($date_to)) {
        $count_sql .= " AND DATE(pr.delivery_date) <= ?";
        $count_params[] = $date_to;
        $count_types .= "s";
    }
    
    if($count_stmt = $conn->prepare($count_sql)) {
        if(!empty($count_params)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
        
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if($count_row = $count_result->fetch_assoc()) {
            $total_rolls = $count_row['total'];
        }
        
        $count_stmt->close();
    }
    
    // دریافت تعداد کل پایانه‌ها
    $terminals_sql = "SELECT COUNT(DISTINCT terminal_id) as total FROM paper_rolls";
    $terminals_result = $conn->query($terminals_sql);
    if($terminals_result && $row = $terminals_result->fetch_assoc()) {
        $total_terminals = $row['total'];
    }
    
    // دریافت تعداد کل کاربران
    $users_sql = "SELECT COUNT(DISTINCT user_id) as total FROM paper_rolls";
    $users_result = $conn->query($users_sql);
    if($users_result && $row = $users_result->fetch_assoc()) {
        $total_users = $row['total'];
    }
}

$total_pages = ceil($total_rolls / $limit);

// دریافت لیست کاربران برای فیلتر
$users = array();
if($conn) {
    $users_sql = "SELECT DISTINCT u.id, u.description 
                 FROM paper_rolls pr 
                 JOIN access_codes u ON pr.user_id = u.id 
                 ORDER BY u.description";
    $users_result = $conn->query($users_sql);
    while($user_row = $users_result->fetch_assoc()) {
        $users[] = $user_row;
    }
}

// دریافت لیست رول‌ها بر اساس فیلترها
$rolls = array();
if($conn) {
    $sql = "SELECT pr.*, t.terminal_number, t.store_name, u.description as user_name,
                  (SELECT SUM(quantity) FROM paper_rolls WHERE terminal_id = pr.terminal_id) as terminal_total,
                  (SELECT SUM(quantity) FROM paper_rolls WHERE user_id = pr.user_id) as user_total
           FROM paper_rolls pr 
           LEFT JOIN terminals t ON pr.terminal_id = t.id
           LEFT JOIN access_codes u ON pr.user_id = u.id
           WHERE 1=1";
    
    $params = array();
    $types = "";
    
    // اعمال فیلترها
    if(!empty($terminal_code_filter)) {
        $sql .= " AND t.terminal_number LIKE ?";
        $params[] = "%$terminal_code_filter%";
        $types .= "s";
    }
    
    if(!empty($merchant_name_filter)) {
        $sql .= " AND t.store_name LIKE ?";
        $params[] = "%$merchant_name_filter%";
        $types .= "s";
    }
    
    if($user_filter > 0) {
        $sql .= " AND pr.user_id = ?";
        $params[] = $user_filter;
        $types .= "i";
    }
    
    if(!empty($date_from)) {
        $sql .= " AND DATE(pr.delivery_date) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if(!empty($date_to)) {
        $sql .= " AND DATE(pr.delivery_date) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY pr.delivery_date DESC LIMIT ? OFFSET ?";
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
            $row['delivery_date'] = convert_to_jalali($row['delivery_date']);
            $row['created_at'] = convert_to_jalali($row['created_at']);
            
            $rolls[] = $row;
        }
        
        $stmt->close();
    }
}

// دریافت آمار رول‌ها به تفکیک کاربر
$user_stats = array();
if($conn) {
    $user_stats_sql = "SELECT u.id, u.description as user_name, 
                             COUNT(pr.id) as roll_count, 
                             SUM(pr.quantity) as total_quantity
                      FROM access_codes u
                      LEFT JOIN paper_rolls pr ON u.id = pr.user_id
                      GROUP BY u.id
                      ORDER BY total_quantity DESC";
    
    $user_stats_result = $conn->query($user_stats_sql);
    while($stats_row = $user_stats_result->fetch_assoc()) {
        if($stats_row['roll_count'] > 0) { // فقط کاربرانی که رول ثبت کرده‌اند
            $user_stats[] = $stats_row;
        }
    }
}

// دریافت آمار رول‌ها به تفکیک پایانه/پذیرنده
$terminal_stats = array();
if($conn) {
    $terminal_stats_sql = "SELECT t.id, t.terminal_number, t.store_name, 
                                 COUNT(pr.id) as roll_count, 
                                 SUM(pr.quantity) as total_quantity
                           FROM terminals t
                           LEFT JOIN paper_rolls pr ON t.id = pr.terminal_id
                           GROUP BY t.id
                           ORDER BY total_quantity DESC
                           LIMIT 10"; // نمایش 10 پایانه برتر
    
    $terminal_stats_result = $conn->query($terminal_stats_sql);
    while($stats_row = $terminal_stats_result->fetch_assoc()) {
        if($stats_row['roll_count'] > 0) { // فقط پایانه‌هایی که رول ثبت شده دارند
            $terminal_stats[] = $stats_row;
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
    <title>گزارش جامع رول‌ها</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
            padding: 2rem 0;
        }
        
        .filter-form {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        
        .stats-card .card-header {
            font-weight: bold;
            padding: 10px 15px;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .roll-description {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .roll-description:hover {
            white-space: normal;
            overflow: visible;
        }
        
        .chart-container {
            height: 300px;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid admin-container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card welcome-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-receipt"></i> گزارش جامع رول‌ها</h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-light mx-1"><i class="fas fa-arrow-right"></i> بازگشت به داشبورد</a>
                            <a href="#" class="btn btn-info" onclick="printReport()"><i class="fas fa-print"></i> چاپ گزارش</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- آمار کلی -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="stats-card bg-primary text-white">
                                    <div class="card-header">
                                        <i class="fas fa-receipt"></i> تعداد کل رول‌های ثبت شده
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($total_rolls); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stats-card bg-success text-white">
                                    <div class="card-header">
                                        <i class="fas fa-credit-card"></i> تعداد پایانه‌های دریافت کننده
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($total_terminals); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stats-card bg-info text-white">
                                    <div class="card-header">
                                        <i class="fas fa-users"></i> تعداد کاربران ثبت کننده
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($total_users); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- فیلترهای جستجو -->
                        <div class="filter-form">
                            <form method="GET" action="admin_roll_report.php" class="row g-3">
                                <div class="col-md-2">
                                    <label for="terminal_code" class="form-label">کد پایانه:</label>
                                    <input type="text" class="form-control" id="terminal_code" name="terminal_code" value="<?php echo htmlspecialchars($terminal_code_filter); ?>" placeholder="جستجو...">
                                </div>
                                <div class="col-md-3">
                                    <label for="merchant_name" class="form-label">نام پذیرنده:</label>
                                    <input type="text" class="form-control" id="merchant_name" name="merchant_name" value="<?php echo htmlspecialchars($merchant_name_filter); ?>" placeholder="جستجو...">
                                </div>
                                <div class="col-md-2">
                                    <label for="user_id" class="form-label">کاربر:</label>
                                    <select name="user_id" id="user_id" class="form-select">
                                        <option value="0">همه کاربران</option>
                                        <?php foreach($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['description']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">از تاریخ:</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">تا تاریخ:</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> فیلتر</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- نمودارها -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-users"></i> نمودار مصرف رول به تفکیک کاربر</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="userRollChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-credit-card"></i> نمودار مصرف رول به تفکیک پایانه (10 مورد برتر)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="terminalRollChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- آمار مصرف به تفکیک کاربر -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-users"></i> آمار مصرف رول به تفکیک کاربر</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>نام کاربر</th>
                                                <th>تعداد دفعات ثبت</th>
                                                <th>تعداد کل رول</th>
                                                <th>درصد از کل</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $total_all_rolls = array_sum(array_column($user_stats, 'total_quantity'));
                                            foreach($user_stats as $index => $stat): 
                                                $percentage = $total_all_rolls > 0 ? round(($stat['total_quantity'] / $total_all_rolls) * 100, 1) : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($stat['user_name']); ?></td>
                                                    <td><?php echo number_format($stat['roll_count']); ?></td>
                                                    <td><?php echo number_format($stat['total_quantity']); ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $percentage; ?>%</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if(empty($user_stats)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">اطلاعاتی یافت نشد.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- جدول سوابق رول‌ها -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-history"></i> سوابق ثبت رول‌ها</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>کد پایانه</th>
                                                <th>نام پذیرنده</th>
                                                <th>تعداد</th>
                                                <th>تاریخ تحویل</th>
                                                <th>توضیحات</th>
                                                <th>کاربر ثبت کننده</th>
                                                <th>تاریخ ثبت</th>
                                                <th>مجموع پایانه</th>
                                                <th>مجموع کاربر</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($rolls)): ?>
                                                <tr>
                                                    <td colspan="10" class="text-center">هیچ رکوردی یافت نشد.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($rolls as $index => $roll): ?>
                                                    <tr>
                                                        <td><?php echo $offset + $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($roll['terminal_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($roll['store_name']); ?></td>
                                                        <td><?php echo number_format($roll['quantity']); ?></td>
                                                        <td><?php echo $roll['delivery_date']; ?></td>
                                                        <td class="roll-description" title="<?php echo htmlspecialchars($roll['description']); ?>">
                                                            <?php echo !empty($roll['description']) ? htmlspecialchars($roll['description']) : '-'; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($roll['user_name']); ?></td>
                                                        <td><?php echo $roll['created_at']; ?></td>
                                                        <td class="text-primary"><?php echo number_format($roll['terminal_total']); ?></td>
                                                        <td class="text-success"><?php echo number_format($roll['user_total']); ?></td>
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
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : ''; ?><?php echo !empty($merchant_name_filter) ? '&merchant_name='.$merchant_name_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id='.$user_filter : ''; ?><?php echo !empty($date_from) ? '&date_from='.$date_from : ''; ?><?php echo !empty($date_to) ? '&date_to='.$date_to : ''; ?>" aria-label="قبلی">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $page + 2);
                                            
                                            if($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1' 
                                                    . (!empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : '')
                                                    . (!empty($merchant_name_filter) ? '&merchant_name='.$merchant_name_filter : '')
                                                    . ($user_filter > 0 ? '&user_id='.$user_filter : '')
                                                    . (!empty($date_from) ? '&date_from='.$date_from : '')
                                                    . (!empty($date_to) ? '&date_to='.$date_to : '')
                                                    . '">1</a></li>';
                                                if($start_page > 2) {
                                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                }
                                            }
                                            
                                            for($i = $start_page; $i <= $end_page; $i++) {
                                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">'
                                                    . '<a class="page-link" href="?page=' . $i 
                                                    . (!empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : '')
                                                    . (!empty($merchant_name_filter) ? '&merchant_name='.$merchant_name_filter : '')
                                                    . ($user_filter > 0 ? '&user_id='.$user_filter : '')
                                                    . (!empty($date_from) ? '&date_from='.$date_from : '')
                                                    . (!empty($date_to) ? '&date_to='.$date_to : '')
                                                    . '">' . $i . '</a>'
                                                    . '</li>';
                                            }
                                            
                                            if($end_page < $total_pages) {
                                                if($end_page < $total_pages - 1) {
                                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages 
                                                    . (!empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : '')
                                                    . (!empty($merchant_name_filter) ? '&merchant_name='.$merchant_name_filter : '')
                                                    . ($user_filter > 0 ? '&user_id='.$user_filter : '')
                                                    . (!empty($date_from) ? '&date_from='.$date_from : '')
                                                    . (!empty($date_to) ? '&date_to='.$date_to : '')
                                                    . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <?php if($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : ''; ?><?php echo !empty($merchant_name_filter) ? '&merchant_name='.$merchant_name_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id='.$user_filter : ''; ?><?php echo !empty($date_from) ? '&date_from='.$date_from : ''; ?><?php echo !empty($date_to) ? '&date_to='.$date_to : ''; ?>" aria-label="بعدی">
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
        </div>
    </div>
    
    <!-- کتابخانه Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    
    <script>
        // نمودار مصرف رول به تفکیک کاربر
        document.addEventListener('DOMContentLoaded', function() {
            // داده‌های آماری کاربران
            const userChartData = {
                labels: [
                    <?php foreach($user_stats as $stat): ?>
                        '<?php echo addslashes($stat['user_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'تعداد رول مصرفی',
                    data: [
                        <?php foreach($user_stats as $stat): ?>
                            <?php echo $stat['total_quantity']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(199, 199, 199, 0.6)',
                        'rgba(83, 102, 255, 0.6)',
                        'rgba(40, 159, 64, 0.6)',
                        'rgba(210, 199, 199, 0.6)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)',
                        'rgba(83, 102, 255, 1)',
                        'rgba(40, 159, 64, 1)',
                        'rgba(210, 199, 199, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            // نمودار پایانه‌ها
            const terminalChartData = {
                labels: [
                    <?php foreach($terminal_stats as $stat): ?>
                        '<?php echo addslashes($stat['terminal_number'] . ' - ' . $stat['store_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'تعداد رول مصرفی',
                    data: [
                        <?php foreach($terminal_stats as $stat): ?>
                            <?php echo $stat['total_quantity']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            };
            
            // تنظیمات مشترک نمودارها
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Vazirmatn'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toLocaleString('fa-IR') + ' رول';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: 'Vazirmatn'
                            },
                            callback: function(value) {
                                return value.toLocaleString('fa-IR');
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Vazirmatn'
                            }
                        }
                    }
                }
            };
            
            // ایجاد نمودار مصرف کاربران
            const userChartCtx = document.getElementById('userRollChart').getContext('2d');
            const userChart = new Chart(userChartCtx, {
                type: 'bar',
                data: userChartData,
                options: commonOptions
            });
            
            // ایجاد نمودار مصرف پایانه‌ها
            const terminalChartCtx = document.getElementById('terminalRollChart').getContext('2d');
            const terminalChart = new Chart(terminalChartCtx, {
                type: 'bar',
                data: terminalChartData,
                options: commonOptions
            });
        });
        
        // تابع چاپ گزارش
        function printReport() {
            // ذخیره محتوای اصلی صفحه
            const originalContent = document.body.innerHTML;
            
            // آماده‌سازی محتوا برای چاپ
            const printContent = document.querySelector('.card-body').innerHTML;
            document.body.innerHTML = `
                <div class="container p-4">
                    <h1 class="text-center mb-4">گزارش جامع رول‌ها</h1>
                    <hr>
                    ${printContent}
                </div>
            `;
            
            // چاپ
            window.print();
            
            // بازگرداندن محتوای اصلی
            document.body.innerHTML = originalContent;
            
            // بازسازی نمودارها
            document.dispatchEvent(new Event('DOMContentLoaded'));
        }
    </script>
</body>
</html>