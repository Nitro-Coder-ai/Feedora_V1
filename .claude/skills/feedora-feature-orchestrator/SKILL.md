---
name: feedora-feature-orchestrator
description: Orchestrate the Feedora full-stack feature team (schema → backend → frontend → integration QA) to build a feature end-to-end across MySQL, PHP, and vanilla JS/CSS. Use whenever the user asks to build/add/implement a Feedora feature touching more than one layer (e.g. "add story polls", "build group call history", "implement saved recipes"). Also triggers on follow-ups: "redo the backend", "re-run", "update the feature", "the frontend doesn't match", "improve X", "just the schema part again", and on "fix the boundary mismatch".
---

# Feedora Feature Orchestrator

Coordinate a 4-agent **pipeline + incremental generate-validate** team to ship a Feedora feature across all layers. Execution mode: **agent team** (the builders share contracts and QA diffs them in real time).

Team members (all `model: "opus"`):
| Agent | Lane |
|-------|------|
| `feedora-schema` | MySQL schema, dated migrations, `includes/*Repository.php` |
| `feedora-backend` | `includes/` services, page controllers, `api/` JSON endpoints, procedural security |
| `feedora-frontend` | `assets/css` + `assets/js`, AJAX, `npm run build` |
| `feedora-qa` (`general-purpose`) | cross-boundary contract diffs + build/test/visual |

## Phase 0 — Context check
Inspect `_workspace/`:
- **Missing** → initial run.
- **Exists + user gives a new feature** → move `_workspace/` to `_workspace_prev/`, fresh run.
- **Exists + user asks to redo/fix one layer** → **partial re-run**: re-invoke only that agent (and QA), feeding it its prior `_workspace/0N_*.md` file plus the user's feedback.

## Phase 1 — Plan & contracts
Restate the feature in one paragraph. Identify which layers it touches and the contract handoffs. Create `_workspace/` and `TaskCreate` one task per layer with dependencies: schema → backend → frontend → qa (qa also runs incrementally, depending on each prior task).

## Phase 2 — Pipeline with incremental QA
`TeamCreate` the four agents. Then drive the pipeline; **data passes by file (`_workspace/0N_*.md`) + SendMessage for the live handoff**:
1. **schema** builds → writes `01_schema`. → **qa** spot-checks migration vs repo (diff 1 partial).
2. **backend** builds on `01_schema` → writes `02_backend`. → **qa** runs diff 1 full (schema↔backend) + security.
3. **frontend** builds on `02_backend` → writes `03_frontend`, runs `npm run build`. → **qa** runs diff 2 (backend↔frontend) + visual.
4. **qa** consolidates `04_qa` with `[BLOCKER]/[WARN]/[OK]` findings.

Don't wait for full completion to QA — each layer is checked as it stabilizes. A `[BLOCKER]` routes back (via SendMessage) to the owning agent, who fixes and re-emits its contract; QA re-verifies.

## Phase 3 — Consolidate & report
Summarize: files changed per layer, the migration to run, build status, and the QA findings table. List any open `[WARN]`s. Tear down the team.

## Data-passing rules
- `_workspace/` holds intermediate contracts; filename convention `0N_<agent>_<feature>.md`. Preserve them (audit trail).
- Only final code lands in the real tree (`scripts/`, `includes/`, `api/`, `assets/`, page files). `_workspace/` is never shipped.

## Error handling
- Retry a failed agent step **once**; if it still fails, proceed without it and record the gap in the report. Never silently drop it.
- **Conflicting data** (e.g. schema says column X, backend expects Y): do not delete either side — surface both to the user with the mismatch.
- `npm run build` failure is a hard stop for the frontend task — report the build error, don't ship stale `*.min.*`.
- **Known repo contradiction:** `CLAUDE.md` forbids Playwright but the repo ships and uses it. QA flags this; surface it to the user rather than choosing unilaterally.

## Team-size note
Four focused agents for a multi-layer feature. For a single-layer change (e.g. CSS-only tweak), skip the team and invoke the one relevant skill directly — the orchestration overhead isn't worth it.

## 테스트 시나리오
- **정상 흐름:** "Add emoji reactions to comments." → schema adds `COMMENT_REACTIONS` + index + repo methods → backend adds `api/comment-react.php` with CSRF + response contract → frontend wires `comments.js`, rebuilds → QA diffs all three boundaries, all `[OK]`, reports the migration to run.
- **에러 흐름 (boundary mismatch):** backend returns `{reaction_count}` but frontend reads `count`. QA's diff 2 catches `[BLOCKER]: backend↔frontend — api/comment-react.php returns 'reaction_count', comments.js reads 'count'`, routes to frontend, frontend aligns the key and rebuilds, QA re-verifies → `[OK]`.
