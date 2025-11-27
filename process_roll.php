<?php
// process_roll.php - پردازش عملیات مربوط به رول‌های کاغذی
include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی اینکه آیا کاربر وارد شده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    header('Content-Type: application/json'); //
    echo json_encode([ //
        'success' => false, //
        'message' => 'خطای دسترسی: لطفاً ابتدا وارد شوید.' //
    ]); //
    exit; //
}

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// تابع کمکی برای تبدیل تاریخ میلادی به شمسی
// این تابع از date_helper.php شامل می شود و دیگر نیازی به تعریف مجدد نیست.
// function convert_to_jalali($date_time) { ... }

// تابع کمکی برای بررسی اعتبار تاریخ
// این تابع به date_helper.php منتقل شده است.
// function is_valid_date($date_string) { ... }

// بررسی عملیات درخواستی
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : ''; //

switch($action) { //
    case 'submitRoll': //
        // ثبت رول جدید
        submitRoll(); //
        break;
        
    case 'getRollHistory': //
        // دریافت تاریخچه رول‌های یک پایانه
        getRollHistory(); //
        break;
        
    default: //
        // عملیات نامعتبر
        header('Content-Type: application/json'); //
        echo json_encode([ //
            'success' => false, //
            'message' => 'عملیات نامعتبر' //
        ]); //
        break;
}

/**
 * ثبت رول جدید
 */
function submitRoll() { //
    global $conn; //
    
    // بررسی روش درخواست
    if ($_SERVER['REQUEST_METHOD'] != 'POST') { //
        outputJSON(false, 'روش درخواست نامعتبر است.'); //
        return; //
    }
    
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) { //
        outputJSON(false, 'خطای امنیتی: دسترسی غیرمجاز'); //
        return; //
    }
    
    // دریافت پارامترهای ورودی
    $terminal_id = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0; //
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0; //
    $delivery_date = isset($_POST['delivery_date']) ? sanitize_input($_POST['delivery_date']) : ''; //
    $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : ''; //
    
    // بررسی اعتبار داده‌ها
    if ($terminal_id <= 0) { //
        outputJSON(false, 'شناسه پایانه نامعتبر است.'); //
        return; //
    }
    
    if ($quantity <= 0) { //
        outputJSON(false, 'لطفاً تعداد رول را به درستی وارد کنید.'); //
        return; //
    }
    
    // تاریخ delivery_date در اینجا از فرم HTML با input type="date" می آید که میلادی است.
    // لذا نیازی به تبدیل از شمسی به میلادی نیست. فقط اعتبار سنجی میلادی نیاز دارد.
    if (empty($delivery_date) || !is_valid_gregorian_date($delivery_date)) { //
        outputJSON(false, 'لطفاً تاریخ تحویل معتبر را مشخص کنید.'); //
        return; //
    }
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) { //
        outputJSON(false, 'خطا در اتصال به پایگاه داده.'); //
        return; //
    }
    
    try { //
        // بررسی وجود پایانه
        $check_sql = "SELECT id FROM terminals WHERE id = ? LIMIT 1"; //
        
        if ($check_stmt = $conn->prepare($check_sql)) { //
            $check_stmt->bind_param("i", $terminal_id); //
            $check_stmt->execute(); //
            $check_result = $check_stmt->get_result(); //
            
            if ($check_result->num_rows === 0) { //
                $check_stmt->close(); //
                outputJSON(false, 'پایانه مورد نظر یافت نشد.'); //
                return; //
            }
            
            $check_stmt->close(); //
        } else { //
            throw new Exception($conn->error); //
        }
        
        // شروع تراکنش
        $conn->begin_transaction(); //
        
        try { //
            // ثبت رول در جدول paper_rolls
            $sql = "INSERT INTO paper_rolls (terminal_id, quantity, delivery_date, description, user_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())"; //
            
            if ($stmt = $conn->prepare($sql)) { //
                $user_id = $_SESSION['user_id']; //
                
                $stmt->bind_param("iissi", $terminal_id, $quantity, $delivery_date, $description, $user_id); //
                
                if (!$stmt->execute()) { //
                    throw new Exception($stmt->error); //
                }
                
                $roll_id = $stmt->insert_id; //
                $stmt->close(); //

                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                            VALUES (?, 'submit_roll', ?, ?, NOW())"; //
                
                if ($log_stmt = $conn->prepare($log_sql)) { //
                    $ip = $_SERVER['REMOTE_ADDR']; //
                    $log_stmt->bind_param("iis", $user_id, $roll_id, $ip); //
                    
                    if (!$log_stmt->execute()) { //
                        throw new Exception($log_stmt->error); //
                    }
                    
                    $log_stmt->close(); //
                } else { //
                    throw new Exception($conn->error); //
                }
                
                // تایید تراکنش
                $conn->commit(); //
                
                // دریافت اطلاعات کاربر ثبت کننده
                $user_info = []; //
                $user_sql = "SELECT description FROM access_codes WHERE id = ? LIMIT 1"; //
                if ($user_stmt = $conn->prepare($user_sql)) { //
                    $user_stmt->bind_param("i", $user_id); //
                    $user_stmt->execute(); //
                    $user_result = $user_stmt->get_result(); //
                    if ($user_row = $user_result->fetch_assoc()) { //
                        $user_info = $user_row; //
                    }
                    $user_stmt->close(); //
                }
                
                // دریافت تعداد واقعی رول از جدول paper_rolls برای نمایش (بدون تغییر در جدول terminals)
                $get_total_sql = "SELECT SUM(quantity) as total FROM paper_rolls WHERE terminal_id = ?"; //
                $total_rolls = 0; //
                if ($total_stmt = $conn->prepare($get_total_sql)) { //
                    $total_stmt->bind_param("i", $terminal_id); //
                    $total_stmt->execute(); //
                    $total_result = $total_stmt->get_result(); //
                    if ($total_row = $total_result->fetch_assoc()) { //
                        $total_rolls = (int)$total_row['total']; //
                    }
                    $total_stmt->close(); //
                }
                
             // در process_roll.php، تابع submitRoll()
outputJSON(true, 'رول با موفقیت ثبت شد.', [ //
    'roll_id' => $roll_id, //
    'total_rolls' => $total_rolls, //
    'user_name' => $user_info['description'] ?? 'نامشخص' //
]); //
            } else { //
                throw new Exception($conn->error); //
            }
        } catch (Exception $e) { //
            // لغو تراکنش در صورت خطا
            $conn->rollback(); //
            throw $e; // ارسال استثنا به بلوک catch بیرونی //
        }
    } catch (Exception $e) { //
        error_log("SQL Error in submitRoll: " . $e->getMessage()); //
        outputJSON(false, 'خطا در ثبت رول: ' . $e->getMessage()); //
    }
}

