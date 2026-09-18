<?php

use App\NativeComponents\Home;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('shows the recent feed to guests without redirecting to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session()->forget('pinkary_api_token');
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'guest-q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Public recent post.',
            'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
        ]]]),
    ]);

    Native::test(Home::class)
        ->call('retry')
        ->assertSee('Public recent post.')
        ->assertDontSee('Log in');
});

it('renders the Pinkary feed from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response([
            'data' => [[
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'What are you building?',
                'answer' => 'A thoughtful mobile experience.',
                'metrics' => ['likes' => 4, 'comments' => 2],
            ]],
        ]),
    ]);

    Native::test(Home::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('What are you building?')
        ->assertSee('A thoughtful mobile experience.');
});

it('renders channel, edited time, preview, images, and polls', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A thoughtful mobile experience.',
            'edited' => true,
            'updated_at' => now()->subHours(13)->toIso8601String(),
            'channel' => ['id' => 1, 'name' => 'Laravel', 'slug' => 'laravel'],
            'preview' => [
                'url' => 'https://mrpunyapal.dev?v=tldr',
                'host' => 'mrpunyapal.dev',
                'title' => 'Punyapal Shah | Laravel Engineer',
                'image' => 'https://mrpunyapal.dev/og.png',
            ],
            'images' => ['https://pinkary.test/storage/a.png'],
            'poll' => [
                'expires_at' => now()->addDay()->toIso8601String(),
                'expired' => false,
                'total_votes' => 3,
                'user_vote_option_id' => null,
                'options' => [
                    ['id' => 1, 'text' => 'Laravel', 'votes' => 2],
                    ['id' => 2, 'text' => 'Rails', 'votes' => 1],
                ],
            ],
            'metrics' => ['likes' => 4, 'comments' => 0],
            'views' => 0,
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Laravel')
        ->assertSee('Edited:')
        ->assertSee('ago')
        ->assertSee('Punyapal Shah | Laravel Engineer')
        ->assertSee('mrpunyapal.dev')
        ->assertSee('Rails')
        ->assertSee('3 votes');
});

it('hides zero counts like the web feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Quiet post?',
            'answer' => 'No engagement yet.',
            'metrics' => ['likes' => 0, 'comments' => 0],
            'views' => 0,
        ]]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('No engagement yet.');

    expect($test->get('posts')[0]['metrics'])->toMatchArray(['likes' => 0, 'comments' => 0]);
});

it('votes in a poll and reconciles with the server', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Pick one.',
            'answer' => 'Pick one.',
            'poll' => [
                'expires_at' => now()->addDay()->toIso8601String(),
                'expired' => false,
                'total_votes' => 0,
                'user_vote_option_id' => null,
                'options' => [
                    ['id' => 1, 'text' => 'Laravel', 'votes' => 0],
                    ['id' => 2, 'text' => 'Rails', 'votes' => 0],
                ],
            ],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/poll/vote' => Http::response(['data' => [
            'expires_at' => now()->addDay()->toIso8601String(),
            'expired' => false,
            'total_votes' => 1,
            'user_vote_option_id' => 1,
            'options' => [
                ['id' => 1, 'text' => 'Laravel', 'votes' => 1],
                ['id' => 2, 'text' => 'Rails', 'votes' => 0],
            ],
        ]]),
    ]);

    $test = Native::test(Home::class)
        ->press("votePoll('q1', 1)");

    expect($test->get('posts')[0]['poll'])->toMatchArray(['total_votes' => 1, 'user_vote_option_id' => 1]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/poll/vote'));
});

it('ignores non-http links', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Link post.',
            'answer' => 'Link post.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->call('openLink', 'javascript:alert(1)')
        ->assertSee('Link post.');
});

it('displays plain text when the API returns HTML', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => '<p>What are you <strong>building</strong>?</p>',
            'answer' => '<p>A <a href="https://pinkary.test">thoughtful</a> mobile experience.</p>',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('What are you building?')
        ->assertSee('A thoughtful mobile experience.');
});

it('abbreviates counts exactly like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Popular post.',
            'answer' => 'Popular post.',
            'metrics' => ['likes' => 1500, 'comments' => 0, 'bookmarks' => 2500000],
            'views' => 1000,
        ]]]),
    ]);

    // Web keeps the ".0" (toFixed(1)): 1.5K, 1.0K, 2.5M.
    Native::test(Home::class)
        ->assertSee('1.5K')
        ->assertSee('1.0K')
        ->assertSee('2.5M');
});

