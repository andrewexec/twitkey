<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class Tweet
{
    /**
     * Create a tweet or reply and return the stored tweet row with user data.
     *
     * @return array<string, mixed>
     */
    public static function create(int $userId, string $body, ?int $replyToId = null, ?int $retweetOfId = null): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Tweet body is required.');
        }
        if (strlen($body) > 140) {
            throw new \InvalidArgumentException('Tweets are limited to 140 characters.');
        }

        $db = Database::instance();
        return $db->transaction(static function () use ($db, $userId, $body, $replyToId, $retweetOfId): array {
            $tweetId = self::insertTweet($db, $userId, $body, $replyToId, $retweetOfId);
            return self::findWithUser($tweetId, true) ?? [];
        });
    }

    /**
     * Return the home timeline for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function feedForUser(int $userId, int $page, ?int $lastId = null): array
    {
        $where = 't.is_deleted = 0 AND u.is_suspended = 0 AND (t.user_id = :user_id OR t.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id))';
        return self::feed($where, ['user_id' => $userId], $page, $lastId);
    }

    /**
     * Return the public timeline.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicTimeline(int $page, ?int $lastId = null): array
    {
        return self::feed('t.is_deleted = 0 AND u.is_suspended = 0', [], $page, $lastId);
    }

    /**
     * Return tweets for a profile tab.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProfile(int $userId, string $tab, int $page, bool $includeSuspended = false): array
    {
        $params = ['user_id' => $userId];
        if ($tab === 'favorites') {
            $where = 't.id IN (SELECT tweet_id FROM favorites WHERE user_id = :user_id) AND t.is_deleted = 0';
        } elseif ($tab === 'replies') {
            $where = 't.user_id = :user_id AND t.reply_to_id IS NOT NULL AND t.is_deleted = 0';
        } else {
            $where = 't.user_id = :user_id AND t.is_deleted = 0';
        }
        if (!$includeSuspended) {
            $where .= ' AND u.is_suspended = 0';
        }
        return self::feed($where, $params, $page, null);
    }

    /**
     * Return tweets matching a search term.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, int $page): array
    {
        $term = '%' . $query . '%';
        return self::feed('t.is_deleted = 0 AND u.is_suspended = 0 AND lower(t.body) LIKE lower(:term)', ['term' => $term], $page, null);
    }

    /**
     * Return tweets mentioning a username.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mentionsFor(string $username, int $page): array
    {
        $term = '%@' . $username . '%';
        return self::feed('t.is_deleted = 0 AND u.is_suspended = 0 AND lower(t.body) LIKE lower(:term)', ['term' => strtolower($term)], $page, null);
    }

    /**
     * Find a tweet and eager-loaded author by id.
     *
     * @return array<string, mixed>|null
     */
    public static function findWithUser(int $id, bool $includeDeleted = false): ?array
    {
        $where = 't.id = :id';
        if (!$includeDeleted) {
            $where .= ' AND t.is_deleted = 0';
        }
        $rows = self::feed($where, ['id' => $id], 1, null, 1, true);
        return $rows[0] ?? null;
    }

    /**
     * Return direct replies to a tweet in chronological order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function repliesTo(int $tweetId, bool $includeDeleted = true): array
    {
        $where = 't.reply_to_id = :tweet_id';
        if (!$includeDeleted) {
            $where .= ' AND t.is_deleted = 0';
        }
        $rows = self::feed($where, ['tweet_id' => $tweetId], 1, null, 200, true, 'ASC');
        return $rows;
    }

    /**
     * Toggle a favorite and return new count/state.
     *
     * @return array{favorited:bool,count:int}
     */
    public static function toggleFavorite(int $userId, int $tweetId): array
    {
        $db = Database::instance();
        return $db->transaction(static function () use ($db, $userId, $tweetId): array {
            $tweet = self::findWithUser($tweetId);
            if (!$tweet) {
                throw new \InvalidArgumentException('Tweet not found.');
            }
            $existing = $db->one('SELECT id FROM favorites WHERE user_id = :user_id AND tweet_id = :tweet_id', ['user_id' => $userId, 'tweet_id' => $tweetId]);
            if ($existing) {
                $db->execute('DELETE FROM favorites WHERE id = :id', ['id' => (int)$existing['id']]);
                $db->execute('UPDATE tweets SET favorite_count = CASE WHEN favorite_count > 0 THEN favorite_count - 1 ELSE 0 END WHERE id = :id', ['id' => $tweetId]);
                $favorited = false;
            } else {
                $db->execute('INSERT INTO favorites (user_id, tweet_id) VALUES (:user_id, :tweet_id)', ['user_id' => $userId, 'tweet_id' => $tweetId]);
                $db->execute('UPDATE tweets SET favorite_count = favorite_count + 1 WHERE id = :id', ['id' => $tweetId]);
                Notification::create((int)$tweet['user_id'], $userId, 'favorite', $tweetId);
                $favorited = true;
            }
            $row = $db->one('SELECT favorite_count FROM tweets WHERE id = :id', ['id' => $tweetId]);
            return ['favorited' => $favorited, 'count' => (int)($row['favorite_count'] ?? 0)];
        });
    }

    /**
     * Create a classic RT tweet and return the new tweet.
     *
     * @return array<string, mixed>
     */
    public static function retweet(int $userId, int $tweetId): array
    {
        $db = Database::instance();
        return $db->transaction(static function () use ($db, $userId, $tweetId): array {
            $original = self::findWithUser($tweetId);
            if (!$original) {
                throw new \InvalidArgumentException('Tweet not found.');
            }
            $existing = $db->one('SELECT id FROM retweets WHERE user_id = :user_id AND tweet_id = :tweet_id', ['user_id' => $userId, 'tweet_id' => $tweetId]);
            if ($existing) {
                throw new \InvalidArgumentException('You already retweeted this.');
            }
            $body = 'RT @' . $original['username'] . ': ' . $original['body'];
            if (strlen($body) > 140) {
                $body = substr($body, 0, 137) . '...';
            }
            $newTweetId = self::insertTweet($db, $userId, $body, null, $tweetId);
            $db->execute('INSERT INTO retweets (user_id, tweet_id) VALUES (:user_id, :tweet_id)', ['user_id' => $userId, 'tweet_id' => $tweetId]);
            $db->execute('UPDATE tweets SET retweet_count = retweet_count + 1 WHERE id = :id', ['id' => $tweetId]);
            Notification::create((int)$original['user_id'], $userId, 'retweet', $tweetId);
            return self::findWithUser($newTweetId, true) ?? [];
        });
    }

    /**
     * Soft-delete a tweet if the actor is the owner or an admin.
     */
    public static function delete(int $tweetId, int $actorId, bool $isAdmin): void
    {
        $tweet = self::findWithUser($tweetId, true);
        if (!$tweet) {
            throw new \InvalidArgumentException('Tweet not found.');
        }
        if (!$isAdmin && (int)$tweet['user_id'] !== $actorId) {
            throw new \RuntimeException('Forbidden.');
        }
        $db = Database::instance();
        $db->transaction(static function () use ($db, $tweetId, $tweet): void {
            if ((int)$tweet['is_deleted'] === 0) {
                $db->execute('UPDATE tweets SET is_deleted = 1 WHERE id = :id', ['id' => $tweetId]);
                $db->execute('UPDATE users SET tweet_count = CASE WHEN tweet_count > 0 THEN tweet_count - 1 ELSE 0 END WHERE id = :id', ['id' => (int)$tweet['user_id']]);
            }
        });
    }

    /**
     * True when a user has favorited a tweet.
     */
    public static function isFavorited(?int $userId, int $tweetId): bool
    {
        if ($userId === null) {
            return false;
        }
        return Database::instance()->one(
            'SELECT id FROM favorites WHERE user_id = :user_id AND tweet_id = :tweet_id',
            ['user_id' => $userId, 'tweet_id' => $tweetId]
        ) !== null;
    }

    /**
     * Return recent tweets for the admin table.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function adminList(int $page): array
    {
        return self::feed('1 = 1', [], $page, null, 50, true);
    }

    /**
     * Fetch tweet rows with eager-loaded users and approved note preview.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private static function feed(string $where, array $params, int $page, ?int $lastId, int $limit = 20, bool $includeSuspended = false, string $direction = 'DESC'): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        if ($lastId !== null) {
            $where .= ' AND t.id < :last_id';
            $params['last_id'] = $lastId;
        }
        if (!$includeSuspended && !str_contains($where, 'u.is_suspended')) {
            $where .= ' AND u.is_suspended = 0';
        }

        $stmt = Database::instance()->pdo()->prepare(
            "SELECT t.*,
                    u.username, u.display_name, u.email, u.bio, u.location, u.website, u.avatar, u.background,
                    u.role, u.verified_type, u.is_verified, u.is_admin, u.is_system, u.is_suspended, u.follower_count, u.following_count, u.tweet_count,
                    u.created_at AS user_created_at,
                    (SELECT cn.body FROM community_notes cn WHERE cn.tweet_id = t.id AND cn.status = 'approved' ORDER BY cn.helpful_votes DESC, cn.id ASC LIMIT 1) AS approved_note_body,
                    (SELECT cn.id FROM community_notes cn WHERE cn.tweet_id = t.id AND cn.status = 'approved' ORDER BY cn.helpful_votes DESC, cn.id ASC LIMIT 1) AS approved_note_id
             FROM tweets t
             JOIN users u ON u.id = t.user_id
             WHERE {$where}
             ORDER BY t.id {$direction}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Insert a tweet inside an existing transaction and perform related updates.
     */
    private static function insertTweet(Database $db, int $userId, string $body, ?int $replyToId, ?int $retweetOfId): int
    {
        $db->execute(
            'INSERT INTO tweets (user_id, body, reply_to_id, retweet_of_id) VALUES (:user_id, :body, :reply_to_id, :retweet_of_id)',
            ['user_id' => $userId, 'body' => $body, 'reply_to_id' => $replyToId, 'retweet_of_id' => $retweetOfId]
        );
        $tweetId = $db->lastInsertId();
        $db->execute('UPDATE users SET tweet_count = tweet_count + 1 WHERE id = :id', ['id' => $userId]);

        if ($replyToId !== null) {
            $parent = self::findWithUser($replyToId, true);
            if ($parent) {
                $db->execute('UPDATE tweets SET reply_count = reply_count + 1 WHERE id = :id', ['id' => $replyToId]);
                Notification::create((int)$parent['user_id'], $userId, 'reply', $tweetId);
            }
        }

        self::indexHashtags($tweetId, $body);
        self::notifyMentions($tweetId, $userId, $body);
        return $tweetId;
    }

    /**
     * Index hashtags found in a tweet.
     */
    private static function indexHashtags(int $tweetId, string $body): void
    {
        if (preg_match_all('/(?<![\w#])#([A-Za-z0-9_]{1,60})/', $body, $matches) !== 1) {
            return;
        }
        $db = Database::instance();
        foreach (array_unique(array_map('strtolower', $matches[1])) as $tag) {
            $existing = $db->one('SELECT id FROM hashtags WHERE tag = :tag', ['tag' => $tag]);
            if (!$existing) {
                $db->execute('INSERT INTO hashtags (tag) VALUES (:tag)', ['tag' => $tag]);
                $hashtagId = $db->lastInsertId();
            } else {
                $hashtagId = (int)$existing['id'];
            }
            if ($db->isMysql()) {
                $db->execute(
                    'INSERT IGNORE INTO tweet_hashtags (tweet_id, hashtag_id) VALUES (:tweet_id, :hashtag_id)',
                    ['tweet_id' => $tweetId, 'hashtag_id' => $hashtagId]
                );
            } else {
                $db->execute(
                    'INSERT OR IGNORE INTO tweet_hashtags (tweet_id, hashtag_id) VALUES (:tweet_id, :hashtag_id)',
                    ['tweet_id' => $tweetId, 'hashtag_id' => $hashtagId]
                );
            }
        }
    }

    /**
     * Notify mentioned users once per tweet.
     */
    private static function notifyMentions(int $tweetId, int $actorId, string $body): void
    {
        if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{1,15})/', $body, $matches) !== 1) {
            return;
        }
        foreach (array_unique(array_map('strtolower', $matches[1])) as $username) {
            $user = User::findByUsername($username);
            if ($user) {
                Notification::create((int)$user['id'], $actorId, 'mention', $tweetId);
            }
        }
    }
}
