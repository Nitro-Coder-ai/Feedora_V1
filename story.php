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

// Allowed story reactions (type => emoji). Server-side whitelist so clients can
// never inject arbitrary emoji; mirrored in assets/js/story.js.
$STORY_REACTIONS = [
  'love'  => '❤️',
  'yummy' => '😋',
  'tasty' => '🤤',
  'wow'   => '😮',
  'haha'  => '😂',
  'fire'  => '🔥',
];

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

// AJAX: lazily fetch viewers for one story (owner only) for the client-side viewer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_viewers') {
  header('Content-Type: application/json');

  $storyId = intval($_POST['story_id'] ?? 0);
  if ($storyId <= 0) {
    echo json_encode(['success' => false, 'viewers' => [], 'view_count' => 0]);
    exit();
  }

  // getStoryViewers already enforces that only the owner can see the viewer list
  $result = $storyManager->getStoryViewers($storyId, $userId);

  // Augment with engagement (reactions summary + replies) for the owner panel.
  if (!empty($result['success'])) {
    try {
      $rx = $pdo->prepare("
        SELECT REACTION_TYPE, REACTION_EMOJI, COUNT(*) AS cnt
        FROM STORY_REACTIONS WHERE STORY_ID = ?
        GROUP BY REACTION_TYPE, REACTION_EMOJI ORDER BY cnt DESC
      ");
      $rx->execute([$storyId]);
      $result['reactions'] = $rx->fetchAll(PDO::FETCH_ASSOC);

      $rp = $pdo->prepare("
        SELECT sr.CONTENT, sr.CREATED_AT, u.NAME, u.PROFILE_IMAGE
        FROM STORY_REPLIES sr JOIN USERS u ON sr.USER_ID = u.USER_ID
        WHERE sr.STORY_ID = ? ORDER BY sr.CREATED_AT DESC LIMIT 100
      ");
      $rp->execute([$storyId]);
      $replies = [];
      foreach ($rp->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $replies[] = [
          'name'    => $r['NAME'],
          'avatar'  => $r['PROFILE_IMAGE'],
          'content' => $r['CONTENT'],
          'time'    => date('M j, g:i A', strtotime($r['CREATED_AT'])),
        ];
      }
      $result['replies'] = $replies;
    } catch (PDOException $e) {
      error_log('story engagement fetch: ' . $e->getMessage());
      $result['reactions'] = [];
      $result['replies'] = [];
    }
  }

  echo json_encode($result);
  exit();
}

// AJAX: react to a story (set / switch / toggle-off a single emoji reaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'react') {
  header('Content-Type: application/json');

  $storyId = intval($_POST['story_id'] ?? 0);
  $type    = (string) ($_POST['reaction_type'] ?? '');
  $service = StoryFactory::createStoryService($pdo);
  $story   = $storyId > 0 ? $service->getStoryById($storyId) : null;

  if (!$story || !$service->canUserAccessStory($storyId, $userId)) {
    echo json_encode(['success' => false, 'error' => 'Story not accessible']);
    exit();
  }
  // No reacting to your own story (matches Facebook).
  if ((int) $story['USER_ID'] === (int) $userId) {
    echo json_encode(['success' => false, 'error' => 'Cannot react to your own story']);
    exit();
  }
  if (!isset($STORY_REACTIONS[$type])) {
    echo json_encode(['success' => false, 'error' => 'Invalid reaction']);
    exit();
  }

  try {
    // What does the viewer currently have?
    $cur = $pdo->prepare("SELECT REACTION_TYPE FROM STORY_REACTIONS WHERE STORY_ID = ? AND USER_ID = ?");
    $cur->execute([$storyId, $userId]);
    $existing = $cur->fetchColumn();

    if ($existing === $type) {
      // Tapping the same reaction removes it (toggle off).
      $pdo->prepare("DELETE FROM STORY_REACTIONS WHERE STORY_ID = ? AND USER_ID = ?")
          ->execute([$storyId, $userId]);
      $myReaction = null;
    } else {
      // Insert or switch the reaction.
      $pdo->prepare("
        INSERT INTO STORY_REACTIONS (STORY_ID, USER_ID, REACTION_TYPE, REACTION_EMOJI)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE REACTION_TYPE = VALUES(REACTION_TYPE), REACTION_EMOJI = VALUES(REACTION_EMOJI)
      ")->execute([$storyId, $userId, $type, $STORY_REACTIONS[$type]]);
      $myReaction = $type;

      // Notify the author + send it as a direct message (Instagram-style), so
      // the reaction lands in the author's chat conversation too.
      try {
        $pdo->prepare("
          INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID)
          VALUES (?, ?, 'story_reaction', ?, ?)
        ")->execute([$userId, (int) $story['USER_ID'], 'reacted ' . $STORY_REACTIONS[$type] . ' to your story', $storyId]);
        $pdo->prepare("
          INSERT INTO MESSAGES (SENDER_ID, RECEIVER_ID, CONTENT, IS_READ, SENT_AT)
          VALUES (?, ?, ?, 0, NOW())
        ")->execute([$userId, (int) $story['USER_ID'], 'Reacted ' . $STORY_REACTIONS[$type] . ' to your story']);
      } catch (PDOException $e) { error_log('story_reaction notif/dm: ' . $e->getMessage()); }
    }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM STORY_REACTIONS WHERE STORY_ID = ?");
    $cnt->execute([$storyId]);
    echo json_encode(['success' => true, 'my_reaction' => $myReaction, 'total' => (int) $cnt->fetchColumn()]);
  } catch (PDOException $e) {
    error_log('story react: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not save reaction']);
  }
  exit();
}

// AJAX: reply to a story (private message to the author)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
  header('Content-Type: application/json');

  $storyId = intval($_POST['story_id'] ?? 0);
  $content = trim((string) ($_POST['content'] ?? ''));
  $service = StoryFactory::createStoryService($pdo);
  $story   = $storyId > 0 ? $service->getStoryById($storyId) : null;

  if (!$story || !$service->canUserAccessStory($storyId, $userId)) {
    echo json_encode(['success' => false, 'error' => 'Story not accessible']);
    exit();
  }
  if ((int) $story['USER_ID'] === (int) $userId) {
    echo json_encode(['success' => false, 'error' => 'Cannot reply to your own story']);
    exit();
  }
  if ($content === '') {
    echo json_encode(['success' => false, 'error' => 'Empty reply']);
    exit();
  }
  if (mb_strlen($content) > 1000) {
    $content = mb_substr($content, 0, 1000);
  }

  try {
    $pdo->prepare("INSERT INTO STORY_REPLIES (STORY_ID, USER_ID, CONTENT) VALUES (?, ?, ?)")
        ->execute([$storyId, $userId, $content]);

    // Deliver the reply straight to the author's chat as a direct message, so
    // story replies become a real conversation (Facebook/Instagram behaviour).
    try {
      $pdo->prepare("
        INSERT INTO MESSAGES (SENDER_ID, RECEIVER_ID, CONTENT, IS_READ, SENT_AT)
        VALUES (?, ?, ?, 0, NOW())
      ")->execute([$userId, (int) $story['USER_ID'], '↩️ Replied to your story: ' . $content]);
    } catch (PDOException $e) { error_log('story_reply dm: ' . $e->getMessage()); }

    try {
      $preview = mb_strlen($content) > 60 ? mb_substr($content, 0, 57) . '…' : $content;
      $pdo->prepare("
        INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID)
        VALUES (?, ?, 'story_reply', ?, ?)
      ")->execute([$userId, (int) $story['USER_ID'], 'replied to your story: ' . $preview, $storyId]);
    } catch (PDOException $e) { error_log('story_reply notif: ' . $e->getMessage()); }

    echo json_encode(['success' => true]);
  } catch (PDOException $e) {
    error_log('story reply: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not send reply']);
  }
  exit();
}

