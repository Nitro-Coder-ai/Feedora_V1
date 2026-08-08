<?php
// Include the database connection script
require_once 'config/config.php';
// Include FIXED notification utilities
require_once 'notification_utils.php';
// Cloudinary uploader (with graceful local-disk fallback)
require_once 'includes/CloudinaryService.php';

/**
 * Validate one uploaded post file by its REAL bytes and push it to Cloudinary.
 * Accepts images (≤10MB), videos (≤50MB) and audio (≤15MB). Every media type
 * goes to Cloudinary — never local disk.
 * @return array{ok:bool,url:?string,public_id:?string,type:?string,error:?string}
 */
function feedora_post_upload(array $f, bool $audioOnly = false): array
{
    $fail = fn($m) => ['ok' => false, 'url' => null, 'public_id' => null, 'type' => null, 'error' => $m];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $fail('');
    $tmp = $f['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (finfo_file($finfo, $tmp) ?: '') : '';
    if ($finfo) finfo_close($finfo);
    $size = (int) ($f['size'] ?? 0);

    if (strncmp($mime, 'audio/', 6) === 0 || $mime === 'application/ogg') {
        if ($size > 15 * 1024 * 1024) return $fail('Audio too large (max 15MB).');
        $type = 'audio';
    } elseif (!$audioOnly && strncmp($mime, 'video/', 6) === 0) {
        if ($size > 50 * 1024 * 1024) return $fail('Video too large (max 50MB).');
        $type = 'video';
    } elseif (!$audioOnly && strncmp($mime, 'image/', 6) === 0) {
        if (safe_image_ext($tmp) === null) return $fail('Invalid image file.');
        if ($size > 10 * 1024 * 1024) return $fail('Image too large (max 10MB).');
        $type = 'image';
    } else {
        return $fail($audioOnly ? 'Please choose an audio file.' : 'Unsupported file type.');
    }

    $cloud = CloudinaryService::upload($tmp, 'feedora/posts');
    if (!$cloud['success']) return $fail('Upload failed. Please try again.');
    return ['ok' => true, 'url' => $cloud['url'], 'public_id' => $cloud['public_id'], 'type' => $type, 'error' => null];
}

// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// FIXED: Ensure user session data is current and accurate
$userId = $_SESSION['user_id'];

// FIXED: Refresh user session data from database to prevent wrong user display
try {
    $userStmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL, PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        // User doesn't exist anymore, logout
        session_destroy();
        header("Location: sign-in.php");
        exit();
    }
    
    // FIXED: Update session with current user data to prevent wrong user display
    $_SESSION['user_id'] = $currentUser['USER_ID'];
    $_SESSION['user_name'] = $currentUser['NAME'];
    $_SESSION['user_email'] = $currentUser['EMAIL'];
    if ($currentUser['PROFILE_IMAGE']) {
        $_SESSION['profile_image'] = $currentUser['PROFILE_IMAGE'];
    }
    
    error_log("✅ Session refreshed for user: " . $currentUser['NAME'] . " (ID: " . $currentUser['USER_ID'] . ")");
    
} catch (PDOException $e) {
    error_log("❌ Error refreshing user session: " . $e->getMessage());
    // Don't redirect, just log the error
}

