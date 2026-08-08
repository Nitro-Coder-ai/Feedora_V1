# Feedora Brand Tokens (chat surface) — shared reference for all agents

Derived from the live `assets/css/chat-overhaul.css` `:root`. The chat already
uses these; new UI MUST match them — do not introduce a new palette/font.

## Color
| Token | Hex | Use |
|-------|-----|-----|
| `--fd-orange` | `#ed5a2c` | primary accent, sent bubbles, active states, CTAs |
| `--fd-orange-dark` | `#d24a20` | hover on primary |
| `--fd-green` | `#2ecc71` | online/presence, success, join-call |
| `--fd-red` | `#e74c3c` | unread badges, destructive (delete), record |
| `--bg-main` | `#ffffff` | main chat area |
| `--bg-panel` | `#f6f7f9` | rails / chat list / info sidebar |
| `--bg-input` | `#eef0f3` | inputs, search |
| `--bg-card` | `#f0f2f5` | received bubbles, chips, segmented track |
| `--tx-white` | `#0f1115` | headers + names |
| `--tx-body` | `#1c1e21` | message/body text |
| `--tx-muted` | `#65676b` | timestamps / secondary |
| `--line` / `--line-2` | `rgba(0,0,0,.08)` / `.12` | borders |

## Type
- Body/UI: **Inter** (400–700), already `@import`ed in chat-overhaul.css.
- Display: **DM Serif Display** (loaded in chat.php head) — reserve for large titles only, sparingly.

## Geometry & motion
- Radius: 12–16px on cards/buttons, `999px` pills/badges, 50% avatars.
- Standard ease: `--ease: all .2s cubic-bezier(.4,0,.2,1)`.
- Expressive (use only for a signature beat): spring `cubic-bezier(.34,1.56,.64,1)`.
- Shadow: `--shadow: 0 4px 24px rgba(0,0,0,.1)`.

## Responsive breakpoints (already in use — keep consistent)
- `≤1199px`: info sidebar becomes a right slide-in overlay (`.info-open`).
- `≤991px`: conversations list narrows.
- `≤767px`: group-rail + conversations list become a left drawer (`.chat-container.drawer-open` + `#mobile-overlay.active`); a fixed FAB `#mobile-conversations-toggle` opens it.

## Quality floor (mandatory on all new UI)
- Tap targets ≥44px on mobile.
- `:focus-visible` orange ring (`outline:2px solid var(--fd-orange); outline-offset:2px`).
- Respect `@media (prefers-reduced-motion: reduce)`.
- Keyboard-operable controls (buttons real `<button>`; clickable divs get `role`+`tabindex`+Enter/Space).

## Hard constraints
- Procedural security: every state-changing request → `csrf_verify()` (function in `config/config.php`). NO `Security::` class.
- All SQL via prepared statements; data access through `includes/*Repository.php`.
- Frontend edits go to `assets/{css,js}/*.{css,js}` source; run `npm run build` (esbuild) to regenerate `*.min.*`. Never hand-edit min files.
- New chat UI lives in `chat-overhaul.css` (loaded last, wins) and `chat.js` / `chat-overhaul.js`.
