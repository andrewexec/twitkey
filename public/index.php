<?php
declare(strict_types=1);

define('TWITKEY_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Twitkey\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = TWITKEY_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use Twitkey\Controllers\AdminController;
use Twitkey\Controllers\ApiController;
use Twitkey\Controllers\AuthController;
use Twitkey\Controllers\HomeController;
use Twitkey\Controllers\NotificationsController;
use Twitkey\Controllers\TweetController;
use Twitkey\Controllers\UserController;
use Twitkey\Core\Database;
use Twitkey\Core\Helpers;
use Twitkey\Core\Router;
use Twitkey\Core\Session;

Helpers::loadEnv(TWITKEY_ROOT . '/.env');
if (Helpers::env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

Session::start();
Database::instance();

$router = new Router();
$router->add('GET', '/', [HomeController::class, 'timeline']);
$router->add('POST', '/', [TweetController::class, 'create']);
$router->add('GET', '/public', [HomeController::class, 'publicTimeline']);
$router->add('GET', '/login', [AuthController::class, 'loginForm']);
$router->add('POST', '/login', [AuthController::class, 'login']);
$router->add('GET', '/register', [AuthController::class, 'registerForm']);
$router->add('POST', '/register', [AuthController::class, 'register']);
$router->add('GET', '/logout', [AuthController::class, 'logout']);
$router->add('GET', '/settings', [UserController::class, 'settings']);
$router->add('POST', '/settings', [UserController::class, 'updateSettings']);
$router->add('GET', '/settings/affiliations', [UserController::class, 'affiliations']);
$router->add('POST', '/settings/affiliations', [UserController::class, 'updateAffiliations']);
$router->add('POST', '/tweet', [TweetController::class, 'create']);
$router->add('GET', '/tweet/{id}', [TweetController::class, 'show']);
$router->add('POST', '/tweet/{id}/reply', [TweetController::class, 'reply']);
$router->add('POST', '/tweet/{id}/retweet', [TweetController::class, 'retweet']);
$router->add('POST', '/tweet/{id}/favorite', [TweetController::class, 'favorite']);
$router->add('POST', '/tweet/{id}/poll/{option_id}', [TweetController::class, 'votePoll']);
$router->add('DELETE', '/tweet/{id}', [TweetController::class, 'delete']);
$router->add('POST', '/follow/{username}', [UserController::class, 'follow']);
$router->add('GET', '/replies', [NotificationsController::class, 'replies']);
$router->add('GET', '/notifications', [NotificationsController::class, 'index']);
$router->add('GET', '/direct_messages', [HomeController::class, 'dms']);
$router->add('POST', '/direct_messages/{user}', [HomeController::class, 'sendDm']);
$router->add('GET', '/search', [HomeController::class, 'search']);
$router->add('GET', '/notes/pending', [TweetController::class, 'pendingNotes']);
$router->add('POST', '/tweet/{id}/note', [TweetController::class, 'addNote']);
$router->add('POST', '/note/{id}/vote', [TweetController::class, 'voteNote']);
$router->add('GET', '/admin', [AdminController::class, 'dashboard']);
$router->add('GET', '/admin/users', [AdminController::class, 'users']);
$router->add('POST', '/admin/users/{id}/action', [AdminController::class, 'userAction']);
$router->add('GET', '/admin/tweets', [AdminController::class, 'tweets']);
$router->add('POST', '/admin/tweets/{id}/action', [AdminController::class, 'tweetAction']);
$router->add('GET', '/admin/notes', [AdminController::class, 'notes']);
$router->add('POST', '/admin/notes/{id}/action', [AdminController::class, 'noteAction']);
$router->add('GET', '/admin/setup', [AdminController::class, 'setup']);
$router->add('GET', '/api/username', [ApiController::class, 'username']);
$router->add('GET', '/api/suggest', [ApiController::class, 'suggest']);
$router->add('GET', '/api/gifs', [ApiController::class, 'gifs']);
$router->add('GET', '/api/locations', [ApiController::class, 'locations']);
$router->add('GET', '/media/{file}', [ApiController::class, 'media']);
$router->add('GET', '/{username}/followers', [UserController::class, 'followers']);
$router->add('GET', '/{username}/following', [UserController::class, 'following']);
$router->add('GET', '/{username}', [UserController::class, 'profile']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
