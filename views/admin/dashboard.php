<?php use Twitkey\Core\Helpers; ?>
<nav class="admin-nav">
    <a href="/admin" class="active">Dashboard</a>
    <a href="/admin/users">Manage Users</a>
    <a href="/admin/tweets">Manage Tweets</a>
    <a href="/admin/notes">Community Notes</a>
</nav>
<div class="content-header">
    <h1>Twitkey Admin Panel</h1>
</div>
<div class="admin-stats">
    <div>Total Users: <strong><?= number_format((int)$stats['users']) ?></strong></div>
    <div>Tweets Today: <strong><?= number_format((int)$stats['tweets_today']) ?></strong></div>
    <div>Active: <strong><?= number_format((int)$stats['active']) ?></strong></div>
    <div>Pending Notes: <strong><?= number_format((int)$stats['pending_notes']) ?></strong></div>
    <div>Suspended: <strong><?= number_format((int)$stats['suspended']) ?></strong></div>
</div>
<h2>Recent Admin Actions</h2>
<?php if ($logs === []): ?>
    <div class="empty-state">No admin actions logged.</div>
<?php else: ?>
    <table class="admin-table">
        <thead><tr><th>Admin</th><th>Action</th><th>Target</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td>@<?= Helpers::h($log['username']) ?></td>
                <td><?= Helpers::h($log['action']) ?></td>
                <td><?= Helpers::h($log['target_type']) ?> #<?= (int)$log['target_id'] ?></td>
                <td><?= Helpers::h($log['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
