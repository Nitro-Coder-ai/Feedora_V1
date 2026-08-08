-- =============================================================================
--  Feedora schema (optimized)
--  Target: MySQL 5.7+ (MAMP).  Engine: InnoDB.  Charset: utf8mb4 (emoji-safe).
--
--  Design rules:
--   * Backward compatible — every pre-existing table/column name is preserved,
--     so existing PHP queries continue to work unchanged.
--   * Additive only — new columns are nullable; new tables/indexes are extra.
--   * Right-sized types: VARCHAR instead of TEXT for short identifiers (lets
--     them be indexed); SMALLINT UNSIGNED for small numerics.
--   * Forward-looking: Cloudinary public_id columns, tags + FULLTEXT search,
--     and analytics indexes/views for saves & follows.
--
--  NOTE: This file builds a FRESH database. To upgrade an EXISTING database
--  without data loss, use scripts/2026_schema_optimize.sql instead.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS Feedora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE Feedora;

-- -----------------------------------------------------------------------------
-- Table: USERS
-- -----------------------------------------------------------------------------
CREATE TABLE USERS (
   USER_ID INTEGER NOT NULL AUTO_INCREMENT,
   NAME VARCHAR(255) NULL,
   EMAIL VARCHAR(255) NULL,
   PASSWORD_HASH VARCHAR(255) NULL,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   LAST_LOGIN_AT TIMESTAMP NULL,
   LAST_LOGOUT_AT TIMESTAMP NULL,
   DATETIME TIMESTAMP NULL,            -- legacy (kept for compatibility)
   DATETIME_STORIES TIMESTAMP NULL,    -- legacy (kept for compatibility)
   DATE_CREATION TIMESTAMP NULL,       -- legacy (kept for compatibility)
   TYPE SMALLINT NULL DEFAULT 0,
   PROFILE_IMAGE VARCHAR(512) NULL,                                  -- widened for Cloudinary URLs
   BANNER_IMAGE VARCHAR(512) NULL DEFAULT 'images/default-banner.jpg',
   PROFILE_IMAGE_PUBLIC_ID VARCHAR(255) NULL,                        -- Cloudinary public_id (for destroy())
   BANNER_IMAGE_PUBLIC_ID VARCHAR(255) NULL,
   PRIMARY KEY (USER_ID),
   UNIQUE KEY uq_users_email (EMAIL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: CATEGORIE
-- -----------------------------------------------------------------------------
CREATE TABLE CATEGORIE (
   ID_CATEGORIE INTEGER NOT NULL AUTO_INCREMENT,
   NAME_CATEGORIE VARCHAR(100) NULL,
   PRIMARY KEY (ID_CATEGORIE),
   UNIQUE KEY uq_categorie_name (NAME_CATEGORIE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO CATEGORIE (NAME_CATEGORIE) VALUES
('Appetizers'), ('Main Course'), ('Desserts'), ('Beverages'),
('Salads'), ('Soups'), ('Breakfast'), ('Snacks');

-- -----------------------------------------------------------------------------
-- Table: RECIPES
-- -----------------------------------------------------------------------------
CREATE TABLE RECIPES (
   RECIPES_ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,
   ID_CATEGORIE INTEGER NULL,
   TITLE VARCHAR(255) NULL,
   PHOTO_URL TEXT NULL,
   PHOTO_PUBLIC_ID VARCHAR(255) NULL,                 -- Cloudinary public_id
   INSTRUCTIONS TEXT NULL,
   PREP_TIME_MINUTES SMALLINT UNSIGNED NULL,
   COOK_TIME_MINUTES SMALLINT UNSIGNED NULL,
   SERVINGS SMALLINT UNSIGNED NULL,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (RECIPES_ID),
   KEY idx_recipes_user (USER_ID),
   KEY idx_recipes_categorie (ID_CATEGORIE),
   FULLTEXT KEY ft_recipes_search (TITLE, INSTRUCTIONS),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
   FOREIGN KEY (ID_CATEGORIE) REFERENCES CATEGORIE(ID_CATEGORIE) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: INGREDIENTS
-- -----------------------------------------------------------------------------
CREATE TABLE INGREDIENTS (
   INGREDIENTS_ID INTEGER NOT NULL AUTO_INCREMENT,
   NAME VARCHAR(255) NULL,
   PRIMARY KEY (INGREDIENTS_ID),
   KEY idx_ingredients_name (NAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: UNIT
-- -----------------------------------------------------------------------------
CREATE TABLE UNIT (
   UNIT_ID INTEGER NOT NULL AUTO_INCREMENT,
   UNIT_NAME VARCHAR(50) NULL,
   PRIMARY KEY (UNIT_ID),
   UNIQUE KEY uq_unit_name (UNIT_NAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO UNIT (UNIT_NAME) VALUES
('cups'), ('tablespoons'), ('teaspoons'), ('ounces'), ('pounds'),
('grams'), ('liters'), ('milliliters'), ('pieces'), ('pinch');

-- -----------------------------------------------------------------------------
-- Table: COLLECT (saved/favorite recipes)
-- -----------------------------------------------------------------------------
CREATE TABLE COLLECT (
   USER_ID INTEGER NOT NULL,
   RECIPES_ID INTEGER NOT NULL,
   DATE_COLLECT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (USER_ID, RECIPES_ID),
   KEY idx_collect_recipe (RECIPES_ID),               -- "how many saved this recipe" / save analytics
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
   FOREIGN KEY (RECIPES_ID) REFERENCES RECIPES(RECIPES_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: DIFFICULTY
-- -----------------------------------------------------------------------------
CREATE TABLE DIFFICULTY (
   DIFFICULTY_ID INTEGER NOT NULL AUTO_INCREMENT,
   DIFFICULTY_NAME VARCHAR(50) NULL,
   PRIMARY KEY (DIFFICULTY_ID),
   UNIQUE KEY uq_difficulty_name (DIFFICULTY_NAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO DIFFICULTY (DIFFICULTY_NAME) VALUES
('Easy'), ('Medium'), ('Hard'), ('Expert');

-- -----------------------------------------------------------------------------
-- Table: DIFFICULTY_RECIPES
-- -----------------------------------------------------------------------------
CREATE TABLE DIFFICULTY_RECIPES (
   DIFFICULTY_ID INTEGER NOT NULL,
   RECIPES_ID INTEGER NOT NULL,
   PRIMARY KEY (DIFFICULTY_ID, RECIPES_ID),
   KEY idx_difficulty_recipes_recipe (RECIPES_ID),
   FOREIGN KEY (DIFFICULTY_ID) REFERENCES DIFFICULTY(DIFFICULTY_ID) ON DELETE CASCADE,
   FOREIGN KEY (RECIPES_ID) REFERENCES RECIPES(RECIPES_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: RECIPE_INGREDIENTS
-- -----------------------------------------------------------------------------
CREATE TABLE RECIPE_INGREDIENTS (
   RECIPES_ID INTEGER NOT NULL,
   INGREDIENTS_ID INTEGER NOT NULL,
   PRIMARY KEY (RECIPES_ID, INGREDIENTS_ID),
   KEY idx_recipe_ingredients_ingredient (INGREDIENTS_ID),
   FOREIGN KEY (RECIPES_ID) REFERENCES RECIPES(RECIPES_ID) ON DELETE CASCADE,
   FOREIGN KEY (INGREDIENTS_ID) REFERENCES INGREDIENTS(INGREDIENTS_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: INGREDIENTS_UNIT
-- -----------------------------------------------------------------------------
CREATE TABLE INGREDIENTS_UNIT (
   INGREDIENTS_ID INTEGER NOT NULL,
   UNIT_ID INTEGER NOT NULL,
   QUANTITY DECIMAL(10,2) NULL,
   PRIMARY KEY (INGREDIENTS_ID, UNIT_ID),
   KEY idx_ingredients_unit_unit (UNIT_ID),
   FOREIGN KEY (INGREDIENTS_ID) REFERENCES INGREDIENTS(INGREDIENTS_ID) ON DELETE CASCADE,
   FOREIGN KEY (UNIT_ID) REFERENCES UNIT(UNIT_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: POSTS
-- -----------------------------------------------------------------------------
CREATE TABLE POSTS (
   POSTS_ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,
   IMAGE_URL TEXT NULL,
   IMAGE_PUBLIC_ID VARCHAR(255) NULL,                 -- Cloudinary public_id
   MEDIA_TYPE VARCHAR(10) NULL DEFAULT 'image',       -- 'image' | 'video'
   DESCRIPTION TEXT NULL,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (POSTS_ID),
   KEY idx_posts_user (USER_ID),
   KEY idx_posts_created_at (CREATED_AT),
   FULLTEXT KEY ft_posts_description (DESCRIPTION),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: POST_REACTIONS
-- -----------------------------------------------------------------------------
CREATE TABLE POST_REACTIONS (
   REACTION_ID INTEGER NOT NULL AUTO_INCREMENT,
   POST_ID INTEGER NOT NULL,
   USER_ID INTEGER NOT NULL,
   REACTION_TYPE VARCHAR(50) NOT NULL,   -- 'yummy','delicious','tasty','love','amazing'
   REACTION_EMOJI VARCHAR(10) NOT NULL,
   CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (REACTION_ID),
   UNIQUE KEY unique_user_post_reaction (POST_ID, USER_ID),  -- one reaction per user per post
   KEY idx_post_reactions_user_id (USER_ID),
   KEY idx_post_reactions_post_type (POST_ID, REACTION_TYPE),  -- per-type counts
   FOREIGN KEY (POST_ID) REFERENCES POSTS(POSTS_ID) ON DELETE CASCADE,
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW POST_REACTION_COUNTS AS
SELECT
    p.POSTS_ID,
    COALESCE(COUNT(pr.REACTION_ID), 0) AS total_reactions,
    COALESCE(SUM(pr.REACTION_TYPE = 'yummy'), 0) AS yummy_count,
    COALESCE(SUM(pr.REACTION_TYPE = 'delicious'), 0) AS delicious_count,
    COALESCE(SUM(pr.REACTION_TYPE = 'tasty'), 0) AS tasty_count,
    COALESCE(SUM(pr.REACTION_TYPE = 'love'), 0) AS love_count,
    COALESCE(SUM(pr.REACTION_TYPE = 'amazing'), 0) AS amazing_count
FROM POSTS p
LEFT JOIN POST_REACTIONS pr ON p.POSTS_ID = pr.POST_ID
GROUP BY p.POSTS_ID;

-- -----------------------------------------------------------------------------
-- Table: NOTIFICATIONS
-- -----------------------------------------------------------------------------
CREATE TABLE NOTIFICATIONS (
   NOTIFICATION_ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,
   TARGET_USER_ID INTEGER NULL,
   NOTIFICATION_TYPE VARCHAR(50) NOT NULL,
   CONTENT TEXT NULL,
   RELATED_ID INTEGER NULL,
   IS_READ BOOLEAN DEFAULT FALSE,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (NOTIFICATION_ID),
   KEY idx_notifications_target_read_created (TARGET_USER_ID, IS_READ, CREATED_AT),
   KEY idx_notifications_user (USER_ID),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
   FOREIGN KEY (TARGET_USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: STORIES
-- -----------------------------------------------------------------------------
CREATE TABLE STORIES (
   STORIES_ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,
   IMAGE_URL TEXT NULL,
   IMAGE_PUBLIC_ID VARCHAR(255) NULL,                 -- Cloudinary public_id
   MEDIA_TYPE VARCHAR(10) NULL DEFAULT 'image',       -- 'image' | 'video'
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   EXPIRES_AT TIMESTAMP NULL DEFAULT NULL,
   VISIBILITY VARCHAR(50) NULL DEFAULT 'public',
   PRIMARY KEY (STORIES_ID),
   KEY idx_stories_user_id (USER_ID),
   KEY idx_stories_expires_user (EXPIRES_AT, USER_ID),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: STORY_VIEWS
-- -----------------------------------------------------------------------------
CREATE TABLE STORY_VIEWS (
   VIEW_ID INTEGER NOT NULL AUTO_INCREMENT,
   STORY_ID INTEGER NOT NULL,
   VIEWER_ID INTEGER NOT NULL,
   VIEWED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (VIEW_ID),
   UNIQUE KEY uq_story_viewer (STORY_ID, VIEWER_ID),
   KEY idx_story_views_viewer (VIEWER_ID),
   FOREIGN KEY (STORY_ID) REFERENCES STORIES(STORIES_ID) ON DELETE CASCADE,
   FOREIGN KEY (VIEWER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: MESSAGES
-- -----------------------------------------------------------------------------
-- RECEIVER_ID is nullable (group messages have no single receiver); GROUP_ID FK is
-- added after CHAT_GROUPS exists (see ALTER below that table). IS_READ powers DM read
-- receipts; EDITED_AT/IS_DELETED power edit & soft-delete (scripts/2026_messages_enhancements.sql).
CREATE TABLE MESSAGES (
   MESSAGE_ID INT AUTO_INCREMENT PRIMARY KEY,
   SENDER_ID INT NOT NULL,
   RECEIVER_ID INT NULL,
   GROUP_ID INT NULL,                                 -- set for group messages (FK added below CHAT_GROUPS)
   CONTENT TEXT,
   IMAGE_URL TEXT,
   IMAGE_PUBLIC_ID VARCHAR(255) NULL,                 -- Cloudinary public_id
   REACTIONS JSON,
   IS_READ TINYINT(1) NOT NULL DEFAULT 0,             -- DM read receipt flag
   SENT_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   EDITED_AT TIMESTAMP NULL DEFAULT NULL,             -- non-null => "edited" marker
   IS_DELETED TINYINT(1) NOT NULL DEFAULT 0,          -- soft-delete / tombstone
   KEY idx_messages_sender (SENDER_ID),
   KEY idx_messages_receiver (RECEIVER_ID),
   KEY idx_messages_sent_at (SENT_AT),
   KEY idx_messages_conversation (SENDER_ID, RECEIVER_ID),
   KEY idx_messages_seen (SENDER_ID, RECEIVER_ID, IS_READ, MESSAGE_ID),
   KEY idx_messages_group (GROUP_ID, MESSAGE_ID),
   FOREIGN KEY (SENDER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
   FOREIGN KEY (RECEIVER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: USER_SESSIONS (online/offline tracking)
-- -----------------------------------------------------------------------------
CREATE TABLE USER_SESSIONS (
   SESSION_ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,
   LAST_ACTIVITY TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (SESSION_ID),
   KEY idx_user_sessions_last_activity (LAST_ACTIVITY),
   KEY idx_user_sessions_user_id (USER_ID),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: FOLLOWERS
-- -----------------------------------------------------------------------------
CREATE TABLE FOLLOWERS (
   ID INTEGER NOT NULL AUTO_INCREMENT,
   USER_ID INTEGER NOT NULL,        -- the followed user
   FOLLOWER_ID INTEGER NOT NULL,    -- the follower
   FOLLOWED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (ID),
   UNIQUE KEY uq_follow (USER_ID, FOLLOWER_ID),
   KEY idx_followers_user_id (USER_ID),
   KEY idx_followers_follower_id (FOLLOWER_ID),
   FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
   FOREIGN KEY (FOLLOWER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: COMMENTS
-- -----------------------------------------------------------------------------
CREATE TABLE COMMENTS (
    COMMENT_ID INT AUTO_INCREMENT PRIMARY KEY,
    POST_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    COMMENT_TEXT TEXT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_comments_post_id (POST_ID),
    KEY idx_comments_user_id (USER_ID),
    KEY idx_comments_post_created (POST_ID, CREATED_AT),
    FOREIGN KEY (POST_ID) REFERENCES POSTS(POSTS_ID) ON DELETE CASCADE,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: COMMENT_REACTIONS
-- -----------------------------------------------------------------------------
CREATE TABLE COMMENT_REACTIONS (
    REACTION_ID INT NOT NULL AUTO_INCREMENT,
    COMMENT_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    REACTION_TYPE VARCHAR(50) NOT NULL,   -- 'yummy','delicious','tasty','love','amazing','like'
    REACTION_EMOJI VARCHAR(10) NOT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (REACTION_ID),
    UNIQUE KEY unique_user_comment_reaction (COMMENT_ID, USER_ID),
    KEY idx_comment_reactions_user_id (USER_ID),
    KEY idx_comment_reactions_comment_type (COMMENT_ID, REACTION_TYPE),
    FOREIGN KEY (COMMENT_ID) REFERENCES COMMENTS(COMMENT_ID) ON DELETE CASCADE,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW COMMENT_REACTION_COUNTS AS
SELECT
    c.COMMENT_ID,
    COALESCE(COUNT(cr.REACTION_ID), 0) AS total_reactions,
    COALESCE(SUM(cr.REACTION_TYPE = 'yummy'), 0) AS yummy_count,
    COALESCE(SUM(cr.REACTION_TYPE = 'delicious'), 0) AS delicious_count,
    COALESCE(SUM(cr.REACTION_TYPE = 'tasty'), 0) AS tasty_count,
    COALESCE(SUM(cr.REACTION_TYPE = 'love'), 0) AS love_count,
    COALESCE(SUM(cr.REACTION_TYPE = 'amazing'), 0) AS amazing_count,
    COALESCE(SUM(cr.REACTION_TYPE = 'like'), 0) AS like_count
FROM COMMENTS c
LEFT JOIN COMMENT_REACTIONS cr ON c.COMMENT_ID = cr.COMMENT_ID
GROUP BY c.COMMENT_ID;

-- =============================================================================
--  NEW: Tags & search
-- =============================================================================
CREATE TABLE TAGS (
   TAG_ID INTEGER NOT NULL AUTO_INCREMENT,
   NAME VARCHAR(50) NOT NULL,
   SLUG VARCHAR(60) NOT NULL,
   CREATED_AT TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (TAG_ID),
   UNIQUE KEY uq_tags_name (NAME),
   UNIQUE KEY uq_tags_slug (SLUG)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE RECIPE_TAGS (
   RECIPES_ID INTEGER NOT NULL,
   TAG_ID INTEGER NOT NULL,
   PRIMARY KEY (RECIPES_ID, TAG_ID),
   KEY idx_recipe_tags_tag (TAG_ID),
   FOREIGN KEY (RECIPES_ID) REFERENCES RECIPES(RECIPES_ID) ON DELETE CASCADE,
   FOREIGN KEY (TAG_ID) REFERENCES TAGS(TAG_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO TAGS (NAME, SLUG) VALUES
('Vegan', 'vegan'), ('Vegetarian', 'vegetarian'), ('Gluten-Free', 'gluten-free'),
('Quick', 'quick'), ('Healthy', 'healthy'), ('Comfort Food', 'comfort-food'),
('Spicy', 'spicy'), ('Budget', 'budget');

-- =============================================================================
--  NEW: Analytics views (saves & follows) — read-only, safe to query anywhere
-- =============================================================================
CREATE OR REPLACE VIEW RECIPE_SAVE_COUNTS AS
SELECT r.RECIPES_ID, COALESCE(COUNT(c.USER_ID), 0) AS save_count
FROM RECIPES r
LEFT JOIN COLLECT c ON c.RECIPES_ID = r.RECIPES_ID
GROUP BY r.RECIPES_ID;

CREATE OR REPLACE VIEW USER_FOLLOW_COUNTS AS
SELECT
    u.USER_ID,
    (SELECT COUNT(*) FROM FOLLOWERS f WHERE f.USER_ID = u.USER_ID) AS followers_count,
    (SELECT COUNT(*) FROM FOLLOWERS f WHERE f.FOLLOWER_ID = u.USER_ID) AS following_count
FROM USERS u;

-- =============================================================================
--  Seed data (sample users + recipes) — used by dev and the E2E test DB
-- =============================================================================
INSERT INTO USERS (NAME, EMAIL, PASSWORD_HASH) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO RECIPES (USER_ID, ID_CATEGORIE, TITLE, INSTRUCTIONS, PREP_TIME_MINUTES, COOK_TIME_MINUTES, SERVINGS) VALUES
(1, 2, 'Classic Spaghetti Carbonara', 'Step 1: Cook spaghetti according to package directions.\nStep 2: In a bowl, whisk together eggs, cheese, and pepper.\nStep 3: Cook bacon until crispy.\nStep 4: Combine hot pasta with egg mixture and bacon.\nStep 5: Serve immediately with extra cheese.', 15, 20, 4),
(2, 3, 'Chocolate Chip Cookies', 'Step 1: Preheat oven to 375°F.\nStep 2: Mix butter, sugars, and vanilla.\nStep 3: Add eggs one at a time.\nStep 4: Mix in flour, baking soda, and salt.\nStep 5: Fold in chocolate chips.\nStep 6: Bake for 9-11 minutes.', 20, 11, 24);

-- -----------------------------------------------------------------------------
-- 2026: Group chats + WebRTC call signaling (see scripts/2026_chat_groups_calls.sql)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS CHAT_GROUPS (
    GROUP_ID INT AUTO_INCREMENT PRIMARY KEY,
    NAME VARCHAR(255) NOT NULL,
    AVATAR_URL VARCHAR(512) NULL,
    AVATAR_PUBLIC_ID VARCHAR(255) NULL,
    CREATED_BY INT NOT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CREATED_BY) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CHAT_GROUP_MEMBERS (
    GROUP_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    ROLE VARCHAR(50) DEFAULT 'member',
    JOINED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (GROUP_ID, USER_ID),
    FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MESSAGES.GROUP_ID is declared in the CREATE above; its FK is added here because it
-- references CHAT_GROUPS, which is defined after the MESSAGES table.
ALTER TABLE MESSAGES ADD CONSTRAINT fk_messages_group
    FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS CALL_SESSIONS (
    CALL_ID INT AUTO_INCREMENT PRIMARY KEY,
    CALLER_ID INT NOT NULL,
    RECEIVER_ID INT NULL,
    GROUP_ID INT NULL,
    CALL_TYPE VARCHAR(20) NOT NULL,
    STATUS VARCHAR(50) DEFAULT 'ringing',
    OFFER_SDP TEXT NULL,
    ANSWER_SDP TEXT NULL,
    STARTED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ENDED_AT TIMESTAMP NULL,
    FOREIGN KEY (CALLER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
    FOREIGN KEY (RECEIVER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE,
    FOREIGN KEY (GROUP_ID) REFERENCES CHAT_GROUPS(GROUP_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CALL_ICE_CANDIDATES (
    CANDIDATE_ID INT AUTO_INCREMENT PRIMARY KEY,
    CALL_ID INT NOT NULL,
    SENDER_ID INT NOT NULL,
    CANDIDATE TEXT NOT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CALL_ID) REFERENCES CALL_SESSIONS(CALL_ID) ON DELETE CASCADE,
    FOREIGN KEY (SENDER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2026: Discord-style multi-party (mesh) calls (see scripts/2026_multiparty_calls.sql).
-- CALL_SESSIONS is the room (group call = one row with GROUP_ID); CALL_PARTICIPANTS
-- is the live roster; CALL_PEER_SIGNALS carries directed offer/answer/ICE between
-- every ordered peer-pair. Legacy CALL_ICE_CANDIDATES stays for the 1:1 path.
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
-- 2026: Messaging enhancements — pins + typing (see scripts/2026_messages_enhancements.sql)
-- MESSAGES.EDITED_AT / IS_DELETED / IS_READ + seen/group indexes are declared in the
-- MESSAGES table above. CONV_KEY scope: DM 'dm:<loId>:<hiId>', group 'grp:<groupId>'.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS MESSAGE_PINS (
    PIN_ID      INT AUTO_INCREMENT PRIMARY KEY,
    MESSAGE_ID  INT NOT NULL,
    CONV_KEY    VARCHAR(64) NOT NULL,            -- canonical scope key
    SCOPE_TYPE  VARCHAR(8)  NOT NULL,            -- 'dm' | 'group'
    GROUP_ID    INT NULL,                        -- set for group scope, NULL for DM
    PINNED_BY   INT NOT NULL,
    PINNED_AT   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pin_message (MESSAGE_ID),       -- pinned at most once per scope
    KEY idx_pins_conv (CONV_KEY, PINNED_AT),
    FOREIGN KEY (MESSAGE_ID) REFERENCES MESSAGES(MESSAGE_ID)   ON DELETE CASCADE,
    FOREIGN KEY (PINNED_BY)  REFERENCES USERS(USER_ID)         ON DELETE CASCADE,
    FOREIGN KEY (GROUP_ID)   REFERENCES CHAT_GROUPS(GROUP_ID)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS MESSAGE_TYPING (
    USER_ID     INT NOT NULL,
    CONV_KEY    VARCHAR(64) NOT NULL,            -- same canonical scope key as pins
    UPDATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (USER_ID, CONV_KEY),
    KEY idx_typing_conv (CONV_KEY, UPDATED_AT),
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
