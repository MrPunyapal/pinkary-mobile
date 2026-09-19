<?php

use App\Services\TokenStorage;

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
