# Claude Code Memory: Workspace Context & Optimization Log

This file acts as a persistent memory and state tracker for **Claude Code** to track current changes, configuration parameters, and phase progress, minimizing context-rebuilding costs.

*CLAUDE: Update this file at the end of every task/phase to maintain a state log and save tokens.*

---

## ⚙️ Active Environment Configuration

### MySQL Database (MAMP)
- **Host**: `localhost`
- **User**: `root`
- **Password**: `root`
- **Database Name**: `Feedora`
- **Schema Reference**: [Feedora.sql](file:///c:/Users/moham/Desktop/Feedora_Project_Full/Feedora.sql)

### Cloudinary CDN
- **Cloud Name**: `dio1bvxbv`
- **API Key**: `692575323714231`
- **API Secret**: `W1EIxjVOBGwVGJLuekgCw89AUNI`
- **Defined constants in config.php**: `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`

---

## 🛠️ Modifications Log (What Has Been Done Already)

The initial schema fixes, performance indexes, and basic notification triggers have been successfully implemented on the `optimization` branch:

1. **MySQL Syntax Compatibility & MAMP Fixes** (`Feedora.sql`):
   - Removed all `IF NOT EXISTS` from index creations (not supported in standard MySQL/MAMP).
   - Changed `EXPIRES_AT` default in `STORIES` to `NULL` (removed expression default `CURRENT_TIMESTAMP + INTERVAL 24 HOUR` which caused error #1064).
   - Changed `VISIBILITY` in `STORIES` from `TEXT DEFAULT 'public'` to `VARCHAR(50) DEFAULT 'public'` (standardized to avoid BLOB/TEXT default error #1101).
   - Added missing performance indexes for `POSTS`, `RECIPES`, `STORIES`, `COMMENTS`, and `NOTIFICATIONS`.
2. **Redundant Schema Checks Removed** (`chefs.php`):
   - Removed the slow `CREATE TABLE IF NOT EXISTS FOLLOWERS` query that ran on every follow toggle request.
3. **Database & Cloudinary Configurations** (`config/config.php`):
   - Updated MySQL password to `'root'` for MAMP.
   - Appended Cloudinary API credentials.
4. **Index/Routing Redirect** (`index.php` [NEW]):
   - Created root page router redirecting `/` to `Home.php`.
5. **Notification Integration Hooks**:
   - Wired up followers notification trigger in `recipes.php` when a new recipe is uploaded.
   - Wired up post owner notification trigger in `comments.php` when a new comment is added.
   - Wired up reaction notification trigger in `post_reaction.php` when a reaction is added.

---

## 📈 Phase Tracking (State Log)

| Phase | Description | Status | Files Modified / Scope |
| :--- | :--- | :--- | :--- |
| **Phase 1** | Directory Restructuring & Modularization (CSS/JS extraction, wrapper fallbacks) | **Pending** | Extract styles/scripts from `dashboard.php`, `chefs.php` |
| **Phase 2** | Client-side Image Compression (Canvas JS) & LocalStorage/IndexedDB Caching | **Done** | Compressor (`assets/js/compressor.js`, WebP q0.8). Caching DONE: `assets/js/cache-manager.js` (IndexedDB, 1h TTL). Loaded site-wide via header.php + Home.php. Verified live: 28 cache-hit imgs / 18 IDB entries on Home reload, 0 console errors. |
| **Phase 3** | Cloudinary integration uploads for posts, recipes, stories, profiles | **Done (uploads)** | `includes/CloudinaryService.php` (signed cURL, local fallback). Wired: post.php, recipes.php, story.php (via StoryFileHandler), profile_settings.php (banner + edit-post). All `php -l` clean. |
| **Phase 4** | Web Vitals & SEO tags (Recipes schema markup) | **Done** | JSON-LD Recipe schema + OG/Twitter/canonical in view-recipe.php; OG on Home.php; loading="lazy"+decoding="async" on feed/list imgs (dashboard, recipes, profile_details). All `php -l` clean; JSON-LD validated via CLI render of recipe #1. |

*Note: Update the table above as you make progress in the project.*

---

## 📝 Phase 2/3 Implementation Notes (session 2026-06-21)

**New files:** `includes/CloudinaryService.php`, `assets/js/compressor.js`.
**Modified:** `post.php`, `recipes.php`, `profile_settings.php`, `includes/StoryFileHandler.php`, `header.php` (script include).

- **CloudinaryService**: dependency-free signed upload via cURL to `/auto/upload`; returns `secure_url`. Every caller **falls back to local `uploads/` disk** if Cloudinary is unconfigured/unreachable → zero downtime preserved. `transform()` helper adds `f_auto,q_auto`.
- **compressor.js**: Canvas→WebP @0.8, downscale longest edge to 1600px, swaps input FileList via DataTransfer. Skips GIF/video. Auto-attaches to image file inputs site-wide (loaded once in `header.php`).
- **Schema**: `USERS.PROFILE_IMAGE`/`BANNER_IMAGE` are VARCHAR(255); typical Cloudinary URLs ~95 chars → no migration needed.

### ✅ Follow-ups completed (session 2026-06-21 cont.)
- **Avatar upload wired** in `settings.php` (`action=update_profile_image`) → Cloudinary `feedora/profiles` → `USERS.PROFILE_IMAGE`, local fallback, old local file deleted only if non-URL. `php -l` clean.
- **Reversible backfill CLI**: `scripts/backfill_to_cloudinary.php` (CLI-only). Default = migrate local `uploads/...` paths in POSTS/RECIPES/USERS/STORIES → Cloudinary, update DB, KEEP local files, write `scripts/backfill_manifest.json`. `--cleanup` deletes a local file ONLY after re-confirming the DB row equals the migrated URL. Also `--dry-run`, `--table=`. `php -l` clean; dry-run verified live (found USERS#3 profile SVG).

### ✅ Follow-ups completed (session 2026-06-21 cont. 2)
- **Home.js reaction-init bug FIXED**: early null-guard in `initializeReactions()` (returns if `#global-reaction-container` / `.reaction-icons` absent) + defensive guards in the reaction-click handler. Verified: Home.php fresh load now has 0 console errors (was `TypeError ...addEventListener` at line 1296).
- **Browser image cache** `assets/js/cache-manager.js`: IndexedDB store `feedora-cache/images`, 1h TTL, auto-evicts expired, MutationObserver for AJAX-added imgs, CORS/offline failures swallowed (network src always preserved). Auto-targets Cloudinary/uploads/images srcs; opt-out via `data-no-cache`. Loaded via header.php (authed pages) AND Home.php (landing). All `php -l` clean.

### ✅ Phase 4 completed (session 2026-06-21 cont. 3)
- **view-recipe.php**: SEO-prep block before `<head>` builds `$seoJsonLd` (Recipe schema) + meta values. Handles Cloudinary (absolute) and local PHOTO_URL via `$feedoraAbsUrl`. JSON encoded with `JSON_HEX_TAG` (no `</script>` breakout). Injected: dynamic `<meta description>`, canonical, og:* , twitter:* , and `<script type="application/ld+json">`. Ingredients/steps built from DB; null/empty fields filtered out. Verified via CLI render of recipe #1 → valid JSON, PT15M/PT20M/PT35M, 5 steps.
- **Home.php**: og:* + twitter:* tags added to `<head>` (landing page share cards).
- **Lazy loading (CLS/CWV)**: `loading="lazy" decoding="async"` added to below-the-fold feed/list images: dashboard.php#2780 (post img), profile_details.php#2200 (post) & #2285 (recipe), recipes.php#527/#529 (recipe grid). LCP/above-fold images (banners, header recipe img, avatars, composer) deliberately left eager.
- **Note**: explicit width/height NOT added — sizes are controlled by existing CSS classes; guessing pixel dims could distort. CSS classes bound layout so CLS is limited. Tunable later if exact intrinsic dims are known.

### ✅ Playwright E2E suite (session 2026-06-21 cont. 4)
- Committed `@playwright/test` project: `package.json`, `playwright.config.ts`, `tests/` (POMs in `tests/pages/`, `fixtures.ts`, `constants.ts`, `auth.setup.ts`, 7 specs), `tests/README.md`. `.gitignore` updated (node_modules, .auth, reports).
- **Isolation**: dedicated `Feedora_test` DB (from Feedora.sql); `config/config.php` now reads `getenv('FEEDORA_DB') ?: 'Feedora'`. Playwright webServer runs `php -S 127.0.0.1:8001` with `FEEDORA_DB=Feedora_test` — dev :8000 / dev DB never touched (verified: 0 e2e users in dev Feedora).
- **Auth**: `auth.setup.ts` signs up/logs in fixed test account, seeds a post, saves `.auth/user.json` storageState; chromium project reuses it.
- **Result**: 9 passed, 1 skipped (stories — test DB has no active stories). Run via `npm test`.
- **Gotchas learned**: php -S is single-threaded on Windows → navigations use `waitUntil:'domcontentloaded'`. Post id is on `.post-card[data-post-id]` (NOT `id`). Real stories = `a.story-item` (static "Add Story" tile = `.story-item.add-story`). Sign-in success redirects to `profile.php` (not dashboard).
- MAMP mysql client: `C:\MAMP\bin\mysql\bin\mysql.exe`, port 3306, root/root.
- **Project-doc conflict**: `CLAUDE.md`/`.claude/SKILL.md` still forbid Playwright; user explicitly opted in. Update those docs to match.

### ✅ Schema optimization (session 2026-06-21 cont. 5)
- **`Feedora.sql` rewritten (optimized, backward-compatible)**: all existing table/column names preserved (app + E2E unaffected). Added: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4` on every table (emoji-safe), right-sized types (VARCHAR vs TEXT, SMALLINT UNSIGNED vs DECIMAL), Cloudinary `*_PUBLIC_ID` columns (USERS x2, POSTS, RECIPES, STORIES, MESSAGES) + widened PROFILE/BANNER to VARCHAR(512), `POSTS/STORIES.MEDIA_TYPE`, `TAGS`+`RECIPE_TAGS`, FULLTEXT on RECIPES(TITLE,INSTRUCTIONS) & POSTS(DESCRIPTION), analytics views `RECIPE_SAVE_COUNTS`/`USER_FOLLOW_COUNTS`, plus save/follow/comment indexes. Seeds use INSERT IGNORE.
- **MySQL 5.7.24** (MAMP). Validated: clean load = 0 errors, 22 tables + 4 views, FULLTEXT search works, all utf8mb4.
- **`scripts/2026_schema_optimize.sql`**: idempotent ADDITIVE migration for an existing DB (stored-proc guards `feedora_add_column`/`feedora_add_index`). Validated on a structure clone of the real dev Feedora — 2 passes, 0 errors, idempotent. Apply with a backup; charset CONVERT step is commented/optional.
- **`Feedora_test` rebuilt** on the optimized schema; full Playwright suite re-run → still **9 passed / 1 skipped** (backward compat proven).
- Dev `Feedora` DB was NOT modified (user chose "optimize schema file" only). To apply to live: backup → run the migration.

### ✅ DB rebuild + Cloudinary upload test (session 2026-06-21 cont. 6)
- **Dev `Feedora` rebuilt** from optimized Feedora.sql (backup taken first: `Feedora_backup_20260621_170217.sql`, gitignored). 22 tables, 4 views, utf8mb4, seed OK.
- **Cloudinary upload test: 3/3 uploaded** (jpg/png/webp) → real secure_urls, stored in POSTS with IMAGE_PUBLIC_ID, HTTP 200 reachable, public_id round-trip via parseUrl() matches. 3 test POST rows (#1-3) now in dev Feedora.
- **✅ RESOLVED — PHP CA bundle**: The `:8000` dev server runs **PHP 8.4 from `C:\php`** (MAMP only ships ≤8.3.1, so MAMP php.ini is NOT the active one). Installed CA bundle at `C:\php\cacert.pem` (150 certs, copied from Git, Git-independent) and set `curl.cainfo` + `openssl.cafile` to it in `C:\php\php.ini` (lines ~1820/1829). Verified: `php -i` shows the values; default-config upload (no `-d`) returns a real secure_url. **The running php.exe (PID 6656) must be restarted to pick this up** — restart the `:8000` server. (MAMP php8.x inis were NOT changed; fix them too only if the app is ever served via MAMP.)

### ✅ Single-DB setup (session 2026-06-21 cont. 7)
- Per user request, `Feedora_test` is NO LONGER a persistent DB. Only optimized **`Feedora`** stands (22 tables).
- Playwright now auto-manages the test DB: `tests/global-setup.ts` creates+seeds `Feedora_test` from `Feedora.sql` at run start; `tests/global-teardown.ts` drops it at run end. Wired via `globalSetup`/`globalTeardown` in playwright.config.ts. mysql client path overridable via `MYSQL_BIN` env (default `C:/MAMP/bin/mysql/bin/mysql.exe`). Verified: suite runs 9 passed/1 skipped, Feedora_test auto-created then dropped, dev Feedora untouched.

### ✅ Live search + header polish (session 2026-06-21 cont. 8)
- **`api/search.php`** (public JSON): FULLTEXT BOOLEAN-mode search (prefix `+term*`) over RECIPES(TITLE,INSTRUCTIONS) + POSTS(DESCRIPTION) + LIKE fallback; empty query = discover mode (latest recipes + TAGS). Distinct placeholders (`:b1/:b2`) required because config runs emulated-prepares OFF.
- **`assets/js/search.js`**: debounced live search, panel rendered on `<body>` fixed-positioned (avoids `.center-container{overflow:hidden}` clipping), staggered item animations, keyboard nav (↑/↓/Enter/Esc), tag-chip inline search. Attaches to `.search-input`/`.mobile-search-input`.
- **`assets/css/search.css`**: animated panel + sticky/professional header polish + responsive. Loaded after Home.css.
- **`images/default-recipe.svg`** created (the app referenced non-existent `default-recipe.jpg` → 404 everywhere; SVG is the new fallback in search).
- Wired into **Home.php only** so far (the page shown). To enable elsewhere add the same `<link>`+`<script>` to sign-in/up/profile/recipes and header.php (authed). Verified live: discover + "choc"/"spag" prefix search work, 0 console errors.
- **Refinement**: thumbnails now show the REAL DB image (PHOTO_URL/IMAGE_URL; videos treated as none). When absent/failed, a CSS **shimmer skeleton** (`.fd-skeleton`) stays instead of an icon. **Tags removed** from discover (API no longer queries TAGS; JS tag rendering+handler removed). Demo: set RECIPES#1 PHOTO_URL to a real Cloudinary URL → shows real image; RECIPES#2 (no photo) shows shimmer.

### ✅ Header responsive fix (session 2026-06-21 cont. 9)
- Root cause: my earlier search.css header block fought Home.css responsive rules — a GLOBAL `.search-container{width:150px}` + `:focus-within{width:230px}` overrode the tablet breakpoints (Home.css shrinks search to 120/100px at ≤1024/≤900), and `.center-container{overflow:visible}` was unnecessary (panel is on <body>) and re-allowed spill.
- Fix: removed those; kept only `header{position:sticky;top:12px}` + hover shadow, and gated the focus-expand to `@media(min-width:1025px)`. Home.css breakpoints (768px → hamburger) now intact.
- **Verified via chrome-devtools-mcp** (real CDP viewport — note: claude-in-chrome `resize_window` does NOT change the render viewport in this env, stays 1280): 390px = clean mobile (logo+hamburger), 800px = full nav fits in pill, 1280px = desktop + search panel opens unclipped.
- The earlier "broken header" screenshot was stale open-menu state, not a real bug (Home.js opens menu only on click; fresh load = closed).

### ✅ Scroll effects (session 2026-06-21 cont. 10)
- **`assets/js/scroll-fx.js` + `assets/css/scroll-fx.css`** on Home.php: (1) auto-hiding header (hide on scroll-down past 90px, show on scroll-up; rAF + passive listener; `header.fd-nav-hidden{translateY(-100%-30px)}`); (2) section reveal — all `<section>`+`.footer-section` fade+slide-up via IntersectionObserver (once), hero fades in on load.
- No-FOUC: inline `<head>` script adds `.fd-js` to <html> before paint; CSS hides sections only under `.fd-js` (no-JS = visible). Resilient: reveal-all fallback if no IntersectionObserver + 4.5s safety timeout. Respects prefers-reduced-motion.
- Verified via chrome-devtools CDP: down→hidden:true, up→hidden:false, near-top→visible; 9/9 sections reveal. php -l + node --check clean; assets 200.
- Note: only on Home.php (scroll-fx assets not loaded on authed header.php pages).
- **Hamburger fix**: `#mobileNav` is `position:fixed` but a CHILD of `<header>`; my scroll-fx `header{will-change:transform}` made header its containing block → menu trapped in a 460×89 header box. Removed `will-change` (and any static transform) from header; added `header:has(.mobile-menu.active){transform:none!important}` so the hide-transform never traps the open menu. Verified via CDP: open menu = full viewport (inset:0, 500×587, z1000), close restores. KEY RULE: never put transform/will-change/filter on `<header>` while the fixed mobile-nav lives inside it.

### ⛔ Still NOT done / blocked (deliberate)
- **Blind uploads/ deletion: still REFUSED.** Use the backfill script's verified `--cleanup` instead.
- ~~view-recipe.php edit handler local upload~~ — DONE: edit-recipe photo now uploads to Cloudinary (`feedora/recipes`) with local fallback; old local photo deleted only if non-URL. `php -l` clean. Every media upload path is now Cloudinary-wired (post create/edit, recipe create/edit, story, avatar, banner).
- **Note**: recipe delete handler (`view-recipe.php` ~line 50) only `unlink()`s local PHOTO_URL; Cloudinary assets are left as orphans on delete (harmless, no error). Add Cloudinary destroy() later if storage cleanup is wanted.
- **Full upload verification** blocked: all upload pages require login (cannot create account / enter password). Verified instead: all `php -l` clean, compressor.js `node --check` OK + HTTP 200, dashboard.php redirects cleanly (no fatal from new includes).

### ⚠️ Security
- Cloudinary **API secret committed** in `config/config.php` — rotate + move to env before public.
- Pre-existing JS bug (not mine): `Home.js:1296 initializeReactions` null `addEventListener` on pages without dashboard elements (e.g. sign-in.php).
