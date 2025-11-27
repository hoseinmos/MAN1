// device_report.js - اسکریپت مربوط به صفحه گزارش دستگاه برای کاربران

document.addEventListener('DOMContentLoaded', function() {
    // بارگذاری گزارش‌های کاربر
    loadUserReports();
    
    // رویدادهای فرم
    setupFormEvents();
});

// بارگذاری گزارش‌های کاربر
function loadUserReports() {
    const userReportsList = document.getElementById('userReportsList');
    
    if (!userReportsList) return;
    
    userReportsList.innerHTML = `
        <tr>
            <td colspan="6" class="text-center">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <p class="mt-2">در حال بارگذاری...</p>
            </td>
        </tr>
    `;
    
    fetch('process_device_report.php?action=get_user_reports')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.reports.length === 0) {
                    userReportsList.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center">هیچ گزارشی یافت نشد</td>
                        </tr>
                    `;
                    return;
                }
                
                let html = '';
                
                data.reports.forEach(report => {
                    let damageType = '';
                    if (report.status === 'healthy') {
                        damageType = 'دستگاه سالم';
                    } else {
                        if (report.is_terminal_damaged && report.is_adapter_damaged) {
                            damageType = 'دستگاه و آداپتور';
                        } else if (report.is_terminal_damaged) {
                            damageType = 'دستگاه';
                        } else if (report.is_adapter_damaged) {
                            damageType = 'آداپتور';
                        }
                    }
                    
                    let statusBadge = '';
                    let statusClass = '';
                    switch (report.status) {
                        case 'healthy':
                            statusBadge = '<span class="badge bg-success">سالم</span>';
                            statusClass = 'repair-status-healthy';
                            break;
                        case 'pending':
                            statusBadge = '<span class="badge bg-danger">در انتظار</span>';
                            statusClass = 'repair-status-pending';
                            break;
                        case 'in_progress':
                            statusBadge = '<span class="badge bg-warning text-dark">در حال تعمیر</span>';
                            statusClass = 'repair-status-in_progress';
                            break;
                        case 'repaired':
                            statusBadge = '<span class="badge bg-success">تعمیر شده</span>';
                            statusClass = 'repair-status-repaired';
                            break;
                        case 'replaced':
                            statusBadge = '<span class="badge bg-primary">تعویض شده</span>';
                            statusClass = 'repair-status-replaced';
                            break;
                        case 'returned':
                            statusBadge = '<span class="badge bg-secondary">برگشت داده شده</span>';
                            statusClass = 'repair-status-returned';
                            break;
                    }
                    
                    html += `
                        <tr class="${statusClass}">
                            <td>${report.id}</td>
                            <td>${report.terminal_serial || '-'}</td>
                            <td>${damageType}</td>
                            <td>${statusBadge}</td>
                            <td>${report.created_at || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewReportDetails(${report.id})">
                                    <i class="fas fa-eye me-1"></i> جزئیات
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                userReportsList.innerHTML = html;
            } else {
                userReportsList.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="alert alert-danger">
                                ${data.message || 'خطا در بارگذاری گزارش‌ها'}
                            </div>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            userReportsList.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="alert alert-danger">
                            خطا در ارتباط با سرور
                        </div>
                    </td>
                </tr>
            `;
            console.error('خطا:', error);
        });
}

// رویدادهای فرم
function setupFormEvents() {
    const deviceReportForm = document.getElementById('deviceReportForm');
    
    if (deviceReportForm) {
        // غیر فعال کردن اعتبارسنجی پیش‌فرض HTML5
        deviceReportForm.noValidate = true;
        
        // اعتبارسنجی فرم قبل از ارسال
        deviceReportForm.addEventListener('submit', function(e) {
            e.preventDefault(); // جلوگیری از ارسال فرم
            
            const terminalSerial = document.getElementById('terminal_serial').value;
            const deviceStatus = document.querySelector('input[name="device_status"]:checked');
            const damageDescription = document.getElementById('damage_description').value;
            
            if (!terminalSerial.trim()) {
                alert('لطفاً سریال دستگاه را وارد کنید');
                return;
            }
            
            if (!deviceStatus) {
                alert('لطفاً وضعیت دستگاه را مشخص کنید');
                return;
            }
            
            if (deviceStatus.value === 'damaged') {
                const isTerminalDamaged = document.getElementById('is_terminal_damaged').checked;
                const isAdapterDamaged = document.getElementById('is_adapter_damaged').checked;
                
                if (!isTerminalDamaged && !isAdapterDamaged) {
                    alert('لطفاً حداقل یک نوع خرابی را انتخاب کنید');
                    return;
                }
                
                if (!damageDescription.trim()) {
                    alert('لطفاً شرح خرابی را وارد کنید');
                    return;
                }
            }
            
            // اگر همه چیز درست بود، فرم را ارسال کن
            this.submit();
        });
    }
}

// مشاهده جزئیات گزارش
function viewReportDetails(reportId) {
    const reportDetailsModal = new bootstrap.Modal(document.getElementById('reportDetailsModal'));
    const reportDetailsContent = document.getElementById('reportDetailsContent');
    
    if (!reportDetailsContent) return;
    
    // نمایش مودال
    reportDetailsModal.show();
    
    // بارگذاری جزئیات
    reportDetailsContent.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">در حال بارگذاری اطلاعات...</p>
        </div>
    `;
    
    fetch(`process_device_report.php?action=get_report_details&report_id=${reportId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const { report, history } = data;
                
                let damageType = '';
                if (report.status === 'healthy') {
                    damageType = 'دستگاه سالم';
                } else {
                    if (report.is_terminal_damaged && report.is_adapter_damaged) {
                        damageType = 'دستگاه و آداپتور';
                    } else if (report.is_terminal_damaged) {
                        damageType = 'دستگاه';
                    } else if (report.is_adapter_damaged) {
                        damageType = 'آداپتور';
                    }
                }
                
                let statusBadge = '';
                switch (report.status) {
                    case 'healthy':
                        statusBadge = '<span class="badge bg-success">سالم</span>';
                        break;
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
                    <div class="mb-4">
                        <h6 class="mb-3">اطلاعات دستگاه</h6>
                        <div class="mb-2 p-2 bg-light rounded">
                            <strong>سریال دستگاه:</strong> ${report.terminal_serial || '-'}
                        </div>
                `;
                
                if (report.adapter_serial) {
                    html += `
                        <div class="mb-2 p-2 bg-light rounded">
                            <strong>سریال آداپتور:</strong> ${report.adapter_serial}
                        </div>
                    `;
                }
                
                html += `
                        <div class="mb-2 p-2 bg-light rounded">
                            <strong>نوع خرابی:</strong> ${damageType}
                        </div>
                        <div class="mb-2 p-2 bg-light rounded">
                            <strong>وضعیت:</strong> ${statusBadge}
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="mb-3">${report.status === 'healthy' ? 'توضیحات' : 'شرح خرابی'}</h6>
                        <div class="p-3 bg-light rounded">
                            ${report.damage_description || 'بدون توضیح'}
                        </div>
                    </div>
                `;
                
                // اضافه کردن توضیحات تعمیرکار اگر موجود باشد
                if (report.technician_notes) {
                    html += `
                        <div class="mb-4">
                            <h6 class="mb-3">توضیحات تعمیرکار</h6>
                            <div class="p-3 bg-light rounded">
                                ${report.technician_notes}
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
                            case 'healthy':
                                statusText = 'سالم';
                                break;
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
                
                reportDetailsContent.innerHTML = html;
            } else {
                reportDetailsContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در دریافت جزئیات گزارش'}
                    </div>
                `;
            }
        })
        .catch(error => {
            reportDetailsContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
            console.error('خطا:', error);
        });
}