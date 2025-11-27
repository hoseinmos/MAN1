<?php
// process_repair.php - پردازش تعمیرات
include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    header('Content-Type: application/json'); //
    echo json_encode([ //
        'success' => false, //
        'message' => 'لطفاً ابتدا وارد سیستم شوید' //
    ]); //
    exit; //
}

// بررسی اینکه کاربر مسئول تعمیرات باشد
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مسئول تعمیرات"){ //
    header('Content-Type: application/json'); //
    echo json_encode([ //
        'success' => false, //
        'message' => 'شما اجازه دسترسی به این بخش را ندارید' //
    ]); //
    exit; //
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800); //

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id']; //

// مدیریت درخواست‌های GET
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) { //
    header('Content-Type: application/json; charset=utf-8'); //
    
    switch($_GET['action']) { //
        case 'get_pending_repairs': //
            getPendingRepairs(); //
            break;
            
        case 'get_repairs': //
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1; //
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; //
            $status = isset($_GET['status']) ? $_GET['status'] : ''; //
            getAllRepairs($page, $limit, $status); //
            break;
            
        case 'get_repair_details': //
            $repair_id = isset($_GET['repair_id']) ? intval($_GET['repair_id']) : 0; //
            getRepairDetails($repair_id); //
            break;
            
        case 'search_repairs': //
            searchRepairs(); //
            break;
            
        case 'get_report': //
            getRepairReport(); //
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
    
    // به‌روزرسانی وضعیت تعمیر
    if (isset($_POST['repair_id'])) { //
        updateRepairStatus(); //
    }
}

// دریافت تعمیرات در انتظار
function getPendingRepairs() { //
    global $conn; //
    
    try { //
        // بررسی اتصال پایگاه داده
        if (!$conn) { //
            throw new Exception('خطا در اتصال به پایگاه داده'); //
        }
        
        $sql = "SELECT dr.*, ac.description as reporter_name 
                FROM device_repairs dr 
                LEFT JOIN access_codes ac ON dr.reported_by = ac.id 
                WHERE dr.status = 'pending' 
                ORDER BY dr.created_at DESC"; //
        
        $result = $conn->query($sql); //
        
        $repairs = []; //
        while ($row = $result->fetch_assoc()) { //
            // تبدیل تاریخ به شمسی
            $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
            $repairs[] = $row; //
        }
        
        echo json_encode([ //
            'success' => true, //
            'repairs' => $repairs //
        ]); //
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در دریافت تعمیرات در انتظار: ' . $e->getMessage() //
        ]); //
    }
}

// دریافت همه تعمیرات
function getAllRepairs($page = 1, $limit = 10, $status = '') { //
    global $conn; //
    
    try { //
        // بررسی اتصال پایگاه داده
        if (!$conn) { //
            throw new Exception('خطا در اتصال به پایگاه داده'); //
        }
        
        $offset = ($page - 1) * $limit; //
        
        // شمارش کل رکوردها
        $count_sql = "SELECT COUNT(*) as total FROM device_repairs"; //
        if (!empty($status)) { //
            $count_sql .= " WHERE status = ?"; //
        }
        
        $count_stmt = $conn->prepare($count_sql); //
        if (!empty($status)) { //
            $count_stmt->bind_param("s", $status); //
        }
        $count_stmt->execute(); //
        $count_result = $count_stmt->get_result(); //
        $total_count = $count_result->fetch_assoc()['total']; //
        $count_stmt->close(); //
        
        // دریافت رکوردها
        $sql = "SELECT dr.*, ac.description as reporter_name 
                FROM device_repairs dr 
                LEFT JOIN access_codes ac ON dr.reported_by = ac.id"; //
        
        if (!empty($status)) { //
            $sql .= " WHERE dr.status = ?"; //
        }
        
        $sql .= " ORDER BY dr.created_at DESC LIMIT ? OFFSET ?"; //
        
        $stmt = $conn->prepare($sql); //
        if (!empty($status)) { //
            $stmt->bind_param("sii", $status, $limit, $offset); //
        } else { //
            $stmt->bind_param("ii", $limit, $offset); //
        }
        
        $stmt->execute(); //
        $result = $stmt->get_result(); //
        
        $repairs = []; //
        while ($row = $result->fetch_assoc()) { //
            // تبدیل تاریخ به شمسی
            $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
            if ($row['repair_date']) { //
                $row['repair_date'] = to_jalali($row['repair_date']); //
            }
            $repairs[] = $row; //
        }
        
        $stmt->close(); //
        
        echo json_encode([ //
            'success' => true, //
            'repairs' => $repairs, //
            'pagination' => [ //
                'page' => $page, //
                'limit' => $limit, //
                'total' => $total_count, //
                'total_pages' => ceil($total_count / $limit) //
            ]
        ]); //
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در دریافت تعمیرات: ' . $e->getMessage() //
        ]); //
    }
}

