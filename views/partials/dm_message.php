<?php
use Twitkey\Core\Helpers;

$sender = [
    'id' => $message['sender_id'],
    'username' => $message['sender_username'],
    'display_name' => $message['sender_display_name'],
    'avatar' => $message['sender_avatar'],
    'is_admin' => $message['sender_is_admin'],
    'is_system' => $message['sender_is_system'],
    'is_verified' => $message['sender_is_verified'],
    'is_private' => $message['sender_is_private'],
    'verified_type' => $message['sender_verified_type'],
];
?>
<div class="dm-message<?= (int)$message['sender_id'] === (int)$currentUser['id'] ? ' mine' : '' ?>" data-message-id="<?= (int)$message['id'] ?>">
    <span class="avatar-frame small-avatar-frame">
        <img src="<?= Helpers::avatarUrl($sender) ?>" class="small-avatar" alt="">
        <?= Helpers::adminAvatarBadge($sender) ?>
    </span>
    <div>
        <?= Helpers::renderUserName($sender) ?>
        <p><?= Helpers::h($message['body']) ?></p>
        <span><?= Helpers::timeAgo($message['created_at']) ?></span>
    </div>
</div>
