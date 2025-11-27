// marketing.js - اسکریپت‌های مربوط به بخش بازاریابی

// اجرای کد پس از بارگیری کامل صفحه
document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی تقویم شمسی
    setupPersianDatepicker();
    
    // راه‌اندازی اعتبارسنجی فرم
    setupFormValidation();
    
    // راه‌اندازی پیش‌نمایش تصاویر
    setupImagePreviews();
    
    // راه‌اندازی ماسک ورودی‌ها
    setupInputMasks();
});

/**
 * راه‌اندازی تقویم شمسی
 */
function setupPersianDatepicker() {
    // بررسی وجود فیلدهای تاریخ
    const dateInputs = document.querySelectorAll('.persian-date');
    if (dateInputs.length === 0 || typeof $.fn.persianDatepicker === 'undefined') return;
    
    // تنظیمات تقویم شمسی
    const datepickerOptions = {
        format: 'YYYY/MM/DD',
        calendar: {
            persian: {
                locale: 'fa'
            }
        },
        onSelect: function() {
            // فراخوانی اعتبارسنجی پس از انتخاب تاریخ
            this.element.dispatchEvent(new Event('change'));
        }
    };
    
    // اعمال تقویم شمسی به همه فیلدهای تاریخ
    dateInputs.forEach(input => {
        $(input).persianDatepicker(datepickerOptions);
    });
}

/**
 * راه‌اندازی اعتبارسنجی فرم
 */
function setupFormValidation() {
    // بررسی وجود فرم
    const form = document.getElementById('marketingForm');
    if (!form) return;
    
    // جلوگیری از ارسال فرم در صورت عدم اعتبار
    form.addEventListener('submit', function(event) {
        if (!validateForm()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
    
    // اعتبارسنجی کد ملی
    const nationalCodeInput = document.getElementById('national_code');
    if (nationalCodeInput) {
        nationalCodeInput.addEventListener('input', function() {
            validateNationalCode(this);
        });
        
        // اعتبارسنجی اولیه
        if (nationalCodeInput.value) {
            validateNationalCode(nationalCodeInput);
        }
    }
    
    // اعتبارسنجی شماره موبایل
    const mobileInput = document.getElementById('mobile');
    if (mobileInput) {
        mobileInput.addEventListener('input', function() {
            validateMobileNumber(this);
        });
        
        // اعتبارسنجی اولیه
        if (mobileInput.value) {
            validateMobileNumber(mobileInput);
        }
    }
    
    // اعتبارسنجی کد پستی
    const postalCodeInput = document.getElementById('postal_code');
    if (postalCodeInput) {
        postalCodeInput.addEventListener('input', function() {
            validatePostalCode(this);
        });
        
        // اعتبارسنجی اولیه
        if (postalCodeInput.value) {
            validatePostalCode(postalCodeInput);
        }
    }
    
    // اعتبارسنجی شماره شبا
    const shebaInput = document.getElementById('sheba_number');
    if (shebaInput) {
        shebaInput.addEventListener('input', function() {
            validateShebaNumber(this);
        });
        
        // اعتبارسنجی اولیه
        if (shebaInput.value) {
            validateShebaNumber(shebaInput);
        }
    }
    
    // اعتبارسنجی فایل‌های آپلودی
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            validateFileUpload(this);
        });
    });
}

/**
 * اعتبارسنجی کل فرم
 */