// دریافت جزئیات تعمیر
function getRepairDetails($repair_id) { //
    global $conn; //
    
    if ($repair_id <= 0) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'شناسه تعمیر نامعتبر است' //
        ]); //
        return; //
    }
    
    try { //
        // بررسی اتصال پایگاه داده
        if (!$conn) { //
            throw new Exception('خطا در اتصال به پایگاه داده'); //
        }
        
        // دریافت اطلاعات تعمیر
        $sql = "SELECT dr.*, 
                       ac1.description as reporter_name,
                       ac2.description as technician_name
                FROM device_repairs dr 
                LEFT JOIN access_codes ac1 ON dr.reported_by = ac1.id 
                LEFT JOIN access_codes ac2 ON dr.technician_id = ac2.id 
                WHERE dr.id = ?"; //
        
        $stmt = $conn->prepare($sql); //
        $stmt->bind_param("i", $repair_id); //
        $stmt->execute(); //
        $result = $stmt->get_result(); //
        
        if ($result->num_rows == 0) { //
            echo json_encode([ //
                'success' => false, //
                'message' => 'تعمیر یافت نشد' //
            ]); //
            return; //
        }
        
        $repair = $result->fetch_assoc(); //
        $stmt->close(); //
        
        // تبدیل تاریخ‌ها به شمسی
        $repair['created_at'] = datetime_to_jalali($repair['created_at'], 'Y/m/d H:i'); //
        if ($repair['repair_date']) { //
            $repair['repair_date'] = to_jalali($repair['repair_date']); //
        }
        
        // دریافت تاریخچه تغییرات
        $history_sql = "SELECT rh.*, ac.description as user_name 
                        FROM repair_history rh 
                        LEFT JOIN access_codes ac ON rh.user_id = ac.id 
                        WHERE rh.repair_id = ? 
                        ORDER BY rh.created_at ASC"; //
        
        $history_stmt = $conn->prepare($history_sql); //
        $history_stmt->bind_param("i", $repair_id); //
        $history_stmt->execute(); //
        $history_result = $history_stmt->get_result(); //
        
        $history = []; //
        while ($row = $history_result->fetch_assoc()) { //
            $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
            $history[] = $row; //
        }
        
        $history_stmt->close(); //
        
        echo json_encode([ //
            'success' => true, //
            'repair' => $repair, //
            'history' => $history //
        ]); //
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در دریافت جزئیات تعمیر: ' . $e->getMessage() //
        ]); //
    }
}

