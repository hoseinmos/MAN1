<?php
// device_report.php - گزارش خرابی دستگاه‌ها
include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    header("location: index.php"); //
    exit; //
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800); //

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// تولید توکن CSRF
$csrf_token = generate_csrf_token(); //
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش دستگاه خراب</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .optional-field {
            border-color: #6c757d !important;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <h2>گزارش دستگاه خراب</h2>
                    <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>ثبت گزارش خرابی دستگاه</h5>
                    </div>
                    <div class="card-body">
                        <form id="deviceReportForm" action="process_device_report.php" method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="terminal_serial" class="form-label">سریال دستگاه: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="terminal_serial" name="terminal_serial">
                                    <div class="invalid-feedback">لطفاً سریال دستگاه را وارد کنید.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="adapter_serial" class="form-label">سریال آداپتور:</label>
                                    <input type="text" class="form-control" id="adapter_serial" name="adapter_serial">
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">وضعیت دستگاه: <span class="text-danger">*</span></label>
                                    <div class="card p-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" id="device_healthy" name="device_status" value="healthy" onchange="toggleDamageOptions()">
                                            <label class="form-check-label" for="device_healthy">دستگاه سالم است</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="device_damaged" name="device_status" value="damaged" onchange="toggleDamageOptions()">
                                            <label class="form-check-label" for="device_damaged">دستگاه خراب است</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="damage_type_section" style="display: none;">
                                    <label class="form-label">نوع خرابی:</label>
                                    <div class="card p-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="is_terminal_damaged" name="is_terminal_damaged" value="1">
                                            <label class="form-check-label" for="is_terminal_damaged">دستگاه خراب است</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_adapter_damaged" name="is_adapter_damaged" value="1">
                                            <label class="form-check-label" for="is_adapter_damaged">آداپتور خراب است</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="damage_description" class="form-label" id="description_label">شرح خرابی: <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="damage_description" name="damage_description" rows="4"></textarea>
                                    <div class="invalid-feedback">لطفاً شرح خرابی را وارد کنید.</div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-danger" id="submitReportBtn">
                                    <i class="fas fa-paper-plane me-2"></i>ارسال گزارش
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>گزارش‌های ارسالی من</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>سریال دستگاه</th>
                                        <th>نوع خرابی</th>
                                        <th>وضعیت</th>
                                        <th>تاریخ ثبت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="userReportsList">
                                    <tr>
                                        <td colspan="6" class="text-center">در حال بارگذاری...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="reportDetailsModal" tabindex="-1" aria-labelledby="reportDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportDetailsModalLabel">جزئیات گزارش خرابی</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body" id="reportDetailsContent">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="device_report.js"></script>
    <script>
    function toggleDamageOptions() {
        const deviceStatus = document.querySelector('input[name="device_status"]:checked');
        const damageTypeSection = document.getElementById('damage_type_section');
        const damageDescription = document.getElementById('damage_description');
        const descriptionLabel = document.getElementById('description_label');
        
        if (deviceStatus && deviceStatus.value === 'healthy') {
            // دستگاه سالم است
            damageTypeSection.style.display = 'none';
            damageDescription.placeholder = 'توضیحات (اختیاری)';
            damageDescription.required = false;
            descriptionLabel.innerHTML = 'توضیحات:';
            
            // پاک کردن انتخاب‌های خرابی
            document.getElementById('is_terminal_damaged').checked = false;
            document.getElementById('is_adapter_damaged').checked = false;
        } else if (deviceStatus && deviceStatus.value === 'damaged') {
            // دستگاه خراب است
            damageTypeSection.style.display = 'block';
            damageDescription.placeholder = 'لطفاً شرح خرابی را وارد کنید';
            damageDescription.required = true;
            descriptionLabel.innerHTML = 'شرح خرابی: <span class="text-danger">*</span>';
        }
    }
    
    // اجرای اولیه برای تنظیم صحیح فرم
    document.addEventListener('DOMContentLoaded', function() {
        toggleDamageOptions();
    });
    </script>
</body>
</html>