<?php
// Include the database connection script
require_once 'config/config.php';
require_once 'includes/CloudinaryService.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Get online users action
    if ($_POST['action'] === 'get_online_users') {
        try {
            // Get users who were active in the last 5 minutes
            $stmt = $pdo->prepare(
                "SELECT USER_ID, LAST_ACTIVITY 
                FROM USER_SESSIONS 
                WHERE LAST_ACTIVITY > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmt->execute();
            $online_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get last seen for offline users
            $stmt = $pdo->prepare(
                "SELECT USER_ID, LAST_ACTIVITY 
                FROM USER_SESSIONS 
                WHERE LAST_ACTIVITY <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmt->execute();
            $offline_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $last_seen = [];
            foreach ($offline_users as $user) {
                $last_activity = strtotime($user['LAST_ACTIVITY']);
                $now = time();
                $diff = $now - $last_activity;
                
                if ($diff < 3600) {
                    $last_seen[$user['USER_ID']] = floor($diff / 60) . ' minutes ago';
                } elseif ($diff < 86400) {
                    $last_seen[$user['USER_ID']] = floor($diff / 3600) . ' hours ago';
                } else {
                    $last_seen[$user['USER_ID']] = date('M j', $last_activity);
                }
            }
            
            $response['success'] = true;
            $response['online_users'] = $online_users;
            $response['last_seen'] = $last_seen;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Update user activity action
    if ($_POST['action'] === 'update_user_activity') {
        try {
            // First, check if user already has a session
            $checkStmt = $pdo->prepare(
                "SELECT SESSION_ID FROM USER_SESSIONS WHERE USER_ID = ? ORDER BY LAST_ACTIVITY DESC LIMIT 1"
            );
            $checkStmt->execute([$_SESSION['user_id']]);
            $existingSession = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingSession) {
                // Update existing session
                $stmt = $pdo->prepare(
                    "UPDATE USER_SESSIONS SET LAST_ACTIVITY = NOW() WHERE SESSION_ID = ?"
                );
                $stmt->execute([$existingSession['SESSION_ID']]);
            } else {
                // Create new session
                $stmt = $pdo->prepare(
                    "INSERT INTO USER_SESSIONS (USER_ID, LAST_ACTIVITY) VALUES (?, NOW())"
                );
                $stmt->execute([$_SESSION['user_id']]);
                
                // Store the session ID in the user's session for future reference
                $_SESSION['session_id'] = $pdo->lastInsertId();
            }
            
            $response['success'] = true;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Send message action - FIXED
    if ($_POST['action'] === 'send_message') {
        // Validate input
        if (!isset($_POST['receiver_id'])) {
            $response['message'] = 'Receiver ID is required';
            echo json_encode($response);
            exit;
        }
        
        $sender_id = $_SESSION['user_id'];
        $receiver_id = $_POST['receiver_id'];
        $content = isset($_POST['content']) ? trim($_POST['content']) : null;
        $image_url = null;
        $reactions = null;
        
        // Handle image upload if present - FIXED
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $response['message'] = 'Invalid file type. Only JPEG, PNG, GIF, and WEBP are allowed.';
                echo json_encode($response);
                exit;
            }

            // Verify the bytes are really an image and get a safe extension
            // (don't trust the client MIME or filename).
            $safeExt = safe_image_ext($_FILES['image']['tmp_name']);
            if ($safeExt === null) {
                $response['message'] = 'Invalid image file.';
                echo json_encode($response);
                exit;
            }

            // Check file size (5MB max)
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $response['message'] = 'File size exceeds the limit of 5MB.';
                echo json_encode($response);
                exit;
            }
            
            // Upload to Cloudinary (primary). Falls back to local disk only if
            // Cloudinary is not configured, preserving graceful degradation.
            $cloud = CloudinaryService::upload($_FILES['image']['tmp_name'], 'feedora/messages');
            if ($cloud['success']) {
                $image_url = $cloud['url'];
            } elseif (!CloudinaryService::isConfigured()) {
                $upload_dir = 'uploads/messages/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $new_filename = uniqid('msg_') . '.' . $safeExt;
                $upload_path = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                } else {
                    $response['message'] = 'Failed to upload image.';
                    echo json_encode($response);
                    exit;
                }
            } else {
                $response['message'] = 'Failed to upload image.';
                echo json_encode($response);
                exit;
            }
        }
        
        // Handle initial reaction if provided
        if (isset($_POST['reaction']) && !empty($_POST['reaction'])) {
            $reaction = $_POST['reaction'];
            $reactions = json_encode([$reaction => 1]);
        }
        
        // Ensure at least content or image is provided - FIXED
        if (empty($content) && !$image_url) {
            $response['message'] = 'Message content or image is required';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Insert message into database
            $stmt = $pdo->prepare(
                "INSERT INTO MESSAGES (SENDER_ID, RECEIVER_ID, CONTENT, IMAGE_URL, REACTIONS, SENT_AT) 
                VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([$sender_id, $receiver_id, $content, $image_url, $reactions]);
            $message_id = $pdo->lastInsertId();
            
            $response['success'] = true;
            $response['message'] = 'Message sent successfully';
            $response['message_id'] = $message_id;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Add reaction action
    if ($_POST['action'] === 'add_reaction') {
        // Validate input
        if (!isset($_POST['message_id']) || !isset($_POST['reaction'])) {
            $response['message'] = 'Message ID and reaction are required';
            echo json_encode($response);
            exit;
        }
        
        $message_id = $_POST['message_id'];
        $reaction = $_POST['reaction'];
        $user_id = $_SESSION['user_id'];
        
        try {
            // Get current reactions
            $stmt = $pdo->prepare("SELECT REACTIONS FROM MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$message_id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                $response['message'] = 'Message not found';
                echo json_encode($response);
                exit;
            }
            
            // Parse existing reactions or create new array
            $reactions = $message['REACTIONS'] ? json_decode($message['REACTIONS'], true) : [];
            
            // Toggle reaction
            if (isset($reactions[$reaction])) {
                $reactions[$reaction]++;
            } else {
                $reactions[$reaction] = 1;
            }
            
            // Update reactions in database
            $stmt = $pdo->prepare("UPDATE MESSAGES SET REACTIONS = ? WHERE MESSAGE_ID = ?");
            $stmt->execute([json_encode($reactions), $message_id]);
            
            $response['success'] = true;
            $response['message'] = 'Reaction added successfully';
            $response['reactions'] = $reactions;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get users action
    if ($_POST['action'] === 'get_users') {
        try {
            $search = isset($_POST['search']) ? '%' . $_POST['search'] . '%' : null;
            
            if ($search) {
                $stmt = $pdo->prepare(
                    "SELECT USER_ID, NAME, PROFILE_IMAGE 
                    FROM USERS 
                    WHERE USER_ID != ? AND NAME LIKE ? 
                    ORDER BY NAME ASC"
                );
                $stmt->execute([$_SESSION['user_id'], $search]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT USER_ID, NAME, PROFILE_IMAGE 
                    FROM USERS 
                    WHERE USER_ID != ? 
                    ORDER BY NAME ASC"
                );
                $stmt->execute([$_SESSION['user_id']]);
            }
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['users'] = $users;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get user details action
    if ($_POST['action'] === 'get_user_details') {
        // Validate input
        if (!isset($_POST['user_id'])) {
            $response['message'] = 'User ID is required';
            echo json_encode($response);
            exit;
        }
        
        $user_id = $_POST['user_id'];
        
        try {
            // Get user details
            $stmt = $pdo->prepare(
                "SELECT USER_ID, NAME, PROFILE_IMAGE 
                FROM USERS 
                WHERE USER_ID = ?"
            );
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $response['message'] = 'User not found';
                echo json_encode($response);
                exit;
            }
            
            $response['success'] = true;
            $response['user'] = $user;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get messages action
    if ($_POST['action'] === 'get_messages') {
        // Validate input
        if (!isset($_POST['receiver_id'])) {
            $response['message'] = 'Receiver ID is required';
            echo json_encode($response);
            exit;
        }
        
        $sender_id = $_SESSION['user_id'];
        $receiver_id = $_POST['receiver_id'];
        $last_timestamp = isset($_POST['last_timestamp']) && !empty($_POST['last_timestamp']) ? $_POST['last_timestamp'] : 0;
        
        try {
            // Get messages between the two users
            if ($last_timestamp > 0) {
                // Only get messages newer than the last timestamp
                $stmt = $pdo->prepare(
                    "SELECT * FROM MESSAGES 
                    WHERE ((SENDER_ID = ? AND RECEIVER_ID = ?) OR (SENDER_ID = ? AND RECEIVER_ID = ?)) 
                    AND UNIX_TIMESTAMP(SENT_AT) > ? 
                    ORDER BY SENT_AT ASC"
                );
                $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id, $last_timestamp / 1000]);
            } else {
                // Get all messages
                $stmt = $pdo->prepare(
                    "SELECT * FROM MESSAGES 
                    WHERE (SENDER_ID = ? AND RECEIVER_ID = ?) OR (SENDER_ID = ? AND RECEIVER_ID = ?) 
                    ORDER BY SENT_AT ASC"
                );
                $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
            }
            
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['messages'] = $messages;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // If we get here, the action was not recognized
    $response['message'] = 'Invalid action';
    echo json_encode($response);
    exit;
}

// Get user's recent conversations
try {
    $current_user_id = $_SESSION['user_id'];
    
    // This query gets the most recent message between the current user and each other user
    $stmt = $pdo->prepare(
        "SELECT 
            u.USER_ID, u.NAME, u.PROFILE_IMAGE,
            m.CONTENT, m.SENT_AT,
            (SELECT COUNT(*) FROM MESSAGES WHERE 
                ((SENDER_ID = ? AND RECEIVER_ID = u.USER_ID) OR 
                (SENDER_ID = u.USER_ID AND RECEIVER_ID = ?)) AND 
                SENT_AT > NOW() - INTERVAL 1 DAY
            ) as MESSAGE_COUNT
        FROM USERS u
        LEFT JOIN MESSAGES m ON 
            ((m.SENDER_ID = ? AND m.RECEIVER_ID = u.USER_ID) OR 
            (m.SENDER_ID = u.USER_ID AND m.RECEIVER_ID = ?))
        WHERE u.USER_ID != ?
        GROUP BY u.USER_ID
        ORDER BY m.SENT_AT DESC"
    );
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $conversations = [];
    // Silently handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Cooking Dashboard">
    <meta name="theme-color" content="#ED5A2C">
    <title>Chat - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/chat.css'); ?>">
</head>
<body>
    <?php include('sidebar.php'); ?>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <!-- Mobile conversations toggle button -->
    <button class="mobile-conversations-toggle" id="mobile-conversations-toggle">
        <i class="fas fa-comments"></i>
    </button>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Mobile overlay for conversations list -->
        <div class="mobile-overlay" id="mobile-overlay"></div>
        
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Conversations List -->
            <div class="conversations-list" id="conversations-list">
                <div class="search-conversation">
                    <input type="text" placeholder="Search conversations..." id="search-conversations">
                    <i class="fas fa-search search-icon"></i>
                </div>
                
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-item" data-user-id="<?php echo $conversation['USER_ID']; ?>" data-user-name="<?php echo htmlspecialchars($conversation['NAME']); ?>" data-user-image="<?php echo $conversation['PROFILE_IMAGE']; ?>">
                            <div class="conversation-avatar">
                                <img src="<?php echo !empty($conversation['PROFILE_IMAGE']) ? $conversation['PROFILE_IMAGE'] : 'uploads/profiles/default.png'; ?>" alt="<?php echo htmlspecialchars($conversation['NAME']); ?>">
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?php echo htmlspecialchars($conversation['NAME']); ?></div>
                                    <div class="conversation-time">
                                        <?php 
                                            if (!empty($conversation['SENT_AT'])) {
                                                $sent_time = strtotime($conversation['SENT_AT']);
                                                $now = time();
                                                $diff = $now - $sent_time;
                                                
                                                if ($diff < 60) {
                                                    echo 'Just now';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . 'm';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . 'h';
                                                } else {
                                                    echo date('M j', $sent_time);
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="conversation-last-message">
                                    <?php 
                                        if (!empty($conversation['CONTENT'])) {
                                            echo htmlspecialchars(substr($conversation['CONTENT'], 0, 35)) . (strlen($conversation['CONTENT']) > 35 ? '...' : '');
                                        } elseif (!empty($conversation['IMAGE_URL'])) {
                                            echo '<i class="fas fa-image"></i> Photo';
                                        } else {
                                            echo 'Start a conversation';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="conversation-item">
                        <div class="conversation-info">
                            <div class="conversation-name">No conversations yet</div>
                            <div class="conversation-last-message">Start a new conversation</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Message Content -->
            <div class="message-content">
                <div class="message-header">
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="message-header-title">
                        Messages
                    </h1>
                    <div class="message-header-actions">
                        <button class="new-message-btn" id="new-message-btn">
                            <i class="fas fa-plus"></i>
                            <span>New Chat</span>
                        </button>
                    </div>
                </div>
                
                <div class="message-area" id="message-area">
                    <div class="no-message-selected" id="no-message-selected">
                        <div class="icon-container">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Your Messages</h3>
                        <p>Select a conversation or start a new one to begin chatting with fellow food enthusiasts</p>
                        <button class="new-message-btn" id="new-message-mobile-btn">
                            <i class="fas fa-plus"></i> Start New Chat
                        </button>
                    </div>
                    
                    <!-- Active conversation will be displayed here -->
                    <div class="active-conversation" id="active-conversation" style="display: none;">
                        <div class="conversation-header-active">
                            <div class="conversation-header-avatar">
                                <img src="uploads/profiles/default.png" alt="User">
                            </div>
                            <div class="conversation-header-info">
                                <div id="active-conversation-name">User Name</div>
                                <div class="conversation-status">
                                    <i class="fas fa-circle status-indicator"></i>
                                    <span class="status-text">Active now</span>
                                </div>
                            </div>
                            <div class="conversation-header-actions">
                                <button class="header-action-btn" title="More options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="messages-container" id="messages-container"></div>
                        
                        <div class="message-input-container">
                            <div class="message-attachments">
                                <label for="message-image" class="attachment-btn" title="Attach image">
                                    <i class="fas fa-image"></i>
                                </label>
                                <input type="file" id="message-image" accept="image/*" style="display: none;">
                                
                                <button class="emoji-btn" id="show-emoji-btn" title="Add emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                            </div>
                            
                            <div class="message-input-wrapper">
                                <textarea id="message-input" placeholder="Type your message..." rows="1"></textarea>
                                <div class="image-preview" id="image-preview" style="display: none;">
                                    <img id="preview-image" src="/placeholder.svg" alt="Preview">
                                    <button class="remove-image-btn" id="remove-image-btn" title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button class="send-message-btn" id="send-message-btn" title="Send message">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- New Message Modal -->
        <div class="modal" id="new-message-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>New Message</h3>
                    <button class="back-to-messages-btn" id="back-to-messages-btn" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="search-users">
                        <input type="text" id="search-users-input" placeholder="Search users...">
                    </div>
                    
                    <div class="users-list" id="users-list">
                        <div class="loading-users">Loading users...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Emoji Picker -->
        <div class="emoji-picker" id="emoji-picker">
            <div class="emoji-picker-header">
                <h4>Emojis</h4>
                <button class="close-emoji-btn" id="close-emoji-btn" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="emoji-categories">
                <button class="emoji-category active" data-category="food" title="Food">
                    🍕
                </button>
                <button class="emoji-category" data-category="smileys" title="Smileys">
                    😊
                </button>
                <button class="emoji-category" data-category="symbols" title="Symbols">
                    ❤️
                </button>
            </div>
            <div class="emoji-container">
                <div class="emoji-group" id="food-emojis">
                    <span class="emoji" data-emoji="🍕">🍕</span>
                    <span class="emoji" data-emoji="🍔">🍔</span>
                    <span class="emoji" data-emoji="🍗">🍗</span>
                    <span class="emoji" data-emoji="🍜">🍜</span>
                    <span class="emoji" data-emoji="🍰">🍰</span>
                    <span class="emoji" data-emoji="🍎">🍎</span>
                    <span class="emoji" data-emoji="🥑">🥑</span>
                    <span class="emoji" data-emoji="🍷">🍷</span>
                    <span class="emoji" data-emoji="🍞">🍞</span>
                    <span class="emoji" data-emoji="🥐">🥐</span>
                    <span class="emoji" data-emoji="🧀">🧀</span>
                    <span class="emoji" data-emoji="🍳">🍳</span>
                    <span class="emoji" data-emoji="🥗">🥗</span>
                    <span class="emoji" data-emoji="🍝">🍝</span>
                    <span class="emoji" data-emoji="🍦">🍦</span>
                    <span class="emoji" data-emoji="🍩">🍩</span>
                </div>
                <div class="emoji-group" id="smileys-emojis" style="display: none;">
                    <span class="emoji" data-emoji="😊">😊</span>
                    <span class="emoji" data-emoji="😂">😂</span>
                    <span class="emoji" data-emoji="😍">😍</span>
                    <span class="emoji" data-emoji="🤔">🤔</span>
                    <span class="emoji" data-emoji="😎">😎</span>
                    <span class="emoji" data-emoji="😋">😋</span>
                    <span class="emoji" data-emoji="🤗">🤗</span>
                    <span class="emoji" data-emoji="😉">😉</span>
                    <span class="emoji" data-emoji="😴">😴</span>
                    <span class="emoji" data-emoji="🤤">🤤</span>
                    <span class="emoji" data-emoji="😇">😇</span>
                    <span class="emoji" data-emoji="🥰">🥰</span>
                </div>
                <div class="emoji-group" id="symbols-emojis" style="display: none;">
                    <span class="emoji" data-emoji="❤️">❤️</span>
                    <span class="emoji" data-emoji="👍">👍</span>
                    <span class="emoji" data-emoji="👏">👏</span>
                    <span class="emoji" data-emoji="🎉">🎉</span>
                    <span class="emoji" data-emoji="🔥">🔥</span>
                    <span class="emoji" data-emoji="✨">✨</span>
                    <span class="emoji" data-emoji="⭐">⭐</span>
                    <span class="emoji" data-emoji="💯">💯</span>
                    <span class="emoji" data-emoji="💪">💪</span>
                    <span class="emoji" data-emoji="🙌">🙌</span>
                    <span class="emoji" data-emoji="👌">👌</span>
                    <span class="emoji" data-emoji="✌️">✌️</span>
                </div>
            </div>
        </div>
        
        <!-- Reactions Menu -->
        <div class="reactions-menu" id="reactions-menu">
            <div class="reaction-item" data-reaction="🍕">
                <span class="reaction-emoji">🍕</span>
            </div>
            <div class="reaction-item" data-reaction="🍔">
                <span class="reaction-emoji">🍔</span>
            </div>
            <div class="reaction-item" data-reaction="😋">
                <span class="reaction-emoji">😋</span>
            </div>
            <div class="reaction-item" data-reaction="👍">
                <span class="reaction-emoji">👍</span>
            </div>
            <div class="reaction-item" data-reaction="❤️">
                <span class="reaction-emoji">❤️</span>
            </div>
            <div class="reaction-item" data-reaction="😂">
                <span class="reaction-emoji">😂</span>
            </div>
            <div class="reaction-item" data-reaction="🔥">
                <span class="reaction-emoji">🔥</span>
            </div>
            <div class="reaction-item" data-reaction="🎉">
                <span class="reaction-emoji">🎉</span>
            </div>
        </div>
    </main>
    
    <!-- Mobile Conversations Toggle -->
    <div class="mobile-conversations-toggle" id="mobile-conversations-toggle">
        <i class="fas fa-comments"></i>
    </div>
    
    <script>
        window.ChatConfig = {
            currentUserId: <?php echo json_encode($_SESSION["user_id"]); ?>,
            currentUserProfileImage: <?php echo json_encode(!empty($_SESSION["profile_image"]) ? $_SESSION["profile_image"] : "uploads/profiles/default.png"); ?>
        };
    </script>
    <script src="<?php echo asset('assets/js/chat.js'); ?>" defer></script>
</body>
</html>
