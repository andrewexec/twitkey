<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class Follow
{
    /**
     * Toggle following state and return the new state.
     *
     * @return array{following:bool,pending:bool,followers:int}
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
                $pending = false;
            } else {
                $request = $db->one(
                    'SELECT id, status FROM follow_requests WHERE requester_id = :requester_id AND target_id = :target_id',
                    ['requester_id' => $followerId, 'target_id' => $followingId]
                );
                if ($request && $request['status'] === 'pending') {
                    $db->execute('UPDATE follow_requests SET status = :status, updated_at = :updated_at WHERE id = :id', ['status' => 'declined', 'updated_at' => date('Y-m-d H:i:s'), 'id' => (int)$request['id']]);
                    $following = false;
                    $pending = false;
                } else {
                    $target = $db->one('SELECT is_private, follow_privacy FROM users WHERE id = :id', ['id' => $followingId]);
                    if ($target && ((int)($target['is_private'] ?? 0) === 1 || ($target['follow_privacy'] ?? 'everyone') === 'approve')) {
                        if ($request) {
                            $db->execute(
                                'UPDATE follow_requests SET status = :status, updated_at = :updated_at WHERE id = :id',
                                ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s'), 'id' => (int)$request['id']]
                            );
                        } else {
                            $db->execute(
                                'INSERT INTO follow_requests (requester_id, target_id) VALUES (:requester_id, :target_id)',
                                ['requester_id' => $followerId, 'target_id' => $followingId]
                            );
                        }
                        $following = false;
                        $pending = true;
                    } else {
                        self::insertFollow($db, $followerId, $followingId);
                        Notification::create($followingId, $followerId, 'follow');
                        $following = true;
                        $pending = false;
                    }
                }
            }
            $row = $db->one('SELECT follower_count FROM users WHERE id = :id', ['id' => $followingId]);
            return ['following' => $following, 'pending' => $pending, 'followers' => (int)($row['follower_count'] ?? 0)];
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

    /**
     * True when both users follow each other.
     */
    public static function isMutual(?int $firstUserId, int $secondUserId): bool
    {
        if ($firstUserId === null) {
            return false;
        }
        return self::isFollowing($firstUserId, $secondUserId) && self::isFollowing($secondUserId, $firstUserId);
    }

    /**
     * True when a follow request is pending.
     */
    public static function isPending(?int $requesterId, int $targetId): bool
    {
        if ($requesterId === null) {
            return false;
        }
        return Database::instance()->one(
            'SELECT id FROM follow_requests WHERE requester_id = :requester_id AND target_id = :target_id AND status = :status',
            ['requester_id' => $requesterId, 'target_id' => $targetId, 'status' => 'pending']
        ) !== null;
    }

    /**
     * Return pending follow requests for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendingForUser(int $targetId): array
    {
        return Database::instance()->all(
            'SELECT fr.*, u.username, u.display_name, u.avatar, u.is_admin, u.is_system, u.is_verified, u.is_private, u.verified_type
             FROM follow_requests fr
             JOIN users u ON u.id = fr.requester_id
             WHERE fr.target_id = :target_id AND fr.status = :status
             ORDER BY fr.created_at DESC',
            ['target_id' => $targetId, 'status' => 'pending']
        );
    }

    /**
     * Count pending follow requests for a user.
     */
    public static function pendingCount(int $targetId): int
    {
        $row = Database::instance()->one(
            'SELECT COUNT(*) AS count FROM follow_requests WHERE target_id = :target_id AND status = :status',
            ['target_id' => $targetId, 'status' => 'pending']
        );
        return (int)($row['count'] ?? 0);
    }

    /**
     * Approve or decline a follow request.
     */
    public static function resolveRequest(int $requestId, int $targetId, string $action): void
    {
        if (!in_array($action, ['approve', 'decline'], true)) {
            throw new \InvalidArgumentException('Invalid follow request action.');
        }
        $db = Database::instance();
        $db->transaction(static function () use ($db, $requestId, $targetId, $action): void {
            $request = $db->one(
                'SELECT * FROM follow_requests WHERE id = :id AND target_id = :target_id AND status = :status',
                ['id' => $requestId, 'target_id' => $targetId, 'status' => 'pending']
            );
            if (!$request) {
                throw new \RuntimeException('Follow request not found.');
            }
            $status = $action === 'approve' ? 'approved' : 'declined';
            $db->execute(
                'UPDATE follow_requests SET status = :status, updated_at = :updated_at WHERE id = :id',
                ['status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $requestId]
            );
            if ($action === 'approve') {
                self::insertFollow($db, (int)$request['requester_id'], $targetId);
            }
        });
    }

    /**
     * Insert a follow relation and update counters idempotently.
     */
    private static function insertFollow(Database $db, int $followerId, int $followingId): void
    {
        $existing = $db->one(
            'SELECT id FROM follows WHERE follower_id = :follower_id AND following_id = :following_id',
            ['follower_id' => $followerId, 'following_id' => $followingId]
        );
        if ($existing) {
            return;
        }
        if ($db->isMysql()) {
            $db->execute(
                'INSERT IGNORE INTO follows (follower_id, following_id) VALUES (:follower_id, :following_id)',
                ['follower_id' => $followerId, 'following_id' => $followingId]
            );
        } else {
            $db->execute(
                'INSERT OR IGNORE INTO follows (follower_id, following_id) VALUES (:follower_id, :following_id)',
                ['follower_id' => $followerId, 'following_id' => $followingId]
            );
        }
        $db->execute('UPDATE users SET following_count = following_count + 1 WHERE id = :id', ['id' => $followerId]);
        $db->execute('UPDATE users SET follower_count = follower_count + 1 WHERE id = :id', ['id' => $followingId]);
    }
}
