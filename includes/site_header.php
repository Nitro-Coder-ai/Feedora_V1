<?php
/**
 * Public site header (logo · nav · search · auth) shared by the standalone
 * content pages (Contact, Terms & Privacy, …). Mirrors the Home.php header so
 * the branding is identical, but is login-aware: signed-in visitors get a
 * Dashboard shortcut instead of the Sign up / Sign in pair.
 *
 * Optional before include:
 *   $activeNav — one of 'home' | 'recipes' | 'live' | 'community' to mark the
 *                current section in the nav.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$activeNav = $activeNav ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$navClass = static function (string $key) use ($activeNav): string {
    return 'nav-link' . ($activeNav === $key ? ' is-active' : '');
};
?>
<!-- Global live search (recipes + posts + people) — powered by api/search.php -->
<link rel="stylesheet" href="<?php echo asset('assets/css/search.css'); ?>">
<script src="<?php echo asset('assets/js/search.js'); ?>" defer></script>
<header>
  <div class="logo-container">
    <div class="logo-icon">
      <a href="Home.php" aria-label="Go to Feedora home" class="logo-link"><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_800/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Icon"></a>
    </div>
  </div>

  <div class="center-container">
    <a href="Home.php" class="home-link" aria-label="Home">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ed5a2c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
      </svg>
    </a>
    <a href="recipes.php" class="<?php echo $navClass('recipes'); ?>">Recipes</a>
    <a href="live.php" class="<?php echo $navClass('live'); ?>">Live Sessions</a>
    <a href="dashboard.php" class="<?php echo $navClass('community'); ?>">Community</a>

    <div class="search-container">
      <input type="text" placeholder="Search" class="search-input" aria-label="Search recipes and chefs">
      <button class="search-button" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </div>
  </div>

  <div class="auth-buttons">
    <?php if ($isLoggedIn): ?>
      <a href="dashboard.php" class="signup-button">Dashboard</a>
      <a href="profile.php" class="signin-button">Profile</a>
    <?php else: ?>
      <a href="sign-up.php" class="signup-button">Sign up</a>
      <a href="./sign-in.php" class="signin-button">Sign in</a>
    <?php endif; ?>
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
        <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_800/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Icon" class="mobile-logo">
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
        <input type="text" placeholder="Search" class="mobile-search-input" aria-label="Search recipes and chefs">
        <button class="mobile-search-button" aria-label="Search">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </button>
      </div>
      <nav class="mobile-nav-links">
        <a href="Home.php" class="mobile-nav-link">Home</a>
        <a href="recipes.php" class="mobile-nav-link">Recipes</a>
        <a href="live.php" class="mobile-nav-link">Live Sessions</a>
        <a href="dashboard.php" class="mobile-nav-link">Community</a>
      </nav>
      <div class="mobile-auth-buttons">
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php" class="mobile-signup-button">Dashboard</a>
          <a href="profile.php" class="mobile-signin-button">Profile</a>
        <?php else: ?>
          <a href="sign-up.php" class="mobile-signup-button">Sign up</a>
          <a href="sign-in.php" class="mobile-signin-button">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>
