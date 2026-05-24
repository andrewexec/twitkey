<?php use Twitkey\Core\Helpers; ?>
<div class="content-header">
    <h1>Help</h1>
</div>
<section class="static-page">
    <h2>Getting Started</h2>
    <p>Post short updates from the “What are you doing?” box. Posts can include text, polls, locations, and media attachments. Public posts appear on the public timeline; private or follower-only posts are only visible to approved followers.</p>

    <h2>Accounts And Privacy</h2>
    <p>Use <a href="/settings">Settings</a> to update your avatar, banner, profile text, private account mode, follow approvals, post visibility, and direct message privacy. Private accounts show a lock and require approval before new followers can see posts.</p>

    <h2>Following And Messages</h2>
    <p>Follow buttons appear on profiles and suggestions. If someone follows you, Twitkey shows a “Follows you” label beside their name. Direct Messages are available when the recipient allows them, including mutual-only messaging.</p>

    <h2>Posts, Replies, And Polls</h2>
    <p>Replies appear under the post they respond to and include a parent-post preview in timelines. Poll results refresh while you are viewing the page. Media previews open in a larger viewer when clicked.</p>

    <h2>Safety</h2>
    <p>Administrators can suspend accounts that break the <?= Helpers::h(Helpers::env('APP_NAME', 'Twitkey')) ?> Terms of Service. Suspended accounts cannot post, reply, message, follow, or use other write actions.</p>

    <h2>More</h2>
    <p>Read the <a href="/terms">Terms of Service</a> and <a href="/privacy">Privacy Policy</a> for platform rules and data handling details.</p>
</section>
