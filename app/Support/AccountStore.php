<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Native\Mobile\Facades\SecureStorage;

/** Use list() in views; token-bearing payloads must never be rendered. */
final class AccountStore
{
    private const string ACCOUNTS_KEY = 'pinkary_accounts';

    private const string ACTIVE_KEY = 'pinkary_active_account';

    private const string FILE = 'pinkary-accounts.enc';

    /**
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
        [$accounts, $activeId] = self::read();

        return ($activeId === null ? null : self::find($accounts, $activeId)) ?? ($accounts[0] ?? null);
    }

    public static function tokenFor(string $id): ?string
    {
        $account = self::find(self::readAll(), $id);

        return $account === null ? null : $account['token'];
    }

    public static function tokenForUsername(string $username): ?string
    {
        foreach (self::readAll() as $account) {
            if (mb_strtolower($account['username']) === mb_strtolower($username)) {
                return $account['token'];
            }
        }

        return null;
    }

    /**
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
     * @param  array<string, mixed>  $profile
     */
    public static function add(string $username, string $token, array $profile): string
    {
        [$accounts] = self::read();

        $accounts = array_values(array_filter(
            $accounts,
            fn (array $account): bool => mb_strtolower($account['username']) !== mb_strtolower($username)
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
        self::write([], null);
        SecureStorage::delete(self::ACCOUNTS_KEY);
        SecureStorage::delete(self::ACTIVE_KEY);
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
        $path = self::path();

        if (File::exists($path)) {
            try {
                $decoded = json_decode(Crypt::decryptString(File::get($path)), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|JsonException) {
                return [[], null];
            }

            return is_array($decoded)
                ? [self::sanitize($decoded['accounts'] ?? []), self::stringOrNull($decoded['active'] ?? null)]
                : [[], null];
        }

        $result = SecureStorage::read(self::ACCOUNTS_KEY);

        if ($result->found() && is_string($result->value)) {
            $decoded = json_decode($result->value, true);

            if (is_array($decoded)) {
                $activeResult = SecureStorage::read(self::ACTIVE_KEY);
                $activeId = self::stringOrNull($decoded['active'] ?? $activeResult->value);
                $accounts = self::sanitize($decoded['accounts'] ?? []);
                self::write($accounts, $activeId);
                SecureStorage::delete(self::ACCOUNTS_KEY);
                SecureStorage::delete(self::ACTIVE_KEY);

                return [$accounts, $activeId];
            }
        }

        $fallback = session(self::ACCOUNTS_KEY, []);
        $accounts = self::sanitize($fallback);
        $activeId = self::stringOrNull(session(self::ACTIVE_KEY));

        if ($accounts !== []) {
            self::write($accounts, $activeId);
        }

        return [$accounts, $activeId];
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
        $payload = json_encode(['accounts' => array_values($accounts), 'active' => $activeId], JSON_THROW_ON_ERROR);
        $path = self::path();

        File::ensureDirectoryExists(dirname($path), 0700);
        File::replace($path, Crypt::encryptString($payload), 0600);
        session()->forget([self::ACCOUNTS_KEY, self::ACTIVE_KEY]);
    }

    private static function path(): string
    {
        return Storage::disk('local')->path(self::FILE);
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
