---
name: feedora-frontend-build
description: Build Feedora vanilla CSS/JS UI, wire AJAX to api/ endpoints against the backend response contract, and run the esbuild asset pipeline (npm run build). Use whenever a feature needs UI, client-side JS, AJAX wiring, styling, responsive layout, or an asset rebuild. Triggers on "build the UI", "style", "wire up the fetch", "the page should show", "rebuild assets", and on re-runs to fix a consumer mismatch.
---

# Feedora Frontend & Build

Build the client side and wire it to `api/`. You consume the backend response contract and emit the **consumer map** for QA.

## Workflow
1. **Read the response contract** in `_workspace/02_backend_<feature>.md`. Read the matching sibling module in `assets/js/` (`story.js`, `chat.js`, `dashboard.js`) for the established vanilla-JS style — no framework.
2. **Fetch against exact keys.** Read response fields exactly as documented. A `data.items` vs `data.results` rename is the most common boundary bug — if a key looks wrong, raise it to backend; never guess-and-rename.
3. **Realtime:** extend the existing single adaptive visibility-aware poller for chat/story; do not add a second `setInterval` loop.
4. **Edit sources only:** `assets/css/*.css`, `assets/js/*.js`. Never hand-edit `*.min.*`. Reference assets via the `asset()` cache-busting helper.
5. **Run `npm run build`** (→ `scripts/build-assets.mjs`, esbuild) after every CSS/JS change to regenerate the min files. If it fails, fix the source — do not ship stale min files.
6. **Verify layout** desktop + mobile using the Playwright suite (`tests/`) or the Chrome extension MCP.
7. **Emit the consumer map** to `_workspace/03_frontend_<feature>.md`: per endpoint → which JS function calls it and which fields it reads/renders; plus files changed and confirmation the build ran.

## Why these rules
The build step is mandatory because the site serves `*.min.*`; editing source without rebuilding ships nothing. The consumer map exists so QA can diff "fields the JS reads" against "fields the API returns" — the highest-yield boundary check in this codebase.

## Done when
UI renders against real endpoint data with exact keys, `npm run build` succeeds, layout verified, and the consumer map is written for QA.
