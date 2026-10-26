<?php
/**
 * Feedora authentication configuration + helpers:
 *   - Social login (Google / Facebook) OAuth 2.0 credentials
 *   - SMTP settings for the email-verification message
 *   - Small helpers used by oauth/*.php, sign-up.php, sign-in.php, verify-email.php
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  CONFIGURE ME — paste your credentials below (or set them as env vars).  │
 * │  Everything is read via getenv() first, so you can keep secrets out of   │
 * │  the repo by exporting FEEDORA_GOOGLE_CLIENT_ID, etc.                     │
 * └─────────────────────────────────────────────────────────────────────────┘
 */

// Secrets are loaded from (in order of precedence):
//   1. FEEDORA_* environment variables
//   2. config/secrets.php  (gitignored — copy from config/secrets.sample.php)
// This file itself contains NO secrets, so it is safe to commit.
$__secrets = is_file(__DIR__ . '/secrets.php') ? (require __DIR__ . '/secrets.php') : [];
if (!function_exists('__auth_secret')) {
    function __auth_secret(array $s, string $envKey, string $key, string $default = ''): string
    {
        $env = getenv($envKey);
        if ($env !== false && $env !== '') return $env;
        $value = (string) ($s[$key] ?? $default);
        // A template placeholder (PASTE_YOUR_..._HERE) means "not set": the feature stays off
        return stripos($value, 'PASTE_YOUR_') === 0 ? $default : $value;
    }
}

// ── Google OAuth 2.0 ────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     __auth_secret($__secrets, 'FEEDORA_GOOGLE_CLIENT_ID',     'GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', __auth_secret($__secrets, 'FEEDORA_GOOGLE_CLIENT_SECRET', 'GOOGLE_CLIENT_SECRET'));

// ── Facebook Login ──────────────────────────────────────────────────────────
define('FACEBOOK_APP_ID',      __auth_secret($__secrets, 'FEEDORA_FACEBOOK_APP_ID',      'FACEBOOK_APP_ID'));
define('FACEBOOK_APP_SECRET',  __auth_secret($__secrets, 'FEEDORA_FACEBOOK_APP_SECRET',  'FACEBOOK_APP_SECRET'));

// ── SMTP (email verification) ───────────────────────────────────────────────
define('SMTP_HOST',      __auth_secret($__secrets, 'FEEDORA_SMTP_HOST',      'SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT',      __auth_secret($__secrets, 'FEEDORA_SMTP_PORT',      'SMTP_PORT', '465'));
define('SMTP_SECURE',    __auth_secret($__secrets, 'FEEDORA_SMTP_SECURE',    'SMTP_SECURE', 'ssl')); // 'ssl' (465) or 'tls' (587)
define('SMTP_USER',      __auth_secret($__secrets, 'FEEDORA_SMTP_USER',      'SMTP_USER'));
define('SMTP_PASS',      __auth_secret($__secrets, 'FEEDORA_SMTP_PASS',      'SMTP_PASS'));
define('SMTP_FROM',      __auth_secret($__secrets, 'FEEDORA_SMTP_FROM',      'SMTP_FROM', SMTP_USER ?: 'no-reply@feedora.local'));
define('SMTP_FROM_NAME', __auth_secret($__secrets, 'FEEDORA_SMTP_FROM_NAME', 'SMTP_FROM_NAME', 'Feedora'));

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Absolute base URL of this install, e.g. http://localhost:8080 (used to build
 *  OAuth redirect URIs + verification links). Override with FEEDORA_BASE_URL. */
if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        $env = getenv('FEEDORA_BASE_URL');
        if ($env) return rtrim($env, '/');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('google_oauth_configured')) {
    function google_oauth_configured(): bool { return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== ''; }
    function facebook_oauth_configured(): bool { return FACEBOOK_APP_ID !== '' && FACEBOOK_APP_SECRET !== ''; }
    function mailer_configured(): bool { return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== ''; }
    function google_redirect_uri(): string { return app_base_url() . '/oauth/google.php'; }
    function facebook_redirect_uri(): string { return app_base_url() . '/oauth/facebook.php'; }
}

/** Cryptographically-random single-use token (hex). */
if (!function_exists('make_verification_token')) {
    function make_verification_token(): string { return bin2hex(random_bytes(32)); }
    function verification_link(string $token): string
    {
        return app_base_url() . '/verify-email.php?token=' . urlencode($token);
    }
    /**
     * We store only the SHA-256 of the token in the DB, never the raw value —
     * so a database read leak cannot be used to verify/hijack an account. The
     * raw token lives only in the emailed link. (sha256 hex = 64 chars, fits
     * VERIFICATION_TOKEN varchar(64).)
     */
    function hash_verification_token(string $token): string { return hash('sha256', $token); }
}

