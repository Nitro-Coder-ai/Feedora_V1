<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();
csrf_verify(); // CSRF: reject forged state-changing POSTs

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = null;
$message = '';

// Get current profile image if exists
try {
    $stmt = $pdo->prepare("SELECT PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['PROFILE_IMAGE']) {
        $profileImage = $result['PROFILE_IMAGE'];
        
        // If user already has a profile image, redirect to dashboard
        // Only show this page to users who haven't set a profile image yet
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $message = 'Error retrieving profile: ' . $e->getMessage();
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $uploadDir = 'uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['profile_image'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    
    // Check for errors
    if ($fileError === 0) {
        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
        $destination = $uploadDir . $newFileName;
        
        // Move uploaded file to destination
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Update profile image in database
            try {
                $stmt = $pdo->prepare("UPDATE USERS SET PROFILE_IMAGE = ? WHERE USER_ID = ?");
                $stmt->execute([$destination, $userId]);
                $profileImage = $destination;
                $message = 'Profile image updated successfully!';
                
                // Update session with new profile image
                $_SESSION['profile_image'] = $destination;
                
                // Redirect to dashboard.php after successful profile update
                header("Location: dashboard.php");
                exit();
            } catch (PDOException $e) {
                $message = 'Error updating profile: ' . $e->getMessage();
            }
        } else {
            $message = 'Error moving uploaded file.';
        }
    } else {
        $message = 'Error uploading file. Error code: ' . $fileError;
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
  <meta name="description" content="Feedora - Set up your profile">
  <meta name="theme-color" content="#ED5A2C">
  <title>Profile Setup - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
  <!-- Favicon -->
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/profile.css'); ?>">
</head>
<body>
  <!-- Loading Screen -->
  <div id="loading-screen" aria-hidden="true">
    <div class="loader-container">
      <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090050/feedora/assets/Pizza-8-webp.webp" alt="Loading" class="loader-image">
    </div>
    <p class="visually-hidden">Loading Feedora</p>
  </div>
  
  <header>
    <div class="logo-container">
      <div class="logo-icon">
        <a href="Home.php" aria-label="Go to Feedora home" class="logo-link"><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Icon"></a>
      </div>
    </div>

    <div class="center-container">
      <a href="Home.php" class="home-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ed5a2c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        </svg>
      </a>
      <a href="/recipes" class="nav-link">Recipes</a>
      <a href="/live-sessions" class="nav-link">Live Sessions</a>
      <a href="/community" class="nav-link">Community</a>
      
      <div class="search-container">
        <input type="text" placeholder="Search" class="search-input">
        <button class="search-button">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </button>
      </div>
    </div>

    <div class="auth-buttons">
      <!-- Show user name if logged in -->
      <span><?php echo htmlspecialchars($userName); ?></span>
    </div>
  </header>

  <main>
    <div class="profile-container">
      <h1 class="profile-title">Set Up Your Profile</h1>
      <p class="profile-subtitle">Add a profile picture to personalize your account</p>
      
      <div class="profile-image-container">
        <?php if ($profileImage): ?>
          <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image" class="profile-image">
        <?php else: ?>
          <div class="profile-placeholder">
            <?php echo strtoupper(substr($userName, 0, 1)); ?>
          </div>
        <?php endif; ?>
      </div>
      
      <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success-message' : 'error-message'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      
      <form action="profile.php" method="POST" enctype="multipart/form-data" class="profile-form">
          <?php echo csrf_field(); ?>
        <div class="file-input-container">
          <input type="file" name="profile_image" id="profile_image" class="file-input" accept="image/*" onchange="updateFileName(this)">
          <label for="profile_image" class="file-input-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            &nbsp;Choose Profile Image
          </label>
          <div id="file-name" class="file-name">No file chosen</div>
        </div>
        
        <button type="submit" class="submit-button">Upload Profile Image</button>
      </form>
      
      <a href="dashboard.php" class="skip-link">Skip for now</a>
    </div>
  </main>
  
  <!-- Footer Section -->
  <footer class="footer-section">
    <div class="footer-container">
      <div class="footer-logo-section">
        <div class="footer-logo">
          <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Logo" class="footer-logo-img">
        </div>
        <p class="footer-description">Feedora is a platform for cooking lovers to share, learn, and connect.</p>
        <div class="footer-social">
          <a href="#" class="social-link facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
            </svg>
          </a>
          <a href="#" class="social-link instagram">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
              <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
            </svg>
          </a>
        </div>
      </div>
      
      <div class="footer-links-section">
        <div class="footer-links-column">
          <h3 class="footer-column-title">Quick Links</h3>
          <ul class="footer-links">
            <li><a href="Home.php" class="footer-link">Home</a></li>
            <li><a href="#" class="footer-link">Recipes</a></li>
            <li><a href="#" class="footer-link">Live Sessions</a></li>
            <li><a href="#" class="footer-link">Contact</a></li>
            <li><a href="#" class="footer-link">Terms & Privacy</a></li>
          </ul>
        </div>
        
        <div class="footer-newsletter">
          <h3 class="footer-column-title">Newsletter</h3>
          <div class="newsletter-form">
            <input type="email" placeholder="Enter your email" class="newsletter-input">
            <button class="newsletter-button">Subscribe</button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p class="copyright">©2025 Feedora All rights reserved</p>
    </div>
  </footer>
  
  <script src="<?php echo asset('Home.js'); ?>" defer></script>
    <script src="<?php echo asset('assets/js/profile.js'); ?>" defer></script>
</body>
</html>