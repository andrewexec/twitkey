<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Notifications</h1>
</div>
<?php foreach ($pendingAffiliations as $aff): ?>
    <div class="notification-row">
        <img src="<?= Helpers::avatarUrl($aff) ?>" class="small-avatar" alt="">
        <div>
            <?= Helpers::renderUserName($aff) ?> has invited you to affiliate with their account.
            <form action="/settings/affiliations" method="post" class="button-row">
                <?= Helpers::csrfField() ?>
                <input type="hidden" name="affiliation_id" value="<?= (int)$aff['id'] ?>">
                <button type="submit" name="action" value="accept" class="mini-button">Accept</button>
                <button type="submit" name="action" value="decline" class="mini-button">Decline</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($notifications === [] && $pendingAffiliations === []): ?>
    <div class="empty-state">No notifications yet.</div>
<?php else: ?>
    <?php foreach ($notifications as $n): ?>
        <?php $actor = ['id' => $n['actor_id'], 'username' => $n['actor_username'], 'display_name' => $n['actor_display_name'], 'avatar' => $n['actor_avatar'], 'is_admin' => $n['actor_is_admin'], 'is_system' => $n['actor_is_system'], 'is_verified' => $n['actor_is_verified'], 'verified_type' => $n['actor_verified_type']]; ?>
        <div class="notification-row<?= (int)$n['is_read'] === 0 ? ' unread' : '' ?>">
            <img src="<?= Helpers::avatarUrl($actor) ?>" class="small-avatar" alt="">
            <div>
                <?= Helpers::renderUserName($actor) ?>
                <?php if ($n['type'] === 'follow'): ?>followed you.
                <?php elseif ($n['type'] === 'favorite'): ?>favorited your tweet.
                <?php elseif ($n['type'] === 'retweet'): ?>retweeted you.
                <?php elseif ($n['type'] === 'reply'): ?>replied to you.
                <?php elseif ($n['type'] === 'mention'): ?>mentioned you.
                <?php elseif ($n['type'] === 'affiliation_invite'): ?>sent an affiliation invite.
                <?php else: ?>created an admin-review notification.
                <?php endif; ?>
                <?php if ($n['tweet_id']): ?><a href="/tweet/<?= (int)$n['tweet_id'] ?>">view tweet</a><?php endif; ?>
                <div class="muted"><?= Helpers::timeAgo($n['created_at']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
