<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;

final class PostViews
{
    private const string VIEWER_KEY = 'pinkary_impression_viewer';

    public static function viewerId(): string
    {
        $stored = SecureStorage::read(self::VIEWER_KEY);
        $viewer = $stored->found() ? $stored->value : session(self::VIEWER_KEY);
        if (is_string($viewer) && Str::isUuid($viewer)) {
            return $viewer;
        }

        $viewer = (string) Str::uuid();
        if (! SecureStorage::set(self::VIEWER_KEY, $viewer)) {
            session([self::VIEWER_KEY => $viewer]);
        }

        return $viewer;
    }

    public static function claim(string $id, ?string $token, string $viewer): ?string
    {
        $key = 'pinkary:impression:'.hash('sha256', $token ?? $viewer).':'.$id;

        return Cache::add($key, true, 30) ? $key : null;
    }

    public static function finish(string $key, bool $accepted): void
    {
        if ($accepted) {
            Cache::put($key, true, now()->addHours(2));
        } else {
            Cache::forget($key);
        }
    }
}
