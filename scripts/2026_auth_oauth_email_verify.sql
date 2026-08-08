-- ============================================================================
-- Feedora — social login (Google/Facebook) + email verification
-- Adds verification + OAuth columns to USERS. Idempotent on MySQL 5.7
-- (no ADD COLUMN IF NOT EXISTS) via information_schema guards.
-- ============================================================================

-- EMAIL_VERIFIED: gate dashboard access until the address is confirmed.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'EMAIL_VERIFIED');
SET @s := IF(@c = 0, 'ALTER TABLE USERS ADD COLUMN EMAIL_VERIFIED TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- VERIFICATION_TOKEN: single-use token emailed to the user (sha256 hex).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'VERIFICATION_TOKEN');
SET @s := IF(@c = 0, 'ALTER TABLE USERS ADD COLUMN VERIFICATION_TOKEN VARCHAR(64) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- VERIFICATION_EXPIRES: token TTL (24h from issue).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'VERIFICATION_EXPIRES');
SET @s := IF(@c = 0, 'ALTER TABLE USERS ADD COLUMN VERIFICATION_EXPIRES DATETIME NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- AUTH_PROVIDER: 'local' | 'google' | 'facebook'.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'AUTH_PROVIDER');
SET @s := IF(@c = 0, "ALTER TABLE USERS ADD COLUMN AUTH_PROVIDER VARCHAR(20) NOT NULL DEFAULT 'local'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- OAUTH_ID: the provider's stable user id (so re-logins map to the same row).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'OAUTH_ID');
SET @s := IF(@c = 0, 'ALTER TABLE USERS ADD COLUMN OAUTH_ID VARCHAR(64) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Index for fast token lookups.
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND INDEX_NAME = 'idx_users_verify_token');
SET @s := IF(@c = 0, 'CREATE INDEX idx_users_verify_token ON USERS (VERIFICATION_TOKEN)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Index for OAuth provider lookups.
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND INDEX_NAME = 'idx_users_oauth');
SET @s := IF(@c = 0, 'CREATE INDEX idx_users_oauth ON USERS (AUTH_PROVIDER, OAUTH_ID)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Grandfather existing accounts: anyone created before this migration keeps
-- access (don't lock out current users). New signups default to 0 (unverified).
UPDATE USERS SET EMAIL_VERIFIED = 1 WHERE EMAIL_VERIFIED = 0 AND PASSWORD_HASH IS NOT NULL;
