<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AccountStore;

final readonly class TokenStorage
{
    public function get(): ?string
    {
        return AccountStore::activeToken();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function store(string $token, string $username, array $profile = []): void
    {
        AccountStore::add($username, $token, $profile);
    }

    public function clear(): void
    {
        AccountStore::removeActive();
    }
}
