# Feedora — Social Login & Email Verification Setup

Everything is already built and wired. To make it live, put **three sets of
credentials** into **`config/secrets.php`** (git-ignored — copy it from
`config/secrets.sample.php`) or set the matching `FEEDORA_*` environment
variables. Never put secrets in `config/auth.php` — that file is committed.
Until configured, the Google/Facebook buttons show a friendly "not configured"
notice, and signup falls back to a **dev link** shown on-screen.

`config/secrets.php` looks like:
```php
<?php return [
  'GOOGLE_CLIENT_ID'     => 'xxxx.apps.googleusercontent.com',
  'GOOGLE_CLIENT_SECRET' => 'GOCSPX-...',
  'FACEBOOK_APP_ID'      => '...',
  'FACEBOOK_APP_SECRET'  => '...',
  'SMTP_HOST' => 'smtp.gmail.com', 'SMTP_PORT' => '465', 'SMTP_SECURE' => 'ssl',
  'SMTP_USER' => 'you@gmail.com', 'SMTP_PASS' => 'app password',
  'SMTP_FROM' => 'you@gmail.com', 'SMTP_FROM_NAME' => 'Feedora',
];
```
The steps below explain where each value comes from.

Your base URL is currently **`http://localhost:8080`**. All redirect URIs below
use it. When you deploy, set `FEEDORA_BASE_URL=https://yourdomain.com` and
re-register the URIs with your real domain.

---

## 1. Google Sign-In

1. Go to <https://console.cloud.google.com/> → create/select a project.
2. **APIs & Services → OAuth consent screen** → choose *External* → fill app
   name, support email, developer email → Save. Add your Google account under
   **Test users** (while the app is in "Testing").
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
   - Application type: **Web application**
   - Authorized redirect URI (exactly):
     ```
     http://localhost:8080/oauth/google.php
     ```
4. Copy the **Client ID** and **Client secret** into `config/auth.php`:
   ```php
   define('GOOGLE_CLIENT_ID',     'xxxxxxxx.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxxxxxxxxxxx');
   ```

---

## 2. Facebook Login

1. Go to <https://developers.facebook.com/apps/> → **Create App** → type
   *Consumer* (or *None*) → give it a name.
2. In the app, add the **Facebook Login** product → **Settings**.
3. Under **Valid OAuth Redirect URIs** add (exactly):
   ```
   http://localhost:8080/oauth/facebook.php
   ```
4. **App Settings → Basic**: copy **App ID** and **App Secret** into
   `config/auth.php`:
   ```php
   define('FACEBOOK_APP_ID',     '123456789012345');
   define('FACEBOOK_APP_SECRET', 'abcdef0123456789abcdef0123456789');
   ```
5. Add your Facebook account under **App Roles → Testers** while the app is in
   *Development* mode (or switch it Live once ready).

> **Security note:** Facebook's API doesn't tell us whether the returned email
> is verified, so Feedora **does not trust or store the Facebook email** and
> never links a Facebook login to an existing local account by email. Each
> Facebook login is its own account keyed by the Facebook user id (this prevents
> account-takeover by email spoofing). Google emails are trusted **only** when
> Google reports `email_verified = true`, in which case they may link to an
> existing account.

---

## 3. Email verification (SMTP)

The verification email is sent over SMTP — no library needed. Any provider
works. Easiest is a **Gmail App Password**:

1. Enable 2-Step Verification on your Google account.
2. <https://myaccount.google.com/apppasswords> → generate a 16-character
   **App Password** (pick "Mail").
3. Fill `config/auth.php`:
   ```php
   define('SMTP_HOST',      'smtp.gmail.com');
   define('SMTP_PORT',      '465');
   define('SMTP_SECURE',    'ssl');           // 'ssl' for 465, 'tls' for 587
   define('SMTP_USER',      'you@gmail.com');
   define('SMTP_PASS',      'your16charapppw'); // the App Password, no spaces
   define('SMTP_FROM',      'you@gmail.com');
   define('SMTP_FROM_NAME', 'Feedora');
   ```

Other providers (same shape):
| Provider   | Host                    | Port | Secure |
|------------|-------------------------|------|--------|
| Gmail      | smtp.gmail.com          | 465  | ssl    |
| Outlook    | smtp.office365.com      | 587  | tls    |
| Brevo      | smtp-relay.brevo.com    | 587  | tls    |
| SendGrid   | smtp.sendgrid.net       | 587  | tls    |

**Until SMTP is set**, signup still works — the verification link is printed on
the "Confirm your email" page so you can click it in dev.

---

## Prefer environment variables? (keeps secrets out of the repo)

Every value reads from `getenv()` first. Instead of editing `config/auth.php`,
export these before starting `php -S localhost:8080`:

```
FEEDORA_GOOGLE_CLIENT_ID, FEEDORA_GOOGLE_CLIENT_SECRET
FEEDORA_FACEBOOK_APP_ID,  FEEDORA_FACEBOOK_APP_SECRET
FEEDORA_SMTP_HOST, FEEDORA_SMTP_PORT, FEEDORA_SMTP_SECURE,
FEEDORA_SMTP_USER, FEEDORA_SMTP_PASS, FEEDORA_SMTP_FROM, FEEDORA_SMTP_FROM_NAME
FEEDORA_BASE_URL   (production only, e.g. https://feedora.app)
```

---

## What was added (for reference)

- DB: `USERS` gained `EMAIL_VERIFIED, VERIFICATION_TOKEN, VERIFICATION_EXPIRES,
  AUTH_PROVIDER, OAUTH_ID` (migration `scripts/2026_auth_oauth_email_verify.sql`,
  already applied). Existing accounts were grandfathered as verified.
- `oauth/google.php`, `oauth/facebook.php` — OAuth start + callback.
- `verify-email.php` — consumes the token, logs the user in.
- `verify-notice.php` — "check your inbox" + resend.
- `includes/Mailer.php` — tiny SMTP client. `config/auth.php` — config + helpers.
- Buttons added to `sign-in.php` / `sign-up.php`; signup no longer logs in until
  the email is verified; sign-in blocks unverified accounts.
