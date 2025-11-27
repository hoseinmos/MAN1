<?php
// marketing_status.php - صفحه تغییر وضعیت بازاریابی (فقط برای مدیر سیستم)
include 'config.php';
include 'jdf.php';

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

$marketing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$marketing_data = [];
$error_message = '';
$success_message = '';

// دریافت اطلاعات بازاریابی
if($marketing_id > 0 && $conn) {
    $sql = "SELECT * FROM marketing WHERE id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $marketing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $marketing_data = $result->fetch_assoc();
            
            // تبدیل تاریخ‌ها به شمسی
            $marketing_data['created_at'] = convert_to_jalali($marketing_data['created_at']);
        } else {
            $_SESSION['error'] = "بازاریابی مورد نظر یافت نشد.";
            header("Location: marketing_list.php");
            exit;
        }
        
        $stmt->close();
    } else {
        $_SESSION['error'] = "خطا در اجرای کوئری: " . $conn->error;
        header("Location: marketing_list.php");
        exit;
    }
} else {
    $_SESSION['error'] = "شناسه بازاریابی نامعتبر است.";
    header("Location: marketing_list.php");
    exit;
}

// پردازش فرم تغییر وضعیت
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $status = sanitize_input($_POST['status']);
        $status_description = sanitize_input($_POST['status_description']);
        
        $sql = "UPDATE marketing SET status = ?, status_description = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
        
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssii", $status, $status_description, $user_id, $marketing_id);
            
            if($stmt->execute()) {
                // ثبت در لاگ
                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                           VALUES (?, 'update_marketing_status', ?, ?, NOW())";
                
                if($log_stmt = $conn->prepare($log_sql)) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iis", $user_id, $marketing_id, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                $success_message = "وضعیت بازاریابی با موفقیت به‌روزرسانی شد.";
                
                // به‌روزرسانی داده‌های فعلی
                $marketing_data['status'] = $status;
                $marketing_data['status_description'] = $status_description;
            } else {
                $error_message = "خطا در به‌روزرسانی وضعیت: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $error_message = "خطا در آماده‌سازی دستور SQL: " . $conn->error;
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
    <title>تغییر وضعیت بازاریابی</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="marketing.css">
</head>
<body class="dashboard-page">
    <div class="container marketing-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-cog me-2"></i> تغییر وضعیت بازاریابی</h2>
                        <div>
                            <a href="marketing_view.php?id=<?php echo $marketing_id; ?>" class="btn btn-info me-2">
                                <i class="fas fa-eye me-1"></i> مشاهده بازاریابی
                            </a>
                            <a href="marketing_list.php" class="btn btn-light">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به لیست
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
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
                        
                        <!-- اطلاعات خلاصه بازاریابی -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">اطلاعات بازاریابی</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <p><strong>کد ملی:</strong> <?php echo htmlspecialchars($marketing_data['national_code']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>نام و نام خانوادگی:</strong> <?php echo htmlspecialchars($marketing_data['first_name'] . ' ' . $marketing_data['last_name']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>نام فروشگاه:</strong> <?php echo htmlspecialchars($marketing_data['store_name']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>تاریخ ثبت:</strong> <?php echo $marketing_data['created_at']; ?></p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <p><strong>شماره موبایل:</strong> <?php echo htmlspecialchars($marketing_data['mobile']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>صنف:</strong> <?php echo !empty($marketing_data['business_type']) ? htmlspecialchars($marketing_data['business_type']) : '-'; ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>وضعیت فعلی:</strong> 
                                        <span class="badge <?php 
                                            if($marketing_data['status'] == 'pending') echo 'bg-warning';
                                            elseif($marketing_data['status'] == 'approved') echo 'bg-success';
                                            elseif($marketing_data['status'] == 'rejected') echo 'bg-danger';
                                        ?>">
                                            <?php 
                                                if($marketing_data['status'] == 'pending') echo 'در انتظار بررسی';
                                                elseif($marketing_data['status'] == 'approved') echo 'تأیید شده';
                                                elseif($marketing_data['status'] == 'rejected') echo 'رد شده';
                                            ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- فرم تغییر وضعیت بازاریابی -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">تغییر وضعیت بازاریابی</h4>
                            <form method="POST" action="" id="statusForm" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">وضعیت جدید:</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo ($marketing_data['status'] == 'pending') ? 'selected' : ''; ?>>در انتظار بررسی</option>
                                                <option value="approved" <?php echo ($marketing_data['status'] == 'approved') ? 'selected' : ''; ?>>تأیید شده</option>
                                                <option value="rejected" <?php echo ($marketing_data['status'] == 'rejected') ? 'selected' : ''; ?>>رد شده</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                لطفاً وضعیت را انتخاب کنید.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="status_description" class="form-label">توضیحات وضعیت:</label>
                                            <textarea class="form-control" id="status_description" name="status_description" rows="3"><?php echo htmlspecialchars($marketing_data['status_description']); ?></textarea>
                                            <div class="form-text">توضیحاتی در مورد دلیل تغییر وضعیت یا اقدامات مورد نیاز برای تکمیل فرآیند.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="marketing_list.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> انصراف
                                    </a>
                                    <button type="submit" name="update_status" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> ذخیره تغییرات
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- تاریخچه تغییرات -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">تاریخچه مدارک</h4>
                            <div class="document-gallery">
                                <?php if(!empty($marketing_data['national_card_image'])): ?>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="document-card">
                                            <div class="document-title">تصویر کارت ملی</div>
                                            <div class="document-preview">
                                                <a href="<?php echo htmlspecialchars($marketing_data['national_card_image']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($marketing_data['national_card_image']); ?>" alt="تصویر کارت ملی" class="img-fluid">
                                                </a>
                                            </div>
                                            <div class="document-actions">
                                                <a href="<?php echo htmlspecialchars($marketing_data['national_card_image']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($marketing_data['business_license_image'])): ?>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="document-card">
                                            <div class="document-title">تصویر جواز کسب/استشهادمحلی</div>
                                            <div class="document-preview">
                                                <a href="<?php echo htmlspecialchars($marketing_data['business_license_image']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($marketing_data['business_license_image']); ?>" alt="تصویر جواز کسب" class="img-fluid">
                                                </a>
                                            </div>
                                            <div class="document-actions">
                                                <a href="<?php echo htmlspecialchars($marketing_data['business_license_image']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($marketing_data['birth_certificate_image'])): ?>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="document-card">
                                            <div class="document-title">تصویر شناسنامه</div>
                                            <div class="document-preview">
                                                <a href="<?php echo htmlspecialchars($marketing_data['birth_certificate_image']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($marketing_data['birth_certificate_image']); ?>" alt="تصویر شناسنامه" class="img-fluid">
                                                </a>
                                            </div>
                                            <div class="document-actions">
                                                <a href="<?php echo htmlspecialchars($marketing_data['birth_certificate_image']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($marketing_data['other_documents_image'])): ?>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="document-card">
                                            <div class="document-title">سایر مدارک</div>
                                            <div class="document-preview">
                                                <a href="<?php echo htmlspecialchars($marketing_data['other_documents_image']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($marketing_data['other_documents_image']); ?>" alt="سایر مدارک" class="img-fluid">
                                                </a>
                                            </div>
                                            <div class="document-actions">
                                                <a href="<?php echo htmlspecialchars($marketing_data['other_documents_image']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(empty($marketing_data['national_card_image']) && empty($marketing_data['business_license_image']) && empty($marketing_data['birth_certificate_image']) && empty($marketing_data['other_documents_image'])): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">هیچ مدرکی آپلود نشده است.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // اعتبارسنجی فرم‌ها
        (function() {
            'use strict';
            
            // اعتبارسنجی فرم تغییر وضعیت
            const form = document.getElementById('statusForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            }
            
            // نمایش یا عدم نمایش فیلد توضیحات وضعیت بر اساس وضعیت
            const statusSelect = document.getElementById('status');
            const statusDescription = document.getElementById('status_description');
            
            if (statusSelect && statusDescription) {
                statusSelect.addEventListener('change', function() {
                    // برای وضعیت "رد شده" توضیحات الزامی است
                    if (this.value === 'rejected') {
                        statusDescription.setAttribute('required', 'required');
                        statusDescription.parentElement.querySelector('.form-text').textContent = 'توضیحات دلیل رد درخواست (الزامی)';
                    } else {
                        statusDescription.removeAttribute('required');
                        statusDescription.parentElement.querySelector('.form-text').textContent = 'توضیحاتی در مورد دلیل تغییر وضعیت یا اقدامات مورد نیاز برای تکمیل فرآیند.';
                    }
                });
                
                // اجرای اولیه
                if (statusSelect.value === 'rejected') {
                    statusDescription.setAttribute('required', 'required');
                    statusDescription.parentElement.querySelector('.form-text').textContent = 'توضیحات دلیل رد درخواست (الزامی)';
                }
            }
        })();
    </script>
</body>
</html>