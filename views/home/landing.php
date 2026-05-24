<?php
use Twitkey\Core\Helpers;
?>
<section class="landing-hero">
    <div class="landing-bird-wrap">
        <img src="/img/logo.svg" class="landing-bird" alt="">
    </div>
    <h1>Welcome to <?= Helpers::h(Helpers::env('APP_NAME', 'Twitkey')) ?></h1>
    <p>Share short updates, follow people, and keep track of the public conversation in a classic 2009-style timeline.</p>
    <div class="landing-actions">
        <a href="/register" class="primary-button landing-primary">Join now</a>
        <a href="/public" class="secondary-button">View public timeline</a>
    </div>
    <div class="landing-stats">
        <span><?= number_format((int)$stats['users']) ?> users</span>
        <span><?= number_format((int)$stats['tweets']) ?> tweets</span>
    </div>
</section>

<section class="landing-login">
    <h2>Sign in</h2>
    <form action="/login" method="post" class="landing-login-form">
        <?= Helpers::csrfField() ?>
        <input type="text" name="login" placeholder="Username or email" autocomplete="username" required>
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <button type="submit" class="primary-button">Sign in</button>
    </form>
</section>

<section class="landing-preview">
    <div class="content-header">
        <h1>Latest public updates</h1>
    </div>
    <?php if ($latestTweets === []): ?>
        <div class="empty-state">No public tweets yet. Join now and be the first to post.</div>
    <?php else: ?>
        <?php foreach (array_slice($latestTweets, 0, 5) as $tweet): ?>
            <?= Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => null]) ?>
        <?php endforeach; ?>
        <div class="landing-more">
            <a href="/public">older public updates</a>
        </div>
    <?php endif; ?>
</section>