// FIXED: Enhanced story submission with proper notification system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  $visibility = $_POST['visibility'] ?? 'public';

  // When an upload exceeds PHP's post_max_size, PHP silently discards the ENTIRE
  // request body — $_POST and $_FILES come back empty. This is the classic reason
  // a video "uploads" but never reaches the database. Detect it and say so clearly.
  $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
  $postMax = trim((string) ini_get('post_max_size'));
  $postMaxBytes = (int) $postMax;
  switch (strtolower(substr($postMax, -1))) {
    case 'g': $postMaxBytes *= 1024; // fall through
    case 'm': $postMaxBytes *= 1024; // fall through
    case 'k': $postMaxBytes *= 1024;
  }
  if (empty($_POST) && empty($_FILES) && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    $_SESSION['story_message'] = "That file is too large for the server to accept. Please upload a smaller video.";
    $_SESSION['story_success'] = false;
    header("Location: dashboard.php");
    exit();
  }

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

// ── Story viewing: build the FULL playlist for a Facebook-style client-side viewer ──
// The viewer transitions between stories and users entirely in the browser (no page
// reloads), so we hand it the complete ordered dataset up front.
const DEFAULT_AVATAR = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

$playlist = [];          // [ { userId, userName, profileImage, isOwner, stories: [ {...} ] }, ... ]
$startUserIndex = 0;
$startStoryIndex = 0;
$hasViewing = false;

