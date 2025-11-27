<?php
// marketing_view.php - صفحه نمایش جزئیات بازاریابی
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

// متغیرهای مورد نیاز
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION["user_description"] === "مدیر سیستم");
$marketing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$marketing_data = [];

// دریافت اطلاعات بازاریابی
if($marketing_id > 0 && $conn) {
    $sql = "SELECT m.*, 
            u_created.description as created_by_name, 
            u_updated.description as updated_by_name
            FROM marketing m
            LEFT JOIN access_codes u_created ON m.created_by = u_created.id
            LEFT JOIN access_codes u_updated ON m.updated_by = u_updated.id
            WHERE m.id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $marketing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $marketing_data = $result->fetch_assoc();
            
            // تبدیل تاریخ‌ها به شمسی
            $marketing_data['created_at'] = convert_to_jalali($marketing_data['created_at']);
            if(!empty($marketing_data['updated_at'])) {
                $marketing_data['updated_at'] = convert_to_jalali($marketing_data['updated_at']);
            }
            if(!empty($marketing_data['birth_date'])) {
                $birth_date = explode('-', $marketing_data['birth_date']);
                $marketing_data['birth_date_jalali'] = gregorian_to_jalali($birth_date[0], $birth_date[1], $birth_date[2], '/');
            } else {
                $marketing_data['birth_date_jalali'] = '';
            }
            
            // کنترل دسترسی (فقط مدیر یا ایجاد کننده)
            if(!$is_admin && $marketing_data['created_by'] != $user_id) {
                $_SESSION['error'] = "شما اجازه مشاهده این بازاریابی را ندارید.";
                header("Location: marketing_list.php");
                exit;
            }
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
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده بازاریابی</title>
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
                        <h2><i class="fas fa-eye me-2"></i> مشاهده بازاریابی</h2>
                        <div>
                            <a href="marketing.php?id=<?php echo $marketing_id; ?>" class="btn btn-warning me-2">
                                <i class="fas fa-edit me-1"></i> ویرایش
                            </a>
                            <a href="marketing_list.php" class="btn btn-light">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به لیست
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- وضعیت بازاریابی -->
                        <div class="status-section mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5>وضعیت بازاریابی: 
                                                <?php if($marketing_data['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">در انتظار بررسی</span>
                                                <?php elseif($marketing_data['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">تأیید شده</span>
                                                <?php elseif($marketing_data['status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">رد شده</span>
                                                <?php endif; ?>
                                            </h5>
                                            
                                            <?php if(!empty($marketing_data['status_description'])): ?>
                                                <p class="text-muted mt-2">
                                                    <strong>توضیحات وضعیت:</strong> <?php echo nl2br(htmlspecialchars($marketing_data['status_description'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <p class="mb-1">
                                                <strong>ثبت توسط:</strong> <?php echo htmlspecialchars($marketing_data['created_by_name']) ?: 'نامشخص'; ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>تاریخ ثبت:</strong> <?php echo $marketing_data['created_at']; ?>
                                            </p>
                                            <?php if(!empty($marketing_data['updated_at'])): ?>
                                                <p class="mb-0">
                                                    <strong>آخرین به‌روزرسانی:</strong> <?php echo $marketing_data['updated_at']; ?>
                                                    <?php if(!empty($marketing_data['updated_by_name'])): ?>
                                                        توسط <?php echo htmlspecialchars($marketing_data['updated_by_name']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات شخصی -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">اطلاعات شخصی</h4>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>کد ملی:</strong> <?php echo htmlspecialchars($marketing_data['national_code']); ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>نام:</strong> <?php echo htmlspecialchars($marketing_data['first_name']); ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>نام خانوادگی:</strong> <?php echo htmlspecialchars($marketing_data['last_name']); ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>تاریخ تولد:</strong> <?php echo !empty($marketing_data['birth_date_jalali']) ? htmlspecialchars($marketing_data['birth_date_jalali']) : '-'; ?></p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>شهر:</strong> <?php echo !empty($marketing_data['city']) ? htmlspecialchars($marketing_data['city']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>تلفن ثابت:</strong> <?php echo !empty($marketing_data['phone']) ? htmlspecialchars($marketing_data['phone']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>موبایل:</strong> <?php echo htmlspecialchars($marketing_data['mobile']); ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>کد پستی:</strong> <?php echo !empty($marketing_data['postal_code']) ? htmlspecialchars($marketing_data['postal_code']) : '-'; ?></p>
                                        </div>
                                    </div>
                                    <?php if(!empty($marketing_data['address'])): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <p><strong>آدرس:</strong> <?php echo htmlspecialchars($marketing_data['address']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات کسب و کار -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">اطلاعات کسب و کار</h4>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>صنف:</strong> <?php echo !empty($marketing_data['business_type']) ? htmlspecialchars($marketing_data['business_type']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>نام فروشگاه:</strong> <?php echo htmlspecialchars($marketing_data['store_name']); ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>کد مالیاتی:</strong> <?php echo !empty($marketing_data['tax_code']) ? htmlspecialchars($marketing_data['tax_code']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>مدل دستگاه:</strong> <?php echo !empty($marketing_data['device_model']) ? htmlspecialchars($marketing_data['device_model']) : '-'; ?></p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>تعداد پایانه:</strong> <?php echo !empty($marketing_data['terminal_count']) ? htmlspecialchars($marketing_data['terminal_count']) : '1'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>پشتیبان:</strong> <?php echo !empty($marketing_data['support_person']) ? htmlspecialchars($marketing_data['support_person']) : '-'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات بانکی -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">اطلاعات بانکی</h4>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>نام بانک:</strong> <?php echo !empty($marketing_data['bank_name']) ? htmlspecialchars($marketing_data['bank_name']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>کد شعبه:</strong> <?php echo !empty($marketing_data['branch_code']) ? htmlspecialchars($marketing_data['branch_code']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>شماره حساب:</strong> <?php echo !empty($marketing_data['account_number']) ? htmlspecialchars($marketing_data['account_number']) : '-'; ?></p>
                                        </div>
                                        <div class="col-md-3 col-sm-6">
                                            <p><strong>شماره شبا:</strong> <?php echo !empty($marketing_data['sheba_number']) ? htmlspecialchars($marketing_data['sheba_number']) : '-'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- مدارک -->
                        <div class="form-section mb-4">
                            <h4 class="form-section-title">مدارک</h4>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row document-gallery">
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
                        
                        <!-- دکمه‌های عملیات -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="marketing_list.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-right me-1"></i> بازگشت به لیست
                            </a>
                            <div>
                                <a href="marketing.php?id=<?php echo $marketing_id; ?>" class="btn btn-warning me-2">
                                    <i class="fas fa-edit me-1"></i> ویرایش
                                </a>
                                <?php if($is_admin || $marketing_data['created_by'] == $user_id): ?>
                                    <a href="marketing_list.php?delete=<?php echo $marketing_id; ?>" class="btn btn-danger" onclick="return confirm('آیا از حذف این بازاریابی اطمینان دارید؟');">
                                        <i class="fas fa-trash me-1"></i> حذف
                                    </a>
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
</body>
</html>