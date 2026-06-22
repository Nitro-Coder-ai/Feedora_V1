-- =============================================================================
--  Migration: optimize an EXISTING Feedora database (no rebuild, no data loss).
--  Adds the new columns/tables/indexes/views from the optimized Feedora.sql.
--
--  Safe & idempotent: re-running is a no-op (helper procedures check first).
--  ALWAYS back up before running:
--    mysqldump -u root -proot Feedora > Feedora_backup.sql
--
--  Apply:
--    mysql -u root -proot Feedora < scripts/2026_schema_optimize.sql
-- =============================================================================

-- ---- idempotency helpers ----------------------------------------------------
DROP PROCEDURE IF EXISTS feedora_add_column;
DROP PROCEDURE IF EXISTS feedora_add_index;

DELIMITER //

CREATE PROCEDURE feedora_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //

CREATE PROCEDURE feedora_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = tbl AND index_name = idx
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //

DELIMITER ;

-- ---- 1. Cloudinary public_id columns + widened image URL columns ------------
CALL feedora_add_column('USERS', 'PROFILE_IMAGE_PUBLIC_ID', 'PROFILE_IMAGE_PUBLIC_ID VARCHAR(255) NULL');
CALL feedora_add_column('USERS', 'BANNER_IMAGE_PUBLIC_ID',  'BANNER_IMAGE_PUBLIC_ID VARCHAR(255) NULL');
ALTER TABLE USERS MODIFY PROFILE_IMAGE VARCHAR(512) NULL;
ALTER TABLE USERS MODIFY BANNER_IMAGE  VARCHAR(512) NULL DEFAULT 'images/default-banner.jpg';

CALL feedora_add_column('POSTS',   'IMAGE_PUBLIC_ID', 'IMAGE_PUBLIC_ID VARCHAR(255) NULL');
CALL feedora_add_column('POSTS',   'MEDIA_TYPE',      "MEDIA_TYPE VARCHAR(10) NULL DEFAULT 'image'");
CALL feedora_add_column('RECIPES', 'PHOTO_PUBLIC_ID', 'PHOTO_PUBLIC_ID VARCHAR(255) NULL');
CALL feedora_add_column('STORIES', 'IMAGE_PUBLIC_ID', 'IMAGE_PUBLIC_ID VARCHAR(255) NULL');
CALL feedora_add_column('STORIES', 'MEDIA_TYPE',      "MEDIA_TYPE VARCHAR(10) NULL DEFAULT 'image'");
CALL feedora_add_column('MESSAGES','IMAGE_PUBLIC_ID', 'IMAGE_PUBLIC_ID VARCHAR(255) NULL');

-- ---- 2. Tags & search -------------------------------------------------------
CREATE TABLE IF NOT EXISTS TAGS (
   TAG_ID INTEGER NOT NULL AUTO_INCREMENT,
   NAME VARCHAR(50) NOT NULL,
   SLUG VARCHAR(60) NOT NULL,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (TAG_ID),
   UNIQUE KEY uq_tags_name (NAME),
   UNIQUE KEY uq_tags_slug (SLUG)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS RECIPE_TAGS (
   RECIPES_ID INTEGER NOT NULL,
   TAG_ID INTEGER NOT NULL,
   PRIMARY KEY (RECIPES_ID, TAG_ID),
   KEY idx_recipe_tags_tag (TAG_ID),
   FOREIGN KEY (RECIPES_ID) REFERENCES RECIPES(RECIPES_ID) ON DELETE CASCADE,
   FOREIGN KEY (TAG_ID) REFERENCES TAGS(TAG_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO TAGS (NAME, SLUG) VALUES
('Vegan','vegan'),('Vegetarian','vegetarian'),('Gluten-Free','gluten-free'),
('Quick','quick'),('Healthy','healthy'),('Comfort Food','comfort-food'),
('Spicy','spicy'),('Budget','budget');

-- FULLTEXT + analytics indexes (idempotent)
CALL feedora_add_index('RECIPES', 'ft_recipes_search',          'FULLTEXT KEY ft_recipes_search (TITLE, INSTRUCTIONS)');
CALL feedora_add_index('POSTS',   'ft_posts_description',        'FULLTEXT KEY ft_posts_description (DESCRIPTION)');
CALL feedora_add_index('COLLECT', 'idx_collect_recipe',         'KEY idx_collect_recipe (RECIPES_ID)');
CALL feedora_add_index('POST_REACTIONS', 'idx_post_reactions_post_type', 'KEY idx_post_reactions_post_type (POST_ID, REACTION_TYPE)');
CALL feedora_add_index('COMMENT_REACTIONS', 'idx_comment_reactions_comment_type', 'KEY idx_comment_reactions_comment_type (COMMENT_ID, REACTION_TYPE)');
CALL feedora_add_index('COMMENTS', 'idx_comments_post_created',  'KEY idx_comments_post_created (POST_ID, CREATED_AT)');

-- NOTE: RECIPES.TITLE must be VARCHAR/CHAR (not TEXT) for the FULLTEXT key above
-- to combine with INSTRUCTIONS cleanly. If your TITLE is still TEXT, run:
--   ALTER TABLE RECIPES MODIFY TITLE VARCHAR(255) NULL;  -- before the FULLTEXT call

-- ---- 3. Analytics views (read-only, safe to replace) ------------------------
CREATE OR REPLACE VIEW RECIPE_SAVE_COUNTS AS
SELECT r.RECIPES_ID, COALESCE(COUNT(c.USER_ID), 0) AS save_count
FROM RECIPES r LEFT JOIN COLLECT c ON c.RECIPES_ID = r.RECIPES_ID
GROUP BY r.RECIPES_ID;

CREATE OR REPLACE VIEW USER_FOLLOW_COUNTS AS
SELECT u.USER_ID,
   (SELECT COUNT(*) FROM FOLLOWERS f WHERE f.USER_ID = u.USER_ID)     AS followers_count,
   (SELECT COUNT(*) FROM FOLLOWERS f WHERE f.FOLLOWER_ID = u.USER_ID) AS following_count
FROM USERS u;

-- ---- 4. (OPTIONAL) convert text tables to utf8mb4 for emoji support ---------
-- Review on a backup first; charset conversion rewrites the table.
-- ALTER TABLE USERS    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE POSTS    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE RECIPES  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE COMMENTS CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE POST_REACTIONS    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE COMMENT_REACTIONS CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE MESSAGES CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- cleanup ----------------------------------------------------------------
DROP PROCEDURE IF EXISTS feedora_add_column;
DROP PROCEDURE IF EXISTS feedora_add_index;
