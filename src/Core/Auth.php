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
        self::loginAs($user, true);
        return true;
    }

    /**
     * Return a user row when the supplied credentials are valid for a switchable account.
     *
     * @return array<string, mixed>|null
     */
    public static function validateCredentials(string $login, string $password): ?array
    {
        $user = User::findByLogin($login);
        if (!$user || (int)($user['is_system'] ?? 0) === 1 || !password_verify($password, (string)$user['password'])) {
            return null;
        }
        return $user;
    }

    /**
     * Set the active account and remember it in this browser session.
     *
     * @param array<string, mixed> $user
     */
    public static function loginAs(array $user, bool $regenerate = false): void
    {
        if ($regenerate) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['account_ids'] = self::normalizedAccountIds([(int)$user['id'], ...self::accountIds()]);
        self::$cachedUser = $user;
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
     * Return switchable account ids remembered in this browser session.
     *
     * @return array<int, int>
     */
    public static function accountIds(): array
    {
        return self::normalizedAccountIds([(int)($_SESSION['user_id'] ?? 0), ...(array)($_SESSION['account_ids'] ?? [])]);
    }

    /**
     * Return switchable account rows for the active browser session.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function linkedAccounts(): array
    {
        $accounts = [];
        foreach (self::accountIds() as $id) {
            $user = User::find($id);
            if ($user && (int)($user['is_system'] ?? 0) !== 1) {
                $accounts[] = $user;
            }
        }
        return $accounts;
    }

    /**
     * Add an account to the current browser session without switching away.
     *
     * @param array<string, mixed> $user
     */
    public static function rememberAccount(array $user): void
    {
        $_SESSION['account_ids'] = self::normalizedAccountIds([(int)$user['id'], ...self::accountIds()]);
    }

    /**
     * Switch the active browser session to a remembered account id.
     */
    public static function switchTo(int $id): bool
    {
        if (!in_array($id, self::accountIds(), true)) {
            return false;
        }
        $user = User::find($id);
        if (!$user || (int)($user['is_system'] ?? 0) === 1) {
            return false;
        }
        self::loginAs($user, true);
        return true;
    }

    /**
     * Remove a remembered account from the browser session.
     */
    public static function forgetAccount(int $id): void
    {
        $_SESSION['account_ids'] = array_values(array_filter(self::accountIds(), static fn (int $accountId): bool => $accountId !== $id));
        if ((int)($_SESSION['user_id'] ?? 0) === $id) {
            unset($_SESSION['user_id']);
            self::$cachedUser = null;
        }
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
        if ((int)$user['is_suspended'] === 1 || (int)($user['is_deleted'] ?? 0) === 1) {
            $reason = trim((string)($user['suspension_reason'] ?? ''));
            $message = 'This account has been suspended.' . ($reason !== '' ? ' Reason: ' . $reason : '');
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => false, 'error' => $message], 403);
            }
            Session::flash('error', $message);
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

    /**
     * Normalize account ids stored in the session.
     *
     * @param mixed $ids
     * @return array<int, int>
     */
    private static function normalizedAccountIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return array_slice($out, 0, 8);
    }
}
