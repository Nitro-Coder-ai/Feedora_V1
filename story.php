<?php
// FIXED story.php - Proper story notification system

// Include the database connection script
require_once 'config/config.php';
require_once 'includes/StoryRepository.php';
require_once 'includes/StoryService.php';
require_once 'includes/StoryFileHandler.php';
require_once 'includes/StoryFactory.php';
require_once 'includes/StoryManager.php';
require_once 'notification_utils.php'; // FIXED: Include at the top

// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: sign-in.php");
  exit();
}

// Initialize story manager with clean architecture
$storyManager = StoryFactory::createStoryManager($pdo);
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$profileImage = $_SESSION['profile_image'] ?? null;

// ENHANCED: Handle AJAX request for individual story view recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_individual_view') {
  header('Content-Type: application/json');

  $storyId = intval($_POST['story_id'] ?? 0);

  if ($storyId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit();
  }

  $result = $storyManager->recordIndividualView($storyId, $userId);
  echo json_encode($result);
  exit();
}

// FIXED: Enhanced story submission with proper notification system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  $visibility = $_POST['visibility'] ?? 'public';

  // Check if files were uploaded
  if (!isset($_FILES['story_media']) || empty($_FILES['story_media']['name'])) {
    $_SESSION['story_message'] = "Please select at least one file to upload.";
    $_SESSION['story_success'] = false;
    header("Location: dashboard.php");
    exit();
  }

  // Handle upload using story manager
  $uploadResult = $storyManager->handleUpload($userId, $_FILES['story_media'], $visibility);

  // Set session messages
  $_SESSION['story_message'] = $uploadResult['message'];
  $_SESSION['story_success'] = $uploadResult['success'];

  // FIXED: Create notifications for successful story uploads
  if ($uploadResult['success'] && $uploadResult['uploaded_count'] > 0) {
    try {
      error_log("📸 FIXED: Starting story notification process for user ID: $userId");
      
      // Get user info for logging
      $userStmt = $pdo->prepare("SELECT NAME FROM USERS WHERE USER_ID = ?");
      $userStmt->execute([$userId]);
      $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
      $notificationUserName = $userData['NAME'] ?? 'Someone';
      
      error_log("📸 User $notificationUserName uploaded {$uploadResult['uploaded_count']} stories");

      // FIXED: Get the actual story IDs from the database (most recent stories)
      $storyStmt = $pdo->prepare("SELECT STORIES_ID FROM STORIES WHERE USER_ID = ? ORDER BY CREATED_AT DESC LIMIT ?");
      $storyStmt->execute([$userId, $uploadResult['uploaded_count']]);
      $recentStories = $storyStmt->fetchAll(PDO::FETCH_COLUMN);

      if (!empty($recentStories)) {
        error_log("📸 Found " . count($recentStories) . " recent story IDs: " . implode(', ', $recentStories));
        
        // FIXED: Notify followers for each uploaded story using the existing function
        foreach ($recentStories as $storyId) {
          $notificationResult = notifyFollowersNewStory($pdo, $storyId, $userId);
          
          if ($notificationResult) {
            error_log("✅ FIXED: Story notification sent successfully for story ID: $storyId");
          } else {
            error_log("❌ FIXED: Failed to send story notification for story ID: $storyId");
          }
        }
        
        error_log("✅ FIXED: Story notification process completed for user: $notificationUserName");
        
      } else {
        error_log("❌ FIXED: No recent stories found in database for user ID: $userId");
      }
      
    } catch (Exception $e) {
      error_log("❌ FIXED: Story notification error: " . $e->getMessage());
      error_log("   User ID: $userId, Upload count: " . ($uploadResult['uploaded_count'] ?? 0));
    }
  }

  header("Location: dashboard.php");
  exit();
}

// Handle story viewing with proper individual story loading
$currentStory = null;
$userStories = [];
$currentIndex = 0;
$viewCount = 0;
$viewers = [];
$isOwner = false;

