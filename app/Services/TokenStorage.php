<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;

final readonly class TokenStorage
{
    private const string KEY = 'pinkary_api_token';

    /**
     * Read the API token from the native keychain/keystore. The session
     * is only a fallback for environments without a native bridge
     * (tests, local preview) — on device the session is never touched.
     */
    public function get(): ?string
    {
        $result = SecureStorage::read(self::KEY);

        if ($result->found()) {
            return $result->value;
        }

        if ($result->failed()) {
            $token = session(self::KEY);

            return is_string($token) && $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * Persist the API token in the native keychain/keystore, falling back
     * to the session only when the native bridge is unavailable.
     */
    public function store(string $token): void
    {
        if (! SecureStorage::set(self::KEY, $token)) {
            session([self::KEY => $token]);
        }
    }

    /**
     * Remove the API token from the native keychain/keystore, falling back
     * to the session only when the native bridge is unavailable.
     */
    public function clear(): void
    {
        if (! SecureStorage::delete(self::KEY)) {
            session()->forget(self::KEY);
        }
    }
}
