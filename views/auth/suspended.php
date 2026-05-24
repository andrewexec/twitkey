<?php
use Twitkey\Core\Helpers;

$appName = Helpers::env('APP_NAME', 'Twitkey');
$isDeleted = (int)($user['is_deleted'] ?? 0) === 1;
$reviewedAt = trim((string)($user['moderation_reviewed_at'] ?? $user['updated_at'] ?? ''));
$reason = trim((string)($user['moderation_reason'] ?? $user['suspension_reason'] ?? ''));
if ($reason === '') {
    $reason = $isDeleted ? 'This account was deleted after moderator review.' : 'This account broke the Twitkey Terms of Service.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::h($isDeleted ? 'Account Deleted' : 'Account Suspended') ?> / <?= Helpers::h($appName) ?></title>
    <link rel="stylesheet" href="/css/twitkey.css">
</head>
<body class="suspension-page">
    <main class="suspension-card" role="main">
        <div class="suspension-stamp"><?= Helpers::h($isDeleted ? 'Account Deleted' : 'Account Suspended') ?></div>
        <h1><?= Helpers::h($isDeleted ? 'Account Deleted' : 'Account Suspended') ?></h1>
        <dl>
            <dt>Reviewed at:</dt>
            <dd><?= Helpers::h($reviewedAt !== '' ? $reviewedAt : 'Not recorded') ?></dd>
            <dt>Reason:</dt>
            <dd><?= Helpers::h($reason) ?></dd>
        </dl>
        <p class="moderation-note">
            Automated Moderation note: during registration, all users are required to read and accept the Terms of Service of <?= Helpers::h($appName) ?>.
        </p>
        <p class="appeal-note">
            If you believe this is a mistake, please <a href="https://x.com/m5rcode" rel="noopener" target="_blank">click here</a>.
        </p>
        <a class="primary-button logout-button" href="/logout">Log out</a>
    </main>
</body>
</html>
