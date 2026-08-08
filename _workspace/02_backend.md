# 02 — Backend contract: mesh call actions + presence spam fix

Owner: `feedora-backend`. Consumers: `feedora-frontend` (builds fetch/render on these
exact keys), `feedora-qa` (cross-checks consumer shapes + security guards).

All changes are in **`chat.php`** only. No schema change (CallRepository methods used
as documented in `01_schema.md`). `php -l chat.php` → clean.

## Global request rules (apply to every action below)
- Transport: `POST chat.php` (form-encoded), `action=<name>` + params.
- Auth: not logged in → 302 redirect to `sign-in.php` (no JSON). Front sends from a
  logged-in page so this is not normally hit by AJAX.
- CSRF: enforced ONCE at top of file via `csrf_verify()` (config/config.php), before
  any action branch. Frontend MUST send the project CSRF token with every POST
  (same as all existing chat AJAX). A forged/missing token is rejected before the
  action runs.
- Response: `Content-Type: application/json`. Every response is an object with at
  least `success` (bool) and, on failure, `message` (string). Keys below are the
  ADDITIONAL keys on success.
- Field-name casing: roster + signal fields are passed straight through from the DB
  in **UPPER_SNAKE** (`USER_ID`, `NAME`, `PROFILE_IMAGE`, `SIGNAL_ID`, `FROM_USER`,
  `KIND`, `PAYLOAD`, `JOINED_AT`, `ROLE`). Do not lowercase them on the client.

---

## NEW mesh call actions (multi-party / Discord-style)
These coexist with the legacy 1:1 actions (`initiate_call`, `answer_call`,
`send_ice_candidate`, `get_call_signaling`, `end_call`, `reject_call`) — all still work.

### `join_call`
Resolve or create the room, then put me in the roster. Send exactly ONE of the three
room selectors.

Request params:
| param | type | when | meaning |
|-------|------|------|---------|
| `action` | string | always | `join_call` |
| `group_id` | int | group call | join the live room for this group, or start one if none |
| `call_id` | int | join existing | join a known room (membership re-checked from the room row) |
| `receiver_id` | int | 1:1 | start a 1:1 room with this user |
| `call_type` | string | optional | `voice` (default) \| `video`. Ignored when joining an existing room (room's type wins) |

Resolution: `call_id` → `getRoom` + authorize + `joinCall`. `group_id` →
`getActiveRoomForGroup` (join if found, else `createRoom` as host). `receiver_id` →
`createRoom` as host.

Membership/guards: group → `isGroupMember`; existing room → caller must be the room's
group member OR its CALLER_ID/RECEIVER_ID. Ended/rejected room → rejected.

Success response:
```json
{
  "success": true,
  "call_id": 42,
  "call_type": "video",
  "me": 7,
  "participants": [
    { "USER_ID": 7, "NAME": "Sara", "PROFILE_IMAGE": "https://...|null", "ROLE": "host", "JOINED_AT": "2026-06-24 12:00:01" }
  ]
}
```
Failure `message`s: `Call not found`, `Call has ended`, `Not allowed`,
`Not a group member`, `group_id, call_id or receiver_id is required`,
`Could not join call`.

### `leave_call`
Drop out; if the room is then empty, end it (`endRoom`).

Request: `action=leave_call`, `call_id` (int, required).

Success: `{ "success": true }`.
Failure: `call_id is required`.

### `get_call_room`  (the mesh poll while in a call)
Roster + directed signals addressed to me since my cursor. Caller must be an active
participant. The client calls this on a short interval while in the call and echoes
back `last_signal_id` from the previous response as the next cursor.

Request:
| param | type | meaning |
|-------|------|---------|
| `action` | string | `get_call_room` |
| `call_id` | int | required |
| `last_signal_id` | int | cursor; 0 (or omit) on first poll |

Success response:
```json
{
  "success": true,
  "participants": [
    { "USER_ID": 7, "NAME": "Sara", "PROFILE_IMAGE": "https://...|null", "ROLE": "host", "JOINED_AT": "..." }
  ],
  "signals": [
    { "SIGNAL_ID": 18, "FROM_USER": 9, "KIND": "offer", "PAYLOAD": "<sdp-or-ice-json>", "CREATED_AT": "..." }
  ],
  "last_signal_id": 18,
  "status": "active"
}
```
Notes for frontend:
- `signals` are ONLY those addressed TO me (`TO_USER` is not echoed — it is always me).
- `KIND` ∈ `offer` | `answer` | `ice`. For `ice`, `PAYLOAD` is the ICE candidate JSON
  string; for `offer`/`answer`, `PAYLOAD` is the SDP string.
- `last_signal_id` = max SIGNAL_ID returned, or the cursor you sent if none. Always
  feed it back next poll.
- `status` is the room's CALL_SESSIONS.STATUS (`ringing`|`active`|`ended`|...). When
  it goes `ended`, tear the call down client-side.
- Diff `participants` between polls to add/remove video tiles (join/leave live).

Failure: `Not an active participant`, `call_id is required`.

### `send_peer_signal`  (directed offer/answer/ice to one peer)
Both me and `to_user` must be active participants. `kind` validated in the repo
(`putSignal` returns 0 for anything not offer/answer/ice).

Request:
| param | type | meaning |
|-------|------|---------|
| `action` | string | `send_peer_signal` |
| `call_id` | int | required |
| `to_user` | int | recipient peer USER_ID (required) |
| `kind` | string | `offer` \| `answer` \| `ice` (required) |
| `payload` | string | SDP string (offer/answer) or ICE candidate JSON (ice). required, non-empty |

Success: `{ "success": true, "signal_id": 19 }`.
Failure: `call_id, to_user and payload are required`, `Not an active participant`,
`Target is not an active participant`, `Invalid signal (bad kind?)`.

---

## Unified `poll` action — group-call advertisement (UNCHANGED keys, confirmed)
The existing `poll` action already advertises a live group room to members through
the `group_call` key (CALL_SESSIONS where GROUP_ID matches the open group, STATUS in
ringing/active, started within 120s, by another caller). A late joiner sees this and
can call `join_call` with `call_id` (or `group_id`). **No poll keys were added or
removed** — `conversations`, `groups`, `messages`, `last_message_id`, `typing`,
`seen`, `active_status`, `incoming_call`, `group_call`, `success` all preserved.

`group_call` shape (existing): `{ CALL_ID, CALLER_ID, CALL_TYPE, STATUS, OFFER_SDP,
CALLER_NAME, CALLER_IMAGE }` or `null`. Frontend: use `CALL_ID` → `join_call`.

---

## Part B — presence spam fix (backend side)
- **Canonical presence action `update_user_activity` confirmed working standalone**
  (chat.php ~258). Request: `action=update_user_activity` (+ CSRF token). Response:
  `{ "success": true }`. It upserts the caller's USER_SESSIONS row (updates
  LAST_ACTIVITY, inserts if none). This is what `sidebar.js` must call instead of the
  dead `update_status`.
