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
        $data = [
            'name' => Payload::string(data_get($profile, 'name', '')),
            'username' => Payload::string(data_get($profile, 'username', '')),
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
            return self::fields($user);
        }

        $account = AccountStore::active();

        return $account === null ? null : self::fields($account);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * @param  array<array-key, mixed>  $user
     * @return array<string, mixed>
     */
    private static function fields(array $user): array
    {
        return [
            'name' => $user['name'] ?? null,
            'username' => $user['username'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'email' => $user['email'] ?? null,
            'member_since' => $user['member_since'] ?? null,
        ];
    }
}
