<?php

declare(strict_types=1);

use App\NativeComponents\AccountSecurity;
use App\Support\AccountStore;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function accountSecurityEndpoint(string $path): string
{
    return mb_rtrim((string) config('services.pinkary.url'), '/').'/api/v1/account/'.$path;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountSecurityResponses(array $overrides = []): array
{
    return array_replace([
        accountSecurityEndpoint('email-verification') => Http::response([
            'email' => 'ada@example.com',
            'verified' => false,
        ]),
        accountSecurityEndpoint('two-factor') => Http::response([
            'enabled' => false,
            'pending_confirmation' => false,
        ]),
        accountSecurityEndpoint('github') => Http::response([
            'connected' => false,
            'eligible' => false,
        ]),
    ], $overrides);
}

function signInAccountSecurityUser(string $username = 'ada', string $token = 'security-token'): void
{
    AccountStore::add($username, $token, ['name' => 'Ada Lovelace', 'username' => $username]);
}

it('loads the email, two-factor, and GitHub status for the active account', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification') => Http::response([
            'email' => 'ada@example.com',
            'verified' => true,
            'verified_at' => 'October 10, 2026',
        ]),
        accountSecurityEndpoint('two-factor') => Http::response([
            'enabled' => true,
            'pending_confirmation' => false,
        ]),
        accountSecurityEndpoint('github') => Http::response([
            'connected' => true,
            'eligible' => false,
            'github_username' => 'ada-lovelace',
            'verification' => ['profile' => true],
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->assertSee('ada@example.com')
        ->assertSee('Your email address is verified.')
        ->assertSee('Connected as @ada-lovelace');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('emailVerifiedAt'))->toBe('October 10, 2026')
        ->and($test->get('twoFactorStatusLoaded'))->toBeTrue()
        ->and($test->get('twoFactorEnabled'))->toBeTrue()
        ->and($test->get('profileVerified'))->toBeTrue();
});

it('retries loading account status after the email status request fails', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification') => Http::sequence()
            ->push(['message' => 'Email status unavailable.'], 503)
            ->push(['email' => 'ada@example.com', 'verified' => false]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->assertSee('Email status unavailable.')
        ->call('retry')
        ->assertSee('ada@example.com');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('errorMessage'))->toBe('')
        ->and($test->get('twoFactorStatusLoaded'))->toBeTrue();
});

it('keeps two-factor actions unavailable when its status cannot be loaded', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response([
            'message' => 'Two-factor status unavailable.',
        ], 503),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->assertSee('Two-factor status unavailable.')
        ->assertSee('Two-factor status could not be loaded. Refresh the account status before making changes.');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('email'))->toBe('ada@example.com')
        ->and($test->get('twoFactorStatusLoaded'))->toBeFalse();
});

it('removes the active account and returns to login when status responds with unauthorized', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification') => Http::response(null, 401),
    ]));

    Native::test(AccountSecurity::class)->assertReplacedWith('/login');

    expect(AccountStore::count())->toBe(0);
});

it('sends a verification email and displays the server response', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification/notification') => Http::response([
            'verified' => true,
            'message' => 'Check your inbox for the verification link.',
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('sendVerificationEmail')
        ->assertSee('Your email address is verified.')
        ->assertSee('Check your inbox for the verification link.');

    expect($test->get('busy'))->toBeFalse()
        ->and($test->get('verificationMessage'))->toBe('Check your inbox for the verification link.');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/email-verification/notification')
        && $request->header('Authorization') === ['Bearer security-token']);
});

it('refreshes email verification after the user follows the email link', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification') => Http::sequence()
            ->push(['email' => 'ada@example.com', 'verified' => false])
            ->push(['email' => 'ada@example.com', 'verified' => true]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->assertSee('Your email address is not verified.')
        ->call('refreshStatus')
        ->assertSee('Your email address is verified.');

    expect($test->get('emailVerified'))->toBeTrue();
});

it('shows an error and resets the busy state when a verification email cannot be sent', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('email-verification/notification') => Http::response([
            'message' => 'Email delivery is temporarily unavailable.',
        ], 503),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('sendVerificationEmail')
        ->assertSee('Email delivery is temporarily unavailable.');

    expect($test->get('busy'))->toBeFalse()
        ->and($test->get('verificationMessage'))->toBe('');
});

it('does not send a password change when confirmation does not match', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses());

    $test = Native::test(AccountSecurity::class)
        ->set('currentPassword', 'current-password')
        ->set('newPassword', 'new-password')
        ->set('passwordConfirmation', 'different-password')
        ->call('changePassword')
        ->assertSee('The password field confirmation does not match.');

    expect($test->get('busy'))->toBeFalse();

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/account/password'));
});

