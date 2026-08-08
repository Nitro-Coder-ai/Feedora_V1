<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();
// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to the dashboard if already logged in
    header("Location: dashboard.php");
    exit();
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // reject forged cross-site logins
    // Throttle brute-force / credential-stuffing: 5 failed attempts / 15 min / IP.
    $rlKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    if (rate_limit_exceeded($rlKey, 5, 900)) {
        $_SESSION['error'] = 'Too many login attempts. Please try again in a few minutes.';
        header('Location: sign-in.php');
        exit;
    }

    // Retrieve and sanitize user inputs
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email and password are required.';
        header('Location: sign-in.php');
        exit;
    }

    try {
        // Prepare an SQL statement to find the user by email
        $stmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL, PASSWORD_HASH, ONBOARDED, EMAIL_VERIFIED FROM USERS WHERE EMAIL = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists and verify password
        if ($user && $user['PASSWORD_HASH'] !== null && password_verify($password, $user['PASSWORD_HASH'])) {
            // Gate: unverified email → don't grant access. Reissue a fresh token,
            // resend the email, and send them to the "check your inbox" page.
            if ((int) ($user['EMAIL_VERIFIED'] ?? 0) === 0) {
                $token = make_verification_token();
                $pdo->prepare("UPDATE USERS SET VERIFICATION_TOKEN = ?, VERIFICATION_EXPIRES = ? WHERE USER_ID = ?")
                    ->execute([hash_verification_token($token), date('Y-m-d H:i:s', time() + 86400), $user['USER_ID']]);
                send_verification_email($user['EMAIL'], $user['NAME'], $token);
                $_SESSION['pending_verify_email'] = $user['EMAIL'];
                $_SESSION['pending_verify_uid']   = (int) $user['USER_ID'];
                if (!mailer_configured()) $_SESSION['pending_verify_link'] = verification_link($token);
                header('Location: verify-notice.php');
                exit;
            }

            // Prevent session fixation: issue a fresh session ID on privilege change.
            session_regenerate_id(true);

            // Store user data in session
            $_SESSION['user_id'] = $user['USER_ID'];
            $_SESSION['user_name'] = $user['NAME'];
            $_SESSION['user_email'] = $user['EMAIL'];

            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE USERS SET LAST_LOGIN_AT = CURRENT_TIMESTAMP WHERE USER_ID = ?");
            $updateStmt->execute([$user['USER_ID']]);

            // First-time users complete the taste survey + follow suggestions
            // before landing on their profile.
            if ((int) ($user['ONBOARDED'] ?? 0) === 0) {
                header('Location: onboarding.php');
                exit;
            }

            // Redirect to profile page after successful login
            header('Location: profile.php');
            exit;
        } else {
            // Invalid credentials — record the failure and show a generic,
            // non-enumerating message (don't reveal whether the email exists).
            rate_limit_record($rlKey, 900);
            $_SESSION['error'] = 'Invalid email or password.';
            header('Location: sign-in.php');
            exit;
        }
    } catch (PDOException $e) {
        // Log the real error server-side; never leak DB details to the user.
        error_log('[sign-in] ' . $e->getMessage());
        $_SESSION['error'] = 'Login failed. Please try again later.';
        header('Location: sign-in.php');
        exit;
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
  <meta name="description" content="Feedora - Sign in to discover, cook & share delicious recipes">
  <meta name="theme-color" content="#ED5A2C">
  <title>Sign In - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset('Home.css'); ?>">
  <!-- Favicon -->
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('assets/css/sign-in.css'); ?>">
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
      <a href="recipes.php" class="nav-link">Recipes</a>
      <a href="live.php" class="nav-link">Live Sessions</a>
      <a href="dashboard.php" class="nav-link">Community</a>

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
      <a href="sign-up.php" class="signup-button">Sign up</a>
      <a href="sign-in.php" class="signin-button">Sign in</a>
    </div>

    <button class="mobile-menu-button menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <div class="mobile-nav mobile-menu" id="mobileNav" aria-hidden="true">
      <div class="mobile-nav-header">
        <div class="logo-container">
          <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Icon" class="mobile-logo">
        </div>
        <button class="close-menu-button" aria-label="Close menu">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <div class="mobile-nav-content">
        <div class="mobile-search">
          <input type="text" placeholder="Search" class="mobile-search-input">
          <button class="mobile-search-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
        </div>
        <nav class="mobile-nav-links">
          <a href="home.php" class="mobile-nav-link">Home</a>
          <a href="recipes.php" class="mobile-nav-link">Recipes</a>
          <a href="live.php" class="mobile-nav-link">Live Sessions</a>
          <a href="dashboard.php" class="mobile-nav-link">Community</a>
        </nav>
        <div class="mobile-auth-buttons">
          <a href="sign-up.php" class="mobile-signup-button">Sign up</a>
          <a href="sign-in.php" class="mobile-signin-button">Sign in</a>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="signin-container">
      <div class="signin-image-container">
        <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782089860/feedora/assets/Chef-webp.webp" alt="Chef illustration" class="signin-image">
      </div>
      <div class="signin-form-container">
        <h1 class="signin-title">Sign in to access your <span class="account-text">account</span></h1>
        <p class="signin-subtitle">Welcome back! We're thrilled to have you return to our platform. Your presence means a lot to us.</p>
        
        <?php if(isset($_SESSION['error'])): ?>
          <div class="error-message">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success'])): ?>
          <div class="success-message">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>
        
        <form action="sign-in.php" method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>
          
          <div class="remember-me">
            <input type="checkbox" id="remember" name="remember" value="1">
            <label for="remember">Remember for 30 days</label>
          </div>
          
          <button type="submit" class="login-button">Login</button>
        </form>

        <?php include __DIR__ . '/includes/social_login.php'; ?>

        <div class="signup-link">
          <p>Don't have an account? <a href="sign-up.php">Sign up</a></p>
        </div>
      </div>
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
          <a href="https://web.facebook.com/profile.php?id=61576878007803" class="social-link facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
            </svg>
          </a>
          <a href="https://www.instagram.com/feedoracook/" class="social-link instagram">
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
            <li><a href="home.php" class="footer-link">Home</a></li>
            <li><a href="recipes.php" class="footer-link">Recipes</a></li>
            <li><a href="live-sessions.php" class="footer-link">Live Sessions</a></li>
            <li><a href="contact.php" class="footer-link">Contact</a></li>
            <li><a href="terms.php" class="footer-link">Terms & Privacy</a></li>
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
      <p class="copyright"> 2025 Feedora All rights reserved</p>
    </div>
  </footer>
  
  <script src="<?php echo asset('Home.js'); ?>" defer></script>
</body>
</html>