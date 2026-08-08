/* Sidebar collapse (desktop icon-rail) — open/close + persisted state.
 * Separate IIFE so it runs on every page that has the sidebar, including chat
 * (the presence beacon below bails on chat, this must not).
 */
(function () {
  "use strict";
  var KEY = "fd_sidebar_collapsed";
  var toggle = document.getElementById("sidebar-collapse-toggle");
  var items = Array.prototype.slice.call(document.querySelectorAll(".sidebar .menu-item"));
  var labels = items.map(function (item) {
    var t = item.querySelector(".menu-item-text");
    return t ? t.textContent.trim() : "";
  });

  function apply(collapsed) {
    document.body.classList.toggle("sidebar-collapsed", collapsed);
    // Collapsed → show the label as a native tooltip (no clipping, no x-scroll).
    items.forEach(function (item, i) {
      if (collapsed && labels[i]) item.setAttribute("title", labels[i]);
      else item.removeAttribute("title");
    });
    if (toggle) {
      toggle.setAttribute("aria-label", collapsed ? "Expand menu" : "Collapse menu");
      toggle.setAttribute("title", collapsed ? "Expand menu" : "Collapse menu");
    }
  }

  // Restore persisted state (desktop only — the drawer owns small screens).
  try {
    if (window.innerWidth > 768 && localStorage.getItem(KEY) === "1") apply(true);
  } catch (e) {}

  if (toggle) {
    toggle.addEventListener("click", function () {
      var collapsed = !document.body.classList.contains("sidebar-collapsed");
      apply(collapsed);
      try { localStorage.setItem(KEY, collapsed ? "1" : "0"); } catch (e) {}
    });
  }

  // Clean, professional resize behaviour:
  //  - kill transitions mid-resize so the sidebar snaps rather than sliding;
  //  - when returning to desktop, clear any leftover mobile-drawer state (open
  //    class, overlay, and the body overflow-lock header.js sets) so the layout
  //    is never left half-open / scroll-locked.
  var resizeTimer = null;
  window.addEventListener("resize", function () {
    document.body.classList.add("fd-no-anim");
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      document.body.classList.remove("fd-no-anim");
    }, 200);

    if (window.innerWidth > 768) {
      var sb = document.querySelector(".sidebar");
      var ov = document.getElementById("sidebar-overlay");
      if (sb) sb.classList.remove("active");
      if (ov) ov.classList.remove("active");
      document.body.classList.remove("sidebar-active");
      document.body.style.overflow = "";
    }
  });
})();

/* Sidebar presence beacon (refactor — 2026-06-24).
 *
 * ROOT-CAUSE FIX (see _workspace/00_plan.md): the old beacon POSTed
 * `action=update_status` (an UNHANDLED action → wasted "Invalid action") to
 * chat.php every 30s on EVERY page that includes the sidebar (Home.php, etc.),
 * with no visibility gate, forever. That is the "spam messages requests".
 *
 * This rewrite:
 *   (a) calls the REAL presence action `update_user_activity` (chat.php),
 *   (b) sends the project CSRF token (X-CSRF-Token header — matches csrf.js;
 *       also POSTs csrf_token as a field so it works even if csrf.js is absent),
 *   (c) only runs when the tab is visible (!document.hidden),
 *   (d) runs at a LOW cadence (60s),
 *   (e) SKIPS entirely on the chat page — the chat's unified poll already keeps
 *       presence fresh (UPDATE USER_SESSIONS.LAST_ACTIVITY each poll), so a
 *       second beacon there would just double the traffic.
 */
(function () {
  "use strict";

  // (e) Bail on the chat page — the chat poll already maintains presence.
  if (window.ChatConfig || document.querySelector(".chat-container")) return;

  var PRESENCE_MS = 60000; // (c) low cadence

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute("content") || "" : "";
  }

  function updateOnlineStatus() {
    if (document.hidden) return; // (b)/(d) visibility gate

    var token = csrfToken();
    var body = "action=update_user_activity"; // (a) the action chat.php actually handles
    if (token) body += "&csrf_token=" + encodeURIComponent(token);

    fetch("chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-CSRF-Token": token // matches the project CSRF contract (csrf_verify)
      },
      body: body,
      keepalive: true
    }).catch(function () {
      /* network blips are non-fatal for a presence beacon */
    });
  }

  // Beat once on load (visible only), then on a slow visibility-gated interval.
  updateOnlineStatus();
  setInterval(updateOnlineStatus, PRESENCE_MS);

  // Send a fresh beat the moment the tab becomes visible again (cheap, gated).
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) updateOnlineStatus();
  });
})();
