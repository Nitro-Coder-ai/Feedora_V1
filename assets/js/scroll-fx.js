/**
 * Feedora scroll effects.
 *  1. Section reveal — fade + slide-up as each <section>/footer enters the
 *     viewport (IntersectionObserver), once.
 *  2. Auto-hiding header — slides the header up while scrolling DOWN, slides it
 *     back into view on the first upward scroll. Always visible near the top.
 *
 * Resilient: if IntersectionObserver is unavailable, or after a safety timeout,
 * everything is revealed so content can never get stuck hidden.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function revealAll() {
    document.querySelectorAll('section, .footer-section').forEach(function (s) { s.classList.add('fd-in'); });
  }

  function setupReveal() {
    var targets = document.querySelectorAll('section, .footer-section');
    if (reduce || !('IntersectionObserver' in window)) { revealAll(); return; }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('fd-in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });

    targets.forEach(function (el) { io.observe(el); });
    setTimeout(revealAll, 4500); // safety net against any missed observations
  }

  function setupHeader() {
    var header = document.querySelector('header');
    if (!header || reduce) return;

    var lastY = window.pageYOffset || 0;
    var ticking = false;
    var DELTA = 6;   // ignore tiny jitter
    var TOP = 90;    // always show within this distance of the top

    function update() {
      var y = window.pageYOffset || 0;
      if (Math.abs(y - lastY) > DELTA) {
        if (y > lastY && y > TOP) header.classList.add('fd-nav-hidden');   // scrolling down
        else header.classList.remove('fd-nav-hidden');                    // scrolling up
        lastY = y;
      }
      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
  }

  function init() { setupReveal(); setupHeader(); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