it('translates a post without leaving the app', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Bonjour.',
            'answer' => 'Bonjour le monde.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->call('translatePost', 'q1', 'answer')
        ->assertSee('Bonjour le monde.');
});

it('shows the following feed when switching tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => []])
            ->push(['data' => [[
                'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'What did you ship today?',
                'answer' => 'A compiler for the mobile age.',
                'metrics' => ['likes' => 7, 'comments' => 3],
            ]]]),
    ]);

    Native::test(Home::class)
        ->changeTab('tabChanged', 1)
        ->assertSet('tab', 'following')
        ->assertSee('A compiler for the mobile age.');
});

it('shows the trending feed when switching tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => []])
            ->push(['data' => [[
                'from' => ['name' => 'Alan Turing', 'username' => 'alan'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'Can machines think on mobile?',
                'answer' => 'The whole feed is talking about it.',
                'metrics' => ['likes' => 42, 'comments' => 9],
            ]]]),
    ]);

    Native::test(Home::class)
        ->changeTab('tabChanged', 2)
        ->assertSet('tab', 'trending')
        ->assertSee('The whole feed is talking about it.');
});

it('retries loading the feed after a failure', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(null, 500)
            ->push(['data' => [[
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'Are we back?',
                'answer' => 'Back online.',
                'metrics' => ['likes' => 1, 'comments' => 0],
            ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('could not be loaded')
        ->call('retry')
        ->assertSee('Back online.');
});

it('shows a tailored empty state for the following tab', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->changeTab('tabChanged', 1)
        ->assertSee('Your following feed is empty.')
        ->assertSee('Follow more people');
});

it('shows the web empty copy on the recent and trending tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->assertSee('There are no questions to show.')
        ->changeTab('tabChanged', 2)
        ->assertSee('There are no trending questions right now.');
});

it('renders thread context above feed replies like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q3',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Third post.',
            'thread' => [
                'parent_id' => 'q2',
                'root_id' => 'q1',
                'posts' => [
                    [
                        'id' => 'q1',
                        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'content' => '__UPDATE__',
                        'answer' => 'First post.',
                        'thread' => ['parent_id' => null, 'root_id' => null, 'posts' => [], 'more' => false, 'more_id' => null],
                        'metrics' => ['likes' => 0, 'comments' => 0],
                    ],
                    [
                        'id' => 'q2',
                        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'content' => '__UPDATE__',
                        'answer' => 'Second post.',
                        'thread' => ['parent_id' => 'q1', 'root_id' => 'q1', 'posts' => [], 'more' => false, 'more_id' => null],
                        'metrics' => ['likes' => 0, 'comments' => 0],
                    ],
                ],
                'more' => false,
                'more_id' => null,
            ],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('First post.')
        ->assertSee('Second post.')
        ->assertSee('Third post.');
});

it('shows the end-of-feed message when nothing is left', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response([
            'data' => [[
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
                'content' => 'Last post?',
                'answer' => 'Last answer.',
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]],
            'links' => ['next' => null],
        ]),
    ]);

    Native::test(Home::class)
        ->assertSee('There are no more questions to load');
});

it('marks company-verified authors', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response([
            'data' => [[
                'from' => ['name' => 'Pinkary Inc', 'username' => 'pinkary', 'company_verified' => true],
                'to' => ['name' => 'Pinkary'],
                'content' => 'Who are you?',
                'answer' => 'The company account.',
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]],
        ]),
    ]);

    Native::test(Home::class)
        ->assertSee('Pinkary Inc')
        ->assertSee('@pinkary');
});

it('abbreviates large metric counts', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response([
            'data' => [[
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'How popular is this?',
                'answer' => 'Very popular.',
                'metrics' => ['likes' => 1500, 'comments' => 2300000],
                'views' => 999,
            ]],
        ]),
    ]);

    Native::test(Home::class)
        ->assertSee('2.3M')
        ->assertSee('1.5K')
        ->assertSee('999');
});

it('likes a post through the action row', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Like this?',
            'answer' => 'Likable answer.',
            'metrics' => ['likes' => 5, 'comments' => 2],
            'views' => 10,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 6]]),
    ]);

    $test = Native::test(Home::class)
        ->tap('5');

    expect($test->get('posts')[0]['metrics'])->toMatchArray(['liked' => true, 'likes' => 6]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/like'));
});

