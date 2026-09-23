<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\SecureStorage;
use Throwable;

/**
 * Pending notification count for the tab-bar bell.
 *
 * The session mirrors the value for the current request. On device the
 * session is not durable, so the count is also written to secure storage
 * — the same split CurrentUser uses — otherwise the badge is always empty
 * after the first screen.
 */
final class UnreadCount
{
    private const string KEY = 'pinkary_unread_count';

    public static function store(int $count): void
    {
        $count = max(0, $count);

        session([self::KEY => $count]);

        try {
            SecureStorage::set(self::KEY, (string) $count);
        } catch (Throwable) {
            // Native bridge unavailable
        }
    }

    public static function get(): ?int
    {
        $count = self::normalize(session(self::KEY));

        if ($count !== null) {
            return $count;
        }

        try {
            $result = SecureStorage::read(self::KEY);

            if ($result->found()) {
                $count = self::normalize($result->value);

                if ($count !== null) {
                    session([self::KEY => $count]);

                    return $count;
                }
            }
        } catch (Throwable) {
            // Native bridge unavailable
        }

        return null;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);

        try {
            SecureStorage::delete(self::KEY);
        } catch (Throwable) {
            // Native bridge unavailable
        }
    }

    private static function normalize(mixed $count): ?int
    {
        if (is_int($count)) {
            return max(0, $count);
        }

        if (is_string($count) && is_numeric($count)) {
            return max(0, (int) $count);
        }

        return null;
    }
}
