<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PinkaryApi
{
    /**
     * Run an API call, returning null when the device cannot reach the
     * API (timeout, DNS) instead of throwing past the screen's error
     * state. Callers map null to their existing error copy.
     *
     * @param  callable(): Response  $call
     */
    public function attempt(callable $call): ?Response
    {
        try {
            return $call();
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Authenticate a user with the Pinkary API.
     */
    public function login(string $email, string $password): Response
    {
        return $this->request()->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Create a Pinkary account and return its access token.
     *
     * @param  array{name: string, username: string, email: string, password: string, password_confirmation: string, terms: bool}  $data
     */
    public function register(array $data): Response
    {
        return $this->request()->post('/api/v1/auth/register', $data);
    }

    /**
     * Load the authenticated user's profile.
     */
    public function profile(string $token): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/profile');
    }

    /**
     * Load the feed one page at a time. Guests read the public tabs
     * without a token, like the web's home page.
     */
    public function feed(?string $token, string $tab = 'recent', int $page = 1): Response
    {
        $request = $this->request();

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request->get('/api/v1/feed', [
            'tab' => $tab,
            'page' => $page,
        ]);
    }

    /**
     * Publish a shared update (thread) for the authenticated user.
     *
     * @param  list<string>  $threadPosts
     * @param  list<string>  $pollOptions
     * @param  array<int, array{options: list<string>, duration: int}|null>  $threadPolls
     */
    public function createThread(string $token, string $content, array $threadPosts = [], ?int $channelId = null, ?string $channelName = null, array $pollOptions = [], int $pollDuration = 1, array $threadPolls = []): Response
    {
        // Follow-up polls travel index-aligned with their post: null
        // entries mean "no poll", exactly like the web composer sends.
        $normalizedThreadPolls = array_map(
            fn (mixed $poll): mixed => $poll === null ? null : [
                'options' => $poll['options'],
                'duration' => $poll['duration'],
            ],
            array_values($threadPolls)
        );

        return $this->request()->withToken($token)->post('/api/v1/questions', array_filter([
            'content' => $content,
            'thread_posts' => $threadPosts,
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'poll_options' => $pollOptions,
            'poll_duration' => $pollOptions === [] ? null : $pollDuration,
            'thread_polls' => $normalizedThreadPolls,
        ], fn (mixed $value): bool => $value !== null && $value !== []));
    }

    /**
     * Like a question for the authenticated user.
     */
    public function like(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/like");
    }

    /**
     * Remove the authenticated user's like.
     */
    public function unlike(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/questions/{$id}/like");
    }

    /**
     * Bookmark a question for the authenticated user.
     */
    public function bookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/bookmark");
    }

    /**
     * Remove the authenticated user's bookmark.
     */
    public function unbookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/questions/{$id}/bookmark");
    }

    /**
     * Load a single question with the author's profile and metrics.
     */
    public function show(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/questions/{$id}");
    }

    /**
     * List a question's direct comments, one page at a time.
     */
    public function comments(string $token, string $id, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/questions/{$id}/comments", [
            'page' => $page,
        ]);
    }

    /**
     * Comment on a question for the authenticated user.
     */
    public function comment(string $token, string $id, string $content): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/comments", [
            'content' => $content,
        ]);
    }

    /**
     * Vote in a question's poll (toggling — voting the same option
     * again removes the vote).
     */
    public function votePoll(string $token, string $id, int $optionId): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/poll/vote", [
            'option_id' => $optionId,
        ]);
    }

    /**
     * List the authenticated user's bookmarked questions.
     */
    public function bookmarks(string $token, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/bookmarks', [
            'page' => $page,
        ]);
    }

    /**
     * List popular channels, optionally filtered by name.
     */
    public function channels(string $token, string $query = ''): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/channels', [
            'q' => $query,
        ]);
    }

    /**
     * Search verified users and hashtags by prefix.
     */
    public function search(string $token, string $query): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/search', [
            'q' => $query,
        ]);
    }

    /**
     * List the authenticated user's notifications, newest first.
     */
    public function notifications(string $token, int $page = 1, ?int $perPage = null): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/notifications', array_filter([
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    /**
     * Mark one notification (or all) as read.
     */
    public function markNotificationsRead(string $token, ?string $id = null): Response
    {
        return $this->request()->withToken($token)->post('/api/v1/notifications/read', array_filter([
            'id' => $id,
        ]));
    }

    /**
     * Show any user's public profile card.
     */
    public function user(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/users/{$username}");
    }

    /**
     * Follow the user (idempotent).
     */
    public function follow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/users/{$username}/follow");
    }

    /**
     * Unfollow the user (idempotent).
     */
    public function unfollow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/users/{$username}/follow");
    }

    /**
     * Record a profile link visit.
     */
    public function clickLink(string $token, int $linkId): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/links/{$linkId}/click");
    }

    /**
     * List a user's answered questions, pinned first (profile posts).
     */
    public function userQuestions(string $token, string $username, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/users/{$username}/questions", [
            'page' => $page,
        ]);
    }

    /**
     * Ask another user a question (255 chars, optionally anonymous).
     */
    public function askQuestion(string $token, string $toUsername, string $content, bool $anonymously = false): Response
    {
        return $this->request()->withToken($token)->post('/api/v1/questions', [
            'content' => $content,
            'to_username' => $toUsername,
            'anonymously' => $anonymously,
        ]);
    }

    /**
     * Update the authenticated user's profile (text fields only —
     * avatar upload needs a file picker the framework does not expose).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(string $token, array $attributes): Response
    {
        return $this->request()->withToken($token)->patch('/api/v1/profile', $attributes);
    }

    /**
     * Create a profile link (description + url).
     */
    public function createLink(string $token, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->post('/api/v1/links', [
            'description' => $description,
            'url' => $url,
        ]);
    }

    /**
     * Update a profile link (description + url).
     */
    public function updateLink(string $token, int $linkId, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->put("/api/v1/links/{$linkId}", [
            'description' => $description,
            'url' => $url,
        ]);
    }

    /**
     * Delete a profile link.
     */
    public function deleteLink(string $token, int $linkId): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/links/{$linkId}");
    }

    /**
     * List a user's followers / following with followed_by_me flags.
     */
    public function followers(string $token, string $username, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/users/{$username}/followers", [
            'page' => $page,
        ]);
    }

    public function following(string $token, string $username, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/users/{$username}/following", [
            'page' => $page,
        ]);
    }

    /**
     * Pin or unpin a question (policy-gated server-side).
     */
    public function pinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/pin");
    }

    public function unpinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/questions/{$id}/pin");
    }

    /**
     * Update a question's answer (24h window enforced server-side).
     */
    public function updateAnswer(string $token, string $id, string $answer): Response
    {
        return $this->request()->withToken($token)->put("/api/v1/questions/{$id}/answer", [
            'answer' => $answer,
        ]);
    }

    /**
     * Delete a question, or ignore it (delete flow on the web).
     */
    public function deleteQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/questions/{$id}");
    }

    public function ignoreQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post("/api/v1/questions/{$id}/ignore");
    }

    /**
     * List a question's likers (policy-gated server-side).
     */
    public function likers(string $token, string $id, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get("/api/v1/questions/{$id}/likes", [
            'page' => $page,
        ]);
    }

    /**
     * Delete a single notification (204 on success).
     */
    public function deleteNotification(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete("/api/v1/notifications/{$id}");
    }

    /**
     * Revoke the current access token.
     */
    public function logout(string $token): Response
    {
        return $this->request()->withToken($token)->post('/api/v1/auth/logout');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pinkary.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
