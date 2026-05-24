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
        $canSeeTweets = User::canViewPosts($profile, $current);
        $tweets = $canSeeTweets ? Tweet::forProfile((int)$profile['id'], $tab, Helpers::page(), Auth::isAdmin()) : [];
        Helpers::render('profile/show', [
            'title' => '@' . $profile['username'],
            'profile' => $profile,
            'tweets' => $tweets,
            'tab' => $tab,
            'isOwn' => $isOwn,
            'isFollowing' => Follow::isFollowing(Auth::id(), (int)$profile['id']),
            'followPending' => Follow::isPending(Auth::id(), (int)$profile['id']),
            'canSeeTweets' => $canSeeTweets,
            'canMessage' => $current ? User::canMessage($current, $profile) : false,
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
            $avatar = $this->handleImageUpload('avatar', (int)$user['id'], 400, 400);
            $banner = $this->handleImageUpload('banner', (int)$user['id'], 1500, 500);
            $website = trim((string)($_POST['website'] ?? ''));
            if ($website !== '' && !preg_match('~^https?://~i', $website)) {
                $website = 'http://' . $website;
            }
            if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Website must be a valid URL.');
            }
            $isPrivate = isset($_POST['is_private']) ? 1 : 0;
            $followPrivacy = (string)($_POST['follow_privacy'] ?? 'everyone');
            $postVisibility = (string)($_POST['post_visibility'] ?? 'public');
            $dmPrivacy = (string)($_POST['dm_privacy'] ?? 'mutuals');
            if (!in_array($followPrivacy, ['everyone', 'approve'], true)) {
                throw new \InvalidArgumentException('Invalid follow privacy setting.');
            }
            if (!in_array($postVisibility, ['public', 'followers'], true)) {
                throw new \InvalidArgumentException('Invalid post visibility setting.');
            }
            if (!in_array($dmPrivacy, ['everyone', 'mutuals', 'none'], true)) {
                throw new \InvalidArgumentException('Invalid message privacy setting.');
            }
            if ($isPrivate === 1) {
                $followPrivacy = 'approve';
                $postVisibility = 'followers';
            }
            User::updateProfile((int)$user['id'], [
                'display_name' => trim((string)($_POST['display_name'] ?? '')),
                'bio' => mb_substr(trim((string)($_POST['bio'] ?? '')), 0, 160),
                'location' => mb_substr(trim((string)($_POST['location'] ?? '')), 0, 80),
                'website' => mb_substr($website, 0, 120),
                'avatar' => $avatar,
                'background' => $banner,
                'is_private' => (string)$isPrivate,
                'follow_privacy' => $followPrivacy,
                'post_visibility' => $postVisibility,
                'dm_privacy' => $dmPrivacy,
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
            Session::flash('success', $result['pending'] ? 'Follow request sent.' : ($result['following'] ? 'You are now following @' . $target['username'] . '.' : 'Follow removed.'));
        } catch (\Throwable $e) {
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect('/' . rawurlencode((string)$target['username']));
    }

    /**
     * Approve or decline a pending follow request.
     */
    public function followRequestAction(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        try {
            Follow::resolveRequest((int)$id, (int)$user['id'], (string)($_POST['action'] ?? ''));
            Session::flash('success', 'Follow request updated.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Helpers::redirect($_SERVER['HTTP_REFERER'] ?? '/notifications');
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
        $current = Auth::user();
        $users = User::canViewPosts($profile, $current) ? User::followers((int)$profile['id'], Helpers::page()) : [];
        Helpers::render('profile/list', ['title' => '@' . $profile['username'] . ' followers', 'profile' => $profile, 'users' => $users, 'kind' => 'followers']);
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
        $current = Auth::user();
        $users = User::canViewPosts($profile, $current) ? User::following((int)$profile['id'], Helpers::page()) : [];
        Helpers::render('profile/list', ['title' => '@' . $profile['username'] . ' following', 'profile' => $profile, 'users' => $users, 'kind' => 'following']);
    }

    /**
     * Validate, resize, and store a user image upload.
     */
    private function handleImageUpload(string $field, int $userId, int $maxWidth, int $maxHeight): ?string
    {
        if (!isset($_FILES[$field]) || (int)$_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!in_array($field, ['avatar', 'banner'], true)) {
            throw new \InvalidArgumentException('Invalid upload field.');
        }
        if ((int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(ucfirst($field) . ' upload failed.');
        }
        $maxBytes = (int)Helpers::env('MAX_AVATAR_SIZE_KB', '2048') * 1024;
        if ((int)$_FILES[$field]['size'] > $maxBytes) {
            throw new \RuntimeException(ucfirst($field) . ' is too large.');
        }
        $tmp = (string)$_FILES[$field]['tmp_name'];
        $info = getimagesize($tmp);
        if ($info === false || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new \RuntimeException(ucfirst($field) . ' must be a real JPEG, PNG, GIF, or WebP image.');
        }

        $source = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($tmp),
            'image/png' => imagecreatefrompng($tmp),
            'image/gif' => imagecreatefromgif($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false,
            default => false,
        };
        if (!$source) {
            throw new \RuntimeException(ucfirst($field) . ' could not be processed.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);
        $newWidth = max(1, (int)floor($width * $scale));
        $newHeight = max(1, (int)floor($height * $scale));
        $image = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $filename = $field . '_' . hash('sha256', $userId . ':' . $field . ':' . microtime(true) . ':' . bin2hex(random_bytes(16))) . '.jpg';
        $path = Database::instance()->dataDir() . '/uploads/' . $filename;
        if (!imagejpeg($image, $path, 88)) {
            imagedestroy($source);
            imagedestroy($image);
            throw new \RuntimeException(ucfirst($field) . ' could not be saved.');
        }
        imagedestroy($source);
        imagedestroy($image);
        return $filename;
    }
}
