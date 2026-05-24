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
                    <img src="<?= Helpers::avatarUrl($conversation) ?>" class="dm-avatar" alt="">
                    <span><?= Helpers::h($conversation['display_name'] ?: $conversation['username']) ?><?= Helpers::renderBadges($conversation) ?><?= (int)($conversation['is_private'] ?? 0) === 1 ? ' <span class="lock-badge" title="Private account">🔒</span>' : '' ?></span>
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
            <div class="dm-messages" data-dm-thread>
                <?php foreach ($messages as $message): ?>
                    <?php $sender = ['id' => $message['sender_id'], 'username' => $message['sender_username'], 'display_name' => $message['sender_display_name'], 'avatar' => $message['sender_avatar'], 'is_admin' => $message['sender_is_admin'], 'is_system' => $message['sender_is_system'], 'is_verified' => $message['sender_is_verified'], 'is_private' => $message['sender_is_private'], 'verified_type' => $message['sender_verified_type']]; ?>
                    <div class="dm-message<?= (int)$message['sender_id'] === (int)$currentUser['id'] ? ' mine' : '' ?>">
                        <img src="<?= Helpers::avatarUrl($sender) ?>" class="small-avatar" alt="">
                        <div>
                            <?= Helpers::renderUserName($sender) ?>
                            <p><?= Helpers::h($message['body']) ?></p>
                            <span><?= Helpers::timeAgo($message['created_at']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($canMessageSelected): ?>
                <form action="/direct_messages/<?= Helpers::h($selected['username']) ?>" method="post" class="dm-form">
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
