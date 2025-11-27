<?php
// index.php - صفحه ورود با طراحی مرکزی و لوگو
include 'config.php';

// بررسی تایم‌اوت نشست
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $_SESSION['error'] = "نشست شما به دلیل عدم فعالیت منقضی شده است. لطفاً دوباره وارد شوید.";
}

// اگر کاربر قبلاً وارد شده است، به داشبورد هدایت شود
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// تولید کپچای جدید
$captcha_num = rand(1000, 9999);
$_SESSION['captcha'] = $captcha_num;

// تولید توکن CSRF
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f8f9fa;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        .main-content {
            flex: 1 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
        }
        
        .login-container {
            width: 500px;
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
        }
        
        .login-header {
            background: linear-gradient(to right, #3a7bd5, #2980b9);
            color: white;
            text-align: center;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .login-form {
            background-color: white;
            padding: 25px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: bold;
            margin-bottom: 8px;
            text-align: right;
            display: block;
        }
        
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .captcha-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .captcha-box {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            margin-right: 10px;
            flex-grow: 1;
            text-align: center;
        }
        
        .refresh-btn {
            background-color: #f2f2f2;
            border: none;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .login-btn {
            background: linear-gradient(to right, #3a7bd5, #2980b9);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-btn:hover {
            background: linear-gradient(to right, #2980b9, #3a7bd5);
        }
        
        .footer {
            flex-shrink: 0;
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }
        
        .alert {
            margin-bottom: 20px;
        }
        
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            background-color: white;
            
            padding: 5px;
          
        }

        @media (max-width: 576px) {
            .login-container {
                width: 100%;
                padding: 0 15px;
            }
            
            .logo {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="login-container">
            <?php if(isset($_SESSION['db_error'])): ?>
                <div class="alert alert-warning">
                    <strong>هشدار:</strong> <?php echo $_SESSION['db_error']; unset($_SESSION['db_error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="login-header">
                ورود به سیستم
            </div>
            <div class="login-form">
                <div class="logo-container">
                    <img src="kiccc.png" alt="لوگو" class="logo">
                </div>
                
                <form action="verify.php" method="post" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="access_code" class="form-label">کد دسترسی  :</label>
                        <input type="password" class="form-control" id="access_code" name="access_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label"> نام کاربری :</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">کد امنیتی:</label>
                        <div class="captcha-container">
                            <div class="captcha-box" id="captcha-display">
                                <?php echo $captcha_num; ?>
                            </div>
                            <button type="button" class="refresh-btn" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i> تازه‌سازی
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="captcha_input" class="form-label">کد امنیتی را وارد کنید:</label>
                        <input type="text" class="form-control" id="captcha_input" name="captcha_input" required>
                    </div>
                    
                    <button type="submit" class="login-btn">ورود</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="footer">
        پیاده سازی و برنامه نویسی:شرکت کارت اعتباری ایران کیش (دفتر کرمانشاه )
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // تابع بروزرسانی کپچا
        function refreshCaptcha() {
            fetch('refresh_captcha.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('captcha-display').innerText = data;
                })
                .catch(error => {
                    console.error('خطا در بروزرسانی کپچا:', error);
                });
        }
    </script>
</body>
</html>