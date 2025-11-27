// script.js - بهبود یافته با اعتبارسنجی فرم جستجو
function refreshCaptcha() {
    // ارسال درخواست AJAX برای دریافت کپچای جدید
    fetch('refresh_captcha.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('captcha-display').innerHTML = data;
        })
        .catch(error => {
            console.error('خطا در بارگذاری کپچا:', error);
        });
}

// اضافه کردن اعتبارسنجی فرم
document.addEventListener('DOMContentLoaded', function() {
    // اعتبارسنجی فرم ورود
    const loginForm = document.querySelector('#loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            const accessCode = document.getElementById('access_code').value;
            const captchaInput = document.getElementById('captcha_input').value;
            
            if (!accessCode || !captchaInput) {
                event.preventDefault();
                showAlert('لطفاً تمام فیلدها را پر کنید.', 'danger');
                return;
            }
            
            // اعتبارسنجی کپچا
            if (captchaInput.length !== 4 || isNaN(captchaInput)) {
                event.preventDefault();
                showAlert('لطفاً کد امنیتی را به صورت صحیح وارد کنید.', 'danger');
                return;
            }
        });
    }
    
    // اعتبارسنجی فرم جستجو
    const searchForm = document.querySelector('#searchForm');
    
    if (searchForm) {
        searchForm.addEventListener('submit', function(event) {
            // دریافت مقادیر فیلدهای جستجو
            const terminalCode = document.querySelector('input[name="terminal_code"]').value.trim();
            const merchantName = document.querySelector('input[name="merchant_name"]').value.trim();
            const terminalModel = document.querySelector('select[name="terminal_model"]').value;
            
            // بررسی اینکه حداقل یکی از فیلدهای اصلی پر شده باشد
            if (terminalCode === '' && merchantName === '') {
                // اگر هیچ فیلدی پر نشده باشد، از ارسال فرم جلوگیری می‌کنیم
                event.preventDefault();
                
                // نمایش پیام خطا در نتایج جستجو
                const searchResults = document.getElementById('searchResults');
                if (searchResults) {
                    searchResults.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            لطفاً حداقل یکی از فیلدهای <strong>شماره پایانه</strong> یا <strong>نام فروشگاه</strong> را وارد کنید.
                        </div>
                    `;
                }
            } else {
                // نمایش حالت بارگذاری
                showLoading('searchResults');
            }
        });
    }
    
    // افزودن انیمیشن به فرم
    const inputs = document.querySelectorAll('input');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('input-focus');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('input-focus');
        });
    });
    
    // اضافه کردن تابع فشردن کلید Enter برای کپچا
    const captchaInput = document.getElementById('captcha_input');
    if (captchaInput) {
        captchaInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loginForm.submit();
            }
        });
    }
    
    // اضافه کردن رویداد به دکمه بستن مودال جزئیات پایانه
    const closeButton = document.querySelector('#terminalDetailsModal .modal-footer .btn-secondary');
    
    if (closeButton) {
        // اضافه کردن رویداد click به دکمه بستن
        closeButton.addEventListener('click', function() {
            // کمی تاخیر برای اطمینان از بسته شدن مودال قبل از رفرش
            setTimeout(function() {
                // رفرش کردن صفحه
                window.location.reload();
            }, 300);
        });
    }
});

// نمایش پیام هشدار
function showAlert(message, type = 'info') {
    // ایجاد عنصر هشدار
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    
    // افزودن پیام
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
    `;
    
    // افزودن به صفحه
    const cardBody = document.querySelector('.card-body');
    if (cardBody) {
        // افزودن به ابتدای card-body قبل از فرم
        const form = cardBody.querySelector('form');
        if (form) {
            cardBody.insertBefore(alertDiv, form);
        } else {
            cardBody.appendChild(alertDiv);
        }
        
        // حذف خودکار پس از 5 ثانیه
        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => {
                alertDiv.remove();
            }, 300);
        }, 5000);
    }
}

// نمایش پیام خطای جستجو
function showSearchError(message) {
    // بررسی وجود پیام خطای قبلی و حذف آن
    const existingError = document.querySelector('.search-error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // دریافت محل نمایش نتایج جستجو
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        // ایجاد المان پیام خطا
        searchResults.innerHTML = `
            <div class="alert alert-warning search-error-message">
                <i class="fas fa-exclamation-triangle me-2"></i> ${message}
            </div>
        `;
    } else {
        // ایجاد المان پیام خطا در نزدیکی فرم
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-warning mt-3 search-error-message';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> ${message}`;
        
        // قرار دادن پیام خطا بعد از دکمه جستجو
        const searchButton = document.querySelector('.btn-primary');
        if (searchButton && searchButton.parentElement) {
            searchButton.parentElement.parentElement.after(errorDiv);
        }
    }
}

// افزودن تابع جستجو و فیلتر برای داشبورد (در صورت وجود جدول)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('table-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // افزودن عملکرد دکمه بالا رفتن صفحه
    const scrollTopBtn = document.getElementById('scroll-top-btn');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                scrollTopBtn.style.display = 'block';
            } else {
                scrollTopBtn.style.display = 'none';
            }
        });
        
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});

// نمایش حالت بارگذاری
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">بارگذاری...</span>
                </div>
                <p class="mt-2">در حال بارگذاری اطلاعات...</p>
            </div>
        `;
    }
}