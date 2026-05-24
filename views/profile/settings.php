<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Settings</h1>
</div>
<form action="/settings" method="post" enctype="multipart/form-data" class="settings-form">
    <?= Helpers::csrfField() ?>
    <label>Full name
        <input type="text" name="display_name" maxlength="80" value="<?= Helpers::h($user['display_name']) ?>" required>
    </label>
    <label>Bio
        <textarea name="bio" maxlength="160"><?= Helpers::h($user['bio']) ?></textarea>
    </label>
    <label>Location
        <input type="text" name="location" maxlength="80" value="<?= Helpers::h($user['location']) ?>">
    </label>
    <label>Website
        <input type="url" name="website" maxlength="120" value="<?= Helpers::h($user['website']) ?>">
    </label>
    <label>Avatar
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
    </label>
    <button type="submit" class="primary-button">Save settings</button>
</form>

<div class="content-header secondary-heading">
    <h1>Affiliated Accounts</h1>
</div>
<?php if (($user['verified_type'] ?? null) === 'business'): ?>
    <form action="/settings/affiliations" method="post" class="settings-form compact-form">
        <?= Helpers::csrfField() ?>
        <input type="hidden" name="action" value="invite">
        <label>Invite @username
            <input type="text" name="username" maxlength="16" placeholder="@username">
        </label>
        <button type="submit" class="primary-button">Send Affiliation Invite</button>
    </form>
    <h2>Sent invites</h2>
    <?php foreach ($sentAffiliations as $aff): ?>
        <div class="user-result">
            <img src="<?= Helpers::avatarUrl($aff) ?>" class="small-avatar" alt="">
            <div><?= Helpers::renderUserName($aff) ?><div class="muted">Status: <?= Helpers::h($aff['status']) ?></div></div>
            <?php if ($aff['status'] !== 'revoked'): ?>
                <form action="/settings/affiliations" method="post">
                    <?= Helpers::csrfField() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="affiliation_id" value="<?= (int)$aff['id'] ?>">
                    <button type="submit" class="mini-button">Revoke</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Pending invites</h2>
<?php if ($pendingAffiliations === []): ?>
    <div class="empty-state">No pending affiliation invites.</div>
<?php else: ?>
    <?php foreach ($pendingAffiliations as $aff): ?>
        <div class="user-result">
            <img src="<?= Helpers::avatarUrl($aff) ?>" class="small-avatar" alt="">
            <div><?= Helpers::renderUserName($aff) ?><div class="muted">@<?= Helpers::h($aff['username']) ?> wants to affiliate with you.</div></div>
            <form action="/settings/affiliations" method="post" class="button-row">
                <?= Helpers::csrfField() ?>
                <input type="hidden" name="affiliation_id" value="<?= (int)$aff['id'] ?>">
                <button type="submit" name="action" value="accept" class="mini-button">Accept</button>
                <button type="submit" name="action" value="decline" class="mini-button">Decline</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
