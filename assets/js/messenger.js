/**
 * Feedora floating mini-messenger.
 *
 * Reuses chat.php's existing JSON actions (poll / get_messages / send_message)
 * to give a Facebook-style bottom-right messenger on every app page: a live
 * unread badge, a sound + toast when a new message arrives, and inline reply —
 * all WITHOUT navigating to the chat page.
 */
(function () {
  "use strict";

  var root = document.getElementById("fd-messenger");
  if (!root) return;
  // Portal to <body> so no ancestor's transform/overflow/stacking context can
  // clip the widget or block taps on the mobile full-screen sheet.
  if (root.parentNode !== document.body) document.body.appendChild(root);
  var ME = parseInt(root.getAttribute("data-me") || "0", 10);
  var DEFAULT_AVATAR =
    "https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg";

  var launcher = document.getElementById("fdm-launcher");
  var badge = document.getElementById("fdm-badge");
  var panel = document.getElementById("fdm-panel");
  var listEl = document.getElementById("fdm-list");
  var threadEl = document.getElementById("fdm-thread");
  var threadMsgs = document.getElementById("fdm-thread-msgs");
  var threadName = document.getElementById("fdm-thread-name");
  var threadStatus = document.getElementById("fdm-thread-status");
  var threadAvatar = document.getElementById("fdm-thread-avatar");
  var replyForm = document.getElementById("fdm-reply");
  var replyInput = document.getElementById("fdm-reply-input");
  var toast = document.getElementById("fdm-toast");
  var toastAvatar = document.getElementById("fdm-toast-avatar");
  var toastName = document.getElementById("fdm-toast-name");
  var toastText = document.getElementById("fdm-toast-text");
  var headTitle = document.getElementById("fdm-head-title");
  var muteBtn = document.getElementById("fdm-mute");

  var state = {
    open: false,
    activeUser: 0,
    threadLastId: 0,      // cursor for active-thread poll
    knownIncomingMax: -1, // for new-message sound/toast detection
    unread: 0,
    muted: false,
    convs: []
  };
  try { state.muted = localStorage.getItem("fdm_muted") === "1"; } catch (e) {}

  function token() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute("content") || "" : ""; }
  function post(body) {
    return fetch("chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-CSRF-Token": token(),
        "X-Requested-With": "fetch"
      },
      body: body
    }).then(function (r) { return r.json(); });
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function relTime(ts) {
    if (!ts) return "";
    var d = Math.floor(Date.now() / 1000) - ts;
    if (d < 60) return "now";
    if (d < 3600) return Math.floor(d / 60) + "m";
    if (d < 86400) return Math.floor(d / 3600) + "h";
    return Math.floor(d / 86400) + "d";
  }

  // ---- Sound: a short two-tone WebAudio ping (no asset needed) --------------
  var audioCtx = null;
  function ping() {
    if (state.muted) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var now = audioCtx.currentTime;
      [[880, 0], [1320, 0.12]].forEach(function (p) {
        var o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = "sine"; o.frequency.value = p[0];
        o.connect(g); g.connect(audioCtx.destination);
        g.gain.setValueAtTime(0.0001, now + p[1]);
        g.gain.exponentialRampToValueAtTime(0.18, now + p[1] + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, now + p[1] + 0.18);
        o.start(now + p[1]); o.stop(now + p[1] + 0.2);
      });
    } catch (e) {}
  }

  function setBadge(n) {
    state.unread = n;
    if (n > 0) { badge.hidden = false; badge.textContent = n > 99 ? "99+" : n; }
    else badge.hidden = true;
    var hb = document.getElementById("messages-badge"); // keep the header badge in sync
    if (hb) { if (n > 0) { hb.style.display = "flex"; hb.textContent = n > 99 ? "99+" : n; } else hb.style.display = "none"; }
    if (headTitle) headTitle.textContent = n > 0 ? "Messages (" + n + ")" : "Messages";
  }
  function pingUI() {
    badge.classList.remove("ping"); void badge.offsetWidth; badge.classList.add("ping");
    launcher.classList.remove("ring"); void launcher.offsetWidth; launcher.classList.add("ring");
  }

  // ---- Toast (incoming preview) ---------------------------------------------
  var toastTimer = null;
  function showToast(c) {
    if (state.open) return;
    toastAvatar.src = c.profile_image || DEFAULT_AVATAR;
    toastName.textContent = c.name || "New message";
    toastText.textContent = c.preview || "New message";
    toast.hidden = false;
    requestAnimationFrame(function () { toast.classList.add("show"); });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 5000);
    toast.onclick = function () { hideToast(); openThread(c.user_id, c.name, c.profile_image); };
  }
  function hideToast() { toast.classList.remove("show"); setTimeout(function () { toast.hidden = true; }, 250); }

  // ---- Conversation list ----------------------------------------------------
  function renderList(convs) {
    if (!convs.length) { listEl.innerHTML = '<div class="fdm-empty">No conversations yet.<br><a href="chat.php" class="fdm-empty-cta">Start a conversation →</a></div>'; return; }
    listEl.innerHTML = convs.map(function (c) {
      var dot = c.online ? '<span class="dot"></span>' : "";
      var b = c.unread > 0 ? '<span class="fdm-conv-badge">' + (c.unread > 9 ? "9+" : c.unread) + "</span>" : "";
      var prev = (c.outgoing ? "You: " : "") + (c.preview || "");
      return '<div class="fdm-conv' + (c.unread > 0 ? " unread" : "") + '" data-uid="' + c.user_id +
        '" data-name="' + esc(c.name) + '" data-avatar="' + esc(c.profile_image || "") + '">' +
        '<div class="fdm-conv-avatar"><img src="' + esc(c.profile_image || DEFAULT_AVATAR) +
        "\" onerror=\"this.src='" + DEFAULT_AVATAR + "'\">" + dot + "</div>" +
        '<div class="fdm-conv-main"><div class="fdm-conv-name">' + esc(c.name) +
        '</div><div class="fdm-conv-prev">' + esc(prev) + "</div></div>" +
        '<div class="fdm-conv-side"><span class="fdm-conv-time">' + relTime(c.sent_ts) + "</span>" + b + "</div></div>";
    }).join("");
  }
  listEl.addEventListener("click", function (e) {
    var row = e.target.closest(".fdm-conv");
    if (row) openThread(parseInt(row.dataset.uid, 10), row.dataset.name, row.dataset.avatar);
  });

  // ---- Thread ---------------------------------------------------------------
  function msgHtml(m) {
    var out = parseInt(m.SENDER_ID, 10) === ME;
    if (m.IMAGE_URL) return '<div class="fdm-msg img ' + (out ? "out" : "in") + '"><img src="' + esc(m.IMAGE_URL) + '"></div>';
    return '<div class="fdm-msg ' + (out ? "out" : "in") + '">' + esc(m.CONTENT || "") + "</div>";
  }
  function renderThread(msgs) {
    threadMsgs.innerHTML = msgs.map(msgHtml).join("");
    threadMsgs.scrollTop = threadMsgs.scrollHeight;
    if (msgs.length) state.threadLastId = parseInt(msgs[msgs.length - 1].MESSAGE_ID, 10);
  }
  function openThread(uid, name, avatar) {
    state.activeUser = uid;
    state.threadLastId = 0;
    if (!state.open) openPanel();
    threadName.textContent = name;
    threadAvatar.src = avatar || DEFAULT_AVATAR;
    threadStatus.textContent = "";
    threadMsgs.innerHTML = '<div class="fdm-empty">Loading…</div>';
    threadEl.hidden = false;
    replyInput.focus();
    post("action=get_messages&receiver_id=" + uid).then(function (d) {
      if (d.success && state.activeUser === uid) {
        renderThread(d.messages || []);
        var c = null;
        state.convs.forEach(function (x) { if (x.user_id === uid) c = x; });
        if (c && c.unread) { setBadge(Math.max(0, state.unread - c.unread)); c.unread = 0; }
      }
    });
  }
  function closeThread() { threadEl.hidden = true; state.activeUser = 0; renderList(state.convs); }
  document.getElementById("fdm-back").addEventListener("click", closeThread);

  replyForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var text = replyInput.value.trim();
    if (!text || !state.activeUser) return;
    replyInput.value = "";
    threadMsgs.insertAdjacentHTML("beforeend", '<div class="fdm-msg out">' + esc(text) + "</div>");
    threadMsgs.scrollTop = threadMsgs.scrollHeight;
    post("action=send_message&receiver_id=" + state.activeUser + "&content=" + encodeURIComponent(text)).then(function (d) {
      if (d && d.message_id) state.threadLastId = Math.max(state.threadLastId, parseInt(d.message_id, 10));
      pollNow();
    });
  });

  // ---- Panel open/close -----------------------------------------------------
  function syncHeaderIcon() {
    var hi = document.getElementById("messages-icon");
    if (hi) hi.classList.toggle("active", state.open);
  }
  function openPanel() { state.open = true; panel.hidden = false; root.classList.add("is-open"); syncHeaderIcon(); hideToast(); pollNow(); }
  function closePanel() { state.open = false; panel.hidden = true; root.classList.remove("is-open"); syncHeaderIcon(); closeThread(); }
  function togglePanel() { if (state.open) closePanel(); else openPanel(); }

  launcher.addEventListener("click", togglePanel);
  document.getElementById("fdm-close").addEventListener("click", closePanel);
  var headerMsg = document.getElementById("messages-icon"); // header icon toggles widget, no redirect
  if (headerMsg) headerMsg.addEventListener("click", function (e) { e.preventDefault(); togglePanel(); });

  function applyMute() {
    muteBtn.querySelector(".fdm-ico-sound").hidden = state.muted;
    muteBtn.querySelector(".fdm-ico-muted").hidden = !state.muted;
    muteBtn.title = state.muted ? "Unmute sounds" : "Mute sounds";
  }
  muteBtn.addEventListener("click", function () {
    state.muted = !state.muted;
    try { localStorage.setItem("fdm_muted", state.muted ? "1" : "0"); } catch (e) {}
    applyMute();
  });
  applyMute();

  // ---- Polling --------------------------------------------------------------
  var timer = null;
  function scheduleNext() { clearTimeout(timer); timer = setTimeout(pollNow, state.open ? 3500 : 8000); }
  function pollNow() {
    clearTimeout(timer);
    if (document.hidden) { scheduleNext(); return; }
    var body = "action=poll";
    if (state.activeUser) body += "&active_user_id=" + state.activeUser + "&last_message_id=" + state.threadLastId;
    post(body).then(function (d) {
      if (!d || !d.success) { scheduleNext(); return; }
      var convs = d.conversations || [];
      var total = convs.reduce(function (n, c) { return n + (c.unread || 0); }, 0);

      // New incoming detection: highest last_message_id among incoming convs.
      var incomingMax = -1, newConv = null;
      convs.forEach(function (c) { if (!c.outgoing && c.last_message_id > incomingMax) { incomingMax = c.last_message_id; newConv = c; } });
      if (state.knownIncomingMax >= 0 && incomingMax > state.knownIncomingMax && newConv) {
        if (!(state.open && state.activeUser === newConv.user_id)) { ping(); pingUI(); showToast(newConv); }
        else ping();
      }
      state.knownIncomingMax = Math.max(state.knownIncomingMax, incomingMax);

      setBadge(total);
      state.convs = convs;
      if (threadEl.hidden) renderList(convs);

      if (state.activeUser && !threadEl.hidden && d.messages && d.messages.length) {
        d.messages.forEach(function (m) { threadMsgs.insertAdjacentHTML("beforeend", msgHtml(m)); });
        threadMsgs.scrollTop = threadMsgs.scrollHeight;
        state.threadLastId = parseInt(d.messages[d.messages.length - 1].MESSAGE_ID, 10);
      }
      if (state.activeUser && d.active_status) threadStatus.textContent = d.active_status.online ? "Active now" : "";
      scheduleNext();
    }).catch(function () { scheduleNext(); });
  }

  document.addEventListener("visibilitychange", function () { if (!document.hidden) pollNow(); });
  pollNow(); // warm the badge + list immediately
})();