// به‌روزرسانی وضعیت تعمیر
function updateRepairStatus() { //
    global $conn, $user_id; //
    
    $repair_id = isset($_POST['repair_id']) ? intval($_POST['repair_id']) : 0; //
    $status = isset($_POST['repair_status']) ? $_POST['repair_status'] : ''; //
    $technician_notes = isset($_POST['technician_notes']) ? $_POST['technician_notes'] : ''; //
    $repair_date = isset($_POST['repair_date']) ? $_POST['repair_date'] : ''; //
    
    if ($repair_id <= 0 || empty($status) || empty($technician_notes) || empty($repair_date)) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'تمام فیلدهای ضروری را پر کنید' //
        ]); //
        return; //
    }
    
    try { //
        // بررسی اتصال پایگاه داده
        if (!$conn) { //
            throw new Exception('خطا در اتصال به پایگاه داده'); //
        }
        
        // تبدیل تاریخ شمسی به میلادی
        $gregorian_date = jalali_to_gregorian_date($repair_date); //
        if (is_null($gregorian_date)) { //
             logError("فرمت تاریخ تعمیر نامعتبر است: " . $repair_date); //
             throw new Exception('فرمت تاریخ تعمیر نامعتبر است'); //
        }
        
        // به‌روزرسانی وضعیت تعمیر
        $sql = "UPDATE device_repairs 
                SET status = ?, 
                    technician_id = ?, 
                    technician_notes = ?, 
                    repair_date = ? 
                WHERE id = ?"; //
        
        $stmt = $conn->prepare($sql); //
        $stmt->bind_param("sissi", $status, $user_id, $technician_notes, $gregorian_date, $repair_id); //
        
        if ($stmt->execute()) { //
            $stmt->close(); //
            
            // ثبت در تاریخچه تغییرات
            $history_sql = "INSERT INTO repair_history (repair_id, status, notes, user_id) 
                           VALUES (?, ?, ?, ?)"; //
            
            $history_stmt = $conn->prepare($history_sql); //
            $history_stmt->bind_param("issi", $repair_id, $status, $technician_notes, $user_id); //
            $history_stmt->execute(); //
            $history_stmt->close(); //
            
            echo json_encode([ //
                'success' => true, //
                'message' => 'وضعیت تعمیر با موفقیت به‌روزرسانی شد' //
            ]); //
        } else { //
            throw new Exception("خطا در به‌روزرسانی وضعیت"); //
        }
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در به‌روزرسانی وضعیت تعمیر: ' . $e->getMessage() //
        ]); //
    }
}

// جستجوی تعمیرات
function searchRepairs() { //
    global $conn; //
    
    $terminal_serial = isset($_GET['terminal_serial']) ? $_GET['terminal_serial'] : ''; //
    $adapter_serial = isset($_GET['adapter_serial']) ? $_GET['adapter_serial'] : ''; //
    
    if (empty($terminal_serial) && empty($adapter_serial)) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'لطفاً حداقل یکی از فیلدهای جستجو را وارد کنید' //
        ]); //
        return; //
    }
    
    try { //
        // بررسی اتصال پایگاه داده
        if (!$conn) { //
            throw new Exception('خطا در اتصال به پایگاه داده'); //
        }
        
        $sql = "SELECT dr.*, ac.description as reporter_name 
                FROM device_repairs dr 
                LEFT JOIN access_codes ac ON dr.reported_by = ac.id 
                WHERE 1=1"; //
        
        $params = []; //
        $types = ""; //
        
        if (!empty($terminal_serial)) { //
            $sql .= " AND dr.terminal_serial LIKE ?"; //
            $params[] = "%$terminal_serial%"; //
            $types .= "s"; //
        }
        
        if (!empty($adapter_serial)) { //
            $sql .= " AND dr.adapter_serial LIKE ?"; //
            $params[] = "%$adapter_serial%"; //
            $types .= "s"; //
        }
        
        $sql .= " ORDER BY dr.created_at DESC"; //
        
        $stmt = $conn->prepare($sql); //
        if (!empty($params)) { //
            $stmt->bind_param($types, ...$params); //
        }
        
        $stmt->execute(); //
        $result = $stmt->get_result(); //
        
        $repairs = []; //
        while ($row = $result->fetch_assoc()) { //
            $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
            if ($row['repair_date']) { //
                $row['repair_date'] = to_jalali($row['repair_date']); //
            }
            $repairs[] = $row; //
        }
        
        $stmt->close(); //
        
        echo json_encode([ //
            'success' => true, //
            'repairs' => $repairs //
        ]); //
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در جستجو: ' . $e->getMessage() //
        ]); //
    }
}