it('changes the password and clears the submitted password fields', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('password') => Http::response(['message' => 'Password updated.']),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('currentPassword', 'current-password')
        ->set('newPassword', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('changePassword')
        ->assertSee('Your password has been changed.');

    expect($test->get('currentPassword'))->toBe('')
        ->and($test->get('newPassword'))->toBe('')
        ->and($test->get('passwordConfirmation'))->toBe('')
        ->and($test->get('busy'))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/v1/account/password')
        && $request['current_password'] === 'current-password'
        && $request['password'] === 'new-password'
        && $request['password_confirmation'] === 'new-password');
});

it('keeps password fields available when the API rejects the change', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('password') => Http::response([
            'message' => 'The current password is incorrect.',
        ], 422),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('changePassword')
        ->assertSee('The current password is incorrect.');

    expect($test->get('busy'))->toBeFalse()
        ->and($test->get('currentPassword'))->toBe('wrong-password')
        ->and($test->get('newPassword'))->toBe('new-password')
        ->and($test->get('passwordConfirmation'))->toBe('new-password');
});

it('requires the current password before starting two-factor setup', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses());

    $test = Native::test(AccountSecurity::class)
        ->call('startTwoFactor')
        ->assertSee('Enter your current password to start two-factor setup.');

    expect($test->get('busy'))->toBeFalse();

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/two-factor'));
});

it('shows the authenticator setup details returned by the API', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response([
            'secret' => 'SETUP-SECRET',
            'qr_code_svg' => '<svg />',
            'pending_confirmation' => true,
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('twoFactorPassword', 'current-password')
        ->call('startTwoFactor')
        ->assertSee('Setup key: SETUP-SECRET')
        ->assertSee('Scan the code, then confirm with a code from your authenticator app.');

    expect($test->get('twoFactorPending'))->toBeTrue()
        ->and($test->get('twoFactorSecret'))->toBe('SETUP-SECRET')
        ->and($test->get('twoFactorQrCode'))->toBe('data:image/svg+xml;base64,PHN2ZyAvPg==')
        ->and($test->get('twoFactorPassword'))->toBe('')
        ->and($test->get('busy'))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/two-factor')
        && $request['current_password'] === 'current-password');
});

it('requires both a password and authenticator code before confirming setup', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response([
            'enabled' => false,
            'pending_confirmation' => true,
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('confirmTwoFactor')
        ->assertSee('Enter your current password and the six-digit authenticator code.');

    expect($test->get('twoFactorPending'))->toBeTrue();

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/account/two-factor/confirm'));
});

it('enables two-factor and displays only valid recovery codes from the response', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response([
            'enabled' => false,
            'pending_confirmation' => true,
        ]),
        accountSecurityEndpoint('two-factor/confirm') => Http::response([
            'enabled' => true,
            'recovery_codes' => ['first-code', 123, 'second-code'],
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('twoFactorPassword', 'current-password')
        ->set('twoFactorCode', ' 123456 ')
        ->set('twoFactorSecret', 'temporary-secret')
        ->set('twoFactorQrCode', 'data:image/svg+xml;base64,temporary')
        ->call('confirmTwoFactor')
        ->assertSee('Two-factor authentication is enabled.')
        ->assertSee('first-code')
        ->assertSee('second-code');

    expect($test->get('twoFactorEnabled'))->toBeTrue()
        ->and($test->get('twoFactorPending'))->toBeFalse()
        ->and($test->get('twoFactorSecret'))->toBe('')
        ->and($test->get('twoFactorQrCode'))->toBe('')
        ->and($test->get('twoFactorPassword'))->toBe('')
        ->and($test->get('twoFactorCode'))->toBe('')
        ->and($test->get('recoveryCodes'))->toBe(['first-code', 'second-code']);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/two-factor/confirm')
        && $request['current_password'] === 'current-password'
        && $request['code'] === '123456');
});

it('shows and hides recovery codes after confirming the current password', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response(['enabled' => true]),
        accountSecurityEndpoint('two-factor/recovery-codes') => Http::response([
            'recovery_codes' => ['recover-one', 'recover-two'],
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('recoveryCodesPassword', 'current-password')
        ->call('showRecoveryCodes')
        ->assertSee('recover-one')
        ->assertSee('recover-two')
        ->call('hideRecoveryCodes')
        ->assertDontSee('recover-one');

    expect($test->get('recoveryCodes'))->toBe([])
        ->and($test->get('recoveryCodesPassword'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/two-factor/recovery-codes')
        && $request['current_password'] === 'current-password');
});

it('requires the current password before showing recovery codes', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response(['enabled' => true]),
    ]));

    Native::test(AccountSecurity::class)
        ->call('showRecoveryCodes')
        ->assertSee('Enter your current password to access recovery codes.');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/two-factor/recovery-codes'));
});

