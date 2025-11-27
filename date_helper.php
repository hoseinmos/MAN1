<?php
// date_helper.php - توابع کمکی برای تبدیل تاریخ

// اطمینان از وجود فایل jdf.php
if (!function_exists('jdate')) {
    require_once 'jdf.php';
}

/**
 * تبدیل تاریخ میلادی به شمسی با فرمت دلخواه
 * 
 * @param string $date تاریخ میلادی (YYYY-MM-DD یا timestamp)
 * @param string $format فرمت خروجی تاریخ شمسی (پیش‌فرض: Y/m/d)
 * @return string تاریخ شمسی
 */
function to_jalali($date, $format = 'Y/m/d') {
    if (empty($date)) return '';
    
    if (is_numeric($date)) {
        // تاریخ به صورت timestamp است
        return jdate($format, $date);
    }
    
    // تبدیل رشته تاریخ به timestamp
    if (strpos($date, '-') !== false) {
        // قالب YYYY-MM-DD
        $parts = explode('-', $date);
        if (count($parts) === 3) {
            $timestamp = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0]);
            return jdate($format, $timestamp);
        }
    } elseif (strpos($date, '/') !== false) {
        // قالب YYYY/MM/DD
        $parts = explode('/', $date);
        if (count($parts) === 3) {
            $timestamp = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0]);
            return jdate($format, $timestamp);
        }
    } elseif (strpos($date, ' ') !== false) {
        // قالب YYYY-MM-DD HH:MM:SS
        $datetime = explode(' ', $date);
        $datepart = explode('-', $datetime[0]);
        $timepart = isset($datetime[1]) ? explode(':', $datetime[1]) : [0, 0, 0];
        
        if (count($datepart) === 3) {
            $hour = isset($timepart[0]) ? $timepart[0] : 0;
            $minute = isset($timepart[1]) ? $timepart[1] : 0;
            $second = isset($timepart[2]) ? $timepart[2] : 0;
            
            $timestamp = mktime($hour, $minute, $second, $datepart[1], $datepart[2], $datepart[0]);
            return jdate($format, $timestamp);
        }
    }
    
    // اگر فرمت‌های بالا تشخیص داده نشد، از strtotime استفاده می‌کنیم
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date; // برگرداندن مقدار اصلی اگر تبدیل ناموفق بود
    }
    
    return jdate($format, $timestamp);
}

/**
 * تبدیل تاریخ شمسی به میلادی
 * 
 * @param string $jalali_date تاریخ شمسی (YYYY/MM/DD)
 * @param string $format فرمت خروجی تاریخ میلادی (پیش‌فرض: Y-m-d)
 * @return string تاریخ میلادی
 */
function to_gregorian($jalali_date, $format = 'Y-m-d') {
    if (empty($jalali_date)) return '';
    
    // تقسیم تاریخ شمسی به اجزای آن
    $parts = explode('/', $jalali_date);
    if (count($parts) !== 3) {
        return $jalali_date; // برگرداندن مقدار اصلی اگر فرمت نامعتبر است
    }
    
    // تبدیل به میلادی با استفاده از تابع jalali_to_gregorian
    list($gy, $gm, $gd) = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
    
    // فرمت‌دهی خروجی
    if ($format === 'Y-m-d') {
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    } elseif ($format === 'timestamp') {
        return mktime(0, 0, 0, $gm, $gd, $gy);
    } else {
        return date($format, mktime(0, 0, 0, $gm, $gd, $gy));
    }
}

/**
 * تبدیل تاریخ و زمان میلادی به شمسی
 * 
 * @param string $datetime تاریخ و زمان میلادی (YYYY-MM-DD HH:MM:SS)
 * @param string $format فرمت خروجی تاریخ شمسی (پیش‌فرض: Y/m/d H:i:s)
 * @return string تاریخ و زمان شمسی
 */
function datetime_to_jalali($datetime, $format = 'Y/m/d H:i:s') {
    if (empty($datetime)) return '';
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime; // برگرداندن مقدار اصلی اگر تبدیل ناموفق بود
    }
    
    return jdate($format, $timestamp);
}

/**
 * نمایش تاریخ شمسی به صورت خوانا (مثلاً "۲۳ فروردین ۱۴۰۲")
 * 
 * @param string $date تاریخ میلادی (YYYY-MM-DD یا timestamp)
 * @return string تاریخ شمسی خوانا
 */
function readable_jalali_date($date) {
    if (empty($date)) return '';
    
    if (is_numeric($date)) {
        return jdate('d F Y', $date);
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    
    return jdate('d F Y', $timestamp);
}

/**
 * تبدیل اعداد انگلیسی به فارسی
 * 
 * @param string|int $input متن یا عدد ورودی
 * @return string متن با اعداد فارسی
 */
function en_to_fa_numbers($input) {
    $fa_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    return str_replace($en_digits, $fa_digits, $input);
}

/**
 * تبدیل اعداد فارسی به انگلیسی
 * 
 * @param string|int $input متن یا عدد ورودی
 * @return string متن با اعداد انگلیسی
 */
function fa_to_en_numbers($input) {
    $fa_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    return str_replace($fa_digits, $en_digits, $input);
}