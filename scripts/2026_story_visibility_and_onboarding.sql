-- =============================================================================
-- Feedora — Story visibility algorithm + onboarding survey & follow recommendations
-- Safe to re-run: uses IF NOT EXISTS / INSERT IGNORE. Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_story_visibility_and_onboarding.sql
-- =============================================================================

-- 1) Cuisine / diet catalog (interests beyond the recipe CATEGORIE list) --------
CREATE TABLE IF NOT EXISTS CUISINES (
   CUISINE_ID  INTEGER NOT NULL AUTO_INCREMENT,
   NAME        VARCHAR(80) NOT NULL,
   PRIMARY KEY (CUISINE_ID),
   UNIQUE KEY uq_cuisine_name (NAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO CUISINES (NAME) VALUES
('Italian'), ('Mexican'), ('Indian'), ('Chinese'), ('Japanese'),
('Mediterranean'), ('French'), ('Thai'), ('American'), ('Middle Eastern'),
('Korean'), ('Spanish'), ('Vegan'), ('Vegetarian'), ('Halal'),
('Keto'), ('Gluten-Free'), ('Baking & Pastry'), ('Street Food'), ('BBQ & Grill'),
('Seafood'), ('Healthy & Light');

-- 2) User ↔ interest links (food categories + cuisines) ------------------------
CREATE TABLE IF NOT EXISTS USER_CATEGORIES (
   USER_ID       INTEGER NOT NULL,
   ID_CATEGORIE  INTEGER NOT NULL,
   PRIMARY KEY (USER_ID, ID_CATEGORIE),
   KEY idx_uc_categorie (ID_CATEGORIE),
   FOREIGN KEY (USER_ID)      REFERENCES USERS(USER_ID)      ON DELETE CASCADE,
   FOREIGN KEY (ID_CATEGORIE) REFERENCES CATEGORIE(ID_CATEGORIE) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS USER_CUISINES (
   USER_ID     INTEGER NOT NULL,
   CUISINE_ID  INTEGER NOT NULL,
   PRIMARY KEY (USER_ID, CUISINE_ID),
   KEY idx_ucu_cuisine (CUISINE_ID),
   FOREIGN KEY (USER_ID)    REFERENCES USERS(USER_ID)    ON DELETE CASCADE,
   FOREIGN KEY (CUISINE_ID) REFERENCES CUISINES(CUISINE_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Onboarding flag on USERS ------------------------------------------------------
--    Guarded: MySQL lacks ADD COLUMN IF NOT EXISTS before 8.0.29. Accounts that
--    existed before onboarding was introduced are marked as onboarded.
SET @has_onboarded := (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USERS' AND COLUMN_NAME = 'ONBOARDED');
SET @sql := IF(@has_onboarded = 0,
   'ALTER TABLE USERS ADD COLUMN ONBOARDED TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_onboarded = 0, 'UPDATE USERS SET ONBOARDED = 1', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Index to support visibility-filtered story queries -------------------------
--    Queries filter EXPIRES_AT > NOW() then branch on VISIBILITY; this covering
--    index lets the optimizer keep using the range scan while reading visibility.
--    Guarded so the script can be re-run.
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STORIES' AND INDEX_NAME = 'idx_stories_expires_vis_user');
SET @sql := IF(@has_idx = 0,
   'CREATE INDEX idx_stories_expires_vis_user ON STORIES (EXPIRES_AT, VISIBILITY, USER_ID)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
