# 01 — Schema contract: Discord-style multi-party (mesh) calls

Owner: `feedora-schema`. Consumers: `feedora-backend` (builds chat.php call
actions on these), `feedora-qa` (cross-checks columns vs repo reads).

## Model
The legacy call model put ONE `OFFER_SDP`/`ANSWER_SDP` pair on each `CALL_SESSIONS`
row — only works 1:1. Multi-party keeps `CALL_SESSIONS` as the **room** (group call =
one row with `GROUP_ID` set) and adds:
- `CALL_PARTICIPANTS` — live roster of who is in the room.
- `CALL_PEER_SIGNALS` — **directed** offer/answer/ICE per ordered peer-pair (mesh).

`CALL_ICE_CANDIDATES` and `CALL_SESSIONS.OFFER_SDP/ANSWER_SDP` are left intact for
the existing 1:1 path (back-compat). The mesh path does NOT use them — it uses
`CALL_PEER_SIGNALS` exclusively (offers/answers go in there too, not in CALL_SESSIONS).

## Migration to run
`scripts/2026_multiparty_calls.sql` — idempotent, re-runnable, pre-8.0.29 safe.
```
mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_multiparty_calls.sql
```
Also mirrored into `chat.php` `ensureChatSchema()` (self-heals a fresh deploy that
hasn't run the migration) and appended to canonical `Feedora.sql`.

## DDL

### CALL_PARTICIPANTS (roster)
| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `CALL_ID` | INT | NO | — | FK → CALL_SESSIONS.CALL_ID (CASCADE); part of PK |
| `USER_ID` | INT | NO | — | FK → USERS.USER_ID (CASCADE); part of PK |
| `ROLE` | VARCHAR(20) | NO | `'participant'` | `'host'` \| `'participant'` |
| `STATUS` | VARCHAR(20) | NO | `'active'` | `'active'` \| `'left'` |
| `JOINED_AT` | TIMESTAMP | — | CURRENT_TIMESTAMP | (re)stamped on join |
| `LEFT_AT` | TIMESTAMP | YES | NULL | set on leave |

PK `(CALL_ID, USER_ID)` · `KEY idx_callpart_active (CALL_ID, STATUS)` (who is in this
call) · `KEY idx_callpart_user (USER_ID)`.

### CALL_PEER_SIGNALS (directed mesh signalling)
| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `SIGNAL_ID` | INT AI | NO | — | PK; monotonic poll cursor |
| `CALL_ID` | INT | NO | — | FK → CALL_SESSIONS.CALL_ID (CASCADE) |
| `FROM_USER` | INT | NO | — | FK → USERS.USER_ID (CASCADE); sender peer |
| `TO_USER` | INT | NO | — | FK → USERS.USER_ID (CASCADE); recipient peer |
| `KIND` | VARCHAR(10) | NO | — | `'offer'` \| `'answer'` \| `'ice'` |
| `PAYLOAD` | TEXT | NO | — | SDP string (offer/answer) OR ICE candidate JSON |
| `CREATED_AT` | TIMESTAMP | — | CURRENT_TIMESTAMP | |

`KEY idx_peersig_inbox (CALL_ID, TO_USER, SIGNAL_ID)` — "signals addressed to me
since cursor": `WHERE CALL_ID=? AND TO_USER=? AND SIGNAL_ID>? ORDER BY SIGNAL_ID`.

### CALL_SESSIONS (unchanged room row)
Existing columns reused: `CALL_ID, CALLER_ID, RECEIVER_ID(NULL), GROUP_ID(NULL),
CALL_TYPE('voice'|'video'), STATUS('ringing'|'active'|'ended'|'rejected'|'missed'),
STARTED_AT, ENDED_AT`. Migration also re-asserts `idx_call_group_status (GROUP_ID,
STATUS)` defensively (it is in the chat-groups migration but not in Feedora.sql).

## Repository — `includes/CallRepository.php`
New class `CallRepository`, constructed `new CallRepository($pdo)` (same as
`MessageRepository`). Already `require`d in `chat.php`. Prepared statements only.

| Method | Returns |
|--------|---------|
| `joinCall(int $callId, int $userId, string $role='participant'): bool` | true on success; upserts roster row → `active`, restamps JOINED_AT, clears LEFT_AT (idempotent re-join) |
| `leaveCall(int $callId, int $userId): bool` | true if an active row flipped to `left` |
| `listParticipants(int $callId): array` | active roster, oldest-join first — rows: `{USER_ID:int, NAME:string, PROFILE_IMAGE:?string, ROLE:string, JOINED_AT:string}` |
| `countActiveParticipants(int $callId): int` | # of `active` participants |
| `isActiveParticipant(int $callId, int $userId): bool` | membership check |
| `putSignal(int $callId, int $fromUser, int $toUser, string $kind, string $payload): int` | new `SIGNAL_ID` (>0) on success; **0** on failure OR if `$kind` ∉ {offer,answer,ice} |
| `getSignalsFor(int $callId, int $toUser, int $sinceSignalId=0, int $limit=200): array` | directed signals to me since cursor, oldest first — rows: `{SIGNAL_ID:int, FROM_USER:int, KIND:string, PAYLOAD:string, CREATED_AT:string}` |
| `getRoom(int $callId): ?array` | `{CALL_ID, CALLER_ID, RECEIVER_ID, GROUP_ID, CALL_TYPE, STATUS, STARTED_AT, ENDED_AT}` or null |
| `getActiveRoomForGroup(int $groupId): ?array` | newest live room for a group: `{CALL_ID:int, CALL_TYPE:string, STATUS:string, CALLER_ID:int}` or null (lets late joiner find an ongoing call) |
| `createRoom(int $callerId, ?int $receiverId, ?int $groupId, string $callType): int` | new `CALL_ID` (creator inserted as `host`/`active` atomically), 0 on failure |
| `endRoom(int $callId): bool` | sets room `ended`+ENDED_AT and marks all active participants `left`; true if room updated |

### Exact column-name notes for backend (do not rename)
- Roster display fields come straight from `USERS`: **`USER_ID`, `NAME`,
  `PROFILE_IMAGE`** (PROFILE_IMAGE is nullable). Same names the chat poll already uses.
- Signal rows expose **`SIGNAL_ID`** (the cursor the client echoes back as
  `sinceSignalId`), **`FROM_USER`**, **`KIND`**, **`PAYLOAD`**. `TO_USER` is NOT
  returned (it is always the caller). Advance the cursor to `max(SIGNAL_ID)`.
- `KIND` is validated in `putSignal` — backend can pass the raw client value;
  invalid kinds return 0 (treat as failure), so no need to re-whitelist.

## Suggested backend action mapping (for `feedora-backend`, not built here)
- `join_call` → `createRoom` (new) or `joinCall` (existing room) + `listParticipants`
- `leave_call` → `leaveCall` (+ `endRoom` when `countActiveParticipants==0`)
- `get_call_room` → `listParticipants` + `getSignalsFor(callId, me, sinceSignalId)`
- `send_peer_signal` → `putSignal(callId, me, toUser, kind, payload)`
- Membership/author guard stays in chat.php (`isGroupMember`/receiver check) +
  `csrf_verify()` before any state change. Repo methods are scope-safe but NOT a
  substitute for the membership guard.

## Conflicts with canonical `Feedora.sql`
None destructive. Two new `CREATE TABLE IF NOT EXISTS` appended after
`CALL_ICE_CANDIDATES`. No existing column renamed or dropped. `idx_call_group_status`
is added by the migration if absent (Feedora.sql's CALL_SESSIONS lacks it, but the
chat-groups migration already adds it on real deploys — additive, safe to re-run).

## Status
- [x] Migration `scripts/2026_multiparty_calls.sql`
- [x] `includes/CallRepository.php` (+ required in chat.php)
- [x] `ensureChatSchema()` mirror in chat.php
- [x] Appended to canonical Feedora.sql
- [x] `php -l` clean on CallRepository.php + chat.php
