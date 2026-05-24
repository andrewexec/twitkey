<?php
declare(strict_types=1);

namespace Twitkey\Controllers;

use Twitkey\Core\Auth;
use Twitkey\Core\Helpers;
use Twitkey\Core\Session;
use Twitkey\Models\CommunityNote;
use Twitkey\Models\Tweet;

final class TweetController
{
    /**
     * Create a tweet.
     */
    public function create(): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        $this->rateLimit((int)$user['id']);
        try {
            $tweet = Tweet::create((int)$user['id'], (string)($_POST['body'] ?? ''));
            if (Helpers::wantsJson()) {
                $html = Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $user]);
                Helpers::json(['ok' => true, 'html' => $html]);
            }
            Helpers::redirect('/');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/');
        }
    }

    /**
     * Show a tweet and its direct replies.
     */
    public function show(string $id): void
    {
        CommunityNote::autoModerate();
        $tweet = Tweet::findWithUser((int)$id, true);
        if (!$tweet) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'Tweet Not Found'], true);
            return;
        }
        if ((int)$tweet['is_deleted'] === 1 && !Auth::isAdmin()) {
            http_response_code(404);
            Helpers::render('errors/404', ['title' => 'Tweet Not Found'], true);
            return;
        }
        $currentUser = Auth::user();
        $note = CommunityNote::approvedForTweet((int)$tweet['id']);
        Helpers::render('tweet/show', [
            'title' => 'Tweet by @' . $tweet['username'],
            'tweet' => $tweet,
            'replies' => Tweet::repliesTo((int)$tweet['id'], true),
            'note' => $note,
            'canNote' => Helpers::eligibleForNotes($currentUser),
        ]);
    }

    /**
     * Reply to a tweet.
     */
    public function reply(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        $this->rateLimit((int)$user['id']);
        try {
            $tweet = Tweet::create((int)$user['id'], (string)($_POST['body'] ?? ''), (int)$id);
            if (Helpers::wantsJson()) {
                $html = Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $user]);
                Helpers::json(['ok' => true, 'html' => $html]);
            }
            Helpers::redirect('/tweet/' . (int)$id);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/tweet/' . (int)$id);
        }
    }

    /**
     * Retweet a tweet.
     */
    public function retweet(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        try {
            $tweet = Tweet::retweet((int)$user['id'], (int)$id);
            if (Helpers::wantsJson()) {
                $html = Helpers::renderPartial('partials/tweet_row', ['tweet' => $tweet, 'currentUser' => $user]);
                Helpers::json(['ok' => true, 'html' => $html]);
            }
            Helpers::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), $_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Toggle favorite state.
     */
    public function favorite(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        try {
            $result = Tweet::toggleFavorite((int)$user['id'], (int)$id);
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => true] + $result);
            }
            Helpers::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), $_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Soft delete a tweet.
     */
    public function delete(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireLogin();
        try {
            Tweet::delete((int)$id, (int)$user['id'], Auth::isAdmin());
            if (Helpers::wantsJson()) {
                Helpers::json(['ok' => true]);
            }
            Helpers::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), $_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Show pending community notes.
     */
    public function pendingNotes(): void
    {
        $user = Auth::requireLogin();
        if (!Helpers::eligibleForNotes($user)) {
            http_response_code(403);
            Helpers::render('errors/403', ['title' => 'Not Eligible'], true);
            return;
        }
        Helpers::render('tweet/pending_notes', [
            'title' => 'Pending Notes',
            'notes' => CommunityNote::pending(Helpers::page()),
            'page' => Helpers::page(),
        ]);
    }

    /**
     * Add a community note to a tweet.
     */
    public function addNote(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        if (!Helpers::eligibleForNotes($user)) {
            $this->fail('You are not eligible to add Community Notes yet.', '/tweet/' . (int)$id);
        }
        try {
            CommunityNote::add((int)$id, (int)$user['id'], (string)($_POST['body'] ?? ''));
            Session::flash('success', 'Community Note submitted for review.');
            Helpers::redirect('/tweet/' . (int)$id);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/tweet/' . (int)$id);
        }
    }

    /**
     * Vote on or flag a community note.
     */
    public function voteNote(string $id): void
    {
        Helpers::verifyCsrf();
        $user = Auth::requireActiveUser();
        $vote = (string)($_POST['vote'] ?? '');
        try {
            if ($vote === 'flag') {
                CommunityNote::flag((int)$id, (int)$user['id'], (string)($_POST['reason'] ?? ''));
                Session::flash('success', 'Note flagged for admin review.');
            } else {
                if (!Helpers::eligibleForNotes($user)) {
                    throw new \RuntimeException('You are not eligible to vote on Community Notes yet.');
                }
                CommunityNote::vote((int)$id, (int)$user['id'], $vote);
                Session::flash('success', 'Your note vote was recorded.');
            }
            Helpers::redirect($_SERVER['HTTP_REFERER'] ?? '/notes/pending');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), $_SERVER['HTTP_REFERER'] ?? '/notes/pending');
        }
    }

    /**
     * Enforce 30 tweets per rolling hour in session.
     */
    private function rateLimit(int $userId): void
    {
        $now = time();
        $key = 'tweet_rate_' . $userId;
        $times = array_filter($_SESSION[$key] ?? [], static fn(int $t): bool => $t > $now - 3600);
        if (count($times) >= 30) {
            throw new \RuntimeException('You have reached the limit of 30 tweets per hour.');
        }
        $times[] = $now;
        $_SESSION[$key] = $times;
    }

    /**
     * Return a form/action error.
     */
    private function fail(string $message, string $fallback): never
    {
        if (Helpers::wantsJson()) {
            Helpers::json(['ok' => false, 'error' => $message], 400);
        }
        Session::flash('error', $message);
        Helpers::redirect($fallback);
    }
}
