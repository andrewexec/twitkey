<?php
use Twitkey\Core\Helpers;

$appName = Helpers::env('APP_NAME', 'Twitkey');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Mode / <?= Helpers::h($appName) ?></title>
    <link rel="stylesheet" href="/css/twitkey.css">
</head>
<body class="maintenance-page">
    <main class="maintenance-card" role="main">
        <div class="suspension-stamp">Maintenance Mode</div>
        <h1><?= Helpers::h($appName) ?> is temporarily unavailable</h1>
        <p>
            The site is currently in maintenance mode while the owner performs updates.
            Only the owner account can access <?= Helpers::h($appName) ?> until maintenance mode is disabled.
        </p>
        <?php if ($user): ?>
            <a class="primary-button logout-button" href="/logout">Log out</a>
        <?php else: ?>
            <a class="primary-button logout-button" href="/login">Owner sign in</a>
        <?php endif; ?>
    </main>
</body>
</html>
