<?php
/**
 * Facebook Login (OAuth 2.0) — single endpoint for both the outbound redirect
 * and the callback. Registered "Valid OAuth Redirect URI" must be exactly:
 *   {BASE_URL}/oauth/facebook.php
 */
require_once __DIR__ . '/../config/config.php';
session_start();

const FB_VER = 'v19.0';

function fb_fail(string $msg): void
{
    $_SESSION['error'] = $msg;
    header('Location: ' . app_base_url() . '/sign-in.php');
    exit;
}

if (!facebook_oauth_configured()) {
    fb_fail('Facebook sign-in is not configured yet.');
}
if (isset($_GET['error'])) {
    fb_fail('Facebook sign-in was cancelled.');
}

// ── Callback leg ────────────────────────────────────────────────────────────
if (isset($_GET['code'])) {
    if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
        || !hash_equals($_SESSION['oauth_state'], (string) $_GET['state'])) {
        fb_fail('Login session expired. Please try again.');
    }
    unset($_SESSION['oauth_state']);

    $tok = oauth_http_get('https://graph.facebook.com/' . FB_VER . '/oauth/access_token?' . http_build_query([
        'client_id'     => FACEBOOK_APP_ID,
        'redirect_uri'  => facebook_redirect_uri(),
        'client_secret' => FACEBOOK_APP_SECRET,
        'code'          => $_GET['code'],
    ]));
    if (!$tok || empty($tok['access_token'])) {
        fb_fail('Could not complete Facebook sign-in. Please try again.');
    }

    $me = oauth_http_get('https://graph.facebook.com/' . FB_VER . '/me?' . http_build_query([
        'fields'       => 'id,name,email,picture.type(large)',
        'access_token' => $tok['access_token'],
    ]));
    if (!$me || empty($me['id'])) {
        fb_fail('Could not read your Facebook profile. Please try again.');
    }

    $picture = $me['picture']['data']['url'] ?? null;
    try {
        // Facebook's Graph API does NOT tell us whether the email is verified,
        // and a Facebook email can be unverified — so we never trust it for
        // linking. Passing $emailVerified=false forces a fresh, unlinked account
        // (no takeover of an existing local account by email collision).
        $uid = oauth_upsert_user(
            $pdo, 'facebook', (string) $me['id'],
            (string) ($me['email'] ?? ''), (string) ($me['name'] ?? ''), $picture, false
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
        error_log('[oauth-facebook] ' . $e->getMessage());
        fb_fail('Sign-in failed. Please try again.');
    }
}

// ── Start leg ───────────────────────────────────────────────────────────────
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$params = http_build_query([
    'client_id'     => FACEBOOK_APP_ID,
    'redirect_uri'  => facebook_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
    'state'         => $_SESSION['oauth_state'],
]);
header('Location: https://www.facebook.com/' . FB_VER . '/dialog/oauth?' . $params);
exit;
