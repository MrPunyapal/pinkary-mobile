<?php

declare(strict_types=1);

namespace App\Support;

final class CurrentUser
{
    private const string KEY = 'pinkary_user';

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function store(array $profile): void
    {
        session([self::KEY => [
            'name' => (string) data_get($profile, 'name', ''),
            'username' => (string) data_get($profile, 'username', ''),
            'avatar' => data_get($profile, 'avatar'),
            'email' => data_get($profile, 'email'),
            'member_since' => data_get($profile, 'member_since.human'),
        ]]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $user = session(self::KEY);

        return is_array($user) ? $user : null;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
