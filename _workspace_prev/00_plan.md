# Feature bundle: Messaging enhancements + brand-aligned responsive UX

One pipeline run. Each layer agent handles all four features, organized as phases.

## Phases (features)
- **P1 — Pinned messages.** The `#pinned-btn` thumbtack already exists in `chat.php` with NO backend. Add pin/unpin/list (per conversation/group), author/member-scoped, shown in a pinned panel.
- **P2 — Edit & delete messages.** No edit/delete today. Author-only. Soft-delete (tombstone "This message was deleted"); edited messages show an "edited" marker.
- **P3 — Read receipts + typing.** DMs have `IS_READ`; add a "Seen" indicator and a typing signal carried on the existing unified `poll` (no new timers).
- **P4 — Search within messages.** Current search only filters the conversation list. Add message-content search scoped to the user's own conversations, with a results UI.

## Cross-cutting (frontend)
- Responsive overhaul of the message surface across the existing breakpoints (1199/991/767) + brand alignment per `_workspace/BRAND.md`.
- Apply the quality floor (focus-visible, reduced-motion, ≥44px targets) to all new controls.

## Pipeline (sub-agent pattern, file-based contracts)
1. `feedora-schema` → `01_schema.md` (one idempotent dated migration in `scripts/` covering P1–P4 + repo methods).
2. `feedora-backend` → `02_backend.md` (endpoints + response contracts, CSRF + membership/author checks).
3. `feedora-frontend` → `03_frontend.md` (UI wiring + overhaul; runs `npm run build`).
4. `feedora-qa` → `04_qa.md` (boundary diffs P1–P4 + build).

## Guardrails
- Idempotent migration; user runs it manually (no agent touches their live DB).
- Conflicting contracts → surface, don't delete a side.
- `npm run build` failure = hard stop for frontend.

## Status
- [x] P-schema → 01_schema.md (migration + MessageRepository)
- [x] P-backend → 02_backend.md (chat.php actions + poll)
- [x] P-frontend → 03_frontend.md (UI + build)
- [x] P-qa → 04_qa.md (0 blockers, 2 WARNs)

Pending user action: run `scripts/2026_messages_enhancements.sql` on the DB.
