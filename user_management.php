<?php
// user_management.php - صفحه مدیریت کاربران
include 'config.php';
include 'jdf.php'; // اضافه کردن فایل jdf.php برای تاریخ شمسی

// بررسی دسترسی کاربر
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// بررسی اینکه کاربر مدیر سیستم باشد
if(!isset($_SESSION["user_description"]) || $_SESSION["user_description"] !== "مدیر سیستم"){
    header("location: dashboard.php?access=denied");
    exit;
}

// بررسی تایم‌اوت نشست
check_session_timeout(1800);

// به‌روزرسانی فعالیت در پایگاه داده
update_session_activity();

// متغیرها برای پیام‌ها
$success_message = "";
$error_message = "";

// اگر فرم افزودن یا ویرایش کاربر ارسال شده باشد
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی: دسترسی غیرمجاز";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        // متغیرهای فرم
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $username = sanitize_input($_POST['username']);
        $access_code = sanitize_input($_POST['access_code']);
        $description = sanitize_input($_POST['description']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // اعتبارسنجی ورودی‌ها
        if (empty($username) || empty($access_code) || empty($description)) {
            $error_message = "همه فیلدهای ضروری را پر کنید.";
        } else {
            // افزودن کاربر جدید
            if ($action === 'add') {
                // بررسی تکراری نبودن نام کاربری
                $check_sql = "SELECT id FROM access_codes WHERE username = ?";
                if ($check_stmt = $conn->prepare($check_sql)) {
                    $check_stmt->bind_param("s", $username);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $error_message = "نام کاربری قبلاً استفاده شده است.";
                    } else {
                        // درج کاربر جدید
                        $insert_sql = "INSERT INTO access_codes (code, username, description, active, created_at) 
                                     VALUES (?, ?, ?, ?, NOW())";
                        
                        if ($insert_stmt = $conn->prepare($insert_sql)) {
                            $insert_stmt->bind_param("sssi", $access_code, $username, $description, $active);
                            
                            if ($insert_stmt->execute()) {
                                $success_message = "کاربر جدید با موفقیت افزوده شد.";
                                
                                // ثبت لاگ
                                $log_sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) 
                                          VALUES (?, 'add_user', ?, NOW())";
                                
                                if ($log_stmt = $conn->prepare($log_sql)) {
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $log_stmt->bind_param("is", $_SESSION['user_id'], $ip);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                            } else {
                                $error_message = "خطا در افزودن کاربر: " . $insert_stmt->error;
                            }
                            
                            $insert_stmt->close();
                        } else {
                            $error_message = "خطا در آماده‌سازی درخواست افزودن کاربر.";
                        }
                    }
                    
                    $check_stmt->close();
                } else {
                    $error_message = "خطا در بررسی تکراری بودن نام کاربری.";
                }
            }
            // ویرایش کاربر موجود
            elseif ($action === 'edit' && $user_id > 0) {
                // بررسی تکراری نبودن نام کاربری (به جز خود کاربر)
                $check_sql = "SELECT id FROM access_codes WHERE username = ? AND id != ?";
                if ($check_stmt = $conn->prepare($check_sql)) {
                    $check_stmt->bind_param("si", $username, $user_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $error_message = "نام کاربری قبلاً استفاده شده است.";
                    } else {
                        // به‌روزرسانی کاربر
                        $update_sql = "UPDATE access_codes 
                                      SET code = ?, username = ?, description = ?, active = ? 
                                      WHERE id = ?";
                        
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("sssii", $access_code, $username, $description, $active, $user_id);
                            
                            if ($update_stmt->execute()) {
                                $success_message = "اطلاعات کاربر با موفقیت به‌روزرسانی شد.";
                                
                                // ثبت لاگ
                                $log_sql = "INSERT INTO user_logs (user_id, action, action_id, ip_address, log_time) 
                                          VALUES (?, 'edit_user', ?, ?, NOW())";
                                
                                if ($log_stmt = $conn->prepare($log_sql)) {
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $log_stmt->bind_param("iis", $_SESSION['user_id'], $user_id, $ip);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                            } else {
                                $error_message = "خطا در به‌روزرسانی کاربر: " . $update_stmt->error;
                            }
                            
                            $update_stmt->close();
                        } else {
                            $error_message = "خطا در آماده‌سازی درخواست به‌روزرسانی کاربر.";
                        }
                    }
                    
                    $check_stmt->close();
                } else {
                    $error_message = "خطا در بررسی تکراری بودن نام کاربری.";
                }
            }
        }
    }
}

// دریافت لیست کاربران
$users = array();
if ($conn) {
    $sql = "SELECT * FROM access_codes ORDER BY id ASC";
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    } else {
        $error_message = "خطا در دریافت لیست کاربران: " . $conn->error;
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
    <title>مدیریت کاربران</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .page-header {
            margin-bottom: 2rem;
        }
        
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        
        .btn-action {
            margin-right: 5px;
        }
        
        .user-status-active {
            color: #28a745;
        }
        
        .user-status-inactive {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h2>مدیریت کاربران</h2>
                    <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
                </div>
                
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- کارت افزودن کاربر جدید -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">افزودن کاربر جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">نام کاربری:</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="access_code" class="form-label">کد دسترسی:</label>
                                        <input type="text" class="form-control" id="access_code" name="access_code" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">توضیحات:</label>
                                        <input type="text" class="form-control" id="description" name="description" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 mt-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                                            <label class="form-check-label" for="active">فعال</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">افزودن کاربر</button>
                        </form>
                    </div>
                </div>
                
                <!-- جدول لیست کاربران -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">لیست کاربران</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام کاربری</th>
                                        <th>کد دسترسی</th>
                                        <th>توضیحات</th>
                                        <th>وضعیت</th>
                                        <th>تاریخ ایجاد</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">هیچ کاربری یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username'] ?? 'تعریف نشده'); ?></td>
                                                <td><?php echo htmlspecialchars($user['code']); ?></td>
                                                <td><?php echo htmlspecialchars($user['description']); ?></td>
                                                <td>
                                                    <?php if ($user['active'] == 1): ?>
                                                        <span class="user-status-active"><i class="fas fa-check-circle me-1"></i> فعال</span>
                                                    <?php else: ?>
                                                        <span class="user-status-inactive"><i class="fas fa-times-circle me-1"></i> غیرفعال</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo jdate('Y/m/d H:i', strtotime($user['created_at']), '', 'Asia/Tehran', 'fa'); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info btn-action" 
                                                            onclick="editUser(<?php echo $user['id']; ?>, 
                                                                            '<?php echo htmlspecialchars($user['username'] ?? ''); ?>', 
                                                                            '<?php echo htmlspecialchars($user['code']); ?>', 
                                                                            '<?php echo htmlspecialchars($user['description']); ?>', 
                                                                            <?php echo $user['active']; ?>)">
                                                        <i class="fas fa-edit"></i> ویرایش
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مودال ویرایش کاربر -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">ویرایش کاربر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="editUserForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">نام کاربری:</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_access_code" class="form-label">کد دسترسی:</label>
                            <input type="text" class="form-control" id="edit_access_code" name="access_code" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">توضیحات:</label>
                            <input type="text" class="form-control" id="edit_description" name="description" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_active" name="active">
                                <label class="form-check-label" for="edit_active">فعال</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('editUserForm').submit()">ذخیره تغییرات</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // تابع باز کردن مودال ویرایش کاربر
        function editUser(id, username, code, description, active) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_access_code').value = code;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_active').checked = active === 1;
            
            var editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editUserModal.show();
        }
    </script>
</body>
</html>