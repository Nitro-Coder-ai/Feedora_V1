<?php
/**
 * Public preview shown at live.php for logged-out visitors. Showcases the Live
 * Sessions feature with existing Feedora chef/session imagery.
 */
$activeNav = 'live';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>document.documentElement.classList.add('fd-js');</script>
  <meta name="description" content="Join live cooking sessions on Feedora — cook along in real time with chefs and home cooks, ask questions and never miss a step.">
  <meta name="theme-color" content="#ED5A2C">
  <title>Live Sessions · Feedora</title>
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
          <span class="cp-eyebrow"><span class="dot"></span> Live Sessions</span>
          <h1 class="cp-title">Cook along, <span class="accent">live &amp; in real time</span></h1>
          <p class="cp-lead">Join chefs and home cooks as they cook on camera. Ask questions in the chat, follow every step at your own pace, and never miss the timing again.</p>
          <div class="cp-hero-actions">
            <a href="sign-up.php" class="cp-btn cp-btn-primary">Join a live session →</a>
            <a href="recipes.php" class="cp-btn cp-btn-ghost">Browse recipes</a>
          </div>
        </div>
        <div class="cp-hero-visual">
          <img class="img-a" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090132/feedora/assets/vf_gordon_ramsay_5265-webp.webp" alt="Chef live" loading="eager">
          <img class="img-b" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782089862/feedora/assets/Choumicha-Lambassadrice-de-la-cuisine-marocaine-dans-le-monde-jpg-webp.webp" alt="Chef Choumicha" loading="eager">
          <img class="img-c" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_400/v1782090063/feedora/assets/Rfissa-Express-6-webp.webp" alt="Dish" loading="lazy">
          <div class="cp-hero-badge"><span class="emoji">🔴</span> Going live daily</div>
        </div>
      </div>
    </section>

    <!-- Why live -->
    <section class="cp-section" style="padding-top:10px;">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>More than a video</h2>
          <p>Live sessions turn watching into cooking together.</p>
        </div>
        <div class="cp-cards">
          <div class="cp-card"><div class="cp-card-ico">💬</div><h3>Ask as you cook</h3><p>Type your questions into the live chat and get answers from the host mid-recipe.</p></div>
          <div class="cp-card"><div class="cp-card-ico">⏱️</div><h3>Perfect timing</h3><p>Follow along in real time so your dish is ready exactly when the chef's is.</p></div>
          <div class="cp-card"><div class="cp-card-ico">🎥</div><h3>Host your own</h3><p>Got skills to share? Go live from your dashboard and build your following.</p></div>
        </div>
      </div>
    </section>

    <!-- Upcoming / featured sessions -->
    <section class="cp-section">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>On the schedule</h2>
          <p>A glimpse of the sessions our community is tuning into.</p>
        </div>
        <div class="cp-showcase">
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge live"><span class="pulse"></span> LIVE</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090132/feedora/assets/vf_gordon_ramsay_5265-webp.webp" alt="Session"><span class="tile-cap"><span class="t">Weeknight Mains, Fast</span><span class="s">Chef Gordon · 1.2k watching</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Today 18:00</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782089862/feedora/assets/Choumicha-Lambassadrice-de-la-cuisine-marocaine-dans-le-monde-jpg-webp.webp" alt="Session"><span class="tile-cap"><span class="t">Moroccan Classics</span><span class="s">Chef Choumicha · Traditional</span></span></a>
          <a href="sign-up.php" class="cp-tile"><span class="tile-badge">Tomorrow</span><img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090107/feedora/assets/images-webp.webp" alt="Session"><span class="tile-cap"><span class="t">Baking Basics</span><span class="s">Community host · Beginner</span></span></a>
        </div>
      </div>
    </section>

    <!-- How live works -->
    <section class="cp-section">
      <div class="cp-wrap cp-split">
        <div class="cp-split-media">
          <img src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_800/v1782089986/feedora/assets/Firefly_-imagine-https-contais3-s3-amazonaws-com-public-original_images-7EqeUk5bLytzTvFhI0e-747404-webp.webp" alt="Cooking live">
        </div>
        <div class="cp-split-body">
          <h2>How a live session works</h2>
          <p>Jumping in takes seconds — no downloads, no setup.</p>
          <ul class="cp-check-list">
            <li>Pick a session from the schedule or catch one that's already live.</li>
            <li>Prep the ingredient list shared by the host before it starts.</li>
            <li>Cook along, drop questions in chat, and react in real time.</li>
            <li>Missed it? Rewatch the replay whenever you like.</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="cp-wrap">
      <div class="cp-cta">
        <h2>The kitchen goes live every day</h2>
        <p>Create a free account to join sessions, chat with chefs and host your own.</p>
        <a href="sign-up.php" class="cp-btn cp-btn-primary">Join Feedora free →</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/site_footer.php'; ?>
</body>

</html>