- **Added a tiny `update_status` alias branch** (decision: defensive). The dead
  `update_status` action previously fell through to `{success:false,"Invalid action"}`.
  It now does the same presence upsert and returns `{ "success": true }`, so any stale
  cached `sidebar.js` no longer errors. This is a safety net only — the real fix is the
  frontend switching to `update_user_activity` at low cadence, visibility-gated, and
  skipping the chat page (per 00_plan.md ROOT-CAUSE). Backend does not throttle; cadence
  is the frontend's responsibility.

### Frontend action for the spam fix
Use **`update_user_activity`** (preferred). `update_status` also works now but is only
a back-compat alias — point new code at `update_user_activity`.

---

## Security notes
- CSRF: top-level `csrf_verify()` covers all new actions (no per-branch token needed,
  but the token MUST be POSTed).
- Membership/authorization on every mesh action:
  - `join_call`: group → `isGroupMember`; existing room → group member or
    caller/receiver of the room.
  - `get_call_room` / `send_peer_signal`: `isActiveParticipant(callId, me)`;
    `send_peer_signal` also requires `isActiveParticipant(callId, to_user)`.
  - `leave_call`: scoped by (call_id, me) inside the repo (can only flip my own row).
- Prepared statements only — all DB access goes through `CallRepository` (and the
  existing prepared `update_status` upsert). No user input interpolated into SQL.
- No file uploads in these actions (no upload guard needed here).
- Repo `putSignal` whitelists `KIND` server-side; `createRoom` clamps `call_type` to
  voice|video.

## Schema mismatches hit
None. Every CallRepository method and column in `01_schema.md` was present and used as
documented. `getRoom` returns `RECEIVER_ID`/`GROUP_ID` (nullable) which `join_call`
relies on for the existing-room authorization path — confirmed present.

## Files changed
- `C:\Users\moham\Desktop\Feedora_Project_Full\chat.php` — added `join_call`,
  `leave_call`, `get_call_room`, `send_peer_signal`, and `update_status` alias.
  `php -l` clean.

## Status
- [x] Mesh actions added (back-compat 1:1 preserved)
- [x] poll group_call advertisement confirmed (no key changes)
- [x] update_user_activity confirmed + update_status alias added
- [x] php -l chat.php clean
