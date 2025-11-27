<?php
function get_device_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // شناسایی دقیق‌تر مرورگر
    $browser = 'نامشخص';
    if (preg_match('/MSIE|Trident|Edge/i', $user_agent)) {
        $browser = 'Internet Explorer/Edge';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edg|OPR|Opera/i', $user_agent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome|Edg|OPR|Opera/i', $user_agent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/Opera|OPR/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Edg/i', $user_agent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/SamsungBrowser/i', $user_agent)) {
        $browser = 'Samsung Browser';
    } elseif (preg_match('/UCBrowser/i', $user_agent)) {
        $browser = 'UC Browser';
    }
    
    // شناسایی سیستم عامل
    $os = 'نامشخص';
    if (preg_match('/Windows NT 10.0/i', $user_agent)) {
        $os = 'Windows 10';
    } elseif (preg_match('/Windows NT 6.3/i', $user_agent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/i', $user_agent)) {
        $os = 'Windows 8';
    } elseif (preg_match('/Windows NT 6.1/i', $user_agent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/Windows NT/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $user_agent)) {
        $os = 'MacOS';
    } elseif (preg_match('/Linux/i', $user_agent) && !preg_match('/Android/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android/i', $user_agent)) {
        // استخراج نسخه اندروید
        preg_match('/Android\s([0-9.]+)/i', $user_agent, $matches);
        $version = isset($matches[1]) ? $matches[1] : '';
        $os = 'Android' . ($version ? ' ' . $version : '');
    } elseif (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
        // استخراج نسخه iOS
        preg_match('/OS\s([0-9_]+)/i', $user_agent, $matches);
        $version = isset($matches[1]) ? str_replace('_', '.', $matches[1]) : '';
        $os = 'iOS' . ($version ? ' ' . $version : '');
    }
    
    // شناسایی نوع دستگاه
    $device_type = 'Desktop';
    if (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent)) {
        $device_type = 'Mobile';
        
        // شناسایی دقیق‌تر نوع موبایل
        if (preg_match('/iPad/i', $user_agent)) {
            $device_type = 'Tablet';
        } elseif (preg_match('/Tablet|SM-T|iPad/i', $user_agent)) {
            $device_type = 'Tablet';
        }
    }
    
    // شناسایی مدل دستگاه
    $device_name = 'نامشخص';
    
    // برای دستگاه‌های اپل
    if (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
        preg_match('/iPhone|iPad|iPod/i', $user_agent, $model_match);
        $device_name = $model_match[0];
    } 
    // برای دستگاه‌های اندروید
    elseif (preg_match('/Android/i', $user_agent)) {
        if (preg_match('/Android.+; ([^;)]+)\)/i', $user_agent, $matches)) {
            $device_name = $matches[1];
            // پاکسازی اطلاعات اضافی
            $device_name = preg_replace('/Build.*/', '', $device_name);
            $device_name = trim($device_name);
        }
    } 
    // برای دستگاه‌های دسکتاپ
    else {
        $device_name = $os;
    }
    
    return [
        'device_type' => $device_type,
        'device_name' => $device_name,
        'browser' => $browser,
        'os' => $os,
        'ip_address' => $ip,
        'user_agent' => $user_agent
    ];
}