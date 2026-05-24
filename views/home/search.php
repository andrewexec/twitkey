<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Search</h1>
</div>
<form action="/search" method="get" class="search-page-form">
    <input type="text" name="q" value="<?= Helpers::h($query) ?>" placeholder="Search tweets or users">
    <button type="submit" class="primary-button">Search</button>
</form>

<?php if ($query !== ''): ?>
    <section class="user-results">
        <h2>People matching “<?= Helpers::h($query) ?>”</h2>
        <?php if ($users === []): ?>
            <div class="empty-state">No users found.</div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <div class="user-result">
                    <img src="<?= Helpers::avatarUrl($user) ?>" class="small-avatar" alt="">
                    <div>
                        <?= Helpers::renderUserName($user) ?> <?= Helpers::followsYouBadge($user) ?>
                        <div class="muted">@<?= Helpers::h($user['username']) ?> · <?= Helpers::h(Helpers::truncate((string)$user['bio'], 80)) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section>
        <h2>Tweets matching “<?= Helpers::h($query) ?>”</h2>
        <div class="timeline">
            <?php if ($tweets === []): ?>
                <div class="empty-state">No tweets found.</div>
            <?php else: ?>
                <?php foreach ($tweets as $tweet): ?>
                    <?= Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $currentUser]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
