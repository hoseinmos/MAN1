<!-- announcements_component.php - کامپوننت نمایش اطلاعیه‌ها در داشبورد -->
<div class="announcement-container">
    <div class="announcement-header">
        <div class="announcement-title"><i class="fas fa-bullhorn me-2"></i> اطلاعیه:</div>
        <div class="announcement-controls">
            <button id="prevAnnouncement" class="btn btn-sm announcement-nav-btn"><i class="fas fa-chevron-right"></i></button>
            <span id="announcementCounter">1/1</span>
            <button id="nextAnnouncement" class="btn btn-sm announcement-nav-btn"><i class="fas fa-chevron-left"></i></button>
        </div>
    </div>
    <div class="announcement-content">
        <div id="announcementText">در حال بارگذاری اطلاعیه‌ها...</div>
    </div>
    <div class="announcement-footer">
        <span id="announcementMeta"></span>
        <?php if(isset($_SESSION["user_description"]) && $_SESSION["user_description"] === "مدیر سیستم"): ?>
            <a href="admin_announcements.php" class="btn btn-sm btn-primary">مدیریت اطلاعیه‌ها</a>
        <?php endif; ?>
    </div>
</div>

<style>
.announcement-container {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
    border-right: 4px solid #3498db;
    position: relative;
}

.announcement-container:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-3px);
}

.announcement-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.announcement-title {
    font-weight: bold;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.announcement-controls {
    display: flex;
    align-items: center;
    gap: 5px;
}

.announcement-nav-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.announcement-nav-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.announcement-nav-btn:disabled {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
    cursor: not-allowed;
}

#announcementCounter {
    font-size: 0.8rem;
    margin: 0 5px;
    color: rgba(255, 255, 255, 0.9);
}

.announcement-content {
    padding: 15px;
    background-color: #fff;
    min-height: 80px;
    position: relative;
}

.announcement-content.normal {
    border-right: 4px solid #3498db;
}

.announcement-content.important {
    border-right: 4px solid #f39c12;
}

.announcement-content.critical {
    border-right: 4px solid #e74c3c;
}

#announcementText {
    line-height: 1.5;
}

.announcement-footer {
    background-color: #f8f9fa;
    padding: 8px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

#announcementMeta {
    font-style: italic;
}

.announcement-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 80px;
}

.announcement-icon {
    margin-right: 8px;
    display: inline-block;
}

.importance-marker {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.importance-marker.normal {
    background-color: #3498db;
}

.importance-marker.important {
    background-color: #f39c12;
}

.importance-marker.critical {
    background-color: #e74c3c;
}

.importance-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: bold;
    margin-right: 8px;
    display: inline-block;
}

.importance-badge.normal {
    background-color: #e3f2fd;
    color: #0d47a1;
}

.importance-badge.important {
    background-color: #fff3e0;
    color: #e65100;
}

.importance-badge.critical {
    background-color: #ffebee;
    color: #b71c1c;
}

.empty-announcement {
    text-align: center;
    padding: 20px 15px;
    color: #6c757d;
}

.empty-announcement i {
    font-size: 24px;
    margin-bottom: 10px;
    color: #adb5bd;
}

/* افکت انیمیشن برای تغییر اطلاعیه */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

