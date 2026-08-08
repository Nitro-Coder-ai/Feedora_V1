<?php
/**
 * Floating "mini messenger" (Facebook-style) — bottom-right launcher + popup.
 *
 * Included by header.php, so it appears on every app page EXCEPT chat.php
 * (which owns the full chat and never includes header.php). It reuses the
 * existing chat.php JSON actions (poll / get_messages / send_message) so no
 * new backend is required. New incoming messages ping a sound + bump a badge
 * and slide in a toast — all without navigating to the chat page.
 */
?>
<div id="fd-messenger" class="fd-messenger" data-me="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>">
    <!-- Incoming-message toast (appears above the launcher) -->
    <button type="button" id="fdm-toast" class="fdm-toast" hidden>
        <img class="fdm-toast-avatar" id="fdm-toast-avatar" src="" alt="">
        <span class="fdm-toast-body">
            <span class="fdm-toast-name" id="fdm-toast-name"></span>
            <span class="fdm-toast-text" id="fdm-toast-text"></span>
        </span>
    </button>

    <!-- Conversation popup -->
    <div id="fdm-panel" class="fdm-panel" hidden>
        <div class="fdm-head">
            <span class="fdm-head-title" id="fdm-head-title">Messages</span>
            <div class="fdm-head-actions">
                <button type="button" id="fdm-mute" class="fdm-head-btn" title="Mute sounds" aria-label="Mute sounds">
                    <svg class="fdm-ico-sound" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>
                    <svg class="fdm-ico-muted" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>
                </button>
                <a href="chat.php" class="fdm-head-btn" title="Open full chat" aria-label="Open full chat">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>
                </a>
                <button type="button" id="fdm-close" class="fdm-head-btn" title="Close" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        </div>

        <!-- Conversation list -->
        <div id="fdm-list" class="fdm-list">
            <div class="fdm-empty">Loading conversations…</div>
        </div>

        <!-- Thread view -->
        <div id="fdm-thread" class="fdm-thread" hidden>
            <div class="fdm-thread-head">
                <button type="button" id="fdm-back" class="fdm-head-btn" aria-label="Back to conversations">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <img id="fdm-thread-avatar" class="fdm-thread-avatar" src="" alt="">
                <div class="fdm-thread-meta">
                    <span id="fdm-thread-name" class="fdm-thread-name"></span>
                    <span id="fdm-thread-status" class="fdm-thread-status"></span>
                </div>
            </div>
            <div id="fdm-thread-msgs" class="fdm-thread-msgs"></div>
            <form id="fdm-reply" class="fdm-reply" autocomplete="off">
                <input type="text" id="fdm-reply-input" class="fdm-reply-input" placeholder="Aa" aria-label="Type a message">
                <button type="submit" class="fdm-reply-send" aria-label="Send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Launcher -->
    <button type="button" id="fdm-launcher" class="fdm-launcher" title="Messages" aria-label="Open messages">
        <svg class="fdm-launcher-ico" xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.04 2 11c0 2.6 1.24 4.93 3.24 6.54V22l3.32-1.82c1.06.29 2.2.45 3.44.45 5.52 0 10-4.04 10-9s-4.48-9-10-9zm1.04 12.06-2.6-2.77-4.86 2.77 5.34-5.67 2.66 2.77 4.8-2.77-5.34 5.67z"/></svg>
        <span id="fdm-badge" class="fdm-badge" hidden>0</span>
    </button>
</div>
<link rel="stylesheet" href="<?php echo asset('assets/css/messenger.css'); ?>">
<script src="<?php echo asset('assets/js/messenger.js'); ?>" defer></script>
