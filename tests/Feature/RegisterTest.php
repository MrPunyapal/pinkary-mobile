<?php

declare(strict_types=1);

use App\NativeComponents\Register;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('requires the terms agreement', function (): void {
    Native::test(Register::class)
        ->tap('Register')
        ->assertSee('terms');
});

it('states the web consent sentence with both legal pages linked', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    Native::test(Register::class)
        ->assertSee('By signing up, I confirm that I am at least 18 years old and accept the')
        ->assertSee('Terms of Service')
        ->assertSee('Privacy Policy')
        ->assertSee('Set up your Pinkary profile and start sharing your links and conversations.')
        ->assertSee('Already have an account?')
        ->assertSee('Sign in here');
});

it('opens the terms and privacy pages of the site in the in-app browser', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    Native::test(Register::class)
        ->tap('Open the Terms of Service')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? '') === 'https://pinkary.test/terms');

    Native::test(Register::class)
        ->tap('Open the Privacy Policy')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? '') === 'https://pinkary.test/privacy');
});

it('stores the token and opens the feed after registration', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/auth/register' => Http::response([
            'token' => 'test-token',
            'data' => [],
        ], 201),
    ]);

    Native::test(Register::class)
        ->input('name', 'Pinkary User')
        ->input('username', 'pinkaryuser')
        ->input('email', 'pinkary@example.com')
        ->input('password', 'password')
        ->input('passwordConfirmation', 'password')
        ->toggle('terms', true)
        ->tap('Register')
        ->assertReplacedWith('/feed');

    expect(AccountStore::activeToken())->toBe('test-token');
});
