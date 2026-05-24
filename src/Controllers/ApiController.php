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
     * Search GIFs through Klipy or a compatible configurable endpoint.
     */
    public function gifs(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            Helpers::json(['ok' => true, 'items' => []]);
        }

        $endpoint = Helpers::env('GIF_API_SEARCH_URL', 'https://api.klipy.com/v2/search?q={query}&key={key}&limit=12&media_filter=gif,tinygif,mediumgif,nanogif,preview&contentfilter=low');
        $endpoint = str_replace(
            ['{query}', '{key}'],
            [rawurlencode($query), rawurlencode(Helpers::env('KLIPY_API_KEY', ''))],
            $endpoint
        );
        $json = $this->fetchJson($endpoint);
        $items = $this->extractGifItems($json);
        if ($items === [] && Helpers::env('KLIPY_API_KEY', '') !== '') {
            $fallback = 'https://api.klipy.com/api/v1/{key}/gifs/search?q={query}&per_page=12&rating=g';
            $json = $this->fetchJson(str_replace(
                ['{query}', '{key}'],
                [rawurlencode($query), rawurlencode(Helpers::env('KLIPY_API_KEY', ''))],
                $fallback
            ));
            $items = $this->extractGifItems($json);
        }
        $results = [];

        foreach ($items as $item) {
            $url = $item['url'] ?? $item['gif'] ?? $item['media'] ?? null;
            if (is_array($url)) {
                $url = $url['url'] ?? null;
            }
            if (!is_string($url) || !str_starts_with($url, 'https://')) {
                continue;
            }
            $results[] = [
                'url' => $url,
                'title' => substr((string)($item['title'] ?? $item['name'] ?? 'GIF'), 0, 80),
            ];
            if (count($results) >= 12) {
                break;
            }
        }

        Helpers::json(['ok' => true, 'items' => $results]);
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
     * Return the active site alert for client polling.
     */
    public function siteAlert(): void
    {
        $alert = Database::instance()->one(
            'SELECT id, message, is_active, updated_at FROM site_alerts WHERE is_active = 1 AND message <> :empty ORDER BY updated_at DESC, id DESC LIMIT 1',
            ['empty' => '']
        );
        Helpers::json(['ok' => true, 'alert' => $alert]);
    }

    /**
     * Proxy remote GIFs so older stored URLs are not blocked by browser hotlink rules.
     */
    public function gifProxy(): void
    {
        $url = (string)($_GET['url'] ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            http_response_code(404);
            return;
        }
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '' || preg_match('/(^|\\.)(klipy\\.com|tenor\\.com|giphy\\.com|wikimedia\\.org|wikipedia\\.org)$/', $host) !== 1) {
            http_response_code(404);
            return;
        }
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => "User-Agent: Twitkey/1.0\r\nAccept: image/gif,image/webp,image/*,*/*\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            http_response_code(404);
            return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($body) ?: 'image/gif';
        if (!in_array($mime, ['image/gif', 'image/webp', 'image/png', 'image/jpeg'], true)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        echo $body;
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
            if (isset($json['data']['data']) && is_array($json['data']['data'])) {
                $items = [];
                foreach ($json['data']['data'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $file = $row['file'] ?? [];
                    if (is_array($file)) {
                        foreach (['hd', 'md', 'sm', 'xs'] as $size) {
                            if (isset($file[$size]['gif']['url']) && is_string($file[$size]['gif']['url'])) {
                                $items[] = ['url' => $file[$size]['gif']['url'], 'title' => $row['title'] ?? 'GIF'];
                                continue 2;
                            }
                        }
                    }
                }
                return $items;
            }
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
            $items = [];
            foreach ($json['results'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $media = $row['media_formats'] ?? [];
                if (is_array($media)) {
                    foreach (['gif', 'mediumgif', 'tinygif', 'nanogif', 'preview'] as $format) {
                        if (isset($media[$format]['url']) && is_string($media[$format]['url'])) {
                            $items[] = [
                                'url' => $media[$format]['url'],
                                'title' => $row['content_description'] ?? $row['title'] ?? 'GIF',
                            ];
                            continue 2;
                        }
                    }
                }
                $items[] = $row;
            }
            return $items;
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
