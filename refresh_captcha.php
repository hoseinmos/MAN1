<?php
// refresh_captcha.php - ادغام شده با get_captcha.php
// پشتیبانی از هر دو فرمت پاسخ (متنی ساده و JSON)

// شروع نشست اگر هنوز فعال نشده باشد
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// تولید یک عدد تصادفی جدید به عنوان کپچا
$captcha_num = rand(1000, 9999);
$_SESSION['captcha'] = $captcha_num;

// بررسی نوع پاسخ درخواستی (JSON یا متن ساده)
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    // ارسال پاسخ JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'captcha' => $captcha_num
    ]);
} else {
    // ارسال پاسخ متنی ساده (سازگار با نسخه قبلی)
    echo $captcha_num;
}
?>