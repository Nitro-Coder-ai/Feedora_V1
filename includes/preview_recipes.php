<?php
/**
 * Public preview shown at recipes.php for logged-out visitors (instead of an
 * abrupt redirect to sign-in). Showcases what the Recipes section offers using
 * existing Feedora imagery + the real recipe categories from the DB.
 *
 * Expects $pdo (from config.php, already required by recipes.php).
 */
$activeNav = 'recipes';
$previewCats = [];
try {
    $stmt = $pdo->query("SELECT NAME_CATEGORIE FROM CATEGORIE ORDER BY NAME_CATEGORIE");
    $previewCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $previewCats = ['Breakfast', 'Main Course', 'Desserts', 'Salads', 'Soups', 'Snacks', 'Beverages', 'Appetizers'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>document.documentElement.classList.add('fd-js');</script>
  <meta name="description" content="Discover, cook and save recipes from a global community of home cooks and chefs on Feedora.">
  <meta name="theme-color" content="#ED5A2C">
  <title>Recipes · Feedora</title>
  <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap">
  <style><?php echo file_get_contents(__DIR__ . '/../fonts.min.css'); ?></style>
  <style><?php
    echo file_get_contents(__DIR__ . '/../Home.min.css');
    echo file_get_contents(__DIR__ . '/../assets/css/content-pages.min.css');
  ?></style>
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_800/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
</head>

<body>
  <?php include __DIR__ . '/site_header.php'; ?>

  <main class="cp-main">
    <section class="cp-hero">
      <div class="cp-wrap cp-hero-inner">
        <div class="cp-hero-copy">
          <span class="cp-eyebrow"><span class="dot"></span> Recipes</span>
          <h1 class="cp-title">Thousands of dishes, <span class="accent">one tap away</span></h1>
          <p class="cp-lead">Browse step-by-step recipes from real home cooks, filter by category, difficulty and time, then save your favourites and cook them your way.</p>
          <div class="cp-hero-actions">
            <a href="sign-up.php" class="cp-btn cp-btn-primary">Start exploring →</a>
            <a href="live.php" class="cp-btn cp-btn-ghost">Watch live cooking</a>
          </div>
        </div>
        <div class="cp-hero-visual">
          <img class="img-a" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090063/feedora/assets/Rfissa-Express-6-webp.webp" alt="Rfissa dish" loading="eager">
          <img class="img-b" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090081/feedora/assets/apple-baghrir_-7-webp.webp" alt="Baghrir" loading="eager">
          <img class="img-c" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_400/v1782090138/feedora/assets/zaalouk-dip-webp.webp" alt="Zaalouk" loading="lazy">
          <div class="cp-hero-badge"><span class="emoji">⭐</span> Save &amp; cook later</div>
        </div>
      </div>
    </section>

    <!-- Real categories -->
    <section class="cp-section" style="padding-top:10px;">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Find your craving</h2>
          <p>Jump straight to the kind of dish you're in the mood for.</p>
        </div>
        <div class="cp-chips">
          <?php foreach ($previewCats as $cat): ?>
            <a href="sign-up.php" class="cp-chip"><?php echo htmlspecialchars($cat); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Showcase -->
    <section class="cp-section">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Fresh from the community</h2>
          <p>A taste of what cooks are sharing on Feedora right now.</p>
        </div>
        <div class="cp-showcase">
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Main Course</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090063/feedora/assets/Rfissa-Express-6-webp.webp" alt="Rfissa Express"><span class="tile-cap"><span class="t">Rfissa Express</span><span class="s">45 min · Serves 4</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Appetizers</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090138/feedora/assets/zaalouk-dip-webp.webp" alt="Zaalouk Dip"><span class="tile-cap"><span class="t">Zaalouk Dip</span><span class="s">25 min · Serves 6</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Breakfast</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090081/feedora/assets/apple-baghrir_-7-webp.webp" alt="Apple Baghrir"><span class="tile-cap"><span class="t">Apple Baghrir</span><span class="s">30 min · Serves 3</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Desserts</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782089993/feedora/assets/Firefly_-imagine-https-contais3-s3-amazonaws-com-public-original_images-vZcXwLUkGj0T1r4et6K-275575-webp.webp" alt="Dessert"><span class="tile-cap"><span class="t">Sweet Treats</span><span class="s">40 min · Serves 8</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Main Course</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782089986/feedora/assets/Firefly_-imagine-https-contais3-s3-amazonaws-com-public-original_images-7EqeUk5bLytzTvFhI0e-747404-webp.webp" alt="Hearty main"><span class="tile-cap"><span class="t">Hearty Mains</span><span class="s">55 min · Serves 4</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Snacks</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090068/feedora/assets/UaaovMo6khVpYfMleLpa0VBhoiQQAHCMBozIHx7x-webp.webp" alt="Snack"><span class="tile-cap"><span class="t">Quick Snacks</span><span class="s">15 min · Serves 2</span></span></a>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section class="cp-section">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Cooking made simple</h2>
          <p>From craving to plated in three easy steps.</p>
        </div>
        <div class="cp-steps">
          <div class="cp-step"><div class="num-badge">1</div><h3>Discover</h3><p>Search or browse by category, difficulty and prep time to find the perfect dish.</p></div>
          <div class="cp-step"><div class="num-badge">2</div><h3>Save</h3><p>Bookmark recipes to your collection and build your personal cookbook.</p></div>
          <div class="cp-step"><div class="num-badge">3</div><h3>Share</h3><p>Cooked something great? Post your own recipe and inspire the community.</p></div>
        </div>
      </div>
    </section>

    <section class="cp-wrap">
      <div class="cp-cta">
        <h2>Your next favourite meal is waiting</h2>
        <p>Create a free account to browse every recipe, save collections and share your own.</p>
        <a href="sign-up.php" class="cp-btn cp-btn-primary">Join Feedora free →</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/site_footer.php'; ?>
</body>

</html>
