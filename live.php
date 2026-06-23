<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// Get user information
$userId = $_SESSION['user_id'];
$profileImage = $_SESSION['profile_image'] ?? 'images/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Live Cooking Sessions Coming Soon">
    <meta name="theme-color" content="#ED5A2C">
    <title>Live Cooking - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:wght@400;500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
    <!-- Favicon -->
    <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/live.css'); ?>">
</head>

<body>
    <?php include('sidebar.php'); ?>
    
    <!-- Decorative Background Elements -->
    <div class="decorative-circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <!-- Dashboard Header is included dynamically via header.php inside main-content -->

    <!-- Enhanced Animated Background -->
    <div class="animated-bg">
        <div class="gradient-bg"></div>
        <!-- Food items will be added here via JavaScript -->
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <?php include('header.php'); ?>
        <div class="coming-soon-container">
            <div class="glass-card">
                <div class="coming-soon-text">
                    <h1 class="main-title">Live Cooking</h1>
                    <h2 class="subtitle">Coming Soon</h2>
                    <p class="description">
                        Get ready for an immersive culinary experience! Join live cooking sessions with professional chefs, 
                        learn new recipes, and cook along in real-time.
                    </p>
                </div>
                
                <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782089865/feedora/assets/Coming-Soon-png.png" alt="Live Cooking Coming Soon" class="coming-soon-image" 
                     onerror="this.src='images/coming-soon-placeholder.png'">
                
                <div class="feature-pills">
                    <div class="pill">🔴 Live Sessions</div>
                    <div class="pill">👨‍🍳 Expert Chefs</div>
                    <div class="pill">📚 Step-by-Step</div>
                    <div class="pill">🎯 Interactive</div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo asset('assets/js/live.js'); ?>" defer></script>
</body>

</html>