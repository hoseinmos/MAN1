// persian-datepicker-setup.js - راه‌اندازی تقویم شمسی برای کل پروژه

document.addEventListener('DOMContentLoaded', function() {
    // پیدا کردن تمام فیلدهای تاریخ
    setupDatepickers();
    
    // تبدیل نمایش تاریخ‌ها به شمسی
    convertAllDatesToJalali();
});

/**
 * راه‌اندازی تقویم شمسی برای همه فیلدهای تاریخ
 */
function setupDatepickers() {
    // انتخاب تمام فیلدهای تاریخ
    const dateInputs = document.querySelectorAll('input[type="date"], .datepicker, .date-input');
    
    if (dateInputs.length === 0) return;
    
    // بررسی وجود کتابخانه‌های مورد نیاز
    if (typeof $ === 'undefined' || typeof $.fn.persianDatepicker === 'undefined') {
        console.error('کتابخانه‌های jQuery و persianDatepicker وجود ندارند!');
        return;
    }
    
    // تنظیم تقویم شمسی برای همه فیلدها
    dateInputs.forEach(function(input) {
        // تغییر نوع ورودی از date به text برای جلوگیری از نمایش تقویم مرورگر
        if (input.type === 'date') {
            input.type = 'text';
        }
        
        // اضافه کردن کلاس برای استایل‌دهی
        input.classList.add('datepicker-input');
        
        // تنظیم تقویم شمسی
        $(input).persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            initialValueType: 'gregorian',
            calendar: {
                persian: {
                    locale: 'fa'
                }
            },
            onSelect: function() {
                // فراخوانی رویداد change برای اعتبارسنجی فرم
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    });
}

/**
 * تبدیل همه تاریخ‌های نمایشی به شمسی
 */
function convertAllDatesToJalali() {
    // انتخاب تمام عناصری که نیاز به تبدیل تاریخ دارند
    const elements = document.querySelectorAll('.jalali-date, [data-jalali="true"]');
    
    if (elements.length === 0) return;
    
    elements.forEach(function(element) {
        // دریافت تاریخ میلادی از متن یا ویژگی داده
        const gregorianDate = element.dataset.gregorianDate || element.textContent.trim();
        
        if (!gregorianDate || gregorianDate === '' || gregorianDate === '-') return;
        
        try {
            // تبدیل تاریخ میلادی به شمسی
            const persianDate = convertToJalali(gregorianDate);
            
            // نمایش تاریخ شمسی
            element.textContent = persianDate;
            
            // ذخیره تاریخ میلادی در ویژگی داده
            element.dataset.gregorianDate = gregorianDate;
        } catch (e) {
            console.error('خطا در تبدیل تاریخ:', gregorianDate, e);
        }
    });
}

/**
 * تبدیل تاریخ میلادی به شمسی
 * @param {string} gregorianDate تاریخ میلادی (YYYY-MM-DD)
 * @returns {string} تاریخ شمسی (YYYY/MM/DD)
 */
function convertToJalali(gregorianDate) {
    if (!gregorianDate) return '';
    
    try {
        // تقسیم تاریخ میلادی به اجزای آن
        let date;
        
        // بررسی قالب تاریخ
        if (gregorianDate.includes('-')) {
            date = gregorianDate.split('-');
        } else if (gregorianDate.includes('/')) {
            date = gregorianDate.split('/');
        } else if (gregorianDate.includes(' ')) {
            // تاریخ و زمان با هم (مثلاً 2025-04-23 10:30:45)
            date = gregorianDate.split(' ')[0].split('-');
        } else {
            return gregorianDate; // قالب ناشناخته
        }
        
        if (date.length !== 3) return gregorianDate;
        
        // استفاده از تابع تبدیل تاریخ میلادی به شمسی
        const year = parseInt(date[0]);
        const month = parseInt(date[1]);
        const day = parseInt(date[2]);
        
        // استفاده از کتابخانه تقویم شمسی
        if (typeof persianDate !== 'undefined') {
            return new persianDate([year, month, day]).format('YYYY/MM/DD');
        }
        
        // یا روش جایگزین اگر کتابخانه نباشد
        // (این فقط یک نمونه ساده است - باید از کتابخانه استفاده کنید)
        const jalaliDate = toJalali(year, month, day);
        return `${jalaliDate.jy}/${jalaliDate.jm < 10 ? '0' + jalaliDate.jm : jalaliDate.jm}/${jalaliDate.jd < 10 ? '0' + jalaliDate.jd : jalaliDate.jd}`;
    } catch (e) {
        console.error('خطا در تبدیل تاریخ:', e);
        return gregorianDate;
    }
}

/**
 * تبدیل تاریخ شمسی به میلادی
 * @param {string} jalaliDate تاریخ شمسی (YYYY/MM/DD)
 * @returns {string} تاریخ میلادی (YYYY-MM-DD)
 */
function convertToGregorian(jalaliDate) {
    if (!jalaliDate) return '';
    
    try {
        // تقسیم تاریخ شمسی به اجزای آن
        const date = jalaliDate.split('/');
        if (date.length !== 3) return jalaliDate;
        
        const jy = parseInt(date[0]);
        const jm = parseInt(date[1]);
        const jd = parseInt(date[2]);
        
        // استفاده از کتابخانه تقویم شمسی
        if (typeof persianDate !== 'undefined') {
            const pDate = new persianDate([jy, jm, jd]);
            return pDate.toCalendar('gregorian').format('YYYY-MM-DD');
        }
        
        // یا روش جایگزین اگر کتابخانه نباشد
        // (این فقط یک نمونه ساده است - باید از کتابخانه استفاده کنید)
        const gregorianDate = toGregorian(jy, jm, jd);
        return `${gregorianDate.gy}-${gregorianDate.gm < 10 ? '0' + gregorianDate.gm : gregorianDate.gm}-${gregorianDate.gd < 10 ? '0' + gregorianDate.gd : gregorianDate.gd}`;
    } catch (e) {
        console.error('خطا در تبدیل تاریخ:', e);
        return jalaliDate;
    }
}

// توابع کمکی اگر کتابخانه در دسترس نباشد - فقط برای پشتیبانی اضافی
// (بهتر است از کتابخانه تقویم شمسی استفاده کنید)
function toJalali(gy, gm, gd) {
    return {
        jy: gy - 621,
        jm: gm,
        jd: gd
    };
}

function toGregorian(jy, jm, jd) {
    return {
        gy: jy + 621,
        gm: jm,
        gd: jd
    };
}