<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Helpers;
use Twitkey\Models\Affiliation;
use Twitkey\Models\Follow;
use Twitkey\Models\Notification;
use Twitkey\Models\Tweet;

final class NotificationsController
{
    /**
     * Show @reply and mention tweets.
     */
    public function replies(): void
    {
        $user = Auth::requireLogin();
        $page = Helpers::page();
        Helpers::render('home/timeline', [
            'title' => '@Replies',
            'heading' => '@Replies',
            'tweets' => Tweet::mentionsFor((string)$user['username'], $page, (int)$user['id']),
            'page' => $page,
            'basePath' => '/replies',
        ]);
    }

    /**
     * Show notification inbox.
     */
    public function index(): void
    {
        $user = Auth::requireLogin();
        $page = Helpers::page();
        $notifications = Notification::forUser((int)$user['id'], $page);
        Notification::markRead((int)$user['id']);
        Helpers::render('notifications/index', [
            'title' => 'Notifications',
            'notifications' => $notifications,
            'pendingAffiliations' => Affiliation::pendingForUser((int)$user['id']),
            'pendingFollowRequests' => Follow::pendingForUser((int)$user['id']),
            'page' => $page,
        ]);
    }
}
