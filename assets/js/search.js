/**
 * Feedora live search.
 *
 * Attaches to every .search-input / .mobile-search-input. As the user types it
 * queries /api/search.php (debounced) and shows an animated results panel; on
 * focus with an empty box it shows a "Discover" panel (latest recipes + tags).
 *
 * The panel is rendered on <body> and fixed-positioned under the active input,
 * so it is never clipped by the header's overflow.
 */
(function () {
  'use strict';

  var API = 'api/search.php';
  var DEBOUNCE = 220;
  var panel, bodyEl, headEl;
  var activeInput = null;
  var items = [];        // current focusable rows
  var sel = -1;          // keyboard selection index
  var lastQuery = null;
  var timer = null;
  var reqId = 0;

  function build() {
    panel = document.createElement('div');
    panel.className = 'fd-search-panel';
    panel.setAttribute('aria-label', 'Search recipes and chefs');
    panel.innerHTML = '<div class="fd-search-head"><span class="fd-label">Discover</span><span class="fd-count"></span></div><div class="fd-search-body"></div>';
    document.body.appendChild(panel);
    headEl = panel.querySelector('.fd-label');
    bodyEl = panel.querySelector('.fd-search-body');
    panel.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep input focus
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function position() {
    if (!activeInput || !panel.classList.contains('fd-open')) return;
    var r = activeInput.getBoundingClientRect();
    var w = panel.offsetWidth;
    var vw = document.documentElement.clientWidth;
    var left = Math.min(Math.max(r.right - w, 8), vw - w - 8);
    panel.style.top = (r.bottom + 10) + 'px';
    panel.style.left = left + 'px';
  }

  function open() {
    panel.classList.add('fd-open');
    position();
  }
  function close() {
    panel.classList.remove('fd-open');
    activeInput = null;
    sel = -1;
  }

  function setLoading() {
    headEl.textContent = 'Searching';
    panel.querySelector('.fd-count').textContent = '';
    bodyEl.removeAttribute('role');
    bodyEl.removeAttribute('aria-label');
    bodyEl.innerHTML = '<div class="fd-loading"><div class="fd-spinner"></div>Searching recipes &amp; posts…</div>';
  }

  function render(data) {
    var results = data.results || [];
    headEl.textContent = data.mode === 'discover' ? 'Discover' : 'Results';
    panel.querySelector('.fd-count').textContent = results.length ? results.length + (data.mode === 'discover' ? '' : ' found') : '';

    if (!results.length) {
      bodyEl.removeAttribute('role');
      bodyEl.removeAttribute('aria-label');
      bodyEl.innerHTML = '<div class="fd-empty">No matches' + (data.query ? ' for “' + esc(data.query) + '”' : '') + '. Try another keyword.</div>';
      items = []; sel = -1; position(); return;
    }

    var html = '';
    results.forEach(function (it, i) {
      // Real DB image when present; otherwise a shimmering skeleton stays put.
      // onload clears the shimmer; onerror removes the <img> so the skeleton shows.
      var thumb = it.image
        ? '<span class="fd-thumb fd-skeleton"><img src="' + esc(it.image) + '" alt="" loading="lazy"'
          + ' onload="this.parentNode.classList.remove(\'fd-skeleton\')" onerror="this.remove()"></span>'
        : '<span class="fd-thumb fd-skeleton"></span>';
      html += '<a class="fd-item" role="option" href="' + esc(it.url) + '" style="--d:' + (i * 40) + 'ms">'
        + thumb
        + '<span class="fd-meta"><span class="fd-title">' + esc(it.title) + '</span>'
        + '<span class="fd-sub">' + esc(it.subtitle) + '</span></span>'
        + '<span class="fd-badge">' + esc(it.type) + '</span></a>';
    });
    bodyEl.innerHTML = html;
    bodyEl.setAttribute('role', 'listbox');
    bodyEl.setAttribute('aria-label', 'Search results');
    bodyEl.scrollTop = 0;
    items = Array.prototype.slice.call(bodyEl.querySelectorAll('.fd-item'));
    sel = -1;
    position();
  }

  function fetchResults(q) {
    if (q === lastQuery && panel.classList.contains('fd-open')) return;
    lastQuery = q;
    var mine = ++reqId;
    if (q.length >= 2) setLoading();
    fetch(API + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (mine === reqId && activeInput) { open(); render(data); } })
      .catch(function () {
        if (mine === reqId) bodyEl.innerHTML = '<div class="fd-empty">Search is unavailable right now.</div>';
      });
  }

  function onInput() {
    var q = this.value.trim();
    clearTimeout(timer);
    timer = setTimeout(function () { fetchResults(q); }, DEBOUNCE);
  }

  function onFocus() {
    activeInput = this;
    lastQuery = null;
    fetchResults(this.value.trim());
  }

  function move(dir) {
    if (!items.length) return;
    if (sel >= 0 && items[sel]) items[sel].classList.remove('fd-active');
    sel = (sel + dir + items.length) % items.length;
    var el = items[sel];
    el.classList.add('fd-active');
    el.scrollIntoView({ block: 'nearest' });
  }

  function onKey(e) {
    if (!panel.classList.contains('fd-open')) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Enter' && sel >= 0 && items[sel]) { e.preventDefault(); items[sel].click(); }
    else if (e.key === 'Escape') { close(); this.blur(); }
  }

  function attach(input) {
    if (input.__fdSearch) return;
    input.__fdSearch = true;
    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', onInput);
    input.addEventListener('focus', onFocus);
    input.addEventListener('keydown', onKey);
  }

  function init() {
    build();
    document.querySelectorAll('.search-input, .mobile-search-input').forEach(attach);

    document.addEventListener('click', function (e) {
      if (!panel.contains(e.target) && !e.target.closest('.search-input, .mobile-search-input')) close();
    });
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
