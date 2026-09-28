<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class FeedCache
{
    /**
     * @return array{items: list<array<string, mixed>>, page: int, hasMore: bool, unreadCount?: int}|null
     */
    public static function get(string $key): ?array
    {
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return null;
        }

        $items = [];

        foreach ($payload['items'] as $item) {
            if (! is_array($item)) {
                return null;
            }

            /** @var array<string, mixed> $item */
            $items[] = $item;
        }

        $page = $payload['page'] ?? null;
        $hasMore = $payload['hasMore'] ?? null;

        if (! is_int($page) || ! is_bool($hasMore)) {
            return null;
        }

        $result = [
            'items' => $items,
            'page' => $page,
            'hasMore' => $hasMore,
        ];

        if (array_key_exists('unreadCount', $payload)) {
            if (! is_int($payload['unreadCount'])) {
                return null;
            }

            $result['unreadCount'] = $payload['unreadCount'];
        }

        return $result;
    }

    /**
     * @param  array{items: list<array<string, mixed>>, page: int, hasMore: bool, unreadCount?: int}  $payload
     *
     * The default TTL is deliberately short. Every screen that reads this
     * cache also refetches on mount, so the cache only paints content
     * before the network answers -- it is not a source of truth. A long
     * TTL would let a cold start paint a list that is many minutes stale
     * before the fresh response replaces it, which reads as "the feed is
     * out of date" even though the refetch is in flight.
     */
    public static function put(string $key, array $payload, int $seconds = 90): void
    {
        Cache::put($key, $payload, $seconds);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
    }

    public static function feedKey(?string $username, string $tab): string
    {
        return 'pinkary:feed:'.self::normalizeUsername($username).':'.$tab;
    }

    public static function bookmarksKey(string $username): string
    {
        return 'pinkary:bookmarks:'.self::normalizeUsername($username);
    }

    public static function notificationsKey(string $username): string
    {
        return 'pinkary:notifications:'.self::normalizeUsername($username);
    }

    private static function normalizeUsername(?string $username): string
    {
        return $username !== null && $username !== '' ? $username : 'guest';
    }
}
