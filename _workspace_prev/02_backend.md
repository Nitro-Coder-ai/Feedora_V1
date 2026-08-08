# 02 — Backend & Response Contract: Messaging Enhancements (P1–P4)

Server logic for the four-feature messaging bundle. Built entirely against the
`01_schema.md` data contract and `includes/MessageRepository.php` (already
present, unchanged). All new endpoints are **inline `$_POST['action']` blocks in
`chat.php`** following the existing convention — no new `api/` files, no new
timers. P3 typing/seen ride the existing unified `poll`.

> The whole `chat.php` POST handler is already gated by `csrf_verify()` (line 11)
> and a login check (`$_SESSION['user_id']`). Every new state-changing action
> inherits that gate; each additionally validates inputs + membership/authorship.

---

## 1. Files changed

### `chat.php` (only file modified)
| Location | Change |
|----------|--------|
| top includes (after CloudinaryService) | `require_once 'includes/MessageRepository.php';` |
| `send_message` success block | P3: derive scope CONV_KEY and `clearTyping($sender, $convKey)` after insert (group → `groupConvKey`, DM → `dmConvKey`). |
| `poll` action | P3: added `$response['typing']` (default `[]`) and `$response['seen']` (default `0`). Group branch sets `typing` via `getTyping(groupConvKey(gid), me)`. DM branch sets `typing` via `getTyping(dmConvKey(me,active), me)` and `seen` via `getReadState(me,active)['last_seen_message_id']`. Uses one `new MessageRepository($pdo)` per poll. |
| new action block (before the `Invalid action` fallback) | P1 `pin_message` / `unpin_message` / `list_pins` (shared scope resolution); P2 `edit_message`, `delete_message`; P3 `set_typing`; P4 `search_messages`. |

**No SQL was inlined for the new features** — every new data access goes through
`MessageRepository`. `MessageRepository.php` itself was **not** modified (its
shapes already fit every endpoint).

All data access via `new MessageRepository($pdo)`. CONV_KEY is **always derived
server-side** from session user + posted scope (`group_id`/`receiver_id`) — never
read from the request body.

---

## 2. RESPONSE CONTRACT

Transport: `POST` form-encoded to `chat.php` with `action=<name>` (+ CSRF token as
the page already sends). `Content-Type` of the response is `application/json`.
Common envelope on every action: `{ "success": bool, "message": string, ... }`.
On any failure (validation / membership / author / DB) `success=false` and
`message` carries a human-readable reason; feature payload keys below are only
guaranteed when `success=true` unless noted.

### P1 — Pins

#### `pin_message`
Request params: `action=pin_message`, `message_id:int`, **plus scope**: either
`group_id:int` (group) **or** `receiver_id:int` (the DM peer).
```json
{
  "success": true,
  "message": "Message pinned",
  "message_id": 123,
  "pinned": true
}
```
`pinned` mirrors `success` (idempotent: re-pinning an already-pinned message still
returns `true`). On failure `pinned:false`.

#### `unpin_message`
Request params: `action=unpin_message`, `message_id:int`, + scope (`group_id` or `receiver_id`).
```json
{
  "success": true,
  "message": "Message unpinned",
  "message_id": 123,
  "pinned": false
}
```
`success=false` + `"Message was not pinned"` if no pin row existed.

#### `list_pins`
Request params: `action=list_pins`, + scope (`group_id` or `receiver_id`). No `message_id`.
```json
{
  "success": true,
  "message": "",
  "pins": [
    {
      "PIN_ID": 9,
      "MESSAGE_ID": 123,
      "CONV_KEY": "dm:4:7",
      "SCOPE_TYPE": "dm",
      "PINNED_BY": 4,
      "PINNED_AT": "2026-06-23 10:15:02",
      "SENDER_ID": 7,
      "CONTENT": "see you at 8",
      "IMAGE_URL": null,
      "SENT_AT": "2026-06-23 10:00:00",
      "EDITED_AT": null,
      "IS_DELETED": 0,
      "SENDER_NAME": "Sara",
      "SENDER_IMAGE": "https://res.cloudinary.com/.../x.jpg"
    }
  ]
}
```
`pins` is newest-pin-first. Types: ints (`PIN_ID, MESSAGE_ID, PINNED_BY, SENDER_ID, IS_DELETED`)
arrive as JSON numbers via `FETCH_ASSOC`; PDO may return them as numeric **strings** —
frontend should coerce. `CONTENT`, `IMAGE_URL`, `EDITED_AT`, `SENDER_IMAGE` are
`string|null`. Empty list → `"pins": []`.

