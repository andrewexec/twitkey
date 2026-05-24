<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class User
{
    /**
     * Create a user and return the new id.
     *
     * @param array{username:string,display_name:string,email:string,password:string} $data
     */
    public static function create(array $data): int
    {
        $db = Database::instance();
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $db->execute(
            'INSERT INTO users (username, display_name, email, password) VALUES (:username, :display_name, :email, :password)',
            [
                'username' => strtolower($data['username']),
                'display_name' => $data['display_name'],
                'email' => strtolower($data['email']),
                'password' => $hash,
            ]
        );
        return $db->lastInsertId();
    }

    /**
     * Find a user by id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::instance()->one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * Find a user by username.
     *
     * @return array<string, mixed>|null
     */
    public static function findByUsername(string $username): ?array
    {
        $username = ltrim(strtolower($username), '@');
        return Database::instance()->one('SELECT * FROM users WHERE lower(username) = lower(:username)', ['username' => $username]);
    }

    /**
     * Find a user by username or email login.
     *
     * @return array<string, mixed>|null
     */
    public static function findByLogin(string $login): ?array
    {
        return Database::instance()->one(
            'SELECT * FROM users WHERE lower(username) = lower(:login) OR lower(email) = lower(:login) LIMIT 1',
            ['login' => trim($login)]
        );
    }

    /**
     * Return true when a username is syntactically valid and not taken.
     */
    public static function usernameAvailable(string $username): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,15}$/', $username)) {
            return false;
        }
        return self::findByUsername($username) === null;
    }

    /**
     * Update editable profile fields for a user.
     *
     * @param array<string, string|null> $fields
     */
    public static function updateProfile(int $id, array $fields): void
    {
        Database::instance()->execute(
            'UPDATE users
             SET display_name = :display_name, bio = :bio, location = :location, website = :website,
                 avatar = COALESCE(:avatar, avatar), background = COALESCE(:background, background), updated_at = :updated_at
             WHERE id = :id',
            [
                'display_name' => $fields['display_name'] ?? '',
                'bio' => $fields['bio'] ?? '',
                'location' => $fields['location'] ?? '',
                'website' => $fields['website'] ?? '',
                'avatar' => $fields['avatar'] ?? null,
                'background' => $fields['background'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    /**
     * Search users by username, display name, or bio.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, int $limit = 10): array
    {
        $term = '%' . $query . '%';
        $stmt = Database::instance()->pdo()->prepare(
            'SELECT * FROM users
             WHERE is_suspended = 0
               AND (lower(username) LIKE lower(:term) OR lower(display_name) LIKE lower(:term) OR lower(bio) LIKE lower(:term))
             ORDER BY follower_count DESC, username ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':term', $term);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return who-to-follow suggestions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function suggestions(?int $currentUserId, int $limit = 3): array
    {
        $db = Database::instance();
        if ($currentUserId === null) {
            $stmt = $db->pdo()->prepare('SELECT * FROM users WHERE is_suspended = 0 ORDER BY follower_count DESC, created_at DESC LIMIT :limit');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $db->pdo()->prepare(
            'SELECT * FROM users
             WHERE id <> :id
               AND is_suspended = 0
               AND id NOT IN (SELECT following_id FROM follows WHERE follower_id = :id)
             ORDER BY follower_count DESC, created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':id', $currentUserId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return users following a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function followers(int $userId, int $page): array
    {
        $stmt = Database::instance()->pdo()->prepare(
            'SELECT u.* FROM follows f JOIN users u ON u.id = f.follower_id
             WHERE f.following_id = :id
             ORDER BY f.created_at DESC LIMIT 20 OFFSET :offset'
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * 20, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return users a user follows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function following(int $userId, int $page): array
    {
        $stmt = Database::instance()->pdo()->prepare(
            'SELECT u.* FROM follows f JOIN users u ON u.id = f.following_id
             WHERE f.follower_id = :id
             ORDER BY f.created_at DESC LIMIT 20 OFFSET :offset'
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * 20, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Set admin state and mirrored role for a user.
     */
    public static function setAdmin(int $id, bool $admin): void
    {
        Database::instance()->execute(
            'UPDATE users SET is_admin = :admin, role = :role, updated_at = :updated_at WHERE id = :id',
            ['admin' => $admin ? 1 : 0, 'role' => $admin ? 'admin' : 'user', 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Set or clear verification state.
     */
    public static function setVerification(int $id, ?string $type): void
    {
        if ($type !== null && !in_array($type, ['business', 'government'], true)) {
            throw new \InvalidArgumentException('Invalid verification type.');
        }
        Database::instance()->execute(
            'UPDATE users SET verified_type = :type, is_verified = :is_verified, updated_at = :updated_at WHERE id = :id',
            ['type' => $type, 'is_verified' => $type === null ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Set or clear the normal verified badge.
     */
    public static function setNormalVerified(int $id, bool $verified): void
    {
        Database::instance()->execute(
            'UPDATE users SET verified_type = NULL, is_verified = :is_verified, updated_at = :updated_at WHERE id = :id',
            ['is_verified' => $verified ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Set suspended state for a user.
     */
    public static function setSuspended(int $id, bool $suspended): void
    {
        Database::instance()->execute(
            'UPDATE users SET is_suspended = :suspended, updated_at = :updated_at WHERE id = :id',
            ['suspended' => $suspended ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Delete a user account.
     */
    public static function delete(int $id): void
    {
        $user = self::find($id);
        if ($user && (int)($user['is_system'] ?? 0) === 1) {
            throw new \RuntimeException('System accounts cannot be deleted.');
        }
        Database::instance()->execute('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * Return the Community Notes system account.
     *
     * @return array<string, mixed>
     */
    public static function communityNotesBot(): array
    {
        $bot = self::findByUsername('CommunityNotes');
        if (!$bot) {
            throw new \RuntimeException('CommunityNotes system account is missing.');
        }
        return $bot;
    }

    /**
     * Return paginated users for the admin table.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function adminList(int $page): array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM users ORDER BY id DESC LIMIT 50 OFFSET :offset');
        $stmt->bindValue(':offset', ($page - 1) * 50, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
