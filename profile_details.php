<?php
// Include the database connection script
require_once 'config/config.php';
// Include Cloudinary service
require_once 'includes/CloudinaryService.php';
// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Helper function to get profile image
function getProfileImage($userProfileImage)
{
    return !empty($userProfileImage) ? htmlspecialchars($userProfileImage) : 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Get the profile user ID from URL parameter
$profileUserId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'follow' || $_POST['action'] === 'unfollow') {
        $targetUserId = (int)$_POST['user_id'];

        try {
            if ($_POST['action'] === 'follow') {
                $stmt = $pdo->prepare("INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID, FOLLOWED_AT) VALUES (?, ?, NOW())");
                if ($stmt->execute([$targetUserId, $currentUserId])) {
                    $response['success'] = true;
                    $response['action'] = 'followed';
                    $response['message'] = 'Successfully followed user';
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
                if ($stmt->execute([$targetUserId, $currentUserId])) {
                    $response['success'] = true;
                    $response['action'] = 'unfollowed';
                    $response['message'] = 'Successfully unfollowed user';
                }
            }

            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$targetUserId]);
            $response['new_follower_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Handle banner image upload
    if ($_POST['action'] === 'upload_banner' && $profileUserId === $currentUserId) {
        try {
            if (!isset($_FILES['banner_image']) || $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed.');
            }

            $file = $_FILES['banner_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
            $fileType = strtolower($file['type']);

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Invalid file type. Please upload JPG, PNG, GIF, WEBP images, or MP4, WEBM, OGG videos only.');
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }

            $uploadDir = 'uploads/banners/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = 'banner_' . $currentUserId . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;

            // Upload to Cloudinary first; fall back to local disk for uptime.
            $cloud = CloudinaryService::upload($file['tmp_name'], 'feedora/banners');
            if ($cloud['success']) {
                $bannerUrl = $cloud['url'];
            } elseif (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $bannerUrl = $uploadPath;
            } else {
                throw new Exception('Failed to save uploaded file.');
            }

            // Get current banner image path to delete old one
            $currentBannerStmt = $pdo->prepare("SELECT BANNER_IMAGE FROM USERS WHERE USER_ID = ?");
            $currentBannerStmt->execute([$currentUserId]);
            $currentBanner = $currentBannerStmt->fetchColumn();

            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare("UPDATE USERS SET BANNER_IMAGE = ? WHERE USER_ID = ?");
            if (!$updateStmt->execute([$bannerUrl, $currentUserId])) {
                $pdo->rollBack();
                if ($bannerUrl === $uploadPath && file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                throw new Exception('Failed to update database.');
            }
            $pdo->commit();

            // Delete old banner image if it exists.
            if ($currentBanner && $currentBanner !== 'images/default-banner.jpg') {
                if (strpos($currentBanner, 'http') === 0) {
                    CloudinaryService::destroyByUrl($currentBanner);
                } elseif (file_exists($currentBanner)) {
                    unlink($currentBanner);
                }
            }

            $response['success'] = true;
            $response['message'] = 'Banner updated successfully!';
            $response['banner_url'] = $bannerUrl;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Handle post editing (only for post owner)
    if ($_POST['action'] === 'edit_post' && isset($_POST['edit_post_id'])) {
        $postId = $_POST['edit_post_id'];
        $description = trim($_POST['edit_description'] ?? '');
        $existingImage = $_POST['existing_image'] ?? null;

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $currentUserId) {
                $response['message'] = 'You do not have permission to edit this post.';
                echo json_encode($response);
                exit();
            }

            // Handle image upload if a new image is provided
            $imageUrl = $existingImage;
            if (isset($_FILES['edit_post_image']) && $_FILES['edit_post_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/posts/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = pathinfo($_FILES['edit_post_image']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('post_') . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
                $fileType = $_FILES['edit_post_image']['type'];

                if (in_array($fileType, $allowedTypes)) {
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
            $stmt->execute([$description, $imageUrl, $postId, $currentUserId]);

            $response['success'] = true;
            $response['message'] = 'Post updated successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error updating post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Handle post deletion (only for post owner)
    if ($_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
        $postId = $_POST['post_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID, IMAGE_URL FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $currentUserId) {
                $response['message'] = 'You do not have permission to delete this post.';
                echo json_encode($response);
                exit();
            }

            // Delete the post from the database
            $stmt = $pdo->prepare("DELETE FROM POSTS WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$postId, $currentUserId]);

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

try {
    // Get profile user's information with aggregated data
    $userStmt = $pdo->prepare("
        SELECT 
            u.USER_ID,
            u.NAME,
            u.EMAIL,
            u.PROFILE_IMAGE,
            u.BANNER_IMAGE,
            u.CREATED_AT,
            u.LAST_LOGIN_AT,
            COALESCE(recipe_count.total, 0) as recipe_count,
            COALESCE(follower_data.follower_count, 0) as follower_count,
            COALESCE(following_data.following_count, 0) as following_count,
            COALESCE(post_data.post_count, 0) as post_count
        FROM USERS u
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as total 
            FROM RECIPES 
            GROUP BY USER_ID
        ) recipe_count ON u.USER_ID = recipe_count.USER_ID
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as follower_count 
            FROM FOLLOWERS 
            GROUP BY USER_ID
        ) follower_data ON u.USER_ID = follower_data.USER_ID
        LEFT JOIN (
            SELECT FOLLOWER_ID, COUNT(*) as following_count 
            FROM FOLLOWERS 
            GROUP BY FOLLOWER_ID
        ) following_data ON u.USER_ID = following_data.FOLLOWER_ID
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as post_count 
            FROM POSTS 
            GROUP BY USER_ID
        ) post_data ON u.USER_ID = post_data.USER_ID
        WHERE u.USER_ID = ?
    ");
    $userStmt->execute([$profileUserId]);
    $profileUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profileUser) {
        header("Location: dashboard.php");
        exit();
    }

    // Check if current user is following this profile
    $isFollowing = false;
    if ($currentUserId !== $profileUserId) {
        $followCheckStmt = $pdo->prepare("SELECT * FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $followCheckStmt->execute([$profileUserId, $currentUserId]);
        $isFollowing = (bool)$followCheckStmt->fetch();
    }

    // FIXED: Single posts query with debugging
    $postsStmt = $pdo->prepare(
        "SELECT 
            p.POSTS_ID,
            p.USER_ID, 
            p.IMAGE_URL,
            p.DESCRIPTION,
            p.CREATED_AT,
            u.NAME, 
            u.PROFILE_IMAGE,
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
        WHERE p.USER_ID = ?
        ORDER BY p.CREATED_AT DESC 
        LIMIT 50"
    );
    $postsStmt->execute([$currentUserId, $profileUserId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG: Log the actual posts data
    error_log("=== POSTS DEBUG INFO ===");
    error_log("Profile User ID: " . $profileUserId);
    error_log("Current User ID: " . $currentUserId);
    error_log("Total posts fetched: " . count($posts));

    if (!empty($posts)) {
        foreach ($posts as $index => $post) {
            error_log("Post Index {$index}: ID=" . ($post['POSTS_ID'] ?? 'NULL') . ", User=" . ($post['NAME'] ?? 'NULL'));
        }
    }

    // Check for duplicate POSTS_ID values
    $postIds = array_column($posts, 'POSTS_ID');
    $duplicates = array_diff_assoc($postIds, array_unique($postIds));
    if (!empty($duplicates)) {
        error_log("WARNING: Duplicate POSTS_ID found: " . implode(', ', $duplicates));
    }

    // Process profile images for posts
    $processedPosts = [];
    foreach ($posts as $post) {
        // FIXED: Ensure each post has a valid, unique POSTS_ID
        if (!isset($post['POSTS_ID']) || empty($post['POSTS_ID'])) {
            error_log("WARNING: Post without POSTS_ID found, skipping");
            continue;
        }

        // Convert POSTS_ID to integer and validate
        $post['POSTS_ID'] = intval($post['POSTS_ID']);
        if ($post['POSTS_ID'] <= 0) {
            error_log("WARNING: Invalid POSTS_ID found: " . $post['POSTS_ID']);
            continue;
        }

        // Process profile image
        $post['PROFILE_IMAGE'] = getProfileImage($post['PROFILE_IMAGE']);

        $processedPosts[] = $post;
    }

    // Use the processed posts array
    $posts = $processedPosts;

    // Get user's recipes (limit to 6 for better UI)
    $recipesStmt = $pdo->prepare(
        "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME 
        FROM RECIPES r 
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
        LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
        LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
        LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
        WHERE r.USER_ID = ? 
        ORDER BY r.RECIPES_ID DESC 
        LIMIT 6"
    );
    $recipesStmt->execute([$profileUserId]);
    $recipes = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in profile_details.php: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

// Set default images
$bannerImage = !empty($profileUser['BANNER_IMAGE']) ? $profileUser['BANNER_IMAGE'] : 'images/default-banner.jpg';
$profileUser['PROFILE_IMAGE'] = getProfileImage($profileUser['PROFILE_IMAGE']);
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
    <title><?php echo htmlspecialchars($profileUser['NAME']); ?> - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">

    <link rel="stylesheet" href="<?php echo asset('assets/css/profile_details.css'); ?>">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php include 'header.php'; ?>

        <!-- Profile Header - UNCHANGED -->
        <div class="profile-header">
            <div class="profile-banner">
                <img src="<?php echo htmlspecialchars($bannerImage); ?>" alt="Profile Banner" class="banner-image">
                <?php if ($profileUserId === $currentUserId): ?>
                    <div class="banner-upload">
                        <input type="file" id="banner-upload" accept="image/*" style="display: none;">
                        <button class="banner-upload-btn" onclick="document.getElementById('banner-upload').click()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                                <circle cx="12" cy="13" r="3"></circle>
                            </svg>
                            Change Banner
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profileUser['PROFILE_IMAGE']); ?>"
                        alt="<?php echo htmlspecialchars($profileUser['NAME']); ?>">
                </div>

                <div class="profile-details">
                    <h1 class="profile-name"><?php echo htmlspecialchars($profileUser['NAME']); ?></h1>
                    <p class="profile-username">@<?php echo strtolower(str_replace(' ', '', $profileUser['NAME'])); ?></p>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($profileUser['post_count']); ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="follower-count"><?php echo number_format($profileUser['follower_count']); ?></span>
                            <span class="stat-label">Followers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($profileUser['following_count']); ?></span>
                            <span class="stat-label">Following</span>
                        </div>
                    </div>

                    <?php if ($profileUserId !== $currentUserId): ?>
                        <div class="profile-actions">
                            <button class="btn <?php echo $isFollowing ? 'btn-secondary' : 'btn-primary'; ?>"
                                onclick="toggleFollow(<?php echo $profileUserId; ?>)"
                                id="follow-btn">
                                <?php echo $isFollowing ? 'Following' : 'Follow'; ?>
                            </button>
                            <a href="chat.php?id=<?php echo $profileUserId; ?>" class="btn btn-outline">Message</a>
                        </div>
                    <?php else: ?>
                        <div class="profile-actions">
                            <a href="settings.php" class="btn btn-secondary">Edit Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- OPTIMIZED Profile Content -->
        <div class="profile-content">
            <!-- Main Posts Section -->
            <div class="profile-main">
                <div class="section-header">
                    <h2 class="section-title">Posts (<?php echo count($posts); ?>)</h2>
                </div>

                <div class="posts-feed">
                    <?php if (count($posts) > 0): ?>
                        <?php foreach ($posts as $arrayIndex => $post): ?>
                            <?php
                            // CRITICAL FIX: Multiple layers of validation
                            $postId = intval($post['POSTS_ID']);
                            $uniqueKey = 'post_' . $postId . '_' . $arrayIndex; // Fallback unique identifier

                            // Additional validation
                            if ($postId <= 0) {
                                error_log("ERROR: Invalid postId in render loop: " . $postId);
                                continue;
                            }

                            // Format date and other data
                            $postDate = new DateTime($post['CREATED_AT']);
                            $formattedDate = $postDate->format('M d, Y \a\t g:i A');

                            $totalReactions = intval($post['total_reactions'] ?? 0);
                            $userReaction = $post['user_reaction'] ?? null;
                            $commentCount = intval($post['comment_count'] ?? 0);

                            // Debug output
                            error_log("Rendering post: ID={$postId}, Index={$arrayIndex}, UniqueKey={$uniqueKey}");
                            ?>

                            <!-- FIXED: Use postId with additional validation -->
                            <div class="post-card" data-post-id="<?php echo $postId; ?>" data-unique-key="<?php echo $uniqueKey; ?>">
                                <!-- Post Header -->
                                <div class="post-header">
                                    <div class="post-user-info">
                                        <img src="<?php echo htmlspecialchars($post['PROFILE_IMAGE']); ?>" alt="Profile" class="post-user-pic">
                                        <div class="post-user-details">
                                            <span class="post-user-name"><?php echo htmlspecialchars($post['NAME']); ?></span>
                                            <div class="post-meta-row">
                                                <span class="post-shared">shared a</span>
                                                <?php
                                                $fileType = 'post';
                                                if (!empty($post['IMAGE_URL'])) {
                                                    $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                                                    $fileType = in_array($fileExtension, ['mp4', 'webm', 'ogg']) ? 'video' : 'image';
                                                }
                                                ?>
                                                <span class="post-meta"><?php echo $fileType; ?></span>
                                                <span class="post-date"><?php echo $formattedDate; ?></span>
                                                <!-- DEBUG: Show post ID -->
                                                <span class="post-debug" style="color: #999; font-size: 11px;">[ID: <?php echo $postId; ?>]</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Post actions menu (edit/delete) - only for post owner -->
                                    <?php if (intval($post['USER_ID']) === intval($currentUserId)): ?>
                                        <div class="post-actions">
                                            <div class="post-actions-menu" onclick="togglePostMenu(<?php echo $postId; ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="1"></circle>
                                                    <circle cx="19" cy="12" r="1"></circle>
                                                    <circle cx="5" cy="12" r="1"></circle>
                                                </svg>
                                            </div>

                                            <div class="post-menu-dropdown" id="post-menu-<?php echo $postId; ?>">
                                                <div class="post-menu-item edit-post" onclick="editPost(<?php echo $postId; ?>, `<?php echo htmlspecialchars(addslashes($post['DESCRIPTION'] ?? ''), ENT_QUOTES); ?>`, `<?php echo htmlspecialchars($post['IMAGE_URL'] ?? '', ENT_QUOTES); ?>`)">Edit Post</div>
                                                <div class="post-menu-item delete-post" onclick="deletePost(<?php echo $postId; ?>)">Delete Post</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Post Media -->
                                <?php if (!empty($post['IMAGE_URL'])): ?>
                                    <div class="post-image">
                                        <?php
                                        $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                                        $isVideo = in_array($fileExtension, ['mp4', 'webm', 'ogg']);
                                        ?>
                                        <?php if ($isVideo): ?>
                                            <video controls width="100%">
                                                <source src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>" type="video/<?php echo $fileExtension; ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>" alt="Post Image" class="post-image-content" loading="lazy" decoding="async" onclick="openImageModal('<?php echo htmlspecialchars($post['IMAGE_URL']); ?>')">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Post Description -->
                                <?php if (!empty($post['DESCRIPTION'])): ?>
                                    <div class="post-description">
                                        <p><?php echo nl2br(htmlspecialchars($post['DESCRIPTION'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Post Stats -->
                                <div class="post-stats">
                                    <div class="post-likes-container" data-post-id="<?php echo $postId; ?>">
                                        <div class="post-likes <?php echo $userReaction ? 'active' : ''; ?>" data-user-reaction="<?php echo $userReaction ?: ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?php echo $userReaction ? 'var(--primary-color)' : 'none'; ?>" stroke="<?php echo $userReaction ? 'var(--primary-color)' : 'currentColor'; ?>" class="like-icon" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                            </svg>
                                            <?php if ($totalReactions > 0): ?>
                                                <span class="like-count" onclick="showReactionUsers(<?php echo $postId; ?>)">
                                                    <?php echo $totalReactions . ($totalReactions === 1 ? ' reaction' : ' reactions'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="like-count">0 reactions</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Food Reactions Panel -->
                                        <div class="food-reactions" id="food-reactions-<?php echo $postId; ?>">
                                            <?php
                                            $reactions = [
                                                'yummy' => ['emoji' => '🍔', 'title' => 'Yummy!'],
                                                'delicious' => ['emoji' => '🍕', 'title' => 'Delicious!'],
                                                'tasty' => ['emoji' => '🍰', 'title' => 'Tasty!'],
                                                'love' => ['emoji' => '🍲', 'title' => 'Love it!'],
                                                'amazing' => ['emoji' => '🍗', 'title' => 'Amazing!']
                                            ];
                                            ?>
                                            <?php foreach ($reactions as $type => $data): ?>
                                                <?php $isUserReaction = ($userReaction === $type) ? ' style="background-color: rgba(237, 90, 44, 0.2); transform: scale(1.1);"' : ''; ?>
                                                <div class="reaction-icon" data-reaction="<?php echo $type; ?>" data-post-id="<?php echo $postId; ?>" title="<?php echo $data['title']; ?>" onclick="handleReaction(<?php echo $postId; ?>, '<?php echo $type; ?>')" <?php echo $isUserReaction; ?>>
                                                    <span><?php echo $data['emoji']; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Comments Section -->
                                    <div class="post-comments" onclick="openCommentsModal(<?php echo $postId; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                        </svg>
                                        <?php if ($commentCount > 0): ?>
                                            <span id="comment-count-<?php echo $postId; ?>"><?php echo $commentCount . ($commentCount === 1 ? ' comment' : ' comments'); ?></span>
                                        <?php else: ?>
                                            <span id="comment-count-<?php echo $postId; ?>">0 comments</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-posts-message">
                            <p><?php echo $profileUserId === $currentUserId ? 'You haven\'t shared any posts yet.' : 'No posts shared yet.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>


            </div>
            <!-- OPTIMIZED Sidebar -->
            <div class="profile-sidebar">
                <!-- Our Recipes Section -->
                <div class="sidebar-section">
                    <div class="sidebar-section-header">
                        <h3 class="sidebar-title">Our Recipes (<?php echo count($recipes); ?>)</h3>
                    </div>

                    <div class="sidebar-content">
                        <?php if (!empty($recipes)): ?>
                            <div class="recipes-list">
                                <?php foreach ($recipes as $recipe): ?>
                                    <div class="recipe-item">
                                        <div class="recipe-image">
                                            <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL'] ?? 'images/default-recipe.jpg'); ?>"
                                                alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>" loading="lazy" decoding="async">
                                        </div>
                                        <div class="recipe-info">
                                            <h4 class="recipe-title">
                                                <a href="view-recipe.php?id=<?php echo $recipe['RECIPES_ID']; ?>">
                                                    <?php echo htmlspecialchars($recipe['TITLE']); ?>
                                                </a>
                                            </h4>
                                            <p class="recipe-category">
                                                <?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-small">
                                <p><?php echo $profileUserId === $currentUserId ? 'You haven\'t created any recipes yet' : 'No recipes shared yet'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($recipes)): ?>
                        <div class="sidebar-footer">
                            <a href="recipes.php?user=<?php echo $profileUserId; ?>" class="btn btn-outline" style="width: 100%; text-align: center; font-size: 12px; padding: 8px 12px;">View All Recipes</a>
                        </div>
                    <?php endif; ?>
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
                    <img src="<?php echo htmlspecialchars($profileUser['PROFILE_IMAGE']); ?>" alt="Your avatar" class="comment-avatar">
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
        window.ProfileDetailsConfig = { currentUserId: <?php echo json_encode($_SESSION["user_id"]); ?> };
    </script>
    <script src="<?php echo asset('assets/js/profile_details.js'); ?>" defer></script>

</html>
