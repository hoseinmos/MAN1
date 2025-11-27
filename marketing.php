<?php
// marketing.php - صفحه اصلی مدیریت بازاریابی
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

// تابع تبدیل تاریخ شمسی به میلادی
function jalali_to_gregorian_date($j_date) {
    if(empty($j_date)) return null;
    
    // تبدیل فرمت تاریخ از 1400/01/01 به آرایه
    $date_parts = explode('/', $j_date);
    if(count($date_parts) !== 3) return null;
    
    // تبدیل به میلادی
    list($gy, $gm, $gd) = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
    
    // بازگشت به فرمت YYYY-MM-DD
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// فانکشن ایجاد پوشه برای هر بازاریابی
function create_marketing_directory($marketing_id) {
    $upload_dir = 'uploads/marketing/' . $marketing_id;
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    return $upload_dir;
}

// فانکشن آپلود فایل
function upload_file($file, $marketing_id, $field_name) {
    // بررسی خطاها
    if ($file['error'] != UPLOAD_ERR_OK && $file['error'] != UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'خطا در آپلود فایل: ' . $file['error']];
    }
    
    // اگر فایلی آپلود نشده، خطا نیست
    if ($file['error'] == UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => ''];
    }
    
    // بررسی نوع فایل
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'فقط فایل‌های تصویری (JPG, JPEG, PNG) مجاز هستند.'];
    }
    
    // بررسی اندازه فایل (۱ مگابایت)
    if ($file['size'] > 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم فایل نباید بیشتر از ۱ مگابایت باشد.'];
    }
    
    // ایجاد پوشه برای ذخیره فایل‌ها
    $upload_dir = create_marketing_directory($marketing_id);
    
    // ایجاد نام فایل منحصر به فرد
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = $field_name . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    // آپلود فایل
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $upload_path];
    } else {
        return ['success' => false, 'message' => 'خطا در آپلود فایل.'];
    }
}

// متغیرهای مورد نیاز
$error_message = '';
$success_message = '';
$marketing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit_mode = $marketing_id > 0;
$marketing_data = [];
$banks = ['بانک تجارت', 'بانک ملت', 'بانک صادرات', 'بانک تجارت', 'بانک سپه', 'بانک کشاورزی', 'بانک مسکن', 'سایر'];
$device_models = ['سیار', 'ثابت', 'سایر'];
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION["user_description"] === "مدیر سیستم");

// دریافت لیست پشتیبان‌ها
$support_persons = [];
if($conn) {
    $sql = "SELECT DISTINCT support_person FROM terminals WHERE support_person IS NOT NULL ORDER BY support_person";
    $result = $conn->query($sql);
    if($result) {
        while($row = $result->fetch_assoc()) {
            if(!empty($row['support_person'])) {
                $support_persons[] = $row['support_person'];
            }
        }
    }
}

// دریافت اطلاعات بازاریابی برای ویرایش
if($is_edit_mode && $conn) {
    $sql = "SELECT * FROM marketing WHERE id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $marketing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $marketing_data = $result->fetch_assoc();
            
            // تبدیل تاریخ میلادی به شمسی برای نمایش
            if(!empty($marketing_data['birth_date'])) {
                $birth_date = explode('-', $marketing_data['birth_date']);
                $marketing_data['birth_date_jalali'] = gregorian_to_jalali($birth_date[0], $birth_date[1], $birth_date[2], '/');
            } else {
                $marketing_data['birth_date_jalali'] = '';
            }
            
            // فقط مدیر یا ایجاد کننده می‌تواند ویرایش کند
            if(!$is_admin && $marketing_data['created_by'] != $user_id) {
                $_SESSION['error'] = "شما اجازه دسترسی به این بازاریابی را ندارید.";
                header("Location: marketing_list.php");
                exit;
            }
        } else {
            $_SESSION['error'] = "بازاریابی مورد نظر یافت نشد.";
            header("Location: marketing_list.php");
            exit;
        }
        
        $stmt->close();
    }
}

