// mobile-script.js - اسکریپت مخصوص برای بهبود رابط کاربری در دستگاه‌های موبایل

document.addEventListener('DOMContentLoaded', function() {
    // جایگزینی جدول‌های عادی با جدول‌های استک‌شده برای موبایل
    setupMobileTables();
    
    // تشخیص دستگاه موبایل و تنظیم کلاس‌های مربوطه
    detectMobileDevice();
});

/**
 * تشخیص دستگاه موبایل و اعمال کلاس‌های مناسب
 */
function detectMobileDevice() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        document.body.classList.add('mobile-device');
        
        // بهینه‌سازی برای لمس
        document.querySelectorAll('button, .btn, a').forEach(element => {
            if (element.offsetWidth < 44 || element.offsetHeight < 44) {
                element.classList.add('touch-target');
            }
        });
        
        // افزودن تشخیص لمس به body
        document.body.classList.add('touch-device');
        
        // بهبود اسکرول در موبایل
        improveScrolling();
    }
}

function setupMobileTables() {
    // بررسی اینکه آیا در حالت موبایل هستیم
    if (window.innerWidth <= 768) {
        const tables = document.querySelectorAll('.table-responsive');
        
        tables.forEach(tableContainer => {
            const table = tableContainer.querySelector('table');
            if (table) {
                // بررسی اینکه آیا جدول عرض بیشتری از کانتینر دارد
                if (table.offsetWidth > tableContainer.offsetWidth) {
                    // اضافه کردن راهنمای اسکرول
                    const scrollGuide = document.createElement('div');
                    scrollGuide.className = 'scroll-guide text-center text-muted small mt-2';
                    scrollGuide.innerHTML = '<i class="fas fa-arrows-left-right me-1"></i> برای مشاهده همه ستون‌ها، به چپ و راست اسکرول کنید';
                    
                    if (!tableContainer.nextElementSibling || !tableContainer.nextElementSibling.classList.contains('scroll-guide')) {
                        tableContainer.parentNode.insertBefore(scrollGuide, tableContainer.nextSibling);
                        
                        // حذف راهنما پس از 5 ثانیه
                        setTimeout(() => {
                            scrollGuide.style.opacity = '0';
                            setTimeout(() => {
                                if (scrollGuide.parentNode) {
                                    scrollGuide.parentNode.removeChild(scrollGuide);
                                }
                            }, 500);
                        }, 5000);
                    }
                }
            }
        });
    }
}

// اجرای تابع در زمان لود صفحه و تغییر اندازه صفحه
document.addEventListener('DOMContentLoaded', setupMobileTables);
window.addEventListener('resize', setupMobileTables);

/**
 * بهبود اسکرول در دستگاه‌های موبایل
 */
function improveScrolling() {
    // یافتن عناصر با اسکرول افقی
    const scrollableElements = document.querySelectorAll('.table-responsive, .overflow-auto');
    
    scrollableElements.forEach(element => {
        // افزودن راهنمای اسکرول
        const scrollGuide = document.createElement('div');
        scrollGuide.className = 'scroll-guide';
        scrollGuide.innerHTML = '<div class="scroll-arrow"><i class="fas fa-chevron-left"></i></div><div class="scroll-text">برای مشاهده بیشتر اسکرول کنید</div><div class="scroll-arrow"><i class="fas fa-chevron-right"></i></div>';
        
        // اگر محتوا بیشتر از عرض ظرف باشد، راهنما را نمایش می‌دهیم
        if (element.scrollWidth > element.clientWidth) {
            element.parentNode.insertBefore(scrollGuide, element.nextSibling);
            
            // پس از مدتی راهنما را محو می‌کنیم
            setTimeout(() => {
                scrollGuide.style.opacity = '0';
                setTimeout(() => {
                    scrollGuide.remove();
                }, 500);
            }, 3000);
        }
    });
}

/**
 * بررسی اینکه آیا کاربر مدیر سیستم است
 */
function isAdmin() {
    // بررسی وجود دکمه‌ها یا بخش‌های مخصوص مدیر
    const adminElements = document.querySelectorAll('.admin-only, [href="admin_issues.php"], [href="admin_roll_report.php"]');
    return adminElements.length > 0;
}

/**
 * تنظیم ارتفاع صفحه برای موبایل (حل مشکل ارتفاع viewport در مرورگرهای موبایل)
 */
function setMobileHeight() {
    // تنظیم ارتفاع برای 100vh واقعی در موبایل
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
}

// اجرای تنظیم ارتفاع و ثبت رویداد تغییر اندازه
setMobileHeight();
window.addEventListener('resize', setMobileHeight);

/**
 * پنهان کردن نوار اسکرول در هنگام اسکرول در موبایل
 */
let scrollTimeout;
window.addEventListener('scroll', function() {
    // نمایش دکمه بازگشت به بالا در صورت اسکرول
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        if (window.scrollY > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    }
    
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(function() {
        // اسکرول متوقف شده است
    }, 150);
}, { passive: true });

/**
 * بهینه‌سازی فرم‌ها برای صفحات کوچک
 */
function optimizeFormsForMobile() {
    if (window.innerWidth <= 768) {
        // تغییر جهت برخی form-row به عمودی
        document.querySelectorAll('.row').forEach(row => {
            const formControls = row.querySelectorAll('.form-control, .form-select');
            if (formControls.length >= 3) {
                row.classList.add('flex-column');
            }
        });
        
        // تنظیم دکمه‌های فرم برای عرض کامل
        document.querySelectorAll('form .btn').forEach(btn => {
            btn.classList.add('w-100', 'mb-2');
        });
    }
}

// اجرای بهینه‌سازی فرم‌ها
window.addEventListener('load', optimizeFormsForMobile);
window.addEventListener('resize', optimizeFormsForMobile);