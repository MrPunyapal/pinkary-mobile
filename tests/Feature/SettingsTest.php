<?php

declare(strict_types=1);

use App\NativeComponents\Settings;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
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