it('replaces recovery codes and confirms the old codes are no longer shown', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::response(['enabled' => true]),
        accountSecurityEndpoint('two-factor/recovery-codes') => Http::response([
            'recovery_codes' => ['new-recovery-code'],
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('recoveryCodes', ['old-recovery-code'])
        ->set('recoveryCodesPassword', 'current-password')
        ->call('regenerateRecoveryCodes')
        ->assertSee('Your recovery codes were replaced.')
        ->assertSee('new-recovery-code')
        ->assertDontSee('old-recovery-code');

    expect($test->get('recoveryCodes'))->toBe(['new-recovery-code'])
        ->and($test->get('recoveryCodesPassword'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/v1/account/two-factor/recovery-codes')
        && $request['current_password'] === 'current-password');
});

it('requires the current password and clears recovery codes when two-factor is disabled', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('two-factor') => Http::sequence()
            ->push(['enabled' => true])
            ->push(null, 204),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('recoveryCodes', ['saved-code'])
        ->call('disableTwoFactor')
        ->assertSee('Enter your current password to turn off two-factor authentication.')
        ->set('disableTwoFactorPassword', 'current-password')
        ->call('disableTwoFactor')
        ->assertSee('Two-factor authentication has been turned off.');

    expect($test->get('twoFactorEnabled'))->toBeFalse()
        ->and($test->get('twoFactorPending'))->toBeFalse()
        ->and($test->get('recoveryCodes'))->toBe([])
        ->and($test->get('disableTwoFactorPassword'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/account/two-factor')
        && $request['current_password'] === 'current-password');
});

it('opens a trusted Pinkary GitHub authorization link and retains the handoff id', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('github') => Http::response(['connected' => false, 'eligible' => true]),
        accountSecurityEndpoint('github/handoff') => Http::response([
            'id' => 'handoff-123',
            'browser_url' => mb_rtrim((string) config('services.pinkary.url'), '/').'/github/authorize?handoff=123',
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('connectGithub')
        ->assertSee('Finish the GitHub authorization in the browser');

    expect($test->get('githubHandoffId'))->toBe('handoff-123')
        ->and($test->get('githubBusy'))->toBeFalse();

    $test->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? null)
        === mb_rtrim((string) config('services.pinkary.url'), '/').'/github/authorize?handoff=123');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/account/github/handoff')
        && $request->header('Authorization') === ['Bearer security-token']);
});

it('rejects a GitHub authorization link hosted outside Pinkary', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('github') => Http::response(['connected' => false, 'eligible' => true]),
        accountSecurityEndpoint('github/handoff') => Http::response([
            'id' => 'handoff-123',
            'browser_url' => 'https://attacker.example/authorize',
        ]),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('connectGithub')
        ->assertSee('Pinkary returned an invalid GitHub authorization link.');

    expect($test->get('githubHandoffId'))->toBe('');

    $test->assertNativeNotCalled('Browser.OpenInApp');
});

it('waits for and then displays a completed GitHub handoff', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('github') => Http::sequence()
            ->push(['connected' => false, 'eligible' => true])
            ->push(['connected' => true, 'eligible' => false, 'github_username' => 'ada-lovelace']),
        accountSecurityEndpoint('github/handoff') => Http::response([
            'id' => 'handoff-123',
            'browser_url' => mb_rtrim((string) config('services.pinkary.url'), '/').'/github/authorize?handoff=123',
        ]),
        accountSecurityEndpoint('github/handoff/handoff-123') => Http::sequence()
            ->push(['status' => 'pending'])
            ->push(['status' => 'completed', 'github_username' => 'ada-lovelace']),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('connectGithub')
        ->call('pollGithubHandoff')
        ->assertSee('Waiting for GitHub authorization to finish.')
        ->call('pollGithubHandoff')
        ->assertSee('Connected as @ada-lovelace')
        ->assertSee('GitHub is connected.');

    expect($test->get('githubConnected'))->toBeTrue()
        ->and($test->get('githubEligible'))->toBeFalse()
        ->and($test->get('githubHandoffId'))->toBe('')
        ->and($test->get('githubUsername'))->toBe('ada-lovelace');
});

