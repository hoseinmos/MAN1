// dashboard.js - فایل جاوااسکریپت اختصاصی برای داشبورد (بهبود یافته)

// توابع کمکی مرکزی
const Utils = {
    // نمایش هشدار
    showAlert(message, type = 'info') {
        if (!message) return;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-floating`;
        alertDiv.role = 'alert';
        
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.classList.add('show-alert');
        }, 10);
        
        setTimeout(() => {
            alertDiv.classList.remove('show-alert');
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 300);
        }, 5000);
    },

    // انیمیشن اعداد
    animateNumber(element, finalValue) {
        if (!element) return;
        
        const duration = 1000;
        const finalNum = parseInt(finalValue, 10);
        const startTime = performance.now();
        
        element.textContent = '0';
        
        function updateNumber(currentTime) {
            const elapsedTime = currentTime - startTime;
            const progress = Math.min(elapsedTime / duration, 1);
            
            const currentValue = Math.floor(progress * finalNum);
            element.textContent = currentValue.toLocaleString('fa-IR');
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            } else {
                element.textContent = finalNum.toLocaleString('fa-IR');
            }
        }
        
        requestAnimationFrame(updateNumber);
    },

    // بررسی CSRF
    checkCSRF() {
        const token = document.querySelector('input[name="csrf_token"]')?.value;
        if (!token) {
            Utils.showAlert('خطای امنیتی: توکن CSRF یافت نشد', 'danger');
            return false;
        }
        return token;
    },

    // مدیریت خطای درخواست
    handleRequestError(error, customMessage = 'خطا در ارتباط با سرور') {
        console.error(error);
        Utils.showAlert(`${customMessage}: ${error.message}`, 'danger');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // متغیرهای سراسری
    let serverTimeDiff = 0; // اختلاف زمانی با سرور (به میلی‌ثانیه)
    let lastSyncTime = 0;   // زمان آخرین همگام‌سازی

    // همگام‌سازی با زمان سرور
    function syncWithServerTime(serverTimeString) {
        // اگر رشته زمان سرور خالی باشد، از آن صرف نظر کن
        if (!serverTimeString) return;
        
        try {
            // تاریخ و زمان فعلی کلاینت
            const clientTime = new Date();
            
            // تبدیل رشته زمان سرور به تاریخ
            // فرمت زمان سرور به صورت "1402/01/01 12:34:56" می‌باشد
            let serverTimeParts = serverTimeString.split(' ');
            if (serverTimeParts.length !== 2) return;
            
            let dateParts = serverTimeParts[0].split('/');
            let timeParts = serverTimeParts[1].split(':');
            
            if (dateParts.length !== 3 || timeParts.length !== 3) return;
            
            // تبدیل تاریخ شمسی به میلادی (این تابع باید جداگانه پیاده‌سازی شود)
            // در اینجا فرض می‌کنیم زمان سرور مستقیماً قابل استفاده است
            const serverTimeObj = new Date();
            const hours = parseInt(timeParts[0]);
            const minutes = parseInt(timeParts[1]);
            const seconds = parseInt(timeParts[2]);
            
            serverTimeObj.setHours(hours, minutes, seconds, 0);
            
            // محاسبه اختلاف زمانی
            serverTimeDiff = serverTimeObj.getTime() - clientTime.getTime();
            lastSyncTime = clientTime.getTime();
            
            console.log("زمان سرور همگام‌سازی شد. اختلاف زمانی: " + serverTimeDiff + " میلی‌ثانیه");
        } catch (error) {
            console.error("خطا در همگام‌سازی زمان سرور: ", error);
        }
    }

    // به‌روزرسانی زمان نمایشی
    function updateTime() {
        const timeDisplay = document.getElementById('time-display');
        if (!timeDisplay) return;
        
        const now = new Date();
        const adjustedTime = new Date(now.getTime() + serverTimeDiff);
        const elapsedSeconds = Math.floor((now.getTime() - lastSyncTime) / 1000);
        
        // اگر بیش از 5 دقیقه از آخرین همگام‌سازی گذشته، زمان را از سرور دریافت کن
        if (elapsedSeconds > 300) {
            updateServerTime();
            return;
        }
        
        // نمایش زمان تنظیم شده
        const hours = adjustedTime.getHours().toString().padStart(2, '0');
        const minutes = adjustedTime.getMinutes().toString().padStart(2, '0');
        const seconds = adjustedTime.getSeconds().toString().padStart(2, '0');
        
        // حفظ بخش تاریخ از اطلاعات سرور
        const serverTimeElem = timeDisplay.getAttribute('data-server-time');
        if (serverTimeElem && serverTimeElem.indexOf(' ') > -1) {
            const datePart = serverTimeElem.split(' ')[0];
            timeDisplay.textContent = `${datePart} ${hours}:${minutes}:${seconds}`;
        } else {
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }
    }

    // دریافت زمان سرور با AJAX
    function updateServerTime() {
        fetch('get_server_time.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('خطا در دریافت زمان سرور');
                }
                return response.text();
            })
            .then(timeString => {
                const timeDisplay = document.getElementById('time-display');
                if (timeDisplay) {
                    // به‌روزرسانی زمان نمایشی و ذخیره در ویژگی داده
                    timeDisplay.textContent = timeString;
                    timeDisplay.setAttribute('data-server-time', timeString);
                    
                    // همگام‌سازی با زمان سرور
                    syncWithServerTime(timeString);
                }
            })
            .catch(error => {
                console.error("خطا در دریافت زمان سرور:", error);
            });
    }

    // اجرا هنگام بارگذاری صفحه
    const timeDisplay = document.getElementById('time-display');
    if (timeDisplay) {
        const serverTimeString = timeDisplay.getAttribute('data-server-time');
        syncWithServerTime(serverTimeString);
    }
    
    // به‌روزرسانی زمان هر ثانیه
    setInterval(updateTime, 1000);
    
    // به‌روزرسانی زمان سرور هر 5 دقیقه
    setInterval(updateServerTime, 5 * 60 * 1000);
    
    // بررسی نوع کاربر و گرفتن اطلاعات مربوطه
    const isAdmin = document.querySelector('.stats-section') !== null;
    if (isAdmin) {
        // گرفتن آمار کلی برای مدیر
        fetchStatistics();
    } else {
        // گرفتن لیست کاربران آنلاین برای کاربران عادی
        fetchOnlineUsers();
        // به‌روزرسانی لیست کاربران آنلاین هر 30 ثانیه
        setInterval(fetchOnlineUsers, 30000);
    }
    
    // مدیریت فرم جستجو
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            showLoading('searchResults');
            performSearch();
        });
    }
    
    // اضافه کردن اثر hover به کارت‌ها
    addCardHoverEffect();
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

// فانکشن برای دریافت آمار کلی
function fetchStatistics() {
    showLoading('stats-container');
    
    fetch('process_terminal.php?action=getStats', {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در دریافت آمار');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // انیمیشن برای نمایش اعداد
            animateNumber('total-terminals', data.totalTerminals);
            animateNumber('total-merchants', data.totalMerchants);
            animateNumber('total-issues', data.totalIssues);
        } else {
            console.error('خطا:', data.message);
            showAlert('خطا در دریافت آمار: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('خطا در دریافت آمار:', error);
        showAlert('خطا در دریافت آمار: ' + error.message, 'danger');
    });
}

// فانکشن برای دریافت لیست کاربران آنلاین
function fetchOnlineUsers() {
    fetch('online_users.php', {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در دریافت لیست کاربران آنلاین');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayOnlineUsers(data.users);
        } else {
            console.error('خطا:', data.message);
            document.getElementById('online-users-list').innerHTML = `
                <tr>
                    <td colspan="3" class="text-center">
                        <div class="alert alert-warning">خطا در بارگذاری لیست کاربران آنلاین.</div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('خطا در دریافت لیست کاربران آنلاین:', error);
        document.getElementById('online-users-list').innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="alert alert-danger">خطا در دریافت لیست کاربران آنلاین: ${error.message}</div>
                </td>
            </tr>
        `;
    });
}

// فانکشن برای نمایش لیست کاربران آنلاین
function displayOnlineUsers(users) {
    const usersList = document.getElementById('online-users-list');
    
    if (!usersList) return;
    
    if (users.length === 0) {
        usersList.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">هیچ کاربر آنلاینی یافت نشد.</td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    users.forEach(user => {
        // تبدیل زمان به فرمت مناسب
        const activityTime = new Date(user.last_activity);
        const now = new Date();
        const diffMinutes = Math.floor((now - activityTime) / (1000 * 60));
        
        let activityText;
        let statusClass = '';
        
        if (diffMinutes < 1) {
            activityText = 'همین الان';
            statusClass = 'text-success';
        } else if (diffMinutes === 1) {
            activityText = '1 دقیقه پیش';
            statusClass = 'text-success';
        } else if (diffMinutes < 5) {
            activityText = `${diffMinutes} دقیقه پیش`;
            statusClass = 'text-success';
        } else if (diffMinutes < 15) {
            activityText = `${diffMinutes} دقیقه پیش`;
            statusClass = 'text-warning';
        } else {
            activityText = `${diffMinutes} دقیقه پیش`;
            statusClass = 'text-danger';
        }
        
        html += `
            <tr>
                <td>کاربر ${user.user_id}</td>
                <td>${user.user_type || 'نامشخص'}</td>
                <td><span class="${statusClass}">${activityText}</span></td>
            </tr>
        `;
    });
    
    usersList.innerHTML = html;
}

// فانکشن برای انجام جستجو
function performSearch() {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    const formData = new FormData(document.getElementById('searchForm'));
    
    fetch('process_terminal.php?action=search', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در جستجو');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displaySearchResults(data.results);
        } else {
            searchResults.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
        }
    })
    .catch(error => {
        searchResults.innerHTML = `<div class="alert alert-danger">خطا در جستجو: ${error.message}</div>`;
        console.error('خطا در جستجو:', error);
    });
}

// ویرایش تابع displaySearchResults در فایل dashboard.js
function displaySearchResults(results) {
    const searchResults = document.getElementById('searchResults');
    
    if (!searchResults) return;
    
    if (!results || results.length === 0) {
        searchResults.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                هیچ نتیجه‌ای یافت نشد. لطفاً معیارهای جستجوی خود را تغییر دهید.
            </div>`;
        return;
    }
    
    // بررسی نمای موبایل
    const isMobile = window.innerWidth <= 768;
    
    let html = `
        <div class="search-results-header">
            <h4 class="mb-3">
                <i class="fas fa-search me-2"></i>
                نتایج جستجو 
                <span class="badge bg-primary rounded-pill">${results.length}</span>
            </h4>
        </div>
        <div class="table-responsive search-results-table">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="terminal-number-column">شماره پایانه</th>
                        <th>نام فروشگاه</th>
                        <th class="${isMobile ? 'd-none-mobile' : ''}">بانک</th>
                        <th class="${isMobile ? 'd-none-mobile' : ''}">مدل دستگاه</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    results.forEach(terminal => {
        // کلاس وضعیت برای نمایش بصری بهتر
        let statusClass = 'bg-success';
        if (terminal.status === 'غیرفعال') {
            statusClass = 'bg-danger';
        }
        
        html += `
            <tr class="search-result-row">
                <td class="terminal-number-column">
                    <div class="terminal-number">
                        ${terminal.terminal_number || '-'}
                        <span class="status-indicator ${statusClass}"></span>
                    </div>
                </td>
                <td>${terminal.store_name || '-'}</td>
                <td class="${isMobile ? 'd-none-mobile' : ''}">${terminal.bank || '-'}</td>
                <td class="${isMobile ? 'd-none-mobile' : ''}">${terminal.device_model || '-'}</td>
                <td>
                    <div class="action-buttons${isMobile ? '-mobile' : ''}">
                        <button class="btn btn-sm btn-primary me-1" onclick="showTerminalDetails(${terminal.id})">
                            <i class="fas fa-eye"></i> <span>جزئیات</span>
                        </button>
                        <a href="track_issues.php?terminal_code=${terminal.terminal_number}" class="btn btn-sm btn-warning">
                            <i class="fas fa-search-plus"></i> <span>پیگیری مشکل</span>
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    searchResults.innerHTML = html;
    
    // اضافه کردن افکت‌های انیمیشن به نتایج جستجو
    const rows = searchResults.querySelectorAll('.search-result-row');
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.classList.add('animated-row');
        }, index * 50);
    });
}

