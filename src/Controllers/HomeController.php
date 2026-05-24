<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Database;
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\Tweet;
use Twitkey\Models\User;

final class HomeController
{
    /**
     * Show the signed-in timeline, or a guest landing page.
     */
    public function timeline(): void
    {
        $page = Helpers::page();
        $user = Auth::user();
        if (!$user) {
            $db = Database::instance();
            Helpers::render('home/landing', [
                'title' => 'Welcome',
                'latestTweets' => Tweet::publicTimeline(1),
                'stats' => [
                    'users' => (int)($db->one('SELECT COUNT(*) AS count FROM users WHERE is_system = 0')['count'] ?? 0),
                    'tweets' => (int)($db->one('SELECT COUNT(*) AS count FROM tweets WHERE is_deleted = 0')['count'] ?? 0),
                ],
            ]);
            return;
        }

        $tweets = Tweet::feedForUser((int)$user['id'], $page, isset($_GET['last_id']) ? (int)$_GET['last_id'] : null);
        Helpers::render('home/timeline', [
            'title' => 'Home',
            'heading' => 'Home',
            'tweets' => $tweets,
            'page' => $page,
            'basePath' => '/',
        ]);
    }

    /**
     * Show all public tweets.
     */
    public function publicTimeline(): void
    {
        $page = Helpers::page();
        Helpers::render('home/timeline', [
            'title' => 'Public Timeline',
            'heading' => 'Public Timeline',
            'tweets' => Tweet::publicTimeline($page),
            'page' => $page,
            'basePath' => '/public',
        ]);
    }

    /**
     * Search tweets and users.
     */
    public function search(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $page = Helpers::page();
        $tweets = $query === '' ? [] : Tweet::search($query, $page, Auth::id());
        $users = $query === '' ? [] : User::search(ltrim($query, '@#'), 8);
        Helpers::render('home/search', [
            'title' => 'Search',
            'query' => $query,
            'tweets' => $tweets,
            'users' => $users,
            'page' => $page,
        ]);
    }

    /**
     * Show direct messages.
     */
    public function dms(): void
    {
        $user = Auth::requireLogin();
        $db = Database::instance();
        $conversations = $db->all(
            'SELECT u.*, MAX(dm.created_at) AS last_at,
                    SUM(CASE WHEN dm.recipient_id = :id AND dm.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
             FROM direct_messages dm
             JOIN users u ON u.id = CASE WHEN dm.sender_id = :id THEN dm.recipient_id ELSE dm.sender_id END
             WHERE dm.sender_id = :id OR dm.recipient_id = :id
             GROUP BY u.id
             ORDER BY last_at DESC',
            ['id' => (int)$user['id']]
        );

        $selected = null;
        if (isset($_GET['user']) && $_GET['user'] !== '') {
            $selected = User::findByUsername((string)$_GET['user']);
        } elseif ($conversations !== []) {
            $selected = User::find((int)$conversations[0]['id']);
        }

        $messages = [];
        if ($selected) {
            $messages = $db->all(
                'SELECT dm.*, s.username AS sender_username, s.display_name AS sender_display_name, s.avatar AS sender_avatar,
                        s.is_admin AS sender_is_admin, s.is_system AS sender_is_system, s.is_verified AS sender_is_verified, s.is_private AS sender_is_private, s.verified_type AS sender_verified_type
                 FROM direct_messages dm
                 JOIN users s ON s.id = dm.sender_id
                 WHERE (dm.sender_id = :me AND dm.recipient_id = :them)
                    OR (dm.sender_id = :them AND dm.recipient_id = :me)
                 ORDER BY dm.created_at ASC',
                ['me' => (int)$user['id'], 'them' => (int)$selected['id']]
            );
            $db->execute('UPDATE direct_messages SET is_read = 1 WHERE recipient_id = :me AND sender_id = :them', ['me' => (int)$user['id'], 'them' => (int)$selected['id']]);
        }

        Helpers::render('home/dms', [
            'title' => 'Direct Messages',
            'conversations' => $conversations,
            'selected' => $selected,
            'messages' => $messages,
            'canMessageSelected' => $selected ? User::canMessage($user, $selected) : false,
        ]);
    }

    /**
     * Send a direct message.
     */
    public function sendDm(string $user): void
    {
        Helpers::verifyCsrf();
        $sender = Auth::requireActiveUser();
        $recipient = User::findByUsername($user);
        $body = trim((string)($_POST['body'] ?? ''));
        if (!$recipient || $body === '' || strlen($body) > 1000) {
            Session::flash('error', 'Message must be 1-1000 characters and recipient must exist.');
            Helpers::redirect('/direct_messages');
        }
        if (!User::canMessage($sender, $recipient)) {
            Session::flash('error', 'You can only message this user if their message privacy allows it.');
            Helpers::redirect('/direct_messages?user=' . rawurlencode((string)$recipient['username']));
        }
        Database::instance()->execute(
            'INSERT INTO direct_messages (sender_id, recipient_id, body) VALUES (:sender_id, :recipient_id, :body)',
            ['sender_id' => (int)$sender['id'], 'recipient_id' => (int)$recipient['id'], 'body' => $body]
        );
        Helpers::redirect('/direct_messages?user=' . rawurlencode((string)$recipient['username']));
    }
}