function validateForm() {
    let isValid = true;
    
    // اعتبارسنجی کد ملی
    const nationalCodeInput = document.getElementById('national_code');
    if (nationalCodeInput && !validateNationalCode(nationalCodeInput)) {
        isValid = false;
    }
    
    // اعتبارسنجی شماره موبایل
    const mobileInput = document.getElementById('mobile');
    if (mobileInput && !validateMobileNumber(mobileInput)) {
        isValid = false;
    }
    
    // اعتبارسنجی کد پستی
    const postalCodeInput = document.getElementById('postal_code');
    if (postalCodeInput && postalCodeInput.value && !validatePostalCode(postalCodeInput)) {
        isValid = false;
    }
    
    // اعتبارسنجی شماره شبا
    const shebaInput = document.getElementById('sheba_number');
    if (shebaInput && shebaInput.value && !validateShebaNumber(shebaInput)) {
        isValid = false;
    }
    
    // اعتبارسنجی فایل‌های آپلودی
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        if (!validateFileUpload(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * اعتبارسنجی کد ملی
 */
function validateNationalCode(input) {
    const value = input.value.trim();
    const isRequired = input.hasAttribute('required');
    
    // اگر فیلد خالی است و اجباری نیست، اعتبارسنجی نکن
    if (!value && !isRequired) {
        input.setCustomValidity('');
        return true;
    }
    
    // بررسی طول کد ملی
    if (value.length !== 10) {
        input.setCustomValidity('کد ملی باید دقیقاً 10 رقم باشد.');
        return false;
    }
    
    // بررسی عدد بودن کد ملی
    if (!/^\d{10}$/.test(value)) {
        input.setCustomValidity('کد ملی باید فقط شامل ارقام باشد.');
        return false;
    }
    
    // اعتبارسنجی الگوریتم کد ملی
    // کدهای ملی که رقم‌های یکسان دارند غیرمعتبر هستند
    if (/^(\d)\1{9}$/.test(value)) {
        input.setCustomValidity('کد ملی وارد شده معتبر نیست.');
        return false;
    }
    
    // محاسبه رقم کنترل کد ملی
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(value.charAt(i)) * (10 - i);
    }
    
    const remainder = sum % 11;
    const controlDigit = remainder < 2 ? remainder : 11 - remainder;
    
    if (controlDigit !== parseInt(value.charAt(9))) {
        input.setCustomValidity('کد ملی وارد شده معتبر نیست.');
        return false;
    }
    
    // کد ملی معتبر است
    input.setCustomValidity('');
    return true;
}

/**
 * اعتبارسنجی شماره موبایل
 */
function validateMobileNumber(input) {
    const value = input.value.trim();
    const isRequired = input.hasAttribute('required');
    
    // اگر فیلد خالی است و اجباری نیست، اعتبارسنجی نکن
    if (!value && !isRequired) {
        input.setCustomValidity('');
        return true;
    }
    
    // بررسی طول شماره موبایل
    if (value.length !== 11) {
        input.setCustomValidity('شماره موبایل باید دقیقاً 11 رقم باشد.');
        return false;
    }
    
    // بررسی عدد بودن شماره موبایل
    if (!/^\d{11}$/.test(value)) {
        input.setCustomValidity('شماره موبایل باید فقط شامل ارقام باشد.');
        return false;
    }
    
    // بررسی شروع شماره موبایل با 09
    if (!value.startsWith('09')) {
        input.setCustomValidity('شماره موبایل باید با 09 شروع شود.');
        return false;
    }
    
    // شماره موبایل معتبر است
    input.setCustomValidity('');
    return true;
}

/**
 * اعتبارسنجی کد پستی
 */
function validatePostalCode(input) {
    const value = input.value.trim();
    
    // اگر فیلد خالی است، اعتبارسنجی نکن
    if (!value) {
        input.setCustomValidity('');
        return true;
    }
    
    // بررسی طول کد پستی
    if (value.length !== 10) {
        input.setCustomValidity('کد پستی باید دقیقاً 10 رقم باشد.');
        return false;
    }
    
    // بررسی عدد بودن کد پستی
    if (!/^\d{10}$/.test(value)) {
        input.setCustomValidity('کد پستی باید فقط شامل ارقام باشد.');
        return false;
    }
    
    // کد پستی معتبر است
    input.setCustomValidity('');
    return true;
}

/**
 * اعتبارسنجی شماره شبا
 */
function validateShebaNumber(input) {
    const value = input.value.trim();
    
    // اگر فیلد خالی است، اعتبارسنجی نکن
    if (!value) {
        input.setCustomValidity('');
        return true;
    }
    
    // بررسی طول شماره شبا
    if (value.length !== 26) {
        input.setCustomValidity('شماره شبا باید دقیقاً 26 رقم باشد.');
        return false;
    }
    
    // بررسی عدد بودن شماره شبا
    if (!/^\d{26}$/.test(value)) {
        input.setCustomValidity('شماره شبا باید فقط شامل ارقام باشد.');
        return false;
    }
    
    // شماره شبا معتبر است
    input.setCustomValidity('');
    return true;
}

/**
 * اعتبارسنجی فایل‌های آپلودی
 */
function validateFileUpload(input) {
    // اگر فایلی انتخاب نشده و فیلد اجباری نیست، اعتبارسنجی نکن
    if (input.files.length === 0) {
        if (input.hasAttribute('required')) {
            input.setCustomValidity('لطفاً یک فایل انتخاب کنید.');
            return false;
        } else {
            input.setCustomValidity('');
            return true;
        }
    }
    
    const file = input.files[0];
    
    // بررسی نوع فایل
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
        input.setCustomValidity('فقط فایل‌های تصویری (JPG, JPEG, PNG) مجاز هستند.');
        return false;
    }
    
 // بررسی اندازه فایل (حداکثر 1 مگابایت)
 const maxSize = 1 * 1024 * 1024; // 1 MB
 if (file.size > maxSize) {
     input.setCustomValidity(`حجم فایل نباید بیشتر از 1 مگابایت باشد. حجم فعلی: ${Math.round(file.size / 1024)} کیلوبایت`);
     return false;
 }
 
 // فایل معتبر است
 input.setCustomValidity('');
 return true;
}

/**
* راه‌اندازی پیش‌نمایش تصاویر
*/
function setupImagePreviews() {
 const fileInputs = document.querySelectorAll('input[type="file"]');
 fileInputs.forEach(input => {
     input.addEventListener('change', function() {
         // بررسی وجود فایل و معتبر بودن آن
         if (this.files && this.files[0] && validateFileUpload(this)) {
             const previewId = this.id + '_preview';
             let previewContainer = document.getElementById(previewId);
             
             // اگر کانتینر پیش‌نمایش وجود ندارد، آن را ایجاد کن
             if (!previewContainer) {
                 previewContainer = document.createElement('div');
                 previewContainer.id = previewId;
                 previewContainer.className = 'image-preview mt-2';
                 this.parentNode.appendChild(previewContainer);
             }
             
             // ایجاد یک FileReader برای خواندن فایل تصویری
             const reader = new FileReader();
             reader.onload = function(e) {
                 previewContainer.innerHTML = `
                     <div class="card">
                         <div class="card-body text-center">
                             <img src="${e.target.result}" alt="پیش‌نمایش" class="img-fluid" style="max-height: 150px;">
                             <div class="mt-2">
                                 <button type="button" class="btn btn-sm btn-danger" onclick="removeFilePreview('${input.id}')">
                                     <i class="fas fa-times"></i> حذف
                                 </button>
                             </div>
                         </div>
                     </div>
                 `;
             };
             reader.readAsDataURL(this.files[0]);
         }
     });
 });
}

/**
* حذف پیش‌نمایش فایل و پاک کردن فیلد آپلود
*/
function removeFilePreview(inputId) {
 const input = document.getElementById(inputId);
 const previewContainer = document.getElementById(inputId + '_preview');
 
 if (input) {
     input.value = ''; // پاک کردن فایل انتخاب شده
     input.dispatchEvent(new Event('change')); // اعمال تغییرات
 }
 
 if (previewContainer) {
     previewContainer.remove(); // حذف کانتینر پیش‌نمایش
 }
}

/**
* راه‌اندازی ماسک‌های ورودی برای فیلدهای خاص
*/
function setupInputMasks() {
 // ماسک برای کد ملی (فقط اعداد، حداکثر 10 رقم)
 const nationalCodeInput = document.getElementById('national_code');
 if (nationalCodeInput) {
     nationalCodeInput.addEventListener('input', function() {
         this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
     });
 }
 
 // ماسک برای شماره موبایل (فقط اعداد، حداکثر 11 رقم)
 const mobileInput = document.getElementById('mobile');
 if (mobileInput) {
     mobileInput.addEventListener('input', function() {
         this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
     });
 }
 
 // ماسک برای کد پستی (فقط اعداد، حداکثر 10 رقم)
 const postalCodeInput = document.getElementById('postal_code');
 if (postalCodeInput) {
     postalCodeInput.addEventListener('input', function() {
         this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
     });
 }
 
 // ماسک برای شماره شبا (فقط اعداد، حداکثر 26 رقم)
 const shebaInput = document.getElementById('sheba_number');
 if (shebaInput) {
     shebaInput.addEventListener('input', function() {
         this.value = this.value.replace(/[^0-9]/g, '').substring(0, 26);
     });
 }
 
 // ماسک برای شماره تلفن (فقط اعداد)
 const phoneInput = document.getElementById('phone');
 if (phoneInput) {
     phoneInput.addEventListener('input', function() {
         this.value = this.value.replace(/[^0-9]/g, '');
     });
 }
}

/**
* نمایش هشدار در صفحه
*/
function showAlert(message, type = 'info') {
 // بررسی وجود هشدار قبلی و حذف آن
 const existingAlert = document.querySelector('.marketing-alert');
 if (existingAlert) {
     existingAlert.remove();
 }
 
 // ایجاد هشدار جدید
 const alertDiv = document.createElement('div');
 alertDiv.className = `alert alert-${type} marketing-alert alert-dismissible fade show`;
 alertDiv.role = 'alert';
 
 alertDiv.innerHTML = `
     <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : 'info-circle'} me-2"></i>
     ${message}
     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
 `;
 
 // افزودن به صفحه
 const form = document.getElementById('marketingForm');
 if (form) {
     form.parentNode.insertBefore(alertDiv, form);
 } else {
     document.querySelector('.card-body').appendChild(alertDiv);
 }
 
 // حذف خودکار پس از 5 ثانیه
 setTimeout(() => {
     if (alertDiv.parentNode) {
         alertDiv.classList.remove('show');
         setTimeout(() => {
             alertDiv.remove();
         }, 300);
     }
 }, 5000);
}