<?php
/**
 * "Continue with Google / Facebook" buttons + divider. Included on sign-in and
 * sign-up. The buttons are always shown so the UI is complete; if a provider
 * isn't configured yet, its endpoint just bounces back with a friendly notice.
 */
?>
<div class="fd-social">
  <div class="fd-social-divider"><span>or continue with</span></div>
  <div class="fd-social-btns">
    <a class="fd-social-btn" href="oauth/google.php" aria-label="Continue with Google">
      <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571.001-.001.002-.001.003-.002l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
      </svg>
      <span>Google</span>
    </a>
    <a class="fd-social-btn" href="oauth/facebook.php" aria-label="Continue with Facebook">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
      <span>Facebook</span>
    </a>
  </div>
</div>

<style>
  .fd-social { margin: 18px 0 4px; }
  .fd-social-divider { display: flex; align-items: center; gap: 12px; color: #9aa0ab; font-size: 13px; margin-bottom: 16px; }
  .fd-social-divider::before, .fd-social-divider::after { content: ""; flex: 1; height: 1px; background: #e6e8eb; }
  .fd-social-btns { display: flex; gap: 12px; }
  .fd-social-btn {
    flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    padding: 11px 14px; border: 1.5px solid #e2e5ea; border-radius: 12px; background: #fff;
    color: #1f2430; font-weight: 600; font-size: 14.5px; text-decoration: none;
    font-family: inherit; transition: border-color .18s ease, box-shadow .18s ease, transform .12s ease;
  }
  .fd-social-btn:hover { border-color: #cfd4db; box-shadow: 0 4px 14px -8px rgba(0,0,0,.35); transform: translateY(-1px); }
  .fd-social-btn:active { transform: translateY(0); }
  @media (max-width: 380px) { .fd-social-btns { flex-direction: column; } }
</style>
