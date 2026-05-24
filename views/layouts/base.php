<?php
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\Notification;

$appName = Helpers::env('APP_NAME', 'Twitkey');
$unread = $currentUser ? Notification::unreadCount((int)$currentUser['id']) : 0;
$notificationsLabel = $unread > 0 ? '(' . ($unread > 99 ? '99+' : (string)$unread) . ') Notifications' : 'Notifications';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= Helpers::h(Helpers::csrfToken()) ?>">
    <title><?= Helpers::h($title ?? $appName) ?> / <?= Helpers::h($appName) ?></title>
    <link rel="stylesheet" href="/css/twitkey.css">
</head>
<body>
<div class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/">
            <span class="brand-word">twitkey</span>
            <img src="/img/logo.png" alt="" class="brand-bird">
        </a>
        <div class="topnav">
            <?php if ($currentUser): ?>
                <a href="/">Home</a> |
                <a href="/<?= Helpers::h($currentUser['username']) ?>">Profile</a> |
                <a href="/replies">@Replies</a> |
                <a href="/notifications"><?= Helpers::h($notificationsLabel) ?></a> |
                <a href="/direct_messages">Direct Messages</a> |
                <a href="/settings">Settings</a> |
                <?php if ((int)$currentUser['is_admin'] === 1): ?>
                    <a href="/admin">Admin</a> |
                <?php endif; ?>
                <a href="/public">Help</a> |
                <a href="/logout">Sign out</a>
            <?php else: ?>
                <a href="/login">Sign in</a> |
                <a href="/register">Join now</a> |
                <a href="/public">Help</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="subheader">
    <div class="subheader-inner">
        <?php if ($currentUser): ?>
            <img src="<?= Helpers::avatarUrl($currentUser) ?>" class="compose-avatar" alt="">
            <form action="/tweet" method="post" class="compose-form" data-tweet-form>
                <?= Helpers::csrfField() ?>
                <label for="compose-body">What are you doing?</label>
                <textarea id="compose-body" name="body" maxlength="140" data-counter-target="#compose-count"></textarea>
                <div class="compose-bottom">
                    <span id="compose-count" class="char-counter">140</span>
                    <button type="submit">update</button>
                </div>
            </form>
            <form action="/search" method="get" class="header-search">
                <input type="text" name="q" placeholder="Search" value="<?= Helpers::h($_GET['q'] ?? '') ?>">
            </form>
        <?php else: ?>
            <div class="guest-cta">
                <strong>What are you doing?</strong>
                <span>Join Twitkey to post short updates and follow public conversations.</span>
                <a href="/register">Join today!</a>
                <a href="/login">Sign in</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="page-wrap<?= !empty($hideSidebar) ? ' page-wrap-centered' : '' ?>">
    <main class="main-col<?= !empty($hideSidebar) ? ' main-col-centered' : '' ?>">
        <?php foreach (Session::consumeFlash() as $flash): ?>
            <div class="flash flash-<?= Helpers::h($flash['type']) ?>"><?= Helpers::h($flash['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>

    <?php if (empty($hideSidebar)): ?>
        <aside class="sidebar">
            <?php if ($currentUser): ?>
                <section class="side-box profile-mini">
                    <div class="side-header">@<?= Helpers::h($currentUser['username']) ?> <a href="/settings">edit profile</a></div>
                    <div class="profile-mini-body">
                        <img src="<?= Helpers::avatarUrl($currentUser) ?>" alt="" class="profile-mini-avatar">
                        <p><?= Helpers::h($currentUser['bio'] ?: 'No bio yet.') ?></p>
                        <div class="mini-stats">
                            <a href="/<?= Helpers::h($currentUser['username']) ?>/following">Following: <?= (int)$currentUser['following_count'] ?></a>
                            <a href="/<?= Helpers::h($currentUser['username']) ?>/followers">Followers: <?= (int)$currentUser['follower_count'] ?></a>
                        </div>
                        <?php if ($unread > 0): ?>
                            <a href="/notifications" class="unread-link"><?= $unread ?> new notification<?= $unread === 1 ? '' : 's' ?></a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="side-box">
                <div class="side-header">Trending Topics</div>
                <?php if (($sidebar['trends'] ?? []) === []): ?>
                    <div class="side-empty">No trends yet.</div>
                <?php else: ?>
                    <ol class="trend-list">
                        <?php foreach ($sidebar['trends'] as $trend): ?>
                            <li><a href="/search?q=%23<?= rawurlencode($trend['tag']) ?>">#<?= Helpers::h($trend['tag']) ?></a> <span>(<?= number_format((int)$trend['count']) ?> tweets)</span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="side-box">
                <div class="side-header">Who to Follow</div>
                <?php if (($sidebar['suggestions'] ?? []) === []): ?>
                    <div class="side-empty">No suggestions yet.</div>
                <?php else: ?>
                    <?php foreach ($sidebar['suggestions'] as $suggestion): ?>
                        <div class="suggestion">
                            <img src="<?= Helpers::avatarUrl($suggestion) ?>" alt="" class="suggestion-avatar">
                            <div class="suggestion-body">
                                <?= Helpers::renderUserName($suggestion) ?>
                                <div class="suggestion-bio"><?= Helpers::h(Helpers::truncate((string)$suggestion['bio'], 40)) ?></div>
                                <?php if ($currentUser): ?>
                                    <form action="/follow/<?= Helpers::h($suggestion['username']) ?>" method="post" data-follow-form>
                                        <?= Helpers::csrfField() ?>
                                        <button class="mini-button" type="submit">Follow</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="side-footer">
                <a href="/public">Public Timeline</a> ·
                <a href="/search">Search</a> ·
                <a href="/notes/pending">Community Notes</a>
            </section>
        </aside>
    <?php endif; ?>
</div>

<script src="/js/twitkey.js"></script>
</body>
</html>
