<?php
// Get the current page file name to highlight the active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

    <link rel="stylesheet" href="<?php echo asset('assets/css/sidebar.css'); ?>">
<!-- Sidebar Navigation -->
<aside class="sidebar">
    <!-- Close button for mobile view -->
    <div class="sidebar-close-btn" id="sidebar-close-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </div>
    
    <div class="sidebar-logo">
        <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089976/feedora/assets/Feedora-svg.svg" alt="Feedora Logo">
    </div>
    <nav class="sidebar-menu">
        <!-- Main Navigation Items -->
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089843/feedora/assets/Asset-51-svg.svg" alt="Dashboard Icon">
            </div>
            <span class="menu-item-text">Dashboard</span>
        </a>
        <a href="recipes.php" class="menu-item <?php echo ($current_page == 'recipes.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089840/feedora/assets/Asset-45-svg.svg" alt="Recipes Icon">
            </div>
            <span class="menu-item-text">Recipes</span>
        </a>
        <a href="chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089829/feedora/assets/Asset-27-svg.svg" alt="Messages Icon">
            </div>
            <span class="menu-item-text">Messages</span>
        </a>
        <a href="chefs.php" class="menu-item <?php echo ($current_page == 'chefs.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089847/feedora/assets/Asset-60-svg.svg" alt="Meet Chefs Icon">
            </div>
            <span class="menu-item-text">Meet Chefs</span>
        </a>
        <a href="live.php" class="menu-item <?php echo ($current_page == 'live.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089835/feedora/assets/Asset-29-svg.svg" alt="Live Sessions Icon">
            </div>
            <span class="menu-item-text">Live Sessions</span>
        </a>


        <a href="profile_settings.php" class="menu-item <?php echo ($current_page == 'profile_settings.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <span class="menu-item-text">Profile</span>
        </a>
        <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
            <span class="menu-item-text">Settings</span>
        </a>

    </nav>

    <!-- Logout Section at Bottom -->
    <div class="sidebar-footer">
        <a href="log-out.php" class="menu-item logout-item">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <span class="menu-item-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Online Status Update Script -->
    <script src="<?php echo asset('assets/js/sidebar.js'); ?>" defer></script>
