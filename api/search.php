<?php
/**
 * Public search endpoint (JSON).
 *
 *  GET /api/search.php?q=<query>
 *   - q empty / < 2 chars  -> "discover" mode: latest recipes + popular tags.
 *   - otherwise            -> FULLTEXT search over RECIPES(TITLE,INSTRUCTIONS)
 *                             and POSTS(DESCRIPTION), BOOLEAN mode with prefix
 *                             wildcards, plus a LIKE fallback for short tokens.
 *
 * Read-only; no auth required (surfaces public recipe/post content).
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q      = trim($_GET['q'] ?? '');
$LIMIT  = 8;
$out    = ['query' => $q, 'mode' => 'discover', 'results' => [], 'tags' => []];
$AVATAR = 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_webp,q_auto/v1782090088/feedora/assets/default-profile-png.svg';

// Return the real DB image URL, or '' when there is none (client shows a
// shimmer skeleton instead of an icon). Videos are treated as "no image".
$img = function (?string $url): string {
    if (!$url) return '';
    if (preg_match('/\.(mp4|webm|mov|ogg)(\?|$)/i', $url)) return '';
    return $url;
};
$snippet = function (?string $s, int $n = 70): string {
    $s = trim(preg_replace('/\s+/', ' ', strip_tags((string)$s)));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
};

try {
    if (mb_strlen($q) < 2) {
        // ---- Discover mode -------------------------------------------------
        // Suggested people first (Facebook-style), then the latest recipes.
        $u = $pdo->query(
            "SELECT USER_ID, NAME, PROFILE_IMAGE FROM USERS
             WHERE NAME IS NOT NULL AND NAME <> '' ORDER BY USER_ID DESC LIMIT 4"
        );
        foreach ($u as $row) {
            $out['results'][] = [
                'type'     => 'person',
                'id'       => (int)$row['USER_ID'],
                'title'    => $row['NAME'],
                'subtitle' => 'View profile',
                'image'    => $row['PROFILE_IMAGE'] ?: $AVATAR,
                'url'      => 'profile_details.php?id=' . (int)$row['USER_ID'],
            ];
        }
        $r = $pdo->query(
            "SELECT RECIPES_ID, TITLE, PHOTO_URL FROM RECIPES ORDER BY RECIPES_ID DESC LIMIT 6"
        );
        foreach ($r as $row) {
            $out['results'][] = [
                'type'     => 'recipe',
                'id'       => (int)$row['RECIPES_ID'],
                'title'    => $row['TITLE'] ?: 'Untitled recipe',
                'subtitle' => 'Recipe',
                'image'    => $img($row['PHOTO_URL']),
                'url'      => 'view-recipe.php?id=' . (int)$row['RECIPES_ID'],
            ];
        }
        echo json_encode($out);
        exit;
    }

    // ---- Search mode -------------------------------------------------------
    $out['mode'] = 'search';
    $terms   = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    $boolean = implode(' ', array_map(fn($t) => '+' . $t . '*', $terms)); // require each, prefix match
    $like    = '%' . $q . '%';
    $likePre = $q . '%';

    // People first (Facebook-style). USERS has no FULLTEXT index, so match the
    // display name with LIKE and rank prefix matches ("jan" -> "Jane") highest.
    // Distinct placeholders because config runs with emulated prepares OFF.
    $us = $pdo->prepare(
        "SELECT USER_ID, NAME, PROFILE_IMAGE
         FROM USERS
         WHERE NAME LIKE :l1
         ORDER BY (NAME LIKE :l2) DESC, NAME ASC
         LIMIT :lim"
    );
    $us->bindValue(':l1', $like);
    $us->bindValue(':l2', $likePre);
    $us->bindValue(':lim', $LIMIT, PDO::PARAM_INT);
    $us->execute();
    foreach ($us as $row) {
        $out['results'][] = [
            'type'     => 'person',
            'id'       => (int)$row['USER_ID'],
            'title'    => $row['NAME'] ?: 'Feedora user',
            'subtitle' => 'View profile',
            'image'    => $row['PROFILE_IMAGE'] ?: $AVATAR,
            'url'      => 'profile_details.php?id=' . (int)$row['USER_ID'],
        ];
    }

    // Recipes (distinct placeholders — config runs with emulated prepares OFF)
    $rs = $pdo->prepare(
        "SELECT RECIPES_ID, TITLE, PHOTO_URL,
                MATCH(TITLE, INSTRUCTIONS) AGAINST(:b1 IN BOOLEAN MODE) AS score
         FROM RECIPES
         WHERE MATCH(TITLE, INSTRUCTIONS) AGAINST(:b2 IN BOOLEAN MODE)
            OR TITLE LIKE :l
         ORDER BY score DESC, RECIPES_ID DESC
         LIMIT :lim"
    );
    $rs->bindValue(':b1', $boolean);
    $rs->bindValue(':b2', $boolean);
    $rs->bindValue(':l', $like);
    $rs->bindValue(':lim', $LIMIT, PDO::PARAM_INT);
    $rs->execute();
    foreach ($rs as $row) {
        $out['results'][] = [
            'type'     => 'recipe',
            'id'       => (int)$row['RECIPES_ID'],
            'title'    => $row['TITLE'] ?: 'Untitled recipe',
            'subtitle' => 'Recipe',
            'image'    => $img($row['PHOTO_URL']),
            'url'      => 'view-recipe.php?id=' . (int)$row['RECIPES_ID'],
        ];
    }

    // Posts
    $ps = $pdo->prepare(
        "SELECT p.POSTS_ID, p.DESCRIPTION, p.IMAGE_URL, u.NAME,
                MATCH(p.DESCRIPTION) AGAINST(:b1 IN BOOLEAN MODE) AS score
         FROM POSTS p
         JOIN USERS u ON u.USER_ID = p.USER_ID
         WHERE MATCH(p.DESCRIPTION) AGAINST(:b2 IN BOOLEAN MODE)
            OR p.DESCRIPTION LIKE :l
         ORDER BY score DESC, p.POSTS_ID DESC
         LIMIT :lim"
    );
    $ps->bindValue(':b1', $boolean);
    $ps->bindValue(':b2', $boolean);
    $ps->bindValue(':l', $like);
    $ps->bindValue(':lim', $LIMIT, PDO::PARAM_INT);
    $ps->execute();
    foreach ($ps as $row) {
        $title = $snippet($row['DESCRIPTION']);
        if ($title === '') continue;
        $out['results'][] = [
            'type'     => 'post',
            'id'       => (int)$row['POSTS_ID'],
            'title'    => $title,
            'subtitle' => 'Post by ' . $row['NAME'],
            'image'    => $img($row['IMAGE_URL']),
            'url'      => 'dashboard.php#post-' . (int)$row['POSTS_ID'],
        ];
    }

    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[search] ' . $e->getMessage());
    echo json_encode(['error' => 'search_failed', 'results' => [], 'tags' => []]);
}