/* استایل‌های واکنش‌گرا */
@media (max-width: 576px) {
    .announcement-title {
        font-size: 0.95rem;
    }
    
    .announcement-content {
        padding: 12px;
    }
    
    .announcement-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .announcement-footer .btn {
        align-self: flex-end;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // متغیرهای سراسری
    let announcements = [];
    let currentIndex = 0;
    
    // اجزای DOM
    const announcementText = document.getElementById('announcementText');
    const announcementMeta = document.getElementById('announcementMeta');
    const announcementCounter = document.getElementById('announcementCounter');
    const prevButton = document.getElementById('prevAnnouncement');
    const nextButton = document.getElementById('nextAnnouncement');
    const announcementContent = document.querySelector('.announcement-content');
    
    // دریافت اطلاعیه‌ها از سرور
    function fetchAnnouncements() {
        fetch('get_announcements.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.announcements.length > 0) {
                    announcements = data.announcements;
                    updateAnnouncementCounter();
                    showAnnouncement(0);
                    updateNavigationButtons();
                } else {
                    showEmptyState();
                }
            })
            .catch(error => {
                console.error('خطا در دریافت اطلاعیه‌ها:', error);
                showErrorState();
            });
    }
    
    // نمایش یک اطلاعیه با اندیس مشخص
    function showAnnouncement(index) {
        if (index < 0 || index >= announcements.length) return;
        
        const announcement = announcements[index];
        currentIndex = index;
        
        // تنظیم کلاس اهمیت
        announcementContent.className = 'announcement-content';
        announcementContent.classList.add(announcement.importance);
        
        // نمایش با افکت
        announcementText.style.opacity = 0;
        
        setTimeout(() => {
            // نمایش محتوا با توجه به اهمیت
            let importanceText = '';
            let importanceClass = '';
            
            switch(announcement.importance) {
                case 'normal':
                    importanceText = 'عادی';
                    importanceClass = 'normal';
                    break;
                case 'important':
                    importanceText = 'مهم';
                    importanceClass = 'important';
                    break;
                case 'critical':
                    importanceText = 'بحرانی';
                    importanceClass = 'critical';
                    break;
            }
            
            // ایجاد نشانگر اهمیت
            let badgeHtml = `<span class="importance-badge ${importanceClass}">${importanceText}</span>`;
            
            // نمایش عنوان و محتوا
            announcementText.innerHTML = `
                <div class="mb-2">
                    ${badgeHtml}
                    <strong>${announcement.title}</strong>
                </div>
                <div>${announcement.content}</div>
            `;
            
            // نمایش متادیتا
            announcementMeta.innerHTML = `
                <span><i class="fas fa-user-edit me-1"></i>${announcement.author_name}</span>
                <span class="mx-2">|</span>
                <span><i class="fas fa-clock me-1"></i>${announcement.created_at}</span>
            `;
            
            announcementText.style.opacity = 1;
            announcementText.classList.add('fade-in');
            
            // به‌روزرسانی دکمه‌ها
            updateNavigationButtons();
        }, 200);
    }
    
    // نمایش حالت خالی (بدون اطلاعیه)
    function showEmptyState() {
        announcementText.innerHTML = `
            <div class="empty-announcement">
                <i class="fas fa-info-circle d-block"></i>
                <p>در حال حاضر هیچ اطلاعیه فعالی وجود ندارد.</p>
            </div>
        `;
        announcementMeta.innerHTML = '';
        announcementCounter.textContent = '0/0';
        prevButton.disabled = true;
        nextButton.disabled = true;
    }
    
    // نمایش حالت خطا
    function showErrorState() {
        announcementText.innerHTML = `
            <div class="empty-announcement">
                <i class="fas fa-exclamation-triangle d-block text-danger"></i>
                <p>خطا در بارگیری اطلاعیه‌ها.</p>
            </div>
        `;
        announcementMeta.innerHTML = '';
        announcementCounter.textContent = '0/0';
        prevButton.disabled = true;
        nextButton.disabled = true;
    }
    
    // به‌روزرسانی شمارنده اطلاعیه‌ها
    function updateAnnouncementCounter() {
        if (announcements.length > 0) {
            announcementCounter.textContent = `${currentIndex + 1}/${announcements.length}`;
        } else {
            announcementCounter.textContent = '0/0';
        }
    }
    
    // به‌روزرسانی وضعیت دکمه‌های ناوبری
    function updateNavigationButtons() {
        prevButton.disabled = currentIndex <= 0;
        nextButton.disabled = currentIndex >= announcements.length - 1;
    }
    
    // ایجاد رویدادها برای دکمه‌های ناوبری
    prevButton.addEventListener('click', function() {
        if (currentIndex > 0) {
            showAnnouncement(currentIndex - 1);
            updateAnnouncementCounter();
        }
    });
    
    nextButton.addEventListener('click', function() {
        if (currentIndex < announcements.length - 1) {
            showAnnouncement(currentIndex + 1);
            updateAnnouncementCounter();
        }
    });
    
    // بارگذاری اطلاعیه‌ها در ابتدا
    fetchAnnouncements();
    
    // بارگذاری مجدد هر 5 دقیقه
    setInterval(fetchAnnouncements, 5 * 60 * 1000);
});
</script>