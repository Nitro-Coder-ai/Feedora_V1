<?php
/**
 * COMPLETELY FIXED notifications.php - Professional Solution
 * 
 * Fixes all issues:
 * 1. Simplified filters (All & Unread only)
 * 2. Correct display logic
 * 3. Proper mark as read functionality
 * 4. Fixed stability after clicking notifications
 * 5. Corrected timezone and timestamp display
 * 6. Professional OOP design with error handling
 */

// Set timezone first (adjust to your server timezone)
date_default_timezone_set('UTC'); // Change this to your timezone, e.g., 'America/New_York'

require_once 'notification_helpers.php';
require_once 'config/config.php';
require_once 'notification_utils.php';
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

/**
 * NotificationManager Class - Professional OOP Approach
 */
class NotificationManager {
    private $pdo;
    private $userId;
    private $timezone;
    
    public function __construct($pdo, $userId, $timezone = 'UTC') {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->timezone = $timezone;
        
        // Set timezone for this session
        date_default_timezone_set($this->timezone);
        
        // Set MySQL timezone to match PHP
        $this->pdo->exec("SET time_zone = '+00:00'"); // Adjust based on your needs
    }
    
    /**
     * Get notifications with proper filtering
     */
    public function getNotifications($filter = 'all', $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        // Base query - always get notifications for current user
        $baseQuery = "
            SELECT 
                n.NOTIFICATION_ID,
                n.USER_ID,
                n.TARGET_USER_ID,
                n.NOTIFICATION_TYPE,
                n.CONTENT,
                n.RELATED_ID,
                n.IS_READ,
                n.CREATED_AT,
                COALESCE(u.NAME, 'System') as SENDER_NAME,
                COALESCE(u.PROFILE_IMAGE, 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg') as SENDER_IMAGE,
                u.USER_ID as SENDER_USER_ID
            FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ?
        ";
        
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ?
        ";
        
        $queryParams = [$this->userId];
        
        // Apply filter conditions
        switch ($filter) {
            case 'unread':
                $baseQuery .= " AND n.IS_READ = 0";
                $countQuery .= " AND n.IS_READ = 0";
                break;
            case 'all':
            default:
                // No additional conditions - show all notifications
                break;
        }
        
        // Add ordering and pagination
        $baseQuery .= " ORDER BY n.IS_READ ASC, n.CREATED_AT DESC LIMIT ? OFFSET ?";
        $finalParams = array_merge($queryParams, [$limit, $offset]);
        
        try {
            // Get total count
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute($queryParams);
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = intval($totalResult['total']);
            
            // Get notifications
            $stmt = $this->pdo->prepare($baseQuery);
            $stmt->execute($finalParams);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unread count (always needed for badge)
            $unreadStmt = $this->pdo->prepare("SELECT COUNT(*) as unread FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
            $unreadStmt->execute([$this->userId]);
            $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = intval($unreadResult['unread']);
            
            return [
                'notifications' => $notifications,
                'total' => $total,
                'unread_count' => $unreadCount,
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'per_page' => $limit
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::getNotifications error: " . $e->getMessage());
            return [
                'notifications' => [],
                'total' => 0,
                'unread_count' => 0,
                'current_page' => 1,
                'total_pages' => 0,
                'per_page' => $limit,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark single notification as read
     */
    public function markAsRead($notificationId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE NOTIFICATIONS 
                SET IS_READ = 1 
                WHERE NOTIFICATION_ID = ? AND TARGET_USER_ID = ? AND IS_READ = 0
            ");
            $result = $stmt->execute([$notificationId, $this->userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Get updated unread count
                $countStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
                $countStmt->execute([$this->userId]);
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'message' => 'Notification marked as read',
                    'unread_count' => intval($countResult['count'])
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Notification not found or already read'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::markAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead() {
        try {
            $stmt = $this->pdo->prepare("UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE TARGET_USER_ID = ? AND IS_READ = 0");
            $result = $stmt->execute([$this->userId]);
            
            if ($result) {
                $affectedRows = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => 'All notifications marked as read',
                    'count' => $affectedRows,
                    'unread_count' => 0
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No unread notifications found'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::markAllAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM NOTIFICATIONS WHERE NOTIFICATION_ID = ? AND TARGET_USER_ID = ?");
            $result = $stmt->execute([$notificationId, $this->userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Get updated unread count
                $countStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
                $countStmt->execute([$this->userId]);
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'message' => 'Notification deleted successfully',
                    'unread_count' => intval($countResult['count'])
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Notification not found'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::deleteNotification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Format notification time with proper timezone handling
     */
    public function formatTime($dateString) {
        try {
            // Create DateTime object with UTC timezone (assuming database stores UTC)
            $notificationTime = new DateTime($dateString, new DateTimeZone('UTC'));
            
            // Convert to user's timezone if needed
            $userTimezone = new DateTimeZone($this->timezone);
            $notificationTime->setTimezone($userTimezone);
            
            $now = new DateTime('now', $userTimezone);
            $diff = $now->getTimestamp() - $notificationTime->getTimestamp();
            
            // Format based on time difference
            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 2592000) {
                $weeks = floor($diff / 604800);
                return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago';
            } else {
                // For older notifications, show the actual date
                return $notificationTime->format('M j, Y \a\t g:i A');
            }
            
        } catch (Exception $e) {
            error_log("❌ NotificationManager::formatTime error: " . $e->getMessage());
            return 'Unknown time';
        }
    }
    
    /**
     * Generate notification link
     */
    public function getNotificationLink($notification) {
        if (!$notification || !isset($notification['NOTIFICATION_TYPE'])) {
            return 'dashboard.php';
        }

        $type = $notification['NOTIFICATION_TYPE'];
        $relatedId = $notification['RELATED_ID'] ?? null;
        $senderId = $notification['USER_ID'] ?? null;

        switch ($type) {
            case 'new_post':
            case 'new_reaction':
            case 'new_comment':
                if ($relatedId) {
                    return 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_story':
                if ($senderId) {
                    return 'story.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_recipe':
                if ($relatedId) {
                    return 'recipes.php?recipe_id=' . intval($relatedId);
                }
                break;

            case 'new_follower':
                if ($senderId) {
                    return 'profile.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_message':
                if ($senderId) {
                    return 'messages.php?user_id=' . intval($senderId);
                }
                break;
        }

        return 'dashboard.php';
    }
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = $_SESSION['profile_image'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

// Initialize NotificationManager
$notificationManager = new NotificationManager($pdo, $userId, 'UTC'); // Change timezone as needed

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'mark_as_read':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            if ($notificationId > 0) {
                echo json_encode($notificationManager->markAsRead($notificationId));
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            echo json_encode($notificationManager->markAllAsRead());
            break;
            
        case 'delete_notification':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            if ($notificationId > 0) {
                echo json_encode($notificationManager->deleteNotification($notificationId));
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit();
}

// Handle filter selection - ONLY 'all' and 'unread'
$allowedFilters = ['all', 'unread'];
$currentFilter = 'all'; // Default to 'all'

if (isset($_GET['filter']) && in_array($_GET['filter'], $allowedFilters)) {
    $currentFilter = $_GET['filter'];
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Get notifications
$result = $notificationManager->getNotifications($currentFilter, $page, $limit);
$notifications = $result['notifications'];
$totalNotifications = $result['total'];
$unreadCount = $result['unread_count'];
$totalPages = $result['total_pages'];
$error = $result['error'] ?? null;

// Check for action messages
$actionMessage = '';
if (isset($_SESSION['notification_message'])) {
    $actionMessage = $_SESSION['notification_message'];
    unset($_SESSION['notification_message']);
}

error_log("📋 FIXED Notifications: Filter={$currentFilter}, Found=" . count($notifications) . ", Total={$totalNotifications}, Unread={$unreadCount}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    
    <link rel="stylesheet" href="<?php echo asset('assets/css/notifications.css'); ?>">
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <div class="notifications-container">
            <!-- Header -->
            <div class="notifications-header">
                <div>
                    <h1 class="notifications-title">
                        🔔 Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="notifications-count"><?php echo $unreadCount; ?> new</span>
                        <?php endif; ?>
                    </h1>
                </div>
                
                <?php if ($totalNotifications > 0): ?>
                <div class="notifications-actions">
                    <?php if ($unreadCount > 0): ?>
                        <button class="action-btn" onclick="markAllAsRead()" id="markAllBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            Mark All Read
                        </button>
                    <?php endif; ?>
                    
                    <button class="action-btn" onclick="window.location.reload()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        Refresh
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- SIMPLIFIED: Only 2 Filters -->
            <div class="notification-filters">
                <span class="filter-label">Filter:</span>
                
                <a href="?filter=all" class="filter-btn <?php echo $currentFilter === 'all' ? 'active' : ''; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    All
                </a>
                
                <a href="?filter=unread" class="filter-btn <?php echo $currentFilter === 'unread' ? 'active' : ''; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    Unread
                </a>

                <div class="filter-counts">
                    <div class="filter-count-item">
                        <span>Total:</span>
                        <span class="filter-count-badge"><?php echo $totalNotifications; ?></span>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                    <div class="filter-count-item">
                        <span>Unread:</span>
                        <span class="filter-count-badge"><?php echo $unreadCount; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <?php echo $currentFilter === 'unread' ? '📭' : '🔕'; ?>
                        </div>
                        <h3>
                            <?php echo $currentFilter === 'unread' ? 'No unread notifications' : 'No notifications yet'; ?>
                        </h3>
                        <p>
                            <?php 
                            echo $currentFilter === 'unread' 
                                ? 'All your notifications have been read! 🎉' 
                                : 'You\'ll see notifications here when people interact with your posts, stories, and recipes!';
                            ?>
                        </p>
                        <?php if ($error): ?>
                            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-top: 20px;">
                                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        if (!$notification || !isset($notification['NOTIFICATION_ID'])) {
                            continue;
                        }
                        
                        $userImage = !empty($notification['SENDER_IMAGE']) ? $notification['SENDER_IMAGE'] : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
                        $userName = !empty($notification['SENDER_NAME']) ? $notification['SENDER_NAME'] : 'Unknown User';
                        $isRead = $notification['IS_READ'] ? 'read' : 'unread';
                        $linkUrl = $notificationManager->getNotificationLink($notification);
                        $content = !empty($notification['CONTENT']) ? $notification['CONTENT'] : 'New notification';
                        $formattedTime = $notificationManager->formatTime($notification['CREATED_AT']);
                        ?>
                        
                        <div class="notification-item <?php echo $isRead; ?>" 
                             id="notification-<?php echo $notification['NOTIFICATION_ID']; ?>">
                            
                            <img src="<?php echo htmlspecialchars($userImage); ?>" 
                                 alt="<?php echo htmlspecialchars($userName); ?>" 
                                 class="notification-avatar"
                                 onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
                            
                            <div class="notification-content">
                                <div class="notification-time">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <?php echo $formattedTime; ?>
                                    <?php if (!$notification['IS_READ']): ?>
                                        <span style="color: var(--primary-color); font-weight: 600; margin-left: 8px;">• NEW</span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="notification-text">
                                    <?php echo $content; ?>
                                </p>

                                <?php if ($linkUrl !== 'dashboard.php'): ?>
                                <div class="redirect-actions">
                                    <a href="<?php echo htmlspecialchars($linkUrl); ?>" 
                                       class="redirect-btn"
                                       onclick="markNotificationAsReadAndGo(<?php echo $notification['NOTIFICATION_ID']; ?>, '<?php echo htmlspecialchars($linkUrl); ?>', event)">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="7" y1="17" x2="17" y2="7"></line>
                                            <polyline points="7 7 17 7 17 17"></polyline>
                                        </svg>
                                        View Source
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-icon">
                                <span style="font-size: 24px;"><?php echo getNotificationIcon($notification['NOTIFICATION_TYPE']); ?></span>
                                <span style="font-size: 10px; color: #666; text-transform: capitalize; margin-top: 2px;">
                                    <?php echo str_replace('_', ' ', $notification['NOTIFICATION_TYPE']); ?>
                                </span>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notification['IS_READ']): ?>
                                    <button class="notification-action mark-read-btn" 
                                            onclick="event.stopPropagation(); markAsRead(<?php echo $notification['NOTIFICATION_ID']; ?>)"
                                            title="Mark as read">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="notification-action delete-btn" 
                                        onclick="event.stopPropagation(); deleteNotification(<?php echo $notification['NOTIFICATION_ID']; ?>)"
                                        title="Delete notification">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="m19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $currentFilter; ?>" class="pagination-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        (<?php echo $totalNotifications; ?> total)
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $currentFilter; ?>" class="pagination-btn">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="toast"></div>

    <script>
        window.NotificationsConfig = {
            actionMessage: <?php echo json_encode($actionMessage ?: null); ?>,
            currentFilter: <?php echo json_encode($currentFilter); ?>,
            stats: {
                displayed: <?php echo json_encode(count($notifications)); ?>,
                total: <?php echo json_encode($totalNotifications); ?>,
                unread: <?php echo json_encode($unreadCount); ?>
            }
        };
    </script>
    <script src="<?php echo asset('assets/js/notifications.js'); ?>" defer></script>

</body>
</html>
