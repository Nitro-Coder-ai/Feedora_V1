---
name: feedora-integration-qa
description: Cross-boundary integration QA for Feedora features — diff schema↔backend↔frontend contracts for column/response/consumer shape mismatches, verify security guards, and run the build/test/visual tooling. Use after any layer of a feature stabilizes, and on "verify the feature", "check it fits together", "QA this", "did I miss anything", "does the frontend match the API".
---

# Feedora Integration QA

Verify the three layers fit. The valuable bugs here are **boundary bugs**, not missing files. Cross-read two layers at once and diff their contracts.

## The three boundary diffs (the actual job)
1. **Schema ↔ Backend** — read the migration/repository AND the response contract together: every column a Repository selects exists in the migration; every API field traces to a real column or computed value.
2. **Backend ↔ Frontend** — read the response contract AND the consumer map together: every key the JS reads exists in the JSON, same name and nesting; every endpoint the JS calls exists with the documented method. (Watch `userId`/`user_id`, `items`/`results`, array vs object.)
3. **Security** — every state-changing page/endpoint calls `csrf_verify()`; uploads pass validation; no raw-string SQL outside repositories.

## Workflow
1. Run **incrementally** — after schema (diff 1 partial), after backend (diff 1 full), after frontend (diff 2), security throughout. Don't wait for the end.
2. For each boundary, open BOTH sides and compare field-by-field. Quote the mismatching lines. Never report "looks fine" without having read both sides.
3. **Run what exists:** `npm run build` must succeed; use the Playwright suite (`tests/`) or Chrome extension MCP for the responsive/visual pass. Note: `CLAUDE.md` forbids Playwright but the repo ships it — flag the contradiction, don't silently choose.
4. **Emit findings** to `_workspace/04_qa_<feature>.md`: each tagged `[BLOCKER]/[WARN]/[OK]`, naming the boundary, both sides, the mismatch, and (for blockers) the agent who must fix it.
5. **Re-verify** after a fix is reported — don't close a blocker you haven't re-checked.

## Why this matters
"Existence checks" (does the file/function exist) miss the bugs that actually break this app — they pass while the JSON key is misspelled. Only a side-by-side contract diff catches those, which is why each builder agent emits an explicit contract.
