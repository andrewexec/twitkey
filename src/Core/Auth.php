<?php
declare(strict_types=1);

namespace Twitkey\Core;

use Twitkey\Models\User;

final class Auth
{
    private static ?array $cachedUser = null;

    /**
     * Return the logged-in user, or null for guests.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        self::$cachedUser = User::find($id);
        if (self::$cachedUser === null) {
            unset($_SESSION['user_id']);
        }
        return self::$cachedUser;
    }

    /**
     * Return the current user id, or null for guests.
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Attempt a username/email and password login.
     */
    public static function attempt(string $login, string $password): bool
    {
        $user = User::findByLogin($login);
        if (!$user || (int)($user['is_system'] ?? 0) === 1 || !password_verify($password, (string)$user['password'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$cachedUser = $user;
        return true;
    }

    /**
     * Log out the current session.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        self::$cachedUser = null;
    }

    /**
     * Require a logged-in user or redirect to the login page.
     *
     * @return array<string, mixed>
     */
    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Helpers::redirect('/login');
        }
        return $user;
    }

    /**
     * Require an active, non-suspended account for write actions.
     *
     * @return array<string, mixed>
     */
    public static function requireActiveUser(): array
    {
        $user = self::requireLogin();
        if ((int)$user['is_suspended'] === 1) {
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => false, 'error' => 'This account has been suspended.'], 403);
            }
            Session::flash('error', 'This account has been suspended.');
            Helpers::redirect('/');
        }
        return $user;
    }

    /**
     * Require an administrator or render a 403 response.
     *
     * @return array<string, mixed>
     */
    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ((int)$user['is_admin'] !== 1) {
            http_response_code(403);
            Helpers::render('errors/403', ['title' => 'Forbidden'], true);
            exit;
        }
        return $user;
    }

    /**
     * True if the current user is an administrator.
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && (int)$user['is_admin'] === 1;
    }

    /**
     * Clear the cached user after profile or privilege updates.
     */
    public static function clearCache(): void
    {
        self::$cachedUser = null;
    }
}
