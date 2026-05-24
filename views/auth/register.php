<?php use Twitkey\Core\Helpers; ?>
<div class="auth-card">
    <h1>Join Twitkey today</h1>
    <form action="/register" method="post" class="auth-form" data-register-form>
        <?= Helpers::csrfField() ?>
        <label>Full name
            <input type="text" name="display_name" maxlength="80" required>
            <span class="field-error"></span>
        </label>
        <label>Username
            <input type="text" name="username" maxlength="15" pattern="[A-Za-z0-9_]{1,15}" data-username-check required>
            <span class="field-error username-status"></span>
        </label>
        <label>Email
            <input type="email" name="email" required>
            <span class="field-error"></span>
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            <span class="field-error"></span>
        </label>
        <label>Confirm password
            <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
            <span class="field-error"></span>
        </label>
        <button type="submit" class="primary-button full-button">Create my account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
</div>
