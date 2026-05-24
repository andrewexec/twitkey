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
                    <span><?= Helpers::h($conversation['username']) ?></span>
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
                    <?php $sender = ['id' => $message['sender_id'], 'username' => $message['sender_username'], 'display_name' => $message['sender_display_name'], 'avatar' => $message['sender_avatar'], 'is_admin' => $message['sender_is_admin'], 'is_system' => $message['sender_is_system'], 'is_verified' => $message['sender_is_verified'], 'verified_type' => $message['sender_verified_type']]; ?>
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
            <form action="/direct_messages/<?= Helpers::h($selected['username']) ?>" method="post" class="dm-form">
                <?= Helpers::csrfField() ?>
                <textarea name="body" maxlength="1000" required></textarea>
                <button type="submit" class="primary-button">Send</button>
            </form>
        <?php endif; ?>
    </section>
</div>
