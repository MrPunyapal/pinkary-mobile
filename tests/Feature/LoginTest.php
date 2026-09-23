<?php

use App\NativeComponents\Login;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('shows a validation message for invalid login input', function (): void {
    Native::test(Login::class)
        ->input('email', 'not-an-email')
        ->tap('Log in')
        ->assertSee('valid email');
});

it('stores the token and opens the feed after login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'token' => 'test-token',
            'data' => [],
        ]),
    ]);

    Native::test(Login::class)
        ->input('email', 'pinkary@example.com')
        ->input('password', 'password')
        ->tap('Log in')
        ->assertReplacedWith('/feed');

    expect(AccountStore::activeToken())->toBe('test-token');
});
