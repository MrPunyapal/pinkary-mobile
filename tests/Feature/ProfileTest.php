<?php

use App\NativeComponents\Home;
use App\NativeComponents\Profile;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function ownProfile(): array
{
    return [
        'id' => 1,
        'name' => 'Pinkary User',
        'username' => 'pinkaryuser',
        'email' => 'pinkary@example.com',
        'bio' => 'Building in public.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 1500, 'following' => 12, 'posts' => 34, 'views' => 999],
        'followed_by_me' => false,
        'is_me' => true,
        'links' => [
            ['id' => 7, 'description' => 'Website', 'url' => 'https://example.com?ref=pinkary'],
        ],
        'member_since' => ['human' => 'today'],
    ];
}

function otherProfile(): array
{
    return [
        'id' => 2,
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => null,
        'bio' => 'First programmer.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 0, 'following' => 0, 'posts' => 3, 'views' => 10],
        'followed_by_me' => false,
        'is_me' => false,
        'links' => [],
        'member_since' => ['human' => '2 years ago'],
    ];
}

it('renders the authenticated profile card like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Pinkary User')
        ->assertSee('@pinkaryuser')
        ->assertSee('Building in public.')
        ->assertSee('1.5K Followers')
        ->assertSee('34 Posts')
        ->assertSee('Website')
        ->assertSee('pinkary@example.com');
});

it('shows the empty-links hint on your own card only', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => array_merge(ownProfile(), ['links' => []])]),
    ]);

    Native::test(Profile::class)
        ->assertSee('No links yet.');
});

it('renders another user with a follow button and no email', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
    ]);

    $test = Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('Ada Lovelace')
        ->assertSee('Follow')
        ->assertSee('3 Posts');

    expect($test->get('profile')['email'])->toBeNull();
});

it('follows and unfollows from the card', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
        'https://pinkary.test/api/v1/users/ada/follow' => Http::sequence()
            ->push(['data' => ['followed' => true, 'followers' => 1]])
            ->push(['data' => ['followed' => false, 'followers' => 0]]),
    ]);

    $test = Native::test(Profile::class, ['username' => 'ada'])
        ->press('toggleFollow')
        ->assertSee('Following');

    expect($test->get('profile'))->toMatchArray([
        'followed_by_me' => true,
        'stats' => ['followers' => 1, 'following' => 0, 'posts' => 3, 'views' => 10],
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/users/ada/follow'));
});

it('records link visits when opening them', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/links/7/click' => Http::response(['data' => ['clicked' => true]]),
    ]);

    Native::test(Profile::class)
        ->call('openUserLink', 7)
        ->assertSee('Website');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/links/7/click'));
});

it('opens author profiles from the feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Who are you?',
            'answer' => 'The company account.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
    ]);

    Native::test(Home::class)
        ->press("openProfile('ada')")
        ->assertNavigatedTo('/user/ada');

    Native::visit('/user/ada')->assertSee('Ada Lovelace');
});
