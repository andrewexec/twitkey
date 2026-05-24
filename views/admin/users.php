<?php use Twitkey\Core\Helpers; ?>
<nav class="admin-nav">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users" class="active">Manage Users</a>
    <a href="/admin/tweets">Manage Tweets</a>
    <a href="/admin/notes">Community Notes</a>
</nav>
<div class="content-header">
    <h1>Manage Users</h1>
</div>
<table class="admin-table admin-users-table">
    <thead>
    <tr><th>ID</th><th>Avatar</th><th>Username</th><th>Role</th><th>Verified</th><th>Admin</th><th>Suspended</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
<?php foreach ($users as $user): ?>
        <?php $verifiedLabel = $user['verified_type'] ?: ((int)($user['is_verified'] ?? 0) === 1 ? 'normal' : '-'); ?>
        <tr>
            <td><?= (int)$user['id'] ?></td>
            <td>
                <span class="avatar-frame tiny-avatar-frame">
                    <img src="<?= Helpers::avatarUrl($user) ?>" class="tiny-avatar" alt="">
                    <?= Helpers::adminAvatarBadge($user) ?>
                </span>
            </td>
            <td><?= Helpers::renderUserName($user) ?><div class="muted">@<?= Helpers::h($user['username']) ?></div></td>
            <td><?= Helpers::h($user['role']) ?></td>
            <td><?= Helpers::h($verifiedLabel) ?></td>
            <td><?= (int)$user['is_admin'] === 1 ? 'yes' : 'no' ?></td>
            <td>
                <?= (int)$user['is_suspended'] === 1 ? 'yes' : 'no' ?>
                <?php if (!empty($user['suspension_reason'])): ?>
                    <div class="muted"><?= Helpers::h($user['suspension_reason']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= Helpers::h($user['created_at']) ?></td>
            <td>
                <form action="/admin/users/<?= (int)$user['id'] ?>/action" method="post" class="admin-actions">
                    <?= Helpers::csrfField() ?>
                    <button name="action" value="<?= (int)$user['is_admin'] === 1 ? 'revoke_admin' : 'grant_admin' ?>"><?= (int)$user['is_admin'] === 1 ? 'Revoke Admin' : 'Grant Admin' ?></button>
                    <button name="action" value="verify_normal">Verify Normal</button>
                    <button name="action" value="verify_business">Verify Business</button>
                    <button name="action" value="verify_government">Verify Government</button>
                    <button name="action" value="remove_verification">Remove Verification</button>
                    <input type="text" name="suspension_reason" maxlength="240" placeholder="Suspension reason">
                    <button name="action" value="<?= (int)$user['is_suspended'] === 1 ? 'unsuspend' : 'suspend' ?>"><?= (int)$user['is_suspended'] === 1 ? 'Unsuspend' : 'Ban Account' ?></button>
                    <button name="action" value="delete" data-confirm="Delete this account?">Delete Account</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
