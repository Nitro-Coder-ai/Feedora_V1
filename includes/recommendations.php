<?php
/**
 * Follow recommendations — Facebook-style "people you may follow".
 *
 * Ranks every other user the viewer doesn't already follow by:
 *   shared food categories + shared cuisines (interest overlap, weighted), then
 *   follower count (popularity), then account recency.
 * When the viewer (or candidates) have no interests yet, it degrades gracefully
 * to a popularity/recency ordering — i.e. "show all profiles to follow".
 */

if (!function_exists('getFollowRecommendations')) {
    /**
     * @return array<int,array{USER_ID:int,NAME:string,PROFILE_IMAGE:?string,
     *   shared_cats:int,shared_cuisines:int,followers_count:int}>
     */
    function getFollowRecommendations(PDO $pdo, int $viewerId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit)); // inlined below; clamp to a sane range
        try {
            $sql = "
                SELECT
                    u.USER_ID, u.NAME, u.PROFILE_IMAGE,
                    (SELECT COUNT(*) FROM USER_CATEGORIES uc
                       WHERE uc.USER_ID = u.USER_ID
                         AND uc.ID_CATEGORIE IN (SELECT ID_CATEGORIE FROM USER_CATEGORIES WHERE USER_ID = ?)
                    ) AS shared_cats,
                    (SELECT COUNT(*) FROM USER_CUISINES ucu
                       WHERE ucu.USER_ID = u.USER_ID
                         AND ucu.CUISINE_ID IN (SELECT CUISINE_ID FROM USER_CUISINES WHERE USER_ID = ?)
                    ) AS shared_cuisines,
                    (SELECT COUNT(*) FROM FOLLOWERS f WHERE f.USER_ID = u.USER_ID) AS followers_count
                FROM USERS u
                WHERE u.USER_ID <> ?
                  AND NOT EXISTS (
                      SELECT 1 FROM FOLLOWERS f2
                      WHERE f2.USER_ID = u.USER_ID AND f2.FOLLOWER_ID = ?
                  )
                ORDER BY (shared_cats * 3 + shared_cuisines * 3) DESC,
                         followers_count DESC,
                         u.CREATED_AT DESC
                LIMIT " . (int) $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$viewerId, $viewerId, $viewerId, $viewerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('getFollowRecommendations error: ' . $e->getMessage());
            return [];
        }
    }
}
