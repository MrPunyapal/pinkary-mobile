<?php

use App\Services\TokenStorage;
use App\Support\AccountStore;
use Native\Mobile\Testing\FakeBridge;

beforeEach(function (): void {
    // Pin the bridge to its in-test fake so secure-storage calls resolve
    // deterministically instead of reaching the live Jump bridge over TCP.
    FakeBridge::enable();
    AccountStore::clear();
});

afterEach(function (): void {
    AccountStore::clear();
    FakeBridge::disable();
});

it('stores the token so it can be read back', function (): void {
    app(TokenStorage::class)->store('test-token', 'ada', ['username' => 'ada']);

    expect(app(TokenStorage::class)->get())->toBe('test-token')
        ->and(AccountStore::activeToken())->toBe('test-token');
});

it('clears the token', function (): void {
    app(TokenStorage::class)->store('test-token', 'ada', ['username' => 'ada']);
    app(TokenStorage::class)->clear();

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(AccountStore::activeToken())->toBeNull();
});

it('returns null when nothing is stored', function (): void {
    AccountStore::clear();

    expect(app(TokenStorage::class)->get())->toBeNull();
});
