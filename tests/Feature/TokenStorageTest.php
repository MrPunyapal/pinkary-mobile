<?php

use App\Services\TokenStorage;
use Native\Mobile\Testing\FakeBridge;

beforeEach(function (): void {
    // Pin the bridge to its in-test fake so secure-storage calls resolve
    // deterministically instead of reaching the live Jump bridge over TCP.
    FakeBridge::enable();
});

afterEach(function (): void {
    FakeBridge::disable();
});

it('stores the token so it can be read back', function (): void {
    app(TokenStorage::class)->store('test-token');

    expect(app(TokenStorage::class)->get())->toBe('test-token')
        ->and(session('pinkary_api_token'))->toBe('test-token');
});

it('clears the token', function (): void {
    app(TokenStorage::class)->store('test-token');
    app(TokenStorage::class)->clear();

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(session('pinkary_api_token'))->toBeNull();
});

it('returns null when nothing is stored', function (): void {
    session()->forget('pinkary_api_token');

    expect(app(TokenStorage::class)->get())->toBeNull();
});
