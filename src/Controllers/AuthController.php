<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\User;

final class AuthController
{
    /**
     * Show the sign-in form.
     */
    public function loginForm(): void
    {
        Helpers::render('auth/login', ['title' => 'Sign in', 'hideSidebar' => true]);
    }

    /**
     * Authenticate a user.
     */
    public function login(): void
    {
        Helpers::verifyCsrf();
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($login === '' || $password === '' || !Auth::attempt($login, $password)) {
            Session::flash('error', 'Invalid username/email or password.');
            Helpers::redirect('/login');
        }
        Helpers::redirect('/');
    }

    /**
     * Show the registration form.
     */
    public function registerForm(): void
    {
        Helpers::render('auth/register', ['title' => 'Join Twitkey', 'hideSidebar' => true]);
    }

    /**
     * Register and sign in a user.
     */
    public function register(): void
    {
        Helpers::verifyCsrf();
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        $errors = [];
        if ($displayName === '' || mb_strlen($displayName) > 80) {
            $errors[] = 'Full name is required and must be 80 characters or less.';
        }
        if (!User::usernameAvailable($username)) {
            $errors[] = 'Username is invalid or already taken.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                Session::flash('error', $error);
            }
            Helpers::redirect('/register');
        }

        try {
            User::create([
                'username' => $username,
                'display_name' => $displayName,
                'email' => $email,
                'password' => $password,
            ]);
            Auth::attempt($username, $password);
            Helpers::redirect('/');
        } catch (\Throwable) {
            Session::flash('error', 'That username or email address is already in use.');
            Helpers::redirect('/register');
        }
    }

    /**
     * End the current session.
     */
    public function logout(): void
    {
        Auth::logout();
        Helpers::redirect('/public');
    }
}
