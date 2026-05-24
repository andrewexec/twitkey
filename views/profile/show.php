<?php
use Twitkey\Core\Helpers;
$bannerUrl = Helpers::bannerUrl($profile);
?>
<section class="profile-header<?= (int)$profile['is_admin'] === 1 ? ' admin-profile' : '' ?><?= $bannerUrl ? ' has-banner' : '' ?>"<?= $bannerUrl ? ' style="--profile-banner: url(' . Helpers::h($bannerUrl) . ');"' : '' ?>>
    <?php if ($bannerUrl): ?>
        <div class="profile-banner" aria-hidden="true"></div>
    <?php endif; ?>
    <?php if ((int)$profile['is_suspended'] === 1): ?>
        <div class="suspended-banner">This account has been suspended.</div>
    <?php endif; ?>
    <img src="<?= Helpers::avatarUrl($profile) ?>" class="profile-avatar" alt="">
    <div class="profile-info">
        <h1><?= Helpers::h($profile['display_name']) ?> <?= Helpers::renderBadges($profile) ?></h1>
        <div class="profile-username">@<?= Helpers::h($profile['username']) ?></div>
        <p><?= Helpers::h($profile['bio']) ?></p>
        <div class="profile-meta">
            <?php if ($profile['website']): ?><span>Web: <a href="<?= Helpers::h($profile['website']) ?>" rel="nofollow noopener" target="_blank"><?= Helpers::h($profile['website']) ?></a></span><?php endif; ?>
            <?php if ($profile['location']): ?><span>Location: <?= Helpers::h($profile['location']) ?></span><?php endif; ?>
        </div>
        <div class="profile-stats">
            <a href="/<?= Helpers::h($profile['username']) ?>/following">Following: <?= (int)$profile['following_count'] ?></a>
            <a href="/<?= Helpers::h($profile['username']) ?>/followers">Followers: <?= (int)$profile['follower_count'] ?></a>
            <span>Tweets: <?= (int)$profile['tweet_count'] ?></span>
        </div>
    </div>
    <div class="profile-action">
        <?php if ($isOwn): ?>
            <a href="/settings" class="secondary-button">Edit Profile</a>
        <?php elseif ($currentUser): ?>
            <form action="/follow/<?= Helpers::h($profile['username']) ?>" method="post" data-follow-form>
                <?= Helpers::csrfField() ?>
                <button type="submit" class="primary-button"><?= $isFollowing ? 'Unfollow' : 'Follow' ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>

<nav class="tabs">
    <a class="<?= $tab === 'tweets' ? 'active' : '' ?>" href="/<?= Helpers::h($profile['username']) ?>">Tweets</a>
    <a class="<?= $tab === 'replies' ? 'active' : '' ?>" href="/<?= Helpers::h($profile['username']) ?>?tab=replies">Replies</a>
    <a class="<?= $tab === 'favorites' ? 'active' : '' ?>" href="/<?= Helpers::h($profile['username']) ?>?tab=favorites">Favorites</a>
</nav>

<div class="timeline">
    <?php if ($tweets === []): ?>
        <div class="empty-state">No tweets to show.</div>
    <?php else: ?>
        <?php foreach ($tweets as $tweet): ?>
            <?= Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $currentUser]) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
