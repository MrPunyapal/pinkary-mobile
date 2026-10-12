<?php

declare(strict_types=1);

use App\NativeComponents\Settings;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    CurrentUser::clear();
});

function settingsProfilePayload(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'Pinkary User',
        'username' => 'pinkaryuser',
        'email' => 'pinkary@example.com',
        'bio' => '',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 1, 'following' => 1, 'posts' => 1, 'views' => 1],
        'followed_by_me' => false,
        'is_me' => true,
        'link_shape' => 'rounded-lg',
        'links' => [],
        'member_since' => ['human' => 'today'],
    ], $overrides);
}

it('leads settings with the profile editor like the web', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => settingsProfilePayload()]),
    ]);

    Native::test(Settings::class)
        ->assertSee('Edit profile')
        ->assertSee('View profile')
        ->tap('Edit profile')
        ->assertNavigatedTo('/profile/edit');
});

it('names the signed-in account on the identity line', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => settingsProfilePayload()]),
    ]);

    Native::test(Settings::class)
        ->assertSee('Signed in as pinkary@example.com')
        ->assertDontSee('Member since')
        ->assertDontSee('Source code');
});

it('says Log Out on its own and names the account when others are stored', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => settingsProfilePayload()]),
    ]);

    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Native::test(Settings::class)
        ->assertSee('Log Out')
        ->assertDontSee('Log out of @pinkaryuser');

    AccountStore::add('grace', 'token-2', ['username' => 'grace']);
    Native::test(Settings::class)
        ->assertSee('Log out of @pinkaryuser');

    AccountStore::clear();
});

it('replaces an unauthenticated visit with the login screen', function (): void {
    Native::test(Settings::class)->assertReplacedWith('/login');
});

it('offers a retry that recovers once the profile request succeeds', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $attempt = 0;

    Http::fake([
        'https://pinkary.test/api/v1/profile' => function () use (&$attempt) {
            $attempt++;

            return $attempt === 1
                ? Http::failedConnection()
                : Http::response(['data' => settingsProfilePayload()]);
        },
    ]);

    Native::test(Settings::class)
        ->assertSet('loading', false)
        ->assertSet('errorMessage', 'Settings could not be loaded. Please try again.')
        ->assertSee('Try again')
        ->tap('Try again')
        ->assertSet('errorMessage', '')
        ->assertSee('Edit profile');

    expect($attempt)->toBe(2);
});

it('sends a retry without a token to login instead of loading', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => settingsProfilePayload()]),
    ]);

    $screen = Native::test(Settings::class)->assertSee('Edit profile');

    AccountStore::clear();

    $screen->call('retry')->assertNavigatedTo('/login');
});

it('drops the dead token and returns to login on unauthorized', function (): void {
    AccountStore::add('ada', 'dead-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(null, 401),
    ]);

    Native::test(Settings::class)->assertReplacedWith('/login');

    expect(AccountStore::activeToken())->toBeNull()
        ->and(CurrentUser::get())->toBeNull();
});

it('switches to another saved account when settings rejects the active token', function (): void {
    AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    AccountStore::add('grace', 'dead-token', ['username' => 'grace']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(null, 401),
    ]);

    Native::test(Settings::class)->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-a');
});

it('shows the load error on an unsuccessful profile response', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['message' => 'Server error.'], 500),
    ]);

    Native::test(Settings::class)
        ->assertSet('loading', false)
        ->assertSee('Settings could not be loaded. Please try again.')
        ->assertDontSee('Edit profile');
});
