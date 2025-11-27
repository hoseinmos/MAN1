<?php
// warehouse_management.php - صفحه مدیریت انبار
include 'config.php'; //
// تابع jdf.php اکنون از طریق date_helper.php در config.php شامل می‌شود.

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ //
    header("location: index.php"); //
    exit; //
}

// بررسی اینکه کاربر مسئول انبار باشد
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مسئول انبار"){ //
    header("location: dashboard.php?access=denied"); //
    exit; //
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800); //

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity(); //

// تنظیمات صفحه
$page_title = "مدیریت انبار"; //
$today_jalali = getMySQLJalaliDateTime('Y/m/d'); //

// بررسی اتصال به پایگاه داده
if (!$conn) { //
    echo "<div class='alert alert-danger'>خطا در اتصال به پایگاه داده. لطفاً دوباره تلاش کنید.</div>"; //
    exit; //
}

// دریافت لیست کاربران
$users = array(); //
if($conn) { //
    $sql = "SELECT id, code, description FROM access_codes WHERE active = 1 ORDER BY description"; //
    $result = $conn->query($sql); //
    if($result && $result->num_rows > 0) { //
        while($row = $result->fetch_assoc()) { //
            $users[] = $row; //
        }
    }
}

// تولید توکن CSRF
$csrf_token = generate_csrf_token(); //
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
</head>
<body class="dashboard-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <h2><?php echo $page_title; ?></h2>
                    <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
                </div>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div id="message-area"></div>
                
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="warehouseTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="assign-rolls-tab" data-bs-toggle="tab" data-bs-target="#assign-rolls" type="button" role="tab" aria-controls="assign-rolls" aria-selected="true">
                                    <i class="fas fa-paper-plane me-2"></i>تخصیص رول کاغذ
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="roll-assignments-tab" data-bs-toggle="tab" data-bs-target="#roll-assignments" type="button" role="tab" aria-controls="roll-assignments" aria-selected="false">
                                    <i class="fas fa-history me-2"></i>سوابق تخصیص
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab" aria-controls="reports" aria-selected="false">
                                    <i class="fas fa-chart-bar me-2"></i>گزارش‌گیری
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-4" id="warehouseTabsContent">
                            <div class="tab-pane fade show active" id="assign-rolls" role="tabpanel" aria-labelledby="assign-rolls-tab">
                                <form id="assignRollForm" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="assign_roll">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="user_id" class="form-label">کاربر دریافت کننده:</label>
                                                <select class="form-select" id="user_id" name="user_id" required>
                                                    <option value="">انتخاب کنید...</option>
                                                    <?php foreach($users as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['description']); ?> (<?php echo htmlspecialchars($user['code']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="invalid-feedback">لطفاً کاربر را انتخاب کنید.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">تعداد رول:</label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                                                <div class="invalid-feedback">لطفاً تعداد رول را مشخص کنید.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="assign_date" class="form-label">تاریخ تخصیص:</label>
                                                <input type="text" class="form-control persian-date" id="assign_date" name="assign_date" value="<?php echo $today_jalali; ?>" required>
                                                <div class="invalid-feedback">لطفاً تاریخ را مشخص کنید.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="description" class="form-label">توضیحات (اختیاری):</label>
                                                <textarea class="form-control" id="description" name="description" rows="1"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-success" id="assignRollBtn">
                                            <i class="fas fa-check-circle me-2"></i>ثبت تخصیص رول
                                        </button>
                                    </div>
                                </form>
                                
                                <div id="assignmentResult" class="mt-4"></div>
                            </div>
                            
                            <div class="tab-pane fade" id="roll-assignments" role="tabpanel" aria-labelledby="roll-assignments-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>سوابق تخصیص رول کاغذ</h5>
                                    <div class="d-flex gap-2">
                                        <select id="userFilter" class="form-select form-select-sm" style="width: auto;">
                                            <option value="">همه کاربران</option>
                                            <?php foreach($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['description']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                                            <option value="">همه وضعیت‌ها</option>
                                            <option value="1">تایید شده</option>
                                            <option value="0">در انتظار تایید</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>کاربر</th>
                                                <th>تعداد</th>
                                                <th>تاریخ تخصیص</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ تایید</th>
                                                <th>توضیحات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assignmentsList">
                                            <tr>
                                                <td colspan="6" class="text-center">در حال بارگذاری...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div id="assignmentPagination" class="d-flex justify-content-center mt-4">
                                    </div>
                            </div>
                            
                            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">گزارش‌گیری از تخصیص رول کاغذ</h5>
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
                                                        <label for="report_user" class="form-label">کاربر:</label>
                                                        <select class="form-select" id="report_user" name="report_user">
                                                            <option value="">همه کاربران</option>
                                                            <?php foreach($users as $user): ?>
                                                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['description']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="report_status" class="form-label">وضعیت تایید:</label>
                                                        <select class="form-select" id="report_status" name="report_status">
                                                            <option value="">همه وضعیت‌ها</option>
                                                            <option value="1">تایید شده</option>
                                                            <option value="0">در انتظار تایید</option>
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
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/persian-date@latest/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="warehouse.js"></script>
</body>
</html>