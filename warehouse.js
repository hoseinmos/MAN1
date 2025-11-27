// warehouse.js - اسکریپت مربوط به صفحه مدیریت انبار

document.addEventListener('DOMContentLoaded', function() { //
    // مقداردهی اولیه داده‌بیندر تاریخ فارسی
    initPersianDatepickers(); //
    
    // رویدادهای فرم تخصیص رول
    setupAssignRollForm(); //
    
    // بارگذاری لیست تخصیص‌ها
    loadAssignments(1); //
    
    // رویدادهای فیلترها
    setupFilters(); //
    
    // رویدادهای فرم گزارش‌گیری
    setupReportForm(); //
});

// مقداردهی داده‌بیندر تاریخ فارسی
function initPersianDatepickers() { //
    const dateInputs = document.querySelectorAll('.persian-date'); //
    
    dateInputs.forEach(input => { //
        // بررسی وجود کتابخانه
        if (typeof $.fn.persianDatepicker === 'undefined') { //
            console.error('کتابخانه Persian Datepicker بارگذاری نشده است'); //
            return; //
        }
        
        $(input).persianDatepicker({ //
            format: 'YYYY/MM/DD', //
            initialValue: input.value ? true : false, //
            autoClose: true, //
            observer: true, //
            persianDigit: false, // استفاده از اعداد انگلیسی به جای فارسی
            calendar: { //
                persian: { //
                    locale: 'fa' //
                }
            }
        }); //
    }); //
}

// تابع تبدیل اعداد فارسی به انگلیسی
function convertPersianToEnglish(str) { //
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']; //
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']; //
    
    if (!str) return str; //
    
    for (let i = 0; i < 10; i++) { //
        const regex = new RegExp(persianNumbers[i], 'g'); //
        str = str.replace(regex, englishNumbers[i]); //
    }
    
    return str; //
} //

// رویدادهای فرم تخصیص رول
function setupAssignRollForm() { //
    const assignRollForm = document.getElementById('assignRollForm'); //
    
    if (assignRollForm) { //
        assignRollForm.addEventListener('submit', function(e) { //
            e.preventDefault(); //
            
            const formData = new FormData(this); //
            let assignDate = formData.get('assign_date'); //
            formData.set('assign_date', convertPersianToEnglish(assignDate)); // تبدیل تاریخ شمسی فارسی به انگلیسی

            const assignmentResult = document.getElementById('assignmentResult'); //
            const submitButton = this.querySelector('button[type="submit"]'); //
            const originalButtonText = submitButton.innerHTML; //
            
            // غیرفعال کردن دکمه و نمایش انیمیشن
            submitButton.disabled = true; //
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                در حال ارسال...
            `; //
            
            fetch('process_warehouse.php', { //
                method: 'POST', //
                body: formData //
            })
            .then(response => response.json()) //
            .then(data => { //
                if (data.success) { //
                    // نمایش پیام موفقیت
                    assignmentResult.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            تخصیص رول کاغذ با موفقیت انجام شد.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `; //
                    
                    // پاک کردن فرم
                    document.getElementById('quantity').value = '1'; //
                    document.getElementById('description').value = ''; //
                    
                    // بارگذاری مجدد لیست تخصیص‌ها
                    setTimeout(() => { //
                        loadAssignments(1); //
                    }, 1000); //
                } else { //
                    // نمایش پیام خطا
                    assignmentResult.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle me-2"></i>
                            ${data.message || 'خطا در ثبت تخصیص رول'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `; //
                }
            })
            .catch(error => { //
                assignmentResult.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-times-circle me-2"></i>
                        خطا در ارتباط با سرور
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `; //
                console.error('خطا:', error); //
            })
            .finally(() => { //
                // فعال کردن مجدد دکمه
                submitButton.disabled = false; //
                submitButton.innerHTML = originalButtonText; //
            }); //
        }); //
    } //
}

