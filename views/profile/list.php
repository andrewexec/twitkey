<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>@<?= Helpers::h($profile['username']) ?> <?= Helpers::h($kind) ?></h1>
</div>
<?php if ($users === []): ?>
    <div class="empty-state">No users to show.</div>
<?php else: ?>
    <?php foreach ($users as $user): ?>
        <div class="user-result">
            <img src="<?= Helpers::avatarUrl($user) ?>" class="small-avatar" alt="">
            <div>
                <?= Helpers::renderUserName($user) ?> <?= Helpers::followsYouBadge($user) ?>
                <div class="muted">@<?= Helpers::h($user['username']) ?> · <?= Helpers::h(Helpers::truncate((string)$user['bio'], 80)) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
