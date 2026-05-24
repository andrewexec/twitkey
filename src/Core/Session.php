<?php
declare(strict_types=1);

namespace Twitkey\Core;

final class Session
{
    /**
     * Start a hardened PHP session once per request.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    /**
     * Store a one-time flash message.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Return and clear flash messages.
     *
     * @return array<int, array{type:string,message:string}>
     */
    public static function consumeFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($messages) ? $messages : [];
    }
}
