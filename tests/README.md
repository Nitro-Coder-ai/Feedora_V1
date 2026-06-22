# Feedora E2E Tests (Playwright)

End-to-end suite covering the core authenticated journeys: signup, signin,
dashboard feed, post creation, comments, reactions, and stories.

## One-time setup

1. **Node deps + browser**
   ```bash
   npm install
   npx playwright install chromium
   ```

2. **Test database is automatic.** `global-setup.ts` creates + seeds a throwaway
   `Feedora_test` from `Feedora.sql` at the start of every run, and
   `global-teardown.ts` drops it afterward — so `Feedora` is the only standing
   database and the dev DB is never touched. No manual DB step needed.

   The setup shells out to MAMP's mysql client at
   `C:\MAMP\bin\mysql\bin\mysql.exe`; override with the `MYSQL_BIN` env var if
   yours differs.

## Running

```bash
npm test               # headless, all specs
npm run test:headed    # see the browser
npm run test:ui        # Playwright UI mode
npm run report         # open the last HTML report
```

`playwright.config.ts` auto-starts a dedicated PHP server on **:8001** with
`FEEDORA_DB=Feedora_test` (read by `config/config.php`), so the dev server on
:8000 and the dev database are never involved.

## How it works

- **Auth:** `auth.setup.ts` (the `setup` project) registers/logs-in a fixed test
  account once, seeds a post if the feed is empty, and saves `storageState` to
  `.auth/user.json`. The `chromium` project reuses that session.
- **Page objects:** `tests/pages/*` (SignUpPage, SignInPage, DashboardPage).
- **Selectors of note:** post cards carry `data-post-id`; reactions use
  `.post-likes-container` + `.reaction-icon[data-reaction]`; comments use
  `#commentsModal/#commentInput/#commentSubmit/#comment-count-<id>`; real stories
  are `a.story-item` (the static "Add Story" tile is `.story-item.add-story`).
- **Determinism:** specs use text-only posts (no live Cloudinary calls). The
  `php -S` server is single-threaded, so navigations wait for `domcontentloaded`
  rather than full `load`.

## Notes
- The stories spec self-skips when the test DB has no active stories.
- Resetting the DB: re-run the load command above to start clean.
