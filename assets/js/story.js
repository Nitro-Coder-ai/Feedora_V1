/* Facebook-style client-side Story viewer engine.
 * The entire playlist (every user with active stories → each of their stories) is
 * handed to the browser via window.StoryConfig, so navigation between stories AND
 * between users happens in place with NO page reloads. */
(function () {
  "use strict";

  const cfg = window.StoryConfig || {};
  const playlist = Array.isArray(cfg.playlist) ? cfg.playlist : [];
  const DEFAULT_AVATAR = cfg.defaultAvatar || "";
  const IMAGE_DURATION = cfg.imageDuration || 5000;

  let ui = cfg.startUserIndex || 0; // current user index
  let si = cfg.startStoryIndex || 0; // current story index within the user

  const el = (id) => document.getElementById(id);

  // DOM references (resolved on init)
  let viewer, backdrop, progressC, avatar, nameEl, timeEl, mediaC, loading,
    pauseBtn, viewersToggle, viewersToggleCount, viewersSheet, viewersSheetCount, viewersList,
    interact, reactionsBar, replyForm, replyInput, reactFlash, viewersReactions, viewersReplies;

  // Playback state
  let raf = null;
  let paused = false;
  let currentVideo = null;
  let imgStart = 0;
  let imgElapsed = 0;

  // Hold-to-pause state
  let holdTimer = null;
  let didHold = false;

  const currentUser = () => playlist[ui];
  const currentStory = () => currentUser().stories[si];

  // Derive a Cloudinary still-frame (jpg) from a video delivery URL by swapping
  // the file extension — used for the blurred backdrop and the <video> poster.
  function videoPoster(url) {
    return String(url || "").replace(/\.(mp4|webm|mov|ogg|avi|m4v)(\?.*)?$/i, ".jpg");
  }

  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function clearProgress() {
    if (raf) {
      cancelAnimationFrame(raf);
      raf = null;
    }
  }

  function showLoading(show) {
    if (loading) loading.style.display = show ? "flex" : "none";
  }

  function buildProgressSegments() {
    const stories = currentUser().stories;
    progressC.innerHTML = "";
    for (let i = 0; i < stories.length; i++) {
      const bar = document.createElement("div");
      bar.className = "story-progress-bar";
      const fill = document.createElement("div");
      fill.className = "story-progress-fill";
      fill.style.width = i < si ? "100%" : "0%";
      bar.appendChild(fill);
      progressC.appendChild(bar);
    }
  }

  function setFill(pct) {
    const bar = progressC.children[si];
    const fill = bar && bar.firstChild;
    if (fill) fill.style.width = Math.min(100, Math.max(0, pct)) + "%";
  }

  // ── Rendering ─────────────────────────────────────────────────────────────
  function render() {
    clearProgress();
    paused = false;
    pauseBtn.classList.remove("is-paused");

    const user = currentUser();
    const story = currentStory();

    // Header
    avatar.src = user.profileImage || DEFAULT_AVATAR;
    nameEl.textContent = user.userName || "User";
    timeEl.textContent = story.timeLabel || "";

    buildProgressSegments();

    // Backdrop (blurred copy of the media). Videos use a Cloudinary still frame
    // derived from the URL so letterbox gaps look intentional, not black.
    const backdropSrc =
      story.mediaType === "image" ? story.imageUrl : videoPoster(story.imageUrl);
    backdrop.style.backgroundImage = 'url("' + backdropSrc + '")';

    // Media
    mediaC.innerHTML = "";
    currentVideo = null;
    showLoading(true);

    if (story.mediaType === "video") {
      const v = document.createElement("video");
      v.className = "story-media-el";
      v.src = story.imageUrl;
      v.poster = videoPoster(story.imageUrl); // show a still frame before playback
      v.playsInline = true;
      v.setAttribute("playsinline", "");
      v.preload = "auto";
      v.controls = false;
      mediaC.appendChild(v);
      currentVideo = v;

      v.addEventListener("loadeddata", () => showLoading(false), { once: true });
      v.addEventListener("ended", goNext);
      v.addEventListener("error", goNext);
      v.addEventListener(
        "loadedmetadata",
        () => {
          const p = v.play();
          // Browsers may block sound-on autoplay; fall back to muted playback.
          if (p && p.catch) {
            p.catch(() => {
              v.muted = true;
              v.play().catch(() => {});
            });
          }
          startVideoProgress();
        },
        { once: true }
      );
    } else {
      const img = document.createElement("img");
      img.className = "story-media-el";
      img.src = story.imageUrl;
      img.alt = "Story";
      mediaC.appendChild(img);

      if (img.complete) {
        showLoading(false);
        startImageProgress();
      } else {
        img.addEventListener(
          "load",
          () => {
            showLoading(false);
            startImageProgress();
          },
          { once: true }
        );
        img.addEventListener(
          "error",
          () => {
            showLoading(false);
            startImageProgress();
          },
          { once: true }
        );
      }
    }

    // Owner sees the activity toggle; everyone else gets the react + reply bar.
    if (user.isOwner) {
      viewersToggle.style.display = "flex";
      if (interact) interact.style.display = "none";
      loadViewers(story.storyId);
    } else {
      viewersToggle.style.display = "none";
      closeViewersSheet();
      if (interact) {
        interact.style.display = "flex";
        if (replyInput) replyInput.value = "";
        reflectMyReaction(story.myReaction || null);
      }
    }

    // Record the view (server ignores the owner's own stories)
    recordView(story.storyId);

    // Keep the URL in sync so refresh/share lands on the same story
    try {
      history.replaceState(null, "", "story.php?id=" + story.storyId);
    } catch (e) {
      /* ignore */
    }
  }

  // ── Progress timers ───────────────────────────────────────────────────────
  function startImageProgress() {
    clearProgress();
    imgStart = performance.now();
    imgElapsed = 0;
    const tick = (now) => {
      if (paused) {
        imgStart = now - imgElapsed; // freeze elapsed while paused
        raf = requestAnimationFrame(tick);
        return;
      }
      imgElapsed = now - imgStart;
      const pct = (imgElapsed / IMAGE_DURATION) * 100;
      setFill(pct);
      if (pct >= 100) {
        setFill(100);
        goNext();
        return;
      }
      raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
  }

  function startVideoProgress() {
    clearProgress();
    const v = currentVideo;
    if (!v) return;
    const tick = () => {
      if (v.duration && !isNaN(v.duration)) {
        setFill((v.currentTime / v.duration) * 100);
      }
      raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
  }

  // ── Navigation ────────────────────────────────────────────────────────────
  function goNext() {
    clearProgress();
    si++;
    if (si >= currentUser().stories.length) {
      ui++;
      si = 0;
      if (ui >= playlist.length) {
        close();
        return;
      }
    }
    render();
  }

  function goPrev() {
    clearProgress();
    si--;
    if (si < 0) {
      ui--;
      if (ui < 0) {
        // Already at the very first story — restart it.
        ui = 0;
        si = 0;
        render();
        return;
      }
      si = currentUser().stories.length - 1; // last story of the previous user
    }
    render();
  }

  function close() {
    clearProgress();
    if (viewer) viewer.classList.add("closing");
    setTimeout(() => {
      window.location.href = "dashboard.php";
    }, 200);
  }

  function setPaused(state) {
    paused = state;
    pauseBtn.classList.toggle("is-paused", state);
    if (currentVideo) {
      if (state) currentVideo.pause();
      else currentVideo.play().catch(() => {});
    }
  }

  // ── Viewers (owner only) ──────────────────────────────────────────────────
  function recordView(storyId) {
    fetch("story.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=record_individual_view&story_id=" + encodeURIComponent(storyId),
    }).catch(() => {});
  }

  function loadViewers(storyId) {
    viewersList.innerHTML = "";
    if (viewersReactions) viewersReactions.innerHTML = "";
    if (viewersReplies) viewersReplies.innerHTML = "";
    viewersToggleCount.textContent = "0";
    viewersSheetCount.textContent = "0";
    fetch("story.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=get_viewers&story_id=" + encodeURIComponent(storyId),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.success) return;
        const count = data.view_count || 0;
        viewersToggleCount.textContent = count;
        viewersSheetCount.textContent = count;

        // Always-visible reactions summary chip on the owner's story view.
        const summaryEl = document.getElementById("storyReactSummary");
        if (summaryEl) {
          const rx0 = data.reactions || [];
          if (rx0.length) {
            const total = rx0.reduce((n, r) => n + (parseInt(r.cnt, 10) || 0), 0);
            const emojis = rx0.slice(0, 3).map((r) => r.REACTION_EMOJI).join("");
            summaryEl.innerHTML = '<span class="srs-emojis">' + emojis + "</span> " + total;
            summaryEl.style.display = "flex";
          } else {
            summaryEl.style.display = "none";
          }
        }

        // Reactions summary (emoji chips with counts) in the activity sheet
        if (viewersReactions) {
          const rx = data.reactions || [];
          if (rx.length) {
            viewersReactions.innerHTML =
              '<div class="activity-label">Reactions</div><div class="reaction-chips">' +
              rx
                .map(
                  (r) =>
                    '<span class="reaction-chip">' +
                    escapeHtml(r.REACTION_EMOJI || "") +
                    " " +
                    (parseInt(r.cnt, 10) || 0) +
                    "</span>"
                )
                .join("") +
              "</div>";
          }
        }

        // Replies list
        if (viewersReplies) {
          const rp = data.replies || [];
          if (rp.length) {
            viewersReplies.innerHTML =
              '<div class="activity-label">Replies</div>' +
              rp
                .map(
                  (r) =>
                    '<div class="reply-item">' +
                    '<img class="viewer-avatar" src="' +
                    escapeHtml(r.avatar || DEFAULT_AVATAR) +
                    "\" onerror=\"this.src='" +
                    DEFAULT_AVATAR +
                    "'\" alt=\"\">" +
                    '<div class="viewer-info"><div class="viewer-name">' +
                    escapeHtml(r.name || "User") +
                    '</div><div class="reply-text">' +
                    escapeHtml(r.content || "") +
                    '</div><div class="viewer-time">' +
                    escapeHtml(r.time || "") +
                    "</div></div></div>"
                )
                .join("");
          }
        }

        // Viewers list
        const viewers = data.viewers || [];
        if (!viewers.length) {
          viewersList.innerHTML =
            '<div class="no-viewers"><div class="no-viewers-icon">👁️</div>' +
            '<p class="no-viewers-text">No viewers yet</p>' +
            '<p class="no-viewers-subtext">Share your story to get more views!</p></div>';
          return;
        }
        viewersList.innerHTML =
          '<div class="activity-label">Viewers</div>' +
          viewers
            .map(
              (v) =>
                '<div class="viewer-item">' +
                '<img class="viewer-avatar" src="' +
                escapeHtml(v.PROFILE_IMAGE || DEFAULT_AVATAR) +
                '" onerror="this.src=\'' +
                DEFAULT_AVATAR +
                "'\" alt=\"\">" +
                '<div class="viewer-info"><div class="viewer-name">' +
                escapeHtml(v.NAME || "User") +
                '</div><div class="viewer-time">' +
                escapeHtml(v.VIEWED_AT || "") +
                "</div></div></div>"
            )
            .join("");
      })
      .catch(() => {});
  }

  function openViewersSheet() {
    viewersSheet.classList.add("open");
    setPaused(true);
  }

  function closeViewersSheet() {
    viewersSheet.classList.remove("open");
  }

  // ── Reactions & replies (viewer side) ──────────────────────────────────────
  function reflectMyReaction(type) {
    if (!reactionsBar) return;
    reactionsBar.querySelectorAll(".story-react-btn").forEach((b) => {
      b.classList.toggle("is-active", b.getAttribute("data-type") === type);
    });
  }

  function flashReaction(emoji) {
    if (!reactFlash) return;
    reactFlash.textContent = emoji;
    reactFlash.classList.remove("animate");
    // force reflow so the animation can re-trigger
    void reactFlash.offsetWidth;
    reactFlash.classList.add("animate");
  }

  function sendReaction(type) {
    const story = currentStory();
    if (!story) return;
    const wasActive = story.myReaction === type;

    // Optimistic UI: toggle locally, then confirm with the server.
    story.myReaction = wasActive ? null : type;
    reflectMyReaction(story.myReaction);
    if (!wasActive && cfg.reactionEmojis && cfg.reactionEmojis[type]) {
      flashReaction(cfg.reactionEmojis[type]);
    }

    fetch("story.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "action=react&story_id=" +
        encodeURIComponent(story.storyId) +
        "&reaction_type=" +
        encodeURIComponent(type),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.success) {
          // Revert on failure.
          story.myReaction = wasActive ? type : null;
          reflectMyReaction(story.myReaction);
          return;
        }
        story.myReaction = data.my_reaction || null;
        reflectMyReaction(story.myReaction);
      })
      .catch(() => {
        story.myReaction = wasActive ? type : null;
        reflectMyReaction(story.myReaction);
      });
  }

  function sendReply() {
    const story = currentStory();
    if (!story || !replyInput) return;
    const content = replyInput.value.trim();
    if (!content) return;

    replyInput.value = "";
    replyInput.placeholder = "Sent ✓";
    fetch("story.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "action=reply&story_id=" +
        encodeURIComponent(story.storyId) +
        "&content=" +
        encodeURIComponent(content),
    })
      .then((r) => r.json())
      .then((data) => {
        replyInput.placeholder =
          data && data.success ? "Reply sent ✓" : "Reply to story…";
        setTimeout(() => {
          if (replyInput) replyInput.placeholder = "Reply to story…";
        }, 1500);
      })
      .catch(() => {
        if (replyInput) replyInput.placeholder = "Reply to story…";
      });
  }

  // ── Input bindings ────────────────────────────────────────────────────────
  function bindTapZone(zone, action) {
    zone.addEventListener("pointerdown", () => {
      didHold = false;
      holdTimer = setTimeout(() => {
        didHold = true;
        setPaused(true);
      }, 220);
    });
    const release = () => {
      clearTimeout(holdTimer);
      if (didHold) {
        setPaused(false);
        didHold = false;
      } else {
        action();
      }
    };
    zone.addEventListener("pointerup", release);
    zone.addEventListener("pointercancel", () => {
      clearTimeout(holdTimer);
      if (didHold) {
        setPaused(false);
        didHold = false;
      }
    });
  }

  function onKey(e) {
    switch (e.key) {
      case "ArrowRight":
      case " ":
        e.preventDefault();
        goNext();
        break;
      case "ArrowLeft":
        e.preventDefault();
        goPrev();
        break;
      case "Escape":
        e.preventDefault();
        close();
        break;
    }
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    if (!playlist.length) {
      window.location.href = "dashboard.php";
      return;
    }

    viewer = el("storyViewer");
    backdrop = el("storyBackdrop");
    progressC = el("storyProgress");
    avatar = el("storyAvatar");
    nameEl = el("storyName");
    timeEl = el("storyTime");
    mediaC = el("storyMedia");
    loading = el("storyLoading");
    pauseBtn = el("storyPause");
    viewersToggle = el("viewersToggle");
    viewersToggleCount = el("viewersToggleCount");
    viewersSheet = el("viewersSheet");
    viewersSheetCount = el("viewersSheetCount");
    viewersList = el("viewersList");
    interact = el("storyInteract");
    reactionsBar = el("storyReactions");
    replyForm = el("storyReplyForm");
    replyInput = el("storyReplyInput");
    reactFlash = el("storyReactFlash");
    viewersReactions = el("viewersReactions");
    viewersReplies = el("viewersReplies");

    el("storyClose").addEventListener("click", close);
    pauseBtn.addEventListener("click", () => setPaused(!paused));

    // Quick emoji reactions
    if (reactionsBar) {
      reactionsBar.addEventListener("click", (e) => {
        const btn = e.target.closest(".story-react-btn");
        if (!btn) return;
        sendReaction(btn.getAttribute("data-type"));
      });
    }

    // Reply box: typing pauses the story so it doesn't advance mid-message.
    if (replyForm) {
      replyForm.addEventListener("submit", (e) => {
        e.preventDefault();
        sendReply();
        if (replyInput) replyInput.blur();
        setPaused(false);
      });
    }
    if (replyInput) {
      replyInput.addEventListener("focus", () => setPaused(true));
      replyInput.addEventListener("blur", () => setPaused(false));
      // Don't let key navigation fire while typing a reply.
      replyInput.addEventListener("keydown", (e) => e.stopPropagation());
    }

    viewersToggle.addEventListener("click", () => {
      if (viewersSheet.classList.contains("open")) {
        closeViewersSheet();
        setPaused(false);
      } else {
        openViewersSheet();
      }
    });
    viewersSheet.addEventListener("click", (e) => {
      if (e.target === viewersSheet) {
        closeViewersSheet();
        setPaused(false);
      }
    });

    bindTapZone(el("tapPrev"), goPrev);
    bindTapZone(el("tapNext"), goNext);
    document.addEventListener("keydown", onKey);

    render();
    console.log("🎬 Facebook-style story viewer ready:", playlist.length, "users");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
