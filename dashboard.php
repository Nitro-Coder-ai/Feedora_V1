<?php
// Include the database connection script
require_once 'config/config.php';
// Include Cloudinary service
require_once 'includes/CloudinaryService.php';
// Include the story cleanup script
require_once 'includes/story_cleanup.php';

require_once 'notification_utils.php';

// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Logged-out visitors get an attractive public preview of the Community
// (instead of a bare redirect to sign-in).
if (!isset($_SESSION['user_id'])) {
    include __DIR__ . '/includes/preview_community.php';
    exit();
}

// Clean up expired stories (older than 24 hours)
// This runs silently in the background when a user visits the dashboard
cleanupExpiredStories($pdo);

// Handle AJAX requests for post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    // Edit post
    if ($_POST['action'] === 'edit_post' && isset($_POST['edit_post_id'])) {
        $postId = $_POST['edit_post_id'];
        $description = trim($_POST['edit_description'] ?? '');
        $existingImage = $_POST['existing_image'] ?? null;
        $userId = $_SESSION['user_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $userId) {
                $response['message'] = 'You do not have permission to edit this post.';
                echo json_encode($response);
                exit();
            }

            // Handle image upload if a new image is provided
            $imageUrl = $existingImage;
            if (isset($_FILES['edit_post_image']) && $_FILES['edit_post_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/posts/';

                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $fileExtension = pathinfo($_FILES['edit_post_image']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('post_') . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;

                // Check if file is an image or video
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
                $fileType = $_FILES['edit_post_image']['type'];

                if (in_array($fileType, $allowedTypes)) {
                    // Move uploaded file to target directory
                    if (move_uploaded_file($_FILES['edit_post_image']['tmp_name'], $targetFilePath)) {
                        $imageUrl = $targetFilePath;
                    } else {
                        $response['message'] = "Sorry, there was an error uploading your file.";
                        echo json_encode($response);
                        exit();
                    }
                } else {
                    $response['message'] = "Only JPG, PNG, GIF, WEBP, and MP4 video files are allowed.";
                    echo json_encode($response);
                    exit();
                }
            }

            // Update post in database
            $stmt = $pdo->prepare("UPDATE POSTS SET DESCRIPTION = ?, IMAGE_URL = ? WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$description, $imageUrl, $postId, $userId]);

            $response['success'] = true;
            $response['message'] = 'Post updated successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error updating post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Delete post
    if ($_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
        $postId = $_POST['post_id'];
        $userId = $_SESSION['user_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID, IMAGE_URL FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $userId) {
                $response['message'] = 'You do not have permission to delete this post.';
                echo json_encode($response);
                exit();
            }

            // Delete the post from the database
            $stmt = $pdo->prepare("DELETE FROM POSTS WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$postId, $userId]);

            // Delete the image or video file if it exists
            if (!empty($post['IMAGE_URL'])) {
                $filePath = $post['IMAGE_URL'];
                if (strpos($filePath, 'http') === 0) {
                    CloudinaryService::destroyByUrl($filePath);
                } elseif (file_exists($filePath)) {
                    try {
                        unlink($filePath);
                    } catch (Exception $e) {
                        // Silently handle file deletion error
                    }
                }
            }

            $response['success'] = true;
            $response['message'] = 'Post deleted successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error deleting post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }
}

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = $_SESSION['profile_image'] ?? null;

// If profile image is not in session, try to get it from database
if (!$profileImage) {
    try {
        $stmt = $pdo->prepare("SELECT PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['PROFILE_IMAGE']) {
            $profileImage = $result['PROFILE_IMAGE'];
            $_SESSION['profile_image'] = $profileImage;
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Set default profile image if none exists
if (!$profileImage) {
    $profileImage = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
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
    <title>Dashboard - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <!-- Favicon -->
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/dashboard.css'); ?>">
</head>

<body>
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include('header.php'); ?>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Enhanced Stories Section -->
            <div class="stories-container">
                <h3 class="section-title">Stories</h3>
                <div class="stories-list">
                    <!-- Enhanced Add Story Button with Multiple Upload -->
                    <div class="story-item add-story">
                        <form action="story.php" method="POST" enctype="multipart/form-data" id="storyForm">
          <?php echo csrf_field(); ?>
                            <input type="file" name="story_media[]" id="storyMedia" class="story-media-input"
                                accept="image/*,video/mp4,video/quicktime,video/webm"
                                multiple
                                onchange="handleStoryUpload(this)">
                            <input type="hidden" name="visibility" value="public">
                            <label for="storyMedia" class="add-story-label">
                                <div class="story-add-icon" style="background-image: url('<?php echo htmlspecialchars($profileImage); ?>'); background-size: cover; background-position: center;">
                                    <div class="plus-icon-container">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </div>
                                </div>
                                <span class="story-username">Add Story</span>
                            </label>
                        </form>
                    </div>

                    <?php
                    // Get active stories from the database (less than 24 hours old)
                    // Enhanced Stories Query - Get all active stories grouped by user with counts
                    try {
                        // Visibility algorithm enforced in SQL: a user appears in the
                        // tray only if they have >=1 story this viewer may see —
                        // own stories, any 'public' story, or 'friends' stories when
                        // the viewer follows the author. First-story fields use
                        // GROUP_CONCAT (newline separator avoids Cloudinary commas)
                        // so the whole tray is a single optimized query.
                        $storyStmt = $pdo->prepare(
                            "SELECT
                    s.USER_ID,
                    u.NAME,
                    u.PROFILE_IMAGE,
                    COUNT(DISTINCT s.STORIES_ID) as story_count,
                    MAX(s.CREATED_AT) as latest_story_time,
                    SUBSTRING_INDEX(GROUP_CONCAT(s.STORIES_ID ORDER BY s.CREATED_AT ASC), ',', 1) as first_story_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(s.IMAGE_URL ORDER BY s.CREATED_AT ASC SEPARATOR 0x0A), 0x0A, 1) as first_story_image,
                    SUBSTRING_INDEX(GROUP_CONCAT(s.MEDIA_TYPE ORDER BY s.CREATED_AT ASC), ',', 1) as first_story_type,
                    COUNT(DISTINCT sv.STORY_ID) as total_viewed_count,
                    COUNT(DISTINCT s.STORIES_ID) as total_stories_count
                FROM STORIES s
                JOIN USERS u ON s.USER_ID = u.USER_ID
                LEFT JOIN STORY_VIEWS sv ON sv.STORY_ID = s.STORIES_ID AND sv.VIEWER_ID = ?
                WHERE s.EXPIRES_AT > NOW()
                  AND (
                        s.USER_ID = ?
                     OR s.VISIBILITY = 'public'
                     OR (s.VISIBILITY = 'friends' AND EXISTS (
                            SELECT 1 FROM FOLLOWERS f
                            WHERE f.USER_ID = s.USER_ID AND f.FOLLOWER_ID = ?))
                  )
                GROUP BY s.USER_ID, u.NAME, u.PROFILE_IMAGE
                ORDER BY latest_story_time DESC"
                        );
                        $storyStmt->execute([$userId, $userId, $userId]);
                        $userStories = $storyStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Display each user's story with enhanced UI
                        foreach ($userStories as $userStory) {
                            $hasUnviewed = ($userStory['total_viewed_count'] < $userStory['total_stories_count']);
                            $storyBorderClass = $hasUnviewed ? 'story-border-unviewed' : 'story-border-viewed';
                            $userImage = !empty($userStory['PROFILE_IMAGE']) ? $userStory['PROFILE_IMAGE'] : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

                            // Calculate time ago for latest story
                            $latestTime = new DateTime($userStory['latest_story_time']);
                            $now = new DateTime();
                            $diff = $now->diff($latestTime);

                            if ($diff->days > 0) {
                                $timeAgo = $diff->days . 'd';
                            } elseif ($diff->h > 0) {
                                $timeAgo = $diff->h . 'h';
                            } elseif ($diff->i > 0) {
                                $timeAgo = $diff->i . 'm';
                            } else {
                                $timeAgo = 'now';
                            }

                            echo '<a href="story.php?user_id=' . $userStory['USER_ID'] . '" class="story-item" data-user-id="' . $userStory['USER_ID'] . '">';
                            
                            // Render story backdrop media if exists
                            if (!empty($userStory['first_story_image'])) {
                                if ($userStory['first_story_type'] === 'video') {
                                    echo '<video class="story-card-bg" src="' . htmlspecialchars($userStory['first_story_image']) . '" autoplay loop muted playsinline></video>';
                                } else {
                                    echo '<img class="story-card-bg" src="' . htmlspecialchars($userStory['first_story_image']) . '" alt="Story Backdrop" loading="lazy">';
                                }
                            }
                            
                            echo '<div class="story-avatar ' . $storyBorderClass . '">';
                            echo '<img src="' . htmlspecialchars($userImage) . '" alt="' . htmlspecialchars($userStory['NAME']) . '">';

                            // Story count indicator for multiple stories
                            if ($userStory['story_count'] > 1) {
                                echo '<div class="story-count-badge">' . $userStory['story_count'] . '</div>';
                            }

                            // Unviewed indicator
                            if ($hasUnviewed) {
                                echo '<div class="story-unviewed-dot"></div>';
                            }

                            echo '</div>';
                            echo '<div class="story-user-info">';
                            echo '<span class="story-username">' . htmlspecialchars($userStory['NAME']) . '</span>';
                            echo '</div>';
                            echo '</a>';
                        }
                    } catch (PDOException $e) {
                        // Silently handle error
                        error_log("Stories query error: " . $e->getMessage());
                    }
                    ?>
                </div>
            </div>

            <!-- Post Creation Section -->
            <div class="post-creation-container" id="create-post" style="scroll-margin-top:88px;">
                <div class="post-tabs">
                    <div class="post-tab active">Make Post</div>
                    <div class="post-tab">Make Recipe</div>
                </div>

                <div class="post-content">
                    <?php if (isset($_SESSION['post_message'])): ?>
                        <div class="message <?php echo isset($_SESSION['post_success']) && $_SESSION['post_success'] ? 'success-message' : 'error-message'; ?>">
                            <?php echo htmlspecialchars($_SESSION['post_message']); ?>
                        </div>
                        <?php
                        // Clear the message after displaying it
                        unset($_SESSION['post_message']);
                        unset($_SESSION['post_success']);
                        ?>
                    <?php endif; ?>

                    <div class="user-info-container">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="user-profile-pic">
                    </div>

                    <form action="post.php" method="POST" enctype="multipart/form-data" class="post-form" id="postForm">
          <?php echo csrf_field(); ?>
                        <textarea name="description" placeholder="What's recipe are you cooking today?" class="post-textarea" id="postDescription"></textarea>

                        <div class="post-options">
                            <div class="media-upload">
                                <label for="postImage" class="media-upload-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    Photo or Video
                                </label>
                                <input type="file" name="post_image" id="postImage" class="media-upload-input" accept="image/*,video/mp4">
                            </div>

                            <button type="submit" class="post-button" id="postButton">
                                Post Now
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>

                        <div class="image-preview-container" id="imagePreviewContainer">
                            <div style="position: relative; display: inline-block;">
                                <img src="/placeholder.svg" alt="Preview" class="image-preview" id="imagePreview">
                                <button type="button" class="remove-image" id="removeImage">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Posts Feed Section with Enhanced Reactions -->
            <?php
            // REPLACE the Posts Feed Section in your dashboard.php with this optimized version:

            // Posts Feed Section with Enhanced Reactions and Comments
            echo '<div class="posts-feed">';

            try {
                // Get posts with reaction counts AND comment counts from database
                $stmt = $pdo->prepare(
                    "SELECT p.*, u.NAME, u.PROFILE_IMAGE, 
                COALESCE(prc.total_reactions, 0) as total_reactions,
                COALESCE(prc.yummy_count, 0) as yummy_count,
                COALESCE(prc.delicious_count, 0) as delicious_count,
                COALESCE(prc.tasty_count, 0) as tasty_count,
                COALESCE(prc.love_count, 0) as love_count,
                COALESCE(prc.amazing_count, 0) as amazing_count,
                pr.REACTION_TYPE as user_reaction,
                COALESCE(cc.comment_count, 0) as comment_count
        FROM POSTS p 
        JOIN USERS u ON p.USER_ID = u.USER_ID 
        LEFT JOIN POST_REACTION_COUNTS prc ON p.POSTS_ID = prc.POSTS_ID
        LEFT JOIN POST_REACTIONS pr ON p.POSTS_ID = pr.POST_ID AND pr.USER_ID = ?
        LEFT JOIN (
            SELECT POST_ID, COUNT(*) as comment_count 
            FROM COMMENTS 
            WHERE COMMENT_TEXT IS NOT NULL 
            GROUP BY POST_ID
        ) cc ON p.POSTS_ID = cc.POST_ID
        ORDER BY p.CREATED_AT DESC 
        LIMIT 10"
                );
                $stmt->execute([$userId]);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($posts) > 0) {
                    foreach ($posts as $post) {
                        // Format the post creation date
                        $postDate = new DateTime($post['CREATED_AT']);
                        $formattedDate = $postDate->format('M d, Y \a\t g:i A');
                        $userProfileImage = !empty($post['PROFILE_IMAGE']) ? $post['PROFILE_IMAGE'] : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

                        echo '<div class="post-card" data-post-id="' . $post['POSTS_ID'] . '">';

                        // Post header
                        echo '<div class="post-header">';
                        echo '<div class="post-user-info">';
                        echo '<img src="' . htmlspecialchars($userProfileImage) . '" alt="Profile" class="post-user-pic">';
                        echo '<div class="post-user-details">';
                        echo '<span class="post-user-name">' . htmlspecialchars($post['NAME']) . '</span>';
                        echo '<div class="post-meta-row">';
                        echo '<span class="post-shared">shared a</span>';

                        $fileType = 'post';
                        if (!empty($post['IMAGE_URL'])) {
                            $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                            $fileType = in_array($fileExtension, ['mp4', 'webm', 'ogg']) ? 'video' : 'image';
                        }
                        echo '<span class="post-meta">' . $fileType . '</span>';
                        echo '<span class="post-date">' . $formattedDate . '</span>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';

                        // Post actions menu (edit/delete) - only for post owner
                        if ($post['USER_ID'] == $_SESSION['user_id']) {
                            echo '<div class="post-actions">';
                            echo '<div class="post-actions-menu" onclick="togglePostMenu(' . $post['POSTS_ID'] . ')">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                            echo '<circle cx="12" cy="12" r="1"></circle>';
                            echo '<circle cx="19" cy="12" r="1"></circle>';
                            echo '<circle cx="5" cy="12" r="1"></circle>';
                            echo '</svg>';
                            echo '</div>';

                            echo '<div class="post-menu-dropdown" id="post-menu-' . $post['POSTS_ID'] . '">';
                            echo '<div class="post-menu-item edit-post" onclick="editPost(' . $post['POSTS_ID'] . ', `' . htmlspecialchars(addslashes($post['DESCRIPTION'] ?? ''), ENT_QUOTES) . '`, `' . htmlspecialchars($post['IMAGE_URL'] ?? '', ENT_QUOTES) . '`)">Edit Post</div>';
                            echo '<div class="post-menu-item delete-post" onclick="deletePost(' . $post['POSTS_ID'] . ')">Delete Post</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</div>';

                        // Post media
                        if (!empty($post['IMAGE_URL'])) {
                            echo '<div class="post-image">';
                            $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                            $isVideo = in_array($fileExtension, ['mp4', 'webm', 'ogg']);

                            if ($isVideo) {
                                echo '<video controls width="100%">';
                                echo '<source src="' . htmlspecialchars($post['IMAGE_URL']) . '" type="video/' . $fileExtension . '">';
                                echo 'Your browser does not support the video tag.';
                                echo '</video>';
                            } else {
                                echo '<img src="' . htmlspecialchars($post['IMAGE_URL']) . '" alt="Post Image" class="post-image-content" loading="lazy" decoding="async" onclick="openImageModal(\'' . htmlspecialchars($post['IMAGE_URL']) . '\')">';
                            }
                            echo '</div>';
                        }

                        // Post description
                        if (!empty($post['DESCRIPTION'])) {
                            echo '<div class="post-description">';
                            echo '<p>' . nl2br(htmlspecialchars($post['DESCRIPTION'])) . '</p>';
                            echo '</div>';
                        }

                        // Post engagement stats with enhanced database-driven reactions
                        $totalReactions = intval($post['total_reactions']);
                        $userReaction = $post['user_reaction'];
                        $commentCount = intval($post['comment_count']);
                        $postId = $post['POSTS_ID'];

                        echo '<div class="post-stats">';
                        echo '<div class="post-likes-container" data-post-id="' . $postId . '">';

                        // Main like button with current reaction state
                        $likeButtonClass = $userReaction ? 'post-likes active' : 'post-likes';
                        echo '<div class="' . $likeButtonClass . '" data-user-reaction="' . ($userReaction ?: '') . '">';

                        // Like icon with proper fill state
                        $iconFill = $userReaction ? 'var(--primary-color)' : 'none';
                        $iconStroke = $userReaction ? 'var(--primary-color)' : 'currentColor';

                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="' . $iconFill . '" stroke="' . $iconStroke . '" class="like-icon" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                        echo '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>';
                        echo '</svg>';

                        // Show reaction count with click handler to show users
                        if ($totalReactions > 0) {
                            echo '<span class="like-count" onclick="showReactionUsers(' . $postId . ')">';
                            echo $totalReactions . ($totalReactions === 1 ? ' reaction' : ' reactions');
                            echo '</span>';
                        } else {
                            echo '<span class="like-count">0 reactions</span>';
                        }
                        echo '</div>';

                        // Food reaction icons with enhanced interaction
                        echo '<div class="food-reactions" id="food-reactions-' . $postId . '">';

                        // Reaction icons with proper data attributes and click handlers
                        $reactions = [
                            'yummy' => ['emoji' => '🍔', 'title' => 'Yummy!'],
                            'delicious' => ['emoji' => '🍕', 'title' => 'Delicious!'],
                            'tasty' => ['emoji' => '🍰', 'title' => 'Tasty!'],
                            'love' => ['emoji' => '🍲', 'title' => 'Love it!'],
                            'amazing' => ['emoji' => '🍗', 'title' => 'Amazing!']
                        ];

                        foreach ($reactions as $type => $data) {
                            $isUserReaction = ($userReaction === $type) ? ' style="background-color: rgba(237, 90, 44, 0.2); transform: scale(1.1);"' : '';
                            echo '<div class="reaction-icon" data-reaction="' . $type . '" data-post-id="' . $postId . '" title="' . $data['title'] . '" onclick="handleReaction(' . $postId . ', \'' . $type . '\')"' . $isUserReaction . '>';
                            echo '<span>' . $data['emoji'] . '</span>';
                            echo '</div>';
                        }

                        echo '</div>';
                        echo '</div>';

                        // UPDATED Comments section with real count and click handler
                        echo '<div class="post-comments" style="cursor: pointer;" onclick="openCommentsModal(' . $postId . ')">';
                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                        echo '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>';
                        echo '</svg>';

                        // Show actual comment count from database
                        if ($commentCount > 0) {
                            echo '<span id="comment-count-' . $postId . '">' . $commentCount . ($commentCount === 1 ? ' comment' : ' comments') . '</span>';
                        } else {
                            echo '<span id="comment-count-' . $postId . '">0 comments</span>';
                        }
                        echo '</div>';
                        echo '</div>';

                        echo '</div>'; // End post-card
                    }
                } else {
                    echo '<div class="no-posts-message">';
                    echo '<p>No posts yet. Be the first to share something!</p>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="error-message">Error loading posts: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }

            echo '</div>'; // End posts-feed
            ?>
        </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal image-modal">
        <div class="image-modal-content">
            <span class="close-modal image-close-btn">&times;</span>
            <div class="image-container">
                <img id="modalImage" src="/placeholder.svg" alt="Full Size Image">
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div id="editPostModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Edit Post</h2>
            <form id="editPostForm" method="post" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
                <input type="hidden" id="edit_post_id" name="edit_post_id">
                <div class="form-group">
                    <textarea id="edit_description" name="edit_description" placeholder="What's recipe are you cooking today?"></textarea>
                </div>
                <div class="form-group">
                    <div class="image-preview-container" id="edit-image-preview-container">
                        <img id="edit-image-preview" src="/placeholder.svg" alt="Image Preview" style="display: none;">
                    </div>
                    <label for="edit_post_image" class="file-upload-label">
                        <span class="upload-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </span>
                        <span>Upload Image or Video</span>
                    </label>
                    <input type="file" id="edit_post_image" name="edit_post_image" accept="image/*,video/mp4" style="display: none;">
                    <input type="hidden" id="existing_image" name="existing_image">
                </div>
                <div class="form-actions">
                    <button type="submit" class="post-button">Update Post</button>
                </div>
            </form>
        </div>
    </div>



    <!-- Comments Modal -->
    <div id="commentsModal" class="comments-modal">
        <div class="comments-modal-content">
            <div class="comments-header">
                <h3>Comments</h3>
                <button class="close-comments" type="button">&times;</button>
            </div>

            <div class="comments-body" id="commentsBody">
                <!-- Comments will be loaded here dynamically -->
            </div>

            <div class="comment-form">
                <div class="comment-input-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Your avatar" class="comment-avatar">
                    <textarea
                        id="commentInput"
                        class="comment-input"
                        placeholder="Write a comment..."
                        rows="1"
                        maxlength="500"></textarea>
                    <button
                        id="commentSubmit"
                        class="comment-submit"
                        type="button"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Reaction Users Modal -->
    <!-- POST Reaction Users Modal -->
    <div id="postReactionUsersModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
            <span class="close-modal" style="cursor: pointer;">&times;</span>
            <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted</h2>
            <div id="postReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                <!-- Post reaction users will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <!-- COMMENT Reaction Users Modal -->
    <div id="commentReactionUsersModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
            <span class="close-modal" style="cursor: pointer;">&times;</span>
            <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted to this comment</h2>
            <div id="commentReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                <!-- Comment reaction users will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <script>
        window.DashboardConfig = { currentUserId: <?php echo json_encode($_SESSION["user_id"]); ?> };
    </script>
    <script src="<?php echo asset('assets/js/dashboard.js'); ?>" defer></script>


</body>

</html>
