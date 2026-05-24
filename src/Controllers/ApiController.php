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
     * Search GIFs through a configurable no-key provider endpoint.
     */
    public function gifs(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            Helpers::json(['ok' => true, 'items' => []]);
        }

        $endpoint = Helpers::env('GIF_API_SEARCH_URL', 'https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrnamespace=6&gsrlimit=12&gsrsearch={query}%20filetype:bitmap%20gif&prop=imageinfo&iiprop=url%7Cmime&format=json&origin=*');
        $json = $this->fetchJson(str_replace('{query}', rawurlencode($query), $endpoint));
        $items = [];

        foreach ($this->extractGifItems($json) as $item) {
            $url = $item['url'] ?? $item['gif'] ?? $item['media'] ?? null;
            if (is_array($url)) {
                $url = $url['url'] ?? null;
            }
            if (!is_string($url) || !str_starts_with($url, 'https://')) {
                continue;
            }
            $items[] = [
                'url' => $url,
                'title' => substr((string)($item['title'] ?? $item['name'] ?? 'GIF'), 0, 80),
            ];
            if (count($items) >= 12) {
                break;
            }
        }

        Helpers::json(['ok' => true, 'items' => $items]);
    }

    /**
     * Search map locations through a configurable geocoder endpoint.
     */
    public function locations(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            Helpers::json(['ok' => true, 'items' => []]);
        }

        $endpoint = Helpers::env('LOCATION_SEARCH_URL', 'https://nominatim.openstreetmap.org/search?format=json&limit=6&q={query}');
        $json = $this->fetchJson(str_replace('{query}', rawurlencode($query), $endpoint));
        $items = [];
        foreach ((array)$json as $row) {
            if (!isset($row['lat'], $row['lon'])) {
                continue;
            }
            $items[] = [
                'label' => substr((string)($row['display_name'] ?? $row['name'] ?? 'Selected location'), 0, 160),
                'lat' => (float)$row['lat'],
                'lng' => (float)$row['lon'],
            ];
        }

        Helpers::json(['ok' => true, 'items' => $items]);
    }

    /**
     * Serve uploaded avatars and banners through a validated local media endpoint.
     */
    public function media(string $file): void
    {
        $isCurrentUpload = preg_match('/^(avatar|banner)_[a-f0-9]{64}\.jpg$/', $file) === 1;
        $isLegacyAvatar = preg_match('/^[a-f0-9]{64}\.jpg$/', $file) === 1;
        $isTweetMedia = preg_match('/^tweet_[a-f0-9]{64}\.(jpg|png|gif|webp)$/', $file) === 1;
        if (!$isCurrentUpload && !$isLegacyAvatar && !$isTweetMedia) {
            http_response_code(404);
            return;
        }

        $dataDir = Database::instance()->dataDir();
        $path = ($isCurrentUpload || $isTweetMedia) ? $dataDir . '/uploads/' . $file : $dataDir . '/avatars/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    /**
     * Fetch JSON from an external API with a short timeout.
     *
     * @return mixed
     */
    private function fetchJson(string $url): mixed
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 4,
                'header' => "User-Agent: Twitkey/1.0\r\nAccept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Support several common GIF API response shapes without binding to one provider.
     *
     * @param mixed $json
     * @return array<int, array<string, mixed>>
     */
    private function extractGifItems(mixed $json): array
    {
        if (!is_array($json)) {
            return [];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            $items = [];
            foreach ($json['data'] as $row) {
                if (isset($row['images']['fixed_height']['url'])) {
                    $items[] = ['url' => $row['images']['fixed_height']['url'], 'title' => $row['title'] ?? 'GIF'];
                } elseif (isset($row['media_formats']['gif']['url'])) {
                    $items[] = ['url' => $row['media_formats']['gif']['url'], 'title' => $row['content_description'] ?? 'GIF'];
                } elseif (is_array($row)) {
                    $items[] = $row;
                }
            }
            return $items;
        }
        if (isset($json['results']) && is_array($json['results'])) {
            return $json['results'];
        }
        if (isset($json['query']['pages']) && is_array($json['query']['pages'])) {
            $items = [];
            foreach ($json['query']['pages'] as $page) {
                $info = $page['imageinfo'][0] ?? null;
                if (!is_array($info) || ($info['mime'] ?? '') !== 'image/gif' || empty($info['url'])) {
                    continue;
                }
                $items[] = [
                    'url' => (string)$info['url'],
                    'title' => preg_replace('/^File:/', '', (string)($page['title'] ?? 'GIF')) ?: 'GIF',
                ];
            }
            return $items;
        }
        return array_is_list($json) ? $json : [];
    }
}
