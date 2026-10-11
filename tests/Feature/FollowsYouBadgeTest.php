<?php

declare(strict_types=1);

use App\NativeComponents\FollowList;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeFollowList(string $mode, array $user, string $profile = 'alice'): void
{
    Http::fake([
        "https://pinkary.test/api/v1/users/{$profile}/{$mode}*" => Http::response([
            'data' => [$user],
            'meta' => ['current_page' => 1, 'last_page' => 1],
        ]),
    ]);
}

function followerPayload(array $extra = []): array
{
    return array_merge([
        'id' => 7,
        'name' => 'Grace Hopper',
        'username' => 'grace',
        'avatar' => null,
        'verification' => ['profile' => false, 'company' => false],
        'followed_by_me' => true,
        'follows_me' => true,
        'is_me' => false,
    ], $extra);
}

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('me', 'test-token', ['username' => 'me']);
    CurrentUser::clear();
});

it('badges a mutual follow on someone elses follower list', function (): void {
    fakeFollowList('followers', followerPayload());

    Native::test(FollowList::class, ['username' => 'alice'])
        ->assertSee('Grace Hopper')
        ->assertSee('Follows you');
});

it('omits the badge when the person does not follow back', function (): void {
    fakeFollowList('followers', followerPayload(['follows_me' => false]));

    Native::test(FollowList::class, ['username' => 'alice'])
        ->assertSee('Grace Hopper')
        ->assertDontSee('Follows you');
});

it('omits the badge on your own follower list', function (): void {
    fakeFollowList('followers', followerPayload(['username' => 'me', 'is_me' => true]), 'me');

    Native::test(FollowList::class, ['username' => 'me'])
        ->assertSee('Grace Hopper')
        ->assertDontSee('Follows you');
});

it('badges the following list too', function (): void {
    fakeFollowList('following', followerPayload());

    Native::test(FollowList::class, ['username' => 'alice', 'mode' => 'following'])
        ->assertSee('Follows you');
});

it('keeps the follow button beside the badge', function (): void {
    fakeFollowList('followers', followerPayload(['followed_by_me' => false]));

    Native::test(FollowList::class, ['username' => 'alice'])
        ->assertSee('Follows you')
        ->assertSee('Follow Back');
});

it('survives a payload with no follows_me key at all', function (): void {
    $user = followerPayload();
    unset($user['follows_me']);

    fakeFollowList('followers', $user);

    Native::test(FollowList::class, ['username' => 'alice'])
        ->assertSee('Grace Hopper')
        ->assertDontSee('Follows you');
});
