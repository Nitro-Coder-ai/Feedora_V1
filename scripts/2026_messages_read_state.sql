-- =============================================================================
-- Feedora — Chat: real read-state + faster conversation queries.
-- Re-runnable guard for the column is in the apply script (no ADD IF NOT EXISTS
-- on this MySQL). Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_messages_read_state.sql
-- =============================================================================

-- Index to fetch a conversation's newest messages and unread counts cheaply.
-- Guarded so the script can be re-run.
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MESSAGES' AND INDEX_NAME = 'idx_messages_pair_id');
SET @sql := IF(@has_idx = 0,
   'CREATE INDEX idx_messages_pair_id ON MESSAGES (RECEIVER_ID, SENDER_ID, MESSAGE_ID)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
