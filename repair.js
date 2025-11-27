// repair.js - اسکریپت مربوط به صفحه مدیریت تعمیرات

document.addEventListener('DOMContentLoaded', function() {
    // مقداردهی اولیه داده‌بیندر تاریخ فارسی
    initPersianDatepickers();
    
    // بارگذاری تعمیرات در انتظار
    loadPendingRepairs();
    
    // بارگذاری همه تعمیرات
    loadAllRepairs(1);
    
    // رویدادهای دکمه‌های به‌روزرسانی
    setupRefreshButtons();
    
    // بارگذاری مجدد داده‌ها هر 30 ثانیه
    setInterval(function() {
        loadPendingRepairs();
    }, 30000);
    
    // رویدادهای فیلترها
    setupFilters();
    
    // رویدادهای جستجو
    setupSearchForm();
    
    // رویدادهای فرم گزارش‌گیری
    setupReportForm();
    
    // رویدادهای مودال
    setupModalEvents();
});

// تابع تبدیل اعداد فارسی به انگلیسی
function convertPersianToEnglish(str) {
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    if (!str) return str;
    
    for (let i = 0; i < 10; i++) {
        const regex = new RegExp(persianNumbers[i], 'g');
        str = str.replace(regex, englishNumbers[i]);
    }
    
    return str;
}

// مقداردهی داده‌بیندر تاریخ فارسی
function initPersianDatepickers() {
    const dateInputs = document.querySelectorAll('.persian-date');
    
    dateInputs.forEach(input => {
        // بررسی وجود کتابخانه
        if (typeof $.fn.persianDatepicker === 'undefined') {
            console.error('کتابخانه Persian Datepicker بارگذاری نشده است');
            return;
        }
        
        $(input).persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: input.value ? true : false,
            autoClose: true,
            observer: true,
            persianDigit: false, // استفاده از اعداد انگلیسی به جای فارسی
            calendar: {
                persian: {
                    locale: 'fa'
                }
            }
        });
    });
}