### P2 — Edit / Delete

#### `edit_message`
Request params: `action=edit_message`, `message_id:int`, `content:string` (required, trimmed, non-empty).
```json
{
  "success": true,
  "message": "Message edited",
  "message_id": 123,
  "edited": true,
  "content": "updated text"
}
```
Author-only (repo matches `SENDER_ID = me`) and blocked on tombstoned messages —
both yield `success=false`, `edited=false`, and **no** `content` key. `content`
echoes the saved (trimmed) text only on success.

#### `delete_message`
Request params: `action=delete_message`, `message_id:int`.
```json
{
  "success": true,
  "message": "Message deleted",
  "message_id": 123,
  "deleted": true
}
```
Soft delete (`IS_DELETED=1`) + removes any pin in one transaction. Author-only;
non-owner / already-deleted → `success=false`, `deleted=false`. Frontend renders
the tombstone ("This message was deleted").

### P3 — Typing + Seen

#### `set_typing`
Request params: `action=set_typing`, + scope (`group_id` or `receiver_id`). Sent on
keystroke heartbeat (frontend throttles).
```json
{ "success": true, "message": "" }
```
No payload beyond the envelope. The **read-back is on the poll** (below), not here.
`clearTyping` happens automatically server-side when the user sends a message
(`send_message`); frontend may also rely on the 6s staleness window.

#### `poll` (extended — P3 fields added)
Request params (unchanged): `action=poll`, `last_message_id:int`,
`active_user_id:int` (open DM peer) **or** `active_group_id:int` (open group).
Existing keys (`conversations`, `groups`, `messages`, `last_message_id`,
`active_status`, `incoming_call`, `group_call`, `success`) are **unchanged**. Two
new keys, **always present** in the poll response:

| Key | Type | When populated |
|-----|------|----------------|
| `typing` | array of `{ "USER_ID":int, "NAME":string, "PROFILE_IMAGE":string\|null }` | others typing now (heartbeat < 6s) in the open conv; `[]` otherwise. Set for both the open DM (`active_user_id`) and the open group (`active_group_id`); excludes me. |
| `seen` | int | DM only: id of the latest message **I** sent to the open peer that they've read (`IS_READ=1`); `0` if none or no DM open. Frontend shows "Seen" under the message whose id == `seen`. |

```json
{
  "success": true,
  "conversations": [ ... ],
  "groups": [ ... ],
  "messages": [ ... ],
  "last_message_id": 4120,
  "active_status": { "online": true, "last_seen": null },
  "typing": [
    { "USER_ID": 7, "NAME": "Sara", "PROFILE_IMAGE": "https://.../x.jpg" }
  ],
  "seen": 4118,
  "incoming_call": null,
  "group_call": null
}
```
For a group poll, `typing` is populated and `seen` stays `0` (group read state lives
in `CHAT_GROUP_READS`, out of scope here). `USER_ID` may arrive as a numeric string
(PDO) — coerce on the client.

### P4 — Search

#### `search_messages`
Request params: `action=search_messages`, `q:string` (raw term — **do not** add `%`;
the repo escapes LIKE wildcards and wraps `%…%`). Empty/whitespace `q` → empty result, `success=true`.
```json
{
  "success": true,
  "message": "",
  "results": [
    {
      "MESSAGE_ID": 4119,
      "SENDER_ID": 7,
      "RECEIVER_ID": 4,
      "GROUP_ID": null,
      "CONTENT": "best pizza in town",
      "IMAGE_URL": null,
      "SENT_AT": "2026-06-23 09:58:00",
      "SENDER_NAME": "Sara",
      "SENDER_IMAGE": "https://.../x.jpg"
    }
  ]
}
```
`results` is newest-first (`MESSAGE_ID DESC`), capped at 30. Scope (sender OR
receiver OR a group the user belongs to) and `IS_DELETED=0` are enforced **inside
the repo SQL** — the backend passes only `$me` + the raw term. For a DM hit
`GROUP_ID:null` and `RECEIVER_ID` is set; for a group hit `RECEIVER_ID:null` and
`GROUP_ID` is set. Numeric fields may arrive as strings (PDO) — coerce client-side.