if (isset($_GET['id']) || isset($_GET['user_id'])) {
  try {
    if (isset($_GET['id'])) {
      // View specific story
      $storyId = intval($_GET['id']);
      $viewResult = $storyManager->handleStoryView($storyId, $userId);

      if (!$viewResult['success']) {
        $_SESSION['story_message'] = $viewResult['message'];
        $_SESSION['story_success'] = false;
        header("Location: dashboard.php");
        exit();
      }

      $currentStory = $viewResult['story'];
      $userStories = $viewResult['user_stories'];
      $currentIndex = $viewResult['current_index'];
      
      // ENHANCED: Get story owner information for profile display
      $ownerStmt = $pdo->prepare("
        SELECT u.NAME, u.PROFILE_IMAGE, s.CREATED_AT, s.USER_ID
        FROM STORIES s 
        JOIN USERS u ON s.USER_ID = u.USER_ID 
        WHERE s.STORIES_ID = ?
      ");
      $ownerStmt->execute([$storyId]);
      $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($ownerInfo) {
        $currentStory['NAME'] = $ownerInfo['NAME'];
        $currentStory['PROFILE_IMAGE'] = $ownerInfo['PROFILE_IMAGE'];
        $currentStory['CREATED_AT'] = $ownerInfo['CREATED_AT'];
      }
      
    } else {
      // View first story of a user
      $storyUserId = intval($_GET['user_id']);
      $storyService = StoryFactory::createStoryService($pdo);
      $userStories = $storyService->getUserStories($storyUserId);

      if (empty($userStories)) {
        header("Location: dashboard.php");
        exit();
      }

      $currentStory = $userStories[0];
      $currentIndex = 0;

      // ENHANCED: Get story owner information for profile display
      $ownerStmt = $pdo->prepare("
        SELECT u.NAME, u.PROFILE_IMAGE 
        FROM USERS u 
        WHERE u.USER_ID = ?
      ");
      $ownerStmt->execute([$storyUserId]);
      $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($ownerInfo) {
        $currentStory['NAME'] = $ownerInfo['NAME'];
        $currentStory['PROFILE_IMAGE'] = $ownerInfo['PROFILE_IMAGE'];
      }

      // Record view for the first story
      $storyManager->recordIndividualView($currentStory['STORIES_ID'], $userId);
    }

    // Get viewers and view count for current story
    $isOwner = ($userId == $currentStory['USER_ID']);

    if ($isOwner) {
      $viewersResult = $storyManager->getStoryViewers($currentStory['STORIES_ID'], $userId);
      $viewers = $viewersResult['viewers'] ?? [];
      $viewCount = $viewersResult['view_count'] ?? 0;
    } else {
      $storyService = StoryFactory::createStoryService($pdo);
      $viewCount = $storyService->getStoryViewCount($currentStory['STORIES_ID']);
    }
  } catch (Exception $e) {
    error_log("Story viewing error: " . $e->getMessage());
    $_SESSION['story_message'] = 'Error viewing story: ' . $e->getMessage();
    $_SESSION['story_success'] = false;
    header("Location: dashboard.php");
    exit();
  }
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
  <meta name="description" content="Feedora - View Story">
  <meta name="theme-color" content="#ED5A2C">
  <title>Story - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/story.css'); ?>">
</head>

<body>

  <?php if ($currentStory): ?>
    <div class="story-container">
      <!-- ENHANCED Progress Bars -->
      <div class="story-progress-container">
        <?php for ($i = 0; $i < count($userStories); $i++): ?>
          <div class="story-progress-bar <?php
                                          echo $i < $currentIndex ? 'viewed' : '';
                                          echo $i === $currentIndex ? ' active' : '';
                                          ?>" data-index="<?php echo $i; ?>">
            <div class="story-progress-fill"></div>
          </div>
        <?php endfor; ?>
      </div>

      <!-- NEW: User Profile Info in Top Left -->
      <div class="story-user-info">
        <div class="story-user-avatar">
          <img src="<?php echo htmlspecialchars($currentStory['PROFILE_IMAGE'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'); ?>" 
               alt="<?php echo htmlspecialchars($currentStory['NAME'] ?? 'User'); ?>"
               onerror="this.src='https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'">
        </div>
        <div class="story-user-details">
          <div class="story-user-name"><?php echo htmlspecialchars($currentStory['NAME'] ?? 'User'); ?></div>
          <div class="story-user-time"><?php echo date('M j, g:i A', strtotime($currentStory['CREATED_AT'])); ?></div>
        </div>
      </div>

      <!-- Close Button -->
      <div class="story-close" onclick="closeStory()">×</div>

      <!-- Story Content -->
      <div class="story-content" id="mediaContainer">
        <?php
        $fileExtension = strtolower(pathinfo($currentStory['IMAGE_URL'], PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'webm', 'ogg', 'avi'];

        if (in_array($fileExtension, $videoExtensions)):
        ?>
          <video class="story-video" id="storyVideo" controls>
            <source src="<?php echo htmlspecialchars($currentStory['IMAGE_URL']); ?>" type="video/<?php echo $fileExtension; ?>">
            Your browser does not support the video tag.
          </video>
        <?php else: ?>
          <img src="<?php echo htmlspecialchars($currentStory['IMAGE_URL']); ?>"
            alt="Story" class="story-image" id="storyImage">
        <?php endif; ?>
      </div>

      <!-- ENHANCED Navigation -->
      <?php if ($currentIndex > 0): ?>
        <div class="story-nav story-nav-prev" onclick="previousStory()"></div>
      <?php endif; ?>

      <?php if ($currentIndex < count($userStories) - 1): ?>
        <div class="story-nav story-nav-next" onclick="nextStory()"></div>
      <?php endif; ?>

      <!-- NEW: Bottom Left Viewers Panel -->
      <?php if ($isOwner): ?>
        <div class="story-viewers-panel">
          <div class="viewers-header">
            <div class="viewers-icon">👁️</div>
            <h3 class="viewers-title">Viewers</h3>
            <div class="viewers-count"><?php echo $viewCount; ?></div>
          </div>
          <div class="viewers-list">
            <?php if (empty($viewers)): ?>
              <div class="no-viewers">
                <div class="no-viewers-icon">👁️</div>
                <p class="no-viewers-text">No viewers yet</p>
                <p class="no-viewers-subtext">Share your story to get more views!</p>
              </div>
            <?php else: ?>
              <?php foreach ($viewers as $viewer): ?>
                <div class="viewer-item">
                  <img src="<?php echo htmlspecialchars($viewer['PROFILE_IMAGE'] ?? 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg'); ?>"
                    alt="Viewer" class="viewer-avatar">
                  <div class="viewer-info">
                    <div class="viewer-name"><?php echo htmlspecialchars($viewer['NAME']); ?></div>
                    <div class="viewer-time"><?php echo date('M j, g:i A', strtotime($viewer['VIEWED_AT'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Loading State -->
      <div class="story-loading" id="storyLoading" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading story...</p>
      </div>
    </div>

    <script>
        window.StoryConfig = {
            stories: <?php echo json_encode($userStories); ?>,
            currentIndex: <?php echo json_encode($currentIndex); ?>,
            userId: <?php echo json_encode($userId); ?>,
            currentStoryId: <?php echo json_encode($currentStory["STORIES_ID"] ?? null); ?>
        };
    </script>
    <script src="<?php echo asset('assets/js/story.js'); ?>" defer></script>

  <?php else: ?>
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background: linear-gradient(135deg, #000, #1a1a1a); color: white;">
      <div style="text-align: center; backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);">
        <div style="font-size: 64px; margin-bottom: 20px;">📱</div>
        <h2 style="margin-bottom: 15px;">Story not found</h2>
        <p style="margin-bottom: 25px; opacity: 0.8;">The story you're looking for doesn't exist or has expired.</p>
        <a href="dashboard.php" style="color: #ED5A2C; text-decoration: none; background: rgba(237,90,44,0.2); padding: 12px 24px; border-radius: 25px; border: 1px solid rgba(237,90,44,0.3); transition: all 0.3s ease;" onmouseover="this.style.background='rgba(237,90,44,0.3)'" onmouseout="this.style.background='rgba(237,90,44,0.2)'">
          ← Back to Dashboard
        </a>
      </div>
    </div>
  <?php endif; ?>

</body>

</html>
