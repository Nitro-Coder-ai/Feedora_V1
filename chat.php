<?php
// Include the database connection script
require_once 'config/config.php';
require_once 'includes/CloudinaryService.php';
require_once 'includes/MessageRepository.php';
require_once 'includes/CallRepository.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}

/**
 * Build the current user's conversation list with real unread counts, last
 * message, online flag and an Instagram-style category:
 *   - 'primary'  : you follow them, or you've already replied/messaged them
 *   - 'requests' : they messaged you, you don't follow them, you've not replied
 * Ordered by unread-first then most-recent (social-feed style). One query for
 * the partners + last message; small correlated subqueries for flags/unread.
 */
function fetchConversations(PDO $pdo, int $uid): array {
    $sql = "
        SELECT
            partner.USER_ID,
            u.NAME, u.PROFILE_IMAGE,
            lm.MESSAGE_ID AS last_message_id, lm.SENDER_ID AS last_sender_id,
            lm.CONTENT, lm.IMAGE_URL, lm.SENT_AT, UNIX_TIMESTAMP(lm.SENT_AT) AS sent_ts,
            (SELECT COUNT(*) FROM MESSAGES x
               WHERE x.RECEIVER_ID = ? AND x.SENDER_ID = partner.USER_ID AND x.IS_READ = 0) AS unread,
            EXISTS(SELECT 1 FROM FOLLOWERS f WHERE f.USER_ID = partner.USER_ID AND f.FOLLOWER_ID = ?) AS i_follow,
            EXISTS(SELECT 1 FROM MESSAGES s WHERE s.SENDER_ID = ? AND s.RECEIVER_ID = partner.USER_ID) AS i_sent,
            EXISTS(SELECT 1 FROM USER_SESSIONS us WHERE us.USER_ID = partner.USER_ID
                     AND us.LAST_ACTIVITY > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS online
        FROM (
            SELECT CASE WHEN SENDER_ID = ? THEN RECEIVER_ID ELSE SENDER_ID END AS USER_ID,
                   MAX(MESSAGE_ID) AS max_id
            FROM MESSAGES
            WHERE SENDER_ID = ? OR RECEIVER_ID = ?
            GROUP BY USER_ID
        ) partner
        JOIN MESSAGES lm ON lm.MESSAGE_ID = partner.max_id
        JOIN USERS u ON u.USER_ID = partner.USER_ID
        ORDER BY (unread > 0) DESC, lm.MESSAGE_ID DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $uid, $uid, $uid, $uid, $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $isPrimary = ((int) $r['i_follow'] === 1) || ((int) $r['i_sent'] === 1);
        $preview = $r['CONTENT'] !== null && $r['CONTENT'] !== ''
            ? mb_substr($r['CONTENT'], 0, 40)
            : (!empty($r['IMAGE_URL']) ? '📷 Photo' : '');
        $out[] = [
            'user_id'        => (int) $r['USER_ID'],
            'name'           => $r['NAME'],
            'profile_image'  => $r['PROFILE_IMAGE'],
            'last_message_id'=> (int) $r['last_message_id'],
            'preview'        => $preview,
            'sent_at'        => $r['SENT_AT'],
            'sent_ts'        => (int) $r['sent_ts'],
            'unread'         => (int) $r['unread'],
            'online'         => (int) $r['online'] === 1,
            'category'       => $isPrimary ? 'primary' : 'requests',
            'outgoing'       => (int) $r['last_sender_id'] === $uid,
        ];
    }
    return $out;
}

/**
 * Facebook-style presence for one user: green "Active now" when seen in the last
 * 2 minutes, otherwise a relative "Active Xm/h ago" / date last-seen label.
 * @return array{online:bool,last_seen:?string}
 */
function userPresence(PDO $pdo, int $userId): array {
    try {
        // Compute the elapsed seconds IN SQL (TIMESTAMPDIFF) so PHP's timezone
        // never disagrees with MySQL's — mixing time() with a MySQL timestamp
        // produced bogus "Active 4h ago" labels.
        $stmt = $pdo->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, MAX(LAST_ACTIVITY), NOW()) AS diff_sec,
                    DATE_FORMAT(MAX(LAST_ACTIVITY), '%b %e') AS d
             FROM USER_SESSIONS WHERE USER_ID = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $row = null;
    }
    if (!$row || $row['diff_sec'] === null) {
        return ['online' => false, 'last_seen' => null];
    }
    $diff = max(0, (int) $row['diff_sec']);
    if ($diff <= 120) {
        return ['online' => true, 'last_seen' => null];
    }
    if ($diff < 3600) {
        $label = floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        $label = floor($diff / 3600) . 'h ago';
    } elseif ($diff < 604800) {
        $label = floor($diff / 86400) . 'd ago';
    } else {
        $label = trim($row['d']);
    }
    return ['online' => false, 'last_seen' => $label];
}

