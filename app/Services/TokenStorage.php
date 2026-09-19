<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;

final readonly class TokenStorage
{
    private const string KEY = 'pinkary_api_token';

    /**
     * Read the API token from the native keychain/keystore, falling back
     * to the session when the native bridge is unavailable (tests, web).
     */
    public function get(): ?string
    {
        if (function_exists('nativephp_call')) {
            $stored = SecureStorage::get(self::KEY);

            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        }

        $token = session(self::KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Persist the API token in the native keychain/keystore and mirror
     * it to the session for the current request cycle.
     */
    public function store(string $token): void
    {
        if (function_exists('nativephp_call')) {
            SecureStorage::set(self::KEY, $token);
        }

        session([self::KEY => $token]);
    }

    /**
     * Remove the API token from both the native keychain/keystore
     * and the session.
     */
    public function clear(): void
    {
        if (function_exists('nativephp_call')) {
            SecureStorage::delete(self::KEY);
        }

        session()->forget(self::KEY);
    }
}
