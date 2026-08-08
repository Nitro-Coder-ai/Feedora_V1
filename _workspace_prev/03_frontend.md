# 03 — Frontend & Consumer Map: Messaging Enhancements (P1–P4)

Client side for the four-feature messaging bundle + brand/responsive pass. All
AJAX is form-encoded `POST` to `chat.php` (CSRF `X-CSRF-Token` header is
auto-attached by the already-loaded `assets/js/csrf.js` patch — no per-call token
handling added). No `api/` calls added. No second poller — P3 typing/seen ride the
existing unified poll via `applyPoll` / the `chat:poll` event.

---

## 1. Files changed

### Markup — `chat.php`
| Where | Added |
|-------|-------|
| `.conversation-header-info` | `#typing-indicator` (animated dots + `.typing-text`, `aria-live="polite"`, `hidden`) — P3 |
| between `.conversation-header-active` and `#messages-container` | `#pinned-panel` (`hidden`, `role="region"`) with `#pinned-close` + `#pinned-list` — P1 |
| after `#conversation-items` | `#message-search-results` (`hidden`, `role="region"`) — P4 |

### CSS — `assets/css/chat-overhaul.css` (loaded last, wins)
Appended one new block "Messaging enhancements P1–P4" **after** the existing
"Message category" refinement block (kept intact). Tokens only (`--fd-orange`,
`--fd-green`, `--fd-red`, `--bg-*`, `--tx-*`, `--ease`, 12–16px radii, 999px pills).
New classes: `.pinned-panel`/`.pinned-item`/`.pin-unpin`, `.msg-actions`,
`.msg-menu`, `.msg-inline-editor`, `.edited-marker`, `.message-tombstone`,
`.typing-indicator`/`.typing-dots` (+ `@keyframes fd-typing`), `.seen-label`,
`.message-search-results`/`.msr-item`, `.msg-highlight` (+ `@keyframes
fd-msg-flash`). Includes `:focus-visible` rings, `prefers-reduced-motion` block,
and a `≤767px` block (≥44px tap targets, always-visible kebab on mobile).

### JS — `assets/js/chat.js`
- `appendMessage()`: after append → `window.ChatEnhance.decorateMessage(el, message, isSent)`.
- `applyPoll(data)` (poller IIFE): after message handling → `ChatEnhance.applyTyping(data.typing)` + `ChatEnhance.applySeen(data.seen)`.
- `openConversation()` (poller IIFE): calls `ChatEnhance.onScopeChange()` on DM open.
- **New IIFE `window.ChatEnhance`** appended at EOF: all P1–P4 client logic (pin panel, kebab menu, inline edit, delete+tombstone, typing heartbeat, typing/seen render, message search, jump-to-message).

### JS — `assets/js/chat-overhaul.js`
- `appendGroupMessage()`: after append → `ChatEnhance.decorateMessage(wrap, m, mine)`.
- `openGroup()`: calls `ChatEnhance.onScopeChange()` on group open.
- Group typing/seen are handled centrally in `applyPoll` (same shared header element), so no duplicate render path was added in the `chat:poll` handler — see note in §2.

Load order is `chat.js` → `chat-overhaul.js` (both `defer`); `window.ChatEnhance`
is defined synchronously at `chat.js` parse time, so it exists before any render.

---

## 2. CONSUMER MAP (diff this against 02_backend.md §2)

### P1 — Pins
| Action | Caller (JS fn) | Request params sent | Response fields read |
|--------|----------------|---------------------|----------------------|
| `list_pins` | `ChatEnhance.loadPins()` (on `#pinned-btn` open + on `onScopeChange` silently + after pin/unpin) | scope: `group_id` **or** `receiver_id` (derived from `window.__activeGroupId`/`__activeUserId`) | `success`, `pins[]` → per pin: `MESSAGE_ID`, `SENDER_IMAGE`, `SENDER_NAME`, `CONTENT`, `IMAGE_URL`, `SENT_AT`, `EDITED_AT`, `IS_DELETED` |
| `pin_message` | `ChatEnhance.togglePin(id,false)` (kebab menu → Pin) | `message_id`, scope | `success`, `pinned` |
| `unpin_message` | `ChatEnhance.togglePin(id,true)` (kebab Unpin + `.pin-unpin` in panel) | `message_id`, scope | `success`, `pinned` |

`pins[]` rendered newest-first as-is (no client re-sort). `MESSAGE_ID` coerced via
`String()`. `IS_DELETED` read with truthy-coerce (handles PDO numeric strings).
Clicking a pin row → `scrollToMessage(MESSAGE_ID)` (smooth scroll + flash highlight).

### P2 — Edit / Delete
| Action | Caller | Request params | Response fields read |
|--------|--------|----------------|----------------------|
| `edit_message` | `ChatEnhance.startEdit()` inline editor Save | `message_id`, `content` (trimmed) | `success`, `content` (echoed; falls back to local value), `message` (alert on failure) |
| `delete_message` | `ChatEnhance.doDelete()` (kebab → Delete → in-menu confirm) | `message_id` | `success`, `message` (alert on failure) |

