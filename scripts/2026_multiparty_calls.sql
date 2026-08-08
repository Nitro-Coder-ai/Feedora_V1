-- =============================================================================
-- Feedora — Discord-style multi-party (mesh) call signaling.
-- Turns CALL_SESSIONS (one OFFER/ANSWER pair = 1:1 only) into an N-party "room":
-- one CALL_SESSIONS row is the room, CALL_PARTICIPANTS tracks who is in it, and
-- CALL_PEER_SIGNALS carries DIRECTED offer/answer/ICE between every ordered pair
-- of peers (mesh). The legacy CALL_ICE_CANDIDATES table is left intact for
-- back-compat with the 1:1 path; the mesh uses CALL_PEER_SIGNALS exclusively.
--
-- Idempotent + re-runnable. Tables use IF NOT EXISTS; the CALL_SESSIONS index add
-- is guarded via a temporary stored procedure that checks information_schema
-- (pre-8.0.29 MySQL has no "ADD INDEX IF NOT EXISTS"). FKs cascade from
-- USERS / CALL_SESSIONS / CHAT_GROUPS so rooms self-clean on deletion.
-- Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_multiparty_calls.sql
-- No agent runs this against a live DB — the user applies it manually.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- CALL_PARTICIPANTS — membership/roster of a call room.
-- One row per (CALL_ID, USER_ID). STATUS flips 'active' -> 'left' on leave so we
-- keep a join/leave audit instead of deleting. "Who is in this call right now"
-- = rows WHERE CALL_ID = ? AND STATUS = 'active' (idx_callpart_active).
-- A user re-joining the same room upserts the existing row back to 'active'.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS CALL_PARTICIPANTS (
    CALL_ID    INT NOT NULL,
    USER_ID    INT NOT NULL,
    ROLE       VARCHAR(20) NOT NULL DEFAULT 'participant',  -- 'host' | 'participant'
    STATUS     VARCHAR(20) NOT NULL DEFAULT 'active',       -- 'active' | 'left'
    JOINED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    LEFT_AT    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (CALL_ID, USER_ID),
    KEY idx_callpart_active (CALL_ID, STATUS),
    KEY idx_callpart_user (USER_ID),
    FOREIGN KEY (CALL_ID) REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- CALL_PEER_SIGNALS — directed mesh signaling.
-- Each row is ONE signal FROM one peer TO another peer within a room. KIND is
-- 'offer' | 'answer' | 'ice'; PAYLOAD holds the SDP string (offer/answer) or the
-- ICE candidate JSON. The recipient polls "signals addressed to me since my
-- cursor": WHERE CALL_ID = ? AND TO_USER = ? AND SIGNAL_ID > ? ORDER BY SIGNAL_ID
-- (idx_peersig_inbox). SIGNAL_ID is the monotonic cursor the client advances.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS CALL_PEER_SIGNALS (
    SIGNAL_ID   INT AUTO_INCREMENT PRIMARY KEY,
    CALL_ID     INT NOT NULL,
    FROM_USER   INT NOT NULL,
    TO_USER     INT NOT NULL,
    KIND        VARCHAR(10) NOT NULL,                       -- 'offer' | 'answer' | 'ice'
    PAYLOAD     TEXT NOT NULL,                              -- SDP string or ICE candidate JSON
    CREATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_peersig_inbox (CALL_ID, TO_USER, SIGNAL_ID),
    FOREIGN KEY (CALL_ID)   REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE,
    FOREIGN KEY (FROM_USER) REFERENCES USERS(USER_ID)         ON DELETE CASCADE,
    FOREIGN KEY (TO_USER)   REFERENCES USERS(USER_ID)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Guarded index on the existing CALL_SESSIONS (the "room").
-- idx_call_group_status powers "is there an active group call for this group?"
-- It ships in scripts/2026_chat_groups_calls.sql but NOT in canonical Feedora.sql,
-- so re-assert it here defensively so this file stands alone on a fresh DB.
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS feedora_multiparty_calls_migrate;
DELIMITER //
CREATE PROCEDURE feedora_multiparty_calls_migrate()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CALL_SESSIONS'
                     AND INDEX_NAME = 'idx_call_group_status') THEN
        ALTER TABLE CALL_SESSIONS ADD INDEX idx_call_group_status (GROUP_ID, STATUS);
    END IF;
END //
DELIMITER ;
CALL feedora_multiparty_calls_migrate();
DROP PROCEDURE IF EXISTS feedora_multiparty_calls_migrate;
