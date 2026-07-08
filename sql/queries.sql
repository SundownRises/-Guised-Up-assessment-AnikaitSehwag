-- =============================================================================
-- Guised Up — SQL Queries (Part D)
-- PostgreSQL syntax
-- =============================================================================

-- -----------------------------------------------------------------------------
-- D1: Top 10 most active users in the last 7 days
-- Counts all interaction types equally (view, reply, reaction each = 1)
-- -----------------------------------------------------------------------------
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(i.id) AS interaction_count
FROM users u
JOIN interactions i ON i.user_id = u.id
WHERE i.created_at >= NOW() - INTERVAL '7 days'
GROUP BY u.id, u.name, u.email
ORDER BY interaction_count DESC
LIMIT 10;

-- -----------------------------------------------------------------------------
-- D2: Posts from most-interacted-with users for a given user
-- Finds which users the given user interacts with most, then returns their
-- posts from the last 30 days
-- -----------------------------------------------------------------------------
SELECT
    p.id,
    p.text,
    p.image_url,
    p.authenticity_score,
    p.created_at,
    target_users.name AS author_name,
    target_users.interaction_count
FROM posts p
JOIN (
    SELECT
        posts.user_id AS target_user_id,
        u.name,
        COUNT(i.id) AS interaction_count
    FROM interactions i
    JOIN posts ON posts.id = i.post_id
    JOIN users u ON u.id = posts.user_id
    WHERE i.user_id = :user_id
    GROUP BY posts.user_id, u.name
    ORDER BY interaction_count DESC
) target_users ON target_users.target_user_id = p.user_id
WHERE p.created_at >= NOW() - INTERVAL '30 days'
ORDER BY target_users.interaction_count DESC, p.created_at DESC;

-- -----------------------------------------------------------------------------
-- D3: Posts with 100+ views but 0 reactions
-- Uses conditional aggregation on the interaction type column
-- -----------------------------------------------------------------------------
SELECT
    p.id,
    p.text,
    p.user_id,
    u.name AS author_name,
    p.created_at,
    COUNT(CASE WHEN i.type = 'view' THEN 1 END) AS view_count,
    COUNT(CASE WHEN i.type = 'reaction' THEN 1 END) AS reaction_count
FROM posts p
JOIN users u ON u.id = p.user_id
LEFT JOIN interactions i ON i.post_id = p.id
GROUP BY p.id, p.text, p.user_id, u.name, p.created_at
HAVING COUNT(CASE WHEN i.type = 'view' THEN 1 END) >= 100
   AND COUNT(CASE WHEN i.type = 'reaction' THEN 1 END) = 0
ORDER BY view_count DESC;

-- -----------------------------------------------------------------------------
-- D4: Spam detection — users who posted 20+ times in any 24-hour window
-- Groups posts by user within the last 24 hours
-- -----------------------------------------------------------------------------
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(p.id) AS post_count_24h
FROM users u
JOIN posts p ON p.user_id = u.id
WHERE p.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY u.id, u.name, u.email
HAVING COUNT(p.id) >= 20
ORDER BY post_count_24h DESC;
