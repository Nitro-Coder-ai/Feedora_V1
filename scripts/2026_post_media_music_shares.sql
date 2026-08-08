-- ============================================================================
-- Feedora — richer posts: multi-media carousel, attached music/sound, sharing
--   * POST_MEDIA   — many media rows per post (image/video/audio) for carousels
--   * POSTS.AUDIO* — an attached music/sound track (Facebook-style)
--   * POSTS.SHARED_FROM — a repost points at the original post
--   * POST_SHARES  — who shared what (share counts, dedupe)
-- Idempotent on MySQL 5.7 (CREATE TABLE IF NOT EXISTS + information_schema guards)
-- ============================================================================

CREATE TABLE IF NOT EXISTS POST_MEDIA (
  MEDIA_ID        INT AUTO_INCREMENT PRIMARY KEY,
  POSTS_ID        INT NOT NULL,
  MEDIA_URL       TEXT NOT NULL,
  MEDIA_PUBLIC_ID VARCHAR(255) NULL,
  MEDIA_TYPE      VARCHAR(10) NOT NULL DEFAULT 'image',  -- image | video | audio
  POSITION        SMALLINT NOT NULL DEFAULT 0,
  CREATED_AT      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_media_post (POSTS_ID, POSITION),
  CONSTRAINT fk_post_media_post FOREIGN KEY (POSTS_ID) REFERENCES POSTS(POSTS_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS POST_SHARES (
  SHARE_ID   INT AUTO_INCREMENT PRIMARY KEY,
  POSTS_ID   INT NOT NULL,   -- the ORIGINAL post being shared
  USER_ID    INT NOT NULL,   -- who shared it
  CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_share (POSTS_ID, USER_ID),
  INDEX idx_share_post (POSTS_ID),
  CONSTRAINT fk_share_post FOREIGN KEY (POSTS_ID) REFERENCES POSTS(POSTS_ID) ON DELETE CASCADE,
  CONSTRAINT fk_share_user FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- POSTS.AUDIO_URL (attached music/sound)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'POSTS' AND COLUMN_NAME = 'AUDIO_URL');
SET @s := IF(@c = 0, 'ALTER TABLE POSTS ADD COLUMN AUDIO_URL TEXT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'POSTS' AND COLUMN_NAME = 'AUDIO_PUBLIC_ID');
SET @s := IF(@c = 0, 'ALTER TABLE POSTS ADD COLUMN AUDIO_PUBLIC_ID VARCHAR(255) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- POSTS.AUDIO_TITLE (label shown in the player, e.g. "Original audio" or a track name)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'POSTS' AND COLUMN_NAME = 'AUDIO_TITLE');
SET @s := IF(@c = 0, "ALTER TABLE POSTS ADD COLUMN AUDIO_TITLE VARCHAR(255) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- POSTS.SHARED_FROM (repost → original post id)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'POSTS' AND COLUMN_NAME = 'SHARED_FROM');
SET @s := IF(@c = 0, 'ALTER TABLE POSTS ADD COLUMN SHARED_FROM INT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'POSTS' AND INDEX_NAME = 'idx_posts_shared_from');
SET @s := IF(@c = 0, 'CREATE INDEX idx_posts_shared_from ON POSTS (SHARED_FROM)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
