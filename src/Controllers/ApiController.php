<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Database;
use Twitkey\Core\Helpers;
use Twitkey\Models\User;

final class ApiController
{
    /**
     * Return username availability.
     */
    public function username(): void
    {
        $username = (string)($_GET['username'] ?? '');
        Helpers::json(['ok' => true, 'available' => User::usernameAvailable($username)]);
    }

    /**
     * Return simple mention/hashtag suggestions for compose autocomplete.
     */
    public function suggest(): void
    {
        $type = (string)($_GET['type'] ?? '@');
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            Helpers::json(['ok' => true, 'items' => []]);
        }
        if ($type === '#') {
            $rows = \Twitkey\Core\Database::instance()->all(
                'SELECT tag AS value FROM hashtags WHERE lower(tag) LIKE lower(:term) ORDER BY tag ASC LIMIT 8',
                ['term' => $q . '%']
            );
        } else {
            $rows = User::search($q, 8);
            $rows = array_map(static fn(array $u): array => ['value' => (string)$u['username']], $rows);
        }
        Helpers::json(['ok' => true, 'items' => $rows]);
    }

    /**
     * Serve uploaded avatars and banners through a validated local media endpoint.
     */
    public function media(string $file): void
    {
        $isCurrentUpload = preg_match('/^(avatar|banner)_[a-f0-9]{64}\.jpg$/', $file) === 1;
        $isLegacyAvatar = preg_match('/^[a-f0-9]{64}\.jpg$/', $file) === 1;
        if (!$isCurrentUpload && !$isLegacyAvatar) {
            http_response_code(404);
            return;
        }

        $dataDir = Database::instance()->dataDir();
        $path = $isCurrentUpload ? $dataDir . '/uploads/' . $file : $dataDir . '/avatars/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}
