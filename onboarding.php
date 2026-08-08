<?php
// Onboarding: food-interest survey + Facebook-style follow recommendations.
require_once 'config/config.php';
require_once 'includes/recommendations.php';
session_start();
csrf_verify();

if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit();
}
$userId = (int) $_SESSION['user_id'];
$DEFAULT_AVATAR = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

// ── POST: save survey selections ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_interests') {
    $categories = array_values(array_unique(array_map('intval', (array) ($_POST['categories'] ?? []))));
    $cuisines   = array_values(array_unique(array_map('intval', (array) ($_POST['cuisines'] ?? []))));

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM USER_CATEGORIES WHERE USER_ID = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM USER_CUISINES WHERE USER_ID = ?')->execute([$userId]);

        if ($categories) {
            $ins = $pdo->prepare('INSERT IGNORE INTO USER_CATEGORIES (USER_ID, ID_CATEGORIE) VALUES (?, ?)');
            foreach ($categories as $c) { $ins->execute([$userId, $c]); }
        }
        if ($cuisines) {
            $ins = $pdo->prepare('INSERT IGNORE INTO USER_CUISINES (USER_ID, CUISINE_ID) VALUES (?, ?)');
            foreach ($cuisines as $c) { $ins->execute([$userId, $c]); }
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('[onboarding] save_interests: ' . $e->getMessage());
    }
    header('Location: onboarding.php?step=follow');
    exit();
}

// ── POST: finish onboarding ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish') {
    try {
        $pdo->prepare('UPDATE USERS SET ONBOARDED = 1 WHERE USER_ID = ?')->execute([$userId]);
    } catch (PDOException $e) {
        error_log('[onboarding] finish: ' . $e->getMessage());
    }
    $_SESSION['onboarded'] = true;
    header('Location: dashboard.php');
    exit();
}

$step = ($_GET['step'] ?? 'interests') === 'follow' ? 'follow' : 'interests';

// Data for whichever step we render.
$categoriesList = [];
$cuisinesList = [];
$recommendations = [];
try {
    if ($step === 'interests') {
        $categoriesList = $pdo->query('SELECT ID_CATEGORIE, NAME_CATEGORIE FROM CATEGORIE ORDER BY NAME_CATEGORIE')->fetchAll(PDO::FETCH_ASSOC);
        $cuisinesList   = $pdo->query('SELECT CUISINE_ID, NAME FROM CUISINES ORDER BY NAME')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $recommendations = getFollowRecommendations($pdo, $userId, 30);
    }
} catch (PDOException $e) {
    error_log('[onboarding] load: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <script src="<?php echo asset('assets/js/csrf.js'); ?>"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Set up your Feedora taste profile">
  <meta name="theme-color" content="#ED5A2C">
  <title>Welcome to Feedora</title>
  <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo asset('fonts.css'); ?>">
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?php echo asset('assets/css/onboarding.css'); ?>">
</head>
<body data-default-avatar="<?php echo htmlspecialchars($DEFAULT_AVATAR, ENT_QUOTES); ?>">

  <div class="ob-wrap">
    <header class="ob-header">
      <img class="ob-logo" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora">
      <div class="ob-steps">
        <span class="ob-dot <?php echo $step === 'interests' ? 'active' : 'done'; ?>">1</span>
        <span class="ob-line"></span>
        <span class="ob-dot <?php echo $step === 'follow' ? 'active' : ''; ?>">2</span>
      </div>
    </header>

    <?php if ($step === 'interests'): ?>
      <main class="ob-card">
        <h1 class="ob-title">What do you love to cook &amp; eat?</h1>
        <p class="ob-subtitle">Pick a few — we'll use these to recommend chefs and recipes for you.</p>

        <form action="onboarding.php" method="POST" id="interestsForm">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save_interests">

          <section class="ob-section">
            <h2 class="ob-section-title">Food categories</h2>
            <div class="ob-chips">
              <?php foreach ($categoriesList as $c): ?>
                <label class="ob-chip">
                  <input type="checkbox" name="categories[]" value="<?php echo (int) $c['ID_CATEGORIE']; ?>">
                  <span><?php echo htmlspecialchars($c['NAME_CATEGORIE']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="ob-section">
            <h2 class="ob-section-title">Cuisines &amp; diets</h2>
            <div class="ob-chips">
              <?php foreach ($cuisinesList as $c): ?>
                <label class="ob-chip">
                  <input type="checkbox" name="cuisines[]" value="<?php echo (int) $c['CUISINE_ID']; ?>">
                  <span><?php echo htmlspecialchars($c['NAME']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <div class="ob-actions">
            <button type="submit" class="ob-btn ob-btn-primary">Continue</button>
          </div>
        </form>
      </main>

    <?php else: ?>
      <main class="ob-card">
        <h1 class="ob-title">People you may like to follow</h1>
        <p class="ob-subtitle">Based on your tastes and the Feedora community. Follow a few to fill your feed.</p>

        <?php if (empty($recommendations)): ?>
          <p class="ob-empty">No other chefs to suggest yet — you're an early bird! 🐣</p>
        <?php else: ?>
          <div class="ob-people">
            <?php foreach ($recommendations as $r): ?>
              <div class="ob-person" data-user-id="<?php echo (int) $r['USER_ID']; ?>">
                <img class="ob-person-avatar"
                     src="<?php echo htmlspecialchars(!empty($r['PROFILE_IMAGE']) ? $r['PROFILE_IMAGE'] : $DEFAULT_AVATAR); ?>"
                     alt="<?php echo htmlspecialchars($r['NAME'] ?? 'Chef'); ?>"
                     onerror="this.src='<?php echo htmlspecialchars($DEFAULT_AVATAR, ENT_QUOTES); ?>'">
                <div class="ob-person-name"><?php echo htmlspecialchars($r['NAME'] ?? 'Chef'); ?></div>
                <div class="ob-person-meta">
                  <?php
                    $shared = (int) $r['shared_cats'] + (int) $r['shared_cuisines'];
                    if ($shared > 0) {
                        echo $shared . ' shared interest' . ($shared > 1 ? 's' : '');
                    } else {
                        $fc = (int) $r['followers_count'];
                        echo $fc > 0 ? ($fc . ' follower' . ($fc > 1 ? 's' : '')) : 'New to Feedora';
                    }
                  ?>
                </div>
                <button type="button" class="ob-follow-btn" data-following="0">Follow</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="ob-actions ob-actions-split">
          <a class="ob-btn ob-btn-ghost" href="onboarding.php?step=interests">← Edit interests</a>
          <form action="onboarding.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="finish">
            <button type="submit" class="ob-btn ob-btn-primary">Finish &amp; explore Feedora</button>
          </form>
        </div>
      </main>
    <?php endif; ?>
  </div>

  <script src="<?php echo asset('assets/js/onboarding.js'); ?>" defer></script>
</body>
</html>
