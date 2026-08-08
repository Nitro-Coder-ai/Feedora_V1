-- =============================================================================
-- Feedora — Story reactions + replies (Facebook-style story interactions).
-- Re-runnable. Apply with:
--   mysql -uroot -proot -h127.0.0.1 Feedora < scripts/2026_story_reactions_replies.sql
-- =============================================================================

-- One quick emoji reaction per user per story (toggle/replace), like POST_REACTIONS.
CREATE TABLE IF NOT EXISTS STORY_REACTIONS (
   REACTION_ID    INTEGER NOT NULL AUTO_INCREMENT,
   STORY_ID       INTEGER NOT NULL,
   USER_ID        INTEGER NOT NULL,
   REACTION_TYPE  VARCHAR(20) NOT NULL,   -- 'love','yummy','tasty','wow','haha','fire'
   REACTION_EMOJI VARCHAR(10) NOT NULL,
   CREATED_AT     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   UPDATED_AT     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (REACTION_ID),
   UNIQUE KEY uq_story_user_reaction (STORY_ID, USER_ID),  -- one per user per story
   KEY idx_story_reactions_story (STORY_ID),
   KEY idx_story_reactions_user (USER_ID),
   FOREIGN KEY (STORY_ID) REFERENCES STORIES(STORIES_ID) ON DELETE CASCADE,
   FOREIGN KEY (USER_ID)  REFERENCES USERS(USER_ID)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-text replies to a story (private to the author, like Instagram/FB DMs).
CREATE TABLE IF NOT EXISTS STORY_REPLIES (
   REPLY_ID    INTEGER NOT NULL AUTO_INCREMENT,
   STORY_ID    INTEGER NOT NULL,
   USER_ID     INTEGER NOT NULL,
   CONTENT     VARCHAR(1000) NOT NULL,
   CREATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (REPLY_ID),
   KEY idx_story_replies_story (STORY_ID, CREATED_AT),
   KEY idx_story_replies_user (USER_ID),
   FOREIGN KEY (STORY_ID) REFERENCES STORIES(STORIES_ID) ON DELETE CASCADE,
   FOREIGN KEY (USER_ID)  REFERENCES USERS(USER_ID)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
