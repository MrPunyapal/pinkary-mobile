<?php

declare(strict_types=1);

namespace App\Support;

final class UnreadCount
{
    private const string KEY = 'pinkary_unread_count';

    public static function store(int $count): void
    {
        session([self::KEY => max(0, $count)]);
    }

    public static function get(): ?int
    {
        $count = session(self::KEY);

        return is_int($count) ? $count : null;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