// بارگذاری تعمیرات در انتظار
function loadPendingRepairs() {
    const pendingRepairsList = document.getElementById('pendingRepairsList');
    
    if (!pendingRepairsList) return;
    
    pendingRepairsList.innerHTML = `
        <tr>
            <td colspan="7" class="text-center">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <p class="mt-2">در حال بارگذاری...</p>
            </td>
        </tr>
    `;
    
    fetch('process_repair.php?action=get_pending_repairs')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.repairs.length === 0) {
                    pendingRepairsList.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center">هیچ تعمیر در انتظاری وجود ندارد</td>
                        </tr>
                    `;
                    return;
                }
                
                let html = '';
                
                data.repairs.forEach(repair => {
                    let damageType = '';
                    if (repair.is_terminal_damaged && repair.is_adapter_damaged) {
                        damageType = 'دستگاه و آداپتور';
                    } else if (repair.is_terminal_damaged) {
                        damageType = 'دستگاه';
                    } else if (repair.is_adapter_damaged) {
                        damageType = 'آداپتور';
                    }
                    
                    html += `
                        <tr>
                            <td>${repair.id}</td>
                            <td>${repair.terminal_serial || '-'}</td>
                            <td>${repair.adapter_serial || '-'}</td>
                            <td>${damageType}</td>
                            <td>${repair.reporter_name || '-'}</td>
                            <td>${repair.created_at || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewRepairDetails(${repair.id})">
                                    <i class="fas fa-eye me-1"></i> جزئیات
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                pendingRepairsList.innerHTML = html;
            } else {
                pendingRepairsList.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="alert alert-danger">
                                ${data.message || 'خطا در بارگذاری تعمیرات در انتظار'}
                            </div>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            pendingRepairsList.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="alert alert-danger">
                            خطا در ارتباط با سرور
                        </div>
                    </td>
                </tr>
            `;
            console.error('خطا:', error);
        });
}

// بارگذاری همه تعمیرات
function loadAllRepairs(page = 1, limit = 10) {
    const allRepairsList = document.getElementById('allRepairsList');
    const paginationContainer = document.getElementById('repairPagination');
    
    if (!allRepairsList) return;
    
    // دریافت مقادیر فیلترها
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    allRepairsList.innerHTML = `
        <tr>
            <td colspan="7" class="text-center">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <p class="mt-2">در حال بارگذاری...</p>
            </td>
        </tr>
    `;
    
    fetch(`process_repair.php?action=get_repairs&page=${page}&limit=${limit}&status=${statusFilter}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.repairs.length === 0) {
                    allRepairsList.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center">هیچ موردی یافت نشد</td>
                        </tr>
                    `;
                    
                    if (paginationContainer) {
                        paginationContainer.innerHTML = '';
                    }
                    
                    return;
                }
                
                let html = '';
                
                data.repairs.forEach(repair => {
                    let damageType = '';
                    if (repair.is_terminal_damaged && repair.is_adapter_damaged) {
                        damageType = 'دستگاه و آداپتور';
                    } else if (repair.is_terminal_damaged) {
                        damageType = 'دستگاه';
                    } else if (repair.is_adapter_damaged) {
                        damageType = 'آداپتور';
                    }
                    
                    let statusBadge = '';
                    switch (repair.status) {
                        case 'pending':
                            statusBadge = '<span class="badge bg-danger">در انتظار</span>';
                            break;
                        case 'in_progress':
                            statusBadge = '<span class="badge bg-warning text-dark">در حال تعمیر</span>';
                            break;
                        case 'repaired':
                            statusBadge = '<span class="badge bg-success">تعمیر شده</span>';
                            break;
                        case 'replaced':
                            statusBadge = '<span class="badge bg-primary">تعویض شده</span>';
                            break;
                        case 'returned':
                            statusBadge = '<span class="badge bg-secondary">برگشت داده شده</span>';
                            break;
                    }
                    
                    html += `
                        <tr>
                            <td>${repair.id}</td>
                            <td>${repair.terminal_serial || '-'}</td>
                            <td>${damageType}</td>
                            <td>${statusBadge}</td>
                            <td>${repair.reporter_name || '-'}</td>
                            <td>${repair.created_at || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewRepairDetails(${repair.id})">
                                    <i class="fas fa-eye me-1"></i> جزئیات
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                allRepairsList.innerHTML = html;
                
                // ایجاد صفحه‌بندی
                if (paginationContainer) {
                    renderPagination(paginationContainer, data.pagination, loadAllRepairs);
                }
            } else {
                allRepairsList.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="alert alert-danger">
                                ${data.message || 'خطا در بارگذاری تعمیرات'}
                            </div>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            allRepairsList.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="alert alert-danger">
                            خطا در ارتباط با سرور
                        </div>
                    </td>
                </tr>
            `;
            console.error('خطا:', error);
        });
}

// ایجاد صفحه‌بندی
function renderPagination(container, pagination, callback) {
    if (!container || !pagination) return;
    
    const { page, total_pages } = pagination;
    
    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    // دکمه قبلی
    html += `
        <li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page - 1}" aria-label="قبلی">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;
    
    // صفحات
    const startPage = Math.max(1, page - 2);
    const endPage = Math.min(total_pages, page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>
        `;
    }
    
    // دکمه بعدی
    html += `
        <li class="page-item ${page >= total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page + 1}" aria-label="بعدی">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;
    
    html += '</ul>';
    
    container.innerHTML = html;
    
    // اضافه کردن رویدادها
    const pageLinks = container.querySelectorAll('.page-link');
    pageLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const pageNum = parseInt(this.getAttribute('data-page'));
            if (pageNum && !isNaN(pageNum) && pageNum > 0) {
                callback(pageNum);
            }
        });
    });
}

// رویدادهای دکمه‌های به‌روزرسانی
function setupRefreshButtons() {
    const refreshPendingBtn = document.getElementById('refreshPendingBtn');
    
    if (refreshPendingBtn) {
        refreshPendingBtn.addEventListener('click', function() {
            loadPendingRepairs();
        });
    }
}

// رویدادهای فیلترها
function setupFilters() {
    const statusFilter = document.getElementById('statusFilter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            loadAllRepairs(1);
        });
    }
}