// تولید گزارش
function getRepairReport() { //
    global $conn; //
    
    // بررسی اتصال پایگاه داده
    if (!$conn) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در اتصال به پایگاه داده' //
        ]); //
        exit; //
    }
    
    $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : ''; //
    $to_date = isset($_GET['to_date']) ? $_GET['to_date'] : ''; //
    $status = isset($_GET['status']) ? $_GET['status'] : ''; //
    $damage_type = isset($_GET['damage_type']) ? $_GET['damage_type'] : ''; //
    
    try { //
        $sql = "SELECT dr.*, 
                       ac1.description as reporter_name,
                       ac2.description as technician_name
                FROM device_repairs dr 
                LEFT JOIN access_codes ac1 ON dr.reported_by = ac1.id 
                LEFT JOIN access_codes ac2 ON dr.technician_id = ac2.id 
                WHERE 1=1"; //
        
        $params = []; //
        $types = ""; //
        
        // فیلتر تاریخ
        if (!empty($from_date)) { //
            try { //
                $gregorian_from = jalali_to_gregorian_date($from_date); //
                if (!is_null($gregorian_from)) { //
                    $sql .= " AND dr.created_at >= ?"; //
                    $params[] = $gregorian_from . " 00:00:00"; //
                    $types .= "s"; //
                } else { //
                    logError("فرمت تاریخ 'از تاریخ' نامعتبر است: " . $from_date); //
                }
            } catch (Exception $e) { //
                logError("خطا در تبدیل تاریخ 'از تاریخ': " . $e->getMessage()); //
                // ادامه اجرا بدون فیلتر تاریخ
            }
        }
        
        if (!empty($to_date)) { //
            try { //
                $gregorian_to = jalali_to_gregorian_date($to_date); //
                if (!is_null($gregorian_to)) { //
                    $sql .= " AND dr.created_at <= ?"; //
                    $params[] = $gregorian_to . " 23:59:59"; //
                    $types .= "s"; //
                } else { //
                    logError("فرمت تاریخ 'تا تاریخ' نامعتبر است: " . $to_date); //
                }
            } catch (Exception $e) { //
                logError("خطا در تبدیل تاریخ 'تا تاریخ': " . $e->getMessage()); //
                // ادامه اجرا بدون فیلتر تاریخ
            }
        }
        
        // فیلتر وضعیت
        if (!empty($status)) { //
            $sql .= " AND dr.status = ?"; //
            $params[] = $status; //
            $types .= "s"; //
        }
        
        // فیلتر نوع خرابی
        if (!empty($damage_type)) { //
            if ($damage_type == 'terminal') { //
                $sql .= " AND dr.is_terminal_damaged = 1 AND dr.is_adapter_damaged = 0"; //
            } elseif ($damage_type == 'adapter') { //
                $sql .= " AND dr.is_terminal_damaged = 0 AND dr.is_adapter_damaged = 1"; //
            } elseif ($damage_type == 'both') { //
                $sql .= " AND dr.is_terminal_damaged = 1 AND dr.is_adapter_damaged = 1"; //
            }
        }
        
        $sql .= " ORDER BY dr.created_at DESC"; //
        
        $stmt = $conn->prepare($sql); //
        if (!empty($params)) { //
            $stmt->bind_param($types, ...$params); //
        }
        
        $stmt->execute(); //
        $result = $stmt->get_result(); //
        
        $repairs = []; //
        while ($row = $result->fetch_assoc()) { //
            $row['created_at'] = datetime_to_jalali($row['created_at'], 'Y/m/d H:i'); //
            if ($row['repair_date']) { //
                $row['repair_date'] = to_jalali($row['repair_date']); //
            }
            $repairs[] = $row; //
        }
        
        $stmt->close(); //
        
        // محاسبه آمار
        $stats = [ //
            'total_repairs' => count($repairs), //
            'status_counts' => [ //
                'pending' => 0, //
                'in_progress' => 0, //
                'repaired' => 0, //
                'replaced' => 0, //
                'returned' => 0, //
                'healthy' => 0 //
            ], //
            'damage_types' => [ //
                'terminal_only' => 0, //
                'adapter_only' => 0, //
                'both' => 0 //
            ]
        ]; //
        
        foreach ($repairs as $repair) { //
            if (isset($repair['status']) && isset($stats['status_counts'][$repair['status']])) { //
                $stats['status_counts'][$repair['status']]++; //
            }
            
            if ($repair['is_terminal_damaged'] && $repair['is_adapter_damaged']) { //
                $stats['damage_types']['both']++; //
            } elseif ($repair['is_terminal_damaged']) { //
                $stats['damage_types']['terminal_only']++; //
            } elseif ($repair['is_adapter_damaged']) { //
                $stats['damage_types']['adapter_only']++; //
            }
        }
        
        echo json_encode([ //
            'success' => true, //
            'repairs' => $repairs, //
            'stats' => $stats //
        ]); //
    } catch (Exception $e) { //
        echo json_encode([ //
            'success' => false, //
            'message' => 'خطا در تولید گزارش: ' . $e->getMessage() //
        ]); //
    }
}