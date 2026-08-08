<?php
/**
 * MessageRepository — data access for the messaging-enhancements bundle.
 *
 * Covers: P1 pinned messages, P2 edit/soft-delete, P3 read-state + typing,
 * P4 message-content search. Prepared statements only (the PDO connection runs
 * with PDO::ATTR_EMULATE_PREPARES = false, so every placeholder is bound exactly
 * once and LIMIT is bound as an int).
 *
 * Conversation scope is a single canonical CONV_KEY string so DMs and groups are
 * handled uniformly:
 *   DM    -> 'dm:<loUserId>:<hiUserId>'   (the two user ids sorted ascending)
 *   group -> 'grp:<groupId>'
 * Build keys with dmConvKey()/groupConvKey() so both sides of a DM resolve to the
 * same key regardless of who is "me".
 */
class MessageRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Canonical conversation keys ──────────────────────────────────────────

    /** Canonical DM scope key for the (orderless) pair of users. */
    public static function dmConvKey(int $userA, int $userB): string
    {
        $lo = min($userA, $userB);
        $hi = max($userA, $userB);
        return 'dm:' . $lo . ':' . $hi;
    }

    /** Canonical group scope key. */
    public static function groupConvKey(int $groupId): string
    {
        return 'grp:' . $groupId;
    }

    // ── P1 — Pinned messages ─────────────────────────────────────────────────

    /**
     * Pin a message within its conversation scope. Idempotent: re-pinning the
     * same message is a no-op (UNIQUE(MESSAGE_ID)).
     *
     * @param string $scopeType 'dm' | 'group'
     * @param int|null $groupId  group id for group scope, null for DM
     * @return bool true on success (including already-pinned)
     */
    public function pinMessage(int $messageId, int $pinnedBy, string $convKey, string $scopeType, ?int $groupId = null): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO MESSAGE_PINS (MESSAGE_ID, CONV_KEY, SCOPE_TYPE, GROUP_ID, PINNED_BY, PINNED_AT)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE PINNED_BY = VALUES(PINNED_BY), PINNED_AT = VALUES(PINNED_AT)"
            );
            return $stmt->execute([$messageId, $convKey, $scopeType, $groupId, $pinnedBy]);
        } catch (PDOException $e) {
            error_log('MessageRepository::pinMessage ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unpin a message. CONV_KEY is required so a caller can only unpin within the
     * scope it is authorized for.
     *
     * @return bool true if a pin row was removed
     */
    public function unpinMessage(int $messageId, string $convKey): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM MESSAGE_PINS WHERE MESSAGE_ID = ? AND CONV_KEY = ?"
            );
            $stmt->execute([$messageId, $convKey]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MessageRepository::unpinMessage ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List pinned messages for a conversation, newest pin first.
     *
     * @return array<int,array{
     *   PIN_ID:int, MESSAGE_ID:int, CONV_KEY:string, SCOPE_TYPE:string,
     *   PINNED_BY:int, PINNED_AT:string,
     *   SENDER_ID:int, CONTENT:?string, IMAGE_URL:?string, SENT_AT:string,
     *   EDITED_AT:?string, IS_DELETED:int,
     *   SENDER_NAME:string, SENDER_IMAGE:?string
     * }>
     */
    public function listPins(string $convKey): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT p.PIN_ID, p.MESSAGE_ID, p.CONV_KEY, p.SCOPE_TYPE, p.PINNED_BY, p.PINNED_AT,
                        m.SENDER_ID, m.CONTENT, m.IMAGE_URL, m.SENT_AT, m.EDITED_AT, m.IS_DELETED,
                        u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                 FROM MESSAGE_PINS p
                 JOIN MESSAGES m ON m.MESSAGE_ID = p.MESSAGE_ID
                 JOIN USERS u    ON u.USER_ID = m.SENDER_ID
                 WHERE p.CONV_KEY = ?
                 ORDER BY p.PINNED_AT DESC, p.PIN_ID DESC"
            );
            $stmt->execute([$convKey]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MessageRepository::listPins ' . $e->getMessage());
            return [];
        }
    }

    // ── P2 — Edit & soft delete ──────────────────────────────────────────────

    /**
     * Soft-delete (tombstone) a message. Author-only. Also removes any pin so a
     * deleted message can't linger in the pinned panel. Returns true if the
     * caller actually owned a non-deleted message and it was tombstoned.
     */
    public function softDeleteMessage(int $messageId, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "UPDATE MESSAGES SET IS_DELETED = 1
                 WHERE MESSAGE_ID = ? AND SENDER_ID = ? AND IS_DELETED = 0"
            );
            $stmt->execute([$messageId, $userId]);
            $changed = $stmt->rowCount() > 0;
            if ($changed) {
                $this->pdo->prepare("DELETE FROM MESSAGE_PINS WHERE MESSAGE_ID = ?")
                          ->execute([$messageId]);
            }
            $this->pdo->commit();
            return $changed;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('MessageRepository::softDeleteMessage ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Edit a message's text. Author-only, not allowed on tombstoned messages.
     * Stamps EDITED_AT = NOW() so the UI can show an "edited" marker.
     * Returns true if a row was updated.
     */
    public function editMessage(int $messageId, int $userId, string $content): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE MESSAGES SET CONTENT = ?, EDITED_AT = NOW()
                 WHERE MESSAGE_ID = ? AND SENDER_ID = ? AND IS_DELETED = 0"
            );
            $stmt->execute([$content, $messageId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MessageRepository::editMessage ' . $e->getMessage());
            return false;
        }
    }

    // ── P3 — Typing + read state ─────────────────────────────────────────────

    /** Upsert the caller's typing heartbeat for a conversation. */
    public function setTyping(int $userId, string $convKey): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO MESSAGE_TYPING (USER_ID, CONV_KEY, UPDATED_AT)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE UPDATED_AT = NOW()"
            );
            return $stmt->execute([$userId, $convKey]);
        } catch (PDOException $e) {
            error_log('MessageRepository::setTyping ' . $e->getMessage());
            return false;
        }
    }

    /** Clear the caller's typing row (e.g. on send or blur). */
    public function clearTyping(int $userId, string $convKey): bool
    {
        try {
            return $this->pdo->prepare(
                "DELETE FROM MESSAGE_TYPING WHERE USER_ID = ? AND CONV_KEY = ?"
            )->execute([$userId, $convKey]);
        } catch (PDOException $e) {
            error_log('MessageRepository::clearTyping ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Users currently typing in a conversation (heartbeat within $windowSeconds),
     * excluding the caller. Returned newest-heartbeat first.
     *
     * @return array<int,array{USER_ID:int, NAME:string, PROFILE_IMAGE:?string}>
     */
    public function getTyping(string $convKey, int $excludeUserId, int $windowSeconds = 6): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.USER_ID, u.NAME, u.PROFILE_IMAGE
                 FROM MESSAGE_TYPING t
                 JOIN USERS u ON u.USER_ID = t.USER_ID
                 WHERE t.CONV_KEY = ?
                   AND t.USER_ID <> ?
                   AND t.UPDATED_AT > DATE_SUB(NOW(), INTERVAL ? SECOND)
                 ORDER BY t.UPDATED_AT DESC"
            );
            $stmt->bindValue(1, $convKey, PDO::PARAM_STR);
            $stmt->bindValue(2, $excludeUserId, PDO::PARAM_INT);
            $stmt->bindValue(3, $windowSeconds, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MessageRepository::getTyping ' . $e->getMessage());
            return [];
        }
    }

    /**
     * DM "Seen" state: the id of the latest message I sent to $peerId that the
     * peer has read (IS_READ = 1). 0 when the peer hasn't read any of mine yet.
     * The frontend renders "Seen" under the message whose id == last_seen_message_id.
     *
     * @return array{peer_id:int, last_seen_message_id:int}
     */
    public function getReadState(int $me, int $peerId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(MESSAGE_ID), 0) AS last_seen
                 FROM MESSAGES
                 WHERE SENDER_ID = ? AND RECEIVER_ID = ? AND IS_READ = 1"
            );
            $stmt->execute([$me, $peerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'peer_id'              => $peerId,
                'last_seen_message_id' => $row ? (int) $row['last_seen'] : 0,
            ];
        } catch (PDOException $e) {
            error_log('MessageRepository::getReadState ' . $e->getMessage());
            return ['peer_id' => $peerId, 'last_seen_message_id' => 0];
        }
    }

    // ── P4 — Search within the user's own conversations ──────────────────────

    /**
     * Substring search over message CONTENT scoped to conversations the user is
     * part of: DMs they sent or received, plus groups they belong to. Tombstoned
     * messages are excluded. Newest match first.
     *
     * Uses LIKE '%term%' rather than FULLTEXT — see 01_schema.md for the rationale
     * (substring/partial matching + small post-scope candidate set + OR-scope that
     * a FULLTEXT index can't combine with). The '%' wrapping is done here, not by
     * the caller; the caller passes the raw user term.
     *
     * @return array<int,array{
     *   MESSAGE_ID:int, SENDER_ID:int, RECEIVER_ID:?int, GROUP_ID:?int,
     *   CONTENT:?string, IMAGE_URL:?string, SENT_AT:string,
     *   SENDER_NAME:string, SENDER_IMAGE:?string
     * }>
     */
    public function searchMessages(int $userId, string $term, int $limit = 30): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        // Escape LIKE wildcards in the user term so they are matched literally.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $like = '%' . $escaped . '%';
        try {
            $stmt = $this->pdo->prepare(
                "SELECT m.MESSAGE_ID, m.SENDER_ID, m.RECEIVER_ID, m.GROUP_ID,
                        m.CONTENT, m.IMAGE_URL, m.SENT_AT,
                        u.NAME AS SENDER_NAME, u.PROFILE_IMAGE AS SENDER_IMAGE
                 FROM MESSAGES m
                 JOIN USERS u ON u.USER_ID = m.SENDER_ID
                 WHERE m.IS_DELETED = 0
                   AND m.CONTENT LIKE ? ESCAPE '\\\\'
                   AND (
                         m.SENDER_ID   = ?
                      OR m.RECEIVER_ID = ?
                      OR m.GROUP_ID IN (SELECT GROUP_ID FROM CHAT_GROUP_MEMBERS WHERE USER_ID = ?)
                   )
                 ORDER BY m.MESSAGE_ID DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $like, PDO::PARAM_STR);
            $stmt->bindValue(2, $userId, PDO::PARAM_INT);
            $stmt->bindValue(3, $userId, PDO::PARAM_INT);
            $stmt->bindValue(4, $userId, PDO::PARAM_INT);
            $stmt->bindValue(5, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MessageRepository::searchMessages ' . $e->getMessage());
            return [];
        }
    }
}
