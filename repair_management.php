<?php
// repair_management.php - صفحه مدیریت تعمیرات
include 'config.php';
include 'jdf.php';

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی اینکه کاربر مسئول تعمیرات باشد
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مسئول تعمیرات"){
    header("location: dashboard.php?access=denied");
    exit;
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// تنظیمات صفحه
$page_title = "مدیریت تعمیرات";
$today_jalali = jdate('Y/m/d', time(), '', 'Asia/Tehran', 'fa');

// تولید توکن CSRF
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css">
    <style>
        .repair-status-pending { color: #dc3545; }
        .repair-status-in_progress { color: #fd7e14; }
        .repair-status-repaired { color: #198754; }
        .repair-status-replaced { color: #0d6efd; }
        .repair-status-returned { color: #6c757d; }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 9px;
            width: 2px;
            background-color: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 3px;
            left: -30px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid #0d6efd;
        }
        
        .timeline-date {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <h2><?php echo $page_title; ?></h2>
                    <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
                </div>
                
                <!-- نوار منو -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="repairTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-repairs-tab" data-bs-toggle="tab" data-bs-target="#pending-repairs" type="button" role="tab" aria-controls="pending-repairs" aria-selected="true">
                                    <i class="fas fa-tools me-2"></i>تعمیرات در انتظار
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="all-repairs-tab" data-bs-toggle="tab" data-bs-target="#all-repairs" type="button" role="tab" aria-controls="all-repairs" aria-selected="false">
                                    <i class="fas fa-list me-2"></i>همه تعمیرات
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab" aria-controls="search" aria-selected="false">
                                    <i class="fas fa-search me-2"></i>جستجو
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab" aria-controls="reports" aria-selected="false">
                                    <i class="fas fa-chart-bar me-2"></i>گزارش‌گیری
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-4" id="repairTabsContent">
                            <!-- تب تعمیرات در انتظار -->
                            <div class="tab-pane fade show active" id="pending-repairs" role="tabpanel" aria-labelledby="pending-repairs-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>تعمیرات در انتظار بررسی</h5>
                                    <button type="button" class="btn btn-sm btn-primary" id="refreshPendingBtn">
                                        <i class="fas fa-sync-alt me-2"></i>به‌روزرسانی
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>سریال دستگاه</th>
                                                <th>سریال آداپتور</th>
                                                <th>نوع خرابی</th>
                                                <th>گزارش دهنده</th>
                                                <th>تاریخ گزارش</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pendingRepairsList">
                                            <tr>
                                                <td colspan="7" class="text-center">در حال بارگذاری...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- تب همه تعمیرات -->
                            <div class="tab-pane fade" id="all-repairs" role="tabpanel" aria-labelledby="all-repairs-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>همه تعمیرات</h5>
                                    <div class="d-flex gap-2">
                                        <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                                            <option value="">همه وضعیت‌ها</option>
                                            <option value="pending">در انتظار</option>
                                            <option value="in_progress">در حال تعمیر</option>
                                            <option value="repaired">تعمیر شده</option>
                                            <option value="replaced">تعویض شده</option>
                                            <option value="returned">برگشت داده شده</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>سریال دستگاه</th>
                                                <th>نوع خرابی</th>
                                                <th>وضعیت</th>
                                                <th>گزارش دهنده</th>
                                                <th>تاریخ گزارش</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="allRepairsList">
                                            <tr>
                                                <td colspan="7" class="text-center">در حال بارگذاری...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div id="repairPagination" class="d-flex justify-content-center mt-4">
                                    <!-- دکمه‌های صفحه‌بندی اینجا نمایش داده می‌شوند -->
                                </div>
                            </div>
                            
                            <!-- تب جستجو -->
                            <div class="tab-pane fade" id="search" role="tabpanel" aria-labelledby="search-tab">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">جستجوی دستگاه</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="searchForm">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="terminal_serial" class="form-label">سریال دستگاه:</label>
                                                        <input type="text" class="form-control" id="terminal_serial" name="terminal_serial">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="adapter_serial" class="form-label">سریال آداپتور:</label>
                                                        <input type="text" class="form-control" id="adapter_serial" name="adapter_serial">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="text-center">
                                                <button type="button" class="btn btn-primary" id="searchBtn">
                                                    <i class="fas fa-search me-2"></i>جستجو
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <div id="searchResult" class="mt-4">
                                    <!-- نتیجه جستجو اینجا نمایش داده می‌شود -->
                                </div>
                            </div>
                            
                        <!-- تب گزارش‌گیری -->
                        <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">گزارش‌گیری از تعمیرات</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="reportForm">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="from_date" class="form-label">از تاریخ:</label>
                                                        <input type="text" class="form-control persian-date" id="from_date" name="from_date">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="to_date" class="form-label">تا تاریخ:</label>
                                                        <input type="text" class="form-control persian-date" id="to_date" name="to_date" value="<?php echo $today_jalali; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="report_status" class="form-label">وضعیت تعمیر:</label>
                                                        <select class="form-select" id="report_status" name="report_status">
                                                            <option value="">همه وضعیت‌ها</option>
                                                            <option value="pending">در انتظار</option>
                                                            <option value="in_progress">در حال تعمیر</option>
                                                            <option value="repaired">تعمیر شده</option>
                                                            <option value="replaced">تعویض شده</option>
                                                            <option value="returned">برگشت داده شده</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="damage_type" class="form-label">نوع خرابی:</label>
                                                        <select class="form-select" id="damage_type" name="damage_type">
                                                            <option value="">همه انواع</option>
                                                            <option value="terminal">دستگاه</option>
                                                            <option value="adapter">آداپتور</option>
                                                            <option value="both">هر دو</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="text-center">
                                                <button type="button" class="btn btn-primary" id="generateReportBtn">
                                                    <i class="fas fa-chart-bar me-2"></i>تولید گزارش
                                                </button>
                                                <button type="button" class="btn btn-success ms-2" id="exportReportBtn">
                                                    <i class="fas fa-file-excel me-2"></i>خروجی Excel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <div id="reportResult" class="mt-4">
                                    <!-- نتیجه گزارش اینجا نمایش داده می‌شود -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مودال جزئیات و بروزرسانی تعمیر -->
    <div class="modal fade" id="repairModal" tabindex="-1" aria-labelledby="repairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="repairModalLabel">جزئیات تعمیر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="repairDetails">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">در حال بارگذاری...</span>
                            </div>
                            <p class="mt-2">در حال دریافت اطلاعات...</p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <form id="updateRepairForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="repair_id" id="repair_id" value="">
                        
                        <div class="mb-3">
                            <label for="repair_status" class="form-label">وضعیت تعمیر:</label>
                            <select class="form-select" id="repair_status" name="repair_status" required>
                                <option value="in_progress">در حال تعمیر</option>
                                <option value="repaired">تعمیر شده</option>
                                <option value="replaced">تعویض شده</option>
                                <option value="returned">برگشت داده شده</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="technician_notes" class="form-label">توضیحات تعمیرکار:</label>
                            <textarea class="form-control" id="technician_notes" name="technician_notes" rows="3" required></textarea>
                            <div class="form-text">لطفاً اقدامات انجام شده، قطعات تعویضی و هرگونه توضیح ضروری را وارد کنید.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="repair_date" class="form-label">تاریخ تعمیر:</label>
                            <input type="text" class="form-control persian-date" id="repair_date" name="repair_date" value="<?php echo $today_jalali; ?>" required>
                        </div>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" id="updateRepairBtn">
                                <i class="fas fa-save me-2"></i>ثبت تغییرات
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/persian-date@latest/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="repair.js"></script>
</body>
</html>