it('clears an expired GitHub handoff so the user can start again', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('github') => Http::response(['connected' => false, 'eligible' => true]),
        accountSecurityEndpoint('github/handoff') => Http::response([
            'id' => 'handoff-123',
            'browser_url' => mb_rtrim((string) config('services.pinkary.url'), '/').'/github/authorize?handoff=123',
        ]),
        accountSecurityEndpoint('github/handoff/handoff-123') => Http::response(['status' => 'expired']),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('connectGithub')
        ->call('pollGithubHandoff')
        ->assertSee('This GitHub authorization link expired. Start again to get a new one.');

    expect($test->get('githubHandoffId'))->toBe('')
        ->and($test->get('githubEligible'))->toBeTrue();
});

it('requires confirmation before disconnecting GitHub and refreshes its status afterward', function (): void {
    signInAccountSecurityUser();
    $githubIsConnected = true;
    Http::fake(accountSecurityResponses([
        accountSecurityEndpoint('github') => function (Request $request) use (&$githubIsConnected): PromiseInterface {
            if ($request->method() === 'DELETE') {
                $githubIsConnected = false;

                return Http::response(null, 204);
            }

            return Http::response([
                'connected' => $githubIsConnected,
                'eligible' => ! $githubIsConnected,
                'github_username' => $githubIsConnected ? 'ada-lovelace' : '',
            ]);
        },
    ]));

    $test = Native::test(AccountSecurity::class)
        ->call('askDisconnectGithub')
        ->assertSee('Disconnect @ada-lovelace from Pinkary?')
        ->call('cancelDisconnectGithub')
        ->assertSee('Connected as @ada-lovelace')
        ->call('disconnectGithub')
        ->call('askDisconnectGithub')
        ->call('disconnectGithub')
        ->assertSee('GitHub has been disconnected.');

    expect($test->get('confirmGithubDisconnect'))->toBeFalse()
        ->and($test->get('githubConnected'))->toBeFalse()
        ->and($test->get('githubEligible'))->toBeTrue()
        ->and($test->get('githubUsername'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/account/github'));
});

it('deletes the account only after a password and DELETE confirmation and switches accounts', function (): void {
    AccountStore::add('grace', 'grace-token', ['name' => 'Grace Hopper', 'username' => 'grace']);
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses([
        mb_rtrim((string) config('services.pinkary.url'), '/').'/api/v1/account' => Http::response(null, 204),
    ]));

    $test = Native::test(AccountSecurity::class)
        ->set('deletePassword', 'current-password')
        ->set('deleteConfirmation', 'REMOVE')
        ->call('deleteAccount')
        ->assertSee('Enter your password and type DELETE to confirm account deletion.')
        ->set('deleteConfirmation', ' delete ')
        ->call('deleteAccount')
        ->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('grace-token')
        ->and($test->get('deletePassword'))->toBe('')
        ->and($test->get('deleteConfirmation'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/account')
        && $request['password'] === 'current-password'
        && $request->header('Authorization') === ['Bearer security-token']);
});

it('replaces account security with login when mounted without an account', function (): void {
    Native::test(AccountSecurity::class)->assertReplacedWith('/login');

    Http::assertSentCount(0);
});

it('clears sensitive fields when leaving account security', function (): void {
    signInAccountSecurityUser();
    Http::fake(accountSecurityResponses());

    $test = Native::test(AccountSecurity::class)
        ->set('currentPassword', 'current')
        ->set('newPassword', 'new')
        ->set('passwordConfirmation', 'new')
        ->set('twoFactorPassword', 'two-factor')
        ->set('twoFactorCode', '123456')
        ->set('twoFactorSecret', 'secret')
        ->set('twoFactorQrCode', 'qr-code')
        ->set('recoveryCodes', ['recovery-code'])
        ->set('recoveryCodesPassword', 'recovery-password')
        ->set('disableTwoFactorPassword', 'disable-password')
        ->set('deletePassword', 'delete-password')
        ->set('deleteConfirmation', 'DELETE')
        ->call('onBackPressed');

    expect($test->get('currentPassword'))->toBe('')
        ->and($test->get('newPassword'))->toBe('')
        ->and($test->get('passwordConfirmation'))->toBe('')
        ->and($test->get('twoFactorPassword'))->toBe('')
        ->and($test->get('twoFactorCode'))->toBe('')
        ->and($test->get('twoFactorSecret'))->toBe('')
        ->and($test->get('twoFactorQrCode'))->toBe('')
        ->and($test->get('recoveryCodes'))->toBe([])
        ->and($test->get('recoveryCodesPassword'))->toBe('')
        ->and($test->get('disableTwoFactorPassword'))->toBe('')
        ->and($test->get('deletePassword'))->toBe('')
        ->and($test->get('deleteConfirmation'))->toBe('');
});
