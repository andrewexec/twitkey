<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class Notification
{
    /**
     * Create a notification unless actor and recipient are the same user.
     */
    public static function create(int $userId, int $actorId, string $type, ?int $tweetId = null): void
    {
        if ($userId === $actorId) {
            return;
        }
        Database::instance()->execute(
            'INSERT INTO notifications (user_id, actor_id, type, tweet_id) VALUES (:user_id, :actor_id, :type, :tweet_id)',
            ['user_id' => $userId, 'actor_id' => $actorId, 'type' => $type, 'tweet_id' => $tweetId]
        );
    }

    /**
     * Return notifications for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId, int $page): array
    {
        $stmt = Database::instance()->pdo()->prepare(
            'SELECT n.*, a.username AS actor_username, a.display_name AS actor_display_name, a.avatar AS actor_avatar,
                    a.is_admin AS actor_is_admin, a.is_system AS actor_is_system, a.is_verified AS actor_is_verified, a.verified_type AS actor_verified_type, t.body AS tweet_body
             FROM notifications n
             JOIN users a ON a.id = n.actor_id
             LEFT JOIN tweets t ON t.id = n.tweet_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT 20 OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * 20, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark all notifications read for a user.
     */
    public static function markRead(int $userId): void
    {
        Database::instance()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = :id', ['id' => $userId]);
    }

    /**
     * Return unread count for a user.
     */
    public static function unreadCount(int $userId): int
    {
        $row = Database::instance()->one('SELECT COUNT(*) AS count FROM notifications WHERE user_id = :id AND is_read = 0', ['id' => $userId]);
        return (int)($row['count'] ?? 0);
    }
}
