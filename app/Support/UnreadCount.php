<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\SecureStorage;
use Throwable;

/** Native sessions are not durable across screens; secure storage persists the badge count. */
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
        }

        return null;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);

        try {
            SecureStorage::delete(self::KEY);
        } catch (Throwable) {
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
