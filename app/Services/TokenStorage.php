<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AccountStore;

final readonly class TokenStorage
{
    /**
     * Read the active account's API token. Backed by AccountStore, so the
     * native keychain/keystore holds one token per account and this
     * always resolves the one in use. The session is only a fallback for
     * environments without a native bridge (tests, local preview) — on
     * device the session is never touched.
     */
    public function get(): ?string
    {
        return AccountStore::activeToken();
    }

    /**
     * Persist the API token for a username (insert or replace that
     * account, then make it active). Without a username the token joins
     * the active account, preserving the legacy single-token contract.
     */
    public function store(string $token, ?string $username = null): void
    {
        AccountStore::add(
            $username ?? AccountStore::active()['username'] ?? '',
            $token,
            []
        );
    }

    /**
     * Remove the active account's token (falling through to the next
     * account), backing the same bridge-then-session contract as get().
     */
    public function clear(): void
    {
        AccountStore::removeActive();
    }
}