Marker/tombstone on inbound rows: `decorateMessage()` reads `m.EDITED_AT`
(non-null/non-empty ⇒ append `.edited-marker`) and `m.IS_DELETED` (truthy ⇒
`renderTombstone()` "This message was deleted", strips kebab + reactions). Source
rows come from `get_messages`/`poll` `SELECT m.*` (include both columns). Edit
success appends the `edited` marker; delete success renders the tombstone locally.

### P3 — Typing + Seen
| Key/Action | Caller | Request params | Response fields read |
|------------|--------|----------------|----------------------|
| `set_typing` | `ChatEnhance.sendTyping()` on `#message-input` `input` (throttled 1/2500ms, skipped when input empty) | scope (`group_id` or `receiver_id`) | `success` (ignored) |
| poll `typing` | `ChatEnhance.applyTyping(data.typing)` from `applyPoll` | — | each item: `NAME` (USER_ID/PROFILE_IMAGE available but not currently rendered) |
| poll `seen` | `ChatEnhance.applySeen(data.seen)` from `applyPoll` | — | `seen` (int) |

`applyTyping` shows `#typing-indicator` + adds `.is-typing` to header-info (CSS
hides the status line while typing). DM single typer → "typing…"; group → name(s).
`applySeen` (DM only, gated on `window.__activeUserId`) finds the `.message-sent`
with `data-message-id == seen` and inserts a "Seen" label after it; clears when
`seen<=0` / no DM open. Group polls send `seen:0` ⇒ no label — matches backend
"DM-only by design".

### P4 — Search
| Action | Caller | Request params | Response fields read |
|--------|--------|----------------|----------------------|
| `search_messages` | `ChatEnhance.runSearch()` (debounced 300ms on `#search-conversations`, fires at `q.length>=2`) | `q` (raw, **no `%`**) | `success`, `results[]` → per row: `MESSAGE_ID`, `SENDER_ID`, `RECEIVER_ID`, `GROUP_ID`, `CONTENT`, `SENT_AT`, `SENDER_NAME`, `SENDER_IMAGE` |

Peer for a DM hit derived client-side: `SENDER_ID===me ? RECEIVER_ID : SENDER_ID`.
On click: group hit → clicks the matching `.rail-group[data-group-id]`; DM hit →
clicks the matching `.conversation-item[data-user-id]` or falls back to
`loadConversation(uid)`; then `jumpWhenReady()` polls the DOM (≤3.6s) and
`scrollToMessage()`. Conversation-list text filtering (existing chat.js behavior)
is untouched and runs alongside.

---

## 3. Build

`npm run build` (esbuild via `scripts/build-assets.mjs`) ran: **Minified 45 files,
Failed: 0.** Regenerated min files relevant here:
- `assets/js/chat.min.js` (80884 → 46791) — contains `ChatEnhance`.
- `assets/js/chat-overhaul.min.js` (27689 → 20497).
- `assets/css/chat-overhaul.min.css` (now 27947 bytes) — contains the new classes.

`node --check` passes on both JS sources. No `*.min.*` was hand-edited.

---

## 4. Responsive / brand note (per breakpoint)

- **Desktop (>1199):** kebab `.msg-actions` reveals on `.message:hover`/focus;
  `.msg-menu` is a fixed popover clamped to the viewport; pin panel slides in below
  the header (max-height 40vh, scrolls); search results render inline under the
  conversation list. Sent bubbles orange, received `--bg-card`, edited marker muted,
  tombstone muted-italic, typing dots + seen check in `--fd-orange`/`--fd-green`.
- **≤1199 (info overlay):** unchanged; pin panel/search/menu all live inside the
  existing columns so the info-sidebar overlay behavior is unaffected.
- **≤991 (narrow list):** search results inherit the narrowed list width; no overflow.
- **≤767 (mobile drawer):** kebab is always visible and sized 44×44; `.msg-menu`
  buttons get 13px vertical padding; `.pin-unpin`/`.pinned-close` go 40px; pin panel
  max-height 50vh; search-result rows 12px padding. All new controls are real
  `<button>`s or `role="button" tabindex="0"` with Enter/Space handlers, carry
  `:focus-visible` orange rings, and the `prefers-reduced-motion` block disables the
  typing animation + flash. Geometry stays on Feedora tokens (Inter, 12–16px radii,
  999px pills, `--ease`); no new palette introduced.

---

## 5. Conflicts / notes for the leader

- **No contract field mismatches.** Every key consumed above matches 02_backend.md
  §2 exactly (`pins`, `pinned`, `content`, `typing[].NAME`, `seen`, `results`). No
  client-side data was renamed or reshaped to paper over the boundary.
- **Known poll limitation (not a contract bug):** the unified `poll` returns only
  *new* messages (by `last_message_id`); there is no "changed-rows" channel. So an
  edit/delete performed by the **peer** on an already-rendered message does not
  propagate live — it shows correctly on the next full load/`get_messages` (which
  carry `EDITED_AT`/`IS_DELETED`). The actor's own edit/delete updates instantly.
  Flagging in case the leader wants a future "mutations since id" poll field; I did
  not invent one client-side.
- **Group typing handled once:** since DM and group share the same
  `#typing-indicator` header element and the poll scopes `typing` to whichever
  conversation is open, `applyTyping` is driven from `applyPoll` for both. No second
  handler was added in `chat-overhaul.js`'s `chat:poll` block to avoid two writers
  racing on the same node.
