<?php
declare(strict_types=1);

namespace Twitkey\Models;

use Twitkey\Core\Database;

final class Affiliation
{
    /**
     * Send or renew an affiliation invite from a business account.
     */
    public static function invite(int $businessId, string $targetUsername): void
    {
        $business = User::find($businessId);
        $target = User::findByUsername($targetUsername);
        if (!$business || ($business['verified_type'] ?? null) !== 'business') {
            throw new \RuntimeException('Only verified businesses can send affiliation invites.');
        }
        if (!$target) {
            throw new \InvalidArgumentException('User not found.');
        }
        if ((int)$target['id'] === $businessId) {
            throw new \InvalidArgumentException('A business cannot affiliate itself.');
        }

        $db = Database::instance();
        $count = $db->one('SELECT COUNT(*) AS count FROM affiliations WHERE business_id = :id AND status IN (:pending, :accepted)', ['id' => $businessId, 'pending' => 'pending', 'accepted' => 'accepted']);
        if ((int)($count['count'] ?? 0) >= 1000) {
            throw new \RuntimeException('A business can affiliate at most 1,000 users.');
        }

        $existing = $db->one(
            'SELECT id FROM affiliations WHERE business_id = :business_id AND affiliated_id = :affiliated_id',
            ['business_id' => $businessId, 'affiliated_id' => (int)$target['id']]
        );
        if ($existing) {
            $db->execute('UPDATE affiliations SET status = :status, created_at = :created_at WHERE id = :id', ['status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'id' => (int)$existing['id']]);
        } else {
            $db->execute(
                'INSERT INTO affiliations (business_id, affiliated_id) VALUES (:business_id, :affiliated_id)',
                ['business_id' => $businessId, 'affiliated_id' => (int)$target['id']]
            );
        }
        Notification::create((int)$target['id'], $businessId, 'affiliation_invite');
    }

    /**
     * Return pending invites for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendingForUser(int $userId): array
    {
        return Database::instance()->all(
            'SELECT a.*, b.username, b.display_name, b.avatar, b.is_admin, b.verified_type
             FROM affiliations a
             JOIN users b ON b.id = a.business_id
             WHERE a.affiliated_id = :id AND a.status = :status
             ORDER BY a.created_at DESC',
            ['id' => $userId, 'status' => 'pending']
        );
    }

    /**
     * Return affiliations sent by a business.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sentByBusiness(int $businessId): array
    {
        return Database::instance()->all(
            'SELECT a.*, u.username, u.display_name, u.avatar, u.is_admin, u.verified_type
             FROM affiliations a
             JOIN users u ON u.id = a.affiliated_id
             WHERE a.business_id = :id
             ORDER BY a.created_at DESC',
            ['id' => $businessId]
        );
    }

    /**
     * Accept a pending invite for the current user.
     */
    public static function accept(int $affiliationId, int $userId): void
    {
        $db = Database::instance();
        $db->transaction(static function () use ($db, $affiliationId, $userId): void {
            $invite = $db->one('SELECT * FROM affiliations WHERE id = :id AND affiliated_id = :user_id AND status = :status', ['id' => $affiliationId, 'user_id' => $userId, 'status' => 'pending']);
            if (!$invite) {
                throw new \InvalidArgumentException('Affiliation invite not found.');
            }
            $db->execute('UPDATE affiliations SET status = :revoked WHERE affiliated_id = :user_id AND status = :accepted', ['revoked' => 'revoked', 'user_id' => $userId, 'accepted' => 'accepted']);
            $db->execute('UPDATE affiliations SET status = :accepted WHERE id = :id', ['accepted' => 'accepted', 'id' => $affiliationId]);
        });
    }

    /**
     * Decline a pending invite.
     */
    public static function decline(int $affiliationId, int $userId): void
    {
        Database::instance()->execute(
            'UPDATE affiliations SET status = :status WHERE id = :id AND affiliated_id = :user_id AND status = :pending',
            ['status' => 'revoked', 'id' => $affiliationId, 'user_id' => $userId, 'pending' => 'pending']
        );
    }

    /**
     * Revoke an affiliation by the business or affiliated user.
     */
    public static function revoke(int $affiliationId, int $actorId): void
    {
        Database::instance()->execute(
            'UPDATE affiliations SET status = :status WHERE id = :id AND (business_id = :actor_id OR affiliated_id = :actor_id)',
            ['status' => 'revoked', 'id' => $affiliationId, 'actor_id' => $actorId]
        );
    }
}