// فانکشن برای نمایش جزئیات پایانه
function showTerminalDetails(terminalId) {
    if (!terminalId) {
        console.error('شناسه پایانه نامعتبر است');
        return;
    }
    
    const modalContent = document.getElementById('terminalDetailsContent');
    if (!modalContent) {
        console.error('عنصر modalContent یافت نشد');
        return;
    }
    
    modalContent.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3">در حال بارگذاری اطلاعات...</p>
        </div>`;
    
    // نمایش مودال
    const terminalModal = new bootstrap.Modal(document.getElementById('terminalDetailsModal'));
    terminalModal.show();
    
    // دریافت اطلاعات از سرور
    fetch(`process_terminal.php?action=getDetails&terminal_id=${terminalId}`, {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در دریافت اطلاعات');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayTerminalDetails(data.terminal);
        } else {
            modalContent.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.message}
                </div>`;
        }
    })
    .catch(error => {
        modalContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-times-circle me-2"></i>
                خطا در دریافت اطلاعات: ${error.message}
            </div>`;
        console.error('خطا در دریافت جزئیات پایانه:', error);
    });
}

// به‌روزرسانی تعداد رول نمایش داده شده
function updateTerminalRollCount(terminalId, addedQuantity) {
    // یافتن عنصر نمایش دهنده تعداد رول
    const rollCountElement = document.querySelector('.terminal-details-container .badge.bg-info.rounded-pill');
    
    if (rollCountElement) {
        // دریافت مقدار فعلی و به‌روزرسانی آن
        const currentCount = parseInt(rollCountElement.textContent) || 0;
        const newCount = currentCount + addedQuantity;
        rollCountElement.textContent = newCount;
        
        // افزودن افکت انیمیشن برای جلب توجه
        rollCountElement.classList.add('badge-updated');
        setTimeout(() => {
            rollCountElement.classList.remove('badge-updated');
        }, 2000);
    }
}

// تابع برای نمایش محتوای جزئیات پایانه - بهبود یافته با طراحی بهتر
function displayTerminalDetails(terminal) {
    if (!terminal) {
        console.error('داده‌های پایانه نامعتبر است');
        return;
    }
    
    const modalContent = document.getElementById('terminalDetailsContent');
    
    let html = `
        <div class="terminal-details-container">
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>
                        اطلاعات اصلی پایانه
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">شماره پایانه بانک:</div>
                                    <div class="col-7 fw-bold">${terminal.terminal_number || '-'}</div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">نام فروشگاه:</div>
                                    <div class="col-7 fw-bold">${terminal.store_name || '-'}</div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">مدل دستگاه:</div>
                                    <div class="col-7">${terminal.device_model || '-'}</div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">نوع دستگاه:</div>
                                    <div class="col-7">${terminal.device_type || '-'}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">پشتیبان:</div>
                                    <div class="col-7">${terminal.support_person || '-'}</div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">گروه ترمینال:</div>
                                    <div class="col-7">${terminal.terminal_group || '-'}</div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">وضعیت:</div>
                                    <div class="col-7">
                                        <span class="badge ${terminal.status === 'فعال' ? 'bg-success' : 'bg-danger'}">
                                            ${terminal.status || '-'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded bg-light">
                                <div class="row">
                                    <div class="col-5 text-muted">تعداد رول:</div>
                                    <div class="col-7">
                                        <span class="badge bg-info rounded-pill">
                                            ${terminal.roll_count || '0'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول مدارک و توضیحات - بخش جدید -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        مدارک و توضیحات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <td class="bg-light text-end fw-bold" width="25%">توضیحات پی ام:</td>
                                <td>${terminal.pm_description || '-'}</td>
                            </tr>
                            <tr class="bg-light-peach">
                                <td class="text-end fw-bold" width="25%">نقص مدارک:</td>
                                <td>${terminal.missing_documents || '-'}</td>
                            </tr>
                            <tr class="bg-light-green">
                                <td class="text-end fw-bold" width="25%">برگشتی مدارک:</td>
                                <td>${terminal.returned_documents || '-'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        آمار تراکنش‌ها
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="p-3 rounded text-center bg-light">
                                <h6 class="text-muted mb-2">تراکنش سه ماهه</h6>
                                <h5 class="mb-0 fw-bold">${terminal.quarterly_transactions || '0'}</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded text-center bg-light">
                                <h6 class="text-muted mb-2">تراکنش ماه جاری</h6>
                                <h5 class="mb-0 fw-bold">${terminal.current_month_transactions || '0'}</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded text-center bg-light">
                                <h6 class="text-muted mb-2">تراکنش روز قبل</h6>
                                <h5 class="mb-0 fw-bold">${terminal.previous_day_transactions || '0'}</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded text-center bg-light">
                                <h6 class="text-muted mb-2">تراکنش ۲ روز</h6>
                                <h5 class="mb-0 fw-bold">${terminal.two_day_transactions || '0'}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    `;
    
    // بخش تاریخچه مشکلات
    let issueHistoryHtml = '';
    if (terminal.issues && terminal.issues.length > 0) {
        issueHistoryHtml = `
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        تاریخچه مشکلات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>تاریخ</th>
                                    <th>شرح مشکل</th>
                                    <th>اولویت</th>
                                    <th>وضعیت</th>
                                    <th>ثبت کننده</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        terminal.issues.forEach(issue => {
            let priorityClass = '';
            let priorityText = '';
            
            switch(issue.priority) {
                case 'low':
                    priorityClass = 'bg-info';
                    priorityText = 'کم';
                    break;
                case 'medium':
                    priorityClass = 'bg-primary';
                    priorityText = 'متوسط';
                    break;
                case 'high':
                    priorityClass = 'bg-warning';
                    priorityText = 'زیاد';
                    break;
                case 'critical':
                    priorityClass = 'bg-danger';
                    priorityText = 'بحرانی';
                    break;
            }
            
            let statusClass = '';
            let statusText = '';
            
            switch(issue.status) {
                case 'open':
                    statusClass = 'bg-danger';
                    statusText = 'باز';
                    break;
                case 'in_progress':
                    statusClass = 'bg-warning';
                    statusText = 'در حال بررسی';
                    break;
                case 'resolved':
                    statusClass = 'bg-success';
                    statusText = 'حل شده';
                    break;
                case 'closed':
                    statusClass = 'bg-secondary';
                    statusText = 'بسته شده';
                    break;
            }
            
            issueHistoryHtml += `
                <tr>
                    <td>${issue.created_at || '-'}</td>
                    <td>${issue.description ? issue.description.substring(0, 50) + (issue.description.length > 50 ? '...' : '') : '-'}</td>
                    <td><span class="badge ${priorityClass}">${priorityText}</span></td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td>${issue.reporter_name || '-'}</td>
                </tr>
            `;
        });
        
        issueHistoryHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    } else {
        issueHistoryHtml = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                تاکنون مشکلی برای این پایانه ثبت نشده است.
            </div>`;
    }
    
    // افزودن بخش ثبت مشکل
    const issueFormHtml = `
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ثبت مشکل جدید
                </h5>
            </div>
            <div class="card-body">
                <form id="issueReportForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]')?.value || ''}">
                    <input type="hidden" id="issue_terminal_id" name="terminal_id" value="${terminal.id}">
                    
                    <div class="mb-3">
                        <label for="issue_description" class="form-label">شرح مشکل</label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="4" required></textarea>
                        <div class="invalid-feedback">
                            لطفاً شرح مشکل را وارد کنید.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="issue_priority" class="form-label">اولویت</label>
                        <select class="form-select" id="issue_priority" name="issue_priority">
                            <option value="low">کم</option>
                            <option value="medium" selected>متوسط</option>
                            <option value="high">زیاد</option>
                            <option value="critical">بحرانی</option>
                        </select>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-danger" id="submitIssueBtn" onclick="submitIssue(${terminal.id})">
                            <i class="fas fa-save me-2"></i> ثبت مشکل
                        </button>
                    </div>
                </form>
                <div id="issueFormAlert" class="mt-3" style="display: none;"></div>
            </div>
        </div>
    `;
    
    // اضافه کردن فرم ثبت رول
    const rollFormHtml = `
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    ثبت رول کاغذی جدید
                </h5>
            </div>
            <div class="card-body">
                <form id="rollSubmitForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]')?.value || ''}">
                    <input type="hidden" name="terminal_id" value="${terminal.id}">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="roll_quantity" class="form-label">تعداد رول:</label>
                                <input type="number" class="form-control" id="roll_quantity" name="roll_quantity" min="1" value="1" required>
                                <div class="invalid-feedback">
                                    لطفاً تعداد معتبر وارد کنید.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="delivery_date" class="form-label">تاریخ تحویل:</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="${new Date().toISOString().split('T')[0]}" required>
                                <div class="invalid-feedback">
                                    لطفاً تاریخ را وارد کنید.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="mb-3 w-100">
                                <button type="button" class="btn btn-success w-100" id="submitRollBtn" onclick="submitRoll(${terminal.id})">
                                    <i class="fas fa-save me-2"></i> ثبت رول
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="roll_description" class="form-label">توضیحات (اختیاری):</label>
                        <textarea class="form-control" id="roll_description" name="roll_description" rows="2"></textarea>
                    </div>
                </form>
                <div id="rollFormAlert" class="mt-3" style="display: none;"></div>
            </div>
        </div>
        
        <!-- نمایش تاریخچه رول‌های تحویل داده شده -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    تاریخچه رول‌های تحویل داده شده
                </h5>
            </div>
            <div class="card-body">
                <div id="rollHistoryTable" class="roll-history-container">
                    <div class="text-center p-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                        <p class="mt-2">در حال بارگذاری تاریخچه رول‌ها...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // اضافه کردن همه بخش‌ها به صفحه - با ترتیب مناسب
    modalContent.innerHTML = html + issueHistoryHtml + issueFormHtml + rollFormHtml;
    
    // بارگذاری تاریخچه رول‌ها
    loadRollHistory(terminal.id);
    
    // اضافه کردن اعتبارسنجی به فرم‌ها
    initFormValidation();
    
    // اضافه کردن استایل‌های خاص برای جدول جدید
    addCustomStyles();
}

// تابع اضافه کردن استایل‌های سفارشی برای جدول جدید
function addCustomStyles() {
    // اگر استایل از قبل وجود دارد، آن را حذف کنیم
    const existingStyle = document.getElementById('terminal-detail-custom-styles');
    if (existingStyle) {
        existingStyle.remove();
    }
    
    // ایجاد استایل جدید
    const style = document.createElement('style');
    style.id = 'terminal-detail-custom-styles';
    style.textContent = `
        .bg-light-peach {
            background-color: #ffebe6;
        }
        .bg-light-green {
            background-color: #e6ffe6;
        }
        .table-bordered td {
            padding: 12px 15px;
        }
    `;
    
    // افزودن استایل به هدر صفحه
    document.head.appendChild(style);
}

// ثبت مشکل - با بهبود در رابط کاربری و اعتبارسنجی
function submitIssue(terminalId) {
    // اعتبارسنجی فرم
    const form = document.getElementById('issueReportForm');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const issueDescription = document.getElementById('issue_description').value.trim();
    const issuePriority = document.getElementById('issue_priority').value;
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // بررسی اعتبار فرم
    if (!issueDescription) {
        showFormMessage('issueFormAlert', 'لطفاً شرح مشکل را وارد کنید.', 'danger');
        return;
    }
    
    // غیرفعال کردن دکمه ثبت و نمایش وضعیت بارگذاری
    const submitBtn = document.getElementById('submitIssueBtn');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> در حال ثبت...';
    
    // پنهان کردن پیام قبلی
    hideFormMessage('issueFormAlert');
    
    // ارسال درخواست به سرور
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('terminal_id', terminalId);
    formData.append('issue_description', issueDescription);
    formData.append('issue_priority', issuePriority);
    
    fetch('process_terminal.php?action=reportIssue', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در ثبت مشکل');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // نمایش پیام موفقیت
            showFormMessage('issueFormAlert', 'مشکل با موفقیت ثبت شد.', 'success');
            
            // پاک کردن فرم
            document.getElementById('issue_description').value = '';
            document.getElementById('issue_priority').value = 'medium';
            form.classList.remove('was-validated');
            
            // اضافه کردن رویداد به مودال برای رفرش صفحه هنگام بستن
            setupModalRefresh();
            
            // بارگذاری مجدد اطلاعات پایانه برای نمایش مشکل جدید در تاریخچه
            setTimeout(() => {
                reloadTerminalDetails(terminalId);
            }, 1500);
        } else {
            // نمایش پیام خطا
            showFormMessage('issueFormAlert', data.message || 'خطا در ثبت مشکل', 'danger');
        }
    })
    .catch(error => {
        // نمایش خطا
        showFormMessage('issueFormAlert', 'خطا در ثبت مشکل: ' + error.message, 'danger');
        console.error('خطا در ثبت مشکل:', error);
    })
    .finally(() => {
        // فعال کردن مجدد دکمه
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
}

// ثبت رول - با بهبود در رابط کاربری و اعتبارسنجی
function submitRoll(terminalId) {
    // اعتبارسنجی فرم
    const form = document.getElementById('rollSubmitForm');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const rollQuantity = document.getElementById('roll_quantity').value;
    const deliveryDate = document.getElementById('delivery_date').value;
    const rollDescription = document.getElementById('roll_description').value;
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // بررسی مقادیر ورودی
    if (!rollQuantity || parseInt(rollQuantity) < 1) {
        showFormMessage('rollFormAlert', 'لطفاً تعداد رول را به درستی وارد کنید.', 'danger');
        return;
    }
    
    if (!deliveryDate) {
        showFormMessage('rollFormAlert', 'لطفاً تاریخ تحویل را مشخص کنید.', 'danger');
        return;
    }
    
    // نمایش وضعیت بارگذاری
    const submitBtn = document.getElementById('submitRollBtn');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> در حال ثبت...';
    
    // پنهان کردن پیام قبلی
    hideFormMessage('rollFormAlert');
    
    // ارسال درخواست به سرور
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('terminal_id', terminalId);
    formData.append('quantity', rollQuantity);
    formData.append('delivery_date', deliveryDate);
    formData.append('description', rollDescription);
    
    fetch('process_roll.php?action=submitRoll', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در ثبت رول');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // نمایش پیام موفقیت
            showFormMessage('rollFormAlert', 'رول با موفقیت ثبت شد.', 'success');
            
            // اضافه کردن رویداد به مودال برای رفرش صفحه هنگام بستن
            setupModalRefresh();
            
            // بازنشانی فرم
            document.getElementById('roll_quantity').value = '1';
            document.getElementById('roll_description').value = '';
            form.classList.remove('was-validated');
            
            // به‌روزرسانی تعداد رول نمایش داده شده در صفحه
            updateTerminalRollCount(terminalId, parseInt(rollQuantity));
            
            // بارگذاری مجدد تاریخچه رول‌ها با تأخیر بیشتر
            setTimeout(() => {
                loadRollHistory(terminalId);
            }, 2000); // افزایش تأخیر به 2 ثانیه
        } else {
            // نمایش پیام خطا
            showFormMessage('rollFormAlert', data.message || 'خطا در ثبت رول.', 'danger');
        }
    })
    .catch(error => {
        console.error('خطا در ثبت رول:', error);
        // نمایش پیام خطا
        showFormMessage('rollFormAlert', 'خطا در ثبت رول: ' + error.message, 'danger');
    })
    .finally(() => {
        // بازگرداندن دکمه به حالت عادی
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
}

// نمایش پیام فرم
function showFormMessage(elementId, message, type = 'success') {
    const alertDiv = document.getElementById(elementId);
    if (!alertDiv) return;
    
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
    `;
    alertDiv.style.display = 'block';
    
    // حذف خودکار پیام پس از 5 ثانیه
    if (type === 'success') {
        setTimeout(() => {
            if (alertDiv.parentElement) {
                hideFormMessage(elementId);
            }
        }, 5000);
    }
}

// پنهان کردن پیام فرم
function hideFormMessage(elementId) {
    const alertDiv = document.getElementById(elementId);
    if (alertDiv) {
        alertDiv.style.display = 'none';
    }
}

// راه‌اندازی اعتبارسنجی فرم‌ها
function initFormValidation() {
    // انتخاب تمام فرم‌ها با کلاس needs-validation
    const forms = document.querySelectorAll('.needs-validation');
    
    // حلقه روی آنها و جلوگیری از ارسال
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// تابع برای بارگذاری تاریخچه رول‌ها
function loadRollHistory(terminalId) {
    const rollHistoryTable = document.getElementById('rollHistoryTable');
    if (!rollHistoryTable) return;
    
    rollHistoryTable.innerHTML = `
        <div class="text-center p-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
            <p class="mt-2">در حال بارگذاری تاریخچه رول‌ها...</p>
        </div>`;
    
    fetch(`process_roll.php?action=getRollHistory&terminal_id=${terminalId}`, {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در دریافت تاریخچه رول‌ها');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayRollHistory(data.rolls);
        } else {
            rollHistoryTable.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.message || 'خطا در دریافت تاریخچه رول‌ها'}
                </div>`;
        }
    })
    .catch(error => {
        console.error('خطا در دریافت تاریخچه رول‌ها:', error);
        rollHistoryTable.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-times-circle me-2"></i>
                خطا در دریافت تاریخچه رول‌ها: ${error.message}
            </div>`;
    });
}

// تابع برای نمایش تاریخچه رول‌ها
function displayRollHistory(rolls) {
    const rollHistoryTable = document.getElementById('rollHistoryTable');
    if (!rollHistoryTable) return;
    
    if (!rolls || rolls.length === 0) {
        rollHistoryTable.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                تاکنون رولی برای این پایانه ثبت نشده است.
            </div>`;
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>تاریخ تحویل</th>
                        <th>تعداد</th>
                        <th>توضیحات</th>
                        <th>ثبت کننده</th>
                        <th>تاریخ ثبت</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    rolls.forEach((roll, index) => {
        html += `
            <tr class="roll-history-row">
                <td>${index + 1}</td>
                <td>${roll.delivery_date || '-'}</td>
                <td>
                    <span class="badge bg-info rounded-pill">${roll.quantity || '0'}</span>
                </td>
                <td>${roll.description || '-'}</td>
                <td>${roll.user_name || '-'}</td>
                <td>${roll.created_at || '-'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    rollHistoryTable.innerHTML = html;
    
    // اضافه کردن افکت‌های انیمیشن به تاریخچه رول‌ها
    const rows = rollHistoryTable.querySelectorAll('.roll-history-row');
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.classList.add('animated-row');
        }, index * 50);
    });
}

// اضافه کردن رویداد رفرش صفحه هنگام بستن مودال
function setupModalRefresh() {
    const terminalDetailsModal = document.getElementById('terminalDetailsModal');
    if (terminalDetailsModal) {
        if (!terminalDetailsModal._hasRefreshEvent) {
            terminalDetailsModal.addEventListener('hidden.bs.modal', function() {
                // رفرش کل صفحه با انیمیشن پس از بستن مودال
                showLoading('searchResults');
                
                // استفاده از location.reload برای رفرش کامل صفحه
                setTimeout(() => {
                    window.location.reload();
                }, 500);
                
                // اگر فقط می‌خواهید نتایج جستجو به‌روز شوند، از کد زیر استفاده کنید
                // setTimeout(() => {
                //     performSearch();
                // }, 500);
            });
            
            // علامت‌گذاری که رویداد اضافه شده است
            terminalDetailsModal._hasRefreshEvent = true;
        }
    }
}

// بارگذاری مجدد اطلاعات پایانه
function reloadTerminalDetails(terminalId) {
    if (!terminalId) return;
    
    // فقط بخش‌های مورد نیاز را به‌روزرسانی کنید، نه کل مودال را
    fetch(`process_terminal.php?action=getDetails&terminal_id=${terminalId}`, {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در دریافت اطلاعات');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.terminal && data.terminal.issues) {
            // به‌روزرسانی فقط بخش تاریخچه مشکلات
            updateIssueHistory(data.terminal.issues);
        }
    })
    .catch(error => {
        console.error('خطا در به‌روزرسانی جزئیات پایانه:', error);
    });
}

// به‌روزرسانی بخش تاریخچه مشکلات
function updateIssueHistory(issues) {
    const issueHistorySection = document.querySelector('.card-header:has(.fa-history)').closest('.card');
    if (!issueHistorySection) return;
    
    if (!issues || issues.length === 0) {
        issueHistorySection.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                تاکنون مشکلی برای این پایانه ثبت نشده است.
            </div>`;
        return;
    }
    
    let issueHistoryHtml = `
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>
                تاریخچه مشکلات
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>تاریخ</th>
                            <th>شرح مشکل</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>ثبت کننده</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    issues.forEach(issue => {
        let priorityClass = '';
        let priorityText = '';
        
        switch(issue.priority) {
            case 'low':
                priorityClass = 'bg-info';
                priorityText = 'کم';
                break;
            case 'medium':
                priorityClass = 'bg-primary';
                priorityText = 'متوسط';
                break;
            case 'high':
                priorityClass = 'bg-warning';
                priorityText = 'زیاد';
                break;
            case 'critical':
                priorityClass = 'bg-danger';
                priorityText = 'بحرانی';
                break;
        }
        
        let statusClass = '';
        let statusText = '';
        
        switch(issue.status) {
            case 'open':
                statusClass = 'bg-danger';
                statusText = 'باز';
                break;
            case 'in_progress':
                statusClass = 'bg-warning';
                statusText = 'در حال بررسی';
                break;
            case 'resolved':
                statusClass = 'bg-success';
                statusText = 'حل شده';
                break;
            case 'closed':
                statusClass = 'bg-secondary';
                statusText = 'بسته شده';
                break;
        }
        
        issueHistoryHtml += `
            <tr class="issue-history-row">
                <td>${issue.created_at || '-'}</td>
                <td>${issue.description ? issue.description.substring(0, 50) + (issue.description.length > 50 ? '...' : '') : '-'}</td>
                <td><span class="badge ${priorityClass}">${priorityText}</span></td>
                <td><span class="badge ${statusClass}">${statusText}</span></td>
                <td>${issue.reporter_name || '-'}</td>
            </tr>
        `;
    });
    
    issueHistoryHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    issueHistorySection.innerHTML = issueHistoryHtml;
    
    // اضافه کردن افکت‌های انیمیشن به سطرهای جدید
    const rows = issueHistorySection.querySelectorAll('.issue-history-row');
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.classList.add('animated-row');
        }, index * 50);
    });
}

// اضافه کردن افکت hover به کارت‌ها
function addCardHoverEffect() {
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('card-hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('card-hover');
        });
    });
}

// نمایش هشدار عمومی
function showAlert(message, type = 'info') {
    if (!message) return;
    
    // ایجاد عنصر هشدار
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-floating`;
    alertDiv.role = 'alert';
    
    // افزودن پیام
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
    `;
    
    // افزودن به صفحه
    document.body.appendChild(alertDiv);
    
    // افکت ظاهر شدن
    setTimeout(() => {
        alertDiv.classList.add('show-alert');
    }, 10);
    
    // حذف خودکار پس از 5 ثانیه
    setTimeout(() => {
        alertDiv.classList.remove('show-alert');
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 300);
    }, 5000);
}

// تبدیل وضعیت و اولویت به متن فارسی
function getStatusText(status) {
    switch(status) {
        case 'open': return 'باز';
        case 'in_progress': return 'در حال بررسی';
        case 'resolved': return 'حل شده';
        case 'closed': return 'بسته شده';
        default: return status || '-';
    }
}

function getPriorityText(priority) {
    switch(priority) {
        case 'low': return 'کم';
        case 'medium': return 'متوسط';
        case 'high': return 'زیاد';
        case 'critical': return 'بحرانی';
        default: return priority || '-';
    }
}

// تغییر نمایش فیلد توضیحات در فرم به‌روزرسانی وضعیت
function toggleCommentsField(selectElement, commentsId) {
    const commentsDiv = document.getElementById(commentsId);
    if (!commentsDiv) return;
    
    const selectedValue = selectElement.value;
    
    // فقط در صورت انتخاب "حل شده" یا "بسته شده" نمایش داده شود
    if (selectedValue === 'resolved' || selectedValue === 'closed') {
        commentsDiv.style.display = 'block';
        
        commentsDiv.style.display = 'block';
        
        // اضافه کردن کلاس انیمیشن
        commentsDiv.classList.add('fade-in');
        
        // ضروری بودن فیلد توضیحات
        const textarea = commentsDiv.querySelector('textarea');
        if (textarea) {
            textarea.setAttribute('required', 'required');
        }
    } else {
        // حذف کلاس انیمیشن
        commentsDiv.classList.remove('fade-in');
        commentsDiv.style.display = 'none';
        
        // غیرضروری کردن فیلد توضیحات
        const textarea = commentsDiv.querySelector('textarea');
        if (textarea) {
            textarea.removeAttribute('required');
        }
    }
}
// اضافه کردن رویداد تغییر اندازه صفحه
window.addEventListener('resize', function() {
    // اگر نتایج جستجو در صفحه وجود دارد، آن را مجدداً بارگذاری می‌کنیم
    const searchResults = document.getElementById('searchResults');
    if (searchResults && searchResults.querySelector('table') && window.lastSearchResults) {
        displaySearchResults(window.lastSearchResults);
    }
});

// ذخیره نتایج آخرین جستجو
// این خط را در انتهای تابع performSearch اضافه کنید (قبل از پایان بلاک then)
window.lastSearchResults = data.results;

// بارگذاری آمار کاربر
function loadUserStats() {
    // دریافت المان‌های آمار
    const userTotalRollsElement = document.getElementById('user-total-rolls');
    const userTodayPmsElement = document.getElementById('user-today-pms');
    const userTotalIssuesElement = document.getElementById('user-total-issues');
    const userOpenIssuesElement = document.getElementById('user-open-issues');
    
    // بررسی وجود المان‌ها
    if (!userTotalRollsElement || !userTodayPmsElement || !userTotalIssuesElement || !userOpenIssuesElement) {
        return; // اگر المان‌ها وجود ندارند، خارج شو
    }
    
    // دریافت آمار از سرور
    fetch('get_user_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // نمایش آمار با انیمیشن
                animateNumber(userTotalRollsElement, data.stats.totalRolls);
                animateNumber(userTodayPmsElement, data.stats.todayPMs);
                animateNumber(userTotalIssuesElement, data.stats.totalIssues);
                animateNumber(userOpenIssuesElement, data.stats.openIssues);
            } else {
                console.error('خطا در دریافت آمار کاربر:', data.message);
                // نمایش پیام خطا در المان‌ها
                userTotalRollsElement.textContent = '-';
                userTodayPmsElement.textContent = '-';
                userTotalIssuesElement.textContent = '-';
                userOpenIssuesElement.textContent = '-';
            }
        })
        .catch(error => {
            console.error('خطا در دریافت آمار کاربر:', error);
            // نمایش پیام خطا در المان‌ها
            userTotalRollsElement.textContent = '-';
            userTodayPmsElement.textContent = '-';
            userTotalIssuesElement.textContent = '-';
            userOpenIssuesElement.textContent = '-';
        });
}

// انیمیشن برای نمایش اعداد آمار
function animateNumber(element, finalValue) {
    if (!element) return;
    
    const duration = 1000; // مدت زمان انیمیشن به میلی‌ثانیه
    const finalNum = parseInt(finalValue, 10);
    const startTime = performance.now();
    
    element.textContent = '0';
    
    function updateNumber(currentTime) {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);
        
        const currentValue = Math.floor(progress * finalNum);
        element.textContent = currentValue.toLocaleString('fa-IR');
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        } else {
            element.textContent = finalNum.toLocaleString('fa-IR');
        }
    }
    
    requestAnimationFrame(updateNumber);
}

// اضافه کردن تابع loadUserStats به بخش بارگذاری داشبورد
document.addEventListener('DOMContentLoaded', function() {
    // کد موجود...
    
    // بارگذاری ویجت‌های داشبورد
    function loadDashboardWidgets() {
        // کد موجود...
        
        // بارگذاری آمار کاربر
        loadUserStats();
        
        // بارگذاری فعالیت‌های کاربر
        const userActivitiesContainer = document.getElementById('user-activities-container');
        if (userActivitiesContainer) {
            fetch('get_user_activities.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('user-activities-loading').style.display = 'none';
                    
                    if (data.success) {
                        let html = '';
                        
                        if (data.activities.length === 0) {
                            html = '<div class="alert alert-info">هیچ فعالیتی یافت نشد.</div>';
                        } else {
                            html = '<div class="issue-timeline">';
                            
                            data.activities.forEach(activity => {
                                let iconClass = '';
                                let actionText = '';
                                
                                switch(activity.action) {
                                    case 'report_issue':
                                        iconClass = 'fas fa-exclamation-triangle text-warning';
                                        actionText = 'گزارش مشکل جدید';
                                        break;
                                    case 'update_issue':
                                        iconClass = 'fas fa-edit text-info';
                                        actionText = 'به‌روزرسانی مشکل';
                                        break;
                                    case 'submit_roll':
                                        iconClass = 'fas fa-receipt text-success';
                                        actionText = 'ثبت رول جدید';
                                        break;
                                    case 'login':
                                        iconClass = 'fas fa-sign-in-alt text-primary';
                                        actionText = 'ورود به سیستم';
                                        break;
                                    case 'logout':
                                        iconClass = 'fas fa-sign-out-alt text-danger';
                                        actionText = 'خروج از سیستم';
                                        break;
                                    case 'add_marketing':
                                        iconClass = 'fas fa-plus-circle text-success';
                                        actionText = 'ثبت بازاریابی جدید';
                                        break;
                                    case 'update_marketing':
                                        iconClass = 'fas fa-edit text-info';
                                        actionText = 'ویرایش بازاریابی';
                                        break;
                                    case 'update_marketing_status':
                                        iconClass = 'fas fa-check-circle text-primary';
                                        actionText = 'تغییر وضعیت بازاریابی';
                                        break;
                                    default:
                                        iconClass = 'fas fa-history text-secondary';
                                        actionText = activity.action;
                                }
                                
                                html += `
                                    <div class="issue-timeline-item">
                                        <div class="issue-timeline-item-content">
                                            <div class="issue-timeline-item-time">
                                                <i class="${iconClass} me-2"></i>
                                                ${activity.log_time}
                                            </div>
                                            <div>
                                                <strong>${actionText}</strong>
                                                ${activity.details ? `<p class="mb-0 mt-1 text-break">${activity.details}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                        }
                        
                        userActivitiesContainer.innerHTML = html;
                        userActivitiesContainer.style.display = 'block';
                    } else {
                        userActivitiesContainer.innerHTML = '<div class="alert alert-warning">خطا در بارگذاری فعالیت‌ها</div>';
                        userActivitiesContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('user-activities-loading').style.display = 'none';
                    userActivitiesContainer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری فعالیت‌ها</div>';
                    userActivitiesContainer.style.display = 'block';
                });
        }
    }
    
    // کد موجود...
});
// بارگذاری تخصیص‌های تایید نشده
function loadPendingAssignments() {
    const pendingAssignmentsContainer = document.getElementById('pendingAssignments');
    
    if (!pendingAssignmentsContainer) return;
    
    fetch('process_roll_confirmation.php?action=get_pending_assignments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.assignments.length === 0) {
                    pendingAssignmentsContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            هیچ تخصیص تایید نشده‌ای وجود ندارد.
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.assignments.length} تخصیص رول کاغذ در انتظار تایید شما می‌باشد.
                    </div>
                `;
                
                html += `<div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>تعداد</th>
                                <th>تاریخ تخصیص</th>
                                <th>توسط</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.assignments.forEach(assignment => {
                    html += `
                        <tr>
                            <td>
                                <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                            </td>
                            <td>${assignment.assign_date}</td>
                            <td>${assignment.assigned_by_name || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="confirmAssignment(${assignment.id})">
                                    <i class="fas fa-check me-1"></i> تایید دریافت
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                    </table>
                </div>`;
                
                pendingAssignmentsContainer.innerHTML = html;
            } else {
                pendingAssignmentsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در بارگذاری تخصیص‌ها'}
                    </div>
                `;
            }
        })
        .catch(error => {
            pendingAssignmentsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
            console.error('خطا:', error);
        });
}

// بارگذاری تخصیص‌های تایید شده
function loadConfirmedAssignments() {
    const confirmedAssignmentsContainer = document.getElementById('confirmedAssignments');
    
    if (!confirmedAssignmentsContainer) return;
    
    fetch('process_roll_confirmation.php?action=get_confirmed_assignments&limit=5')
        .then(response => {
            // بررسی content-type
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new TypeError("پاسخ سرور در قالب JSON نیست");
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.assignments.length === 0) {
                    confirmedAssignmentsContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            هیچ سابقه‌ای یافت نشد.
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>تعداد</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>تاریخ تایید</th>
                                    <th>توسط</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.assignments.forEach(assignment => {
                    html += `
                        <tr>
                            <td>
                                <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                            </td>
                            <td>${assignment.assign_date}</td>
                            <td>${assignment.confirm_date}</td>
                            <td>${assignment.assigned_by_name || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                    </table>
                </div>`;
                
                confirmedAssignmentsContainer.innerHTML = html;
            } else {
                confirmedAssignmentsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در بارگذاری تخصیص‌ها'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('خطا:', error);
            confirmedAssignmentsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور: ${error.message}
                </div>
            `;
        });
}
// اصلاح بخش کنترل تایید رول در dashboard.js
// dashboard.js - فایل جاوااسکریپت اختصاصی برای داشبورد (بخش مربوط به تایید رول کاغذ)
function confirmAssignment(assignmentId) {
    if (!confirm('آیا از تایید دریافت این رول کاغذ اطمینان دارید؟')) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (!csrfToken || !csrfToken.value) {
        // تلاش برای یافتن توکن در فیلدهای hidden موجود
        const hiddenTokenInput = document.querySelector('input[type="hidden"][name="csrf_token"]');
        
        if (!hiddenTokenInput || !hiddenTokenInput.value) {
            alert('خطای امنیتی: توکن CSRF یافت نشد');
            console.error('CSRF token not found in form');
            return;
        }
        
        csrfToken.value = hiddenTokenInput.value;
    }
    
    // نمایش loading
    const confirmBtn = event.target;
    const originalBtnContent = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال تایید...';
    
    // ایجاد فرم برای ارسال داده‌ها
    const formData = new FormData();
    formData.append('assignment_id', assignmentId);
    formData.append('csrf_token', csrfToken.value);
    formData.append('action', 'confirm_assignment');
    
    // ارسال درخواست تایید
    fetch('process_roll_confirmation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('خطا در تایید رول');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // نمایش پیام موفقیت
            showAlert('رول با موفقیت تایید شد.', 'success');
            
            // بارگذاری مجدد لیست تخصیص‌ها
            loadPendingAssignments();
            loadConfirmedAssignments();
        } else {
            // نمایش پیام خطا
            showAlert(data.message || 'خطا در تایید رول', 'danger');
        }
    })
    .catch(error => {
        // نمایش خطا
        showAlert('خطا در تایید رول: ' + error.message, 'danger');
        console.error('خطا در تایید رول:', error);
    })
    .finally(() => {
        // فعال کردن مجدد دکمه
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalBtnContent;
    });
}

// اضافه کردن به رویداد DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // بارگذاری بخش تایید رول کاغذ
    loadPendingAssignments();
    loadConfirmedAssignments();
});