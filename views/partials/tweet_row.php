<?php
use Twitkey\Core\Auth;
use Twitkey\Core\Helpers;
use Twitkey\Models\Tweet;

$currentUser = $currentUser ?? Auth::user();
$author = [
    'id' => $tweet['user_id'] ?? $tweet['id'] ?? 0,
    'username' => $tweet['username'] ?? '',
    'display_name' => $tweet['display_name'] ?? $tweet['username'] ?? '',
    'avatar' => $tweet['avatar'] ?? null,
    'is_admin' => $tweet['is_admin'] ?? 0,
    'is_system' => $tweet['is_system'] ?? 0,
    'is_verified' => $tweet['is_verified'] ?? 0,
    'verified_type' => $tweet['verified_type'] ?? null,
];
$deleted = (int)($tweet['is_deleted'] ?? 0) === 1;
$tweetId = (int)($tweet['id'] ?? 0);
$favorited = $currentUser ? Tweet::isFavorited((int)$currentUser['id'], $tweetId) : false;
?>
<article class="tweet-row<?= $deleted ? ' tweet-row-deleted' : '' ?>" id="tweet-<?= $tweetId ?>" data-tweet-id="<?= $tweetId ?>">
    <a href="/<?= Helpers::h($author['username']) ?>"><img src="<?= Helpers::avatarUrl($author) ?>" class="tweet-avatar" alt=""></a>
    <div class="tweet-content">
        <?php if ($deleted): ?>
            <span class="tweet-body deleted-text">[Tweet deleted]</span>
        <?php else: ?>
            <strong><?= Helpers::renderUserName($author) ?></strong>
            <?php if ((string)$tweet['body'] !== ''): ?>
                <span class="tweet-body"><?= Helpers::renderTweetBody((string)$tweet['body']) ?></span>
            <?php endif; ?>
            <?php if (!empty($tweet['approved_note_body'])): ?>
                <a class="note-preview" href="/tweet/<?= $tweetId ?>" title="<?= Helpers::h(Helpers::truncate((string)$tweet['approved_note_body'], 120)) ?>">📋</a>
            <?php endif; ?>
            <?php if (!empty($tweet['gif_url'])): ?>
                <div class="tweet-gif"><img src="<?= Helpers::h($tweet['gif_url']) ?>" alt="GIF attachment" loading="lazy"></div>
            <?php endif; ?>
            <?php if (!empty($tweet['media'])): ?>
                <div class="tweet-media media-count-<?= count($tweet['media']) ?>">
                    <?php foreach ($tweet['media'] as $media): ?>
                        <a href="/media/<?= rawurlencode((string)$media['file_name']) ?>" target="_blank" rel="noopener">
                            <img src="/media/<?= rawurlencode((string)$media['file_name']) ?>" alt="Tweet attachment" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($tweet['poll'])): ?>
                <?php $poll = $tweet['poll']; $totalVotes = max(0, (int)$poll['total_votes']); ?>
                <div class="tweet-poll">
                    <div class="poll-question"><?= Helpers::h($poll['question']) ?></div>
                    <?php foreach ($poll['options'] as $option): ?>
                        <?php $votes = (int)$option['vote_count']; $percent = $totalVotes > 0 ? (int)round(($votes / $totalVotes) * 100) : 0; ?>
                        <form action="/tweet/<?= $tweetId ?>/poll/<?= (int)$option['id'] ?>" method="post" class="poll-option-form" data-poll-form>
                            <?= Helpers::csrfField() ?>
                            <button type="submit" class="poll-option"<?= !$currentUser ? ' disabled' : '' ?>>
                                <span class="poll-fill" style="width: <?= $percent ?>%"></span>
                                <span class="poll-text"><?= Helpers::h($option['body']) ?></span>
                                <span class="poll-percent"><?= $percent ?>%</span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <div class="poll-meta"><?= number_format($totalVotes) ?> vote<?= $totalVotes === 1 ? '' : 's' ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($tweet['location_label']) && $tweet['location_lat'] !== null && $tweet['location_lng'] !== null): ?>
                <div class="tweet-location">
                    <img src="/img/icon_location.svg" alt="">
                    <a href="https://www.openstreetmap.org/?mlat=<?= rawurlencode((string)$tweet['location_lat']) ?>&mlon=<?= rawurlencode((string)$tweet['location_lng']) ?>#map=12/<?= rawurlencode((string)$tweet['location_lat']) ?>/<?= rawurlencode((string)$tweet['location_lng']) ?>" target="_blank" rel="noopener">
                        <?= Helpers::h($tweet['location_label']) ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="tweet-meta">
            <a href="/tweet/<?= $tweetId ?>"><?= Helpers::timeAgo((string)$tweet['created_at']) ?></a>
            <?php if (!$deleted): ?>
                · <a href="/tweet/<?= $tweetId ?>" class="tweet-action" data-reply-toggle>reply</a>
                <?php if ($currentUser): ?>
                    · <form action="/tweet/<?= $tweetId ?>/retweet" method="post" class="inline-form" data-retweet-form>
                        <?= Helpers::csrfField() ?><button type="submit" class="tweet-action">retweet</button>
                    </form>
                    · <form action="/tweet/<?= $tweetId ?>/favorite" method="post" class="inline-form" data-favorite-form>
                        <?= Helpers::csrfField() ?><button type="submit" class="tweet-action<?= $favorited ? ' is-favorited' : '' ?>"><?= $favorited ? 'favorited' : 'favorite' ?></button>
                    </form>
                <?php endif; ?>
                · <span><span class="reply-count"><?= (int)$tweet['reply_count'] ?></span> replies</span>
                · <span><span class="retweet-count"><?= (int)$tweet['retweet_count'] ?></span> retweets</span>
                · <span><span class="favorite-count"><?= (int)$tweet['favorite_count'] ?></span> favorites</span>
                <?php if ($currentUser && ((int)$currentUser['id'] === (int)$tweet['user_id'] || (int)$currentUser['is_admin'] === 1)): ?>
                    · <button class="tweet-action delete-action" data-delete-url="/tweet/<?= $tweetId ?>">delete</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($currentUser && !$deleted): ?>
            <form action="/tweet/<?= $tweetId ?>/reply" method="post" class="inline-reply" data-reply-form>
                <?= Helpers::csrfField() ?>
                <textarea name="body" maxlength="140" placeholder="Reply to @<?= Helpers::h($author['username']) ?>"></textarea>
                <button type="submit">reply</button>
            </form>
        <?php endif; ?>
    </div>
</article>
