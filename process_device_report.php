<?php
// process_device_report.php - پردازش گزارش دستگاه خراب توسط کاربران
include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    $_SESSION['error'] = "لطفاً ابتدا وارد سیستم شوید."; //
    header("location: index.php"); //
    exit; //
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800); //

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id']; //

// تابع کمکی برای تبدیل تاریخ میلادی به شمسی
// این تابع از date_helper.php شامل می شود و دیگر نیازی به تعریف مجدد نیست.
// function convert_to_jalali($date_time) { ... }

// بررسی اگر فرم ارسال شده است
if ($_SERVER["REQUEST_METHOD"] == "POST") { //
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) { //
        $_SESSION['error'] = "خطای امنیتی: دسترسی غیرمجاز"; //
        header("location: device_report.php"); //
        exit; //
    }
    
    // بررسی اتصال پایگاه داده
    if (!$conn) { //
        $_SESSION['error'] = "خطا در اتصال به پایگاه داده"; //
        header("location: device_report.php"); //
        exit; //
    }
    
    // دریافت داده‌های فرم
    $terminal_serial = sanitize_input($_POST['terminal_serial']); //
    $adapter_serial = isset($_POST['adapter_serial']) ? sanitize_input($_POST['adapter_serial']) : ""; //
    $device_status = $_POST['device_status']; //
    $damage_description = sanitize_input($_POST['damage_description']); //
    
    // بررسی‌های اعتبارسنجی
    if (empty($terminal_serial)) { //
        $_SESSION['error'] = "لطفاً سریال دستگاه را وارد کنید"; //
        header("location: device_report.php"); //
        exit; //
    }
    
    // تنظیم مقادیر بر اساس وضعیت دستگاه
    if ($device_status === 'healthy') { //
        // برای دستگاه سالم
        $is_terminal_damaged = 0; //
        $is_adapter_damaged = 0; //
        $status = 'healthy'; //
        // شرح برای دستگاه سالم اختیاری است
        if (empty($damage_description)) { //
            $damage_description = "دستگاه سالم است"; //
        }
    } else { //
        // برای دستگاه خراب
        $is_terminal_damaged = isset($_POST['is_terminal_damaged']) ? 1 : 0; //
        $is_adapter_damaged = isset($_POST['is_adapter_damaged']) ? 1 : 0; //
        $status = 'pending'; //
        
        // برای دستگاه خراب باید حداقل یک نوع خرابی انتخاب شود
        if ($is_terminal_damaged == 0 && $is_adapter_damaged == 0) { //
            $_SESSION['error'] = "لطفاً حداقل یک نوع خرابی را انتخاب کنید"; //
            header("location: device_report.php"); //
            exit; //
        }
        
        // برای دستگاه خراب شرح خرابی اجباری است
        if (empty($damage_description)) { //
            $_SESSION['error'] = "لطفاً شرح خرابی را وارد کنید"; //
            header("location: device_report.php"); //
            exit; //
        }
    }
    
    // اضافه کردن گزارش به پایگاه داده
    $sql = "INSERT INTO device_repairs (terminal_serial, adapter_serial, is_terminal_damaged, is_adapter_damaged, damage_description, reported_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)"; //
    
    if ($stmt = $conn->prepare($sql)) { //
        $stmt->bind_param("ssiisis", $terminal_serial, $adapter_serial, $is_terminal_damaged, $is_adapter_damaged, $damage_description, $user_id, $status); //
        
        if ($stmt->execute()) { //
            $repair_id = $stmt->insert_id; //
            
            // ثبت در تاریخچه تغییرات
            $history_sql = "INSERT INTO repair_history (repair_id, status, notes, user_id) 
                           VALUES (?, ?, ?, ?)"; //
            
            if ($history_stmt = $conn->prepare($history_sql)) { //
                if ($device_status === 'healthy') { //
                    $notes = "گزارش دستگاه سالم ثبت شد."; //
                } else { //
                    $notes = "گزارش خرابی دستگاه ثبت شد."; //
                }
                $history_stmt->bind_param("issi", $repair_id, $status, $notes, $user_id); //
                $history_stmt->execute(); //
                $history_stmt->close(); //
            }
            
            // ثبت لاگ فعالیت
            $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                       VALUES (?, 'report_repair', ?, ?, NOW())"; //
            
            if ($log_stmt = $conn->prepare($log_sql)) { //
                $ip = $_SERVER['REMOTE_ADDR']; //
                $log_stmt->bind_param("iis", $user_id, $repair_id, $ip); //
                $log_stmt->execute(); //
                $log_stmt->close(); //
            }
            
            if ($device_status === 'healthy') { //
                $_SESSION['success'] = "گزارش دستگاه سالم با موفقیت ثبت شد"; //
            } else { //
                $_SESSION['success'] = "گزارش خرابی دستگاه با موفقیت ثبت شد"; //
            }
        } else { //
            $_SESSION['error'] = "خطا در ثبت گزارش: " . $stmt->error; //
        }
        
        $stmt->close(); //
    } else { //
        $_SESSION['error'] = "خطا در آماده‌سازی درخواست: " . $conn->error; //
    }
    
    header("location: device_report.php"); //
    exit; //
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) { //
    // برای درخواست‌های GET و AJAX
    header('Content-Type: application/json'); //
    
    $action = $_GET['action']; //
    
    switch ($action) { //
        case 'get_user_reports': //
            // دریافت گزارش‌های کاربر
            $sql = "SELECT r.*, h.created_at as last_update_time, h.status as last_status, h.notes as last_notes 
                    FROM device_repairs r 
                    LEFT JOIN (
                        SELECT repair_id, MAX(created_at) as max_date 
                        FROM repair_history 
                        GROUP BY repair_id
                    ) latest ON r.id = latest.repair_id
                    LEFT JOIN repair_history h ON latest.repair_id = h.repair_id AND latest.max_date = h.created_at
                    WHERE r.reported_by = ? 
                    ORDER BY r.created_at DESC"; //
            
            $reports = []; //
            
            if ($stmt = $conn->prepare($sql)) { //
                $stmt->bind_param("i", $user_id); //
                
                if ($stmt->execute()) { //
                    $result = $stmt->get_result(); //
                    while ($row = $result->fetch_assoc()) { //
                        // تبدیل تاریخ‌ها به شمسی
                        $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
                        
                        if ($row['repair_date']) { //
                            $row['repair_date'] = datetime_to_jalali($row['repair_date'], 'Y/m/d H:i'); //
                        }
                        
                        if ($row['last_update_time']) { //
                            $row['last_update_time'] = datetime_to_jalali($row['last_update_time'], 'Y/m/d H:i'); //
                        }
                        
                        $reports[] = $row; //
                    }
                }
                $stmt->close(); //
            }
            
            echo json_encode([ //
                'success' => true, //
                'reports' => $reports //
            ]); //
            break;
        
        case 'get_report_details': //
            // دریافت جزئیات یک گزارش
            if (!isset($_GET['report_id']) || empty($_GET['report_id'])) { //
                echo json_encode(['success' => false, 'message' => 'شناسه گزارش مشخص نشده است']); //
                exit; //
            }
            
            $report_id = intval($_GET['report_id']); //
            
            // بررسی مالکیت گزارش
            $check_sql = "SELECT id FROM device_repairs WHERE id = ? AND reported_by = ?"; //
            $report_exists = false; //
            
            if ($check_stmt = $conn->prepare($check_sql)) { //
                $check_stmt->bind_param("ii", $report_id, $user_id); //
                $check_stmt->execute(); //
                $check_result = $check_stmt->get_result(); //
                $report_exists = ($check_result->num_rows > 0); //
                $check_stmt->close(); //
            }
            
            if (!$report_exists) { //
                echo json_encode(['success' => false, 'message' => 'گزارش مورد نظر یافت نشد یا شما دسترسی به آن ندارید']); //
                exit; //
            }
            
            // دریافت اطلاعات گزارش
            $sql = "SELECT r.*, 
                      u1.description as reporter_name,
                      u2.description as technician_name
                    FROM device_repairs r 
                    LEFT JOIN access_codes u1 ON r.reported_by = u1.id 
                    LEFT JOIN access_codes u2 ON r.technician_id = u2.id
                    WHERE r.id = ?"; //
            
            $report = null; //
            
            if ($stmt = $conn->prepare($sql)) { //
                $stmt->bind_param("i", $report_id); //
                
                if ($stmt->execute()) { //
                    $result = $stmt->get_result(); //
                    if ($row = $result->fetch_assoc()) { //
                        // تبدیل تاریخ‌ها به شمسی
                        $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
                        
                        if ($row['repair_date']) { //
                            $row['repair_date'] = datetime_to_jalali($row['repair_date'], 'Y/m/d H:i'); //
                        }
                        
                        if ($row['updated_at']) { //
                            $row['updated_at'] = datetime_to_jalali($row['updated_at'], 'Y/m/d H:i'); //
                        }
                        
                        $report = $row; //
                    }
                }
                $stmt->close(); //
            }
            
            if (!$report) { //
                echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات گزارش']); //
                exit; //
            }
            
            // دریافت تاریخچه تغییرات
            $sql = "SELECT h.*, u.description as user_name 
                    FROM repair_history h 
                    LEFT JOIN access_codes u ON h.user_id = u.id 
                    WHERE h.repair_id = ? 
                    ORDER BY h.created_at ASC"; //
            
            $history = []; //
            
            if ($stmt = $conn->prepare($sql)) { //
                $stmt->bind_param("i", $report_id); //
                
                if ($stmt->execute()) { //
                    $result = $stmt->get_result(); //
                    while ($row = $result->fetch_assoc()) { //
                        // تبدیل تاریخ‌ها به شمسی
                        $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
                        
                        $history[] = $row; //
                    }
                }
                $stmt->close(); //
            }
            
            echo json_encode([ //
                'success' => true, //
                'report' => $report, //
                'history' => $history //
            ]); //
            break;
        
        default: //
            echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']); //
            break;
    }
    
    exit; //
} else { //
    // در صورت دسترسی مستقیم به فایل
    header("location: device_report.php"); //
    exit; //
}