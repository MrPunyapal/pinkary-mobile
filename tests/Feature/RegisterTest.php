<?php

use App\NativeComponents\Register;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('requires the terms agreement', function (): void {
    Native::test(Register::class)
        ->tap('Create account')
        ->assertSee('terms');
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
        ->tap('Create account')
        ->assertReplacedWith('/feed');

    expect(session('pinkary_api_token'))->toBe('test-token');
});
