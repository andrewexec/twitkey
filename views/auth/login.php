<?php use Twitkey\Core\Helpers; ?>
<div class="auth-card">
    <h1>Sign in to Twitkey</h1>
    <form action="/login" method="post" class="auth-form">
        <?= Helpers::csrfField() ?>
        <label>Username or email
            <input type="text" name="login" autocomplete="username" required>
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="primary-button full-button">Sign in</button>
    </form>
    <p class="auth-switch">New to Twitkey? <a href="/register">Join today!</a></p>
</div>