if (isset($_GET['id']) || isset($_GET['user_id'])) {
  $hasViewing = true;
  try {
    $storyService = StoryFactory::createStoryService($pdo);

    // Users that have >=1 story VISIBLE to the viewer (own + public + friends-of
    // followers), most-recently-active first. Visibility is enforced in SQL.
    $activeUserIds = $storyService->getActiveStoryUserIds($userId);

    foreach ($activeUserIds as $uid) {
      // Only the stories this viewer is allowed to see for that user.
      $stories = $storyService->getVisibleUserStories($uid, $userId);
      if (empty($stories)) {
        continue;
      }

      $items = [];
      foreach ($stories as $st) {
        $items[] = [
          'storyId'   => (int) $st['STORIES_ID'],
          'imageUrl'  => $st['IMAGE_URL'],
          // Robust media detection straight from the DB column, never the file extension.
          'mediaType' => (($st['MEDIA_TYPE'] ?? 'image') === 'video') ? 'video' : 'image',
          'createdAt' => $st['CREATED_AT'],
          'timeLabel' => date('M j, g:i A', strtotime($st['CREATED_AT'])),
        ];
      }

      $playlist[] = [
        'userId'       => (int) $uid,
        'userName'     => $stories[0]['NAME'] ?? 'User',
        'profileImage' => !empty($stories[0]['PROFILE_IMAGE']) ? $stories[0]['PROFILE_IMAGE'] : DEFAULT_AVATAR,
        'isOwner'      => ((int) $uid === (int) $userId),
        'stories'      => $items,
      ];
    }

    if (empty($playlist)) {
      header("Location: dashboard.php");
      exit();
    }

    // Preload the viewer's own reaction per story so the bar highlights instantly.
    $allStoryIds = [];
    foreach ($playlist as $u) {
      foreach ($u['stories'] as $st) { $allStoryIds[] = $st['storyId']; }
    }
    $myReactions = [];
    if (!empty($allStoryIds)) {
      $ph = implode(',', array_fill(0, count($allStoryIds), '?'));
      $rstmt = $pdo->prepare("SELECT STORY_ID, REACTION_TYPE FROM STORY_REACTIONS WHERE USER_ID = ? AND STORY_ID IN ($ph)");
      $rstmt->execute(array_merge([$userId], $allStoryIds));
      foreach ($rstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $myReactions[(int) $r['STORY_ID']] = $r['REACTION_TYPE'];
      }
    }
    foreach ($playlist as &$u) {
      foreach ($u['stories'] as &$st) {
        $st['myReaction'] = $myReactions[$st['storyId']] ?? null;
      }
      unset($st);
    }
    unset($u);

    // Resolve the starting position from the request.
    if (isset($_GET['id'])) {
      $targetId = intval($_GET['id']);
      foreach ($playlist as $ui => $u) {
        foreach ($u['stories'] as $si => $st) {
          if ($st['storyId'] === $targetId) {
            $startUserIndex = $ui;
            $startStoryIndex = $si;
            break 2;
          }
        }
      }
    } else {
      $targetUser = intval($_GET['user_id']);
      $position = $_GET['position'] ?? 'first';
      foreach ($playlist as $ui => $u) {
        if ($u['userId'] === $targetUser) {
          $startUserIndex = $ui;
          $startStoryIndex = ($position === 'last') ? count($u['stories']) - 1 : 0;
          break;
        }
      }
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

  <?php if ($hasViewing && !empty($playlist)): ?>
    <!-- Facebook-style viewer: a single stage that the JS engine renders & re-renders
         in place. Backdrop = blurred current media; foreground = centered portrait card. -->
    <div class="story-viewer" id="storyViewer">
      <div class="story-backdrop" id="storyBackdrop"></div>

      <button class="story-close" id="storyClose" aria-label="Close stories">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="6" y1="6" x2="18" y2="18"></line>
          <line x1="18" y1="6" x2="6" y2="18"></line>
        </svg>
      </button>

      <!-- Stage card -->
      <div class="story-stage" id="storyStage">
        <!-- Progress segments -->
        <div class="story-progress-container" id="storyProgress"></div>

        <!-- Header: avatar + name + time -->
        <div class="story-header">
          <div class="story-user-info">
            <div class="story-user-avatar">
              <img id="storyAvatar" src="<?php echo htmlspecialchars(DEFAULT_AVATAR); ?>" alt="User"
                   onerror="this.src='<?php echo htmlspecialchars(DEFAULT_AVATAR); ?>'">
            </div>
            <div class="story-user-details">
              <div class="story-user-name" id="storyName">User</div>
              <div class="story-user-time" id="storyTime"></div>
            </div>
          </div>
          <button class="story-pause-btn" id="storyPause" aria-label="Pause">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1"></rect><rect x="14" y="5" width="4" height="14" rx="1"></rect></svg>
          </button>
        </div>

        <!-- Media (image or video injected here) -->
        <div class="story-media" id="storyMedia"></div>

        <!-- Tap zones for prev / next -->
        <div class="story-tap story-tap-prev" id="tapPrev" aria-label="Previous"></div>
        <div class="story-tap story-tap-next" id="tapNext" aria-label="Next"></div>

        <!-- Loading spinner -->
        <div class="story-loading" id="storyLoading" style="display:none;">
          <div class="loading-spinner"></div>
        </div>

        <!-- Owner-only footer: activity (views + reactions + replies) toggle -->
        <button class="story-viewers-toggle" id="viewersToggle" style="display:none;" aria-label="See activity">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
          </svg>
          <span id="viewersToggleCount">0</span>
        </button>

        <!-- Owner-only: always-visible reactions summary on the story view -->
        <div class="story-react-summary" id="storyReactSummary" style="display:none;"></div>

        <!-- Viewer-only footer: quick emoji reactions + reply box -->
        <div class="story-interact" id="storyInteract" style="display:none;">
          <div class="story-reactions" id="storyReactions">
            <?php foreach ($STORY_REACTIONS as $rtype => $remoji): ?>
              <button type="button" class="story-react-btn" data-type="<?php echo htmlspecialchars($rtype, ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($rtype, ENT_QUOTES); ?>"><?php echo $remoji; ?></button>
            <?php endforeach; ?>
          </div>
          <form class="story-reply" id="storyReplyForm" autocomplete="off">
            <input type="text" id="storyReplyInput" class="story-reply-input" placeholder="Reply to story…" maxlength="1000">
            <button type="submit" class="story-reply-send" aria-label="Send reply">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
          </form>
          <div class="story-react-flash" id="storyReactFlash" aria-hidden="true"></div>
        </div>
      </div>

      <!-- Slide-up activity sheet (owner only): reactions + replies + viewers -->
      <div class="story-viewers-sheet" id="viewersSheet">
        <div class="viewers-sheet-handle"></div>
        <div class="viewers-header">
          <h3 class="viewers-title">Story activity</h3>
          <div class="viewers-count" id="viewersSheetCount">0</div>
        </div>
        <div class="viewers-reactions" id="viewersReactions"></div>
        <div class="viewers-replies" id="viewersReplies"></div>
        <div class="viewers-list" id="viewersList"></div>
      </div>
    </div>

    <script>
      window.StoryConfig = {
        playlist: <?php echo json_encode($playlist); ?>,
        startUserIndex: <?php echo json_encode($startUserIndex); ?>,
        startStoryIndex: <?php echo json_encode($startStoryIndex); ?>,
        userId: <?php echo json_encode($userId); ?>,
        defaultAvatar: <?php echo json_encode(DEFAULT_AVATAR); ?>,
        reactionEmojis: <?php echo json_encode($STORY_REACTIONS); ?>,
        imageDuration: 5000
      };
    </script>
    <script src="<?php echo asset('assets/js/story.js'); ?>" defer></script>

  <?php else: ?>
    <div class="story-empty">
      <div class="story-empty-card">
        <div style="font-size: 64px; margin-bottom: 20px;">📱</div>
        <h2 style="margin-bottom: 15px;">Story not found</h2>
        <p style="margin-bottom: 25px; opacity: 0.8;">The story you're looking for doesn't exist or has expired.</p>
        <a href="dashboard.php" class="story-empty-back">← Back to Dashboard</a>
      </div>
    </div>
  <?php endif; ?>

</body>

</html>
