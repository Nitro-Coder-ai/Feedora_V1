<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$activeNav = 'community';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>document.documentElement.classList.add('fd-js');</script>
  <meta name="description" content="Get in touch with the Feedora team — support, partnerships, press and feedback from our community of home cooks and chefs.">
  <meta name="theme-color" content="#ED5A2C">
  <title>Contact Us · Feedora</title>

  <meta property="og:type" content="website">
  <meta property="og:title" content="Contact Us · Feedora">
  <meta property="og:description" content="Questions, ideas or partnerships? Reach the Feedora team.">
  <meta property="og:url" content="<?php echo htmlspecialchars($baseUrl . '/contact.php', ENT_QUOTES); ?>">

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
          <span class="cp-eyebrow"><span class="dot"></span> We'd love to hear from you</span>
          <h1 class="cp-title">Let's talk food, <span class="accent">features &amp; feedback</span></h1>
          <p class="cp-lead">Whether you've hit a snag, have a recipe collaboration in mind, or just want to tell us what would make Feedora better — our kitchen door is always open.</p>
          <div class="cp-hero-actions">
            <a href="#contact-form" class="cp-btn cp-btn-primary">Send a message →</a>
            <a href="#faq" class="cp-btn cp-btn-ghost">Browse FAQ</a>
          </div>
        </div>
        <div class="cp-hero-visual">
          <img class="img-a" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090132/feedora/assets/vf_gordon_ramsay_5265-webp.webp" alt="Chef at work" loading="eager">
          <img class="img-b" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_600/v1782090063/feedora/assets/Rfissa-Express-6-webp.webp" alt="Plated dish" loading="eager">
          <img class="img-c" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto,w_400/v1782090138/feedora/assets/zaalouk-dip-webp.webp" alt="Dip" loading="lazy">
          <div class="cp-hero-badge"><span class="emoji">💬</span> Replies within 24h</div>
        </div>
      </div>
    </section>

    <!-- Ways to reach us -->
    <section class="cp-section">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Pick the door that fits</h2>
          <p>Different questions reach different people — choose a lane so we can route you to the right cook.</p>
        </div>
        <div class="cp-cards">
          <div class="cp-card">
            <div class="cp-card-ico">🛟</div>
            <h3>Help &amp; Support</h3>
            <p>Account issues, uploads not working, or a bug in the kitchen? Our support team gets you unstuck fast.</p>
          </div>
          <div class="cp-card">
            <div class="cp-card-ico">🤝</div>
            <h3>Partnerships</h3>
            <p>Brands, chefs and creators who want to host live sessions or feature recipes — let's cook something together.</p>
          </div>
          <div class="cp-card">
            <div class="cp-card-ico">📣</div>
            <h3>Press &amp; Media</h3>
            <p>Writing about Feedora? Grab assets, quotes and interviews from our communications team.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact form + info -->
    <section class="cp-section" id="contact-form">
      <div class="cp-wrap cp-contact-grid">
        <aside class="cp-contact-aside">
          <span class="aside-glow" aria-hidden="true"></span>
          <h3>Contact details</h3>
          <p>Prefer the direct route? Reach us on any of these — real people, no bots.</p>
          <div class="cp-contact-item">
            <div class="ci-ico">✉️</div>
            <div>
              <div class="ci-label">Email</div>
              <div class="ci-value"><a href="mailto:hello@feedora.app">hello@feedora.app</a></div>
            </div>
          </div>
          <div class="cp-contact-item">
            <div class="ci-ico">🛟</div>
            <div>
              <div class="ci-label">Support</div>
              <div class="ci-value"><a href="mailto:support@feedora.app">support@feedora.app</a></div>
            </div>
          </div>
          <div class="cp-contact-item">
            <div class="ci-ico">📍</div>
            <div>
              <div class="ci-label">Studio</div>
              <div class="ci-value">Casablanca, Morocco</div>
            </div>
          </div>
          <div class="cp-contact-item">
            <div class="ci-ico">⏱️</div>
            <div>
              <div class="ci-label">Hours</div>
              <div class="ci-value">Mon–Fri · 9:00–18:00 GMT+1</div>
            </div>
          </div>
          <div class="cp-contact-socials">
            <a href="https://web.facebook.com/profile.php?id=61576878007803" aria-label="Facebook">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
            </a>
            <a href="https://www.instagram.com/feedoracook/" aria-label="Instagram">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </a>
          </div>
        </aside>

        <form class="cp-form-card" id="contactForm" novalidate>
          <div class="cp-form-success" id="contactSuccess">✅ Thanks! Your message is on its way — we'll get back to you soon.</div>
          <div class="cp-field row">
            <div class="cp-field">
              <label for="cf-name">Full name</label>
              <input type="text" id="cf-name" name="name" placeholder="Jane Cook" required>
            </div>
            <div class="cp-field">
              <label for="cf-email">Email</label>
              <input type="email" id="cf-email" name="email" placeholder="jane@example.com" required>
            </div>
          </div>
          <div class="cp-field">
            <label for="cf-topic">What's this about?</label>
            <select id="cf-topic" name="topic">
              <option value="support">Help &amp; Support</option>
              <option value="partnership">Partnership</option>
              <option value="press">Press &amp; Media</option>
              <option value="feedback">Product feedback</option>
              <option value="other">Something else</option>
            </select>
          </div>
          <div class="cp-field">
            <label for="cf-message">Message</label>
            <textarea id="cf-message" name="message" placeholder="Tell us what's on your mind…" required></textarea>
          </div>
          <button type="submit" class="cp-btn cp-btn-primary">Send message</button>
          <p class="cp-form-note">By sending, you agree to our <a href="terms.php">Terms &amp; Privacy</a>.</p>
        </form>
      </div>
    </section>

    <!-- FAQ -->
    <section class="cp-section" id="faq">
      <div class="cp-wrap">
        <div class="cp-section-head">
          <h2>Quick answers</h2>
          <p>The things people ask us most — you might not even need to write in.</p>
        </div>
        <div class="cp-faq">
          <div class="cp-faq-item">
            <button class="cp-faq-q" type="button">Is Feedora free to use?<span class="chev">▾</span></button>
            <div class="cp-faq-a">Yes. Creating an account, browsing recipes, joining live sessions and posting to the community are all free. Some premium chef events may be ticketed.</div>
          </div>
          <div class="cp-faq-item">
            <button class="cp-faq-q" type="button">How do I host a live cooking session?<span class="chev">▾</span></button>
            <div class="cp-faq-a">Head to the Live Sessions page and request creator access. Once approved, you can schedule a session, invite followers and go live straight from your dashboard.</div>
          </div>
          <div class="cp-faq-item">
            <button class="cp-faq-q" type="button">Can I sell or license my recipes?<span class="chev">▾</span></button>
            <div class="cp-faq-a">You keep ownership of everything you post. Reach out via the Partnerships lane above and we'll walk you through monetization options.</div>
          </div>
          <div class="cp-faq-item">
            <button class="cp-faq-q" type="button">How is my data handled?<span class="chev">▾</span></button>
            <div class="cp-faq-a">We only collect what we need to run Feedora and never sell your personal data. The full breakdown lives in our <a href="terms.php#privacy">Privacy Policy</a>.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cp-wrap">
      <div class="cp-cta">
        <h2>Hungry to get cooking?</h2>
        <p>Join thousands of home cooks sharing recipes, going live and swapping tips every day.</p>
        <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'sign-up.php'; ?>" class="cp-btn cp-btn-primary">Join Feedora →</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/site_footer.php'; ?>

  <script>
    // FAQ accordion
    document.querySelectorAll('.cp-faq-q').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.cp-faq-item');
        var isOpen = item.classList.contains('open');
        document.querySelectorAll('.cp-faq-item.open').forEach(function (o) { o.classList.remove('open'); });
        if (!isOpen) item.classList.add('open');
      });
    });
    // Contact form — client-side validation + success state (no message is sent
    // to a server yet; wire this to an endpoint when a messages table exists).
    var form = document.getElementById('contactForm');
    if (form) form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      document.getElementById('contactSuccess').classList.add('show');
      form.querySelector('button[type="submit"]').textContent = 'Sent ✓';
      form.reset();
      setTimeout(function () { document.getElementById('contactSuccess').classList.remove('show'); form.querySelector('button[type="submit"]').textContent = 'Send message'; }, 6000);
    });
  </script>
</body>

</html>