it('opens the detail screen from the comments row', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Open me?',
            'answer' => 'Detailed answer.',
            'metrics' => ['likes' => 0, 'comments' => 4],
            'views' => 0,
        ]]]),
    ]);

    Native::test(Home::class)
        ->press("openDetail('q1')")
        ->assertNavigatedTo('/question/q1');
});

it('unlikes an already liked post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Unlike this?',
            'answer' => 'Unlikeable answer.',
            'metrics' => ['likes' => 6, 'comments' => 0, 'liked' => true],
            'views' => 0,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => false, 'likes' => 5]]),
    ]);

    $test = Native::test(Home::class)
        ->tap('6');

    expect($test->get('posts')[0]['metrics'])->toMatchArray(['liked' => false, 'likes' => 5]);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/questions/q1/like'));
});

it('reverts the like when the server rejects it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Fail this?',
            'answer' => 'Reverted answer.',
            'metrics' => ['likes' => 5, 'comments' => 0],
            'views' => 0,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(null, 500),
    ]);

    $test = Native::test(Home::class)
        ->tap('5');

    expect($test->get('posts')[0]['metrics'])->toMatchArray(['liked' => false, 'likes' => 5]);
});

it('returns to login when liking with a rejected token', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Auth this?',
            'answer' => 'Expired answer.',
            'metrics' => ['likes' => 5, 'comments' => 0],
            'views' => 0,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(null, 401),
    ]);

    Native::test(Home::class)
        ->tap('5')
        ->assertReplacedWith('/login');

    expect(session('pinkary_api_token'))->toBeNull();
});

it('toggles bookmarks through the action row', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Save this?',
            'answer' => 'Bookmarkable answer.',
            'metrics' => ['likes' => 0, 'comments' => 0],
            'views' => 0,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/bookmark' => Http::sequence()
            ->push(['data' => ['bookmarked' => true, 'bookmarks' => 1]])
            ->push(['data' => ['bookmarked' => false, 'bookmarks' => 0]]),
    ]);

    $test = Native::test(Home::class)
        ->press("toggleBookmark('q1')");

    expect($test->get('posts')[0]['metrics']['bookmarked'])->toBeTrue();

    $test->press("toggleBookmark('q1')");

    expect($test->get('posts')[0]['metrics']['bookmarked'])->toBeFalse();

    Http::assertSentCount(3);
});

it('appends the next page when loading more', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push([
                'data' => [[
                    'id' => 'q1',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Pinkary'],
                    'content' => 'First page?',
                    'answer' => 'First page answer.',
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'links' => ['next' => 'https://pinkary.test/api/v1/feed?page=2'],
            ])
            ->push([
                'data' => [[
                    'id' => 'q2',
                    'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                    'to' => ['name' => 'Pinkary'],
                    'content' => 'Second page?',
                    'answer' => 'Second page answer.',
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(Home::class)
        ->assertSee('First page answer.')
        ->assertSee('Load more')
        ->call('loadMore')
        ->assertSee('Second page answer.')
        ->assertDontSee('Load more');
});

it('keeps the feed when loading more fails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push([
                'data' => [[
                    'id' => 'q1',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Pinkary'],
                    'content' => 'Still here?',
                    'answer' => 'Persisted answer.',
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'links' => ['next' => 'https://pinkary.test/api/v1/feed?page=2'],
            ])
            ->push(null, 500),
    ]);

    Native::test(Home::class)
        ->call('loadMore')
        ->assertSee('Persisted answer.')
        ->assertSee('Could not load more posts.')
        ->assertSee('Load more');
});

it('does not redirect guests from the home feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session()->forget('pinkary_api_token');
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->assertSee('There are no questions to show.');
});

it('returns to login when the feed rejects the token', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(null, 401),
    ]);

    Native::test(Home::class)
        ->assertReplacedWith('/login');

    expect(session('pinkary_api_token'))->toBeNull();
});

it('reverts the bookmark when the server rejects it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'Save this?',
            'answer' => 'Unsaveable answer.',
            'metrics' => ['likes' => 0, 'comments' => 0],
            'views' => 0,
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/bookmark' => Http::response(null, 500),
    ]);

    $test = Native::test(Home::class)
        ->press("toggleBookmark('q1')");

    expect($test->get('posts')[0]['metrics']['bookmarked'])->toBeFalse();
});

it('clamps an unknown tab index to recent', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->changeTab('tabChanged', 9)
        ->assertSet('tab', 'recent')
        ->assertSet('selectedTab', 0);
});