// مشاهده جزئیات تعمیر
function viewRepairDetails(repairId) {
    const repairModal = new bootstrap.Modal(document.getElementById('repairModal'));
    const repairDetails = document.getElementById('repairDetails');
    const repairIdInput = document.getElementById('repair_id');
    
    if (!repairDetails || !repairIdInput) return;
    
    // نمایش مودال
    repairModal.show();
    
    // تنظیم شناسه تعمیر در فرم
    repairIdInput.value = repairId;
    
    // بارگذاری جزئیات
    repairDetails.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">در حال بارگذاری اطلاعات...</p>
        </div>
    `;
    
    fetch(`process_repair.php?action=get_repair_details&repair_id=${repairId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const { repair, history } = data;
                
                let damageType = '';
                if (repair.is_terminal_damaged && repair.is_adapter_damaged) {
                    damageType = 'دستگاه و آداپتور';
                } else if (repair.is_terminal_damaged) {
                    damageType = 'دستگاه';
                } else if (repair.is_adapter_damaged) {
                    damageType = 'آداپتور';
                }
                
                let statusBadge = '';
                switch (repair.status) {
                    case 'pending':
                        statusBadge = '<span class="badge bg-danger">در انتظار</span>';
                        break;
                    case 'in_progress':
                        statusBadge = '<span class="badge bg-warning text-dark">در حال تعمیر</span>';
                        break;
                    case 'repaired':
                        statusBadge = '<span class="badge bg-success">تعمیر شده</span>';
                        break;
                    case 'replaced':
                        statusBadge = '<span class="badge bg-primary">تعویض شده</span>';
                        break;
                    case 'returned':
                        statusBadge = '<span class="badge bg-secondary">برگشت داده شده</span>';
                        break;
                }
                
                let html = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-3">اطلاعات دستگاه</h6>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>سریال دستگاه:</strong> ${repair.terminal_serial || '-'}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>سریال آداپتور:</strong> ${repair.adapter_serial || '-'}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>نوع خرابی:</strong> ${damageType}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>وضعیت:</strong> ${statusBadge}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">اطلاعات گزارش</h6>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>گزارش دهنده:</strong> ${repair.reporter_name || '-'}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>تاریخ گزارش:</strong> ${repair.created_at || '-'}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>تاریخ تعمیر:</strong> ${repair.repair_date || '-'}
                            </div>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>تعمیرکار:</strong> ${repair.technician_name || '-'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="mb-3">شرح خرابی</h6>
                        <div class="p-3 bg-light rounded">
                            ${repair.damage_description || 'بدون توضیح'}
                        </div>
                    </div>
                `;
                
                // اضافه کردن توضیحات تعمیرکار اگر موجود باشد
                if (repair.technician_notes) {
                    html += `
                        <div class="mb-4">
                            <h6 class="mb-3">توضیحات تعمیرکار</h6>
                            <div class="p-3 bg-light rounded">
                                ${repair.technician_notes}
                            </div>
                        </div>
                    `;
                }
                
                // اضافه کردن تاریخچه تغییرات
                if (history && history.length > 0) {
                    html += `
                        <div>
                            <h6 class="mb-3">تاریخچه تغییرات</h6>
                            <div class="timeline">
                    `;
                    
                    history.forEach(item => {
                        let statusText = '';
                        switch (item.status) {
                            case 'pending':
                                statusText = 'در انتظار';
                                break;
                            case 'in_progress':
                                statusText = 'در حال تعمیر';
                                break;
                            case 'repaired':
                                statusText = 'تعمیر شده';
                                break;
                            case 'replaced':
                                statusText = 'تعویض شده';
                                break;
                            case 'returned':
                                statusText = 'برگشت داده شده';
                                break;
                        }
                        
                        html += `
                            <div class="timeline-item">
                                <div class="timeline-date">${item.created_at} - ${item.user_name || '-'}</div>
                                <div class="p-2 bg-light rounded">
                                    <strong>وضعیت:</strong> ${statusText}<br>
                                    <strong>توضیحات:</strong> ${item.notes || 'بدون توضیح'}
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                }
                
                repairDetails.innerHTML = html;
                
                // تنظیم مقدار پیش‌فرض در فرم به‌روزرسانی
                document.getElementById('repair_status').value = repair.status;
                document.getElementById('technician_notes').value = '';
            } else {
                repairDetails.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در دریافت جزئیات تعمیر'}
                    </div>
                `;
            }
        })
        .catch(error => {
            repairDetails.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
            console.error('خطا:', error);
        });
}

// رویدادهای مودال
function setupModalEvents() {
    const updateRepairBtn = document.getElementById('updateRepairBtn');
    
    if (updateRepairBtn) {
        updateRepairBtn.addEventListener('click', function() {
            updateRepair();
        });
    }
}

// به‌روزرسانی وضعیت تعمیر
function updateRepair() {
    const form = document.getElementById('updateRepairForm');
    const repairId = document.getElementById('repair_id').value;
    const repairStatus = document.getElementById('repair_status').value;
    const technicianNotes = document.getElementById('technician_notes').value;
    let repairDate = document.getElementById('repair_date').value;
    
    // تبدیل اعداد فارسی به انگلیسی
    repairDate = convertPersianToEnglish(repairDate);
    
    // بررسی اعتبارسنجی
    if (!repairId || !repairStatus || !technicianNotes || !repairDate) {
        alert('لطفاً تمام فیلدهای ضروری را تکمیل کنید');
        return;
    }
    
    // اعتبارسنجی فرمت تاریخ شمسی
    const datePattern = /^\d{4}\/\d{2}\/\d{2}$/;
    if (!datePattern.test(repairDate)) {
        alert('فرمت تاریخ باید به صورت YYYY/MM/DD باشد');
        return;
    }
    
    // قرار دادن مقدار تبدیل شده در فرم
    document.getElementById('repair_date').value = repairDate;
    
    // غیرفعال کردن دکمه
    const updateBtn = document.getElementById('updateRepairBtn');
    const originalText = updateBtn.innerHTML;
    
    updateBtn.disabled = true;
    updateBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        در حال ثبت...
    `;
    
    // ارسال داده‌ها
    const formData = new FormData(form);
    
    fetch('process_repair.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // بستن مودال
            bootstrap.Modal.getInstance(document.getElementById('repairModal')).hide();
            
            // نمایش پیام موفقیت
            alert(data.message);
            
            // بارگذاری مجدد لیست‌ها
            loadPendingRepairs();
            loadAllRepairs(1);
        } else {
            alert(data.message || 'خطا در به‌روزرسانی وضعیت تعمیر');
        }
    })
    .catch(error => {
        alert('خطا در ارتباط با سرور');
        console.error('خطا:', error);
    })
    .finally(() => {
        // فعال کردن مجدد دکمه
        updateBtn.disabled = false;
        updateBtn.innerHTML = originalText;
    });
}

