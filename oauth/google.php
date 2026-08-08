<?php
/**
 * Google OAuth 2.0 — single endpoint for both the outbound redirect and the
 * callback (Google returns here with ?code=...). Registered redirect URI must
 * be exactly:  {BASE_URL}/oauth/google.php
 */
require_once __DIR__ . '/../config/config.php';
session_start();

function goog_fail(string $msg): void
{
    $_SESSION['error'] = $msg;
    header('Location: ' . app_base_url() . '/sign-in.php');
    exit;
}

if (!google_oauth_configured()) {
    goog_fail('Google sign-in is not configured yet.');
}

// ── Callback leg: Google redirected back with a code ────────────────────────
if (isset($_GET['code'])) {
    if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
        || !hash_equals($_SESSION['oauth_state'], (string) $_GET['state'])) {
        goog_fail('Login session expired. Please try again.');
    }
    unset($_SESSION['oauth_state']);

    $tok = oauth_http_post('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    if (!$tok || empty($tok['access_token'])) {
        goog_fail('Could not complete Google sign-in. Please try again.');
    }

    $me = oauth_http_get('https://www.googleapis.com/oauth2/v3/userinfo',
        ['Authorization: Bearer ' . $tok['access_token']]);
    if (!$me || empty($me['sub'])) {
        goog_fail('Could not read your Google profile. Please try again.');
    }

    try {
        // Google returns email_verified; only trust the email for account
        // linking when Google itself has verified it (guards account takeover).
        $emailVerified = !empty($me['email_verified'])
            && ($me['email_verified'] === true || $me['email_verified'] === 'true');
        $uid = oauth_upsert_user(
            $pdo, 'google', (string) $me['sub'],
            (string) ($me['email'] ?? ''), (string) ($me['name'] ?? ''),
            $me['picture'] ?? null, $emailVerified
        );
        $u = $pdo->prepare("SELECT NAME, EMAIL, PROFILE_IMAGE, ONBOARDED FROM USERS WHERE USER_ID = ?");
        $u->execute([$uid]);
        $row = $u->fetch(PDO::FETCH_ASSOC);

        session_regenerate_id(true);
        $_SESSION['user_id']    = $uid;
        $_SESSION['user_name']  = $row['NAME'] ?? 'Feedora Cook';
        $_SESSION['user_email'] = $row['EMAIL'] ?? '';
        if (!empty($row['PROFILE_IMAGE'])) $_SESSION['profile_image'] = $row['PROFILE_IMAGE'];

        header('Location: ' . app_base_url() . '/' . ((int) ($row['ONBOARDED'] ?? 0) === 0 ? 'onboarding.php' : 'dashboard.php'));
        exit;
    } catch (Throwable $e) {
        error_log('[oauth-google] ' . $e->getMessage());
        goog_fail('Sign-in failed. Please try again.');
    }
}

// ── Start leg: send the user to Google ──────────────────────────────────────
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => google_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['oauth_state'],
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
