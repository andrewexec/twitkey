<?php use Twitkey\Core\Helpers; ?>
<nav class="admin-nav">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Manage Users</a>
    <a href="/admin/tweets" class="active">Manage Tweets</a>
    <a href="/admin/notes">Community Notes</a>
</nav>
<div class="content-header">
    <h1>Manage Tweets</h1>
</div>
<table class="admin-table">
    <thead><tr><th>Preview</th><th>Author</th><th>Date</th><th>Flags</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($tweets as $tweet): ?>
        <tr>
            <td><?= Helpers::h(Helpers::truncate((string)$tweet['body'], 100)) ?></td>
            <td>@<?= Helpers::h($tweet['username']) ?></td>
            <td><?= Helpers::h($tweet['created_at']) ?></td>
            <td><?= (int)$tweet['is_deleted'] === 1 ? 'deleted' : 'active' ?><?= $tweet['approved_note_body'] ? ' · noted' : '' ?></td>
            <td>
                <form action="/admin/tweets/<?= (int)$tweet['id'] ?>/action" method="post" class="admin-actions">
                    <?= Helpers::csrfField() ?>
                    <a href="/tweet/<?= (int)$tweet['id'] ?>">View</a>
                    <button name="action" value="delete">Delete</button>
                    <button name="action" value="remove_note">Remove Community Note</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
