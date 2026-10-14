<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\SecureStorage;
use Throwable;

final class DraftStore
{
    private const string PREFIX = 'pinkary_compose_draft_';

    private const string REPLY_PREFIX = 'pinkary_reply_draft_';

    /**
     * @return array{
     *     content: string,
     *     posts: list<string>,
     *     postPolls: list<array{isPoll: bool, options: list<string>, duration: int}>,
     *     images: list<array{url: string, path: string, name: string, postIndex: int}>,
     *     channelId: int|null,
     *     channelName: string,
     *     isPoll: bool,
     *     pollOptions: list<string>,
     *     pollDuration: int,
     *     anonymous: bool
     * }|null
     */
    public static function get(?string $askUsername = null): ?array
    {
        $raw = self::read(self::keyFor($askUsername));

        return $raw === null ? null : self::sanitize($raw);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function put(?string $askUsername, array $draft): void
    {
        $clean = self::sanitize($draft);

        if ($clean === null || self::isEmpty($clean)) {
            self::clear($askUsername);

            return;
        }

        self::write(self::keyFor($askUsername), $clean);
    }

    public static function clear(?string $askUsername = null): void
    {
        $key = self::keyFor($askUsername);

        session()->forget($key);

        try {
            SecureStorage::delete($key);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{
     *     content: string,
     *     images: list<array{url: string, path: string, name: string, postIndex: int}>,
     *     isPoll: bool,
     *     pollOptions: list<string>,
     *     pollDuration: int
     * }|null
     */
    public static function getReplyDraft(string $parentId): ?array
    {
        $raw = self::read(self::replyKeyFor($parentId));

        return $raw === null ? null : self::sanitizeReplyDraft($raw);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function putReplyDraft(string $parentId, array $draft): void
    {
        $clean = self::sanitizeReplyDraft($draft);

        if ($clean === null || (mb_trim($clean['content']) === '' && $clean['images'] === [] && ! $clean['isPoll'])) {
            self::clearReplyDraft($parentId);

            return;
        }

        self::write(self::replyKeyFor($parentId), $clean);
    }

    public static function clearReplyDraft(string $parentId): void
    {
        $key = self::replyKeyFor($parentId);

        session()->forget($key);

        try {
            SecureStorage::delete($key);
        } catch (Throwable) {
        }
    }

    /**
     * @param  array{
     *     content: string,
     *     posts: list<string>,
     *     postPolls: list<array{isPoll: bool, options: list<string>, duration: int}>,
     *     images: list<array{url: string, path: string, name: string, postIndex: int}>,
     *     channelId: int|null,
     *     channelName: string,
     *     isPoll: bool,
     *     pollOptions: list<string>,
     *     pollDuration: int,
     *     anonymous: bool
     * }  $draft
     */
    private static function isEmpty(array $draft): bool
    {
        if (mb_trim($draft['content']) !== '') {
            return false;
        }

        foreach ($draft['posts'] as $post) {
            if (mb_trim($post) !== '') {
                return false;
            }
        }

        if ($draft['isPoll'] || $draft['anonymous']) {
            return false;
        }

        if ($draft['images'] !== []) {
            return false;
        }

        if ($draft['channelId'] !== null || mb_trim($draft['channelName']) !== '') {
            return false;
        }

        foreach ($draft['postPolls'] as $poll) {
            if ($poll['isPoll']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     content: string,
     *     posts: list<string>,
     *     postPolls: list<array{isPoll: bool, options: list<string>, duration: int}>,
     *     images: list<array{url: string, path: string, name: string, postIndex: int}>,
     *     channelId: int|null,
     *     channelName: string,
     *     isPoll: bool,
     *     pollOptions: list<string>,
     *     pollDuration: int,
     *     anonymous: bool
     * }|null
     */
    private static function sanitize(mixed $draft): ?array
    {
        if (! is_array($draft)) {
            return null;
        }

        $posts = self::stringList($draft['posts'] ?? []);
        $postPolls = self::pollList($draft['postPolls'] ?? [], count($posts));
        $pollOptions = self::stringList($draft['pollOptions'] ?? ['', '']);

        if (count($pollOptions) < 2) {
            $pollOptions = ['', ''];
        }

        $channelId = $draft['channelId'] ?? null;

        if ($channelId !== null && is_numeric($channelId)) {
            $channelId = (int) $channelId;
        } else {
            $channelId = null;
        }

        $pollDuration = is_numeric($draft['pollDuration'] ?? null) ? (int) $draft['pollDuration'] : 1;

        if ($pollDuration < 1) {
            $pollDuration = 1;
        }

        return [
            'content' => isset($draft['content']) && is_string($draft['content']) ? $draft['content'] : '',
            'posts' => $posts,
            'postPolls' => $postPolls,
            'images' => self::imageList($draft['images'] ?? []),
            'channelId' => $channelId,
            'channelName' => isset($draft['channelName']) && is_string($draft['channelName']) ? $draft['channelName'] : '',
            'isPoll' => (bool) ($draft['isPoll'] ?? false),
            'pollOptions' => $pollOptions,
            'pollDuration' => $pollDuration,
            'anonymous' => (bool) ($draft['anonymous'] ?? false),
        ];
    }

    /**
     * @return array{
     *     content: string,
     *     images: list<array{url: string, path: string, name: string, postIndex: int}>,
     *     isPoll: bool,
     *     pollOptions: list<string>,
     *     pollDuration: int
     * }|null
     */
    private static function sanitizeReplyDraft(mixed $draft): ?array
    {
        if (! is_array($draft)) {
            return null;
        }

        $options = array_slice(self::stringList($draft['pollOptions'] ?? ['', '']), 0, 4);

        if (count($options) < 2) {
            $options = ['', ''];
        }

        $duration = is_numeric($draft['pollDuration'] ?? null) ? (int) $draft['pollDuration'] : 1;

        return [
            'content' => isset($draft['content']) && is_string($draft['content']) ? $draft['content'] : '',
            'images' => self::imageList($draft['images'] ?? []),
            'isPoll' => (bool) ($draft['isPoll'] ?? false),
            'pollOptions' => $options,
            'pollDuration' => min(7, max(1, $duration)),
        ];
    }

    /**
     * @return list<array{url: string, path: string, name: string, postIndex: int}>
     */
    private static function imageList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $images = [];
        $seen = [];

        foreach (array_values($values) as $image) {
            if (! is_array($image)) {
                continue;
            }

            $url = $image['url'] ?? null;
            $name = $image['name'] ?? null;

            if (! is_string($url) || $url === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $path = $image['path'] ?? '';
            $postIndex = is_int($image['postIndex'] ?? null) && $image['postIndex'] >= -1 ? $image['postIndex'] : -1;
            $identity = serialize([(is_string($path) && $path !== '' ? $path : $url), $postIndex]);

            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;

            $images[] = [
                'url' => $url,
                'path' => is_string($path) ? $path : '',
                'name' => $name,
                'postIndex' => $postIndex,
            ];
        }

        return $images;
    }

    /** @return list<string> */
    private static function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach (array_values($values) as $value) {
            $clean[] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
        }

        return $clean;
    }

    /**
     * @return list<array{isPoll: bool, options: list<string>, duration: int}>
     */
    private static function pollList(mixed $values, int $postCount): array
    {
        $polls = [];

        if (is_array($values)) {
            foreach (array_values($values) as $poll) {
                $polls[] = self::sanitizePoll($poll);
            }
        }

        while (count($polls) < $postCount) {
            $polls[] = ['isPoll' => false, 'options' => ['', ''], 'duration' => 1];
        }

        return array_slice($polls, 0, $postCount);
    }

    /**
     * @return array{isPoll: bool, options: list<string>, duration: int}
     */
    private static function sanitizePoll(mixed $poll): array
    {
        if (! is_array($poll)) {
            return ['isPoll' => false, 'options' => ['', ''], 'duration' => 1];
        }

        $options = self::stringList($poll['options'] ?? ['', '']);

        if (count($options) < 2) {
            $options = ['', ''];
        }

        $duration = is_numeric($poll['duration'] ?? null) ? (int) $poll['duration'] : 1;

        return [
            'isPoll' => (bool) ($poll['isPoll'] ?? false),
            'options' => $options,
            'duration' => max(1, $duration),
        ];
    }

    private static function keyFor(?string $askUsername): string
    {
        $account = self::accountKey();
        $target = mb_strtolower(mb_trim((string) $askUsername));
        $slot = $target === '' ? 'thread' : 'ask_'.$target;

        return self::PREFIX.$account.'_'.$slot;
    }

    private static function replyKeyFor(string $parentId): string
    {
        return self::REPLY_PREFIX.self::accountKey().'_'.hash('sha256', $parentId);
    }

    private static function accountKey(): string
    {
        $account = AccountStore::active();
        $username = is_array($account) ? Payload::string($account['username']) : '';

        if ($username === '') {
            $user = CurrentUser::get();
            $username = is_array($user) ? Payload::string($user['username'] ?? '') : '';
        }

        $username = mb_strtolower(mb_trim($username));

        return $username !== '' ? $username : 'default';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(string $key): ?array
    {
        $stored = session($key);

        if (is_array($stored)) {
            return self::stringKeys($stored);
        }

        try {
            $result = SecureStorage::read($key);

            if ($result->found() && is_string($result->value)) {
                $decoded = json_decode($result->value, true);

                if (is_array($decoded)) {
                    $draft = self::stringKeys($decoded);
                    session([$key => $draft]);

                    return $draft;
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    /**
     * JSON objects whose keys are numeric strings decode to integer keys, so
     * the draft shape is only guaranteed once the keys are spelled as strings.
     *
     * @param  array<array-key, mixed>  $draft
     * @return array<string, mixed>
     */
    private static function stringKeys(array $draft): array
    {
        $fields = [];

        foreach ($draft as $field => $value) {
            $fields[Payload::string($field)] = $value;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private static function write(string $key, array $draft): void
    {
        session([$key => $draft]);

        try {
            SecureStorage::set($key, json_encode($draft, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
        }
    }
}
