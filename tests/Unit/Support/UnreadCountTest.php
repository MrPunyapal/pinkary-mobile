<?php

declare(strict_types=1);

use App\Support\UnreadCount;
use Native\Mobile\Facades\SecureStorage;
use Native\Mobile\SecureStorageResult;
use Native\Mobile\Testing\FakeBridge;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    UnreadCount::clear();
});

afterEach(function (): void {
    SecureStorage::clearResolvedInstance(Native\Mobile\SecureStorage::class);
});

it('clamps a negative count before persisting it', function (): void {
    UnreadCount::store(-3);

    expect(UnreadCount::get())->toBe(0)
        ->and(session('pinkary_unread_count'))->toBe(0);
});

it('persists the count to secure storage as well as the session', function (): void {
    UnreadCount::store(4);

    FakeBridge::current()?->assertCalled('SecureStorage.Set', fn (array $params): bool => ($params['key'] ?? null) === 'pinkary_unread_count'
        && ($params['value'] ?? null) === '4');
});

it('restores the count from secure storage when the session is gone', function (): void {
    FakeBridge::enable()->respondTo('SecureStorage.Get', ['value' => '5']);
    session()->forget('pinkary_unread_count');

    expect(UnreadCount::get())->toBe(5)
        ->and(session('pinkary_unread_count'))->toBe(5);
});

it('returns null when secure storage holds nothing usable', function (string $stored): void {
    FakeBridge::enable()->respondTo('SecureStorage.Get', ['value' => $stored]);
    session()->forget('pinkary_unread_count');

    expect(UnreadCount::get())->toBeNull();
})->with([
    'empty string' => '',
    'not a number' => 'not-a-number',
]);

it('keeps the session value ahead of secure storage', function (): void {
    UnreadCount::store(2);
    FakeBridge::enable()->respondTo('SecureStorage.Get', ['value' => '99']);

    expect(UnreadCount::get())->toBe(2);
});

it('survives an unavailable keychain', function (): void {
    SecureStorage::swap(new class
    {
        public function set(string $key, ?string $value): bool
        {
            throw new RuntimeException('keychain unavailable');
        }

        public function read(string $key): SecureStorageResult
        {
            throw new RuntimeException('keychain unavailable');
        }

        public function delete(string $key): bool
        {
            throw new RuntimeException('keychain unavailable');
        }
    });

    UnreadCount::store(4);

    expect(UnreadCount::get())->toBe(4);

    session()->forget('pinkary_unread_count');

    expect(UnreadCount::get())->toBeNull();

    UnreadCount::clear();

    expect(session()->has('pinkary_unread_count'))->toBeFalse();
});
