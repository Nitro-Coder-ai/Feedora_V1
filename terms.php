<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$activeNav = '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$lastUpdated = 'Updated January 2026';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>document.documentElement.classList.add('fd-js');</script>
  <meta name="description" content="Feedora's Terms of Service and Privacy Policy — the plain-language agreement covering how you use Feedora and how we handle your data.">
  <meta name="theme-color" content="#ED5A2C">
  <title>Terms &amp; Privacy · Feedora</title>

  <meta property="og:type" content="website">
  <meta property="og:title" content="Terms &amp; Privacy · Feedora">
  <meta property="og:description" content="How you use Feedora, and how we protect your data.">
  <meta property="og:url" content="<?php echo htmlspecialchars($baseUrl . '/terms.php', ENT_QUOTES); ?>">

  <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap">
  <style><?php echo file_get_contents(__DIR__ . '/fonts.min.css'); ?></style>
  <style><?php
    echo file_get_contents(__DIR__ . '/Home.min.css');
    echo file_get_contents(__DIR__ . '/assets/css/content-pages.min.css');
  ?></style>
  <link rel="icon" href="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_800/v1782090013/feedora/assets/Frame-1171277973-svg.svg" type="image/svg+xml">
</head>

<body>
  <?php include __DIR__ . '/includes/site_header.php'; ?>

  <main class="cp-main">
    <!-- Hero -->
    <section class="cp-hero">
      <div class="cp-wrap cp-hero-inner">
        <div class="cp-hero-copy">
          <span class="cp-eyebrow"><span class="dot"></span> The fine print, in plain words</span>
          <h1 class="cp-title">Terms &amp; <span class="accent">Privacy</span></h1>
          <p class="cp-lead">No dense legalese. Here's the honest agreement for using Feedora, and a clear account of what data we collect, why, and the control you keep over it.</p>
          <div class="cp-hero-actions">
            <a href="#terms" class="cp-btn cp-btn-primary" data-jump="terms">Read the Terms</a>
            <a href="#privacy" class="cp-btn cp-btn-ghost" data-jump="privacy">Read the Privacy Policy</a>
          </div>
        </div>
        <div class="cp-hero-visual">
          <img class="img-a" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090107/feedora/assets/images-webp.webp" alt="Cooking together" loading="eager">
          <img class="img-b" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090081/feedora/assets/apple-baghrir_-7-webp.webp" alt="Dish" loading="eager">
          <img class="img-c" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_400/v1782089874/feedora/assets/Cooking_1-svg.svg" alt="Cooking illustration" loading="lazy">
          <div class="cp-hero-badge"><span class="emoji">🔒</span> Your data, your rules</div>
        </div>
      </div>
    </section>

    <!-- Trust highlights -->
    <section class="cp-section" style="padding-top:16px;">
      <div class="cp-wrap">
        <div class="cp-cards">
          <div class="cp-card">
            <div class="cp-card-ico">🙅</div>
            <h3>We never sell your data</h3>
            <p>Your personal information is never sold or rented to advertisers. Full stop.</p>
          </div>
          <div class="cp-card">
            <div class="cp-card-ico">🎛️</div>
            <h3>You stay in control</h3>
            <p>Export, edit or delete your account and content whenever you want, from settings.</p>
          </div>
          <div class="cp-card">
            <div class="cp-card-ico">🛡️</div>
            <h3>Encrypted &amp; guarded</h3>
            <p>Passwords are hashed and traffic is protected. Security is built in, not bolted on.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Legal body -->
    <section class="cp-section">
      <div class="cp-wrap cp-legal">
        <aside class="cp-toc">
          <h4>On this page</h4>
          <a href="#terms" class="cp-toc-link active" data-jump="terms">Terms of Service</a>
          <a href="#privacy" class="cp-toc-link" data-jump="privacy">Privacy Policy</a>
          <a href="#cookies" class="cp-toc-link" data-jump="privacy">Cookies</a>
          <a href="#contact-legal" class="cp-toc-link" data-jump="privacy">Contact</a>
        </aside>

        <div class="cp-legal-body">
          <div class="cp-legal-tabs" role="tablist">
            <button class="cp-legal-tab active" data-tab="terms" role="tab">Terms of Service</button>
            <button class="cp-legal-tab" data-tab="privacy" role="tab">Privacy Policy</button>
          </div>

          <!-- TERMS -->
          <div class="cp-legal-panel active" id="panel-terms">
            <span class="cp-updated"><?php echo htmlspecialchars($lastUpdated); ?></span>
            <div class="cp-article" id="terms">
              <h3>1. Welcome to Feedora</h3>
              <p>Feedora is a community platform for cooking lovers to discover recipes, share their own creations, join live cooking sessions and connect with other cooks. By creating an account or using Feedora, you agree to these Terms. If you don't agree, please don't use the service.</p>

              <h3>2. Your account</h3>
              <p>You must be at least 13 years old to use Feedora. You're responsible for keeping your login details secure and for everything that happens under your account. Please give us accurate information when you sign up, and let us know if you spot any unauthorized use.</p>

              <h3>3. Content you post</h3>
              <p>You own the recipes, photos, videos and messages you create. By posting them on Feedora, you grant us a non-exclusive license to display and distribute that content within the platform so others can enjoy it. You promise that:</p>
              <ul>
                <li>The content is yours or you have the right to share it.</li>
                <li>It doesn't break any law or infringe anyone's rights.</li>
                <li>It isn't hateful, harassing, deceptive or otherwise harmful.</li>
              </ul>

              <h3>4. Community rules</h3>
              <p>Keep Feedora a welcoming kitchen. Don't spam, impersonate others, scrape the platform, or attempt to disrupt the service. We may remove content or suspend accounts that break these rules to protect the community.</p>

              <h3>5. Live sessions</h3>
              <p>When you host or join a live session, you agree to behave respectfully. Hosts are responsible for the content of their broadcasts. We may end sessions that violate our guidelines.</p>

              <h3>6. Availability &amp; changes</h3>
              <p>We work hard to keep Feedora running smoothly, but we can't promise it will always be available or bug-free. We may update, pause or retire features over time. We'll give reasonable notice of significant changes to these Terms.</p>

              <h3>7. Liability</h3>
              <p>Feedora is provided "as is." To the extent permitted by law, we aren't liable for indirect or incidental damages arising from your use of the platform. Recipes and cooking advice are shared by the community — always use your own judgment in the kitchen, especially around allergens and food safety.</p>

              <h3>8. Ending your use</h3>
              <p>You can delete your account at any time from settings. We may suspend or terminate accounts that seriously or repeatedly break these Terms.</p>
            </div>
          </div>

          <!-- PRIVACY -->
          <div class="cp-legal-panel" id="panel-privacy">
            <span class="cp-updated"><?php echo htmlspecialchars($lastUpdated); ?></span>
            <div class="cp-article" id="privacy">
              <h3>What we collect</h3>
              <p>We keep data collection lean. Depending on how you use Feedora, that may include:</p>
              <ul>
                <li><strong>Account details</strong> — your name, email and password (stored only as a secure hash).</li>
                <li><strong>Content</strong> — recipes, posts, photos, videos and messages you create.</li>
                <li><strong>Usage data</strong> — basic activity like logins and interactions, to keep the service working and improve it.</li>
              </ul>

              <h3>How we use it</h3>
              <p>Your data is used to run your account, show your content to the right people, personalize what you see, keep the platform safe, and respond when you contact us. We do not use it to build advertising profiles.</p>

              <h3 id="cookies">Cookies</h3>
              <p>We use essential cookies to keep you signed in and to protect against forged requests. We prefer the most privacy-respecting defaults and don't load non-essential trackers without your consent.</p>

              <h3>Sharing</h3>
              <p><strong>We never sell your personal data.</strong> We only share information with trusted service providers who help us operate Feedora (for example, image hosting), and only what they need to do their job. We may disclose data if legally required to.</p>

              <h3>Your rights &amp; choices</h3>
              <p>You're in control. At any time you can:</p>
              <ul>
                <li>Access and update your profile information from settings.</li>
                <li>Delete individual posts, recipes or messages.</li>
                <li>Delete your entire account, which removes your personal data.</li>
              </ul>

              <h3>Security</h3>
              <p>Passwords are hashed, state-changing requests are CSRF-protected, uploads are validated, and access is rate-limited. No system is perfectly secure, but we take reasonable measures to protect your information.</p>

              <h3 id="contact-legal">Questions?</h3>
              <p>If you have any questions about your data or these policies, reach us any time via the <a href="contact.php">Contact page</a> or at <a href="mailto:hello@feedora.app">hello@feedora.app</a>.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cp-wrap">
      <div class="cp-cta">
        <h2>Cook with confidence</h2>
        <p>Clear rules, real privacy, and a community that has your back. That's the Feedora promise.</p>
        <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'sign-up.php'; ?>" class="cp-btn cp-btn-primary">Get started →</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/site_footer.php'; ?>

  <script>
    (function () {
      var tabs = document.querySelectorAll('.cp-legal-tab');
      var panels = { terms: document.getElementById('panel-terms'), privacy: document.getElementById('panel-privacy') };
      var tocLinks = document.querySelectorAll('.cp-toc-link');
      function show(which) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === which); });
        Object.keys(panels).forEach(function (k) { if (panels[k]) panels[k].classList.toggle('active', k === which); });
        tocLinks.forEach(function (l) { l.classList.toggle('active', l.dataset.jump === which); });
      }
      tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
      // Any element with data-jump switches panel (hero buttons + TOC), then scrolls.
      document.querySelectorAll('[data-jump]').forEach(function (el) {
        el.addEventListener('click', function (e) {
          var which = el.dataset.jump;
          show(which);
          var href = el.getAttribute('href');
          if (href && href.charAt(0) === '#') {
            var target = document.querySelector(href);
            if (target) { e.preventDefault(); setTimeout(function () { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 20); }
          }
        });
      });
      // Open the right tab if the URL carries #privacy / #cookies / #contact-legal
      var hash = window.location.hash.replace('#', '');
      if (hash === 'privacy' || hash === 'cookies' || hash === 'contact-legal') show('privacy');
    })();
  </script>
</body>

</html>
