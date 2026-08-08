<?php
/**
 * Public site footer shared by the standalone content pages. Mirrors the
 * Home.php footer, with the Quick Links now pointing at the real pages
 * (Contact + Terms & Privacy) instead of dead "#" anchors.
 */
?>
<footer class="footer-section">
  <div class="footer-container">
    <div class="footer-logo-section">
      <div class="footer-logo">
        <img loading="lazy" decoding="async" src="https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto,w_800/v1782089973/feedora/assets/Feedora-logo-svg.svg" alt="Feedora Logo" class="footer-logo-img">
      </div>
      <p class="footer-description">Feedora is a platform for cooking lovers to share, learn, and connect.</p>
      <div class="footer-social">
        <a href="https://web.facebook.com/profile.php?id=61576878007803" class="social-link facebook" aria-label="Visit our Facebook page">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
          </svg>
        </a>
        <a href="https://www.instagram.com/feedoracook/" class="social-link instagram" aria-label="Visit our Instagram page">
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
          <li><a href="recipes.php" class="footer-link">Recipes</a></li>
          <li><a href="live.php" class="footer-link">Live Sessions</a></li>
          <li><a href="contact.php" class="footer-link">Contact</a></li>
          <li><a href="terms.php" class="footer-link">Terms &amp; Privacy</a></li>
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

<script>
  // Minimal, self-contained mobile-menu toggle (no Home.js dependency).
  (function () {
    var toggle = document.querySelector('.menu-toggle');
    var menu = document.querySelector('.mobile-menu');
    var closeBtn = document.querySelector('.close-menu-button');
    if (!toggle || !menu) return;
    function open() { menu.classList.add('active'); toggle.setAttribute('aria-expanded', 'true'); menu.setAttribute('aria-hidden', 'false'); }
    function close() { menu.classList.remove('active'); toggle.setAttribute('aria-expanded', 'false'); menu.setAttribute('aria-hidden', 'true'); }
    toggle.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    menu.addEventListener('click', function (e) { if (e.target === menu) close(); });
  })();
</script>
