<?php
/**
 * Template for config/secrets.php (which is gitignored). All entries are optional.
 *
 *   1. Copy this file to  config/secrets.php
 *   2. Replace the PASTE_YOUR_..._HERE values you want to use
 *
 * A value left as PASTE_YOUR_..._HERE counts as "not set": that feature is simply
 * turned off (no Google/Facebook button; email verification shows the link on
 * screen in development instead of sending an email).
 * FEEDORA_* environment variables (docs/AUTH_SETUP.md) override this file.
 */
return [
    // Google sign-in: https://console.cloud.google.com/apis/credentials
    'GOOGLE_CLIENT_ID'     => 'PASTE_YOUR_GOOGLE_CLIENT_ID_HERE',      // xxxx.apps.googleusercontent.com
    'GOOGLE_CLIENT_SECRET' => 'PASTE_YOUR_GOOGLE_CLIENT_SECRET_HERE',  // GOCSPX-...

    // Facebook login: https://developers.facebook.com/apps
    'FACEBOOK_APP_ID'      => 'PASTE_YOUR_FACEBOOK_APP_ID_HERE',
    'FACEBOOK_APP_SECRET'  => 'PASTE_YOUR_FACEBOOK_APP_SECRET_HERE',

    // Email verification (SMTP). Gmail: use an App Password, not your login password.
    'SMTP_HOST'      => 'smtp.gmail.com',
    'SMTP_PORT'      => '465',
    'SMTP_SECURE'    => 'ssl',      // 'ssl' (465) or 'tls' (587)
    'SMTP_USER'      => 'PASTE_YOUR_EMAIL_ADDRESS_HERE',
    'SMTP_PASS'      => 'PASTE_YOUR_EMAIL_APP_PASSWORD_HERE',
    'SMTP_FROM'      => 'PASTE_YOUR_EMAIL_ADDRESS_HERE',   // usually the same as SMTP_USER
    'SMTP_FROM_NAME' => 'Feedora',
];
