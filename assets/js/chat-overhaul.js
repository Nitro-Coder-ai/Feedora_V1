/* ============================================================================
 * Feedora chat overhaul — group chats + WebRTC calls (DB-backed signaling).
 * Runs alongside chat.js. Consumes the unified poll via the "chat:poll" event
 * (no extra polling) and drives groups, the info sidebar, and 1:1 WebRTC calls.
 * ==========================================================================*/
(function () {
  "use strict";
  var cfg = window.ChatConfig || {};
  var ME = String(cfg.currentUserId);
  var DEF = cfg.defaultAvatar || "uploads/profiles/default.png";

  var el = function (id) { return document.getElementById(id); };
  // Attribute-safe HTML escaper: also encodes " and ' so values used inside
  // attribute contexts (title="...", data-*="...") cannot break out → no XSS.
  function esc(x) {
    return String(x == null ? "" : x)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function post(body) {
    return fetch("chat.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body })
      .then(function (r) { return r.json(); });
  }
  function qs(obj) { return Object.keys(obj).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(obj[k]); }).join("&"); }

  var groups = Array.isArray(cfg.initialGroups) ? cfg.initialGroups : [];
  var activeGroup = null; // {group_id, name, ...}

  // ── Channels list (merged into the single left sidebar) ─────────────────────
  // Renders group rows (avatar/initials + name + unread) into #rail-groups. The
  // click hook stays `.rail-group` + data-group-id so the existing handler works.
  function renderRail() {
    var host = el("rail-groups");
    if (!host) return;
    if (!groups.length) {
      host.innerHTML = '<div class="channels-empty">No channels yet</div>';
      return;
    }
    host.innerHTML = groups.map(function (g) {
      var initials = (g.name || "G").trim().slice(0, 2).toUpperCase();
      var avatar = g.avatar_url
        ? '<img src="' + esc(g.avatar_url) + '" alt="">'
        : '<span class="channel-initials">' + esc(initials) + "</span>";
      var badge = g.unread > 0 ? '<span class="channel-badge">' + (g.unread > 9 ? "9+" : g.unread) + "</span>" : "";
      var active = (activeGroup && String(activeGroup.group_id) === String(g.group_id)) ? " active" : "";
      return (
        '<button class="rail-group channel-item' + active + '" data-group-id="' + g.group_id +
        '" title="' + esc(g.name) + '" aria-label="Open channel ' + esc(g.name) + '">' +
        '<span class="channel-avatar">' + avatar + "</span>" +
        '<span class="channel-name">' + esc(g.name) + "</span>" +
        badge + "</button>"
      );
    }).join("");
  }

  function openGroup(gid) {
    var g = groups.find(function (x) { return String(x.group_id) === String(gid); });
    if (!g) return;
    activeGroup = g;
    window.__activeGroupId = String(gid);
    window.__activeUserId = null;       // stop DM polling path
    window.__lastMessageId = 0;
    if (window.ChatEnhance) window.ChatEnhance.onScopeChange();
    renderRail();
    var dms = el("rail-dms"); if (dms) dms.classList.remove("active");

    // Show the conversation panel as a group
    var noSel = el("no-message-selected"); if (noSel) noSel.style.display = "none";
    var ac = el("active-conversation"); if (ac) ac.style.display = "flex";
    var nameEl = el("active-conversation-name"); if (nameEl) nameEl.textContent = g.name;
    var avatarImg = document.querySelector(".conversation-header-avatar img");
    if (avatarImg) avatarImg.src = g.avatar_url || DEF;
    var status = document.querySelector(".conversation-header-info .conversation-status");
    if (status) status.innerHTML = '<span class="status-text">Group · tap members ➜</span>';

    var mc = el("messages-container"); if (mc) mc.innerHTML = '<div class="loading-messages">Loading…</div>';

    post(qs({ action: "get_messages", group_id: gid })).then(function (data) {
      if (!data.success) return;
      if (mc) mc.innerHTML = "";
      (data.messages || []).forEach(appendGroupMessage);
      window.__lastMessageId = data.last_message_id || 0;
      if (mc) mc.scrollTop = mc.scrollHeight;
      updateFilesPanel();
      mark_group_read(gid, window.__lastMessageId);
    });
    loadGroupMembers(gid);
  }

  function mark_group_read(gid, mid) {
    post(qs({ action: "mark_group_read", group_id: gid, last_message_id: mid || 0 })).catch(function () {});
  }

  // ── Group message rendering (sender avatar + name for others) ──────────────
  function appendGroupMessage(m) {
    var mc = el("messages-container");
    if (!mc) return;
    if (mc.querySelector('.message[data-message-id="' + m.MESSAGE_ID + '"]')) return;
    var mine = String(m.SENDER_ID) === ME;
    var wrap = document.createElement("div");
    wrap.className = "message " + (mine ? "message-sent" : "message-received");
    wrap.dataset.messageId = m.MESSAGE_ID;
    var avatar = mine ? (cfg.currentUserProfileImage || DEF) : (m.SENDER_IMAGE || DEF);
    var media = "";
    if (m.IMAGE_URL) {
      var u = m.IMAGE_URL;
      var isAudio = /\/feedora\/voice\//.test(u) || /\.(mp3|m4a|wav|oga)(\?.*)?$/i.test(u);
      var isVid = !isAudio && (/\/video\/upload\//.test(u) || /\.(mp4|webm|mov|ogg|m4v)(\?.*)?$/i.test(u));
      if (isAudio) media = '<div class="message-audio"><audio src="' + esc(u) + '" controls preload="metadata"></audio></div>';
      else if (isVid) media = '<div class="message-image is-video"><video src="' + esc(u) + '" controls playsinline preload="metadata"></video></div>';
      else media = '<div class="message-image"><img src="' + esc(u) + '" alt=""></div>';
    }
    var nameLabel = mine ? "" : '<div class="msg-sender-name">' + esc(m.SENDER_NAME || "User") + "</div>";
    var text = m.CONTENT ? '<div class="message-text">' + esc(m.CONTENT) + "</div>" : "";
    wrap.innerHTML =
      '<div class="message-avatar"><img src="' + esc(avatar) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\"></div>" +
      '<div class="message-content"><div class="message-bubble">' + nameLabel + text + media + "</div></div>";
    mc.appendChild(wrap);
    // P1/P2: pin affordance + edited marker / deleted tombstone (shared logic).
    if (window.ChatEnhance) window.ChatEnhance.decorateMessage(wrap, m, mine);
  }

  // ── Info sidebar: members + files ──────────────────────────────────────────
  function loadGroupMembers(gid) {
    post(qs({ action: "get_group_members", group_id: gid })).then(function (data) {
      if (!data.success) return;
      var host = el("info-members");
      var cnt = el("info-member-count");
      if (cnt) cnt.textContent = "· " + data.members.length;
      var iAmAdmin = data.members.some(function (m) { return String(m.USER_ID) === ME && m.ROLE === "admin"; });
      var invite = el("info-invite-btn"); if (invite) invite.style.display = iAmAdmin ? "block" : "none";
      if (host) {
        host.innerHTML = data.members.map(function (m) {
          var role = m.ROLE === "admin" ? '<span class="role">Admin</span>' : "";
          var dot = '<span class="mdot ' + (Number(m.online) ? "on" : "") + '"></span>';
          var nm = String(m.USER_ID) === ME ? "You" : esc(m.NAME);
          return '<div class="info-member"><img src="' + esc(m.PROFILE_IMAGE || DEF) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\">" +
                 '<span class="nm">' + nm + "</span>" + role + dot + "</div>";
        }).join("");
      }
    });
  }

  function updateFilesPanel() {
    var mc = el("messages-container");
    if (!mc) return;
    var imgs = mc.querySelectorAll(".message-image img");
    var vids = mc.querySelectorAll(".message-image video");
    var p = el("files-photos"); if (p) p.textContent = imgs.length;
    var v = el("files-videos"); if (v) v.textContent = vids.length;
    var thumbs = el("info-file-thumbs");
    if (thumbs) {
      var html = "";
      imgs.forEach(function (im, i) { if (i < 9) html += '<img src="' + im.src + '" alt="">'; });
      thumbs.innerHTML = html;
    }
  }

  // ── Create group modal ─────────────────────────────────────────────────────
  var cgSelected = {};
  var cgAvatarFile = null;
  function openCreateGroup() {
    var modal = el("create-group-modal"); if (!modal) return;
    modal.classList.add("active"); modal.style.display = "flex";
    cgSelected = {}; cgAvatarFile = null;
    el("cg-name").value = "";
    el("cg-avatar-preview").innerHTML = '<i class="fas fa-camera"></i>';
    post(qs({ action: "get_users" })).then(function (data) {
      if (!data.success) return;
      var host = el("cg-members");
      host.innerHTML = (data.users || []).map(function (u) {
        return '<div class="cg-member" data-id="' + u.USER_ID + '" data-name="' + esc(u.NAME) + '">' +
          '<img src="' + esc(u.PROFILE_IMAGE || DEF) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\">" +
          '<span class="nm">' + esc(u.NAME) + '</span><span class="chk"></span></div>';
      }).join("");
    });
  }
  function closeCreateGroup() { var m = el("create-group-modal"); if (m) { m.classList.remove("active"); m.style.display = "none"; } }

  function submitCreateGroup() {
    var name = (el("cg-name").value || "").trim();
    if (!name) { el("cg-name").focus(); return; }
    var fd = new FormData();
    fd.append("action", "create_group");
    fd.append("name", name);
    Object.keys(cgSelected).forEach(function (id) { fd.append("members[]", id); });
    if (cgAvatarFile) fd.append("avatar", cgAvatarFile);
    var btn = el("cg-create-btn"); btn.disabled = true; btn.textContent = "Creating…";
    fetch("chat.php", { method: "POST", body: fd }).then(function (r) { return r.json(); }).then(function (data) {
      btn.disabled = false; btn.textContent = "Create group";
      if (data.success) {
        closeCreateGroup();
        if (window.__chatPollNow) window.__chatPollNow();
        setTimeout(function () { openGroup(data.group_id); }, 400);
      } else { alert(data.message || "Could not create group"); }
    }).catch(function () { btn.disabled = false; btn.textContent = "Create group"; });
  }

  // ═══════════════ Multi-party (mesh) WebRTC calls — Discord-style ════════════
  // Backed by the mesh actions in 02_backend.md: join_call / get_call_room /
  // send_peer_signal / leave_call. One CALL_SESSIONS row = the "room"; each pair
  // of participants runs its own RTCPeerConnection (full mesh). Signalling is
  // DIRECTED (to_user) and consumed from get_call_room.signals (UPPER_SNAKE:
  // FROM_USER / KIND / PAYLOAD). Glare-free initiation: the LOWER user id offers.
  var RTC_CFG = { iceServers: [{ urls: "stun:stun.l.google.com:19302" }] };
  var mesh = {
    callId: null,
    type: "voice",
    me: Number(cfg.currentUserId),
    localStream: null,
    peers: {},     // remoteId -> { pc, haveRemote, pendingIce: [] }
    parts: {},     // userId  -> participant row {USER_ID,NAME,PROFILE_IMAGE,ROLE,...}
    pollTimer: null,
    lastSig: 0,
    audioOn: true,
    videoOn: true
  };

  function showCallWindow(show) { var w = el("call-window"); if (w) w.style.display = show ? "flex" : "none"; }
  function meshActive() { return mesh.callId != null; }

  function mediaErrorMessage(err) {
    var n = (err && err.name) || "";
    if (n === "NotAllowedError" || n === "SecurityError") return "Permission denied. Allow mic/camera access in your browser, then try again.";
    if (n === "NotFoundError" || n === "DevicesNotFoundError") return "No microphone/camera found on this device.";
    if (n === "NotReadableError" || n === "TrackStartError") return "Your camera/mic is busy (another app or tab is using it).";
    if (err && err.message === "insecure-context") return "Calls require HTTPS or localhost. Open the site via http://localhost:8080.";
    return "Could not access mic/camera: " + (n || (err && err.message) || "unknown error");
  }

  function getMeshMedia(video) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      return Promise.reject(new Error("insecure-context"));
    }
    return navigator.mediaDevices.getUserMedia({ audio: true, video: !!video })
      .catch(function (err) {
        // Two tabs on one PC can't open the camera twice — fall back to audio-only
        // so the call still connects instead of failing outright.
        if (video && (err.name === "NotReadableError" || err.name === "TrackStartError" || err.name === "OverconstrainedError")) {
          return navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        }
        throw err;
      })
      .then(function (stream) {
        mesh.localStream = stream;
        mesh.audioOn = stream.getAudioTracks().length > 0;
        mesh.videoOn = stream.getVideoTracks().length > 0;
      });
  }

  // Entry: start (or join the existing) room for the open DM / group.
  function meshStart(type) {
    var receiver = window.__activeUserId || "";
    var gid = window.__activeGroupId || "";
    if (!receiver && !gid) { alert("Open a conversation first."); return; }
    var p = { action: "join_call", call_type: type };
    if (gid) p.group_id = gid; else p.receiver_id = receiver;
    meshBoot(p, type);
  }

  // Entry: join a known room advertised by the poll (group_call / incoming_call).
  // `type` is what we capture locally (join_call ignores it for an existing room).
  function meshJoin(callId, type) {
    meshBoot({ action: "join_call", call_id: callId }, type);
  }

  function meshBoot(params, type) {
    if (meshActive()) return; // already in a call
    showCallWindow(true);
    setCallTitle(window.__activeGroupId && activeGroup ? activeGroup.name : "Call");
    getMeshMedia(type === "video").then(function () {
      return post(qs(params));
    }).then(function (data) {
      if (!data || !data.success) { meshTeardown(true); alert((data && data.message) || "Call failed"); return; }
      mesh.callId = data.call_id;
      mesh.type = data.call_type || type || "voice";
      mesh.me = Number(data.me);
      mesh.lastSig = 0;
      addLocalTile();
      applyParticipants(data.participants || []);
      meshPoll();
      mesh.pollTimer = setInterval(meshPoll, 1200);
    }).catch(function (err) { console.error(err); meshTeardown(true); alert(mediaErrorMessage(err)); });
  }

  function meshSend(toUser, kind, payload) {
    return post(qs({ action: "send_peer_signal", call_id: mesh.callId, to_user: toUser, kind: kind, payload: payload })).catch(function () {});
  }

  function meshEnsurePeer(remoteId) {
    remoteId = Number(remoteId);
    if (remoteId === mesh.me) return null;
    if (mesh.peers[remoteId]) return mesh.peers[remoteId];
    var pc = new RTCPeerConnection(RTC_CFG);
    var entry = { pc: pc, haveRemote: false, pendingIce: [] };
    mesh.peers[remoteId] = entry;
    if (mesh.localStream) mesh.localStream.getTracks().forEach(function (t) { pc.addTrack(t, mesh.localStream); });
    pc.onicecandidate = function (e) { if (e.candidate) meshSend(remoteId, "ice", JSON.stringify(e.candidate)); };
    pc.ontrack = function (e) {
      var tile = meshTile(remoteId);
      var v = tile.querySelector("video");
      if (v && e.streams && e.streams[0]) { v.srcObject = e.streams[0]; tile.classList.add("has-video"); }
    };
    return entry;
  }

  // Deterministic, glare-free: the lower user id creates the offer to the higher.
  function meshMaybeOffer(remoteId) {
    remoteId = Number(remoteId);
    if (mesh.me >= remoteId) return;          // the other side will offer to me
    var entry = mesh.peers[remoteId];
    if (!entry) return;
    var pc = entry.pc;
    pc.createOffer()
      .then(function (o) { return pc.setLocalDescription(o).then(function () { return o; }); })
      .then(function (o) { meshSend(remoteId, "offer", JSON.stringify(o)); })
      .catch(function (e) { console.error(e); });
  }

  function meshFlushIce(remoteId) {
    var e = mesh.peers[remoteId];
    if (!e) return;
    e.pendingIce.forEach(function (c) { e.pc.addIceCandidate(new RTCIceCandidate(c)).catch(function () {}); });
    e.pendingIce = [];
  }

  function meshHandleSignal(sig) {
    var from = Number(sig.FROM_USER);
    var entry = meshEnsurePeer(from);
    if (!entry) return;
    var pc = entry.pc;
    if (sig.KIND === "offer") {
      pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.PAYLOAD)))
        .then(function () { entry.haveRemote = true; meshFlushIce(from); return pc.createAnswer(); })
        .then(function (a) { return pc.setLocalDescription(a).then(function () { return a; }); })
        .then(function (a) { meshSend(from, "answer", JSON.stringify(a)); })
        .catch(function (e) { console.error(e); });
    } else if (sig.KIND === "answer") {
      pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.PAYLOAD)))
        .then(function () { entry.haveRemote = true; meshFlushIce(from); })
        .catch(function (e) { console.error(e); });
    } else if (sig.KIND === "ice") {
      var c = JSON.parse(sig.PAYLOAD);
      if (entry.haveRemote) pc.addIceCandidate(new RTCIceCandidate(c)).catch(function () {});
      else entry.pendingIce.push(c);
    }
  }

  function meshPoll() {
    if (!mesh.callId) return;
    post(qs({ action: "get_call_room", call_id: mesh.callId, last_signal_id: mesh.lastSig })).then(function (d) {
      if (!d || !d.success) return;
      if (d.status === "ended") { meshTeardown(true); return; }
      applyParticipants(d.participants || []);
      (d.signals || []).forEach(meshHandleSignal);
      if (typeof d.last_signal_id !== "undefined") mesh.lastSig = d.last_signal_id;
    }).catch(function () {});
  }

  // Diff the roster: add a tile + peer (and maybe offer) for newcomers, drop
  // tiles/peers for those who left.
  function applyParticipants(list) {
    var seen = {};
    list.forEach(function (p) {
      var id = Number(p.USER_ID);
      seen[id] = true;
      mesh.parts[id] = p;
      if (id === mesh.me) { meshTile(id, p); return; }
      meshTile(id, p);
      var fresh = !mesh.peers[id];
      meshEnsurePeer(id);
      if (fresh) meshMaybeOffer(id);
    });
    Object.keys(mesh.peers).forEach(function (id) { if (!seen[id]) meshDropPeer(Number(id)); });
    updateCallCount(Object.keys(seen).length || 1);
  }

  function meshTile(id, p) {
    id = Number(id);
    var grid = el("call-grid");
    if (!grid) return document.createElement("div");
    var t = grid.querySelector('.call-tile[data-user="' + id + '"]');
    if (!t) {
      t = document.createElement("div");
      t.className = "call-tile" + (id === mesh.me ? " is-local" : "");
      t.dataset.user = id;
      var img = (p && p.PROFILE_IMAGE) || (id === mesh.me ? (cfg.currentUserProfileImage || DEF) : DEF);
      t.innerHTML =
        '<video autoplay playsinline' + (id === mesh.me ? " muted" : "") + "></video>" +
        '<div class="call-tile-av"><img src="' + esc(img) + "\" onerror=\"this.src='" + DEF + "'\" alt=\"\"></div>" +
        '<div class="call-tile-name"></div>';
      grid.appendChild(t);
    }
    var nm = t.querySelector(".call-tile-name");
    if (nm) nm.textContent = id === mesh.me ? "You" : ((p && p.NAME) || "User");
    return t;
  }

  function addLocalTile() {
    var t = meshTile(mesh.me, mesh.parts[mesh.me] || { NAME: "You", PROFILE_IMAGE: cfg.currentUserProfileImage });
    var v = t.querySelector("video");
    if (v) { v.muted = true; if (mesh.localStream) { v.srcObject = mesh.localStream; } }
    if (mesh.localStream && mesh.localStream.getVideoTracks().length) t.classList.add("has-video");
  }

  function meshDropPeer(id) {
    id = Number(id);
    var e = mesh.peers[id];
    if (e) { try { e.pc.close(); } catch (_) {} delete mesh.peers[id]; }
    var grid = el("call-grid");
    var t = grid && grid.querySelector('.call-tile[data-user="' + id + '"]');
    if (t) t.remove();
    delete mesh.parts[id];
  }

  function setCallTitle(t) { var e = el("call-title"); if (e) e.textContent = t || "Call"; }
  function updateCallCount(n) { var e = el("call-count"); if (e) e.textContent = n + (n === 1 ? " in call" : " in call"); }

  function meshTeardown(silent) {
    if (mesh.pollTimer) { clearInterval(mesh.pollTimer); mesh.pollTimer = null; }
    var cid = mesh.callId;
    Object.keys(mesh.peers).forEach(function (id) { try { mesh.peers[id].pc.close(); } catch (_) {} });
    mesh.peers = {};
    if (mesh.localStream) { mesh.localStream.getTracks().forEach(function (t) { t.stop(); }); mesh.localStream = null; }
    var grid = el("call-grid"); if (grid) grid.innerHTML = "";
    showCallWindow(false);
    mesh.callId = null; mesh.parts = {}; mesh.lastSig = 0; mesh.audioOn = true; mesh.videoOn = true;
    var mb = el("call-mute-btn"); if (mb) mb.classList.remove("off");
    var cb = el("call-video-btn"); if (cb) cb.classList.remove("off");
    if (cid && !silent) post(qs({ action: "leave_call", call_id: cid })).catch(function () {});
  }

  // Leave: tell the server, then tear down locally.
  function meshLeave() {
    var cid = mesh.callId;
    meshTeardown(true);
    if (cid) post(qs({ action: "leave_call", call_id: cid })).catch(function () {});
  }

  // Incoming call modal
  var ringingCallId = null;
  function showIncoming(call) {
    if (meshActive() || ringingCallId === call.CALL_ID) return;
    ringingCallId = call.CALL_ID;
    el("call-incoming-avatar").src = call.CALLER_IMAGE || DEF;
    el("call-incoming-name").textContent = call.CALLER_NAME || "Someone";
    el("call-incoming-type").textContent = "Incoming " + (call.CALL_TYPE === "video" ? "video" : "voice") + " call…";
    var modal = el("call-incoming-modal");
    modal.style.display = "flex";
    modal.dataset.callId = call.CALL_ID;
    modal.dataset.offer = call.OFFER_SDP || "";
    modal.dataset.type = call.CALL_TYPE;
    modal.dataset.image = call.CALLER_IMAGE || "";
  }
  function hideIncoming() { var m = el("call-incoming-modal"); if (m) m.style.display = "none"; ringingCallId = null; }

  // ── Voice notes (WhatsApp-style) ───────────────────────────────────────────
  var mediaRecorder = null, recChunks = [], recTimer = null, recSeconds = 0, recStream = null;
  function startRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { alert("Recording needs HTTPS or localhost."); return; }
    if (!window.__activeUserId && !window.__activeGroupId) { alert("Open a conversation first."); return; }
    if (mediaRecorder) return;
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      recStream = stream; recChunks = [];
      mediaRecorder = new MediaRecorder(stream);
      mediaRecorder.ondataavailable = function (e) { if (e.data && e.data.size > 0) recChunks.push(e.data); };
      mediaRecorder.start();
      var bar = el("voice-record-bar"); if (bar) bar.style.display = "flex";
      recSeconds = 0; var t = el("vr-time"); if (t) t.textContent = "0:00";
      recTimer = setInterval(function () {
        recSeconds++;
        var m = Math.floor(recSeconds / 60), s = recSeconds % 60;
        var te = el("vr-time"); if (te) te.textContent = m + ":" + (s < 10 ? "0" : "") + s;
        if (recSeconds >= 120) stopRecording(true); // 2 min cap
      }, 1000);
    }).catch(function (err) { alert(mediaErrorMessage(err)); });
  }
  function cleanupRec() {
    clearInterval(recTimer); recTimer = null;
    if (recStream) { recStream.getTracks().forEach(function (t) { t.stop(); }); recStream = null; }
    var bar = el("voice-record-bar"); if (bar) bar.style.display = "none";
  }
  function stopRecording(send) {
    if (!mediaRecorder) { cleanupRec(); return; }
    var mr = mediaRecorder; mediaRecorder = null;
    mr.onstop = function () {
      var blob = new Blob(recChunks, { type: "audio/webm" });
      cleanupRec();
      if (send && blob.size > 0) uploadVoice(blob);
    };
    try { mr.stop(); } catch (e) { cleanupRec(); }
  }
  function uploadVoice(blob) {
    var fd = new FormData();
    fd.append("action", "send_message");
    if (window.__activeGroupId) fd.append("group_id", window.__activeGroupId);
    else fd.append("receiver_id", window.__activeUserId);
    fd.append("image", new File([blob], "voice_" + Date.now() + ".webm", { type: "audio/webm" }));
    fetch("chat.php", { method: "POST", body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.success && window.__chatPollNow) window.__chatPollNow();
      else if (!d.success) alert(d.message || "Could not send voice note.");
    }).catch(function () {});
  }

  // ── Wire up + poll integration ─────────────────────────────────────────────
  function init() {
    renderRail();

    var recBtn = el("voice-record-btn");
    if (recBtn) recBtn.addEventListener("click", function () { if (mediaRecorder) stopRecording(true); else startRecording(); });
    var vrSend = el("vr-send"); if (vrSend) vrSend.addEventListener("click", function () { stopRecording(true); });
    var vrCancel = el("vr-cancel"); if (vrCancel) vrCancel.addEventListener("click", function () { stopRecording(false); });

    // Single-input composer: one trailing button toggles mic (empty) ↔ send
    // (typing), WhatsApp-style. The text field and send logic stay in chat.js;
    // here we only swap which trailing button is visible.
    var composerInput = el("message-input");
    var micBtn = el("voice-record-btn");
    var sendBtn = el("send-message-btn");
    function refreshComposer() {
      if (!composerInput || !micBtn || !sendBtn) return;
      var hasText = composerInput.value.trim().length > 0;
      micBtn.style.display = hasText ? "none" : "";
      sendBtn.style.display = hasText ? "" : "none";
    }
    if (composerInput) {
      composerInput.addEventListener("input", refreshComposer);
      // After Enter-to-send or clicking send, chat.js clears the field without
      // firing 'input' — re-check shortly after so the mic returns.
      composerInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) setTimeout(refreshComposer, 60);
      });
    }
    if (sendBtn) sendBtn.addEventListener("click", function () { setTimeout(refreshComposer, 60); });
    refreshComposer();

    var railGroups = el("rail-groups");
    if (railGroups) railGroups.addEventListener("click", function (e) {
      var b = e.target.closest(".rail-group"); if (b) openGroup(b.dataset.groupId);
    });
    var dms = el("rail-dms");
    if (dms) dms.addEventListener("click", function () {
      activeGroup = null; window.__activeGroupId = null; renderRail();
      dms.classList.add("active");
      var ac = el("active-conversation"); if (ac) ac.style.display = "none";
      var noSel = el("no-message-selected"); if (noSel) noSel.style.display = "flex";
    });
    var createBtn = el("rail-create-group"); if (createBtn) createBtn.addEventListener("click", openCreateGroup);
    var closeCg = el("close-create-group"); if (closeCg) closeCg.addEventListener("click", closeCreateGroup);
    var cgCreate = el("cg-create-btn"); if (cgCreate) cgCreate.addEventListener("click", submitCreateGroup);

    var cgMembers = el("cg-members");
    if (cgMembers) cgMembers.addEventListener("click", function (e) {
      var row = e.target.closest(".cg-member"); if (!row) return;
      var id = row.dataset.id;
      if (cgSelected[id]) { delete cgSelected[id]; row.classList.remove("selected"); }
      else { cgSelected[id] = true; row.classList.add("selected"); }
    });
    var cgSearch = el("cg-member-search");
    if (cgSearch) cgSearch.addEventListener("input", function () {
      var t = this.value.toLowerCase();
      document.querySelectorAll("#cg-members .cg-member").forEach(function (r) {
        r.style.display = (r.dataset.name || "").toLowerCase().indexOf(t) >= 0 ? "flex" : "none";
      });
    });
    var cgAvatar = el("cg-avatar");
    if (cgAvatar) cgAvatar.addEventListener("change", function (e) {
      var f = e.target.files[0]; if (!f) return; cgAvatarFile = f;
      el("cg-avatar-preview").innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt="">';
    });

    // Info sidebar: toggle-only slide-in panel at all widths, with a backdrop +
    // click-outside + Escape to close (frees the conversation column).
    var togInfo = el("toggle-info-btn");
    var infoScrim = null;
    function setInfo(open) {
      var s = el("info-sidebar");
      if (!s) return;
      s.classList.toggle("info-open", open);
      if (togInfo) togInfo.classList.toggle("is-active", open);
      if (open && !infoScrim) {
        infoScrim = document.createElement("div");
        infoScrim.className = "info-scrim";
        s.parentNode.insertBefore(infoScrim, s);
        infoScrim.addEventListener("click", function () { setInfo(false); });
      }
      if (infoScrim) infoScrim.classList.toggle("show", open);
    }
    if (togInfo) togInfo.addEventListener("click", function () {
      var s = el("info-sidebar");
      setInfo(!(s && s.classList.contains("info-open")));
    });
    var infoClose = el("info-close-btn");
    if (infoClose) infoClose.addEventListener("click", function () { setInfo(false); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { var s = el("info-sidebar"); if (s && s.classList.contains("info-open")) setInfo(false); }
    });

    // Mobile drawer: hamburger opens group-rail + chat list together
    var container = document.querySelector(".chat-container");
    var ov = el("mobile-overlay");
    function setDrawer(open) {
      if (!container) return;
      container.classList.toggle("drawer-open", open);
      if (ov) ov.classList.toggle("active", open);
    }
    var mobToggle = el("mobile-conversations-toggle");
    if (mobToggle) mobToggle.addEventListener("click", function () { setDrawer(!container.classList.contains("drawer-open")); });
    if (ov) ov.addEventListener("click", function () { setDrawer(false); });
    // Close the drawer once a chat/group is picked (mobile)
    document.addEventListener("click", function (e) {
      if (e.target.closest(".conversation-item") || e.target.closest(".rail-group") || e.target.closest("#rail-dms")) {
        if (window.innerWidth <= 767) setTimeout(function () { setDrawer(false); }, 80);
      }
    });
    var invite = el("info-invite-btn");
    if (invite) invite.addEventListener("click", function () {
      if (!activeGroup) return;
      openCreateGroup(); // reuse picker; on submit we instead invite
      el("create-group-modal").dataset.mode = "invite";
      el("cg-name").style.display = "none";
      el("cg-create-btn").textContent = "Invite to " + activeGroup.name;
      el("cg-create-btn").onclick = function () {
        var ids = Object.keys(cgSelected);
        if (!ids.length) return;
        var body = "action=invite_members&group_id=" + activeGroup.group_id + "&" + ids.map(function (i) { return "user_ids[]=" + i; }).join("&");
        post(body).then(function (d) { if (d.success) { closeCreateGroup(); loadGroupMembers(activeGroup.group_id); } });
      };
    });

    // Call buttons (all routed through the multi-party mesh).
    var vc = el("voice-call-btn"); if (vc) vc.addEventListener("click", function () { meshStart("voice"); });
    var vid = el("video-call-btn"); if (vid) vid.addEventListener("click", function () { meshStart("video"); });
    var endB = el("call-end-btn"); if (endB) endB.addEventListener("click", function () { meshLeave(); });
    var muteB = el("call-mute-btn"); if (muteB) muteB.addEventListener("click", function () {
      if (!mesh.localStream) return;
      mesh.audioOn = !mesh.audioOn;
      mesh.localStream.getAudioTracks().forEach(function (t) { t.enabled = mesh.audioOn; });
      muteB.classList.toggle("off", !mesh.audioOn);
    });
    var camB = el("call-video-btn"); if (camB) camB.addEventListener("click", function () {
      if (!mesh.localStream) return;
      mesh.videoOn = !mesh.videoOn;
      mesh.localStream.getVideoTracks().forEach(function (t) { t.enabled = mesh.videoOn; });
      camB.classList.toggle("off", !mesh.videoOn);
      var grid = el("call-grid");
      var lt = grid && grid.querySelector(".call-tile.is-local");
      if (lt) lt.classList.toggle("has-video", mesh.videoOn);
    });
    var acc = el("call-accept-btn"); if (acc) acc.addEventListener("click", function () {
      var m = el("call-incoming-modal");
      hideIncoming();
      meshJoin(Number(m.dataset.callId), m.dataset.type);
    });
    var rej = el("call-reject-btn"); if (rej) rej.addEventListener("click", function () {
      var m = el("call-incoming-modal");
      post(qs({ action: "reject_call", call_id: m.dataset.callId })).catch(function () {});
      hideIncoming();
    });

    // React to the unified poll
    document.addEventListener("chat:poll", function (e) {
      var data = e.detail || {};
      if (Array.isArray(data.groups)) {
        groups = data.groups;
        // keep activeGroup reference fresh
        if (activeGroup) { var g = groups.find(function (x) { return String(x.group_id) === String(activeGroup.group_id); }); if (g) activeGroup = g; }
        renderRail();
      }
      // New group messages for the open group
      if (window.__activeGroupId && Array.isArray(data.messages) && data.messages.length) {
        var appended = false;
        data.messages.forEach(function (m) {
          if (String(m.GROUP_ID) === String(window.__activeGroupId)) { appendGroupMessage(m); appended = true; }
        });
        if (data.last_message_id) window.__lastMessageId = data.last_message_id;
        if (appended) { var mc = el("messages-container"); if (mc) mc.scrollTop = mc.scrollHeight; updateFilesPanel(); mark_group_read(window.__activeGroupId, window.__lastMessageId); }
      }
      // Incoming direct call
      if (data.incoming_call && !meshActive()) showIncoming(data.incoming_call);
      // Group call available to join (inline)
      renderGroupCallBanner(data.group_call);
    });
  }

  function renderGroupCallBanner(call) {
    var mc = el("messages-container");
    if (!mc) return;
    var existing = el("group-call-banner");
    if (!call) { if (existing) existing.remove(); return; }
    if (meshActive()) { if (existing) existing.remove(); return; }
    if (!existing) {
      existing = document.createElement("div");
      existing.id = "group-call-banner";
      existing.className = "msg-callevent";
      mc.appendChild(existing);
    }
    existing.innerHTML = '<i class="fas fa-phone" style="color:var(--fd-green)"></i> ' +
      esc(call.CALLER_NAME || "Someone") + " started a " + (call.CALL_TYPE === "video" ? "video" : "voice") + " call" +
      '<button class="join-call-btn" id="join-group-call">Join</button>';
    var jb = el("join-group-call");
    if (jb) jb.onclick = function () { meshJoin(Number(call.CALL_ID), call.CALL_TYPE); };
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
