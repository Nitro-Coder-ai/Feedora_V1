/* Extracted from notifications.php inline <script> (refactor). PHP values via window.NotificationsConfig. */

        // COMPLETELY FIXED JavaScript for notifications
        
        // Global variables
        let isProcessing = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 FIXED Notifications System Loaded');
            
            if (window.NotificationsConfig.actionMessage) {
                showToast(window.NotificationsConfig.actionMessage, 'success');
            }

            console.log(`📊 Stats: ${window.NotificationsConfig.stats.displayed} displayed, ${window.NotificationsConfig.stats.total} total, ${window.NotificationsConfig.stats.unread} unread, filter: ${window.NotificationsConfig.currentFilter}`);
        });

        // FIXED: Mark notification as read and redirect
        function markNotificationAsReadAndGo(notificationId, redirectUrl, event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (isProcessing) return;
            
            console.log('📋 Mark as read and redirect:', notificationId, redirectUrl);
            
            showToast('Opening source...', 'info');
            
            // Open redirect URL immediately
            window.open(redirectUrl, '_blank');
            
            // Mark as read in background
            markAsReadRequest(notificationId, function(success, data) {
                if (success) {
                    updateNotificationUI(notificationId, data.unread_count);
                }
            });
        }

        // FIXED: Mark single notification as read
        function markAsRead(notificationId) {
            if (isProcessing) return;
            
            markAsReadRequest(notificationId, function(success, data) {
                if (success) {
                    updateNotificationUI(notificationId, data.unread_count);
                    showToast('✅ Marked as read', 'success');
                } else {
                    showToast('❌ ' + (data.message || 'Error marking as read'), 'error');
                }
            });
        }

        // FIXED: Mark all notifications as read
        function markAllAsRead() {
            const unreadNotifications = document.querySelectorAll('.notification-item.unread');
            if (unreadNotifications.length === 0) {
                showToast('ℹ️ No unread notifications', 'info');
                return;
            }

            if (!confirm(`Mark all ${unreadNotifications.length} notifications as read?`)) {
                return;
            }

            if (isProcessing) return;
            isProcessing = true;

            const markAllBtn = document.getElementById('markAllBtn');
            if (markAllBtn) {
                markAllBtn.disabled = true;
                markAllBtn.innerHTML = '<span>⏳ Processing...</span>';
            }

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI based on current filter
                    const currentFilter = window.NotificationsConfig.currentFilter;
                    
                    if (currentFilter === 'all') {
                        // In 'all' view, just update styling
                        unreadNotifications.forEach(notification => {
                            notification.classList.remove('unread');
                            notification.classList.add('read');
                            
                            const newIndicator = notification.querySelector('.notification-time span');
                            if (newIndicator && newIndicator.textContent.includes('NEW')) {
                                newIndicator.remove();
                            }
                            
                            const markReadBtn = notification.querySelector('.mark-read-btn');
                            if (markReadBtn) {
                                markReadBtn.remove();
                            }
                        });
                    } else if (currentFilter === 'unread') {
                        // In 'unread' view, remove notifications
                        unreadNotifications.forEach(notification => {
                            notification.style.animation = 'slideOut 0.3s ease forwards';
                            setTimeout(() => notification.remove(), 300);
                        });
                        
                        // Show empty state after animation
                        setTimeout(() => {
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                location.reload();
                            }
                        }, 400);
                    }
                    
                    updateUnreadCount(0);
                    showToast(`✅ ${data.count} notifications marked as read`, 'success');
                    
                    if (markAllBtn) {
                        markAllBtn.style.display = 'none';
                    }
                } else {
                    showToast('❌ ' + (data.message || 'Error marking all as read'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Network error occurred', 'error');
            })
            .finally(() => {
                isProcessing = false;
                if (markAllBtn) {
                    markAllBtn.disabled = false;
                    markAllBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Mark All Read
                    `;
                }
            });
        }

        // FIXED: Delete notification
        function deleteNotification(notificationId) {
            if (!confirm('🗑️ Delete this notification permanently?')) {
                return;
            }

            if (isProcessing) return;
            isProcessing = true;

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_notification&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationElement = document.getElementById(`notification-${notificationId}`);
                    if (notificationElement) {
                        notificationElement.style.animation = 'slideOut 0.3s ease forwards';
                        setTimeout(() => {
                            notificationElement.remove();
                            updateUnreadCount(data.unread_count);
                            
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    showToast('✅ Notification deleted', 'success');
                } else {
                    showToast('❌ ' + (data.message || 'Error deleting notification'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Network error occurred', 'error');
            })
            .finally(() => {
                isProcessing = false;
            });
        }

        // Helper function for mark as read requests
        function markAsReadRequest(notificationId, callback) {
            if (isProcessing) return;
            isProcessing = true;

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_as_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                callback(data.success, data);
            })
            .catch(error => {
                console.error('Error:', error);
                callback(false, { message: 'Network error occurred' });
            })
            .finally(() => {
                isProcessing = false;
            });
        }

        // Helper function to update notification UI
        function updateNotificationUI(notificationId, unreadCount) {
            const currentFilter = window.NotificationsConfig.currentFilter;
            const notificationElement = document.getElementById(`notification-${notificationId}`);
            
            if (notificationElement) {
                if (currentFilter === 'all') {
                    // In 'all' view, just update styling
                    notificationElement.classList.remove('unread');
                    notificationElement.classList.add('read');
                    
                    const newIndicator = notificationElement.querySelector('.notification-time span');
                    if (newIndicator && newIndicator.textContent.includes('NEW')) {
                        newIndicator.remove();
                    }
                    
                    const markReadBtn = notificationElement.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                } else if (currentFilter === 'unread') {
                    // In 'unread' view, remove notification
                    notificationElement.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        notificationElement.remove();
                        
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
            }
            
            updateUnreadCount(unreadCount);
        }

        // Helper function to update unread count
        function updateUnreadCount(count) {
            const countBadge = document.querySelector('.notifications-count');
            const markAllBtn = document.getElementById('markAllBtn');
            const filterCountBadges = document.querySelectorAll('.filter-count-badge');
            
            if (count === 0) {
                if (countBadge) {
                    countBadge.remove();
                }
                if (markAllBtn) {
                    markAllBtn.style.display = 'none';
                }
            } else if (countBadge) {
                countBadge.textContent = `${count} new`;
            }
            
            // Update filter count badges
            if (filterCountBadges.length > 1) {
                filterCountBadges[1].textContent = count;
            }
            
            // Update header notification badge
            const headerBadge = document.getElementById('notification-badge');
            if (headerBadge) {
                if (count > 0) {
                    headerBadge.textContent = count;
                    headerBadge.style.display = 'flex';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            
            toast.innerHTML = `<span>${message}</span>`;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Add slide-out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                    max-height: 100px;
                    padding: 20px 30px;
                    margin: 0;
                }
                to {
                    opacity: 0;
                    transform: translateX(-100%);
                    max-height: 0;
                    padding: 0 30px;
                    margin: 0;
                }
            }
        `;
        document.head.appendChild(style);

        console.log('✅ COMPLETELY FIXED Notifications System Ready!');
