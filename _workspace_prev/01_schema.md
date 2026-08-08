# 01 — Schema & Data Contract: Messaging Enhancements (P1–P4)

Data layer for the four-feature messaging bundle. One idempotent dated migration
(`scripts/2026_messages_enhancements.sql`) + a new `includes/MessageRepository.php`.
Backend (`02_backend.md`) builds against the contract below; QA cross-checks columns
against repo reads.

> **CONV_KEY** is the single canonical conversation scope used by pins + typing:
> - DM → `dm:<loUserId>:<hiUserId>` (the two user ids sorted ascending — both
>   participants resolve to the same key regardless of who is "me")
> - group → `grp:<groupId>`
> Build it with `MessageRepository::dmConvKey($a,$b)` / `::groupConvKey($gid)`.

---

## 1. Migration SQL

Full SQL is in **`scripts/2026_messages_enhancements.sql`**. Summary:

- **New tables** (`CREATE TABLE IF NOT EXISTS`): `MESSAGE_PINS`, `MESSAGE_TYPING`.
- **Guarded `MESSAGES` changes** (temporary stored proc checking `information_schema`,
  pre-8.0.29 safe, re-runnable):
  - `EDITED_AT TIMESTAMP NULL` (P2)
  - `IS_DELETED TINYINT(1) NOT NULL DEFAULT 0` (P2)
  - `IS_READ TINYINT(1) NOT NULL DEFAULT 0` — **defensive add** (see Decisions §4)
  - `GROUP_ID INT NULL` + FK, and `RECEIVER_ID` made nullable — defensive re-assert
  - index `idx_messages_seen (SENDER_ID, RECEIVER_ID, IS_READ, MESSAGE_ID)` (P3)
  - index `idx_messages_group (GROUP_ID, MESSAGE_ID)` (P4 + group fetch)

Apply: `mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_messages_enhancements.sql`
(user runs it; no agent touches the live DB). `Feedora.sql` will be appended only
after this migration is finalized.

---

## 2. Data contract — tables

### MESSAGE_PINS (new) — P1
| Column | Type | Null | Notes | Exposed by |
|--------|------|------|-------|------------|
| PIN_ID | INT AUTO_INCREMENT PK | no | | listPins |
| MESSAGE_ID | INT | no | FK→MESSAGES, **UNIQUE** (pinned once per scope), ON DELETE CASCADE | pinMessage / unpinMessage / listPins |
| CONV_KEY | VARCHAR(64) | no | canonical scope key | pinMessage / listPins |
| SCOPE_TYPE | VARCHAR(8) | no | `'dm'` \| `'group'` | pinMessage / listPins |
| GROUP_ID | INT | yes | set for group scope, FK→CHAT_GROUPS, ON DELETE CASCADE | pinMessage |
| PINNED_BY | INT | no | FK→USERS | pinMessage / listPins |
| PINNED_AT | TIMESTAMP | no | default NOW() | listPins (ORDER BY) |

Indexes: `uq_pin_message(MESSAGE_ID)`, `idx_pins_conv(CONV_KEY, PINNED_AT)`.

### MESSAGE_TYPING (new) — P3
| Column | Type | Null | Notes | Exposed by |
|--------|------|------|-------|------------|
| USER_ID | INT | no | PK part, FK→USERS | setTyping / getTyping(excluded) |
| CONV_KEY | VARCHAR(64) | no | PK part | setTyping / getTyping |
| UPDATED_AT | TIMESTAMP | no | default NOW() ON UPDATE NOW() (heartbeat) | getTyping (window filter) |

Indexes: PK `(USER_ID, CONV_KEY)`, `idx_typing_conv(CONV_KEY, UPDATED_AT)`.

### MESSAGES (changed) — P2/P3/P4
Existing columns unchanged (`MESSAGE_ID, SENDER_ID, RECEIVER_ID, GROUP_ID, CONTENT,
IMAGE_URL, IMAGE_PUBLIC_ID, REACTIONS, IS_READ, SENT_AT`). Added/affected:
| Column | Type | Null | Notes | Exposed by |
|--------|------|------|-------|------------|
| EDITED_AT | TIMESTAMP | yes | NULL until first edit; non-null ⇒ show "edited" | editMessage sets; listPins/searchMessages read |
| IS_DELETED | TINYINT(1) | no | 0/1 soft-delete tombstone | softDeleteMessage sets; search/listPins read |
| IS_READ | TINYINT(1) | no | DM read flag (defensive add) | getReadState reads |
| RECEIVER_ID | INT | **yes** | now nullable (group msgs) | — |
| GROUP_ID | INT | yes | FK→CHAT_GROUPS | searchMessages reads |

