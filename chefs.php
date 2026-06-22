<?php
require_once 'config/config.php';
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX follow/unfollow requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_follow') {
    header('Content-Type: application/json');
    
    $chefId = (int)$_POST['chef_id'];
    $userId = $_SESSION['user_id'];
    
    try {
        // Check if already following
        $checkStmt = $pdo->prepare("SELECT * FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $checkStmt->execute([$chefId, $userId]);
        $isFollowing = $checkStmt->fetch();

        if ($isFollowing) {
            // Unfollow
            $unfollowStmt = $pdo->prepare("DELETE FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
            $unfollowStmt->execute([$chefId, $userId]);
            
            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$chefId]);
            $newCount = $countStmt->fetch()['follower_count'];
            
            echo json_encode([
                'success' => true, 
                'action' => 'unfollowed', 
                'message' => 'Chef unfollowed successfully!',
                'new_follower_count' => $newCount
            ]);
        } else {
            // Follow
            $followStmt = $pdo->prepare("INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID) VALUES (?, ?)");
            $followStmt->execute([$chefId, $userId]);
            
            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$chefId]);
            $newCount = $countStmt->fetch()['follower_count'];
            
            echo json_encode([
                'success' => true, 
                'action' => 'followed', 
                'message' => 'Now following this chef!',
                'new_follower_count' => $newCount
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch unique chefs with their details (excluding current user and ensuring no duplicates)
$stmt = $pdo->prepare("
    SELECT DISTINCT
        u.USER_ID,
        u.NAME,
        u.PROFILE_IMAGE,
        COALESCE(recipe_data.recipe_count, 0) as recipe_count,
        COALESCE(recipe_data.specialties, '') as specialties,
        COALESCE(follower_data.follower_count, 0) as follower_count
    FROM USERS u
    LEFT JOIN (
        SELECT 
            r.USER_ID,
            COUNT(DISTINCT r.RECIPES_ID) as recipe_count,
            GROUP_CONCAT(DISTINCT c.NAME_CATEGORIE SEPARATOR ', ') as specialties
        FROM RECIPES r
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE
        GROUP BY r.USER_ID
    ) recipe_data ON u.USER_ID = recipe_data.USER_ID
    LEFT JOIN (
        SELECT 
            f.USER_ID,
            COUNT(DISTINCT f.FOLLOWER_ID) as follower_count
        FROM FOLLOWERS f
        GROUP BY f.USER_ID
    ) follower_data ON u.USER_ID = follower_data.USER_ID
    WHERE u.USER_ID != ?
    AND u.NAME IS NOT NULL 
    AND u.NAME != ''
    GROUP BY u.USER_ID, u.NAME, u.PROFILE_IMAGE
    ORDER BY follower_data.follower_count DESC, recipe_data.recipe_count DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check which chefs the current user is following
$followingStmt = $pdo->prepare("
    SELECT USER_ID FROM FOLLOWERS WHERE FOLLOWER_ID = ?
");
$followingStmt->execute([$_SESSION['user_id']]);
$following = $followingStmt->fetchAll(PDO::FETCH_COLUMN);

// Chef expertise data (demo content)
$expertiseData = [
    'Gordon Ramsay' => [
        'title' => 'British Celebrity Chef',
        'expertise' => ['Fine Dining', 'French Cuisine', 'Restaurant Management'],
        'image' => 'images/chefs/gordon-ramsay.jpg'
    ],
    'ChoumiCha Chafai' => [
        'title' => 'The Great Lady Of Moroccan cuisine',
        'expertise' => ['Moroccan Cuisine', 'Traditional Cooking', 'Pastry'],
        'image' => 'images/chefs/choumicha-chafai.jpg'
    ],
    'Massimo Bottura' => [
        'title' => 'Italian Culinary Master',
        'expertise' => ['Modern Italian', 'Culinary Innovation', 'Fine Dining'],
        'image' => 'images/chefs/massimo-bottura.jpg'
    ],
    'Heston Blumenthal' => [
        'title' => 'British Celebrity Chef',
        'expertise' => ['Molecular Gastronomy', 'Food Science', 'Experimental Cuisine'],
        'image' => 'images/chefs/heston-blumenthal.jpg'
    ],
    'René Redzepi' => [
        'title' => 'Nordic Cuisine Pioneer',
        'expertise' => ['Foraging', 'Nordic Cuisine', 'Sustainable Cooking'],
        'image' => 'images/chefs/rene-redzepi.jpg'
    ],
    'Dominique Crenn' => [
        'title' => 'French-American Chef',
        'expertise' => ['Artistic Cuisine', 'French Technique', 'Sustainable Practices'],
        'image' => 'images/chefs/dominique-crenn.jpg'
    ]
];

// Enhance chef data with expertise info and remove any remaining duplicates
$uniqueChefs = [];
$seenUserIds = [];

foreach ($chefs as &$chef) {
    // Skip if we've already processed this user ID
    if (in_array($chef['USER_ID'], $seenUserIds)) {
        continue;
    }
    $seenUserIds[] = $chef['USER_ID'];
    
    $name = $chef['NAME'];
    if (array_key_exists($name, $expertiseData)) {
        $chef['title'] = $expertiseData[$name]['title'];
        $chef['expertise'] = $expertiseData[$name]['expertise'];
        $chef['profile_image'] = $expertiseData[$name]['image'];
    } else {
        $chef['title'] = 'Culinary Enthusiast';
        $specialties = !empty($chef['specialties']) ? explode(',', $chef['specialties']) : ['Home Cooking'];
        $chef['expertise'] = array_filter(array_map('trim', $specialties), function($item) {
            return !empty($item);
        });
        if (empty($chef['expertise'])) {
            $chef['expertise'] = ['Home Cooking'];
        }
        $chef['profile_image'] = $chef['PROFILE_IMAGE'] ?? 'images/default-avatar.png';
    }
    
    // Check if current user is following this chef
    $chef['is_following'] = in_array($chef['USER_ID'], $following);
    
    $uniqueChefs[] = $chef;
}

$chefs = $uniqueChefs;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover and follow amazing chefs on Feedora">
    <meta name="theme-color" content="#ED5A2C">
    <title>Discover Chefs - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <!-- Favicon -->
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/chefs.css'); ?>">
</head>
<body>
    <?php include('sidebar.php'); ?>

        <main class="main-content">
            <?php include('header.php'); ?>
        <div class="content-container">
            <div class="section-header">
                <h2 class="section-title">Featured Chefs</h2>
            </div>
            
            <div class="chef-grid">
                <?php if (empty($chefs)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-light);">
                        <h3>No chefs found</h3>
                        <p>Be the first to create some recipes and become a featured chef!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chefs as $chef): ?>
                    <div class="chef-card" data-chef-id="<?= htmlspecialchars($chef['USER_ID']) ?>">
                        <a href="profile_details.php?id=<?= htmlspecialchars($chef['USER_ID']) ?>" class="chef-image-container">
                            <img src="<?= htmlspecialchars($chef['profile_image']) ?>" 
                                 alt="<?= htmlspecialchars($chef['NAME']) ?>" 
                                 class="chef-image"
                                 onerror="this.src='images/default-avatar.png'">
                        </a>
                        <div class="chef-details">
                            <a href="profile_details.php?id=<?= htmlspecialchars($chef['USER_ID']) ?>" class="chef-name">
                                <?= htmlspecialchars($chef['NAME']) ?>
                            </a>
                            <div class="chef-stats">
                                <div class="stat-item">
                                    <span class="stat-value follower-count" data-chef-id="<?= $chef['USER_ID'] ?>"><?= $chef['follower_count'] ?></span>
                                    <span class="stat-label">Followers</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?= $chef['recipe_count'] ?></span>
                                    <span class="stat-label">Recipes</span>
                                </div>
                            </div>
                            <button class="follow-btn <?= $chef['is_following'] ? 'following' : '' ?>" 
                                    onclick="toggleFollow(<?= $chef['USER_ID'] ?>)">
                                <?php if ($chef['is_following']): ?>
                                    Following
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6L9 17l-5-5"></path>
                                    </svg>
                                <?php else: ?>
                                    Follow
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($chefs)): ?>
            <div class="load-more-container">
                <button class="load-more-btn" id="loadMoreBtn">
                    Load More Chefs
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script>
        window.ChefsConfig = { following: <?= json_encode($following) ?> };
    </script>
    <script src="<?php echo asset('assets/js/chefs.js'); ?>" defer></script>
</body>
</html>
