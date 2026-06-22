<?php
// Include the shared notification helpers FIRST
require_once 'notification_helpers.php';

// Include the database connection script if not already included
if (!isset($pdo)) {
    require_once 'config/config.php';
}

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// ENHANCED: Current user information with validation
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$profileImage = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
$notificationCount = 0;
$notifications = [];

try {
    if (!$userId) {
        throw new Exception("User ID not found in session");
    }

    // ENHANCED: Get current user data from database (always fresh data)
    $userStmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL, PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser && $currentUser['NAME']) {
        $userName = $currentUser['NAME'];
        $_SESSION['user_name'] = $userName;
    }

    if (!$currentUser) {
        // User doesn't exist anymore, logout
        error_log("❌ User ID {$userId} not found in database, logging out");
        session_destroy();
        header("Location: sign-in.php");
        exit();
    }

    // Update session data with current database values
    $_SESSION['user_id'] = $currentUser['USER_ID'];
    $_SESSION['user_name'] = $currentUser['NAME'];
    $_SESSION['user_email'] = $currentUser['EMAIL'];

    $userName = $currentUser['NAME'];

    if ($currentUser['PROFILE_IMAGE']) {
        $_SESSION['profile_image'] = $currentUser['PROFILE_IMAGE'];
        $profileImage = $currentUser['PROFILE_IMAGE'];
    }

    error_log("✅ Header: Current user is " . $userName . " (ID: " . $userId . ")");

    // ENHANCED: Get unread notification count with professional formatting
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM NOTIFICATIONS n 
        INNER JOIN USERS u ON n.USER_ID = u.USER_ID 
        WHERE n.TARGET_USER_ID = ? 
        AND n.IS_READ = 0 
        AND u.USER_ID IS NOT NULL
        AND n.CREATED_AT >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $countStmt->execute([$userId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $notificationCount = intval($result['count'] ?? 0);

    error_log("📱 User {$userName} has {$notificationCount} unread notifications");

    // ENHANCED: Get recent notifications with professional content and validation
    if ($notificationCount > 0) {
        $notifyStmt = $pdo->prepare("
            SELECT 
                n.NOTIFICATION_ID,
                n.USER_ID,
                n.TARGET_USER_ID,
                n.NOTIFICATION_TYPE,
                n.CONTENT,
                n.RELATED_ID,
                n.IS_READ,
                n.CREATED_AT,
                u.NAME as SENDER_NAME, 
                u.PROFILE_IMAGE as SENDER_IMAGE,
                u.USER_ID as SENDER_USER_ID
            FROM NOTIFICATIONS n 
            INNER JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ? 
            AND u.USER_ID IS NOT NULL
            AND n.USER_ID != n.TARGET_USER_ID
            AND n.CREATED_AT >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY n.CREATED_AT DESC 
            LIMIT 15
        ");
        $notifyStmt->execute([$userId]);
        $notifications = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("📋 Retrieved " . count($notifications) . " notifications for display");

        // ENHANCED: Clean up orphaned notifications and very old ones
        $cleanupStmt = $pdo->prepare("
            DELETE n FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE (n.TARGET_USER_ID = ? AND u.USER_ID IS NULL) 
            OR n.CREATED_AT < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
        $cleanupStmt->execute([$userId]);
        $cleanedUp = $cleanupStmt->rowCount();

        if ($cleanedUp > 0) {
            error_log("🧹 Cleaned up {$cleanedUp} orphaned/old notifications");
        }
    }
} catch (PDOException $e) {
    // Log error but don't break the page
    error_log("❌ Header database error: " . $e->getMessage());
    $notificationCount = 0;
    $notifications = [];
    // Try to get user name from session as fallback
    $userName = $_SESSION['user_name'] ?? 'User';
    $profileImage = $_SESSION['profile_image'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
}

// ENHANCED: Professional notification link generation with validation
function getNotificationLink($notification)
{
    if (!$notification || !isset($notification['NOTIFICATION_TYPE'])) {
        return 'dashboard.php';
    }

    $link = 'dashboard.php';
    $type = $notification['NOTIFICATION_TYPE'];
    $relatedId = $notification['RELATED_ID'] ?? null;
    $senderId = $notification['USER_ID'] ?? null;

    try {
        switch ($type) {
            case 'new_post':
                if ($relatedId) {
                    $link = 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_reaction':
            case 'new_comment':
                if ($relatedId) {
                    $link = 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_story':
                if ($senderId) {
                    $link = 'story.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_recipe':
                if ($relatedId) {
                    $link = 'recipes.php?recipe_id=' . intval($relatedId);
                }
                break;

            case 'new_follower':
                if ($senderId) {
                    $link = 'profile.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_message':
                if ($senderId) {
                    $link = 'messages.php?user_id=' . intval($senderId);
                }
                break;

            default:
                $link = 'dashboard.php';
                break;
        }
    } catch (Exception $e) {
        error_log("Error generating notification link: " . $e->getMessage());
        $link = 'dashboard.php';
    }

    return $link;
}

// ENHANCED: Add debugging information to logs
error_log("🔍 Header loaded for user: {$userName} (ID: {$userId}) with {$notificationCount} notifications");
?>

    <link rel="stylesheet" href="<?php echo asset('assets/css/header.css'); ?>">
<!-- Header -->
<header class="dashboard-header">
    <!-- Hamburger Menu for Mobile -->
    <div class="hamburger-menu" id="hamburger-menu">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="header-logo">
        <a href="Home.php" aria-label="Go to Feedora home" class="logo-link"><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090019/feedora/assets/Muslim-Woman-Cooking-Illustration_Muslim-Woman-Cooking-Illustration-01-svg.svg" alt="Feedora Icon"></a>
    </div>


    <!-- Header Actions -->
    <div class="header-actions">
        <!-- Search -->
        <div class="header-action-item search-container" id="search-container">
            <input type="text" placeholder="Search..." class="search-input">
            <button class="search-button" id="search-icon">
                <svg class=".search-button svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </div>

        <!-- FIXED: Enhanced Notification with Error Handling -->
        <div class="header-action-item notification-icon" id="notification-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
            <?php endif; ?>

            <!-- FIXED: Enhanced Notification Dropdown with Error Handling -->
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <?php if (count($notifications) > 0): ?>
                        <button class="mark-all-read" id="mark-all-read">Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="notification-list" id="notification-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            // Extra validation for each notification
                            if (!$notification || !isset($notification['NOTIFICATION_ID'])) {
                                continue;
                            }

                           $userImage = !empty($notification['SENDER_IMAGE']) ? $notification['SENDER_IMAGE'] : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
$userName = $notification['SENDER_NAME'] ?? 'Unknown User';

                            $isRead = ($notification['IS_READ'] ?? 0) ? 'read' : 'unread';
                            $linkUrl = getNotificationLink($notification);
                            $content = !empty($notification['CONTENT']) ? $notification['CONTENT'] : 'New notification';
                            $createdAt = !empty($notification['CREATED_AT']) ? $notification['CREATED_AT'] : date('Y-m-d H:i:s');
                            $notificationType = !empty($notification['NOTIFICATION_TYPE']) ? $notification['NOTIFICATION_TYPE'] : 'general';
                            ?>
                            <a href="<?php echo htmlspecialchars($linkUrl); ?>" class="notification-link" onclick="markNotificationAsRead(<?php echo intval($notification['NOTIFICATION_ID']); ?>)">
                                <div class="notification-item <?php echo $isRead; ?>" data-id="<?php echo intval($notification['NOTIFICATION_ID']); ?>">
                                    <div class="notification-user-image">
                                        <img src="<?php echo htmlspecialchars($userImage); ?>"
                                            alt="<?php echo htmlspecialchars($userName); ?>"
                                            onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo formatNotificationContent($content); ?></p>
                                        <span class="notification-time">
                                            <?php echo formatNotificationTime($createdAt, $notificationType); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-notifications">
                            <div class="no-notifications-icon">🔔</div>
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="notification-footer">
                    <a href="notifications.php" class="view-all-notifications">View all notifications</a>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <a href="settings.php">
            <div class="header-action-item settings-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
        </a>

        <!-- User Profile -->
        <div class="user-profile" id="user-profile">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <span class="user-role">Member</span>
            </div>
            <div class="user-profile-image">
                <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'); ?>"
                    alt="User Profile"
                    onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
            </div>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profile-dropdown">
                <a href="profile_Settings.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Settings</span>
                </a>
                <a href="log-out.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script src="<?php echo asset('assets/js/header.js'); ?>" defer></script>
<!-- Feedora client-side WebP image compressor (auto-attaches to image file inputs) -->
<script src="<?php echo asset('assets/js/compressor.js'); ?>" defer></script>

<!-- Feedora browser image cache (IndexedDB, 1h TTL) -->
<script src="<?php echo asset('assets/js/cache-manager.js'); ?>" defer></script>