> Existing chat.php columns the backend must NOT alter: `REACTIONS` (JSON text),
> `IMAGE_PUBLIC_ID`. The read flag is `IS_READ` (TINYINT), already written by
> chat.php's `UPDATE MESSAGES SET IS_READ = 1 ...` flows.

---

## 3. MessageRepository — method signatures + return shapes

`includes/MessageRepository.php`, constructed as `new MessageRepository($pdo)`
(matches `StoryRepository`). Prepared statements only. Static key helpers:
`MessageRepository::dmConvKey(int,int): string`, `::groupConvKey(int): string`.

### P1 — Pins
```php
pinMessage(int $messageId, int $pinnedBy, string $convKey, string $scopeType, ?int $groupId = null): bool
// idempotent (re-pin = no-op). $scopeType 'dm'|'group'; $groupId null for DM.

unpinMessage(int $messageId, string $convKey): bool   // true if a row was removed

listPins(string $convKey): array
// rows (newest pin first), keys:
//   PIN_ID:int, MESSAGE_ID:int, CONV_KEY:string, SCOPE_TYPE:string,
//   PINNED_BY:int, PINNED_AT:string,
//   SENDER_ID:int, CONTENT:?string, IMAGE_URL:?string, SENT_AT:string,
//   EDITED_AT:?string, IS_DELETED:int,
//   SENDER_NAME:string, SENDER_IMAGE:?string
```

### P2 — Edit & delete (author-only enforced in SQL via `SENDER_ID = ?`)
```php
softDeleteMessage(int $messageId, int $userId): bool
// sets IS_DELETED=1 AND removes any pin (one transaction). true only if the
// caller owned a non-deleted message. Frontend renders "This message was deleted".

editMessage(int $messageId, int $userId, string $content): bool
// sets CONTENT + EDITED_AT=NOW(); blocked on tombstoned msgs. true if updated.
```

### P3 — Typing + read state
```php
setTyping(int $userId, string $convKey): bool          // upsert heartbeat
clearTyping(int $userId, string $convKey): bool         // on send/blur

getTyping(string $convKey, int $excludeUserId, int $windowSeconds = 6): array
// rows: { USER_ID:int, NAME:string, PROFILE_IMAGE:?string } — others typing now

getReadState(int $me, int $peerId): array
// { peer_id:int, last_seen_message_id:int }
// last_seen_message_id = MAX(MESSAGE_ID) of my msgs to peer with IS_READ=1 (0 if none).
// Frontend shows "Seen" under the message whose id == last_seen_message_id.
```

### P4 — Search
```php
searchMessages(int $userId, string $term, int $limit = 30): array
// rows (newest first), keys:
//   MESSAGE_ID:int, SENDER_ID:int, RECEIVER_ID:?int, GROUP_ID:?int,
//   CONTENT:?string, IMAGE_URL:?string, SENT_AT:string,
//   SENDER_NAME:string, SENDER_IMAGE:?string
// Pass the RAW term (no %). Repo escapes LIKE wildcards + wraps with %…%.
// Scope: m.SENDER_ID=user OR m.RECEIVER_ID=user OR group the user belongs to;
//        IS_DELETED=0 excluded.
```

Exact search query shape the backend executes (real prepared stmt, distinct
placeholders bound once; `LIMIT` bound `PARAM_INT`):
```sql
SELECT m.MESSAGE_ID, m.SENDER_ID, m.RECEIVER_ID, m.GROUP_ID,
       m.CONTENT, m.IMAGE_URL, m.SENT_AT,
       u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
FROM MESSAGES m JOIN USERS u ON u.USER_ID = m.SENDER_ID
WHERE m.IS_DELETED = 0
  AND m.CONTENT LIKE ? ESCAPE '\\'
  AND ( m.SENDER_ID = ? OR m.RECEIVER_ID = ?
        OR m.GROUP_ID IN (SELECT GROUP_ID FROM CHAT_GROUP_MEMBERS WHERE USER_ID = ?) )
ORDER BY m.MESSAGE_ID DESC
LIMIT ?
```

