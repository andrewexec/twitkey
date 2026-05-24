<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Database;
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\Affiliation;
use Twitkey\Models\Follow;
use Twitkey\Models\Tweet;
use Twitkey\Models\User;

final class UserController
{
    /**
     * Show a user profile.
     */
    public function profile(string $username): void
    {
        $profile = User::findByUsername($username);
        if (!$profile) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'User Not Found'], true);
            return;
        }
        $tab = (string)($_GET['tab'] ?? 'tweets');
        if (!in_array($tab, ['tweets', 'replies', 'favorites'], true)) {
            $tab = 'tweets';
        }
        $current = Auth::user();
        $isOwn = $current && (int)$current['id'] === (int)$profile['id'];
        $canSeeTweets = (int)$profile['is_suspended'] === 0 || Auth::isAdmin();
        $tweets = $canSeeTweets ? Tweet::forProfile((int)$profile['id'], $tab, Helpers::page(), Auth::isAdmin()) : [];
        Helpers::render('profile/show', [
            'title' => '@' . $profile['username'],
            'profile' => $profile,
            'tweets' => $tweets,
            'tab' => $tab,
            'isOwn' => $isOwn,
            'isFollowing' => Follow::isFollowing(Auth::id(), (int)$profile['id']),
            'page' => Helpers::page(),
        ]);
    }

    /**
     * Show profile settings.
     */
    public function settings(): void
    {
        $user = Auth::requireLogin();
        Helpers::render('profile/settings', [
            'title' => 'Settings',
            'user' => $user,
            'pendingAffiliations' => Affiliation::pendingForUser((int)$user['id']),
            'sentAffiliations' => ($user['verified_type'] ?? null) === 'business' ? Affiliation::sentByBusiness((int)$user['id']) : [],
        ]);
    }

    /**
     * Update profile settings.
     */
    public function updateSettings(): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        try {
            $avatar = $this->handleAvatar((int)$user['id']);
            $website = trim((string)($_POST['website'] ?? ''));
            if ($website !== '' && !preg_match('~^https?://~i', $website)) {
                $website = 'http://' . $website;
            }
            if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Website must be a valid URL.');
            }
            User::updateProfile((int)$user['id'], [
                'display_name' => trim((string)($_POST['display_name'] ?? '')),
                'bio' => substr(trim((string)($_POST['bio'] ?? '')), 0, 160),
                'location' => substr(trim((string)($_POST['location'] ?? '')), 0, 80),
                'website' => substr($website, 0, 120),
                'avatar' => $avatar,
            ]);
            Auth::clearCache();
            Session::flash('success', 'Settings updated.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/settings');
    }

    /**
     * Show affiliation settings.
     */
    public function affiliations(): void
    {
        $this->settings();
    }

    /**
     * Update affiliation invites and status.
     */
    public function updateAffiliations(): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'invite') {
                Affiliation::invite((int)$user['id'], (string)($_POST['username'] ?? ''));
                Session::flash('success', 'Affiliation invite sent.');
            } elseif ($action === 'accept') {
                Affiliation::accept((int)($_POST['affiliation_id'] ?? 0), (int)$user['id']);
                Session::flash('success', 'Affiliation accepted.');
            } elseif ($action === 'decline') {
                Affiliation::decline((int)($_POST['affiliation_id'] ?? 0), (int)$user['id']);
                Session::flash('success', 'Affiliation declined.');
            } elseif ($action === 'revoke') {
                Affiliation::revoke((int)($_POST['affiliation_id'] ?? 0), (int)$user['id']);
                Session::flash('success', 'Affiliation revoked.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/settings/affiliations');
    }

    /**
     * Toggle following a user.
     */
    public function follow(string $username): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        $target = User::findByUsername($username);
        if (!$target) {
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => false, 'error' => 'User not found.'], 404);
            }
            Session::flash('error', 'User not found.');
            Helpers::redirect('/');
        }
        try {
            $result = Follow::toggle((int)$user['id'], (int)$target['id']);
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => true] + $result);
            }
        } catch (\Throwable $e) {
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/' . rawurlencode((string)$target['username']));
    }

    /**
     * Show a user's followers.
     */
    public function followers(string $username): void
    {
        $profile = User::findByUsername($username);
        if (!$profile) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'User Not Found'], true);
            return;
        }
        Helpers::render('profile/list', ['title' => '@' . $profile['username'] . ' followers', 'profile' => $profile, 'users' => User::followers((int)$profile['id'], Helpers::page()), 'kind' => 'followers']);
    }

    /**
     * Show accounts a user follows.
     */
    public function following(string $username): void
    {
        $profile = User::findByUsername($username);
        if (!$profile) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'User Not Found'], true);
            return;
        }
        Helpers::render('profile/list', ['title' => '@' . $profile['username'] . ' following', 'profile' => $profile, 'users' => User::following((int)$profile['id'], Helpers::page()), 'kind' => 'following']);
    }

    /**
     * Validate, resize, and store an avatar upload.
     */
    private function handleAvatar(int $userId): ?string
    {
        if (!isset($_FILES['avatar']) || (int)$_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)$_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Avatar upload failed.');
        }
        $maxBytes = (int)Helpers::env('MAX_AVATAR_SIZE_KB', '2048') * 1024;
        if ((int)$_FILES['avatar']['size'] > $maxBytes) {
            throw new \RuntimeException('Avatar is too large.');
        }
        $tmp = (string)$_FILES['avatar']['tmp_name'];
        $info = getimagesize($tmp);
        if ($info === false || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new \RuntimeException('Avatar must be a real JPEG, PNG, GIF, or WebP image.');
        }

        $source = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($tmp),
            'image/png' => imagecreatefrompng($tmp),
            'image/gif' => imagecreatefromgif($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false,
            default => false,
        };
        if (!$source) {
            throw new \RuntimeException('Avatar could not be processed.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(400 / max(1, $width), 400 / max(1, $height), 1);
        $newWidth = max(1, (int)floor($width * $scale));
        $newHeight = max(1, (int)floor($height * $scale));
        $image = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $filename = hash('sha256', $userId . ':' . microtime(true) . ':' . random_bytes(8)) . '.jpg';
        $path = Database::instance()->dataDir() . '/avatars/' . $filename;
        imagejpeg($image, $path, 88);
        imagedestroy($source);
        imagedestroy($image);
        return $filename;
    }
}
