# Feature bundle: Chat poll-spam fix + clean 3-column redesign + Discord-style multi-party calls

One orchestrated pipeline. schema → backend → frontend → qa. Contracts pass by file
(`_workspace/0N_*.md`). Brand tokens in `_workspace/BRAND.md` are binding.

## User request (verbatim intent)
1. **Fix "spam messages requests."** Server log shows `POST /chat.php` every 30s while
   the user is on `Home.php`.
2. **Redesign the chat interface** to be clean/professional and **denser at 100% zoom**
   (currently "too big"). Inspiration: a 3-column messenger (Friends + Channels in one
   left rail, center conversation, right contact-info). Feedora brand.
3. New features → wire to MySQL backend directly.
4. **Fix calls/video like Discord** — multiple users interacting together (multi-party).
5. Priority: clean UI.

## Decisions locked with the user
- **Layout: collapse to clean 3-column.** Merge the existing left group-rail into the left
  sidebar as a "Channels/Groups" section beneath the conversation list (matches the
  inspiration). Tighten density throughout. Right info sidebar stays (overlay ≤1199px).
- **Calls: full Discord-style multi-party.** Mesh WebRTC with a participants table +
  per-peer offer/answer/ICE so 3+ users in a group can all see/hear each other in a
  video tile grid.

## ROOT-CAUSE already diagnosed (for backend/frontend — do not re-investigate)
- The chat poller (`assets/js/chat.js`, IIFE ~line 1098) is already adaptive
  (FAST=3s, SLOW=15s), pauses on `document.hidden`, single instance. It is NOT the spam.
- **The spam is `assets/js/sidebar.js`:** `setInterval(updateOnlineStatus, 30000)` fires
  `POST chat.php` with `action=update_status` on EVERY page that includes the sidebar
  (Home.php etc.), with **no visibility gate, forever**. Worse: `update_status` is NOT a
  handled action in `chat.php` — it falls through to `{success:false,"Invalid action"}`.
  So it is pure wasted traffic; presence is already kept fresh by the chat `poll` action
  (`UPDATE USER_SESSIONS SET LAST_ACTIVITY`) and by `update_user_activity`.
- **Fix:** make the sidebar presence beacon (a) call the REAL action
  (`update_user_activity`, which exists), (b) gate on `!document.hidden`, (c) run at a
  low cadence (e.g. 60s) AND skip entirely on the chat page (where the poll already does
  it). Net: no more constant chat.php hits from non-chat pages.

## Layer tasks
1. `feedora-schema` → `01_schema.md`
   - New idempotent dated migration in `scripts/` for **multi-party calls**:
     `CALL_PARTICIPANTS` (CALL_ID, USER_ID, ROLE, JOINED_AT, LEFT_AT, status) and a
     per-directed-pair signaling table `CALL_PEER_SIGNALS` (CALL_ID, FROM_USER, TO_USER,
     KIND in offer/answer/ice, PAYLOAD, SEQ/ID, CREATED_AT) so a mesh can exchange
     directed SDP/ICE. Keep existing `CALL_SESSIONS` (now the "room"); group call = one
     CALL_SESSIONS row with GROUP_ID, participants join it.
   - Repo methods on `includes/MessageRepository.php` (or a small CallRepository) for:
     joinCall, leaveCall, listParticipants, putSignal(directed), getSignals(since id,
     for me), and room lifecycle. All prepared statements, idempotent migration.
2. `feedora-backend` → `02_backend.md`
   - chat.php call actions reworked for mesh: `join_call`, `leave_call`,
     `get_call_room` (participants + new directed signals since cursor), directed
     `send_peer_signal`. Keep 1:1 working as a 2-party room. CSRF + membership/author
     guards. Response contracts documented for the frontend.
   - **Spam fix (backend side):** ensure `update_user_activity` is the canonical presence
     action; the dead `update_status` branch is unnecessary (frontend will stop sending
     it). Do not break existing presence.
3. `feedora-frontend` → `03_frontend.md`
   - **Spam fix (frontend):** rewrite `assets/js/sidebar.js` per ROOT-CAUSE above.
   - **3-column redesign** in `assets/css/chat-overhaul.css` + markup in `chat.php`:
     merge group-rail into left sidebar (Conversations + Channels/Groups sections),
     denser spacing/typography so it reads well at 100% zoom, keep brand tokens, keep
     the responsive breakpoints + quality floor (focus-visible, reduced-motion, ≥44px).
   - **Multi-party call UI:** video-tile grid (Discord-style) driven by the mesh signals
     in `assets/js/chat-overhaul.js`; participants join/leave live.
   - Run `npm run build` (hard stop on failure).
4. `feedora-qa` → `04_qa.md`
   - Boundary diffs schema↔backend↔frontend (call signaling keys, participant shape,
     sidebar action), security guards, build + `php -l`. Re-flag the CLAUDE.md↔Playwright
     contradiction. `[BLOCKER]/[WARN]/[OK]`.

## Guardrails
- Idempotent migration; user runs it manually (no agent touches the live DB).
- Conflicting contracts → surface both sides, never delete one.
- `npm run build` failure = hard stop for frontend; never ship stale `*.min.*`.
- Never hand-edit `*.min.*`; edit source then build.
- Procedural security only: `csrf_verify()` (config/config.php). No `Security::` class.

## Status
- [ ] schema → 01_schema.md
- [ ] backend → 02_backend.md
- [x] frontend → 03_frontend.md
- [ ] qa → 04_qa.md
