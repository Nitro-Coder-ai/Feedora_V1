<?php
/**
 * Template for config/secrets.php (which is gitignored).
 *
 *   1. Copy this file to  config/secrets.php
 *   2. Fill in your real credentials
 *
 * Alternatively, leave this file alone and set the FEEDORA_* environment
 * variables listed in docs/AUTH_SETUP.md — those take precedence over this file.
 */
return [
    'GOOGLE_CLIENT_ID'     => '',   // xxxx.apps.googleusercontent.com
    'GOOGLE_CLIENT_SECRET' => '',   // GOCSPX-...

    'FACEBOOK_APP_ID'      => '',
    'FACEBOOK_APP_SECRET'  => '',

    'SMTP_HOST'      => 'smtp.gmail.com',
    'SMTP_PORT'      => '465',
    'SMTP_SECURE'    => 'ssl',      // 'ssl' (465) or 'tls' (587)
    'SMTP_USER'      => '',         // full email address
    'SMTP_PASS'      => '',         // app password (not your login password)
    'SMTP_FROM'      => '',         // usually same as SMTP_USER
    'SMTP_FROM_NAME' => 'Feedora',
];