/**
 * دریافت تاریخچه رول‌های یک پایانه
 */
function getRollHistory() { //
    global $conn; //
    
    // دریافت شناسه پایانه
    $terminal_id = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0; //
    
    if ($terminal_id <= 0) { //
        outputJSON(false, 'شناسه پایانه نامعتبر است.'); //
        return; //
    }
    
    // بررسی اتصال به پایگاه داده
    if (!$conn) { //
        outputJSON(false, 'خطا در اتصال به پایگاه داده.'); //
        return; //
    }
    
    try { //
        // بررسی وجود پایانه
        $check_sql = "SELECT id FROM terminals WHERE id = ? LIMIT 1"; //
        
        if ($check_stmt = $conn->prepare($check_sql)) { //
            $check_stmt->bind_param("i", $terminal_id); //
            $check_stmt->execute(); //
            $check_result = $check_stmt->get_result(); //
            
            if ($check_result->num_rows === 0) { //
                $check_stmt->close(); //
                outputJSON(false, 'پایانه مورد نظر یافت نشد.'); //
                return; //
            }
            
            $check_stmt->close(); //
        } else { //
            throw new Exception($conn->error); //
        }
        
        // دریافت تاریخچه رول‌ها
        $sql = "SELECT p.*, a.description as user_name
                FROM paper_rolls p
                LEFT JOIN access_codes a ON p.user_id = a.id
                WHERE p.terminal_id = ?
                ORDER BY p.delivery_date DESC, p.created_at DESC"; //
        
        if ($stmt = $conn->prepare($sql)) { //
            $stmt->bind_param("i", $terminal_id); //
            $stmt->execute(); //
            $result = $stmt->get_result(); //
            
            $rolls = []; //
            while ($row = $result->fetch_assoc()) { //
                // تبدیل تاریخ‌ها به شمسی
                if (isset($row['delivery_date'])) { //
                    $row['delivery_date'] = to_jalali($row['delivery_date']); //
                }
                
                if (isset($row['created_at'])) { //
                    $row['created_at'] = datetime_to_jalali($row['created_at']); //
                }
                
                $rolls[] = $row; //
            }
            
            $stmt->close(); //
            
            outputJSON(true, '', ['rolls' => $rolls]); //
        } else { //
            throw new Exception($conn->error); //
        }
    } catch (Exception $e) { //
        error_log("SQL Error in getRollHistory: " . $e->getMessage()); //
        outputJSON(false, 'خطا در دریافت تاریخچه رول‌ها: ' . $e->getMessage()); //
    }
}

/**
 * ارسال پاسخ JSON
 */
function outputJSON($success, $message = '', $data = []) { //
    header('Content-Type: application/json'); //
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); //
    
    $response = [ //
        'success' => $success, //
        'message' => $message //
    ]; //
    
    if (!empty($data)) { //
        $response = array_merge($response, $data); //
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE); //
    exit; //
}