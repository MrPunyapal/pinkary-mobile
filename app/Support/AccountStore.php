<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;

/**
 * Multi-account token store (web multi-login parity).
 *
 * Every account keeps its own Sanctum token in the native
 * keychain/keystore, which encrypts values at rest and only releases
 * them after a device unlock. The session merely mirrors the payload
 * when the native bridge is unavailable (tests, local preview) — on
 * device the session is never touched.
 *
 * Views must use list() (tokens stripped). Tokens leave this class only
 * through activeToken()/tokenFor()/tokenForUsername()/allTokens(),
 * straight into an API call — never into Blade.
 */
final class AccountStore
{
    private const string ACCOUNTS_KEY = 'pinkary_accounts';

    private const string ACTIVE_KEY = 'pinkary_active_account';

    /**
     * Accounts for the switcher UI, newest first. Tokens are stripped —
     * safe to hand to Blade.
     *
     * @return list<array{id: string, username: string, name: string, avatar: mixed}>
     */
    public static function list(): array
    {
        return array_map(fn (array $account): array => [
            'id' => $account['id'],
            'username' => $account['username'],
            'name' => $account['name'],
            'avatar' => $account['avatar'],
        ], self::readAll());
    }

    public static function count(): int
    {
        return count(self::readAll());
    }

    public static function activeId(): ?string
    {
        [$accounts, $activeId] = self::read();

        if ($activeId !== null && self::find($accounts, $activeId) !== null) {
            return $activeId;
        }

        return $accounts === [] ? null : $accounts[0]['id'];
    }

    public static function activeToken(): ?string
    {
        $account = self::active();

        return $account === null ? null : $account['token'];
    }

    /**
     * @return array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}|null
     */
    public static function active(): ?array
    {
        [$accounts] = self::read();
        $activeId = self::activeId();

        return $activeId === null ? null : self::find($accounts, $activeId);
    }

    public static function tokenFor(string $id): ?string
    {
        $account = self::find(self::readAll(), $id);

        return $account === null ? null : $account['token'];
    }

    public static function tokenForUsername(string $username): ?string
    {
        foreach (self::readAll() as $account) {
            if (strtolower($account['username']) === strtolower($username)) {
                return $account['token'];
            }
        }

        return null;
    }

    /**
     * Every stored token (for logout-all revocation). Internal use only —
     * never pass to Blade.
     *
     * @return list<string>
     */
    public static function allTokens(): array
    {
        return array_values(array_map(
            fn (array $account): string => $account['token'],
            self::readAll()
        ));
    }

    /**
     * Insert or replace the account for a username (usernames match
     * case-insensitively) and make it active. Returns the account id.
     *
     * @param  array<string, mixed>  $profile
     */
    public static function add(string $username, string $token, array $profile): string
    {
        [$accounts] = self::read();

        $accounts = array_values(array_filter(
            $accounts,
            fn (array $account): bool => strtolower($account['username']) !== strtolower($username)
        ));

        $id = Str::random(32);

        array_unshift($accounts, [
            'id' => $id,
            'username' => $username,
            'name' => (string) data_get($profile, 'name', ''),
            'avatar' => data_get($profile, 'avatar'),
            'email' => data_get($profile, 'email'),
            'member_since' => data_get($profile, 'member_since.human', data_get($profile, 'member_since')),
            'token' => $token,
        ]);

        self::write($accounts, $id);

        return $id;
    }

    /**
     * Refresh the stored profile snapshot (name/avatar) without moving
     * the account or touching its token.
     *
     * @param  array<string, mixed>  $profile
     */
    public static function refreshProfile(string $id, array $profile): void
    {
        [$accounts, $activeId] = self::read();
        $changed = false;

        foreach ($accounts as &$account) {
            if ($account['id'] !== $id) {
                continue;
            }

            $account['name'] = (string) data_get($profile, 'name', $account['name']);
            $account['avatar'] = data_get($profile, 'avatar', $account['avatar']);
            $account['email'] = data_get($profile, 'email', $account['email'] ?? null);
            $account['member_since'] = data_get($profile, 'member_since.human', data_get($profile, 'member_since', $account['member_since'] ?? null));
            $changed = true;

            break;
        }
        unset($account);

        if ($changed) {
            self::write($accounts, $activeId);
        }
    }

