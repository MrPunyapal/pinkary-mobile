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
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->assertMissingElement('top_bar')
        ->assertSee('Pinkary User')
        ->assertSee('@pinkaryuser')
        ->assertSee('Building in public.')
        ->assertSee('1.5K Followers')
        ->assertSee('34 Posts')
        ->assertSee('Website')
        ->assertDontSee('pinkary@example.com')
        ->assertSee('Edit profile')
        ->assertSee('Edit links');
});

it('shows the empty-links hint on your own card only', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => array_merge(ownProfile(), ['links' => []])]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->assertSee('No links yet.');
});

it('renders another user with a follow button and no email', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
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
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
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
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
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
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->press("openProfile('ada')")
        ->assertNavigatedTo('/user/ada');

    Native::visit('/user/ada')->assertSee('Ada Lovelace');
});

it('toggles the profile QR code section', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->call('toggleQr')
        ->assertSee('https://pinkary.test/@pinkaryuser');
});

it('renders other users with a single in-content top bar title', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
    ]);

    // Bookmarks pattern: centered in-content page heading (vertically
    // centered, no chrome top_bar hoist).
    Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('@ada')
        ->assertMissingElement('top_bar');
});

function profileQuestionRow(string $id, string $answer, bool $pinned = false): array
{
    return [
        'id' => $id,
        'pinned' => $pinned,
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Pinkary User', 'username' => 'pinkaryuser'],
        'content' => 'What are you building?',
        'answer' => $answer,
        'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
        'views' => 0,
    ];
}

it('lists the profile posts with pagination', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::sequence()
            ->push([
                'data' => [profileQuestionRow('q1', 'Building in public.', true)],
                'links' => ['next' => 'https://pinkary.test/api/v1/users/pinkaryuser/questions?page=2'],
            ])
            ->push([
                'data' => [profileQuestionRow('q2', 'An older answer.')],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Building in public.')
        ->assertSee('Pinned')
        ->call('loadMoreQuestions')
        ->assertSee('An older answer.');
});

it('surfaces post list failures with a retry', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::sequence()
            ->push(['message' => 'Server error'], 500)
            ->push(['data' => [profileQuestionRow('q1', 'Building in public.')], 'links' => ['next' => null]]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Posts could not be loaded. Please try again.')
        ->call('retryQuestions')
        ->assertSee('Building in public.');
});

it('opens the followers and following lists from the pills', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->call('openFollowers')
        ->assertNavigatedTo('/user/pinkaryuser/followers');

    Native::test(Profile::class)
        ->call('openFollowing')
        ->assertNavigatedTo('/user/pinkaryuser/following');
});

it('asks other users from their profile', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherProfile()]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('Ask @ada')
        ->call('askUser')
        ->assertNavigatedTo('/ask/ada');
});

it('edits your own profile from the profile screen', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Edit profile')
        ->assertSee('Edit links')
        ->call('editProfile')
        ->assertNavigatedTo('/profile/edit');
});

it('shows link click counts on the card', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => array_merge(ownProfile(), [
            'links' => [
                ['id' => 7, 'description' => 'Website', 'url' => 'https://example.com?ref=pinkary', 'click_count' => 12],
            ],
        ])]),
        'https://pinkary.test/api/v1/users/pinkaryuser/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class)
        ->assertSee('12 clicks');
});
