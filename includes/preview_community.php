<?php
/**
 * Public preview shown at dashboard.php ("Community") for logged-out visitors.
 * Showcases the social feed / stories / chefs using existing imagery and, where
 * possible, real member names + avatars from the DB.
 *
 * Expects $pdo (from config.php, already required by dashboard.php).
 */
$activeNav = 'community';
$defaultAvatar = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';
$members = [];
try {
    $stmt = $pdo->query("SELECT NAME, PROFILE_IMAGE FROM USERS WHERE NAME IS NOT NULL AND NAME <> '' ORDER BY USER_ID DESC LIMIT 4");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $members = [];
}
$roles = ['Home cook', 'Recipe creator', 'Live host', 'Food lover'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>document.documentElement.classList.add('fd-js');</script>
  <meta name="description" content="Join the Feedora community — share your cooking, post stories, react to recipes and follow your favourite chefs.">
  <meta name="theme-color" content="#ED5A2C">
  <title>Community · Feedora</title>
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
          <span class="cp-eyebrow"><span class="dot"></span> Community</span>
          <h1 class="cp-title">Where cooks <span class="accent">connect &amp; share</span></h1>
          <p class="cp-lead">A living feed of recipes, stories and kitchen wins. Post your dishes, react to others, follow chefs you love and chat with cooks around the world.</p>
          <div class="cp-hero-actions">
            <a href="sign-up.php" class="cp-btn cp-btn-primary">Join the community →</a>
            <a href="recipes.php" class="cp-btn cp-btn-ghost">Browse recipes</a>
          </div>
        </div>
        <div class="cp-hero-visual">
          <img class="img-a" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090101/feedora/assets/image-asset-webp.webp" alt="Community dish" loading="eager">
          <img class="img-b" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090068/feedora/assets/UaaovMo6khVpYfMleLpa0VBhoiQQAHCMBozIHx7x-webp.webp" alt="Shared plate" loading="eager">
          <img class="img-c" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_400/v1782090086/feedora/assets/bEeEOjt3fCQGMuYywteM8WNsM7EdOWIeToNisZgy-webp.webp" alt="Dish" loading="lazy">
          <div class="cp-hero-badge"><span class="emoji">👩‍🍳</span> Real cooks, real food</div>
        </div>
      </div>
    </section>

    <!-- What you can do -->
    <section class="cp-section" style="padding-top:10px;">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Your kitchen, your people</h2>
          <p>Everything you need to share the joy of cooking and find your crowd.</p>
        </div>
        <div class="cp-cards">
          <div class="cp-card"><div class="cp-card-ico">📸</div><h3>Post your dishes</h3><p>Share photos and recipes of what you're cooking and get feedback from the community.</p></div>
          <div class="cp-card"><div class="cp-card-ico">⚡</div><h3>Stories &amp; reactions</h3><p>Drop 24-hour cooking stories and react to posts with yummy, spicy, sweet and more.</p></div>
          <div class="cp-card"><div class="cp-card-ico">💬</div><h3>Chat &amp; call</h3><p>Message cooks directly, start group chats and even hop on voice or video calls.</p></div>
        </div>
      </div>
    </section>

    <!-- Members -->
    <section class="cp-section">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Meet the cooks</h2>
          <p>Thousands of home cooks and chefs are already sharing on Feedora.</p>
        </div>
        <div class="cp-people">
          <?php if (!empty($members)): ?>
            <?php foreach ($members as $i => $m): ?>
              <?php $img = !empty($m['PROFILE_IMAGE']) ? $m['PROFILE_IMAGE'] : $defaultAvatar; ?>
              <div class="cp-person">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($m['NAME']); ?>" onerror="this.src='<?php echo $defaultAvatar; ?>'">
                <div class="name"><?php echo htmlspecialchars($m['NAME']); ?></div>
                <div class="role"><?php echo htmlspecialchars($roles[$i % count($roles)]); ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="cp-person"><img src="<?php echo $defaultAvatar; ?>" alt=""><div class="name">Feedora Cook</div><div class="role">Home cook</div></div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Feed preview split -->
    <section class="cp-section">
      <div class="cp-wrap cp-split reverse">
        <div class="cp-split-media">
          <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_800/v1782090101/feedora/assets/image-asset-webp.webp" alt="Community feed">
        </div>
        <div class="cp-split-body">
          <h2>A feed that actually inspires</h2>
          <p>No noise — just great food from people who love to cook.</p>
          <ul class="cp-check-list">
            <li>Follow chefs and friends to shape your personal feed.</li>
            <li>Save recipes straight from posts into your cookbook.</li>
            <li>React, comment and swap tips in the comments.</li>
            <li>Get notified when the cooks you follow post something new.</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="cp-wrap">
      <div class="cp-cta">
        <h2>Pull up a chair at the table</h2>
        <p>Create a free account to post, follow, react and chat with the community.</p>
        <a href="sign-up.php" class="cp-btn cp-btn-primary">Join Feedora free →</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/site_footer.php'; ?>
</body>

</html>