    public static function activate(string $id): bool
    {
        [$accounts, $activeId] = self::read();

        if (self::find($accounts, $id) === null) {
            return false;
        }

        if ($activeId !== $id) {
            self::write($accounts, $id);
        }

        return true;
    }

    /**
     * Remove an account. When the active account goes away, the newest
     * remaining one takes over (web parity: logout falls through to the
     * next account instead of stranding the user).
     */
    public static function remove(string $id): void
    {
        [$accounts, $activeId] = self::read();

        $accounts = array_values(array_filter(
            $accounts,
            fn (array $account): bool => $account['id'] !== $id
        ));

        if ($activeId === $id) {
            $activeId = $accounts === [] ? null : $accounts[0]['id'];
        }

        self::write($accounts, $activeId);
    }

    public static function removeActive(): void
    {
        $activeId = self::activeId();

        if ($activeId !== null) {
            self::remove($activeId);
        }
    }

    public static function clear(): void
    {
        $accountsDeleted = SecureStorage::delete(self::ACCOUNTS_KEY);
        $activeDeleted = SecureStorage::delete(self::ACTIVE_KEY);

        if (! $accountsDeleted || ! $activeDeleted) {
            session()->forget([self::ACCOUNTS_KEY, self::ACTIVE_KEY]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}|null
     */
    private static function find(array $accounts, string $id): ?array
    {
        foreach ($accounts as $account) {
            if ($account['id'] === $id) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @return array{0: list<array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}>, 1: ?string}
     */
    private static function read(): array
    {
        $result = SecureStorage::read(self::ACCOUNTS_KEY);

        if ($result->found() && is_string($result->value)) {
            $decoded = json_decode($result->value, true);

            if (is_array($decoded)) {
                $activeResult = SecureStorage::read(self::ACTIVE_KEY);
                $activeId = $activeResult->found() && is_string($activeResult->value) && $activeResult->value !== ''
                    ? $activeResult->value
                    : null;

                return [self::sanitize($decoded['accounts'] ?? []), $activeId];
            }
        }

        if ($result->failed()) {
            $fallback = session(self::ACCOUNTS_KEY, []);

            return [
                self::sanitize(is_array($fallback) ? $fallback : []),
                self::stringOrNull(session(self::ACTIVE_KEY)),
            ];
        }

        return [[], null];
    }

    /** @return list<array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}> */
    private static function readAll(): array
    {
        return self::read()[0];
    }

    /**
     * @param  list<array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}>  $accounts
     */
    private static function write(array $accounts, ?string $activeId): void
    {
        $payload = json_encode(['accounts' => array_values($accounts), 'active' => $activeId]);

        if (! SecureStorage::set(self::ACCOUNTS_KEY, (string) $payload)) {
            session([self::ACCOUNTS_KEY => $accounts, self::ACTIVE_KEY => $activeId]);
        } elseif ($activeId !== null) {
            SecureStorage::set(self::ACTIVE_KEY, $activeId);
        } else {
            SecureStorage::delete(self::ACTIVE_KEY);
        }
    }

    /**
     * @return list<array{id: string, username: string, name: string, avatar: mixed, email: mixed, member_since: mixed, token: string}>
     */
    private static function sanitize(mixed $accounts): array
    {
        if (! is_array($accounts)) {
            return [];
        }

        $clean = [];

        foreach (array_values($accounts) as $account) {
            if (! is_array($account)
                || ! isset($account['id'], $account['token'])
                || ! is_string($account['id']) || $account['id'] === ''
                || ! is_string($account['token']) || $account['token'] === '') {
                continue;
            }

            $clean[] = [
                'id' => $account['id'],
                'username' => isset($account['username']) && is_string($account['username']) ? $account['username'] : '',
                'name' => isset($account['name']) && is_string($account['name']) ? $account['name'] : '',
                'avatar' => $account['avatar'] ?? null,
                'email' => $account['email'] ?? null,
                'member_since' => $account['member_since'] ?? null,
                'token' => $account['token'],
            ];
        }

        return $clean;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
