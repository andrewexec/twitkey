<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1><?= Helpers::h($heading ?? 'Timeline') ?></h1>
</div>
<div class="timeline" id="timeline">
    <?php if ($tweets === []): ?>
        <div class="empty-state">No tweets yet.</div>
    <?php else: ?>
        <?php foreach ($tweets as $tweet): ?>
            <?= Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $currentUser]) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="pagination">
    <?php if (($page ?? 1) > 1): ?>
        <a href="<?= Helpers::h($basePath ?? '/') ?>?page=<?= (int)$page - 1 ?>">newer</a>
    <?php endif; ?>
    <?php if (count($tweets) >= 20): ?>
        <a href="<?= Helpers::h($basePath ?? '/') ?>?page=<?= (int)$page + 1 ?>">older</a>
    <?php endif; ?>
</div>
