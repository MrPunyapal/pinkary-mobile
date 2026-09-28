<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PinkaryApi
{
    private const string PREFIX = '/api/v1';

    private const int CONNECT_TIMEOUT = 5;

    private const int TIMEOUT = 15;

    /**
     * Legacy shared-update marker. Older API servers sent this literal
     * string as a shared update's `content`; normalizeQuestion() folds
     * it into the `is_update` flag so no screen can render it.
     */
    private const string UPDATE_MARKER = '__UPDATE__';

    /**
     * Normalize one question payload from the API.
     *
     * A shared update (a post where you answered your own question)
     * used to arrive with the literal `__UPDATE__` marker as its
     * `content`; the API now sends `is_update: true` with a null
     * content. Both shapes collapse to the same normalized payload
     * here, so the blade components only ever read `is_update`.
     *
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public function normalizeQuestion(array $post): array
    {
        if (($post['content'] ?? null) === self::UPDATE_MARKER) {
            $post['is_update'] = true;
            $post['content'] = null;
        }

        // Chained posts carry their rendered ancestors, which are
        // question payloads of their own — normalize them too.
        if (is_array($post['thread']['posts'] ?? null)) {
            $post['thread']['posts'] = $this->normalizeQuestions($post['thread']['posts']);
        }

        return $post;
    }

    /**
     * Normalize a list of question payloads, dropping anything that
     * is not a payload so screens never render a half-decoded row.
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeQuestions(mixed $posts): array
    {
        if (! is_array($posts)) {
            return [];
        }

        $normalized = [];

        foreach ($posts as $post) {
            if (is_array($post)) {
                $normalized[] = $this->normalizeQuestion($post);
            }
        }

        return $normalized;
    }

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
        return $this->request()->post(self::PREFIX.'/auth/login', [
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
        return $this->request()->post(self::PREFIX.'/auth/register', $data);
    }

    /**
     * Load the authenticated user's profile.
     */
    public function profile(string $token): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/profile');
    }

    /**
     * Load the feed one page at a time. Guests read the public tabs
     * without a token, like the web's home page.
     */
    public function feed(?string $token, string $tab = 'recent', int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX.'/feed', [
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
    public function createThread(
        string $token,
        string $content,
        array $threadPosts = [],
        ?int $channelId = null,
        ?string $channelName = null,
        array $pollOptions = [],
        int $pollDuration = 1,
        array $threadPolls = []
    ): Response {
        return $this->request()->withToken($token)->post(
            self::PREFIX.'/questions',
            $this->threadPayload($content, $threadPosts, $channelId, $channelName, $pollOptions, $pollDuration, $threadPolls)
        );
    }

    /**
     * Like a question for the authenticated user.
     */
    public function like(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/like");
    }

    /**
     * Remove the authenticated user's like.
     */
    public function unlike(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/like");
    }

    /**
     * Bookmark a question for the authenticated user.
     */
    public function bookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/bookmark");
    }

    /**
     * Remove the authenticated user's bookmark.
     */
    public function unbookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/bookmark");
    }

    /**
     * Load a single question with the author's profile and metrics.
     * Guests read it like the web's public question page.
     */
    public function show(?string $token, string $id): Response
    {
        return $this->authed($token)->get(self::PREFIX."/questions/{$id}");
    }

    /**
     * List a question's direct comments, one page at a time.
     */
    public function comments(?string $token, string $id, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/questions/{$id}/comments", [
            'page' => $page,
        ]);
    }

    /**
     * Comment on a question for the authenticated user.
     */
    public function comment(string $token, string $id, string $content): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/comments", [
            'content' => $content,
        ]);
    }

    /**
     * Vote in a question's poll (toggling — voting the same option
     * again removes the vote).
     */
    public function votePoll(string $token, string $id, int $optionId): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/poll/vote", [
            'option_id' => $optionId,
        ]);
    }

    /**
     * List the authenticated user's bookmarked questions.
     */
    public function bookmarks(string $token, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/bookmarks', [
            'page' => $page,
        ]);
    }

    /**
     * List popular channels, optionally filtered by name.
     */
    public function channels(string $token, string $query = ''): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/channels', [
            'q' => $query,
        ]);
    }

    /**
     * Search verified users and hashtags by prefix. Guests search
     * without a token, like the web's public user directory.
     */
    public function search(?string $token, string $query): Response
    {
        return $this->authed($token)->get(self::PREFIX.'/search', [
            'q' => $query,
        ]);
    }

    /**
     * List the authenticated user's notifications, newest first.
     */
    public function notifications(string $token, int $page = 1, ?int $perPage = null): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/notifications', array_filter([
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    /**
     * Mark one notification (or all) as read.
     */
    public function markNotificationsRead(string $token, ?string $id = null): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/notifications/read', array_filter([
            'id' => $id,
        ]));
    }

    /**
     * Show any user's public profile card. Guests read it like the
     * web's public profile page.
     */
    public function user(?string $token, string $username): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}");
    }

    /**
     * Follow the user (idempotent).
     */
    public function follow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/users/{$username}/follow");
    }

    /**
     * Unfollow the user (idempotent).
     */
    public function unfollow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/users/{$username}/follow");
    }

    /**
     * Record a profile link visit.
     */
    /**
     * Record a click on a profile link.
     *
     * Guests count too: the endpoint reads the user id optionally
     * (`LinkClickController` passes `$request->user()?->id`) and the web
     * records anonymous clicks, so the token is optional here.
     */
    public function clickLink(?string $token, int $linkId): Response
    {
        return $this->authed($token)->post(self::PREFIX."/links/{$linkId}/click");
    }

    /**
     * List a user's answered questions, pinned first (profile posts).
     */
    public function userQuestions(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/questions", [
            'page' => $page,
        ]);
    }

    /**
     * Upload a post's images, returning their public URLs and stored paths.
     *
     * The composer appends `![name](url)` to the post body from what this
     * returns, so the markdown can be written before the post exists — which
     * is what the web does too, uploading the moment a file is picked rather
     * than at publish.
     *
     * @param  list<string>  $paths  Absolute paths on the device.
     */
    public function uploadImages(string $token, array $paths): Response
    {
        $request = $this->request()->withToken($token)->asMultipart();

        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $handle = fopen($path, 'rb');

            if ($handle === false) {
                continue;
            }

            $request = $request->attach('images[]', $handle, basename($path));
        }

        return $request->post(self::PREFIX.'/images');
    }

    /**
     * Ask another user a question (255 chars, optionally anonymous).
     */
    public function askQuestion(string $token, string $toUsername, string $content, bool $anonymously = false): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/questions', [
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
        return $this->request()->withToken($token)->patch(self::PREFIX.'/profile', $attributes);
    }

    /**
     * Create a profile link (description + url).
     */
    public function createLink(string $token, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/links', [
            'description' => $description,
            'url' => $url,
        ]);
    }

    /**
     * Update a profile link (description + url).
     */
    public function updateLink(string $token, int $linkId, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->put(self::PREFIX."/links/{$linkId}", [
            'description' => $description,
            'url' => $url,
        ]);
    }

    /**
     * Delete a profile link.
     */
    public function deleteLink(string $token, int $linkId): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/links/{$linkId}");
    }

    /**
     * Show or hide a profile link — the web's eye toggle on your own link
     * rows. The endpoint only takes `is_visible` (the controller falls
     * back to the stored description/url), so a PATCH with that one key
     * is the whole call (LinkController:32-41).
     */
    public function updateLinkVisibility(string $token, int $linkId, bool $isVisible): Response
    {
        return $this->request()->withToken($token)->patch(self::PREFIX."/links/{$linkId}", [
            'is_visible' => $isVisible,
        ]);
    }

    /**
     * List a user's followers with followed_by_me flags.
     */
    public function followers(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/followers", [
            'page' => $page,
        ]);
    }

    /**
     * List the users a user follows with followed_by_me flags.
     */
    public function following(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/following", [
            'page' => $page,
        ]);
    }

    /**
     * Pin or unpin a question (policy-gated server-side).
     */
    public function pinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/pin");
    }

    public function unpinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/pin");
    }

    /**
     * Update a question's answer (24h window enforced server-side).
     */
    public function updateAnswer(string $token, string $id, string $answer): Response
    {
        return $this->request()->withToken($token)->put(self::PREFIX."/questions/{$id}/answer", [
            'answer' => $answer,
        ]);
    }

    /**
     * Delete a question, or ignore it (delete flow on the web).
     */
    public function deleteQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}");
    }

    public function ignoreQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/ignore");
    }

    /**
     * List a question's likers (policy-gated server-side).
     */
    public function likers(string $token, string $id, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX."/questions/{$id}/likes", [
            'page' => $page,
        ]);
    }

    /**
     * Delete a single notification (204 on success).
     */
    public function deleteNotification(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/notifications/{$id}");
    }

    /**
     * Revoke the current access token.
     */
    public function logout(string $token): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/auth/logout');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pinkary.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }

    /**
     * Request builder with an optional bearer token (guest-safe endpoints).
     */
    private function authed(?string $token): PendingRequest
    {
        $request = $this->request();

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * Thread payload, dropping null/empty entries the API does not expect.
     *
     * @param  list<string>  $threadPosts
     * @param  list<string>  $pollOptions
     * @param  array<int, array{options: list<string>, duration: int}|null>  $threadPolls
     * @return array<string, mixed>
     */
    private function threadPayload(
        string $content,
        array $threadPosts,
        ?int $channelId,
        ?string $channelName,
        array $pollOptions,
        int $pollDuration,
        array $threadPolls
    ): array {
        return array_filter([
            'content' => $content,
            'thread_posts' => $threadPosts,
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'poll_options' => $pollOptions,
            'poll_duration' => $pollOptions === [] ? null : $pollDuration,
            'thread_polls' => $this->normalizeThreadPolls($threadPolls),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Normalize follow-up polls index-aligned with their post: null
     * entries mean "no poll", exactly like the web composer sends.
     *
     * @param  array<int, array{options: list<string>, duration: int}|null>  $threadPolls
     * @return array<int, array{options: list<string>, duration: int}|null>
     */
    private function normalizeThreadPolls(array $threadPolls): array
    {
        return array_map(
            fn (mixed $poll): mixed => $poll === null ? null : [
                'options' => $poll['options'],
                'duration' => $poll['duration'],
            ],
            array_values($threadPolls)
        );
    }
}
