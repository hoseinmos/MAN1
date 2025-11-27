<?php
// logout.php - بروزرسانی شده برای حذف نشست از پایگاه داده
include 'config.php';

// اگر کاربر وارد نشده است
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// حذف نشست از پایگاه داده
$session_id = session_id();
$sql = "DELETE FROM sessions WHERE session_id = ?";
if($conn && $stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $stmt->close();
}

// ثبت خروج در لاگ
if(isset($_SESSION['user_id'])) {
    $sql = "INSERT INTO user_logs (user_id, action, ip_address, log_time) 
            VALUES (?, 'logout', ?, NOW())";
    if($conn && $stmt = $conn->prepare($sql)) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("is", $user_id, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// پاک‌سازی تمام متغیرهای نشست
$_SESSION = array();

// اگر از کوکی نشست استفاده می‌شود، آن را نیز حذف کنید
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// نابودی نشست
session_destroy();

// هدایت به صفحه ورود با پیام موفقیت
session_start();
$_SESSION['success'] = "شما با موفقیت از سیستم خارج شدید.";
header("location: index.php");
exit;
?>