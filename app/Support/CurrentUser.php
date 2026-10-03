<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

final class CurrentUser
{
    private const string KEY = 'pinkary_user';

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function store(array $profile): void
    {
        $data = [
            'name' => (string) data_get($profile, 'name', ''),
            'username' => (string) data_get($profile, 'username', ''),
            'avatar' => data_get($profile, 'avatar'),
            'email' => data_get($profile, 'email'),
            'member_since' => data_get($profile, 'member_since.human'),
        ];

        session([self::KEY => $data]);

        $account = AccountStore::active();

        if ($account !== null && $account['username'] === $data['username']) {
            AccountStore::refreshProfile($account['id'], $profile);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $user = session(self::KEY);

        if (is_array($user)) {
            return $user;
        }

        $account = AccountStore::active();

        return $account === null ? null : Arr::only($account, ['name', 'username', 'avatar', 'email', 'member_since']);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
