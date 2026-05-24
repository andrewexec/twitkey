<?php
declare(strict_types=1);

namespace Twitkey\Core;

final class Helpers
{
    /**
     * Load .env key/value pairs if a .env file exists.
     */
    public static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '' && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }

    /**
     * Return an environment value with a fallback.
     */
    public static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : (string)$value;
    }

    /**
     * Escape text for safe HTML output.
     */
    public static function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render a view, optionally inside the base layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], bool $layout = true): void
    {
        $viewPath = TWITKEY_ROOT . '/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('Missing view: ' . $view);
        }

        $currentUser = Auth::user();
        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        $content = (string)ob_get_clean();

        if ($layout) {
            $title = $title ?? self::env('APP_NAME', 'Twitkey');
            $hideSidebar = $hideSidebar ?? false;
            $sidebar = $sidebar ?? self::sidebarData($currentUser);
            include TWITKEY_ROOT . '/views/layouts/base.php';
            return;
        }
        echo $content;
    }

    /**
     * Render a view to a string without the layout.
     *
     * @param array<string, mixed> $data
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        $viewPath = TWITKEY_ROOT . '/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('Missing view: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        return (string)ob_get_clean();
    }

    /**
     * Redirect and stop execution.
     */
    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    /**
     * Send JSON and stop execution.
     *
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * True when the client prefers a JSON response.
     */
    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
            || isset($_GET['ajax']);
    }

    /**
     * Return the current CSRF token.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    /**
     * Return a hidden CSRF input.
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::h(self::csrfToken()) . '">';
    }

    /**
     * Verify the CSRF token for a state-changing request.
     */
    public static function verifyCsrf(): void
    {
        $token = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($token === '' || !hash_equals(self::csrfToken(), $token)) {
            if (self::wantsJson()) {
                self::json(['ok' => false, 'error' => 'Invalid CSRF token.'], 419);
            }
            http_response_code(419);
            Session::flash('error', 'Your form expired. Please try again.');
            self::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Return a sanitized page number.
     */
    public static function page(): int
    {
        return max(1, (int)($_GET['page'] ?? 1));
    }

    /**
     * Return the SQL offset for a page.
     */
    public static function offset(int $page, int $perPage = 20): int
    {
        return max(0, ($page - 1) * $perPage);
    }

    /**
     * Return the public avatar URL for a user row.
     *
     * @param array<string, mixed>|null $user
     */
    public static function avatarUrl(?array $user): string
    {
        $avatar = $user['avatar'] ?? null;
        return $avatar ? '/media/' . rawurlencode((string)$avatar) : '/img/default_avatar.png';
    }

    /**
     * Return the public profile banner URL for a user row.
     *
     * @param array<string, mixed>|null $user
     */
    public static function bannerUrl(?array $user): ?string
    {
        $banner = $user['background'] ?? null;
        return $banner ? '/media/' . rawurlencode((string)$banner) : null;
    }

    /**
     * Link mentions, hashtags, and URLs after escaping tweet text.
     */
    public static function renderTweetBody(string $body): string
    {
        $escaped = self::h($body);
        $parts = preg_split('~(https?://[^\s<]+)~i', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return nl2br($escaped, false);
        }

        $html = '';
        foreach ($parts as $part) {
            if (preg_match('~^https?://[^\s<]+$~i', $part) === 1) {
                $html .= '<a href="' . $part . '" rel="nofollow noopener" target="_blank">' . $part . '</a>';
                continue;
            }
            $part = preg_replace_callback('/(?<![\w@])@([A-Za-z0-9_]{1,15})/', static function (array $m): string {
                $u = $m[1];
                return '<a href="/' . rawurlencode($u) . '">@' . self::h($u) . '</a>';
            }, $part) ?? $part;
            $part = preg_replace_callback('/(?<![\w#])#([A-Za-z0-9_]{1,60})/', static function (array $m): string {
                $tag = $m[1];
                return '<a href="/search?q=%23' . rawurlencode($tag) . '">#' . self::h($tag) . '</a>';
            }, $part) ?? $part;
            $html .= $part;
        }
        return nl2br($html, false);
    }

    /**
     * Render admin, verification, and affiliation badges for a user row.
     *
     * @param array<string, mixed> $user
     */
    public static function renderBadges(array $user): string
    {
        $html = '';
        if ((int)($user['is_admin'] ?? 0) === 1) {
            $html .= '<img src="/img/admin_badge.png" class="image-badge admin-image-badge" title="Administrator" alt="Administrator">';
        }
        if (($user['verified_type'] ?? null) === 'business') {
            $html .= '<img src="/img/verified_badge.webp" class="image-badge verified-image-badge" title="Verified Business" alt="Verified Business">';
        } elseif (($user['verified_type'] ?? null) === 'government') {
            $html .= '<img src="/img/verified_badge.webp" class="image-badge verified-image-badge" title="Verified Government" alt="Verified Government">';
        } elseif ((int)($user['is_verified'] ?? 0) === 1) {
            $html .= '<img src="/img/verified_badge.webp" class="image-badge verified-image-badge" title="Verified" alt="Verified">';
        }

        $affiliation = self::acceptedAffiliation((int)($user['id'] ?? 0));
        if ($affiliation) {
            $avatarSrc = self::avatarUrl($affiliation);
            $username = self::h($affiliation['username']);
            $html .= '<a href="/' . $username . '" title="Affiliated with @' . $username . '" class="affiliation-link">';
            $html .= '<img src="' . $avatarSrc . '" class="affiliation-avatar" alt="@' . $username . '">';
            $html .= '</a>';
        }
        return $html;
    }

    /**
     * Render a linked display name with badges.
     *
     * @param array<string, mixed> $user
     */
    public static function renderUserName(array $user): string
    {
        $username = self::h($user['username'] ?? '');
        $display = self::h($user['display_name'] ?? $user['username'] ?? '');
        return '<a href="/' . $username . '" class="username">' . $display . '</a>' . self::renderBadges($user);
    }

    /**
     * Return the accepted business affiliation for a user, cached per request.
     *
     * @return array<string, mixed>|null
     */
    public static function acceptedAffiliation(int $userId): ?array
    {
        static $cache = [];
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        $db = Database::instance();
        $cache[$userId] = $db->one(
            'SELECT b.* FROM affiliations a JOIN users b ON b.id = a.business_id WHERE a.affiliated_id = :id AND a.status = :status LIMIT 1',
            ['id' => $userId, 'status' => 'accepted']
        );
        return $cache[$userId];
    }

    /**
     * Render a compact relative timestamp.
     */
    public static function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        if ($time === false) {
            return self::h($datetime);
        }
        $diff = max(0, time() - $time);
        if ($diff < 60) {
            return 'less than a minute ago';
        }
        if ($diff < 3600) {
            $m = (int)floor($diff / 60);
            return 'about ' . $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int)floor($diff / 3600);
            return 'about ' . $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 604800) {
            $d = (int)floor($diff / 86400);
            return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
        }
        return date('M j, Y', $time);
    }

    /**
     * Truncate text safely for compact widgets.
     */
    public static function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return rtrim(substr($text, 0, max(0, $length - 3))) . '...';
    }

    /**
     * Return data used by the right sidebar.
     *
     * @param array<string, mixed>|null $currentUser
     * @return array<string, mixed>
     */
    public static function sidebarData(?array $currentUser): array
    {
        return [
            'trends' => self::trendingTopics(),
            'suggestions' => \Twitkey\Models\User::suggestions($currentUser ? (int)$currentUser['id'] : null, 3),
        ];
    }

    /**
     * Return cached trending hashtags.
     *
     * @return array<int, array{tag:string,count:int}>
     */
    public static function trendingTopics(): array
    {
        $db = Database::instance();
        $cache = $db->dataDir() . '/cache/trends.json';
        if (is_file($cache) && (time() - (int)filemtime($cache) < 300)) {
            $decoded = json_decode((string)file_get_contents($cache), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $cutoffSql = $db->isMysql() ? 'DATE_SUB(NOW(), INTERVAL 1 DAY)' : "datetime('now', '-1 day')";
        $publishedSql = $db->isMysql() ? '(t.scheduled_at IS NULL OR t.scheduled_at <= NOW())' : "(t.scheduled_at IS NULL OR t.scheduled_at <= datetime('now'))";
        $rows = $db->all(
            "SELECT h.tag, COUNT(*) AS count
             FROM hashtags h
             JOIN tweet_hashtags th ON th.hashtag_id = h.id
             JOIN tweets t ON t.id = th.tweet_id
             JOIN users u ON u.id = t.user_id
             WHERE t.created_at >= {$cutoffSql} AND {$publishedSql} AND t.is_deleted = 0 AND u.is_suspended = 0
             GROUP BY h.id, h.tag
             ORDER BY count DESC, h.tag ASC
             LIMIT 10"
        );
        $trends = array_map(static fn(array $row): array => ['tag' => (string)$row['tag'], 'count' => (int)$row['count']], $rows);
        file_put_contents($cache, json_encode($trends, JSON_THROW_ON_ERROR));
        return $trends;
    }

    /**
     * True when a user meets community note eligibility.
     *
     * @param array<string, mixed>|null $user
     */
    public static function eligibleForNotes(?array $user): bool
    {
        if (!$user || (int)$user['tweet_count'] < 5) {
            return false;
        }
        $created = strtotime((string)$user['created_at']);
        return $created !== false && $created <= strtotime('-7 days');
    }
}
