-- =============================================================================
-- Feedora — Messaging enhancements (P1 pins, P2 edit/delete, P3 read/typing, P4 search)
-- Idempotent + re-runnable. Tables use IF NOT EXISTS; column / index / nullability
-- changes are guarded via a temporary stored procedure that checks
-- information_schema (pre-8.0.29 MySQL has no "ADD COLUMN IF NOT EXISTS").
-- Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_messages_enhancements.sql
-- No agent runs this against a live DB — the user applies it manually.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- P1 — Pinned messages.
-- A pin is scoped to a conversation. CONV_KEY is the canonical scope key:
--   DM    -> 'dm:<loUserId>:<hiUserId>'   (user ids sorted ascending)
--   group -> 'grp:<groupId>'
-- A message lives in exactly one conversation, so UNIQUE(MESSAGE_ID) enforces
-- "pinned at most once per scope". List pins for a scope via idx_pins_conv.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS MESSAGE_PINS (
    PIN_ID      INT AUTO_INCREMENT PRIMARY KEY,
    MESSAGE_ID  INT NOT NULL,
    CONV_KEY    VARCHAR(64) NOT NULL,            -- canonical scope key (see header)
    SCOPE_TYPE  VARCHAR(8)  NOT NULL,            -- 'dm' | 'group'
    GROUP_ID    INT NULL,                        -- set for group scope, NULL for DM
    PINNED_BY   INT NOT NULL,
    PINNED_AT   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pin_message (MESSAGE_ID),
    KEY idx_pins_conv (CONV_KEY, PINNED_AT),
    FOREIGN KEY (MESSAGE_ID) REFERENCES MESSAGES(MESSAGE_ID)   ON DELETE CASCADE,
    FOREIGN KEY (PINNED_BY)  REFERENCES USERS(USER_ID)         ON DELETE CASCADE,
    FOREIGN KEY (GROUP_ID)   REFERENCES CHAT_GROUPS(GROUP_ID)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- P3 — Typing signal (ephemeral, carried on the existing unified poll).
-- One short-lived row per (user, conversation), upserted via ON DUPLICATE KEY.
-- The poll reads rows for the open CONV_KEY whose UPDATED_AT is within a few
-- seconds. No new timers; rows are tiny and self-expire by staleness.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS MESSAGE_TYPING (
    USER_ID     INT NOT NULL,
    CONV_KEY    VARCHAR(64) NOT NULL,            -- same canonical scope key as pins
    UPDATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (USER_ID, CONV_KEY),
    KEY idx_typing_conv (CONV_KEY, UPDATED_AT),
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Guarded column / index / nullability changes on MESSAGES.
--   P2: EDITED_AT, IS_DELETED.
--   P3: IS_READ (defensive — runtime has it but canonical Feedora.sql does not),
--       idx_messages_seen for the "Seen" lookup.
--   P4: idx_messages_group for group-scoped search + group fetch.
--   Also: GROUP_ID + RECEIVER_ID-nullable (defensive; chat groups migration adds
--         these, re-asserted here so this file stands alone).
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS feedora_msg_enh_migrate;
DELIMITER //
CREATE PROCEDURE feedora_msg_enh_migrate()
BEGIN
    -- GROUP_ID (defensive; needed so search/pins compile even on a fresh DB)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND COLUMN_NAME = 'GROUP_ID') THEN
        ALTER TABLE MESSAGES ADD COLUMN GROUP_ID INT NULL AFTER RECEIVER_ID;
        ALTER TABLE MESSAGES ADD CONSTRAINT fk_messages_group
            FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE;
    END IF;

    -- RECEIVER_ID nullable (group messages have no single receiver)
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                 AND COLUMN_NAME = 'RECEIVER_ID' AND IS_NULLABLE = 'NO') THEN
        ALTER TABLE MESSAGES MODIFY COLUMN RECEIVER_ID INT NULL;
    END IF;

    -- IS_READ (DM read receipts). Defensive: canonical dump omits it.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND COLUMN_NAME = 'IS_READ') THEN
        ALTER TABLE MESSAGES ADD COLUMN IS_READ TINYINT(1) NOT NULL DEFAULT 0 AFTER REACTIONS;
    END IF;

    -- P2: EDITED_AT (NULL until first edit; presence => "edited" marker)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND COLUMN_NAME = 'EDITED_AT') THEN
        ALTER TABLE MESSAGES ADD COLUMN EDITED_AT TIMESTAMP NULL DEFAULT NULL AFTER SENT_AT;
    END IF;

    -- P2: IS_DELETED (soft delete / tombstone)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND COLUMN_NAME = 'IS_DELETED') THEN
        ALTER TABLE MESSAGES ADD COLUMN IS_DELETED TINYINT(1) NOT NULL DEFAULT 0 AFTER EDITED_AT;
    END IF;

    -- P3: index for the "Seen" lookup (last outgoing message read by the peer)
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND INDEX_NAME = 'idx_messages_seen') THEN
        ALTER TABLE MESSAGES ADD INDEX idx_messages_seen (SENDER_ID, RECEIVER_ID, IS_READ, MESSAGE_ID);
    END IF;

    -- P4: index for group-scoped search + group message fetch
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES'
                     AND INDEX_NAME = 'idx_messages_group') THEN
        ALTER TABLE MESSAGES ADD INDEX idx_messages_group (GROUP_ID, MESSAGE_ID);
    END IF;
END //
DELIMITER ;
CALL feedora_msg_enh_migrate();
DROP PROCEDURE IF EXISTS feedora_msg_enh_migrate;