---

## 3. Security notes

- **CSRF:** all of the above are `POST` actions inside `chat.php`, covered by the
  single top-level `csrf_verify()` (line 11). Not removed, not duplicated.
- **AuthN:** the page-level `$_SESSION['user_id']` guard (redirect to sign-in)
  applies to every action; `$me` is always taken from the session, never POST.
- **CONV_KEY integrity:** pin/unpin/list/set_typing derive `CONV_KEY` server-side
  from `$me` + posted `group_id`/`receiver_id` via `MessageRepository::groupConvKey`
  / `::dmConvKey`. A forged `CONV_KEY` in the body is ignored (no such param read).
- **Group membership:** `pin_message`, `unpin_message`, `list_pins`, `set_typing`
  call `isGroupMember($pdo,$gid,$me)` before doing anything with a group scope —
  mirroring the existing `send_message` / `get_messages` checks. Poll's group typing
  is inside the existing `isGroupMember` branch.
- **Authorship (P2):** `edit_message` / `delete_message` enforce author via the
  repo's `SENDER_ID = ?` predicate; the session `$me` is passed, never a posted id.
  A non-author request simply updates 0 rows → `success=false`.
- **Input validation:** `message_id` must be `> 0`; `edit_message` requires a
  non-empty trimmed `content`; scope actions require at least one of
  `group_id`/`receiver_id`. Search trims `q` and short-circuits empty.
- **Injection:** zero inline SQL added for these features; all access is via
  `MessageRepository` prepared statements. Search wildcards are escaped in the repo
  (`\`, `%`, `_`) with `ESCAPE '\\'`.

---

## 4. Conflicts / notes for the leader

- **No contract conflicts.** Every repo method signature in `01_schema.md` §3 fit
  the endpoints as-is; `MessageRepository.php` was not edited.
- **Runtime dependency on the migration:** these endpoints assume
  `scripts/2026_messages_enhancements.sql` has been applied (tables `MESSAGE_PINS`,
  `MESSAGE_TYPING`; columns `MESSAGES.EDITED_AT`, `IS_DELETED`, `IS_READ`,
  `GROUP_ID`). The repo methods are wrapped in try/catch and degrade to
  `false`/`[]` if a table/column is missing, so a pre-migration request fails soft
  (envelope `success=false`) rather than fatally — but the features are inert until
  the user runs the migration. (Schema's `IS_READ`/`GROUP_ID` defensive-add note in
  `01_schema.md` §5 still stands.)
- **`seen` is DM-only by design** (group read state is `CHAT_GROUP_READS`). Flagged
  so frontend doesn't expect a group "seen" cursor on the poll.

---

## 5. Hand-off

- **→ feedora-frontend:** build P1 pinned panel (`#pinned-btn`) against
  `list_pins`/`pin_message`/`unpin_message`; P2 edit/delete controls against
  `edit_message`/`delete_message` (render tombstone on `IS_DELETED`/`deleted`, and
  an "edited" marker when a message's `EDITED_AT` is non-null — note: live
  `messages`/`get_messages` rows include `EDITED_AT` and `IS_DELETED` via `m.*`);
  P3 read the new poll `typing`/`seen` keys and POST `set_typing` on keystroke; P4
  wire the search box to `search_messages` (`q` raw, render `results`). All AJAX
  stays pointed at `chat.php` (existing convention) — do not add `api/` calls.
- **→ feedora-qa:** cross-check the keys above against the repo reads in
  `01_schema.md` §3 and the consumers in `03_frontend.md`. Verify the poll still
  returns its original keys plus `typing`/`seen`, and that group typing populates
  while `seen` stays `0` for groups.
