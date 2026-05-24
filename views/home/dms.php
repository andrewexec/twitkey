<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Direct Messages</h1>
</div>
<div class="dm-layout">
    <aside class="dm-list">
        <?php if ($conversations === []): ?>
            <div class="side-empty">No conversations.</div>
        <?php else: ?>
            <?php foreach ($conversations as $conversation): ?>
                <a class="dm-conversation<?= $selected && (int)$selected['id'] === (int)$conversation['id'] ? ' active' : '' ?>" href="/direct_messages?user=<?= Helpers::h($conversation['username']) ?>">
                    <span class="avatar-frame dm-avatar-frame">
                        <img src="<?= Helpers::avatarUrl($conversation) ?>" class="dm-avatar" alt="">
                        <?= Helpers::adminAvatarBadge($conversation) ?>
                    </span>
                    <span><?= Helpers::h($conversation['display_name'] ?: $conversation['username']) ?><?= Helpers::renderBadges($conversation) ?><?= (int)($conversation['is_private'] ?? 0) === 1 ? ' <span class="lock-badge tooltip-wrap" data-tooltip="Private account">🔒</span>' : '' ?></span>
                    <?php if ((int)$conversation['unread_count'] > 0): ?><b><?= (int)$conversation['unread_count'] ?></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>
    <section class="dm-thread">
        <?php if (!$selected): ?>
            <div class="empty-state">Select a conversation.</div>
        <?php else: ?>
            <h2>Conversation with @<?= Helpers::h($selected['username']) ?></h2>
            <div class="dm-stats">
                <span data-dm-message-count><?= number_format((int)$messageCount) ?></span> message<?= (int)$messageCount === 1 ? '' : 's' ?> in this conversation ·
                you sent <?= number_format((int)$sentCount) ?>
            </div>
            <div class="dm-messages" data-dm-thread data-dm-user="<?= Helpers::h($selected['username']) ?>">
                <?php foreach ($messages as $message): ?>
                    <?= Helpers::renderPartial('partials/dm_message', ['message' => $message, 'currentUser' => $currentUser]) ?>
                <?php endforeach; ?>
            </div>
            <?php if ($canMessageSelected): ?>
                <form action="/direct_messages/<?= Helpers::h($selected['username']) ?>" method="post" class="dm-form" data-dm-form>
                    <?= Helpers::csrfField() ?>
                    <textarea name="body" maxlength="1000" required></textarea>
                    <button type="submit" class="primary-button">Send</button>
                </form>
            <?php else: ?>
                <div class="empty-state">You can message this user only when their privacy settings allow it.</div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
