<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DisplayText;
use App\Support\Payload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PinkaryApi
{
    private const string PREFIX = '/api/v1';

    private const int CONNECT_TIMEOUT = 5;

    private const int TIMEOUT = 15;

    private const string UPDATE_MARKER = '__UPDATE__';

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public function normalizeQuestion(array $post): array
    {
        foreach (['content', 'answer'] as $field) {
            if (is_string($post[$field.'_html'] ?? null)) {
                $post[$field.'_source'] = $post[$field] ?? null;
                $post[$field] = $post[$field.'_html'];
            }
        }

        if (($post['content'] ?? null) === self::UPDATE_MARKER) {
            $post['is_update'] = true;
            $post['content'] = null;
        }

        if (($post['is_update'] ?? false) === true) {
            $post['content'] = null;
        }

        if (! is_array($post['preview'] ?? null)) {
            $post['preview'] = DisplayText::preview($post['answer'] ?? $post['content'] ?? null);
        }

        if (is_array($post['thread'] ?? null)) {
            $thread = $post['thread'];

            if (is_array($thread['posts'] ?? null)) {
                $thread['posts'] = $this->normalizeQuestions($thread['posts']);
                $post['thread'] = $thread;
            }
        }

        return $post;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalizeQuestions(mixed $posts): array
    {
        if (! is_array($posts)) {
            return [];
        }

        $normalized = [];

        foreach ($posts as $post) {
            if (! is_array($post)) {
                continue;
            }

            $fields = [];

            foreach ($post as $field => $value) {
                $fields[(string) $field] = $value;
            }

            $normalized[] = $this->normalizeQuestion($fields);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalizeNotifications(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalizedItem = [];

            foreach ($item as $key => $value) {
                $normalizedItem[(string) $key] = $value;
            }

            if (($normalizedItem['snippet'] ?? null) === self::UPDATE_MARKER) {
                $normalizedItem['snippet'] = null;
            }

            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    /**
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

    public function login(string $email, string $password): Response
    {
        return $this->request()->post(self::PREFIX.'/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function twoFactorChallenge(string $challenge, string $code, bool $useRecoveryCode = false): Response
    {
        $payload = ['challenge' => $challenge];
        $payload[$useRecoveryCode ? 'recovery_code' : 'code'] = $code;

        return $this->request()->post(self::PREFIX.'/auth/login/two-factor-challenge', $payload);
    }

    /**
     * @param  array{name: string, username: string, email: string, password: string, password_confirmation: string, terms: bool, cf-turnstile-response?: string, captcha_state?: string}  $data
     */
    public function register(array $data): Response
    {
        return $this->request()->post(self::PREFIX.'/auth/register', $data);
    }

    public function captcha(string $action, ?string $token = null): Response
    {
        return $this->authed($token)->get(self::PREFIX.'/captcha', ['action' => $action]);
    }

    public function profile(string $token): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/profile');
    }

    public function feed(?string $token, string $tab = 'recent', int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX.'/feed', [
            'tab' => $tab,
            'page' => $page,
        ]);
    }

    /** @param list<string> $ids */
    public function recordViews(?string $token, array $ids, string $viewer): Response
    {
        return $this->authed($token)->withUserAgent('PinkaryMobile/1.0')->connectTimeout(2)->timeout(3)->post(self::PREFIX.'/questions/views', [
            'question_ids' => $ids,
            'viewer_id' => $viewer,
        ]);
    }

    /**
     * @param  list<string>  $threadPosts
     * @param  list<string>  $pollOptions
     * @param  array<int, array{options: list<string>, duration: int}|null>  $threadPolls
     * @param  array{cf-turnstile-response?: string, captcha_state?: string}  $captcha
     */
    public function createThread(
        string $token,
        string $content,
        array $threadPosts = [],
        ?int $channelId = null,
        ?string $channelName = null,
        array $pollOptions = [],
        int $pollDuration = 1,
        array $threadPolls = [],
        array $captcha = []
    ): Response {
        return $this->request()->withToken($token)->post(
            self::PREFIX.'/questions',
            [...$this->threadPayload($content, $threadPosts, $channelId, $channelName, $pollOptions, $pollDuration, $threadPolls), ...$captcha]
        );
    }

    public function like(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/like");
    }

    public function unlike(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/like");
    }

    public function repost(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/repost");
    }

    public function unrepost(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/repost");
    }

    public function bookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/bookmark");
    }

    public function unbookmark(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/bookmark");
    }

    public function show(?string $token, string $id): Response
    {
        return $this->authed($token)->get(self::PREFIX."/questions/{$id}");
    }

    public function comments(?string $token, string $id, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/questions/{$id}/comments", [
            'page' => $page,
        ]);
    }

    /**
     * @param  array{cf-turnstile-response?: string, captcha_state?: string}  $captcha
     */
    public function comment(string $token, string $id, string $content, array $captcha = []): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/comments", [
            'content' => $content,
            ...$captcha,
        ]);
    }

    public function votePoll(string $token, string $id, int $optionId): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/poll/vote", [
            'option_id' => $optionId,
        ]);
    }

    public function bookmarks(string $token, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/bookmarks', [
            'page' => $page,
        ]);
    }

    public function channels(string $token, string $query = ''): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/channels', [
            'q' => $query,
        ]);
    }

    public function search(?string $token, string $query): Response
    {
        return $this->authed($token)->get(self::PREFIX.'/search', [
            'q' => $query,
        ]);
    }

    public function notifications(string $token, int $page = 1, ?int $perPage = null): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX.'/notifications', array_filter([
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function markNotificationsRead(string $token, ?string $id = null): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/notifications/read', array_filter([
            'id' => $id,
        ]));
    }

    public function user(?string $token, string $username): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}");
    }

    public function follow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/users/{$username}/follow");
    }

    public function unfollow(string $token, string $username): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/users/{$username}/follow");
    }

    public function clickLink(?string $token, int $linkId): Response
    {
        return $this->authed($token)->post(self::PREFIX."/links/{$linkId}/click");
    }

    public function userQuestions(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/questions", [
            'page' => $page,
        ]);
    }

    /**
     * @param  list<string>  $paths  Absolute paths on the device.
     */
    public function uploadImages(string $token, array $paths): Response
    {
        $request = $this->multipartRequest()->withToken($token);

        foreach ($paths as $path) {
            $handle = @fopen($path, 'rb');

            if ($handle === false) {
                continue;
            }

            $request = $request->attach('images[]', $handle, basename($path));
        }

        return $request->post(self::PREFIX.'/images');
    }

    /**
     * @param  array{cf-turnstile-response?: string, captcha_state?: string}  $captcha
     */
    public function askQuestion(string $token, string $toUsername, string $content, bool $anonymously = false, array $captcha = []): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/questions', [
            'content' => $content,
            'to_username' => $toUsername,
            'anonymously' => $anonymously,
            ...$captcha,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(string $token, array $attributes): Response
    {
        return $this->request()->withToken($token)->patch(self::PREFIX.'/profile', $attributes);
    }

    /**
     * @param  resource  $file
     */
    public function updateAvatar(string $token, mixed $file, string $filename): Response
    {
        return $this->multipartRequest()
            ->withToken($token)
            ->attach('avatar', $file, $filename)
            ->post(self::PREFIX.'/profile', ['_method' => 'PATCH']);
    }

    public function createLink(string $token, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/links', [
            'description' => $description,
            'url' => $url,
        ]);
    }

    public function updateLink(string $token, int $linkId, string $description, string $url): Response
    {
        return $this->request()->withToken($token)->put(self::PREFIX."/links/{$linkId}", [
            'description' => $description,
            'url' => $url,
        ]);
    }

    public function deleteLink(string $token, int $linkId): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/links/{$linkId}");
    }

    public function updateLinkVisibility(string $token, int $linkId, bool $isVisible): Response
    {
        return $this->request()->withToken($token)->patch(self::PREFIX."/links/{$linkId}", [
            'is_visible' => $isVisible,
        ]);
    }

    /** @param list<int> $ids */
    public function sortLinks(string $token, array $ids): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/links/sort', [
            'sort' => $ids,
        ]);
    }

    public function followers(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/followers", [
            'page' => $page,
        ]);
    }

    public function following(?string $token, string $username, int $page = 1): Response
    {
        return $this->authed($token)->get(self::PREFIX."/users/{$username}/following", [
            'page' => $page,
        ]);
    }

    public function pinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/pin");
    }

    public function unpinQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}/pin");
    }

    public function updateAnswer(string $token, string $id, string $answer): Response
    {
        return $this->request()->withToken($token)->put(self::PREFIX."/questions/{$id}/answer", [
            'answer' => $answer,
        ]);
    }

    public function deleteQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/questions/{$id}");
    }

    public function ignoreQuestion(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX."/questions/{$id}/ignore");
    }

    public function likers(string $token, string $id, int $page = 1): Response
    {
        return $this->request()->withToken($token)->get(self::PREFIX."/questions/{$id}/likes", [
            'page' => $page,
        ]);
    }

    public function deleteNotification(string $token, string $id): Response
    {
        return $this->request()->withToken($token)->delete(self::PREFIX."/notifications/{$id}");
    }

    public function logout(string $token): Response
    {
        return $this->request()->withToken($token)->post(self::PREFIX.'/auth/logout');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(mb_rtrim(Payload::string(config('services.pinkary.url')), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }

    /** Use a fresh multipart builder; asMultipart() does not clear existing JSON headers. */
    private function multipartRequest(): PendingRequest
    {
        return Http::baseUrl(mb_rtrim(Payload::string(config('services.pinkary.url')), '/'))
            ->acceptJson()
            ->asMultipart()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }

    private function authed(?string $token): PendingRequest
    {
        $request = $this->request();

        if ($token !== null) {
            return $request->withToken($token);
        }

        return $request;
    }

    /**
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
