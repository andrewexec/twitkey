<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Privacy Policy</h1>
</div>
<section class="static-page">
    <p><?= Helpers::h(Helpers::env('APP_NAME', 'Twitkey')) ?> is a self-hosted social network. The operator of this installation controls its server, database, backups, and logs.</p>

    <h2>Information Stored</h2>
    <p>Twitkey stores account details, profile text, uploaded media, posts, polls, votes, follows, follow requests, direct messages, notifications, community notes, moderation actions, and privacy settings needed to run the service.</p>

    <h2>Visibility Controls</h2>
    <p>Public posts can be viewed by anyone. Follower-only and private-account posts are limited to approved followers and administrators. Direct messages are visible to conversation participants and administrators with database access to the self-hosted server.</p>

    <h2>Uploads</h2>
    <p>Uploaded avatars, banners, and post media are stored on the server data volume. Twitkey validates file types before saving uploads and serves them through local media URLs.</p>

    <h2>Security</h2>
    <p>Passwords are stored as bcrypt hashes. Forms use CSRF tokens, user content is escaped before display, and database access uses prepared statements. Browser inspection cannot be fully prevented by any website; server-side validation and escaping are the actual security boundary.</p>

    <h2>Data Removal</h2>
    <p>Administrators can delete accounts and soft-delete posts. The server operator remains responsible for deleting backups or exported data outside the application database.</p>
</section>
