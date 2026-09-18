<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PinkaryApi
{
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
    public function notifications(string $token, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/notifications', [
            'page' => $page,
        ]);
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
            ->timeout(15);
    }
}