/**
 * Block disposable / throwaway email providers and undeliverable domains at
 * signup so spammers can't churn accounts. Returns [allowed, reason].
 * OAuth logins skip this (the provider already vouches for the address).
 */
if (!function_exists('email_is_allowed')) {
    function disposable_email_domains(): array
    {
        return [
            'mailinator.com', 'yopmail.com', 'guerrillamail.com', 'guerrillamail.info',
            'sharklasers.com', 'grr.la', '10minutemail.com', '10minutemail.net',
            'tempmail.com', 'temp-mail.org', 'tempmailo.com', 'throwawaymail.com',
            'trashmail.com', 'getnada.com', 'dispostable.com', 'fakeinbox.com',
            'maildrop.cc', 'mailnesia.com', 'moakt.com', 'emailondeck.com',
            'tempr.email', 'mohmal.com', 'mytemp.email', 'spam4.me', 'mintemail.com',
            'discard.email', 'inboxkitten.com', 'mailcatch.com', 'tempinbox.com',
            'burnermail.io', 'anonaddy.me', 'nada.email', 'tmpmail.org', 'trbvm.com',
        ];
    }

    function email_is_allowed(string $email): array
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) return [false, 'Please enter a valid email address.'];
        $domain = substr($email, $at + 1);
        if ($domain === '' || strpos($domain, '.') === false) {
            return [false, 'Please enter a valid email address.'];
        }
        if (in_array($domain, disposable_email_domains(), true)) {
            return [false, 'Disposable or temporary email addresses are not allowed. Please use a permanent email.'];
        }
        // Deliverability: the domain must actually accept mail (MX, or at least A).
        // Skipped for local/test domains so dev + E2E aren't blocked.
        $isDev = str_ends_with($domain, '.local') || str_ends_with($domain, '.test')
            || getenv('FEEDORA_DB') === 'Feedora_test';
        if (!$isDev && function_exists('checkdnsrr')) {
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                return [false, "That email domain can't receive mail. Please use a valid email address."];
            }
        }
        return [true, ''];
    }
}

/**
 * Send the "confirm your email" message. Returns [ok, error].
 * If SMTP isn't configured, returns [false, 'SMTP not configured'] so callers
 * can fall back gracefully (e.g. show the link on-screen in dev).
 */
if (!function_exists('send_verification_email')) {
    function send_verification_email(string $toEmail, string $toName, string $token): array
    {
        if (!mailer_configured()) return [false, 'SMTP not configured'];
        require_once __DIR__ . '/../includes/Mailer.php';
        $link = verification_link($token);
        $safeName = htmlspecialchars($toName, ENT_QUOTES);
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        // Feedora brand icon delivered as a raster PNG (email clients don't render SVG).
        $logo = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_png,q_auto,w_120/v1782090013/feedora/assets/Frame-1171277973-svg.svg';

        // Table-based, inline-styled markup for maximum email-client compatibility.
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light"></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f7;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:28px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #eceef1;font-family:Arial,Helvetica,sans-serif;">'
            // ── Header: primary-color band with the logo icon on top ──
            . '<tr><td align="center" style="background:#ED5A2C;padding:30px 24px;">'
            . '<img src="' . $logo . '" width="56" height="56" alt="Feedora" style="display:block;margin:0 auto 10px;border-radius:14px;background:#ffffff;padding:8px;">'
            . '<div style="color:#ffffff;font-size:24px;font-weight:bold;letter-spacing:.3px;">Feedora</div>'
            . '</td></tr>'
            // ── Body ──
            . '<tr><td style="padding:32px 34px 8px;">'
            . '<h1 style="font-size:21px;color:#1c1e21;margin:0 0 14px;">Confirm your email</h1>'
            . '<p style="color:#4b5262;line-height:1.65;font-size:15px;margin:0 0 8px;">Hi ' . $safeName . ', welcome to Feedora! '
            . 'Confirm your email address to activate your account and start sharing recipes, going live, and connecting with cooks.</p>'
            . '</td></tr>'
            . '<tr><td align="center" style="padding:22px 34px 6px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" bgcolor="#ED5A2C" style="border-radius:999px;">'
            . '<a href="' . $safeLink . '" target="_blank" style="display:inline-block;padding:14px 34px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;border-radius:999px;">Verify my email</a>'
            . '</td></tr></table></td></tr>'
            . '<tr><td style="padding:18px 34px 4px;">'
            . '<p style="color:#8a93a3;font-size:13px;line-height:1.6;margin:0;">Or paste this link into your browser:<br>'
            . '<a href="' . $safeLink . '" style="color:#ED5A2C;word-break:break-all;">' . $safeLink . '</a></p>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 34px 30px;">'
            . '<p style="color:#9aa0ab;font-size:12px;line-height:1.6;margin:0;">This link expires in 24 hours. '
            . 'If you didn\'t create a Feedora account, you can safely ignore this email.</p>'
            . '</td></tr>'
            // ── Footer ──
            . '<tr><td style="background:#fafbfc;border-top:1px solid #eceef1;padding:18px 34px;">'
            . '<p style="color:#aab0bb;font-size:12px;margin:0;text-align:center;">© ' . date('Y') . ' Feedora · Cook, share &amp; connect</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        // Plain-text alternative (multipart) — big anti-spam win.
        $text = "Confirm your Feedora email\n\n"
            . "Hi " . $toName . ", welcome to Feedora!\n\n"
            . "Confirm your email address to activate your account:\n"
            . $link . "\n\n"
            . "This link expires in 24 hours. If you didn't create a Feedora account, ignore this email.\n\n"
            . "© " . date('Y') . " Feedora";

        $mailer = new Mailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE, SMTP_FROM, SMTP_FROM_NAME);
        return $mailer->send($toEmail, $toName, 'Confirm your Feedora email', $html, $text);
    }
}

