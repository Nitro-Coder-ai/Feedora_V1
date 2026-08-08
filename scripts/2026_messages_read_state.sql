-- =============================================================================
-- Feedora — Chat: real read-state + faster conversation queries.
-- Re-runnable guard for the column is in the apply script (no ADD IF NOT EXISTS
-- on this MySQL). Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_messages_read_state.sql
-- =============================================================================

-- Index to fetch a conversation's newest messages and unread counts cheaply.
CREATE INDEX idx_messages_pair_id ON MESSAGES (RECEIVER_ID, SENDER_ID, MESSAGE_ID);
