<?php
// process_roll_confirmation.php - پردازش تاییدیه‌های رول کاغذ

include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    header('Content-Type: application/json'); //
    echo json_encode([ //
        'success' => false, //
        'message' => 'لطفاً ابتدا وارد سیستم شوید' //
    ]); //
    exit; //
}

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id']; //

// مدیریت درخواست‌های GET
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) { //
    header('Content-Type: application/json; charset=utf-8'); //
    
    switch($_GET['action']) { //
        case 'get_pending_assignments': //
            getPendingAssignments($user_id); //
            break;
            
        case 'get_confirmed_assignments': //
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5; //
            getConfirmedAssignments($user_id, $limit); //
            break;
            
        default: //
            echo json_encode([ //
                'success' => false, //
                'message' => 'عملیات نامعتبر' //
            ]); //
    }
}

// مدیریت درخواست‌های POST
if ($_SERVER["REQUEST_METHOD"] == "POST") { //
    header('Content-Type: application/json; charset=utf-8'); //
    
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطای امنیتی: دسترسی غیرمجاز' //
        ]); //
        exit; //
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_assignment') { //
        confirmAssignment(); //
    } else { //
        // برای سازگاری با کد قدیمی
        confirmAssignment(); //
    }
}

// تابع دریافت تخصیص‌های تایید نشده
function getPendingAssignments($user_id) { //
    global $conn; //
    
    try { //
        $sql = "SELECT ra.*, ac.description as assigned_by_name 
                FROM roll_assignments ra 
                LEFT JOIN access_codes ac ON ra.assigned_by = ac.id 
                WHERE ra.user_id = ? AND ra.confirmed = 0 
                ORDER BY ra.created_at DESC"; //
        
        if ($stmt = $conn->prepare($sql)) { //
            $stmt->bind_param("i", $user_id); //
            $stmt->execute(); //
            $result = $stmt->get_result(); //
            
            $assignments = []; //
            while ($row = $result->fetch_assoc()) { //
                // تبدیل تاریخ به شمسی
                $row['assign_date'] = to_jalali($row['assign_date']); //
                $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
                $assignments[] = $row; //
            }
            
            $stmt->close(); //
            
            echo json_encode([ //
                'success' => true, //
                'assignments' => $assignments //
            ]); //
        } else { //
            throw new Exception("خطا در آماده‌سازی پرس‌وجو"); //
        }
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در دریافت تخصیص‌های تایید نشده: ' . $e->getMessage() //
        ]); //
    }
}

// تابع دریافت تخصیص‌های تایید شده
function getConfirmedAssignments($user_id, $limit = 5) { //
    global $conn; //
    
    try { //
        $sql = "SELECT ra.*, ac.description as assigned_by_name 
                FROM roll_assignments ra 
                LEFT JOIN access_codes ac ON ra.assigned_by = ac.id 
                WHERE ra.user_id = ? AND ra.confirmed = 1 
                ORDER BY ra.confirm_date DESC 
                LIMIT ?"; //
        
        if ($stmt = $conn->prepare($sql)) { //
            $stmt->bind_param("ii", $user_id, $limit); //
            $stmt->execute(); //
            $result = $stmt->get_result(); //
            
            $assignments = []; //
            while ($row = $result->fetch_assoc()) { //
                // تبدیل تاریخ به شمسی
                $row['assign_date'] = to_jalali($row['assign_date']); //
                $row['confirm_date'] = $row['confirm_date'] ? datetime_to_jalali($row['confirm_date'], 'Y/m/d H:i') : '-'; //
                $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
                $assignments[] = $row; //
            }
            
            $stmt->close(); //
            
            echo json_encode([ //
                'success' => true, //
                'assignments' => $assignments //
            ]); //
        } else { //
            throw new Exception("خطا در آماده‌سازی پرس‌وجو"); //
        }
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در دریافت تخصیص‌های تایید شده: ' . $e->getMessage() //
        ]); //
    }
}

// تابع تایید تخصیص
function confirmAssignment() { //
    global $conn; //
    
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0; //
    $user_id = $_SESSION['user_id']; //
    
    if ($assignment_id <= 0) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'شناسه تخصیص نامعتبر است' //
        ]); //
        return; //
    }
    
    try { //
        // بررسی اینکه تخصیص برای این کاربر است
        $check_sql = "SELECT * FROM roll_assignments WHERE id = ? AND user_id = ? AND confirmed = 0"; //
        
        if ($check_stmt = $conn->prepare($check_sql)) { //
            $check_stmt->bind_param("ii", $assignment_id, $user_id); //
            $check_stmt->execute(); //
            $result = $check_stmt->get_result(); //
            
            if ($result->num_rows == 0) { //
                $check_stmt->close(); //
                echo json_encode([ //
                    'success' => false, //
                    'message' => 'تخصیص یافت نشد یا قبلاً تایید شده است' //
                ]); //
                return; //
            }
            
            $check_stmt->close(); //
        } else { //
            throw new Exception("خطا در بررسی تخصیص"); //
        }
        
        // به‌روزرسانی وضعیت تایید
        $update_sql = "UPDATE roll_assignments SET confirmed = 1, confirm_date = NOW() WHERE id = ?"; //
        
        if ($update_stmt = $conn->prepare($update_sql)) { //
            $update_stmt->bind_param("i", $assignment_id); //
            
            if ($update_stmt->execute()) { //
                $update_stmt->close(); //
                
                // ثبت در لاگ
                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                            VALUES (?, 'confirm_roll', ?, ?, NOW())"; //
                
                if ($log_stmt = $conn->prepare($log_sql)) { //
                    $ip = $_SERVER['REMOTE_ADDR']; //
                    $log_stmt->bind_param("iis", $user_id, $assignment_id, $ip); //
                    $log_stmt->execute(); //
                    $log_stmt->close(); //
                }
                
                echo json_encode([ //
                    'success' => true, //
                    'message' => 'تخصیص رول کاغذ با موفقیت تایید شد' //
                ]); //
            } else { //
                throw new Exception("خطا در به‌روزرسانی تخصیص"); //
            }
        } else { //
            throw new Exception("خطا در آماده‌سازی پرس‌وجو به‌روزرسانی"); //
        }
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در تایید تخصیص: ' . $e->getMessage() //
        ]); //
    }
}