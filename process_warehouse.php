<?php
// process_warehouse.php - پردازش درخواست‌های مدیریت انبار
include 'config.php';
include 'jdf.php';

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

// بررسی اینکه کاربر مسئول انبار باشد
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مسئول انبار"){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این بخش را ندارید']);
    exit;
}

// تنظیم هدر خروجی به JSON
header('Content-Type: application/json');

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id'];

// بررسی اقدام مورد نظر
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'assign_roll':
        // بررسی CSRF
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'خطای امنیتی: دسترسی غیرمجاز']);
            exit;
        }

        // بررسی اتصال پایگاه داده
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده']);
            exit;
        }

        // تخصیص رول کاغذ به کاربر
        if (!isset($_POST['user_id']) || !isset($_POST['quantity']) || !isset($_POST['assign_date'])) {
            echo json_encode(['success' => false, 'message' => 'لطفاً تمام فیلدهای ضروری را تکمیل کنید']);
            exit;
        }
        
        $target_user_id = intval($_POST['user_id']);
        $quantity = intval($_POST['quantity']);
        $assign_date = sanitize_input($_POST['assign_date']);
        $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
        
        // تبدیل تاریخ شمسی به میلادی
        $date_parts = explode('/', $assign_date);
        if(count($date_parts) == 3) {
            list($year, $month, $day) = $date_parts;
            list($g_year, $g_month, $g_day) = jalali_to_gregorian($year, $month, $day);
            $gregorian_date = sprintf('%04d-%02d-%02d', $g_year, $g_month, $g_day);
        } else {
            echo json_encode(['success' => false, 'message' => 'فرمت تاریخ نامعتبر است']);
            exit;
        }
        
        // اضافه کردن تخصیص به پایگاه داده
        $sql = "INSERT INTO roll_assignments (user_id, quantity, assign_date, description, assigned_by) 
                VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iissi", $target_user_id, $quantity, $gregorian_date, $description, $user_id);
            if ($stmt->execute()) {
                // ثبت لاگ فعالیت
                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                           VALUES (?, 'assign_roll', ?, ?, NOW())";
                if ($log_stmt = $conn->prepare($log_sql)) {
                    $assignment_id = $stmt->insert_id;
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iis", $user_id, $assignment_id, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                echo json_encode(['success' => true, 'message' => 'تخصیص رول کاغذ با موفقیت انجام شد']);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در ثبت تخصیص: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در آماده‌سازی درخواست: ' . $conn->error]);
        }
        break;
    
    case 'get_assignments':
        // دریافت لیست تخصیص‌ها
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        
        $user_filter = isset($_GET['user_id']) && !empty($_GET['user_id']) ? intval($_GET['user_id']) : null;
        $status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : null;
        
        // ساخت شرط‌های فیلتر
        $where_clause = "";
        $params = [];
        $types = "";
        
        if ($user_filter !== null) {
            $where_clause .= " WHERE a.user_id = ?";
            $params[] = $user_filter;
            $types .= "i";
        }
        
        if ($status_filter !== null) {
            $where_clause .= empty($where_clause) ? " WHERE a.confirmed = ?" : " AND a.confirmed = ?";
            $params[] = $status_filter;
            $types .= "i";
        }
        
        // شمارش کل رکوردها
        $count_sql = "SELECT COUNT(*) as total FROM roll_assignments a" . $where_clause;
        $total_records = 0;
        
        if ($count_stmt = $conn->prepare($count_sql)) {
            if (!empty($params)) {
                $count_stmt->bind_param($types, ...$params);
            }
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            if ($count_row = $count_result->fetch_assoc()) {
                $total_records = $count_row['total'];
            }
            $count_stmt->close();
        }
        
        // دریافت رکوردها
        $sql = "SELECT a.*, 
                  u1.description as user_name, 
                  u2.description as assigner_name 
                FROM roll_assignments a 
                LEFT JOIN access_codes u1 ON a.user_id = u1.id 
                LEFT JOIN access_codes u2 ON a.assigned_by = u2.id"
                . $where_clause . 
                " ORDER BY a.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $assignments = [];
        
        if ($stmt = $conn->prepare($sql)) {
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // تبدیل تاریخ‌ها به شمسی
                    $row['assign_date'] = jdate('Y/m/d', strtotime($row['assign_date']), '', 'Asia/Tehran', 'fa');
                    $row['created_at'] = jdate('Y/m/d H:i', strtotime($row['created_at']), '', 'Asia/Tehran', 'fa');
                    
                    if ($row['confirm_date']) {
                        $row['confirm_date'] = jdate('Y/m/d H:i', strtotime($row['confirm_date']), '', 'Asia/Tehran', 'fa');
                    }
                    
                    $assignments[] = $row;
                }
            }
            $stmt->close();
        }
        
        // محاسبه تعداد صفحات
        $total_pages = ceil($total_records / $limit);
        
        echo json_encode([
            'success' => true, 
            'assignments' => $assignments, 
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $total_records,
                'total_pages' => $total_pages
            ]
        ]);
        break;
    
    case 'get_report':
        // گزارش‌گیری از تخصیص‌ها
        $from_date = isset($_GET['from_date']) && !empty($_GET['from_date']) ? sanitize_input($_GET['from_date']) : null;
        $to_date = isset($_GET['to_date']) && !empty($_GET['to_date']) ? sanitize_input($_GET['to_date']) : null;
        $user_filter = isset($_GET['user_id']) && !empty($_GET['user_id']) ? intval($_GET['user_id']) : null;
        $status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : null;
        
        // تبدیل تاریخ‌ها به میلادی
        $gregorian_from_date = null;
        $gregorian_to_date = null;
        
        if ($from_date) {
            $from_parts = explode('/', $from_date);
            if (count($from_parts) == 3) {
                list($f_year, $f_month, $f_day) = $from_parts;
                list($g_f_year, $g_f_month, $g_f_day) = jalali_to_gregorian($f_year, $f_month, $f_day);
                $gregorian_from_date = sprintf('%04d-%02d-%02d', $g_f_year, $g_f_month, $g_f_day);
            }
        }
        
        if ($to_date) {
            $to_parts = explode('/', $to_date);
            if (count($to_parts) == 3) {
                list($t_year, $t_month, $t_day) = $to_parts;
                list($g_t_year, $g_t_month, $g_t_day) = jalali_to_gregorian($t_year, $t_month, $t_day);
                $gregorian_to_date = sprintf('%04d-%02d-%02d', $g_t_year, $g_t_month, $g_t_day);
            }
        }
        
        // ساخت شرط‌های فیلتر
        $where_clause = [];
        $params = [];
        $types = "";
        
        if ($gregorian_from_date) {
            $where_clause[] = "a.assign_date >= ?";
            $params[] = $gregorian_from_date;
            $types .= "s";
        }
        
        if ($gregorian_to_date) {
            $where_clause[] = "a.assign_date <= ?";
            $params[] = $gregorian_to_date;
            $types .= "s";
        }
        
        if ($user_filter !== null) {
            $where_clause[] = "a.user_id = ?";
            $params[] = $user_filter;
            $types .= "i";
        }
        
        if ($status_filter !== null) {
            $where_clause[] = "a.confirmed = ?";
            $params[] = $status_filter;
            $types .= "i";
        }
        
        $where_sql = empty($where_clause) ? "" : " WHERE " . implode(" AND ", $where_clause);
        
        // دریافت رکوردها
        $sql = "SELECT a.*, 
                  u1.description as user_name, 
                  u1.code as user_code,
                  u2.description as assigner_name 
                FROM roll_assignments a 
                LEFT JOIN access_codes u1 ON a.user_id = u1.id 
                LEFT JOIN access_codes u2 ON a.assigned_by = u2.id"
                . $where_sql . 
                " ORDER BY a.assign_date DESC, a.created_at DESC";
        
        $assignments = [];
        
        if ($stmt = $conn->prepare($sql)) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // تبدیل تاریخ‌ها به شمسی
                    $row['assign_date'] = jdate('Y/m/d', strtotime($row['assign_date']), '', 'Asia/Tehran', 'fa');
                    $row['created_at'] = jdate('Y/m/d H:i', strtotime($row['created_at']), '', 'Asia/Tehran', 'fa');
                    
                    if ($row['confirm_date']) {
                        $row['confirm_date'] = jdate('Y/m/d H:i', strtotime($row['confirm_date']), '', 'Asia/Tehran', 'fa');
                    }
                    
                    $assignments[] = $row;
                }
            }
            $stmt->close();
        }
        
        // محاسبه آمار گزارش
        $stats = [
            'total_assignments' => count($assignments),
            'total_rolls' => 0,
            'confirmed_assignments' => 0,
            'pending_assignments' => 0,
        ];
        
        foreach ($assignments as $assignment) {
            $stats['total_rolls'] += $assignment['quantity'];
            if ($assignment['confirmed']) {
                $stats['confirmed_assignments']++;
            } else {
                $stats['pending_assignments']++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'assignments' => $assignments,
            'stats' => $stats
        ]);
        break;
   
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']);
        break;
}
?>