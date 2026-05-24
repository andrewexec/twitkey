<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Database;
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\CommunityNote;
use Twitkey\Models\Tweet;
use Twitkey\Models\User;

final class AdminController
{
    /**
     * Show the admin dashboard.
     */
    public function dashboard(): void
    {
        Auth::requireAdmin();
        $db = Database::instance();
        $todaySql = $db->isMysql() ? 'DATE(created_at) = CURRENT_DATE' : "date(created_at) = date('now')";
        Helpers::render('admin/dashboard', [
            'title' => 'Admin',
            'stats' => [
                'users' => (int)($db->one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
                'tweets_today' => (int)($db->one("SELECT COUNT(*) AS c FROM tweets WHERE {$todaySql}")['c'] ?? 0),
                'active' => (int)($db->one("SELECT COUNT(DISTINCT user_id) AS c FROM tweets WHERE {$todaySql}")['c'] ?? 0),
                'pending_notes' => (int)($db->one('SELECT COUNT(*) AS c FROM community_notes WHERE status = :status', ['status' => 'pending'])['c'] ?? 0),
                'suspended' => (int)($db->one('SELECT COUNT(*) AS c FROM users WHERE is_suspended = 1')['c'] ?? 0),
            ],
            'logs' => $db->all('SELECT l.*, u.username FROM admin_log l JOIN users u ON u.id = l.admin_id ORDER BY l.created_at DESC LIMIT 10'),
        ]);
    }

    /**
     * Show manageable users.
     */
    public function users(): void
    {
        Auth::requireAdmin();
        Helpers::render('admin/users', ['title' => 'Manage Users', 'users' => User::adminList(Helpers::page()), 'page' => Helpers::page()]);
    }

    /**
     * Apply an admin action to a user.
     */
    public function userAction(string $id): void
    {
        Helpers::verifyCsrf();
        $admin = Auth::requireAdmin();
        $userId = (int)$id;
        $action = (string)($_POST['action'] ?? '');
        try {
            match ($action) {
                'grant_admin' => User::setAdmin($userId, true),
                'revoke_admin' => User::setAdmin($userId, false),
                'verify_normal' => User::setNormalVerified($userId, true),
                'verify_business' => User::setVerification($userId, 'business'),
                'verify_government' => User::setVerification($userId, 'government'),
                'remove_verification' => User::setVerification($userId, null),
                'suspend' => User::setSuspended($userId, true),
                'unsuspend' => User::setSuspended($userId, false),
                'delete' => User::delete($userId),
                default => throw new \InvalidArgumentException('Unknown admin action.'),
            };
            $this->log((int)$admin['id'], $action, 'user', $userId);
            Session::flash('success', 'User action applied.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/admin/users');
    }

    /**
     * Show manageable tweets.
     */
    public function tweets(): void
    {
        Auth::requireAdmin();
        Helpers::render('admin/tweets', ['title' => 'Manage Tweets', 'tweets' => Tweet::adminList(Helpers::page()), 'page' => Helpers::page()]);
    }

    /**
     * Apply an admin action to a tweet.
     */
    public function tweetAction(string $id): void
    {
        Helpers::verifyCsrf();
        $admin = Auth::requireAdmin();
        $tweetId = (int)$id;
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'delete') {
                Tweet::delete($tweetId, (int)$admin['id'], true);
            } elseif ($action === 'remove_note') {
                CommunityNote::rejectForTweet($tweetId, (int)$admin['id']);
            } elseif ($action === 'add_note') {
                $bot = User::communityNotesBot();
                CommunityNote::addApproved($tweetId, (int)$bot['id'], (string)($_POST['body'] ?? ''), (int)$admin['id']);
            } else {
                throw new \InvalidArgumentException('Unknown tweet action.');
            }
            $this->log((int)$admin['id'], $action, 'tweet', $tweetId);
            Session::flash('success', 'Tweet action applied.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/admin/tweets');
    }

    /**
     * Show manageable community notes.
     */
    public function notes(): void
    {
        Auth::requireAdmin();
        Helpers::render('admin/notes', ['title' => 'Community Notes', 'notes' => CommunityNote::adminList(Helpers::page()), 'page' => Helpers::page()]);
    }

    /**
     * Apply an admin action to a community note.
     */
    public function noteAction(string $id): void
    {
        Helpers::verifyCsrf();
        $admin = Auth::requireAdmin();
        $noteId = (int)$id;
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'approve') {
                CommunityNote::setStatus($noteId, 'approved', (int)$admin['id']);
            } elseif ($action === 'reject') {
                CommunityNote::setStatus($noteId, 'rejected', (int)$admin['id']);
            } elseif ($action === 'delete') {
                CommunityNote::delete($noteId);
            } else {
                throw new \InvalidArgumentException('Unknown note action.');
            }
            $this->log((int)$admin['id'], $action, 'note', $noteId);
            Session::flash('success', 'Note action applied.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/admin/notes');
    }

    /**
     * Promote the first admin once using the setup token.
     */
    public function setup(): void
    {
        $flag = Database::instance()->dataDir() . '/.admin_setup_done';
        if (is_file($flag)) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'Not Found'], true);
            return;
        }
        $token = (string)($_GET['token'] ?? '');
        $username = (string)($_GET['username'] ?? '');
        if ($token === '' || !hash_equals(Helpers::env('ADMIN_SETUP_TOKEN', ''), $token) || $username === '') {
            http_response_code(403);
            Helpers::render('errors/403', ['title' => 'Forbidden'], true);
            return;
        }
        $user = User::findByUsername($username);
        if (!$user) {
            Session::flash('error', 'User not found.');
            Helpers::redirect('/login');
        }
        User::setAdmin((int)$user['id'], true);
        file_put_contents($flag, date(DATE_ATOM));
        Session::flash('success', '@' . $user['username'] . ' is now an admin.');
        Helpers::redirect('/' . rawurlencode((string)$user['username']));
    }

    /**
     * Write an admin action to the audit log.
     */
    private function log(int $adminId, string $action, string $targetType, int $targetId): void
    {
        Database::instance()->execute(
            'INSERT INTO admin_log (admin_id, action, target_type, target_id) VALUES (:admin_id, :action, :target_type, :target_id)',
            ['admin_id' => $adminId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId]
        );
    }
}