// رویدادهای فرم جستجو
function setupSearchForm() {
    const searchBtn = document.getElementById('searchBtn');
    
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            performSearch();
        });
    }
}

// انجام جستجو
function performSearch() {
    const searchResult = document.getElementById('searchResult');
    const terminalSerial = document.getElementById('terminal_serial').value;
    const adapterSerial = document.getElementById('adapter_serial').value;
    
    if (!searchResult) return;
    
    // بررسی اعتبارسنجی
    if (!terminalSerial && !adapterSerial) {
        searchResult.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                لطفاً حداقل یکی از فیلدهای جستجو را وارد کنید
            </div>
        `;
        return;
    }
    
    searchResult.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">در حال جستجو...</p>
        </div>
    `;
    
    fetch(`process_repair.php?action=search_repairs&terminal_serial=${terminalSerial}&adapter_serial=${adapterSerial}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.repairs.length === 0) {
                    searchResult.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            هیچ موردی یافت نشد
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">نتایج جستجو (${data.repairs.length} مورد)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>سریال دستگاه</th>
                                            <th>سریال آداپتور</th>
                                            <th>نوع خرابی</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ گزارش</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                data.repairs.forEach(repair => {
                    let damageType = '';
                    if (repair.is_terminal_damaged && repair.is_adapter_damaged) {
                        damageType = 'دستگاه و آداپتور';
                    } else if (repair.is_terminal_damaged) {
                        damageType = 'دستگاه';
                    } else if (repair.is_adapter_damaged) {
                        damageType = 'آداپتور';
                    }
                    
                    let statusBadge = '';
                    switch (repair.status) {
                        case 'pending':
                            statusBadge = '<span class="badge bg-danger">در انتظار</span>';
                            break;
                        case 'in_progress':
                            statusBadge = '<span class="badge bg-warning text-dark">در حال تعمیر</span>';
                            break;
                        case 'repaired':
                            statusBadge = '<span class="badge bg-success">تعمیر شده</span>';
                            break;
                        case 'replaced':
                            statusBadge = '<span class="badge bg-primary">تعویض شده</span>';
                            break;
                        case 'returned':
                            statusBadge = '<span class="badge bg-secondary">برگشت داده شده</span>';
                            break;
                    }
                    
                    html += `
                        <tr>
                            <td>${repair.id}</td>
                            <td>${repair.terminal_serial || '-'}</td>
                            <td>${repair.adapter_serial || '-'}</td>
                            <td>${damageType}</td>
                            <td>${statusBadge}</td>
                            <td>${repair.created_at || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewRepairDetails(${repair.id})">
                                    <i class="fas fa-eye me-1"></i> جزئیات
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                
                searchResult.innerHTML = html;
            } else {
                searchResult.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در جستجو'}
                    </div>
                `;
            }
        })
        .catch(error => {
            searchResult.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
            console.error('خطا:', error);
        });
}

// رویدادهای فرم گزارش‌گیری
function setupReportForm() {
    const generateReportBtn = document.getElementById('generateReportBtn');
    const exportReportBtn = document.getElementById('exportReportBtn');
    
    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', function() {
            generateReport();
        });
    }
    
    if (exportReportBtn) {
        exportReportBtn.addEventListener('click', function() {
            generateReport(true);
        });
    }
}

// تولید گزارش
function generateReport(isExport = false) {
    const reportResult = document.getElementById('reportResult');
    let fromDate = document.getElementById('from_date').value;
    let toDate = document.getElementById('to_date').value;
    const statusFilter = document.getElementById('report_status').value;
    const damageType = document.getElementById('damage_type').value;
    
    // تبدیل اعداد فارسی به انگلیسی در تاریخ‌ها
    fromDate = convertPersianToEnglish(fromDate);
    toDate = convertPersianToEnglish(toDate);
    
    if (!reportResult) return;
    
    reportResult.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">در حال تولید گزارش...</p>
        </div>
    `;
    
    fetch(`process_repair.php?action=get_report&from_date=${fromDate}&to_date=${toDate}&status=${statusFilter}&damage_type=${damageType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (isExport) {
                    exportToExcel(data.repairs, `گزارش_تعمیرات_از_${fromDate || '0'}_تا_${toDate || 'کنون'}`);
                    return;
                }
                
                const { repairs, stats } = data;
                
                if (repairs.length === 0) {
                    reportResult.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            هیچ موردی برای نمایش یافت نشد.
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">آمار کلی گزارش</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">کل تعمیرات</h6>
                                        <h5 class="mb-0 fw-bold">${stats.total_repairs}</h5>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">نوع خرابی</h6>
                                        <p class="mb-0 small">
                                            <span class="badge bg-info rounded-pill mb-1">دستگاه: ${stats.damage_types.terminal_only}</span>
                                            <span class="badge bg-info rounded-pill mb-1">آداپتور: ${stats.damage_types.adapter_only}</span>
                                            <span class="badge bg-info rounded-pill mb-1">هر دو: ${stats.damage_types.both}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">وضعیت</h6>
                                        <p class="mb-0 small">
                                            <span class="badge bg-danger rounded-pill mb-1">در انتظار: ${stats.status_counts.pending}</span>
                                            <span class="badge bg-warning text-dark rounded-pill mb-1">در حال تعمیر: ${stats.status_counts.in_progress}</span>
                                            <span class="badge bg-success rounded-pill mb-1">تعمیر شده: ${stats.status_counts.repaired}</span>
                                            <span class="badge bg-primary rounded-pill mb-1">تعویض شده: ${stats.status_counts.replaced}</span>
                                            <span class="badge bg-secondary rounded-pill mb-1">برگشت داده شده: ${stats.status_counts.returned}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                html += `
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">جزئیات گزارش</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>سریال دستگاه</th>
                                            <th>نوع خرابی</th>
                                            <th>وضعیت</th>
                                            <th>گزارش دهنده</th>
                                            <th>تاریخ گزارش</th>
                                            <th>تاریخ تعمیر</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                repairs.forEach(repair => {
                    let damageType = '';
                    if (repair.is_terminal_damaged && repair.is_adapter_damaged) {
                        damageType = 'دستگاه و آداپتور';
                    } else if (repair.is_terminal_damaged) {
                        damageType = 'دستگاه';
                    } else if (repair.is_adapter_damaged) {
                        damageType = 'آداپتور';
                    }
                    
                    let statusBadge = '';
                    switch (repair.status) {
                        case 'pending':
                            statusBadge = '<span class="badge bg-danger">در انتظار</span>';
                            break;
                        case 'in_progress':
                            statusBadge = '<span class="badge bg-warning text-dark">در حال تعمیر</span>';
                            break;
                        case 'repaired':
                            statusBadge = '<span class="badge bg-success">تعمیر شده</span>';
                            break;
                        case 'replaced':
                            statusBadge = '<span class="badge bg-primary">تعویض شده</span>';
                            break;
                        case 'returned':
                            statusBadge = '<span class="badge bg-secondary">برگشت داده شده</span>';
                            break;
                    }
                    
                    html += `
                        <tr>
                            <td>${repair.id}</td>
                            <td>${repair.terminal_serial || '-'}</td>
                            <td>${damageType}</td>
                            <td>${statusBadge}</td>
                            <td>${repair.reporter_name || '-'}</td>
                            <td>${repair.created_at || '-'}</td>
                            <td>${repair.repair_date || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                
                reportResult.innerHTML = html;
            } else {
                reportResult.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در تولید گزارش'}
                    </div>
                `;
            }
        })
        .catch(error => {
            reportResult.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
            console.error('خطا:', error);
        });
}

// خروجی اکسل
function exportToExcel(data, fileName) {
    // بررسی وجود کتابخانه XLSX
    if (typeof XLSX === 'undefined') {
        alert('کتابخانه XLSX بارگذاری نشده است. لطفاً صفحه را رفرش کنید.');
        return;
    }
    
    // تبدیل داده‌ها به فرمت مناسب اکسل
    const formattedData = data.map(item => {
        let damageType = '';
        if (item.is_terminal_damaged && item.is_adapter_damaged) {
            damageType = 'دستگاه و آداپتور';
        } else if (item.is_terminal_damaged) {
            damageType = 'دستگاه';
        } else if (item.is_adapter_damaged) {
            damageType = 'آداپتور';
        }
        
        let statusText = '';
        switch (item.status) {
            case 'pending':
                statusText = 'در انتظار';
                break;
            case 'in_progress':
                statusText = 'در حال تعمیر';
                break;
            case 'repaired':
                statusText = 'تعمیر شده';
                break;
            case 'replaced':
                statusText = 'تعویض شده';
                break;
            case 'returned':
                statusText = 'برگشت داده شده';
                break;
        }
        
        return {
            'شناسه': item.id,
            'سریال دستگاه': item.terminal_serial || '-',
            'سریال آداپتور': item.adapter_serial || '-',
            'نوع خرابی': damageType,
            'وضعیت': statusText,
            'گزارش دهنده': item.reporter_name || '-',
            'تاریخ گزارش': item.created_at || '-',
            'تاریخ تعمیر': item.repair_date || '-',
            'تعمیرکار': item.technician_name || '-',
            'توضیحات خرابی': item.damage_description || '-',
            'توضیحات تعمیرکار': item.technician_notes || '-'
        };
    });
    
    try {
        // ایجاد فایل اکسل
        const worksheet = XLSX.utils.json_to_sheet(formattedData, { skipHeader: false });
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'تعمیرات');
        
        // ذخیره فایل
        XLSX.writeFile(workbook, `${fileName}.xlsx`);
    } catch (error) {
        console.error('خطا در ایجاد فایل اکسل:', error);
        alert('خطا در ایجاد فایل اکسل. لطفاً دوباره تلاش کنید.');
    }
}