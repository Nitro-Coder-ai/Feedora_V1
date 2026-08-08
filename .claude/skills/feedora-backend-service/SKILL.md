---
name: feedora-backend-service
description: Build Feedora server-side logic — includes/ service classes, page controllers, and api/ JSON endpoints — on the schema data contract, enforcing procedural CSRF/upload/session security. Use whenever a feature needs a new endpoint, service, page handler, AJAX backend, or security guard. Triggers on "add an endpoint", "API", "save/process on the server", "CSRF", "backend logic", and on re-runs to align a response shape.
---

# Feedora Backend Service

Build services, page controllers, and `api/` endpoints. You consume the schema data contract and emit the **response contract** the frontend builds against.

## Workflow
1. **Read the data contract** in `_workspace/01_schema_<feature>.md`. Read a sibling for the pattern: the Story stack (`StoryRepository` → `StoryService` → `StoryFileHandler`/`StoryFactory`/`StoryManager`) and `api/follow.php` / `api/search.php` for the JSON envelope.
2. **Layer it:** Repository (already built) → Service (business logic in `includes/`) → page/API (HTTP). Pages/APIs never run raw SQL — call repository methods.
3. **Security pattern** (copy exactly from the top of `story.php`): `require_once 'config/config.php';` → `session_start();` → `csrf_verify();` on every state-changing request. These are **functions in `config/config.php`**, not a `Security::` class — that class does not exist. Apply rate-limit + upload validation helpers where relevant; media goes through `includes/CloudinaryService.php` (see `StoryFileHandler`), not raw local writes.
4. **Endpoints** live in `api/`, return a stable JSON shape, require login + CSRF. Frontend points at `api/`, never at page files.
5. **Emit the response contract** to `_workspace/02_backend_<feature>.md`: per endpoint → method, params, exact JSON shape (field names, types, nesting); plus files changed and which security guards were applied.

## Why these rules
The procedural security helpers are the real, deployed mechanism — inventing a `Security::` class produces code that fatals. Exact JSON field names matter because the frontend reads keys literally; a documented contract is what lets QA diff the boundary instead of discovering `userId`/`user_id` at runtime.

## Done when
Endpoints enforce CSRF/login, route all data through repositories, and the response contract is written with exact field names for the frontend agent.
