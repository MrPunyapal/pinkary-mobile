<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\SecureStorage;
use Throwable;

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

        try {
            SecureStorage::set(self::KEY, json_encode($data));
        } catch (Throwable) {
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

        try {
            $result = SecureStorage::read(self::KEY);

            if ($result->found() && is_string($result->value)) {
                $decoded = json_decode($result->value, true);
                if (is_array($decoded)) {
                    session([self::KEY => $decoded]);

                    return $decoded;
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
}
