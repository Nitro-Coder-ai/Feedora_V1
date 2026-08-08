<?php
/**
 * CallRepository — data access for Discord-style multi-party (mesh) calls.
 *
 * A call "room" is one CALL_SESSIONS row (kept from the 1:1 design; group call =
 * one row with GROUP_ID set). CALL_PARTICIPANTS is the live roster; signalling is
 * DIRECTED per ordered peer-pair through CALL_PEER_SIGNALS so a mesh of N peers
 * can exchange their own offer/answer/ICE.
 *
 * Prepared statements only (the PDO connection runs with
 * PDO::ATTR_EMULATE_PREPARES = false, so LIMIT and cursors are bound as ints).
 *
 * Author / group-membership authorization is the BACKEND's responsibility
 * (chat.php verifies isGroupMember / receiver before calling these). These
 * methods still scope every query by CALL_ID + USER_ID so a caller can only ever
 * touch its own roster row and its own addressed signals.
 */
class CallRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Roster ────────────────────────────────────────────────────────────────

    /**
     * Join (or re-join) a call room. Upserts the caller's roster row to 'active'
     * and stamps JOINED_AT = NOW(), clearing any prior LEFT_AT. Idempotent: a
     * peer that double-joins stays a single 'active' row.
     *
     * @param string $role 'host' | 'participant' (host typically = the initiator)
     * @return bool true on success
     */
    public function joinCall(int $callId, int $userId, string $role = 'participant'): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO CALL_PARTICIPANTS (CALL_ID, USER_ID, ROLE, STATUS, JOINED_AT, LEFT_AT)
                 VALUES (?, ?, ?, 'active', NOW(), NULL)
                 ON DUPLICATE KEY UPDATE STATUS = 'active', JOINED_AT = NOW(), LEFT_AT = NULL"
            );
            return $stmt->execute([$callId, $userId, $role]);
        } catch (PDOException $e) {
            error_log('CallRepository::joinCall ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark the caller as having left the room: STATUS = 'left', LEFT_AT = NOW().
     * Does not delete the row (kept for the join/leave audit). Returns true if a
     * row was actually flipped (i.e. the caller was an active participant).
     */
    public function leaveCall(int $callId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE CALL_PARTICIPANTS SET STATUS = 'left', LEFT_AT = NOW()
                 WHERE CALL_ID = ? AND USER_ID = ? AND STATUS = 'active'"
            );
            $stmt->execute([$callId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('CallRepository::leaveCall ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Active participants in a room, with display fields, oldest-join first.
     *
     * @return array<int,array{
     *   USER_ID:int, NAME:string, PROFILE_IMAGE:?string,
     *   ROLE:string, JOINED_AT:string
     * }>
     */
    public function listParticipants(int $callId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT p.USER_ID, u.NAME, u.PROFILE_IMAGE, p.ROLE, p.JOINED_AT
                 FROM CALL_PARTICIPANTS p
                 JOIN USERS u ON u.USER_ID = p.USER_ID
                 WHERE p.CALL_ID = ? AND p.STATUS = 'active'
                 ORDER BY p.JOINED_AT ASC, p.USER_ID ASC"
            );
            $stmt->execute([$callId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CallRepository::listParticipants ' . $e->getMessage());
            return [];
        }
    }

    /** Count of currently-active participants in a room (0 when empty/ended). */
    public function countActiveParticipants(int $callId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM CALL_PARTICIPANTS WHERE CALL_ID = ? AND STATUS = 'active'"
            );
            $stmt->execute([$callId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('CallRepository::countActiveParticipants ' . $e->getMessage());
            return 0;
        }
    }

    /** True if $userId is an active participant of $callId. */
    public function isActiveParticipant(int $callId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM CALL_PARTICIPANTS
                 WHERE CALL_ID = ? AND USER_ID = ? AND STATUS = 'active' LIMIT 1"
            );
            $stmt->execute([$callId, $userId]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('CallRepository::isActiveParticipant ' . $e->getMessage());
            return false;
        }
    }

    // ── Directed mesh signalling ────────────────────────────────────────────────

    /**
     * Enqueue one directed signal (offer/answer/ice) FROM a peer TO a peer.
     * KIND is normalised to one of 'offer' | 'answer' | 'ice'; anything else is
     * rejected (returns 0) so the column stays a clean enum-by-convention.
     *
     * @return int the new SIGNAL_ID (> 0) on success, 0 on failure/invalid KIND
     */
    public function putSignal(int $callId, int $fromUser, int $toUser, string $kind, string $payload): int
    {
        if (!in_array($kind, ['offer', 'answer', 'ice'], true)) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO CALL_PEER_SIGNALS (CALL_ID, FROM_USER, TO_USER, KIND, PAYLOAD, CREATED_AT)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$callId, $fromUser, $toUser, $kind, $payload]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('CallRepository::putSignal ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Directed signals addressed TO me in a room since my cursor, oldest first.
     * The caller advances $sinceSignalId to the max SIGNAL_ID it has consumed.
     *
     * @return array<int,array{
     *   SIGNAL_ID:int, FROM_USER:int, KIND:string, PAYLOAD:string, CREATED_AT:string
     * }>
     */
    public function getSignalsFor(int $callId, int $toUser, int $sinceSignalId = 0, int $limit = 200): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT SIGNAL_ID, FROM_USER, KIND, PAYLOAD, CREATED_AT
                 FROM CALL_PEER_SIGNALS
                 WHERE CALL_ID = ? AND TO_USER = ? AND SIGNAL_ID > ?
                 ORDER BY SIGNAL_ID ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $callId, PDO::PARAM_INT);
            $stmt->bindValue(2, $toUser, PDO::PARAM_INT);
            $stmt->bindValue(3, $sinceSignalId, PDO::PARAM_INT);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CallRepository::getSignalsFor ' . $e->getMessage());
            return [];
        }
    }

    // ── Room lifecycle ──────────────────────────────────────────────────────────

    /**
     * Fetch a call room row (the CALL_SESSIONS record). Returns null if missing.
     *
     * @return array{
     *   CALL_ID:int, CALLER_ID:int, RECEIVER_ID:?int, GROUP_ID:?int,
     *   CALL_TYPE:string, STATUS:string, STARTED_AT:string, ENDED_AT:?string
     * }|null
     */
    public function getRoom(int $callId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT CALL_ID, CALLER_ID, RECEIVER_ID, GROUP_ID, CALL_TYPE, STATUS, STARTED_AT, ENDED_AT
                 FROM CALL_SESSIONS WHERE CALL_ID = ?"
            );
            $stmt->execute([$callId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('CallRepository::getRoom ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The active room for a group, if any (newest 'ringing'/'active' first). Used
     * to let a late joiner discover an ongoing group call instead of starting a
     * new one. Returns null when no live room exists.
     *
     * @return array{CALL_ID:int, CALL_TYPE:string, STATUS:string, CALLER_ID:int}|null
     */
    public function getActiveRoomForGroup(int $groupId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT CALL_ID, CALL_TYPE, STATUS, CALLER_ID
                 FROM CALL_SESSIONS
                 WHERE GROUP_ID = ? AND STATUS IN ('ringing','active')
                 ORDER BY CALL_ID DESC LIMIT 1"
            );
            $stmt->execute([$groupId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('CallRepository::getActiveRoomForGroup ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a multi-party room and put the creator in it as 'host' atomically.
     * Returns the new CALL_ID, or 0 on failure.
     *
     * @param string $callType 'voice' | 'video'
     */
    public function createRoom(int $callerId, ?int $receiverId, ?int $groupId, string $callType): int
    {
        $callType = $callType === 'video' ? 'video' : 'voice';
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "INSERT INTO CALL_SESSIONS (CALLER_ID, RECEIVER_ID, GROUP_ID, CALL_TYPE, STATUS)
                 VALUES (?, ?, ?, ?, 'ringing')"
            );
            $stmt->execute([$callerId, $receiverId, $groupId, $callType]);
            $callId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare(
                "INSERT INTO CALL_PARTICIPANTS (CALL_ID, USER_ID, ROLE, STATUS, JOINED_AT)
                 VALUES (?, ?, 'host', 'active', NOW())
                 ON DUPLICATE KEY UPDATE STATUS = 'active', JOINED_AT = NOW(), LEFT_AT = NULL"
            )->execute([$callId, $callerId]);
            $this->pdo->commit();
            return $callId;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('CallRepository::createRoom ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * End a room: set STATUS = 'ended', stamp ENDED_AT, and mark every still-active
     * participant as 'left'. Returns true if the room row was updated.
     */
    public function endRoom(int $callId): bool
    {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "UPDATE CALL_SESSIONS SET STATUS = 'ended', ENDED_AT = NOW()
                 WHERE CALL_ID = ? AND STATUS <> 'ended'"
            );
            $stmt->execute([$callId]);
            $changed = $stmt->rowCount() > 0;
            $this->pdo->prepare(
                "UPDATE CALL_PARTICIPANTS SET STATUS = 'left', LEFT_AT = NOW()
                 WHERE CALL_ID = ? AND STATUS = 'active'"
            )->execute([$callId]);
            $this->pdo->commit();
            return $changed;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('CallRepository::endRoom ' . $e->getMessage());
            return false;
        }
    }
}