$message = '';
$postSuccess = false;

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $mediaItems = [];   // carousel media: [ [url,public_id,type], ... ]
    $audio = null;      // attached music/sound: [url,public_id,type]
    $imageUrl = null;   // cover (first media) — kept for feed back-compat

    // Gather media files from the new multi-file field `post_media[]` plus the
    // legacy single `post_image` (back-compat). Everything → Cloudinary.
    $files = [];
    if (isset($_FILES['post_media']) && is_array($_FILES['post_media']['name'] ?? null)) {
        $n = count($_FILES['post_media']['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($_FILES['post_media']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $files[] = [
                'name'     => $_FILES['post_media']['name'][$i],
                'type'     => $_FILES['post_media']['type'][$i],
                'tmp_name' => $_FILES['post_media']['tmp_name'][$i],
                'size'     => $_FILES['post_media']['size'][$i],
                'error'    => $_FILES['post_media']['error'][$i],
            ];
        }
    }
    if (isset($_FILES['post_image']) && ($_FILES['post_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $files[] = $_FILES['post_image'];
    }

    // Cap the carousel at 10 items; upload each to Cloudinary.
    foreach (array_slice($files, 0, 10) as $f) {
        $r = feedora_post_upload($f);
        if ($r['ok']) $mediaItems[] = $r;
        elseif ($r['error']) $message = $r['error'];
    }
    if ($mediaItems) $imageUrl = $mediaItems[0]['url'];

    // Optional attached music / sound track.
    if (isset($_FILES['post_audio']) && ($_FILES['post_audio']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $ar = feedora_post_upload($_FILES['post_audio'], true);
        if ($ar['ok']) $audio = $ar;
        elseif ($ar['error']) $message = $ar['error'];
    }
    $audioTitle = trim($_POST['audio_title'] ?? '');

    // ENHANCED: Insert post into database and send professional notifications
    if ($description !== '' || $mediaItems || $audio) {
        try {
            // Begin transaction to ensure post and notifications are created together
            $pdo->beginTransaction();
            
            error_log("🚀 Creating post for user ID: {$userId} with name: " . $_SESSION['user_name']);
            
            // Insert the post (cover = first media; carousel rows added below).
            $firstType = $mediaItems ? $mediaItems[0]['type'] : 'image';
            $stmt = $pdo->prepare(
                "INSERT INTO POSTS (USER_ID, IMAGE_URL, IMAGE_PUBLIC_ID, MEDIA_TYPE, DESCRIPTION, AUDIO_URL, AUDIO_PUBLIC_ID, AUDIO_TITLE, CREATED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId, $imageUrl, ($mediaItems[0]['public_id'] ?? null), $firstType, $description,
                ($audio['url'] ?? null), ($audio['public_id'] ?? null), ($audio ? ($audioTitle !== '' ? $audioTitle : 'Audio') : null),
            ]);

            // Get the new post ID
            $postId = $pdo->lastInsertId();

            // Store every media item for the carousel.
            if ($mediaItems) {
                $mStmt = $pdo->prepare(
                    "INSERT INTO POST_MEDIA (POSTS_ID, MEDIA_URL, MEDIA_PUBLIC_ID, MEDIA_TYPE, POSITION) VALUES (?, ?, ?, ?, ?)"
                );
                foreach ($mediaItems as $pos => $m) {
                    $mStmt->execute([$postId, $m['url'], $m['public_id'], $m['type'], $pos]);
                }
            }
            
            error_log("✅ Post created with ID: {$postId}");
            
            // ENHANCED: Send professional notifications to followers
            $userName = $_SESSION['user_name'] ?? 'Someone';
            
            // Get all followers
            $followersStmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
            $followersStmt->execute([$userId]);
            $followers = $followersStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($followers)) {
                // Create professional notification content
                $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> shared a new post";
                
                // Add preview of the post content if available
                if (!empty($description)) {
                    $previewText = strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                    $notificationContent .= ": " . htmlspecialchars($previewText);
                }
                
                // Batch insert notifications for better performance
                $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($followers));
                $placeholders = rtrim($placeholders, ',');
                
                $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
                        VALUES $placeholders";
                $stmt = $pdo->prepare($sql);
                
                $params = [];
                foreach ($followers as $followerId) {
                    $params = array_merge($params, [
                        $userId,                    // USER_ID (who created the post)
                        $followerId,               // TARGET_USER_ID (who receives the notification)
                        'new_post',               // NOTIFICATION_TYPE
                        $notificationContent,     // CONTENT
                        $postId                   // RELATED_ID (post ID)
                    ]);
                }
                
                $result = $stmt->execute($params);
                
                if ($result) {
                    error_log("✅ Professional notifications sent to " . count($followers) . " followers for post {$postId}");
                    
                    // ENHANCED: Log detailed notification info for debugging
                    error_log("📊 Notification details:");
                    error_log("   - Post ID: {$postId}");
                    error_log("   - Author: {$userName} (ID: {$userId})");
                    error_log("   - Followers notified: " . count($followers));
                    error_log("   - Content preview: " . substr($notificationContent, 0, 100));
                    
                } else {
                    error_log("⚠️ Failed to send notifications for post {$postId}");
                }
            } else {
                error_log("ℹ️ No followers to notify for user {$userName} (ID: {$userId})");
            }
            
            // Commit the transaction
            $pdo->commit();
            
            $postSuccess = true;
            $_SESSION['post_message'] = "Your post has been published successfully and notifications sent to your followers!";
            $_SESSION['post_success'] = true;
            
            error_log("✅ Post creation and notification process completed successfully");
            
        } catch (PDOException $e) {
            // Rollback on error
            $pdo->rollback();
            error_log("❌ Post creation failed: " . $e->getMessage());
            $_SESSION['post_message'] = 'Error creating post: ' . $e->getMessage();
            $_SESSION['post_success'] = false;
        }
    } else {
        $_SESSION['post_message'] = "Please add a description or image to your post.";
        $_SESSION['post_success'] = false;
    }
    
    // FIXED: Add a small delay before redirect to ensure session is properly saved
    sleep(1);
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit();
}
?>