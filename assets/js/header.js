/* Extracted from header.php inline <script> (refactor). */

    // FIXED: Enhanced notification functions with proper error handling
    let notificationDropdownOpen = false;
    let isProcessingNotification = false;

    // Header and sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Initializing enhanced header with robust notification system...');

        // Initialize all components with error handling
        try {
            initializeHeaderComponents();
            initializeNotificationSystem();
            initializeMobileFeatures();
            console.log('✅ Header initialized successfully');
        } catch (error) {
            console.error('❌ Header initialization error:', error);
            // Graceful fallback - at least basic functionality should work
            initializeBasicHeader();
        }
    });

    function initializeHeaderComponents() {
        const hamburgerMenu = document.getElementById('hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
        const menuItems = document.querySelectorAll('.menu-item');
        const menuTexts = document.querySelectorAll('.menu-item-text');
        const currentCategory = document.getElementById('current-category');
        const searchContainer = document.getElementById('search-container');
        const searchIcon = document.getElementById('search-icon');
        const searchInput = document.querySelector('.search-input');
        const userProfile = document.getElementById('user-profile');
        const profileDropdown = document.getElementById('profile-dropdown');

        // Get current page name from URL
        const currentPage = window.location.pathname.split('/').pop().split('.')[0];

        // Set current category text based on current page
        if (currentCategory) {
            let categoryText = 'Dashboard';
            const pageMap = {
                'dashboard': 'Dashboard',
                'recipes': 'Recipes',
                'messages': 'Messages',
                'chat': 'Messages',
                'chefs': 'Meet Chefs',
                'live': 'Live Sessions',
                'profile': 'Profile',
                'settings': 'Settings'
            };

            if (pageMap[currentPage]) {
                categoryText = pageMap[currentPage];
            }
            currentCategory.textContent = categoryText;
        }

        // Toggle sidebar function with error handling
        function toggleSidebar(show) {
            try {
                if (show) {
                    hamburgerMenu?.classList.add('active');
                    sidebar?.classList.add('active');
                    sidebarOverlay?.classList.add('active');
                    document.body.classList.add('sidebar-active');
                    document.body.style.overflow = 'hidden';

                    menuTexts.forEach(text => {
                        if (text) {
                            text.style.display = 'block';
                            text.style.opacity = '1';
                            text.style.visibility = 'visible';
                        }
                    });
                } else {
                    hamburgerMenu?.classList.remove('active');
                    sidebar?.classList.remove('active');
                    sidebarOverlay?.classList.remove('active');
                    document.body.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                }
            } catch (error) {
                console.error('Sidebar toggle error:', error);
            }
        }

        // Search functionality
        if (searchIcon && searchContainer) {
            searchIcon.addEventListener('click', function() {
                try {
                    searchContainer.classList.toggle('expanded');
                    if (searchContainer.classList.contains('expanded')) {
                        searchInput?.focus();
                    }
                } catch (error) {
                    console.error('Search toggle error:', error);
                }
            });

            document.addEventListener('click', function(event) {
                try {
                    if (!searchContainer.contains(event.target) && searchContainer.classList.contains('expanded')) {
                        searchContainer.classList.remove('expanded');
                    }
                } catch (error) {
                    console.error('Search close error:', error);
                }
            });
        }

        // Profile dropdown functionality
        if (userProfile && profileDropdown) {
            userProfile.addEventListener('click', function(e) {
                try {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');

                    // Close notification dropdown if open
                    const notificationDropdown = document.getElementById('notification-dropdown');
                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('show');
                        notificationDropdownOpen = false;
                    }
                } catch (error) {
                    console.error('Profile dropdown error:', error);
                }
            });

            document.addEventListener('click', function(event) {
                try {
                    if (!userProfile.contains(event.target)) {
                        profileDropdown.classList.remove('active');
                    }
                } catch (error) {
                    console.error('Profile dropdown close error:', error);
                }
            });
        }

        // Menu items functionality
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                try {
                    const menuText = this.querySelector('.menu-item-text');
                    if (menuText && currentCategory) {
                        currentCategory.textContent = menuText.textContent;
                    }
                    toggleSidebar(false);
                } catch (error) {
                    console.error('Menu item click error:', error);
                }
            });
        });

        // Hamburger menu functionality
        if (hamburgerMenu && sidebar && sidebarOverlay) {
            hamburgerMenu.addEventListener('click', function() {
                try {
                    const isActive = sidebar.classList.contains('active');
                    toggleSidebar(!isActive);
                } catch (error) {
                    console.error('Hamburger menu error:', error);
                }
            });

            sidebarOverlay.addEventListener('click', function() {
                try {
                    toggleSidebar(false);
                } catch (error) {
                    console.error('Sidebar overlay error:', error);
                }
            });

            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', function() {
                    try {
                        toggleSidebar(false);
                    } catch (error) {
                        console.error('Sidebar close error:', error);
                    }
                });
            }
        }
    }

    // FIXED: Enhanced notification system with comprehensive error handling
    function initializeNotificationSystem() {
        const notificationIcon = document.getElementById('notification-icon');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const markAllReadBtn = document.getElementById('mark-all-read');

        if (!notificationIcon || !notificationDropdown) {
            console.warn('Notification elements not found, skipping initialization');
            return;
        }

        // Notification dropdown functionality with error handling
        notificationIcon.addEventListener('click', function(e) {
            try {
                e.stopPropagation();
                notificationDropdownOpen = !notificationDropdownOpen;

                if (notificationDropdownOpen) {
                    notificationDropdown.classList.add('show');
                    // Close profile dropdown if open
                    const profileDropdown = document.getElementById('profile-dropdown');
                    if (profileDropdown) {
                        profileDropdown.classList.remove('active');
                    }

                    // Refresh notifications on open
                    refreshNotifications();
                } else {
                    notificationDropdown.classList.remove('show');
                }
            } catch (error) {
                console.error('Notification dropdown error:', error);
                showNotificationError('Error opening notifications');
            }
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            try {
                if (!notificationIcon.contains(event.target)) {
                    notificationDropdown.classList.remove('show');
                    notificationDropdownOpen = false;
                }
            } catch (error) {
                console.error('Notification close error:', error);
            }
        });

        // Mark all notifications as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                try {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!isProcessingNotification) {
                        markAllNotificationsAsRead();
                    }
                } catch (error) {
                    console.error('Mark all read error:', error);
                    showToast('Error marking notifications as read', 'error');
                }
            });
        }

        // Initialize periodic refresh (every 30 seconds when page is visible)
        initializeNotificationRefresh();
    }

    // FIXED: Enhanced function to mark a single notification as read
    function markNotificationAsRead(notificationId) {
        if (isProcessingNotification || !notificationId) {
            return;
        }

        isProcessingNotification = true;

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_notifications_read&notification_ids=${JSON.stringify([parseInt(notificationId)])}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the UI
                    const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                        notificationItem.classList.add('read');
                    }

                    // Update notification badge
                    updateNotificationBadge(data.unread_count);
                } else {
                    console.error('Failed to mark notification as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            })
            .finally(() => {
                isProcessingNotification = false;
            });
    }

    // FIXED: Enhanced function to mark all notifications as read
    function markAllNotificationsAsRead() {
        if (isProcessingNotification) {
            return;
        }

        const unreadNotifications = document.querySelectorAll('.notification-item.unread');
        const notificationIds = Array.from(unreadNotifications)
            .map(item => parseInt(item.getAttribute('data-id')))
            .filter(id => !isNaN(id));

        if (notificationIds.length === 0) {
            showToast('No unread notifications', 'info');
            return;
        }

        isProcessingNotification = true;
        const markAllBtn = document.getElementById('mark-all-read');

        if (markAllBtn) {
            markAllBtn.disabled = true;
            markAllBtn.textContent = 'Processing...';
        }

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_notifications_read&notification_ids=${JSON.stringify(notificationIds)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update all notification items
                    unreadNotifications.forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                    });

                    // Update notification badge
                    updateNotificationBadge(0);

                    // Hide mark all read button
                    if (markAllBtn) {
                        markAllBtn.style.display = 'none';
                    }

                    showToast('All notifications marked as read', 'success');
                } else {
                    throw new Error(data.message || 'Failed to mark notifications as read');
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
                showToast('Error marking notifications as read', 'error');
            })
            .finally(() => {
                isProcessingNotification = false;

                if (markAllBtn) {
                    markAllBtn.disabled = false;
                    markAllBtn.textContent = 'Mark all read';
                }
            });
    }

    // FIXED: Enhanced function to update notification badge
    function updateNotificationBadge(count) {
        try {
            const badge = document.getElementById('notification-badge');
            const countNum = parseInt(count) || 0;

            if (countNum > 0) {
                if (badge) {
                    badge.textContent = countNum;
                    badge.style.display = 'flex';
                } else {
                    // Create badge if it doesn't exist
                    const notificationIcon = document.getElementById('notification-icon');
                    if (notificationIcon) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.id = 'notification-badge';
                        newBadge.textContent = countNum;
                        notificationIcon.appendChild(newBadge);
                    }
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error updating notification badge:', error);
        }
    }

    // FIXED: Function to refresh notifications periodically
    function refreshNotifications() {
        if (isProcessingNotification) {
            return;
        }

        // Only refresh if dropdown is open
        if (!notificationDropdownOpen) {
            return;
        }

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_notifications&limit=10'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.notifications) {
                    updateNotificationList(data.notifications);
                }
            })
            .catch(error => {
                console.error('Error refreshing notifications:', error);
            });
    }

    // Function to update notification list
    function updateNotificationList(notifications) {
        try {
            const notificationList = document.getElementById('notification-list');
            if (!notificationList) return;

            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="no-notifications">
                        <div class="no-notifications-icon">🔔</div>
                        <p>No notifications yet</p>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                if (!notification || !notification.NOTIFICATION_ID) return;

const userImage = notification.SENDER_IMAGE || 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
const userName = notification.SENDER_NAME || 'Unknown User';


                const isRead = notification.IS_READ ? 'read' : 'unread';
                const content = notification.CONTENT || 'New notification';

                html += `
                    <a href="#" class="notification-link" onclick="markNotificationAsRead(${notification.NOTIFICATION_ID})">
                        <div class="notification-item ${isRead}" data-id="${notification.NOTIFICATION_ID}">
                            <div class="notification-user-image">
                                <img src="${userImage}" alt="${userName}" onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                            </div>
                            <div class="notification-content">
                                <p>${content}</p>
                                <span class="notification-time">
                                    ${formatTimeAgo(notification.CREATED_AT)}
                                </span>
                            </div>
                        </div>
                    </a>
                `;
            });

            notificationList.innerHTML = html;
        } catch (error) {
            console.error('Error updating notification list:', error);
            showNotificationError('Error loading notifications');
        }
    }

    // Function to show notification error
    function showNotificationError(message) {
        const notificationList = document.getElementById('notification-list');
        if (notificationList) {
            notificationList.innerHTML = `
                <div class="notification-error">
                    <p>⚠️ ${message}</p>
                    <button onclick="location.reload()" style="margin-top: 10px; padding: 5px 10px; background: #ED5A2C; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Refresh Page
                    </button>
                </div>
            `;
        }
    }

    // Function to initialize notification refresh
    function initializeNotificationRefresh() {
        // Refresh every 30 seconds when page is visible
        setInterval(() => {
            if (!document.hidden && notificationDropdownOpen) {
                refreshNotifications();
            }
        }, 30000);

        // Refresh when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && notificationDropdownOpen) {
                setTimeout(refreshNotifications, 1000);
            }
        });
    }

    // Helper function to format time
    function formatTimeAgo(dateString) {
        try {
            const now = new Date();
            const date = new Date(dateString);
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

            return date.toLocaleDateString();
        } catch (error) {
            return 'Unknown time';
        }
    }

    // Initialize mobile features
    function initializeMobileFeatures() {
        // Check for highlighted post on page load
        checkForHighlightedPost();
    }

    // Basic header initialization fallback
    function initializeBasicHeader() {
        console.log('Initializing basic header functionality...');

        // Basic hamburger menu
        const hamburger = document.getElementById('hamburger-menu');
        const sidebar = document.querySelector('.sidebar');

        if (hamburger && sidebar) {
            hamburger.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                document.body.classList.toggle('sidebar-active');
            });
        }
    }

    // Function to check for highlighted post
    function checkForHighlightedPost() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightPostId = urlParams.get('highlight');

            if (highlightPostId) {
                setTimeout(() => {
                    const postElement = document.getElementById(`post-${highlightPostId}`);
                    if (postElement) {
                        postElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        postElement.style.transition = 'all 0.3s ease';
                        postElement.style.backgroundColor = 'rgba(237, 90, 44, 0.1)';
                        postElement.style.border = '2px solid rgba(237, 90, 44, 0.3)';
                        postElement.style.borderRadius = '12px';
                        postElement.style.padding = '10px';

                        setTimeout(() => {
                            postElement.style.backgroundColor = '';
                            postElement.style.border = '';
                            postElement.style.padding = '';
                        }, 3000);
                    }
                }, 1000);
            }
        } catch (error) {
            console.error('Error highlighting post:', error);
        }
    }

    // Enhanced toast notification function
    function showToast(message, type = 'success') {
        try {
            // Remove existing toasts
            document.querySelectorAll('.toast-notification').forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 500;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInUp 0.3s ease, slideOutDown 0.3s ease 2.7s forwards;
                display: flex;
                align-items: center;
                gap: 8px;
                max-width: 300px;
            `;

            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
            toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

            // Add animation styles if not already present
            if (!document.getElementById('toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideInUp {
                        from { opacity: 0; transform: translateY(100px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    @keyframes slideOutDown {
                        from { opacity: 1; transform: translateY(0); }
                        to { opacity: 0; transform: translateY(100px); }
                    }
                `;
                document.head.appendChild(style);
            }

            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        } catch (error) {
            console.error('Error showing toast:', error);
        }
    }

    // Make functions globally available
    window.markNotificationAsRead = markNotificationAsRead;
    window.markAllNotificationsAsRead = markAllNotificationsAsRead;
    window.updateNotificationBadge = updateNotificationBadge;
    window.showToast = showToast;
    window.refreshNotifications = refreshNotifications;

    console.log('✅ Enhanced Header with Robust Notification System Loaded Successfully!');