// بارگذاری لیست تخصیص‌ها
function loadAssignments(page = 1, limit = 10) { //
    const assignmentsList = document.getElementById('assignmentsList'); //
    const paginationContainer = document.getElementById('assignmentPagination'); //
    
    if (!assignmentsList) return; //
    
    // دریافت مقادیر فیلترها
    const userFilter = document.getElementById('userFilter')?.value || ''; //
    const statusFilter = document.getElementById('statusFilter')?.value || ''; //
    
    assignmentsList.innerHTML = `
        <tr>
            <td colspan="6" class="text-center">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <p class="mt-2">در حال بارگذاری...</p>
            </td>
        </tr>
    `; //
    
    fetch(`process_warehouse.php?action=get_assignments&page=${page}&limit=${limit}&user_id=${userFilter}&status=${statusFilter}`) //
        .then(response => response.json()) //
        .then(data => { //
            if (data.success) { //
                if (data.assignments.length === 0) { //
                    assignmentsList.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center">هیچ موردی یافت نشد</td>
                        </tr>
                    `; //
                    
                    if (paginationContainer) { //
                        paginationContainer.innerHTML = ''; //
                    }
                    
                    return; //
                }
                
                let html = ''; //
                
                data.assignments.forEach(assignment => { //
                    let statusBadge = ''; //
                    if (assignment.confirmed) { //
                        statusBadge = '<span class="badge bg-success">تایید شده</span>'; //
                    } else { //
                        statusBadge = '<span class="badge bg-warning text-dark">در انتظار تایید</span>'; //
                    }
                    
                    html += `
                        <tr>
                            <td>${assignment.user_name || '-'}</td>
                            <td>
                                <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                            </td>
                            <td>${assignment.assign_date || '-'}</td>
                            <td>${statusBadge}</td>
                            <td>${assignment.confirm_date || '-'}</td>
                            <td>${assignment.description || '-'}</td>
                        </tr>
                    `; //
                }); //
                
                assignmentsList.innerHTML = html; //
                
                // ایجاد صفحه‌بندی
                if (paginationContainer) { //
                    renderPagination(paginationContainer, data.pagination, loadAssignments); //
                }
            } else { //
                assignmentsList.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="alert alert-danger">
                                ${data.message || 'خطا در بارگذاری لیست تخصیص‌ها'}
                            </div>
                        </td>
                    </tr>
                `; //
            }
        })
        .catch(error => { //
            assignmentsList.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="alert alert-danger">
                            خطا در ارتباط با سرور
                        </div>
                    </td>
                </tr>
            `; //
            console.error('خطا:', error); //
        }); //
}

// ایجاد صفحه‌بندی
function renderPagination(container, pagination, callback) { //
    if (!container || !pagination) return; //
    
    const { page, total_pages } = pagination; //
    
    if (total_pages <= 1) { //
        container.innerHTML = ''; //
        return; //
    }
    
    let html = '<ul class="pagination justify-content-center">'; //
    
    // دکمه قبلی
    html += `
        <li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page - 1}" aria-label="قبلی">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `; //
    
    // صفحات
    const startPage = Math.max(1, page - 2); //
    const endPage = Math.min(total_pages, page + 2); //
    
    for (let i = startPage; i <= endPage; i++) { //
        html += `
            <li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>
        `; //
    }
    
    // دکمه بعدی
    html += `
        <li class="page-item ${page >= total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page + 1}" aria-label="بعدی">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `; //
    
    html += '</ul>'; //
    
    container.innerHTML = html; //
    
    // اضافه کردن رویدادها
    const pageLinks = container.querySelectorAll('.page-link'); //
    pageLinks.forEach(link => { //
        link.addEventListener('click', function(e) { //
            e.preventDefault(); //
            
            const pageNum = parseInt(this.getAttribute('data-page')); //
            if (pageNum && !isNaN(pageNum) && pageNum > 0) { //
                callback(pageNum); //
            }
        }); //
    }); //
}

// رویدادهای فیلترها
function setupFilters() { //
    const userFilter = document.getElementById('userFilter'); //
    const statusFilter = document.getElementById('statusFilter'); //
    
    if (userFilter) { //
        userFilter.addEventListener('change', function() { //
            loadAssignments(1); //
        }); //
    }
    
    if (statusFilter) { //
        statusFilter.addEventListener('change', function() { //
            loadAssignments(1); //
        }); //
    }
}

// رویدادهای فرم گزارش‌گیری
function setupReportForm() { //
    const generateReportBtn = document.getElementById('generateReportBtn'); //
    const exportReportBtn = document.getElementById('exportReportBtn'); //
    
    if (generateReportBtn) { //
        generateReportBtn.addEventListener('click', function() { //
            generateReport(); //
        }); //
    }
    
    if (exportReportBtn) { //
        exportReportBtn.addEventListener('click', function() { //
            generateReport(true); //
        }); //
    }
}

// تولید گزارش
function generateReport(isExport = false) { //
    const reportResult = document.getElementById('reportResult'); //
    let fromDate = document.getElementById('from_date').value; //
    let toDate = document.getElementById('to_date').value; //
    const userFilter = document.getElementById('report_user').value; //
    const statusFilter = document.getElementById('report_status').value; //
    
    // تبدیل اعداد فارسی به انگلیسی در تاریخ‌ها
    fromDate = convertPersianToEnglish(fromDate); //
    toDate = convertPersianToEnglish(toDate); //
    
    if (!reportResult) return; //
    
    reportResult.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">در حال تولید گزارش...</p>
        </div>
    `; //
    
    fetch(`process_warehouse.php?action=get_report&from_date=${fromDate}&to_date=${toDate}&user_id=${userFilter}&status=${statusFilter}`) //
        .then(response => response.json()) //
        .then(data => { //
            if (data.success) { //
                if (isExport) { //
                    exportToExcel(data.assignments, `گزارش_تخصیص_رول_از_${fromDate || '0'}_تا_${toDate || 'کنون'}`); //
                    return; //
                }
                
                const { assignments, stats } = data; //
                
                if (assignments.length === 0) { //
                    reportResult.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            هیچ موردی برای نمایش یافت نشد.
                        </div>
                    `; //
                    return; //
                }
                
                let html = `
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">آمار کلی گزارش</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">کل موارد</h6>
                                        <h5 class="mb-0 fw-bold">${stats.total_assignments}</h5>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">کل رول‌ها</h6>
                                        <h5 class="mb-0 fw-bold">${stats.total_rolls}</h5>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">تایید شده</h6>
                                        <h5 class="mb-0 fw-bold">${stats.confirmed_assignments}</h5>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted">در انتظار تایید</h6>
                                        <h5 class="mb-0 fw-bold">${stats.pending_assignments}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `; //
                
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
                                            <th>کاربر</th>
                                            <th>تعداد</th>
                                            <th>تاریخ تخصیص</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ تایید</th>
                                            <th>توضیحات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `; //
                
                assignments.forEach(assignment => { //
                    let statusBadge = ''; //
                    if (assignment.confirmed) { //
                        statusBadge = '<span class="badge bg-success">تایید شده</span>'; //
                    } else { //
                        statusBadge = '<span class="badge bg-warning text-dark">در انتظار تایید</span>'; //
                    }
                    
                    html += `
                        <tr>
                            <td>${assignment.user_name || '-'}</td>
                            <td>
                                <span class="badge bg-info rounded-pill">${assignment.quantity}</span>
                            </td>
                            <td>${assignment.assign_date || '-'}</td>
                            <td>${statusBadge}</td>
                            <td>${assignment.confirm_date || '-'}</td>
                            <td>${assignment.description || '-'}</td>
                        </tr>
                    `; //
                }); //
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `; //
                
                reportResult.innerHTML = html; //
            } else { //
                reportResult.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message || 'خطا در تولید گزارش'}
                    </div>
                `; //
            }
        })
        .catch(error => { //
            reportResult.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    خطا در ارتباط با سرور
                </div>
            `; //
            console.error('خطا:', error); //
        }); //
}

// خروجی اکسل
function exportToExcel(data, fileName) { //
    // تبدیل داده‌ها به فرمت مناسب اکسل
    const formattedData = data.map(item => { //
        return { //
            'کاربر': item.user_name || '-', //
            'کد کاربری': item.user_code || '-', //
            'تعداد رول': item.quantity, //
            'تاریخ تخصیص': item.assign_date || '-', //
            'وضعیت': item.confirmed ? 'تایید شده' : 'در انتظار تایید', //
            'تاریخ تایید': item.confirm_date || '-', //
            'توضیحات': item.description || '-', //
            'تخصیص دهنده': item.assigner_name || '-' //
        }; //
    }); //
    
    // ایجاد فایل اکسل
    const worksheet = XLSX.utils.json_to_sheet(formattedData, { skipHeader: false }); //
    const workbook = XLSX.utils.book_new(); //
    XLSX.utils.book_append_sheet(workbook, worksheet, 'تخصیص رول'); //
    
    // ذخیره فایل
    XLSX.writeFile(workbook, `${fileName}.xlsx`); //
}