---

## 4. Decisions / justifications

**Typing storage — lightweight upsert table, not a column.**
`MESSAGE_TYPING(USER_ID, CONV_KEY, UPDATED_AT)` with PK `(USER_ID, CONV_KEY)`. A row
is upserted on each keystroke heartbeat and read by the existing unified `poll`
(no new timer): `WHERE CONV_KEY = ? AND USER_ID <> ? AND UPDATED_AT > NOW() - 6s`.
Chosen over a `MESSAGES`/`USER_SESSIONS` column because typing is per-conversation
(not per-user-global), ephemeral, and self-expiring by staleness — one tiny row per
active typist, no schema coupling to message rows. `CONV_KEY` makes DM and group
typing identical. Backend may best-effort delete stale rows or rely on the window.

**Seen — derived from `MESSAGES.IS_READ`, no new table.**
DMs already flip `IS_READ=1` when the recipient opens/polls a thread (chat.php). The
backend exposes "seen" as `last_seen_message_id` = `MAX(MESSAGE_ID)` of my outgoing
messages to the peer with `IS_READ=1`. `idx_messages_seen(SENDER_ID, RECEIVER_ID,
IS_READ, MESSAGE_ID)` makes that a single index-range max. (Group read state stays in
the existing `CHAT_GROUP_READS` table — out of scope for this repo.)

**Search — LIKE '%term%' with scope indexes, NOT FULLTEXT.**
1. Users expect substring/partial matches ("piz" → "pizza"); InnoDB FULLTEXT is
   token/word-boundary based with a min-token-size (default 3) and stopwords, so it
   silently misses those.
2. The selective predicate is the user-scope (sender/receiver/group membership), not
   the text. After scoping, the candidate set is small (one user's messages), so a
   LIKE scan over it is cheap and predictable.
3. The OR-heavy scope predicate can't be combined with a FULLTEXT `MATCH` in the same
   query plan — the optimizer would pick one access path; adding a FULLTEXT index
   would just add write/index-maintenance cost for no win here.
`idx_messages_group` + existing sender/receiver indexes cover the scope filter.

---

## 5. Conflicts surfaced to the leader

- **`MESSAGES.IS_READ` and `MESSAGES.GROUP_ID` are NOT in canonical `Feedora.sql`**
  (the `CREATE TABLE MESSAGES` at line 273 omits both; `GROUP_ID` exists only as
  commented-out ALTERs at lines 446–448, and `IS_READ` appears only on
  `NOTIFICATIONS`). Yet chat.php's live queries use both. They are added at runtime
  by `ensureChatSchema()` / out-of-band migrations. **Decision taken:** this migration
  *defensively* guard-adds `IS_READ`, `GROUP_ID` (+FK) and makes `RECEIVER_ID`
  nullable, all idempotent, so the file stands alone on a fresh DB. No existing column
  was renamed. If the leader prefers to *assume* those columns always pre-exist, the
  three defensive blocks in the stored proc can be dropped — flagging rather than
  deciding silently.

---

## 6. Hand-off

- **→ feedora-backend:** contract above is stable; build endpoints for pin/unpin/list,
  edit/delete, setTyping/getTyping (wire onto the existing `poll`), getReadState (add
  `seen` to the DM poll payload), searchMessages. Enforce `csrf_verify()` on all
  state-changing actions and membership/author checks (repo already scopes author via
  `SENDER_ID`; backend must verify group membership before pin/list/typing on a group
  `CONV_KEY`, mirroring `isGroupMember()`).
- **→ feedora-qa:** cross-check these columns against repo reads — `MESSAGE_PINS`
  (CONV_KEY/SCOPE_TYPE/GROUP_ID), `MESSAGE_TYPING.UPDATED_AT` window, and
  `MESSAGES.{EDITED_AT, IS_DELETED, IS_READ}` presence after migration. Verify the
  IS_READ/GROUP_ID conflict note above.
