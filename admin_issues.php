<?php
// admin_issues.php - صفحه مدیریت مشکلات پایانه‌ها (فقط برای مدیر)
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

// دریافت تعداد کل مشکلات برای صفحه‌بندی
$total_issues = 0;
if($conn) {
    $count_sql = "SELECT COUNT(*) as total FROM terminal_issues ti
                 LEFT JOIN terminals t ON ti.terminal_id = t.id
                 WHERE 1=1";
    
    $count_params = array();
    $count_types = "";
    
    // اعمال فیلترها
    if(!empty($status_filter)) {
        $count_sql .= " AND ti.status = ?";
        $count_params[] = $status_filter;
        $count_types .= "s";
    }
    
    if(!empty($priority_filter)) {
        $count_sql .= " AND ti.priority = ?";
        $count_params[] = $priority_filter;
        $count_types .= "s";
    }
    
    // اضافه کردن فیلتر کد پایانه
    if(!empty($terminal_code_filter)) {
        $count_sql .= " AND t.terminal_number LIKE ?";
        $count_params[] = "%$terminal_code_filter%";
        $count_types .= "s";
    }
    
    if($count_stmt = $conn->prepare($count_sql)) {
        if(!empty($count_params)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
        
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if($count_row = $count_result->fetch_assoc()) {
            $total_issues = $count_row['total'];
        }
        
        $count_stmt->close();
    }
}
$total_pages = ceil($total_issues / $limit);

// دریافت فیلترهای جستجو
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? sanitize_input($_GET['priority']) : '';
$terminal_code_filter = isset($_GET['terminal_code']) ? sanitize_input($_GET['terminal_code']) : '';
// دریافت لیست مشکلات
$issues = array();
if($conn) {
    $sql = "SELECT ti.*, t.terminal_number as terminal_code, t.store_name as merchant_name, u.description as reporter_name 
            FROM terminal_issues ti
            LEFT JOIN terminals t ON ti.terminal_id = t.id
            LEFT JOIN access_codes u ON ti.created_by = u.id
            WHERE 1=1";
    
    $params = array();
    $types = "";
    
    // اعمال فیلترها
    if(!empty($status_filter)) {
        $sql .= " AND ti.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if(!empty($priority_filter)) {
        $sql .= " AND ti.priority = ?";
        $params[] = $priority_filter;
        $types .= "s";
    }
    
    // اضافه کردن فیلتر کد پایانه
    if(!empty($terminal_code_filter)) {
        $sql .= " AND t.terminal_number LIKE ?";
        $params[] = "%$terminal_code_filter%";
        $types .= "s";
    }
    
    $sql .= " ORDER BY ti.created_at DESC LIMIT ? OFFSET ?";
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
            if(!empty($row['resolved_at'])) {
                $row['resolved_at'] = convert_to_jalali($row['resolved_at']);
            }
            
            $issues[] = $row;
        }
        
        $stmt->close();
    }
}
// اگر فرم به‌روزرسانی وضعیت ارسال شده باشد
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_status"])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
        $new_status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
        $comments = isset($_POST['comments']) ? sanitize_input($_POST['comments']) : '';
        
        if($issue_id > 0 && !empty($new_status)) {
            $resolved_at = ($new_status == 'resolved') ? 'NOW()' : 'NULL';
            
            $sql = "UPDATE terminal_issues SET status = ?, comments = ?, updated_by = ?, updated_at = NOW(), resolved_at = " . $resolved_at . " WHERE id = ?";
            
            if($stmt = $conn->prepare($sql)) {
                $user_id = $_SESSION['user_id'];
                
                $stmt->bind_param("ssis", $new_status, $comments, $user_id, $issue_id);
                
                if($stmt->execute()) {
                    $_SESSION['success'] = "وضعیت مشکل با موفقیت به‌روزرسانی شد.";
                    
                    // ثبت در لاگ
                    $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                                VALUES (?, 'update_issue', ?, ?, NOW())";
                    
                    if($log_stmt = $conn->prepare($log_sql)) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iis", $user_id, $issue_id, $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    // هدایت به صفحه فعلی برای جلوگیری از ارسال مجدد فرم
                    header("location: admin_issues.php".(!empty($_SERVER['QUERY_STRING']) ? "?".$_SERVER['QUERY_STRING'] : ""));
                    exit;
                } else {
                    $_SESSION['error'] = "خطا در به‌روزرسانی وضعیت مشکل.";
                }
                
                $stmt->close();
            } else {
                $_SESSION['error'] = "خطا در اتصال به پایگاه داده.";
            }
        } else {
            $_SESSION['error'] = "پارامترهای نامعتبر.";
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
    <title>مدیریت مشکلات پایانه‌ها</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
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
        
        .filter-form {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        /* حل مشکل لرزش */
        .admin-container .card {
            transition: none;
            transform: none !important;
        }

        .admin-container .card:hover {
            transform: none !important;
        }

        .modal-content, .modal-dialog {
            transform: none !important;
            transition: none !important;
        }

        /* برای ثبات بیشتر در موقعیت مودال */
        .modal {
            overflow-y: auto !important;
        }
    </style>
</head>
<body>
    <div class="container admin-container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card welcome-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-exclamation-triangle"></i> مدیریت مشکلات پایانه‌ها</h2>
                        <a href="dashboard.php" class="btn btn-light"><i class="fas fa-arrow-right"></i> بازگشت به داشبورد</a>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            </div>
                        <?php endif; ?>
<!-- فیلترهای جستجو -->
<div class="filter-form">
    <form method="GET" action="admin_issues.php" class="row g-3">
        <div class="col-md-3">
            <label for="terminal_code" class="form-label">کد پایانه:</label>
            <input type="text" class="form-control" id="terminal_code" name="terminal_code" value="<?php echo isset($_GET['terminal_code']) ? htmlspecialchars($_GET['terminal_code']) : ''; ?>" placeholder="جستجوی کد پایانه...">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">وضعیت:</label>
            <select name="status" id="status" class="form-select">
                <option value="">همه</option>
                <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>باز</option>
                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>در حال بررسی</option>
                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>حل شده</option>
                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>بسته شده</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="priority" class="form-label">اولویت:</label>
            <select name="priority" id="priority" class="form-select">
                <option value="">همه</option>
                <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>کم</option>
                <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>متوسط</option>
                <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>زیاد</option>
                <option value="critical" <?php echo $priority_filter == 'critical' ? 'selected' : ''; ?>>بحرانی</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> اعمال فیلتر</button>
            <a href="admin_issues.php" class="btn btn-secondary ms-2"><i class="fas fa-redo"></i> پاک کردن</a>
        </div>
    </form>
</div>             
                        <!-- جدول مشکلات -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>کد پایانه</th>
                                        <th>نام پذیرنده</th>
                                        <th>شرح مشکل</th>
                                        <th>اولویت</th>
                                        <th>وضعیت</th>
                                        <th>ثبت کننده</th>
                                        <th>تاریخ ثبت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($issues)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">هیچ مشکلی یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($issues as $index => $issue): ?>
                                            <tr>
                                                <td><?php echo $offset + $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($issue['terminal_code']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['merchant_name']); ?></td>
                                                <td><?php echo htmlspecialchars(mb_substr($issue['description'], 0, 50, 'UTF-8')) . (mb_strlen($issue['description'], 'UTF-8') > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="priority-badge priority-<?php echo $issue['priority']; ?>">
                                                        <?php 
                                                            $priority_text = '';
                                                            switch($issue['priority']) {
                                                                case 'low': $priority_text = 'کم'; break;
                                                                case 'medium': $priority_text = 'متوسط'; break;
                                                                case 'high': $priority_text = 'زیاد'; break;
                                                                case 'critical': $priority_text = 'بحرانی'; break;
                                                                default: $priority_text = $issue['priority'];
                                                            }
                                                            echo $priority_text;
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $issue['status']; ?>">
                                                        <?php 
                                                            $status_text = '';
                                                            switch($issue['status']) {
                                                                case 'open': $status_text = 'باز'; break;
                                                                case 'in_progress': $status_text = 'در حال بررسی'; break;
                                                                case 'resolved': $status_text = 'حل شده'; break;
                                                                case 'closed': $status_text = 'بسته شده'; break;
                                                                default: $status_text = $issue['status'];
                                                            }
                                                            echo $status_text;
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($issue['reporter_name'] ?? 'نامشخص'); ?></td>
                                                <td><?php echo $issue['created_at']; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewIssueModal<?php echo $issue['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $issue['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- مودال نمایش جزئیات مشکل -->
                                          <!-- مودال نمایش جزئیات مشکل -->
<div class="modal fade" id="viewIssueModal<?php echo $issue['id']; ?>" tabindex="-1" aria-labelledby="viewIssueModalLabel<?php echo $issue['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewIssueModalLabel<?php echo $issue['id']; ?>">جزئیات مشکل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">کد پایانه:</label>
                                                                        <div><?php echo htmlspecialchars($issue['terminal_code']); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">نام پذیرنده:</label>
                                                                        <div><?php echo htmlspecialchars($issue['merchant_name']); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">اولویت:</label>
                                                                        <div>
                                                                            <span class="priority-badge priority-<?php echo $issue['priority']; ?>">
                                                                                <?php echo $priority_text; ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">وضعیت:</label>
                                                                        <div>
                                                                            <span class="status-badge status-<?php echo $issue['status']; ?>">
                                                                                <?php echo $status_text; ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">ثبت کننده:</label>
                                                                        <div><?php echo htmlspecialchars($issue['reporter_name'] ?? 'نامشخص'); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold">تاریخ ثبت:</label>
                                                                        <div><?php echo $issue['created_at']; ?></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="fw-bold">شرح مشکل:</label>
                                                                <div class="p-3 bg-light rounded">
                                                                    <?php echo nl2br(htmlspecialchars($issue['description'])); ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if(!empty($issue['comments']) && ($issue['status'] == 'resolved' || $issue['status'] == 'closed')): ?>
                <div class="mb-3">
                    <label class="fw-bold">توضیحات حل/بستن مشکل:</label>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($issue['comments'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                          <!-- مودال به‌روزرسانی وضعیت مشکل -->
<div class="modal fade" id="updateStatusModal<?php echo $issue['id']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel<?php echo $issue['id']; ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel<?php echo $issue['id']; ?>">به‌روزرسانی وضعیت مشکل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <form method="POST" action="admin_issues.php<?php echo !empty($_SERVER['QUERY_STRING']) ? "?".$_SERVER['QUERY_STRING'] : ""; ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="status<?php echo $issue['id']; ?>" class="form-label">وضعیت جدید:</label>
                        <select name="status" id="status<?php echo $issue['id']; ?>" class="form-select" onchange="toggleCommentsField(this, 'commentsDiv<?php echo $issue['id']; ?>')">
                            <option value="open" <?php echo $issue['status'] == 'open' ? 'selected' : ''; ?>>باز</option>
                            <option value="in_progress" <?php echo $issue['status'] == 'in_progress' ? 'selected' : ''; ?>>در حال بررسی</option>
                            <option value="resolved" <?php echo $issue['status'] == 'resolved' ? 'selected' : ''; ?>>حل شده</option>
                            <option value="closed" <?php echo $issue['status'] == 'closed' ? 'selected' : ''; ?>>بسته شده</option>
                        </select>
                    </div>
                    
                    <!-- فیلد توضیحات - به صورت پیش‌فرض مخفی -->
                    <div id="commentsDiv<?php echo $issue['id']; ?>" class="mb-3" style="display: <?php echo ($issue['status'] == 'resolved' || $issue['status'] == 'closed') ? 'block' : 'none'; ?>;">
                        <label for="comments<?php echo $issue['id']; ?>" class="form-label">توضیحات:</label>
                        <textarea name="comments" id="comments<?php echo $issue['id']; ?>" class="form-control" rows="3" placeholder="توضیحات خود را در مورد حل یا بستن این مشکل وارد کنید..."><?php echo $issue['comments']; ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>توجه:</strong> با انتخاب وضعیت "حل شده"، تاریخ حل شدن مشکل به طور خودکار ثبت می‌شود.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="update_status" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- صفحه‌بندی -->
                        <?php if($total_pages > 1): ?>
                            <nav aria-label="صفحه‌بندی">
                                <ul class="pagination justify-content-center">
                                <?php if($page > 1): ?>
    <li class="page-item">
        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?><?php echo !empty($priority_filter) ? '&priority='.$priority_filter : ''; ?><?php echo !empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : ''; ?>" aria-label="قبلی">
            <span aria-hidden="true">&laquo;</span>
        </a>
    </li>
<?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' 
                                            . (!empty($status_filter) ? '&status='.$status_filter : '') 
                                            . (!empty($priority_filter) ? '&priority='.$priority_filter : '') 
                                            . '">1</a></li>';
                                        if($start_page > 2) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                    }
                                    
                                    for($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">'
                                            . '<a class="page-link" href="?page=' . $i 
                                            . (!empty($status_filter) ? '&status='.$status_filter : '') 
                                            . (!empty($priority_filter) ? '&priority='.$priority_filter : '') 
                                            . '">' . $i . '</a>'
                                            . '</li>';
                                    }
                                    
                                    if($end_page < $total_pages) {
                                        if($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages 
                                            . (!empty($status_filter) ? '&status='.$status_filter : '') 
                                            . (!empty($priority_filter) ? '&priority='.$priority_filter : '') 
                                            . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if($page < $total_pages): ?>
    <li class="page-item">
        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?><?php echo !empty($priority_filter) ? '&priority='.$priority_filter : ''; ?><?php echo !empty($terminal_code_filter) ? '&terminal_code='.$terminal_code_filter : ''; ?>" aria-label="بعدی">
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
    <script>
// تابع برای نمایش/مخفی کردن فیلد توضیحات
function toggleCommentsField(selectElement, commentsId) {
    const commentsDiv = document.getElementById(commentsId);
    const selectedValue = selectElement.value;
    
    // فقط در صورت انتخاب "حل شده" یا "بسته شده" نمایش داده شود
    if (selectedValue === 'resolved' || selectedValue === 'closed') {
        commentsDiv.style.display = 'block';
    } else {
        commentsDiv.style.display = 'none';
    }
}

// اجرای تابع برای تمام فرم‌ها در هنگام بارگذاری صفحه
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(select => {
        const issueId = select.id.replace('status', '');
        toggleCommentsField(select, 'commentsDiv' + issueId);
    });
});
</script>
</body>
</html>