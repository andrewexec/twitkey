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
<table class="admin-table">
    <thead>
    <tr><th>ID</th><th>Avatar</th><th>Username</th><th>Role</th><th>Verified</th><th>Admin</th><th>Suspended</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= (int)$user['id'] ?></td>
            <td><img src="<?= Helpers::avatarUrl($user) ?>" class="tiny-avatar" alt=""></td>
            <td><?= Helpers::renderUserName($user) ?><div class="muted">@<?= Helpers::h($user['username']) ?></div></td>
            <td><?= Helpers::h($user['role']) ?></td>
            <td><?= Helpers::h($user['verified_type'] ?: '-') ?></td>
            <td><?= (int)$user['is_admin'] === 1 ? 'yes' : 'no' ?></td>
            <td><?= (int)$user['is_suspended'] === 1 ? 'yes' : 'no' ?></td>
            <td><?= Helpers::h($user['created_at']) ?></td>
            <td>
                <form action="/admin/users/<?= (int)$user['id'] ?>/action" method="post" class="admin-actions">
                    <?= Helpers::csrfField() ?>
                    <button name="action" value="<?= (int)$user['is_admin'] === 1 ? 'revoke_admin' : 'grant_admin' ?>"><?= (int)$user['is_admin'] === 1 ? 'Revoke Admin' : 'Grant Admin' ?></button>
                    <button name="action" value="verify_business">Verify Business</button>
                    <button name="action" value="verify_government">Verify Government</button>
                    <button name="action" value="remove_verification">Remove Verification</button>
                    <button name="action" value="<?= (int)$user['is_suspended'] === 1 ? 'unsuspend' : 'suspend' ?>"><?= (int)$user['is_suspended'] === 1 ? 'Unsuspend' : 'Suspend' ?></button>
                    <button name="action" value="delete" data-confirm="Delete this account?">Delete Account</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