/** Self-healing schema: create group/call tables if a deploy hasn't run the SQL. */
function ensureChatSchema(PDO $pdo): void {
    if (!empty($_SESSION['chat_schema_ok'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS CHAT_GROUPS (GROUP_ID INT AUTO_INCREMENT PRIMARY KEY, NAME VARCHAR(255) NOT NULL, AVATAR_URL VARCHAR(512) NULL, AVATAR_PUBLIC_ID VARCHAR(255) NULL, CREATED_BY INT NOT NULL, CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (CREATED_BY) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS CHAT_GROUP_MEMBERS (GROUP_ID INT NOT NULL, USER_ID INT NOT NULL, ROLE VARCHAR(50) DEFAULT 'member', JOINED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (GROUP_ID, USER_ID), KEY idx_cgm_user (USER_ID), FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE, FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS CHAT_GROUP_READS (GROUP_ID INT NOT NULL, USER_ID INT NOT NULL, LAST_READ_MESSAGE_ID INT NOT NULL DEFAULT 0, PRIMARY KEY (GROUP_ID, USER_ID), FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE, FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS CALL_SESSIONS (CALL_ID INT AUTO_INCREMENT PRIMARY KEY, CALLER_ID INT NOT NULL, RECEIVER_ID INT NULL, GROUP_ID INT NULL, CALL_TYPE VARCHAR(20) NOT NULL, STATUS VARCHAR(50) DEFAULT 'ringing', OFFER_SDP TEXT NULL, ANSWER_SDP TEXT NULL, STARTED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ENDED_AT TIMESTAMP NULL, KEY idx_call_receiver_status (RECEIVER_ID, STATUS), FOREIGN KEY (CALLER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE, FOREIGN KEY (RECEIVER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE, FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS CALL_ICE_CANDIDATES (CANDIDATE_ID INT AUTO_INCREMENT PRIMARY KEY, CALL_ID INT NOT NULL, SENDER_ID INT NOT NULL, CANDIDATE TEXT NOT NULL, CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_ice_call (CALL_ID, CANDIDATE_ID), FOREIGN KEY (CALL_ID) REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE, FOREIGN KEY (SENDER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Multi-party (mesh) calls: roster + directed per-peer offer/answer/ICE (see scripts/2026_multiparty_calls.sql).
        $pdo->exec("CREATE TABLE IF NOT EXISTS CALL_PARTICIPANTS (CALL_ID INT NOT NULL, USER_ID INT NOT NULL, ROLE VARCHAR(20) NOT NULL DEFAULT 'participant', STATUS VARCHAR(20) NOT NULL DEFAULT 'active', JOINED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, LEFT_AT TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (CALL_ID, USER_ID), KEY idx_callpart_active (CALL_ID, STATUS), KEY idx_callpart_user (USER_ID), FOREIGN KEY (CALL_ID) REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE, FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS CALL_PEER_SIGNALS (SIGNAL_ID INT AUTO_INCREMENT PRIMARY KEY, CALL_ID INT NOT NULL, FROM_USER INT NOT NULL, TO_USER INT NOT NULL, KIND VARCHAR(10) NOT NULL, PAYLOAD TEXT NOT NULL, CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_peersig_inbox (CALL_ID, TO_USER, SIGNAL_ID), FOREIGN KEY (CALL_ID) REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE, FOREIGN KEY (FROM_USER) REFERENCES USERS(USER_ID) ON DELETE CASCADE, FOREIGN KEY (TO_USER) REFERENCES USERS(USER_ID) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // MESSAGES.GROUP_ID (guarded — pre-8.0.29 has no ADD COLUMN IF NOT EXISTS)
        $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='MESSAGES' AND COLUMN_NAME='GROUP_ID'")->fetchColumn();
        if ((int)$has === 0) {
            $pdo->exec("ALTER TABLE MESSAGES ADD COLUMN GROUP_ID INT NULL AFTER RECEIVER_ID");
            $pdo->exec("ALTER TABLE MESSAGES MODIFY COLUMN RECEIVER_ID INT NULL");
            $pdo->exec("ALTER TABLE MESSAGES ADD CONSTRAINT fk_messages_group FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE");
        }
        $_SESSION['chat_schema_ok'] = true;
    } catch (PDOException $e) {
        error_log('[chat] ensureChatSchema: ' . $e->getMessage());
    }
}

/** Is $uid a member of $gid? */
function isGroupMember(PDO $pdo, int $gid, int $uid): bool {
    $s = $pdo->prepare("SELECT 1 FROM CHAT_GROUP_MEMBERS WHERE GROUP_ID = ? AND USER_ID = ? LIMIT 1");
    $s->execute([$gid, $uid]);
    return (bool) $s->fetch();
}

/** Groups the user belongs to, with last message + per-user unread, recent first. */
function fetchGroups(PDO $pdo, int $uid): array {
    $sql = "
        SELECT g.GROUP_ID, g.NAME, g.AVATAR_URL,
            lm.MESSAGE_ID AS last_message_id, lm.CONTENT, lm.IMAGE_URL,
            UNIX_TIMESTAMP(lm.SENT_AT) AS sent_ts, su.NAME AS last_sender,
            (SELECT COUNT(*) FROM MESSAGES m2
               WHERE m2.GROUP_ID = g.GROUP_ID AND m2.SENDER_ID <> ?
                 AND m2.MESSAGE_ID > COALESCE(
                    (SELECT LAST_READ_MESSAGE_ID FROM CHAT_GROUP_READS r
                       WHERE r.GROUP_ID = g.GROUP_ID AND r.USER_ID = ?), 0)) AS unread
        FROM CHAT_GROUPS g
        JOIN CHAT_GROUP_MEMBERS cm ON cm.GROUP_ID = g.GROUP_ID AND cm.USER_ID = ?
        LEFT JOIN MESSAGES lm ON lm.MESSAGE_ID =
            (SELECT MAX(m3.MESSAGE_ID) FROM MESSAGES m3 WHERE m3.GROUP_ID = g.GROUP_ID)
        LEFT JOIN USERS su ON su.USER_ID = lm.SENDER_ID
        ORDER BY (lm.MESSAGE_ID IS NULL), lm.MESSAGE_ID DESC, g.GROUP_ID DESC";
    $s = $pdo->prepare($sql);
    $s->execute([$uid, $uid, $uid]);
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $preview = $r['CONTENT'] !== null && $r['CONTENT'] !== ''
            ? (($r['last_sender'] ? $r['last_sender'] . ': ' : '') . mb_substr($r['CONTENT'], 0, 34))
            : (!empty($r['IMAGE_URL']) ? '📷 Photo' : 'No messages yet');
        $out[] = [
            'group_id'        => (int) $r['GROUP_ID'],
            'name'            => $r['NAME'],
            'avatar_url'      => $r['AVATAR_URL'],
            'last_message_id' => (int) $r['last_message_id'],
            'preview'         => $preview,
            'sent_ts'         => (int) $r['sent_ts'],
            'unread'          => (int) $r['unread'],
        ];
    }
    return $out;
}

/** Members of a group with role + presence. */
function fetchGroupMembers(PDO $pdo, int $gid): array {
    $s = $pdo->prepare("
        SELECT u.USER_ID, u.NAME, u.PROFILE_IMAGE, cm.ROLE,
            EXISTS(SELECT 1 FROM USER_SESSIONS us WHERE us.USER_ID = u.USER_ID
                     AND us.LAST_ACTIVITY > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS online
        FROM CHAT_GROUP_MEMBERS cm
        JOIN USERS u ON u.USER_ID = cm.USER_ID
        WHERE cm.GROUP_ID = ?
        ORDER BY (cm.ROLE = 'admin') DESC, u.NAME ASC");
    $s->execute([$gid]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Make sure group/call tables exist (self-healing on first load post-deploy).
ensureChatSchema($pdo);

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
    
    // Send message action — supports DMs (receiver_id) and groups (group_id).
    if ($_POST['action'] === 'send_message') {
        $sender_id = (int) $_SESSION['user_id'];
        $group_id = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
        $receiver_id = ($group_id === null && isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '')
            ? (int) $_POST['receiver_id'] : null;

        if ($group_id === null && $receiver_id === null) {
            $response['message'] = 'A receiver or group is required';
            echo json_encode($response); exit;
        }
        if ($group_id !== null && !isGroupMember($pdo, $group_id, $sender_id)) {
            $response['message'] = 'You are not a member of this group';
            echo json_encode($response); exit;
        }

        $content = isset($_POST['content']) ? trim($_POST['content']) : null;
        $image_url = null;
        $reactions = null;
        
        // Handle image OR video upload: Cloudinary -> compress -> deliver URL.
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['image'];
            $clientType = (string) $f['type'];
            $isImage = strncmp($clientType, 'image/', 6) === 0;
            $isVideo = strncmp($clientType, 'video/', 6) === 0;
            $isAudio = strncmp($clientType, 'audio/', 6) === 0;

            if ($isImage) {
                // Verify the bytes really are an image (don't trust client MIME).
                $safeExt = safe_image_ext($f['tmp_name']);
                if ($safeExt === null) {
                    $response['message'] = 'Invalid image file.';
                    echo json_encode($response); exit;
                }
                if ($f['size'] > 10 * 1024 * 1024) {
                    $response['message'] = 'Image too large (max 10MB).';
                    echo json_encode($response); exit;
                }
            } elseif ($isVideo) {
                if ($f['size'] > 50 * 1024 * 1024) {
                    $response['message'] = 'Video too large (max 50MB).';
                    echo json_encode($response); exit;
                }
            } elseif ($isAudio) {
                // Voice notes (WhatsApp-style). Cloudinary stores audio as a
                // 'video' resource; small size cap keeps them snappy.
                if ($f['size'] > 15 * 1024 * 1024) {
                    $response['message'] = 'Voice note too large (max 15MB).';
                    echo json_encode($response); exit;
                }
            } else {
                $response['message'] = 'Only images, videos and voice notes can be sent.';
                echo json_encode($response); exit;
            }

            // Upload (auto image/video) then compress on delivery:
            //   f_auto,q_auto,c_limit,w_1080 — ~70% smaller, no crop/zoom, video-safe.
            // Voice notes go to a distinct folder so the client can detect audio
            // (Cloudinary serves both audio & video webm under /video/upload/).
            $cloud = CloudinaryService::upload($f['tmp_name'], $isAudio ? 'feedora/voice' : 'feedora/messages');
            if ($cloud['success']) {
                // Audio: deliver the raw secure URL (width/format transforms don't
                // apply to a track-less audio asset). Images/videos get compression.
                $image_url = $isAudio ? $cloud['url'] : CloudinaryService::transform($cloud['url'], 1080);
            } elseif (!CloudinaryService::isConfigured() && $isImage) {
                // Local fallback for images only when Cloudinary isn't configured.
                $upload_dir = 'uploads/messages/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $new_filename = uniqid('msg_') . '.' . $safeExt;
                $upload_path = $upload_dir . $new_filename;
                if (move_uploaded_file($f['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                } else {
                    $response['message'] = 'Failed to upload media.';
                    echo json_encode($response); exit;
                }
            } else {
                $response['message'] = 'Failed to upload media.';
                echo json_encode($response); exit;
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
            // Insert message into database (DM or group)
            $stmt = $pdo->prepare(
                "INSERT INTO MESSAGES (SENDER_ID, RECEIVER_ID, GROUP_ID, CONTENT, IMAGE_URL, REACTIONS, SENT_AT)
                VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->execute([$sender_id, $receiver_id, $group_id, $content, $image_url, $reactions]);
            $message_id = $pdo->lastInsertId();

            // P3: sending ends the typing signal for this conversation scope.
            $msgRepo = new MessageRepository($pdo);
            $sendConvKey = $group_id !== null
                ? MessageRepository::groupConvKey($group_id)
                : MessageRepository::dmConvKey($sender_id, $receiver_id);
            $msgRepo->clearTyping($sender_id, $sendConvKey);

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
    
    // Get messages action — full history for a DM (receiver_id) or group (group_id),
    // id-ordered with sender name/avatar for group rendering.
    if ($_POST['action'] === 'get_messages') {
        $sender_id = (int) $_SESSION['user_id'];
        $group_id = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
        $receiver_id = ($group_id === null && isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '')
            ? (int) $_POST['receiver_id'] : null;

        if ($group_id === null && $receiver_id === null) {
            $response['message'] = 'A receiver or group is required';
            echo json_encode($response); exit;
        }

        try {
            if ($group_id !== null) {
                if (!isGroupMember($pdo, $group_id, $sender_id)) {
                    $response['message'] = 'You are not a member of this group';
                    echo json_encode($response); exit;
                }
                $stmt = $pdo->prepare(
                    "SELECT m.*, u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                     FROM MESSAGES m JOIN USERS u ON u.USER_ID = m.SENDER_ID
                     WHERE m.GROUP_ID = ? ORDER BY m.MESSAGE_ID ASC"
                );
                $stmt->execute([$group_id]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $maxId = $messages ? (int) end($messages)['MESSAGE_ID'] : 0;
                if ($maxId > 0) {
                    $pdo->prepare(
                        "INSERT INTO CHAT_GROUP_READS (GROUP_ID, USER_ID, LAST_READ_MESSAGE_ID) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE LAST_READ_MESSAGE_ID = GREATEST(LAST_READ_MESSAGE_ID, VALUES(LAST_READ_MESSAGE_ID))"
                    )->execute([$group_id, $sender_id, $maxId]);
                }
            } else {
                $stmt = $pdo->prepare(
                    "SELECT m.*, u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                     FROM MESSAGES m JOIN USERS u ON u.USER_ID = m.SENDER_ID
                     WHERE (m.SENDER_ID = ? AND m.RECEIVER_ID = ?) OR (m.SENDER_ID = ? AND m.RECEIVER_ID = ?)
                     ORDER BY m.MESSAGE_ID ASC"
                );
                $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $pdo->prepare("UPDATE MESSAGES SET IS_READ = 1 WHERE RECEIVER_ID = ? AND SENDER_ID = ? AND IS_READ = 0")
                    ->execute([$sender_id, $receiver_id]);
            }

            $response['success'] = true;
            $response['messages'] = $messages;
            $response['last_message_id'] = $messages ? (int) end($messages)['MESSAGE_ID'] : 0;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Unified poll: ONE request returns conversation list (+unread/categories),
    // new messages for the open chat, and refreshes the caller's presence.
    // Replaces the old 3-timer flood — the client calls this on an adaptive,
    // visibility-aware schedule.
    if ($_POST['action'] === 'poll') {
        $me = (int) $_SESSION['user_id'];
        try {
            // Touch presence (so "online" works without a separate timer).
            $upd = $pdo->prepare("UPDATE USER_SESSIONS SET LAST_ACTIVITY = NOW() WHERE USER_ID = ?");
            $upd->execute([$me]);
            if ($upd->rowCount() === 0) {
                $pdo->prepare("INSERT INTO USER_SESSIONS (USER_ID, LAST_ACTIVITY) VALUES (?, NOW())")->execute([$me]);
            }

            $response['conversations'] = fetchConversations($pdo, $me);
            $response['groups'] = fetchGroups($pdo, $me);
            $response['messages'] = [];
            $response['last_message_id'] = (int) ($_POST['last_message_id'] ?? 0);
            // P3 defaults: others typing in the open conv + my last "Seen" id.
            $response['typing'] = [];
            $response['seen'] = 0;

            $afterId = (int) ($_POST['last_message_id'] ?? 0);
            $activeId = isset($_POST['active_user_id']) ? (int) $_POST['active_user_id'] : 0;
            $activeGroup = isset($_POST['active_group_id']) ? (int) $_POST['active_group_id'] : 0;
            $pollRepo = new MessageRepository($pdo);

            if ($activeGroup > 0 && isGroupMember($pdo, $activeGroup, $me)) {
                // New group messages since the cursor (with sender info).
                $stmt = $pdo->prepare(
                    "SELECT m.*, u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                     FROM MESSAGES m JOIN USERS u ON u.USER_ID = m.SENDER_ID
                     WHERE m.GROUP_ID = ? AND m.MESSAGE_ID > ? ORDER BY m.MESSAGE_ID ASC"
                );
                $stmt->execute([$activeGroup, $afterId]);
                $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['messages'] = $msgs;
                if ($msgs) {
                    $response['last_message_id'] = (int) end($msgs)['MESSAGE_ID'];
                    $pdo->prepare(
                        "INSERT INTO CHAT_GROUP_READS (GROUP_ID, USER_ID, LAST_READ_MESSAGE_ID) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE LAST_READ_MESSAGE_ID = GREATEST(LAST_READ_MESSAGE_ID, VALUES(LAST_READ_MESSAGE_ID))"
                    )->execute([$activeGroup, $me, (int) end($msgs)['MESSAGE_ID']]);
                }
                // P3: who else is typing in this group right now (excludes me).
                $response['typing'] = $pollRepo->getTyping(MessageRepository::groupConvKey($activeGroup), $me);
            } elseif ($activeId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT m.*, u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                     FROM MESSAGES m JOIN USERS u ON u.USER_ID = m.SENDER_ID
                     WHERE ((m.SENDER_ID = ? AND m.RECEIVER_ID = ?) OR (m.SENDER_ID = ? AND m.RECEIVER_ID = ?))
                       AND m.MESSAGE_ID > ? ORDER BY m.MESSAGE_ID ASC"
                );
                $stmt->execute([$me, $activeId, $activeId, $me, $afterId]);
                $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['messages'] = $msgs;
                if ($msgs) {
                    $response['last_message_id'] = (int) end($msgs)['MESSAGE_ID'];
                }
                $pdo->prepare("UPDATE MESSAGES SET IS_READ = 1 WHERE RECEIVER_ID = ? AND SENDER_ID = ? AND IS_READ = 0")
                    ->execute([$me, $activeId]);
                $response['active_status'] = userPresence($pdo, $activeId);
                // P3: peer typing + my "Seen" cursor (latest of my msgs the peer read).
                $dmKey = MessageRepository::dmConvKey($me, $activeId);
                $response['typing'] = $pollRepo->getTyping($dmKey, $me);
                $response['seen'] = $pollRepo->getReadState($me, $activeId)['last_seen_message_id'];
            }

            // Incoming call ringing for me (direct), with caller info.
            $callStmt = $pdo->prepare(
                "SELECT cs.CALL_ID, cs.CALLER_ID, cs.CALL_TYPE, cs.OFFER_SDP, u.NAME AS CALLER_NAME, u.PROFILE_IMAGE AS CALLER_IMAGE
                 FROM CALL_SESSIONS cs JOIN USERS u ON u.USER_ID = cs.CALLER_ID
                 WHERE cs.RECEIVER_ID = ? AND cs.STATUS = 'ringing'
                   AND cs.STARTED_AT > DATE_SUB(NOW(), INTERVAL 60 SECOND)
                 ORDER BY cs.CALL_ID DESC LIMIT 1"
            );
            $callStmt->execute([$me]);
            $incoming = $callStmt->fetch(PDO::FETCH_ASSOC);
            $response['incoming_call'] = $incoming ?: null;

            // Active/ringing call in the currently open group (for the inline Join).
            $response['group_call'] = null;
            if ($activeGroup > 0 && isGroupMember($pdo, $activeGroup, $me)) {
                // Advertise an ongoing group call by its LIVE roster, not a time box:
                // show the "Join" banner to members who aren't already in it as long
                // as ≥1 participant is active. endRoom() (last leaver) drops it.
                $gc = $pdo->prepare(
                    "SELECT cs.CALL_ID, cs.CALLER_ID, cs.CALL_TYPE, cs.STATUS, cs.OFFER_SDP, u.NAME AS CALLER_NAME, u.PROFILE_IMAGE AS CALLER_IMAGE
                     FROM CALL_SESSIONS cs JOIN USERS u ON u.USER_ID = cs.CALLER_ID
                     WHERE cs.GROUP_ID = ? AND cs.STATUS IN ('ringing','active')
                       AND EXISTS (SELECT 1 FROM CALL_PARTICIPANTS p
                                    WHERE p.CALL_ID = cs.CALL_ID AND p.STATUS = 'active')
                       AND NOT EXISTS (SELECT 1 FROM CALL_PARTICIPANTS pm
                                    WHERE pm.CALL_ID = cs.CALL_ID AND pm.USER_ID = ? AND pm.STATUS = 'active')
                     ORDER BY cs.CALL_ID DESC LIMIT 1"
                );
                $gc->execute([$activeGroup, $me]);
                $response['group_call'] = $gc->fetch(PDO::FETCH_ASSOC) ?: null;
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

    // Mark a conversation's incoming messages as read.
    if ($_POST['action'] === 'mark_read') {
        $me = (int) $_SESSION['user_id'];
        $other = (int) ($_POST['user_id'] ?? 0);
        if ($other > 0) {
            try {
                $pdo->prepare("UPDATE MESSAGES SET IS_READ = 1 WHERE RECEIVER_ID = ? AND SENDER_ID = ? AND IS_READ = 0")
                    ->execute([$me, $other]);
                $response['success'] = true;
            } catch (PDOException $e) {
                $response['message'] = 'Database error: ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit;
    }

    // ── Group chats ──────────────────────────────────────────────────────────
    if ($_POST['action'] === 'create_group') {
        $me = (int) $_SESSION['user_id'];
        $name = trim((string) ($_POST['name'] ?? ''));
        $members = array_values(array_unique(array_map('intval', (array) ($_POST['members'] ?? []))));
        if ($name === '') { $response['message'] = 'Group name is required'; echo json_encode($response); exit; }

        $avatarUrl = null; $avatarPublicId = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK
            && strncmp((string) $_FILES['avatar']['type'], 'image/', 6) === 0) {
            $c = CloudinaryService::upload($_FILES['avatar']['tmp_name'], 'feedora/groups');
            if ($c['success']) { $avatarUrl = CloudinaryService::transform($c['url'], 256); $avatarPublicId = $c['public_id'] ?? null; }
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO CHAT_GROUPS (NAME, AVATAR_URL, AVATAR_PUBLIC_ID, CREATED_BY) VALUES (?, ?, ?, ?)")
                ->execute([$name, $avatarUrl, $avatarPublicId, $me]);
            $gid = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO CHAT_GROUP_MEMBERS (GROUP_ID, USER_ID, ROLE) VALUES (?, ?, 'admin')")->execute([$gid, $me]);
            $add = $pdo->prepare("INSERT IGNORE INTO CHAT_GROUP_MEMBERS (GROUP_ID, USER_ID, ROLE) VALUES (?, ?, 'member')");
            foreach ($members as $uid) { if ($uid !== $me) { $add->execute([$gid, $uid]); } }
            $pdo->commit();
            $response['success'] = true;
            $response['group_id'] = $gid;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'get_group_members') {
        $me = (int) $_SESSION['user_id'];
        $gid = (int) ($_POST['group_id'] ?? 0);
        if ($gid <= 0 || !isGroupMember($pdo, $gid, $me)) { $response['message'] = 'Not allowed'; echo json_encode($response); exit; }
        $response['success'] = true;
        $response['members'] = fetchGroupMembers($pdo, $gid);
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'invite_members') {
        $me = (int) $_SESSION['user_id'];
        $gid = (int) ($_POST['group_id'] ?? 0);
        $ids = array_values(array_unique(array_map('intval', (array) ($_POST['user_ids'] ?? []))));
        // Only an admin of the group may invite.
        $role = null;
        if ($gid > 0) {
            $r = $pdo->prepare("SELECT ROLE FROM CHAT_GROUP_MEMBERS WHERE GROUP_ID = ? AND USER_ID = ?");
            $r->execute([$gid, $me]); $role = $r->fetchColumn();
        }
        if ($role !== 'admin') { $response['message'] = 'Only group admins can invite'; echo json_encode($response); exit; }
        try {
            $add = $pdo->prepare("INSERT IGNORE INTO CHAT_GROUP_MEMBERS (GROUP_ID, USER_ID, ROLE) VALUES (?, ?, 'member')");
            foreach ($ids as $uid) { $add->execute([$gid, $uid]); }
            $response['success'] = true;
            $response['members'] = fetchGroupMembers($pdo, $gid);
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'mark_group_read') {
        $me = (int) $_SESSION['user_id'];
        $gid = (int) ($_POST['group_id'] ?? 0);
        $mid = (int) ($_POST['last_message_id'] ?? 0);
        if ($gid > 0 && isGroupMember($pdo, $gid, $me)) {
            try {
                $pdo->prepare(
                    "INSERT INTO CHAT_GROUP_READS (GROUP_ID, USER_ID, LAST_READ_MESSAGE_ID) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE LAST_READ_MESSAGE_ID = GREATEST(LAST_READ_MESSAGE_ID, VALUES(LAST_READ_MESSAGE_ID))"
                )->execute([$gid, $me, $mid]);
                $response['success'] = true;
            } catch (PDOException $e) { $response['message'] = $e->getMessage(); }
        }
        echo json_encode($response); exit;
    }

    // ── WebRTC call signaling (DB-backed) ────────────────────────────────────
    if ($_POST['action'] === 'initiate_call') {
        $me = (int) $_SESSION['user_id'];
        $receiver = isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '' ? (int) $_POST['receiver_id'] : null;
        $gid = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
        $type = ($_POST['call_type'] ?? 'voice') === 'video' ? 'video' : 'voice';
        $offer = (string) ($_POST['offer_sdp'] ?? '');
        if (($receiver === null && $gid === null) || $offer === '') {
            $response['message'] = 'Invalid call request'; echo json_encode($response); exit;
        }
        if ($gid !== null && !isGroupMember($pdo, $gid, $me)) { $response['message'] = 'Not a group member'; echo json_encode($response); exit; }
        try {
            // Auto-expire any prior ringing calls I started.
            $pdo->prepare("UPDATE CALL_SESSIONS SET STATUS='ended', ENDED_AT=NOW() WHERE CALLER_ID=? AND STATUS IN ('ringing','active')")->execute([$me]);
            $pdo->prepare("INSERT INTO CALL_SESSIONS (CALLER_ID, RECEIVER_ID, GROUP_ID, CALL_TYPE, STATUS, OFFER_SDP) VALUES (?, ?, ?, ?, 'ringing', ?)")
                ->execute([$me, $receiver, $gid, $type, $offer]);
            $response['success'] = true;
            $response['call_id'] = (int) $pdo->lastInsertId();
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'answer_call') {
        $me = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        $answer = (string) ($_POST['answer_sdp'] ?? '');
        try {
            $c = $pdo->prepare("SELECT * FROM CALL_SESSIONS WHERE CALL_ID = ?");
            $c->execute([$callId]); $call = $c->fetch(PDO::FETCH_ASSOC);
            $allowed = $call && ((int) $call['RECEIVER_ID'] === $me || ($call['GROUP_ID'] && isGroupMember($pdo, (int) $call['GROUP_ID'], $me)));
            if (!$allowed) { $response['message'] = 'Not allowed'; echo json_encode($response); exit; }
            $pdo->prepare("UPDATE CALL_SESSIONS SET ANSWER_SDP = ?, STATUS = 'active' WHERE CALL_ID = ?")->execute([$answer, $callId]);
            $response['success'] = true;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'send_ice_candidate') {
        $me = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        $cand = (string) ($_POST['candidate'] ?? '');
        if ($callId > 0 && $cand !== '') {
            try {
                $pdo->prepare("INSERT INTO CALL_ICE_CANDIDATES (CALL_ID, SENDER_ID, CANDIDATE) VALUES (?, ?, ?)")
                    ->execute([$callId, $me, $cand]);
                $response['success'] = true;
            } catch (PDOException $e) { $response['message'] = $e->getMessage(); }
        }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'get_call_signaling') {
        $me = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        $lastCand = (int) ($_POST['last_candidate_id'] ?? 0);
        try {
            $c = $pdo->prepare("SELECT STATUS, ANSWER_SDP FROM CALL_SESSIONS WHERE CALL_ID = ?");
            $c->execute([$callId]); $call = $c->fetch(PDO::FETCH_ASSOC);
            if (!$call) { $response['message'] = 'Call not found'; echo json_encode($response); exit; }
            $response['status'] = $call['STATUS'];
            $response['answer_sdp'] = $call['ANSWER_SDP'];
            // ICE from the OTHER party only.
            $ice = $pdo->prepare("SELECT CANDIDATE_ID, CANDIDATE FROM CALL_ICE_CANDIDATES WHERE CALL_ID = ? AND SENDER_ID <> ? AND CANDIDATE_ID > ? ORDER BY CANDIDATE_ID ASC");
            $ice->execute([$callId, $me, $lastCand]);
            $rows = $ice->fetchAll(PDO::FETCH_ASSOC);
            $response['candidates'] = $rows;
            $response['last_candidate_id'] = $rows ? (int) end($rows)['CANDIDATE_ID'] : $lastCand;
            $response['success'] = true;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    if ($_POST['action'] === 'end_call' || $_POST['action'] === 'reject_call') {
        $me = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        $newStatus = $_POST['action'] === 'reject_call' ? 'rejected' : 'ended';
        try {
            $pdo->prepare("UPDATE CALL_SESSIONS SET STATUS = ?, ENDED_AT = NOW()
                           WHERE CALL_ID = ? AND (CALLER_ID = ? OR RECEIVER_ID = ?)")
                ->execute([$newStatus, $callId, $me, $me]);
            $response['success'] = true;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // ── Multi-party (mesh) calls — Discord-style ─────────────────────────────
    // A "room" is one CALL_SESSIONS row (group call = GROUP_ID set). The roster
    // lives in CALL_PARTICIPANTS and signalling is DIRECTED per ordered peer-pair
    // in CALL_PEER_SIGNALS (see includes/CallRepository.php + 01_schema.md).
    // These NEW actions coexist with the legacy 1:1 actions above (back-compat).

    // join_call: resolve/create the room then join it. Body has ONE of:
    //   group_id    → join (or start) the live room for a group I belong to
    //   call_id     → join an existing room (membership re-checked from the room)
    //   receiver_id → start/continue a 1:1 room with that user
    if ($_POST['action'] === 'join_call') {
        $me   = (int) $_SESSION['user_id'];
        $gid  = isset($_POST['group_id'])    && $_POST['group_id']    !== '' ? (int) $_POST['group_id']    : 0;
        $cid  = isset($_POST['call_id'])     && $_POST['call_id']     !== '' ? (int) $_POST['call_id']     : 0;
        $rcv  = isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '' ? (int) $_POST['receiver_id'] : 0;
        $type = ($_POST['call_type'] ?? 'voice') === 'video' ? 'video' : 'voice';
        $calls = new CallRepository($pdo);
        try {
            $callId = 0; $room = null;
            if ($cid > 0) {
                // Join an existing room — authorize from the room itself.
                $room = $calls->getRoom($cid);
                if (!$room) { $response['message'] = 'Call not found'; echo json_encode($response); exit; }
                if ($room['STATUS'] === 'ended' || $room['STATUS'] === 'rejected') {
                    $response['message'] = 'Call has ended'; echo json_encode($response); exit;
                }
                $allowed = ($room['GROUP_ID'] && isGroupMember($pdo, (int) $room['GROUP_ID'], $me))
                    || (int) $room['CALLER_ID'] === $me || (int) $room['RECEIVER_ID'] === $me;
                if (!$allowed) { $response['message'] = 'Not allowed'; echo json_encode($response); exit; }
                $callId = $cid;
                $calls->joinCall($callId, $me);
            } elseif ($gid > 0) {
                if (!isGroupMember($pdo, $gid, $me)) { $response['message'] = 'Not a group member'; echo json_encode($response); exit; }
                $existing = $calls->getActiveRoomForGroup($gid);
                if ($existing) {
                    $callId = (int) $existing['CALL_ID'];
                    $type   = $existing['CALL_TYPE'];
                    $calls->joinCall($callId, $me);
                } else {
                    $callId = $calls->createRoom($me, null, $gid, $type); // creator joined as host
                }
            } elseif ($rcv > 0) {
                $callId = $calls->createRoom($me, $rcv, null, $type); // creator joined as host
            } else {
                $response['message'] = 'group_id, call_id or receiver_id is required';
                echo json_encode($response); exit;
            }
            if ($callId <= 0) { $response['message'] = 'Could not join call'; echo json_encode($response); exit; }
            $room = $room ?: $calls->getRoom($callId);
            $response['success']      = true;
            $response['call_id']      = $callId;
            $response['call_type']    = $room ? $room['CALL_TYPE'] : $type;
            $response['me']           = $me;
            $response['participants'] = $calls->listParticipants($callId);
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // leave_call: drop out of a room; if it is now empty, end it.
    if ($_POST['action'] === 'leave_call') {
        $me     = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        if ($callId <= 0) { $response['message'] = 'call_id is required'; echo json_encode($response); exit; }
        $calls = new CallRepository($pdo);
        try {
            $calls->leaveCall($callId, $me);
            if ($calls->countActiveParticipants($callId) === 0) {
                $calls->endRoom($callId);
            }
            $response['success'] = true;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // get_call_room: the mesh poll while in a call — roster + directed signals
    // addressed to me since my cursor. Caller must be an active participant.
    if ($_POST['action'] === 'get_call_room') {
        $me     = (int) $_SESSION['user_id'];
        $callId = (int) ($_POST['call_id'] ?? 0);
        $since  = (int) ($_POST['last_signal_id'] ?? 0);
        if ($callId <= 0) { $response['message'] = 'call_id is required'; echo json_encode($response); exit; }
        $calls = new CallRepository($pdo);
        try {
            if (!$calls->isActiveParticipant($callId, $me)) {
                $response['message'] = 'Not an active participant'; echo json_encode($response); exit;
            }
            $room    = $calls->getRoom($callId);
            $signals = $calls->getSignalsFor($callId, $me, $since);
            $maxId   = $since;
            foreach ($signals as $s) { if ((int) $s['SIGNAL_ID'] > $maxId) { $maxId = (int) $s['SIGNAL_ID']; } }
            $response['success']        = true;
            $response['participants']   = $calls->listParticipants($callId);
            $response['signals']        = $signals;
            $response['last_signal_id'] = $maxId;
            $response['status']         = $room ? $room['STATUS'] : 'ended';
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // send_peer_signal: enqueue one directed offer/answer/ice from me to a peer.
    // Both me and the target must be active participants of the call.
    if ($_POST['action'] === 'send_peer_signal') {
        $me      = (int) $_SESSION['user_id'];
        $callId  = (int) ($_POST['call_id'] ?? 0);
        $toUser  = (int) ($_POST['to_user'] ?? 0);
        $kind    = (string) ($_POST['kind'] ?? '');
        $payload = (string) ($_POST['payload'] ?? '');
        if ($callId <= 0 || $toUser <= 0 || $payload === '') {
            $response['message'] = 'call_id, to_user and payload are required'; echo json_encode($response); exit;
        }
        $calls = new CallRepository($pdo);
        try {
            if (!$calls->isActiveParticipant($callId, $me)) {
                $response['message'] = 'Not an active participant'; echo json_encode($response); exit;
            }
            if (!$calls->isActiveParticipant($callId, $toUser)) {
                $response['message'] = 'Target is not an active participant'; echo json_encode($response); exit;
            }
            $sigId = $calls->putSignal($callId, $me, $toUser, $kind, $payload);
            if ($sigId <= 0) { $response['message'] = 'Invalid signal (bad kind?)'; echo json_encode($response); exit; }
            $response['success']   = true;
            $response['signal_id'] = $sigId;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // Presence beacon alias. The dead client action `update_status` used to fall
    // through to "Invalid action" (wasted traffic — see 00_plan.md ROOT-CAUSE).
    // The frontend now calls `update_user_activity`; this alias keeps any stale
    // client returning a clean {success:true} instead of an error.
    if ($_POST['action'] === 'update_status') {
        $me = (int) $_SESSION['user_id'];
        try {
            $upd = $pdo->prepare("UPDATE USER_SESSIONS SET LAST_ACTIVITY = NOW() WHERE USER_ID = ?");
            $upd->execute([$me]);
            if ($upd->rowCount() === 0) {
                $pdo->prepare("INSERT INTO USER_SESSIONS (USER_ID, LAST_ACTIVITY) VALUES (?, NOW())")->execute([$me]);
            }
            $response['success'] = true;
        } catch (PDOException $e) { $response['message'] = 'Database error: ' . $e->getMessage(); }
        echo json_encode($response); exit;
    }

    // ── P1: Pinned messages (pin / unpin / list) ─────────────────────────────
    // All three share the same scope resolution: a group (group_id, membership
    // verified) or a DM (receiver_id → canonical dm: key with me). CONV_KEY is
    // always derived server-side via MessageRepository — never trusted from POST.
    if ($_POST['action'] === 'pin_message'
        || $_POST['action'] === 'unpin_message'
        || $_POST['action'] === 'list_pins') {
        $me = (int) $_SESSION['user_id'];
        $gid  = isset($_POST['group_id'])    && $_POST['group_id']    !== '' ? (int) $_POST['group_id']    : null;
        $peer = ($gid === null && isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '')
            ? (int) $_POST['receiver_id'] : null;

        if ($gid === null && $peer === null) {
            $response['message'] = 'A receiver or group is required';
            echo json_encode($response); exit;
        }
        if ($gid !== null) {
            if (!isGroupMember($pdo, $gid, $me)) {
                $response['message'] = 'You are not a member of this group';
                echo json_encode($response); exit;
            }
            $convKey   = MessageRepository::groupConvKey($gid);
            $scopeType = 'group';
        } else {
            $convKey   = MessageRepository::dmConvKey($me, $peer);
            $scopeType = 'dm';
            $gid       = null;
        }

        $repo = new MessageRepository($pdo);

        if ($_POST['action'] === 'list_pins') {
            $response['success'] = true;
            $response['pins'] = $repo->listPins($convKey);
            echo json_encode($response); exit;
        }

        $messageId = (int) ($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            $response['message'] = 'A valid message id is required';
            echo json_encode($response); exit;
        }

        if ($_POST['action'] === 'pin_message') {
            $ok = $repo->pinMessage($messageId, $me, $convKey, $scopeType, $gid);
            $response['success'] = $ok;
            $response['message'] = $ok ? 'Message pinned' : 'Could not pin message';
            $response['message_id'] = $messageId;
            $response['pinned'] = $ok;
        } else { // unpin_message
            $ok = $repo->unpinMessage($messageId, $convKey);
            $response['success'] = $ok;
            $response['message'] = $ok ? 'Message unpinned' : 'Message was not pinned';
            $response['message_id'] = $messageId;
            $response['pinned'] = false;
        }
        echo json_encode($response); exit;
    }

    // ── P2: Edit a message (author-only, enforced in repo via SENDER_ID) ──────
    if ($_POST['action'] === 'edit_message') {
        $me = (int) $_SESSION['user_id'];
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : '';
        if ($messageId <= 0) {
            $response['message'] = 'A valid message id is required';
            echo json_encode($response); exit;
        }
        if ($content === '') {
            $response['message'] = 'Message content is required';
            echo json_encode($response); exit;
        }
        $repo = new MessageRepository($pdo);
        $ok = $repo->editMessage($messageId, $me, $content);
        $response['success'] = $ok;
        $response['message'] = $ok ? 'Message edited' : 'Could not edit message';
        $response['message_id'] = $messageId;
        $response['edited'] = $ok;
        if ($ok) { $response['content'] = $content; }
        echo json_encode($response); exit;
    }

    // ── P2: Soft-delete a message (author-only; also drops any pin) ───────────
    if ($_POST['action'] === 'delete_message') {
        $me = (int) $_SESSION['user_id'];
        $messageId = (int) ($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            $response['message'] = 'A valid message id is required';
            echo json_encode($response); exit;
        }
        $repo = new MessageRepository($pdo);
        $ok = $repo->softDeleteMessage($messageId, $me);
        $response['success'] = $ok;
        $response['message'] = $ok ? 'Message deleted' : 'Could not delete message';
        $response['message_id'] = $messageId;
        $response['deleted'] = $ok;
        echo json_encode($response); exit;
    }

    // ── P3: Typing heartbeat (rides the unified poll for the read-back) ───────
    if ($_POST['action'] === 'set_typing') {
        $me = (int) $_SESSION['user_id'];
        $gid  = isset($_POST['group_id'])    && $_POST['group_id']    !== '' ? (int) $_POST['group_id']    : null;
        $peer = ($gid === null && isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '')
            ? (int) $_POST['receiver_id'] : null;
        if ($gid === null && $peer === null) {
            $response['message'] = 'A receiver or group is required';
            echo json_encode($response); exit;
        }
        if ($gid !== null) {
            if (!isGroupMember($pdo, $gid, $me)) {
                $response['message'] = 'You are not a member of this group';
                echo json_encode($response); exit;
            }
            $convKey = MessageRepository::groupConvKey($gid);
        } else {
            $convKey = MessageRepository::dmConvKey($me, $peer);
        }
        $repo = new MessageRepository($pdo);
        $response['success'] = $repo->setTyping($me, $convKey);
        echo json_encode($response); exit;
    }

    // ── P4: Search within the user's own messages (scope enforced in repo) ────
    if ($_POST['action'] === 'search_messages') {
        $me = (int) $_SESSION['user_id'];
        $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        if ($q === '') {
            $response['success'] = true;
            $response['results'] = [];
            echo json_encode($response); exit;
        }
        $repo = new MessageRepository($pdo);
        $response['success'] = true;
        $response['results'] = $repo->searchMessages($me, $q);
        echo json_encode($response); exit;
    }

    // If we get here, the action was not recognized
    $response['message'] = 'Invalid action';
    echo json_encode($response);
    exit;
}

// Initial conversation list (real unread + categories). JS keeps it live via poll.
$current_user_id = (int) $_SESSION['user_id'];
try {
    $conversations = fetchConversations($pdo, $current_user_id);
    $groups = fetchGroups($pdo, $current_user_id);
} catch (PDOException $e) {
    $conversations = [];
    $groups = [];
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
    <link rel="stylesheet" href="<?php echo asset('assets/css/chat-overhaul.css'); ?>">
</head>
<body>
    <?php include('sidebar.php'); ?>

    <!-- (Sidebar overlay is provided by header.php now — no duplicate here.) -->

    <!-- Mobile conversations toggle button -->
    <button class="mobile-conversations-toggle" id="mobile-conversations-toggle">
        <i class="fas fa-comments"></i>
    </button>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Standard Feedora app top bar (same header as every other page) -->
        <?php include('header.php'); ?>

        <!-- Mobile overlay for conversations list -->
        <div class="mobile-overlay" id="mobile-overlay"></div>

        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Column 1: unified left sidebar (Direct Messages + Channels) -->
            <div class="conversations-list" id="conversations-list">
                <div class="conversations-top">
                    <a href="dashboard.php" class="conv-brand" title="Feedora — back to dashboard">
                        <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" alt="Feedora">
                    </a>
                    <h2 class="conversations-title">Messages</h2>
                    <button class="conv-new-chat" id="new-chat-round" title="New chat" aria-label="Start a new chat">
                        <i class="fas fa-pen-to-square"></i>
                    </button>
                </div>
                <div class="search-conversation">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Search chats" id="search-conversations" aria-label="Search chats">
                </div>

                <!-- Instagram-style category tabs -->
                <div class="conversation-tabs" id="conversation-tabs" role="tablist" aria-label="Message categories">
                    <button class="conv-tab active" data-category="primary" role="tab" aria-selected="true">Primary</button>
                    <button class="conv-tab" data-category="requests" role="tab" aria-selected="false">
                        Requests <span class="conv-tab-badge" id="requests-badge" style="display:none;">0</span>
                    </button>
                </div>

                <!-- One scroll area holds both the Direct Messages list and the Channels list -->
                <div class="conv-scroll" id="conv-scroll">
                    <div class="conv-section-label">Direct Messages</div>
                    <!-- Rendered by chat.js from ChatConfig.initialConversations + live poll -->
                    <div class="conversation-items" id="conversation-items"></div>

                    <!-- In-message search results (P4) -->
                    <div class="message-search-results" id="message-search-results" hidden role="region" aria-label="Message search results"></div>

                    <!-- Channels / groups section (merged from the old group-rail) -->
                    <div class="channels-head">
                        <span class="conv-section-label">Channels</span>
                        <button class="channels-add" id="rail-create-group" title="Create group" aria-label="Create a group">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <!-- Group rows rendered by chat-overhaul.js (renderRail) -->
                    <div class="channels-list" id="rail-groups"></div>
                </div>
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
                                <div class="typing-indicator" id="typing-indicator" hidden aria-live="polite">
                                    <span class="typing-dots"><span></span><span></span><span></span></span>
                                    <span class="typing-text"></span>
                                </div>
                            </div>
                            <div class="conversation-header-actions">
                                <button class="header-action-btn call-btn" id="voice-call-btn" title="Voice call">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <button class="header-action-btn call-btn" id="video-call-btn" title="Video call">
                                    <i class="fas fa-video"></i>
                                </button>
                                <button class="header-action-btn" id="pinned-btn" title="Pinned messages">
                                    <i class="fas fa-thumbtack"></i>
                                </button>
                                <button class="header-action-btn" id="toggle-info-btn" title="Members & files">
                                    <i class="fas fa-users"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Pinned messages panel (P1) -->
                        <div class="pinned-panel" id="pinned-panel" hidden role="region" aria-label="Pinned messages">
                            <div class="pinned-panel-head">
                                <span class="pinned-panel-title"><i class="fas fa-thumbtack"></i> Pinned messages</span>
                                <button class="pinned-close" id="pinned-close" title="Close" aria-label="Close pinned messages">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="pinned-list" id="pinned-list"></div>
                        </div>

                        <div class="messages-container" id="messages-container"></div>
                        
                        <!-- Single-input composer: attachments · one text field ·
                             one trailing button that is a MIC when empty and a
                             SEND arrow when you type. Recording overlays the row. -->
                        <div class="message-input-container" id="composer">
                            <label for="message-image" class="composer-icon-btn" title="Attach photo or video">
                                <i class="fas fa-image"></i>
                            </label>
                            <input type="file" id="message-image" accept="image/*,video/*" style="display: none;">
                            <button class="composer-icon-btn" id="show-emoji-btn" title="Add emoji" type="button">
                                <i class="far fa-face-smile"></i>
                            </button>

                            <div class="message-input-wrapper">
                                <textarea id="message-input" placeholder="Message…" rows="1"></textarea>
                                <div class="image-preview" id="image-preview" style="display: none;">
                                    <img id="preview-image" src="/placeholder.svg" alt="Preview">
                                    <button class="remove-image-btn" id="remove-image-btn" title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Trailing button: mic (default) OR send (when typing) -->
                            <button class="composer-send mic" id="voice-record-btn" type="button" title="Record voice note" aria-label="Record voice note">
                                <i class="fas fa-microphone"></i>
                            </button>
                            <button class="composer-send send" id="send-message-btn" type="button" title="Send message" aria-label="Send message" style="display:none;">
                                <i class="fas fa-paper-plane"></i>
                            </button>

                            <!-- Recording bar overlays the whole composer row while recording -->
                            <div class="voice-record-bar" id="voice-record-bar" style="display:none;">
                                <button class="vr-cancel" id="vr-cancel" type="button" title="Cancel"><i class="fas fa-trash"></i></button>
                                <span class="vr-dot"></span>
                                <span class="vr-time" id="vr-time">0:00</span>
                                <span class="vr-hint">Recording voice message…</span>
                                <button class="vr-send" id="vr-send" type="button" title="Send"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Call window overlay (multi-party mesh, Discord-style) -->
                    <div class="call-window" id="call-window" style="display:none;" role="dialog" aria-label="Call">
                        <div class="call-topbar">
                            <span class="call-title" id="call-title">Call</span>
                            <span class="call-count" id="call-count" aria-live="polite">1 in call</span>
                        </div>
                        <!-- Responsive video-tile grid; tiles are added/removed live by chat-overhaul.js -->
                        <div class="call-grid" id="call-grid"></div>
                        <div class="call-tray">
                            <button class="call-ctrl" id="call-mute-btn" title="Mute" aria-label="Toggle microphone"><i class="fas fa-microphone"></i></button>
                            <button class="call-ctrl" id="call-video-btn" title="Toggle camera" aria-label="Toggle camera"><i class="fas fa-video"></i></button>
                            <button class="call-ctrl call-hangup" id="call-end-btn" title="Leave call" aria-label="Leave call"><i class="fas fa-phone-slash"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Column 4: Info sidebar (members + shared files) -->
            <aside class="info-sidebar" id="info-sidebar">
                <div class="info-header">
                    <h3 class="info-header-title">Details</h3>
                    <button class="info-close-btn" id="info-close-btn" title="Close" aria-label="Close details panel">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="info-section">
                    <h3 class="info-title">Members <span id="info-member-count"></span></h3>
                    <div class="info-members" id="info-members">
                        <p class="info-empty">Open a group to see members.</p>
                    </div>
                    <button class="info-invite-btn" id="info-invite-btn" style="display:none;">
                        <i class="fas fa-user-plus"></i> Invite members
                    </button>
                </div>
                <div class="info-section">
                    <h3 class="info-title">Files</h3>
                    <div class="info-files" id="info-files">
                        <div class="info-file-row"><i class="far fa-images"></i> <span id="files-photos">0</span> photos</div>
                        <div class="info-file-row"><i class="far fa-file-video"></i> <span id="files-videos">0</span> videos</div>
                        <div class="info-file-thumbs" id="info-file-thumbs"></div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Incoming call modal -->
        <div class="call-incoming-modal" id="call-incoming-modal" style="display:none;">
            <div class="call-incoming-card">
                <img class="call-incoming-avatar" id="call-incoming-avatar" src="" alt="">
                <div class="call-incoming-name" id="call-incoming-name">Someone</div>
                <div class="call-incoming-type" id="call-incoming-type">Incoming call…</div>
                <div class="call-incoming-actions">
                    <button class="call-accept" id="call-accept-btn"><i class="fas fa-phone"></i> Join</button>
                    <button class="call-reject" id="call-reject-btn"><i class="fas fa-phone-slash"></i> Reject</button>
                </div>
            </div>
        </div>

        <!-- Create group modal -->
        <div class="modal" id="create-group-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Create a group</h3>
                    <button class="back-to-messages-btn" id="close-create-group" title="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <label class="cg-avatar-label" for="cg-avatar">
                        <span id="cg-avatar-preview"><i class="fas fa-camera"></i></span>
                        <input type="file" id="cg-avatar" accept="image/*" style="display:none;">
                    </label>
                    <input type="text" id="cg-name" class="cg-input" placeholder="Group name" maxlength="255">
                    <div class="cg-search"><input type="text" id="cg-member-search" class="cg-input" placeholder="Search people to add…"></div>
                    <div class="cg-members" id="cg-members"></div>
                    <button class="cg-create-btn" id="cg-create-btn">Create group</button>
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

    <script>
        window.ChatConfig = {
            currentUserId: <?php echo json_encode($_SESSION["user_id"]); ?>,
            currentUserProfileImage: <?php echo json_encode(!empty($_SESSION["profile_image"]) ? $_SESSION["profile_image"] : "uploads/profiles/default.png"); ?>,
            currentUserName: <?php echo json_encode($_SESSION["user_name"] ?? "You"); ?>,
            defaultAvatar: "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg",
            initialConversations: <?php echo json_encode($conversations); ?>,
            initialGroups: <?php echo json_encode($groups ?? []); ?>
        };
    </script>
    <script src="<?php echo asset('assets/js/chat.js'); ?>" defer></script>
    <script src="<?php echo asset('assets/js/chat-overhaul.js'); ?>" defer></script>

    <!-- Overlay safety net: never let a scrim/modal/drawer trap the user.
         Clicking a modal backdrop or any overlay closes it; Escape closes
         everything; and the page scroll-lock is always released. -->
    <script>
    (function () {
      function releaseLock() { document.body.style.overflow = ''; document.body.classList.remove('sidebar-active'); }
      function closeAll() {
        // Modals (dark backdrops)
        document.querySelectorAll('.modal').forEach(function (m) { m.style.display = 'none'; m.classList.remove('active'); });
        // Info panel + its scrim
        var info = document.getElementById('info-sidebar');
        if (info) info.classList.remove('info-open');
        document.querySelectorAll('.info-scrim').forEach(function (s) { s.classList.remove('show'); });
        // Mobile conversation drawer + overlay
        var cc = document.querySelector('.chat-container'); if (cc) cc.classList.remove('drawer-open');
        var mo = document.getElementById('mobile-overlay'); if (mo) mo.classList.remove('active');
        // App-nav sidebar drawer + its overlay
        var sb = document.querySelector('.sidebar'); if (sb) sb.classList.remove('active');
        var so = document.getElementById('sidebar-overlay'); if (so) so.classList.remove('active');
        releaseLock();
      }
      // Click directly on a modal's dark backdrop (not its content) closes it.
      document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('modal')) { e.target.style.display = 'none'; e.target.classList.remove('active'); releaseLock(); }
      });
      // Escape is a universal "get me out".
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
      // Safety on load: clear any lock left over from a previous page/state.
      releaseLock();
    })();
    </script>
</body>
</html>
