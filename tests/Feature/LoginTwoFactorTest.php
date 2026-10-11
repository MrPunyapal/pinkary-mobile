<?php

declare(strict_types=1);

use App\NativeComponents\Login;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
});

it('asks for a code when the account needs two factor', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'message' => 'Two factor authentication is required to finish signing in.',
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate');

    expect($test->get('needsTwoFactor'))->toBeTrue()
        ->and($test->get('twoFactorChallenge'))->toBe('a-challenge')
        ->and($test->get('errorMessage'))->toBe('');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/login'));
});

it('does not call the challenge endpoint before a code is entered', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->set('twoFactorCode', '   ')
        ->call('submitTwoFactor');

    expect($test->get('errorMessage'))->toContain('Enter the code');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'two-factor-challenge'));
});

it('shows the server message when the code is refused', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
        'https://pinkary.test/api/v1/auth/login/two-factor-challenge' => Http::response([
            'message' => 'The two factor challenge is invalid or has expired.',
            'errors' => ['challenge' => ['The two factor challenge is invalid or has expired.']],
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->set('twoFactorCode', '000000')
        ->call('submitTwoFactor');

    expect($test->get('errorMessage'))->toContain('invalid or has expired');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/login/two-factor-challenge'));
});

it('sends the challenge and the code together', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
        'https://pinkary.test/api/v1/auth/login/two-factor-challenge' => Http::response([
            'token' => 'a-token',
            'data' => ['username' => 'pinkary'],
        ], 200),
    ]);

    Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->set('twoFactorCode', '123456')
        ->call('submitTwoFactor');

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/api/v1/auth/login/two-factor-challenge')) {
            return false;
        }

        return $request['challenge'] === 'a-challenge'
            && $request['code'] === '123456';
    });
});

it('sends a recovery code under the recovery_code field', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
        'https://pinkary.test/api/v1/auth/login/two-factor-challenge' => Http::response([
            'message' => 'The recovery code is invalid.',
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->call('toggleTwoFactorMethod')
        ->assertSee('Enter one of your recovery codes.')
        ->set('twoFactorCode', 'abcd-efgh')
        ->call('submitTwoFactor')
        ->assertSee('The recovery code is invalid.');

    expect($test->get('usingRecoveryCode'))->toBeTrue();

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/api/v1/auth/login/two-factor-challenge')) {
            return false;
        }

        return $request['challenge'] === 'a-challenge'
            && $request['recovery_code'] === 'abcd-efgh'
            && ! array_key_exists('code', $request->data());
    });
});

it('shows a connection error when login cannot reach the API', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection failed.'));

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate');

    expect($test->get('busy'))->toBeFalse()
        ->and($test->get('errorMessage'))->toContain('could not connect');
});

it('goes back to the password form when the challenge is abandoned', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'code' => 'two_factor_required',
            'challenge' => 'a-challenge',
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->set('twoFactorCode', '123456')
        ->call('cancelTwoFactor');

    expect($test->get('needsTwoFactor'))->toBeFalse()
        ->and($test->get('twoFactorChallenge'))->toBe('')
        ->and($test->get('twoFactorCode'))->toBe('')
        ->and($test->get('email'))->toBe('pinkary@example.com');
});

it('a plain failed login is not mistaken for a two factor prompt', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'message' => 'The provided credentials are incorrect.',
            'errors' => ['email' => ['The provided credentials are incorrect.']],
        ], 422),
    ]);

    $test = Native::test(Login::class)
        ->set('email', 'pinkary@example.com')
        ->set('password', 'wrong')
        ->call('authenticate');

    expect($test->get('needsTwoFactor'))->toBeFalse()
        ->and($test->get('errorMessage'))->toContain('credentials are incorrect');
});