// پردازش فرم ارسالی برای ثبت یا ویرایش
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marketing'])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        // دریافت داده‌های فرم
        $national_code = isset($_POST['national_code']) ? sanitize_input($_POST['national_code']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_input($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_input($_POST['last_name']) : '';
        $birth_date_jalali = isset($_POST['birth_date']) ? sanitize_input($_POST['birth_date']) : '';
        $birth_date = jalali_to_gregorian_date($birth_date_jalali);
        $city = isset($_POST['city']) ? sanitize_input($_POST['city']) : '';
        $business_type = isset($_POST['business_type']) ? sanitize_input($_POST['business_type']) : '';
        $device_model = isset($_POST['device_model']) ? sanitize_input($_POST['device_model']) : '';
        $terminal_count = isset($_POST['terminal_count']) ? (int)$_POST['terminal_count'] : 1;
        $store_name = isset($_POST['store_name']) ? sanitize_input($_POST['store_name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
        $mobile = isset($_POST['mobile']) ? sanitize_input($_POST['mobile']) : '';
        $postal_code = isset($_POST['postal_code']) ? sanitize_input($_POST['postal_code']) : '';
        $tax_code = isset($_POST['tax_code']) ? sanitize_input($_POST['tax_code']) : '';
        $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
        $bank_name = isset($_POST['bank_name']) ? sanitize_input($_POST['bank_name']) : '';
        $branch_code = isset($_POST['branch_code']) ? sanitize_input($_POST['branch_code']) : '';
        $account_number = isset($_POST['account_number']) ? sanitize_input($_POST['account_number']) : '';
        $sheba_number = isset($_POST['sheba_number']) ? sanitize_input($_POST['sheba_number']) : '';
        $support_person = isset($_POST['support_person']) ? sanitize_input($_POST['support_person']) : '';
        $status = $is_admin && isset($_POST['status']) ? sanitize_input($_POST['status']) : 'pending';
        $status_description = isset($_POST['status_description']) ? sanitize_input($_POST['status_description']) : '';
        
        // بررسی اعتبارسنجی داده‌ها
        if(empty($national_code) || strlen($national_code) != 10 || !ctype_digit($national_code)) {
            $error_message = "کد ملی باید ۱۰ رقم باشد.";
        } elseif(empty($first_name)) {
            $error_message = "لطفاً نام را وارد کنید.";
        } elseif(empty($last_name)) {
            $error_message = "لطفاً نام خانوادگی را وارد کنید.";
        } elseif(empty($store_name)) {
            $error_message = "لطفاً نام فروشگاه را وارد کنید.";
        } elseif(empty($mobile) || strlen($mobile) != 11 || !ctype_digit($mobile)) {
            $error_message = "شماره موبایل باید ۱۱ رقم باشد.";
        } elseif(!empty($postal_code) && strlen($postal_code) != 10) {
            $error_message = "کد پستی باید ۱۰ رقم باشد.";
        } elseif(!empty($sheba_number) && strlen($sheba_number) != 26) {
            $error_message = "شماره شبا باید ۲۶ رقم باشد.";
        } else {
            // بررسی وجود کد ملی تکراری (در حالت افزودن جدید)
            if(!$is_edit_mode) {
                $check_sql = "SELECT id FROM marketing WHERE national_code = ?";
                if($check_stmt = $conn->prepare($check_sql)) {
                    $check_stmt->bind_param("s", $national_code);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if($check_result->num_rows > 0) {
                        $error_message = "این کد ملی قبلاً در سیستم ثبت شده است.";
                        $check_stmt->close();
                    } else {
                        $check_stmt->close();
                        
                        // ثبت اطلاعات جدید
                        $sql = "INSERT INTO marketing (national_code, first_name, last_name, birth_date, city, business_type, 
                                device_model, terminal_count, store_name, phone, mobile, postal_code, tax_code, address, 
                                bank_name, branch_code, account_number, sheba_number, support_person, status, status_description, 
                                created_by, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        if($stmt = $conn->prepare($sql)) {
                            $stmt->bind_param("ssssssissssssssssssssi", 
                                $national_code, $first_name, $last_name, $birth_date, $city, $business_type,
                                $device_model, $terminal_count, $store_name, $phone, $mobile, $postal_code, 
                                $tax_code, $address, $bank_name, $branch_code, $account_number, $sheba_number, 
                                $support_person, $status, $status_description, $user_id);
                            
                            if($stmt->execute()) {
                                $new_marketing_id = $conn->insert_id;
                                $stmt->close();
                                
                                // آپلود فایل‌ها
                                $file_upload_errors = [];
                                
                                // آپلود عکس کارت ملی (اجباری)
                                if(isset($_FILES['national_card_image']) && $_FILES['national_card_image']['error'] != UPLOAD_ERR_NO_FILE) {
                                    $upload_result = upload_file($_FILES['national_card_image'], $new_marketing_id, 'national_card');
                                    if($upload_result['success']) {
                                        $national_card_image = $upload_result['filename'];
                                        
                                        // به‌روزرسانی مسیر فایل در دیتابیس
                                        $update_sql = "UPDATE marketing SET national_card_image = ? WHERE id = ?";
                                        if($update_stmt = $conn->prepare($update_sql)) {
                                            $update_stmt->bind_param("si", $national_card_image, $new_marketing_id);
                                            $update_stmt->execute();
                                            $update_stmt->close();
                                        }
                                    } else {
                                        $file_upload_errors[] = "خطا در آپلود تصویر کارت ملی: " . $upload_result['message'];
                                    }
                                }
                                
                                // آپلود تصویر مجوز کسب (اجباری)
                                if(isset($_FILES['business_license_image']) && $_FILES['business_license_image']['error'] != UPLOAD_ERR_NO_FILE) {
                                    $upload_result = upload_file($_FILES['business_license_image'], $new_marketing_id, 'business_license');
                                    if($upload_result['success']) {
                                        $business_license_image = $upload_result['filename'];
                                        
                                        // به‌روزرسانی مسیر فایل در دیتابیس
                                        $update_sql = "UPDATE marketing SET business_license_image = ? WHERE id = ?";
                                        if($update_stmt = $conn->prepare($update_sql)) {
                                            $update_stmt->bind_param("si", $business_license_image, $new_marketing_id);
                                            $update_stmt->execute();
                                            $update_stmt->close();
                                        }
                                    } else {
                                        $file_upload_errors[] = "خطا در آپلود تصویر مجوز کسب: " . $upload_result['message'];
                                    }
                                }
                                
                                // آپلود تصویر شناسنامه (اختیاری)
                                if(isset($_FILES['birth_certificate_image']) && $_FILES['birth_certificate_image']['error'] != UPLOAD_ERR_NO_FILE) {
                                    $upload_result = upload_file($_FILES['birth_certificate_image'], $new_marketing_id, 'birth_certificate');
                                    if($upload_result['success']) {
                                        $birth_certificate_image = $upload_result['filename'];
                                        
                                        // به‌روزرسانی مسیر فایل در دیتابیس
                                        $update_sql = "UPDATE marketing SET birth_certificate_image = ? WHERE id = ?";
                                        if($update_stmt = $conn->prepare($update_sql)) {
                                            $update_stmt->bind_param("si", $birth_certificate_image, $new_marketing_id);
                                            $update_stmt->execute();
                                            $update_stmt->close();
                                        }
                                    } else {
                                        $file_upload_errors[] = "خطا در آپلود تصویر شناسنامه: " . $upload_result['message'];
                                    }
                                }
                                
                                // آپلود سایر مدارک (اختیاری)
                                if(isset($_FILES['other_documents_image']) && $_FILES['other_documents_image']['error'] != UPLOAD_ERR_NO_FILE) {
                                    $upload_result = upload_file($_FILES['other_documents_image'], $new_marketing_id, 'other_documents');
                                    if($upload_result['success']) {
                                        $other_documents_image = $upload_result['filename'];
                                        
                                        // به‌روزرسانی مسیر فایل در دیتابیس
                                        $update_sql = "UPDATE marketing SET other_documents_image = ? WHERE id = ?";
                                        if($update_stmt = $conn->prepare($update_sql)) {
                                            $update_stmt->bind_param("si", $other_documents_image, $new_marketing_id);
                                            $update_stmt->execute();
                                            $update_stmt->close();
                                        }
                                    } else {
                                        $file_upload_errors[] = "خطا در آپلود سایر مدارک: " . $upload_result['message'];
                                    }
                                }
                                
                                // ثبت در لاگ
                                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                                           VALUES (?, 'add_marketing', ?, ?, NOW())";
                                
                                if($log_stmt = $conn->prepare($log_sql)) {
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $log_stmt->bind_param("iis", $user_id, $new_marketing_id, $ip);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                                
                                // پیام موفقیت آمیز
                                $_SESSION['success'] = "اطلاعات بازاریابی با موفقیت ثبت شد." . 
                                                  (!empty($file_upload_errors) ? "<br>هشدار: " . implode("<br>", $file_upload_errors) : "");
                                header("Location: marketing_list.php");
                                exit;
                            } else {
                                $error_message = "خطا در ثبت اطلاعات: " . $stmt->error;
                            }
                        } else {
                            $error_message = "خطا در آماده‌سازی دستور SQL: " . $conn->error;
                        }
                    }
                }
            } else {
                // به روزرسانی اطلاعات موجود
                $sql = "UPDATE marketing SET 
                        national_code = ?, first_name = ?, last_name = ?, birth_date = ?, city = ?, business_type = ?, 
                        device_model = ?, terminal_count = ?, store_name = ?, phone = ?, mobile = ?, postal_code = ?, 
                        tax_code = ?, address = ?, bank_name = ?, branch_code = ?, account_number = ?, sheba_number = ?, 
                        support_person = ?, updated_by = ?, updated_at = NOW()";
                
                // اگر کاربر مدیر است، وضعیت را نیز به‌روزرسانی کن
                if($is_admin) {
                    $sql .= ", status = ?, status_description = ?";
                }
                
                $sql .= " WHERE id = ?";
                
                if($stmt = $conn->prepare($sql)) {
                    if($is_admin) {
                        $stmt->bind_param("ssssssissssssssssssissi", 
                            $national_code, $first_name, $last_name, $birth_date, $city, $business_type,
                            $device_model, $terminal_count, $store_name, $phone, $mobile, $postal_code, 
                            $tax_code, $address, $bank_name, $branch_code, $account_number, $sheba_number, 
                            $support_person, $user_id, $status, $status_description, $marketing_id);
                    } else {
                        $stmt->bind_param("ssssssissssssssssssii", 
                            $national_code, $first_name, $last_name, $birth_date, $city, $business_type,
                            $device_model, $terminal_count, $store_name, $phone, $mobile, $postal_code, 
                            $tax_code, $address, $bank_name, $branch_code, $account_number, $sheba_number, 
                            $support_person, $user_id, $marketing_id);
                    }
                    
                    if($stmt->execute()) {
                        $stmt->close();
                        
                        // آپلود فایل‌ها
                        $file_upload_errors = [];
                        
                        // آپلود عکس کارت ملی (به‌روزرسانی در صورت وجود)
                        if(isset($_FILES['national_card_image']) && $_FILES['national_card_image']['error'] != UPLOAD_ERR_NO_FILE) {
                            $upload_result = upload_file($_FILES['national_card_image'], $marketing_id, 'national_card');
                            if($upload_result['success']) {
                                $national_card_image = $upload_result['filename'];
                                
                                // به‌روزرسانی مسیر فایل در دیتابیس
                                $update_sql = "UPDATE marketing SET national_card_image = ? WHERE id = ?";
                                if($update_stmt = $conn->prepare($update_sql)) {
                                    $update_stmt->bind_param("si", $national_card_image, $marketing_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            } else {
                                $file_upload_errors[] = "خطا در آپلود تصویر کارت ملی: " . $upload_result['message'];
                            }
                        }
                        
                        // آپلود تصویر مجوز کسب (به‌روزرسانی در صورت وجود)
                        if(isset($_FILES['business_license_image']) && $_FILES['business_license_image']['error'] != UPLOAD_ERR_NO_FILE) {
                            $upload_result = upload_file($_FILES['business_license_image'], $marketing_id, 'business_license');
                            if($upload_result['success']) {
                                $business_license_image = $upload_result['filename'];
                                
                                // به‌روزرسانی مسیر فایل در دیتابیس
                                $update_sql = "UPDATE marketing SET business_license_image = ? WHERE id = ?";
                                if($update_stmt = $conn->prepare($update_sql)) {
                                    $update_stmt->bind_param("si", $business_license_image, $marketing_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            } else {
                                $file_upload_errors[] = "خطا در آپلود تصویر مجوز کسب: " . $upload_result['message'];
                            }
                        }
                        
                        // آپلود تصویر شناسنامه (به‌روزرسانی در صورت وجود)
                        if(isset($_FILES['birth_certificate_image']) && $_FILES['birth_certificate_image']['error'] != UPLOAD_ERR_NO_FILE) {
                            $upload_result = upload_file($_FILES['birth_certificate_image'], $marketing_id, 'birth_certificate');
                            if($upload_result['success']) {
                                $birth_certificate_image = $upload_result['filename'];
                                
                                // به‌روزرسانی مسیر فایل در دیتابیس
                                $update_sql = "UPDATE marketing SET birth_certificate_image = ? WHERE id = ?";
                                if($update_stmt = $conn->prepare($update_sql)) {
                                    $update_stmt->bind_param("si", $birth_certificate_image, $marketing_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            } else {
                                $file_upload_errors[] = "خطا در آپلود تصویر شناسنامه: " . $upload_result['message'];
                            }
                        }
                        
                        // آپلود سایر مدارک (به‌روزرسانی در صورت وجود)
                        if(isset($_FILES['other_documents_image']) && $_FILES['other_documents_image']['error'] != UPLOAD_ERR_NO_FILE) {
                            $upload_result = upload_file($_FILES['other_documents_image'], $marketing_id, 'other_documents');
                            if($upload_result['success']) {
                                $other_documents_image = $upload_result['filename'];
                                
                                // به‌روزرسانی مسیر فایل در دیتابیس
                                $update_sql = "UPDATE marketing SET other_documents_image = ? WHERE id = ?";
                                if($update_stmt = $conn->prepare($update_sql)) {
                                    $update_stmt->bind_param("si", $other_documents_image, $marketing_id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            } else {
                                $file_upload_errors[] = "خطا در آپلود سایر مدارک: " . $upload_result['message'];
                            }
                        }
                        
                        // ثبت در لاگ
                        $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                                   VALUES (?, 'update_marketing', ?, ?, NOW())";
                        
                        if($log_stmt = $conn->prepare($log_sql)) {
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $log_stmt->bind_param("iis", $user_id, $marketing_id, $ip);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        
                        // پیام موفقیت آمیز
                        $_SESSION['success'] = "اطلاعات بازاریابی با موفقیت به‌روزرسانی شد." . 
                                          (!empty($file_upload_errors) ? "<br>هشدار: " . implode("<br>", $file_upload_errors) : "");
                        header("Location: marketing_list.php");
                        exit;
                    } else {
                        $error_message = "خطا در به‌روزرسانی اطلاعات: " . $stmt->error;
                    }
                } else {
                    $error_message = "خطا در آماده‌سازی دستور SQL: " . $conn->error;
                }
            }
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
    <title><?php echo $is_edit_mode ? 'ویرایش بازاریابی' : 'ثبت بازاریابی جدید'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="marketing.css">
</head>
<body class="dashboard-page">
    <div class="container marketing-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-handshake me-2"></i> <?php echo $is_edit_mode ? 'ویرایش بازاریابی' : 'ثبت بازاریابی جدید'; ?></h2>
                        <div>
                            <a href="marketing_list.php" class="btn btn-light me-2">
                                <i class="fas fa-list me-1"></i> لیست بازاریابی‌ها
                            </a>
                            <a href="dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به داشبورد
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- فرم ثبت/ویرایش بازاریابی -->
                        <form method="POST" action="" enctype="multipart/form-data" id="marketingForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- فیلدهای وضعیت (فقط برای مدیر) -->
                            <?php if($is_admin && $is_edit_mode): ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="status-section mb-4">
                                            <h4>وضعیت بازاریابی</h4>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="status" class="form-label">وضعیت:</label>
                                                        <select class="form-select" id="status" name="status">
                                                            <option value="pending" <?php echo isset($marketing_data['status']) && $marketing_data['status'] == 'pending' ? 'selected' : ''; ?>>در انتظار بررسی</option>
                                                            <option value="approved" <?php echo isset($marketing_data['status']) && $marketing_data['status'] == 'approved' ? 'selected' : ''; ?>>تأیید شده</option>
                                                            <option value="rejected" <?php echo isset($marketing_data['status']) && $marketing_data['status'] == 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="mb-3">
                                                        <label for="status_description" class="form-label">توضیحات وضعیت:</label>
                                                        <textarea class="form-control" id="status_description" name="status_description" rows="3"><?php echo isset($marketing_data['status_description']) ? htmlspecialchars($marketing_data['status_description']) : ''; ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- اطلاعات شخصی -->
                            <div class="form-section mb-4">
                                <h4 class="form-section-title">اطلاعات شخصی</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="national_code" class="form-label required">کد ملی:</label>
                                            <input type="text" class="form-control" id="national_code" name="national_code" value="<?php echo isset($marketing_data['national_code']) ? htmlspecialchars($marketing_data['national_code']) : ''; ?>" maxlength="10" required <?php echo $is_edit_mode ? 'readonly' : ''; ?>>
                                            <div class="invalid-feedback">کد ملی را وارد کنید (10 رقم)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label required">نام:</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($marketing_data['first_name']) ? htmlspecialchars($marketing_data['first_name']) : ''; ?>" required>
                                            <div class="invalid-feedback">نام را وارد کنید</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label required">نام خانوادگی:</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($marketing_data['last_name']) ? htmlspecialchars($marketing_data['last_name']) : ''; ?>" required>
                                            <div class="invalid-feedback">نام خانوادگی را وارد کنید</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="birth_date" class="form-label">تاریخ تولد:</label>
                                            <input type="text" class="form-control persian-date" id="birth_date" name="birth_date" value="<?php echo isset($marketing_data['birth_date_jalali']) ? htmlspecialchars($marketing_data['birth_date_jalali']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="city" class="form-label">شهر:</label>
                                            <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($marketing_data['city']) ? htmlspecialchars($marketing_data['city']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">تلفن ثابت:</label>
                                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($marketing_data['phone']) ? htmlspecialchars($marketing_data['phone']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="mobile" class="form-label required">موبایل:</label>
                                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo isset($marketing_data['mobile']) ? htmlspecialchars($marketing_data['mobile']) : ''; ?>" maxlength="11" required>
                                            <div class="invalid-feedback">شماره موبایل را وارد کنید (11 رقم)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="postal_code" class="form-label">کد پستی:</label>
                                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo isset($marketing_data['postal_code']) ? htmlspecialchars($marketing_data['postal_code']) : ''; ?>" maxlength="10">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="address" class="form-label">آدرس:</label>
                                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($marketing_data['address']) ? htmlspecialchars($marketing_data['address']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات کسب و کار -->
                            <div class="form-section mb-4">
                                <h4 class="form-section-title">اطلاعات کسب و کار</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="business_type" class="form-label">صنف:</label>
                                            <input type="text" class="form-control" id="business_type" name="business_type" value="<?php echo isset($marketing_data['business_type']) ? htmlspecialchars($marketing_data['business_type']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="store_name" class="form-label required">نام فروشگاه:</label>
                                            <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo isset($marketing_data['store_name']) ? htmlspecialchars($marketing_data['store_name']) : ''; ?>" required>
                                            <div class="invalid-feedback">نام فروشگاه را وارد کنید</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="tax_code" class="form-label">کد مالیاتی:</label>
                                            <input type="text" class="form-control" id="tax_code" name="tax_code" value="<?php echo isset($marketing_data['tax_code']) ? htmlspecialchars($marketing_data['tax_code']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="device_model" class="form-label">مدل دستگاه:</label>
                                            <select class="form-select" id="device_model" name="device_model">
                                                <option value="">انتخاب کنید...</option>
                                                <?php foreach($device_models as $model): ?>
                                                    <option value="<?php echo htmlspecialchars($model); ?>" <?php echo isset($marketing_data['device_model']) && $marketing_data['device_model'] == $model ? 'selected' : ''; ?>><?php echo htmlspecialchars($model); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="terminal_count" class="form-label">تعداد پایانه:</label>
                                            <input type="number" class="form-control" id="terminal_count" name="terminal_count" value="<?php echo isset($marketing_data['terminal_count']) ? (int)$marketing_data['terminal_count'] : 1; ?>" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="support_person" class="form-label">پشتیبان:</label>
                                            <select class="form-select" id="support_person" name="support_person">
                                                <option value="">انتخاب کنید...</option>
                                                <?php foreach($support_persons as $support): ?>
                                                    <option value="<?php echo htmlspecialchars($support); ?>" <?php echo isset($marketing_data['support_person']) && $marketing_data['support_person'] == $support ? 'selected' : ''; ?>><?php echo htmlspecialchars($support); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات بانکی -->
                            <div class="form-section mb-4">
                                <h4 class="form-section-title">اطلاعات بانکی</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="bank_name" class="form-label">نام بانک:</label>
                                            <select class="form-select" id="bank_name" name="bank_name">
                                                <option value="">انتخاب کنید...</option>
                                                <?php foreach($banks as $bank): ?>
                                                    <option value="<?php echo htmlspecialchars($bank); ?>" <?php echo isset($marketing_data['bank_name']) && $marketing_data['bank_name'] == $bank ? 'selected' : ''; ?>><?php echo htmlspecialchars($bank); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="branch_code" class="form-label">کد شعبه:</label>
                                            <input type="text" class="form-control" id="branch_code" name="branch_code" value="<?php echo isset($marketing_data['branch_code']) ? htmlspecialchars($marketing_data['branch_code']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="account_number" class="form-label">شماره حساب:</label>
                                            <input type="text" class="form-control" id="account_number" name="account_number" value="<?php echo isset($marketing_data['account_number']) ? htmlspecialchars($marketing_data['account_number']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="sheba_number" class="form-label">شماره شبا:</label>
                                            <input type="text" class="form-control" id="sheba_number" name="sheba_number" value="<?php echo isset($marketing_data['sheba_number']) ? htmlspecialchars($marketing_data['sheba_number']) : ''; ?>" maxlength="26">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- آپلود مدارک -->
                            <div class="form-section mb-4">
                                <h4 class="form-section-title">مدارک</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="national_card_image" class="form-label <?php echo !$is_edit_mode ? 'required' : ''; ?>">تصویر کارت ملی:</label>
                                            <input type="file" class="form-control" id="national_card_image" name="national_card_image" accept="image/jpeg, image/png, image/jpg" <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                                            <div class="form-text">فرمت‌های مجاز: JPG, JPEG, PNG - حداکثر حجم: 1 مگابایت</div>
                                            <?php if($is_edit_mode && !empty($marketing_data['national_card_image'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($marketing_data['national_card_image']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i> مشاهده فایل موجود
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="business_license_image" class="form-label <?php echo !$is_edit_mode ? 'required' : ''; ?>">تصویر جواز کسب/استشهادمحلی:</label>
                                            <input type="file" class="form-control" id="business_license_image" name="business_license_image" accept="image/jpeg, image/png, image/jpg" <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                                            <div class="form-text">فرمت‌های مجاز: JPG, JPEG, PNG - حداکثر حجم: 1 مگابایت</div>
                                            <?php if($is_edit_mode && !empty($marketing_data['business_license_image'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($marketing_data['business_license_image']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i> مشاهده فایل موجود
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="birth_certificate_image" class="form-label">تصویر شناسنامه (اختیاری):</label>
                                            <input type="file" class="form-control" id="birth_certificate_image" name="birth_certificate_image" accept="image/jpeg, image/png, image/jpg">
                                            <div class="form-text">فرمت‌های مجاز: JPG, JPEG, PNG - حداکثر حجم: 1 مگابایت</div>
                                            <?php if($is_edit_mode && !empty($marketing_data['birth_certificate_image'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($marketing_data['birth_certificate_image']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i> مشاهده فایل موجود
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="other_documents_image" class="form-label">سایر مدارک (اختیاری):</label>
                                            <input type="file" class="form-control" id="other_documents_image" name="other_documents_image" accept="image/jpeg, image/png, image/jpg">
                                            <div class="form-text">فرمت‌های مجاز: JPG, JPEG, PNG - حداکثر حجم: 1 مگابایت</div>
                                            <?php if($is_edit_mode && !empty($marketing_data['other_documents_image'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($marketing_data['other_documents_image']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i> مشاهده فایل موجود
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- دکمه‌های عملیات -->
                            <div class="d-flex justify-content-between">
                                <a href="marketing_list.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> انصراف
                                </a>
                                <button type="submit" name="submit_marketing" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> <?php echo $is_edit_mode ? 'به‌روزرسانی اطلاعات' : 'ثبت اطلاعات'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script src="script.js"></script>
    <script src="marketing.js"></script>
</body>
</html>