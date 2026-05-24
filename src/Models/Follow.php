<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class Follow
{
    /**
     * Toggle following state and return the new state.
     *
     * @return array{following:bool,followers:int}
     */
    public static function toggle(int $followerId, int $followingId): array
    {
        if ($followerId === $followingId) {
            throw new \InvalidArgumentException('You cannot follow yourself.');
        }

        $db = Database::instance();
        return $db->transaction(static function () use ($db, $followerId, $followingId): array {
            $exists = $db->one(
                'SELECT id FROM follows WHERE follower_id = :follower_id AND following_id = :following_id',
                ['follower_id' => $followerId, 'following_id' => $followingId]
            );
            if ($exists) {
                $db->execute('DELETE FROM follows WHERE id = :id', ['id' => (int)$exists['id']]);
                $db->execute('UPDATE users SET following_count = CASE WHEN following_count > 0 THEN following_count - 1 ELSE 0 END WHERE id = :id', ['id' => $followerId]);
                $db->execute('UPDATE users SET follower_count = CASE WHEN follower_count > 0 THEN follower_count - 1 ELSE 0 END WHERE id = :id', ['id' => $followingId]);
                $following = false;
            } else {
                $db->execute(
                    'INSERT INTO follows (follower_id, following_id) VALUES (:follower_id, :following_id)',
                    ['follower_id' => $followerId, 'following_id' => $followingId]
                );
                $db->execute('UPDATE users SET following_count = following_count + 1 WHERE id = :id', ['id' => $followerId]);
                $db->execute('UPDATE users SET follower_count = follower_count + 1 WHERE id = :id', ['id' => $followingId]);
                Notification::create($followingId, $followerId, 'follow');
                $following = true;
            }
            $row = $db->one('SELECT follower_count FROM users WHERE id = :id', ['id' => $followingId]);
            return ['following' => $following, 'followers' => (int)($row['follower_count'] ?? 0)];
        });
    }

    /**
     * True when one user follows another.
     */
    public static function isFollowing(?int $followerId, int $followingId): bool
    {
        if ($followerId === null) {
            return false;
        }
        return Database::instance()->one(
            'SELECT id FROM follows WHERE follower_id = :follower_id AND following_id = :following_id',
            ['follower_id' => $followerId, 'following_id' => $followingId]
        ) !== null;
    }
}