/** Small cURL JSON/OAuth helpers shared by the provider callbacks. */
if (!function_exists('oauth_http_post')) {
    function oauth_http_post(string $url, array $fields): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? (json_decode($res, true) ?: null) : null;
    }
    function oauth_http_get(string $url, array $headers = []): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? (json_decode($res, true) ?: null) : null;
    }
}

/**
 * Find-or-create a user for a social login, then return the USER_ID.
 * Social accounts are treated as email-verified (the provider vouches for it).
 * Links to an existing local account with the same email instead of duplicating.
 */
if (!function_exists('oauth_upsert_user')) {
    function oauth_upsert_user(PDO $pdo, string $provider, string $oauthId, string $email, string $name, ?string $picture, bool $emailVerified = false): int
    {
        // 1) Already linked by provider id? (always safe — proves control of the
        //    social identity itself.)
        $s = $pdo->prepare("SELECT USER_ID FROM USERS WHERE AUTH_PROVIDER = ? AND OAUTH_ID = ? LIMIT 1");
        $s->execute([$provider, $oauthId]);
        if ($row = $s->fetch(PDO::FETCH_ASSOC)) return (int) $row['USER_ID'];

        // SECURITY: only a PROVIDER-VERIFIED email may be trusted for linking to
        // (or storing on) an account. Linking by an unverified email lets an
        // attacker who claims a victim's address take over the victim's local
        // account. Unverified/absent emails create a fresh, unlinked account
        // keyed solely by the provider id.
        $trustedEmail = ($emailVerified && $email !== '') ? $email : '';

        // 2) Existing account with the same VERIFIED email? Link it.
        if ($trustedEmail !== '') {
            $s = $pdo->prepare("SELECT USER_ID FROM USERS WHERE EMAIL = ? LIMIT 1");
            $s->execute([$trustedEmail]);
            if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) $row['USER_ID'];
                $pdo->prepare("UPDATE USERS SET AUTH_PROVIDER = ?, OAUTH_ID = ?, EMAIL_VERIFIED = 1 WHERE USER_ID = ?")
                    ->execute([$provider, $oauthId, $uid]);
                return $uid;
            }
        }

        // 3) Brand-new social account (no password, onboarded=0). Store the email
        //    only when the provider verified it — otherwise leave it NULL so we
        //    never assert an address the user hasn't proven they control.
        $ins = $pdo->prepare(
            "INSERT INTO USERS (NAME, EMAIL, PASSWORD_HASH, AUTH_PROVIDER, OAUTH_ID, EMAIL_VERIFIED, PROFILE_IMAGE, ONBOARDED)
             VALUES (?, ?, NULL, ?, ?, 1, ?, 0)"
        );
        $ins->execute([$name ?: 'Feedora Cook', $trustedEmail ?: null, $provider, $oauthId, $picture]);
        return (int) $pdo->lastInsertId();
    }
}
