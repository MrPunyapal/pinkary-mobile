<?php

use App\NativeComponents\Home;
use App\NativeLayout\MobileLayout;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\FeedCache;
use App\Support\UnreadCount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;

it('shows the error state when the api is unreachable', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(function (): void {
        throw new ConnectionException('timeout');
    });

    Native::test(Home::class)
        ->assertSee('The Pinkary feed could not be loaded. Please try again.');
});

it('reverts optimistic likes when the api is unreachable', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Optimistic post.',
            'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => function (): void {
            throw new ConnectionException('timeout');
        },
    ]);

    $test = Native::test(Home::class)->call('toggleLike', 'q1');

    expect(data_get($test->get('posts'), '0.metrics.liked'))->toBeFalse()
        ->and(data_get($test->get('posts'), '0.metrics.likes'))->toBe(0);
});

it('renders expired polls static with no vote handler', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Closed poll post.',
            'poll' => [
                'expired' => true,
                'total_votes' => 5,
                'user_vote_option_id' => 1,
                'options' => [
                    ['id' => 1, 'text' => 'Laravel', 'votes' => 4],
                    ['id' => 2, 'text' => 'Rails', 'votes' => 1],
                ],
            ],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Laravel')
        ->assertSee('Poll expired')
        ->assertSee('5 votes');
});

it('shows the recent feed to guests without redirecting to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->call('showFollowing')
        ->assertSet('tab', 'following')
        ->assertSee('A compiler for the mobile age.');
});

it('shows the trending feed when switching tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->call('showTrending')
        ->assertSet('tab', 'trending')
        ->assertSee('The whole feed is talking about it.');
});

it('retries loading the feed after a failure', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->call('showFollowing')
        ->assertSee('Your following feed is empty.')
        ->assertSee('Follow more people');
});

it('shows the web empty copy on the recent and trending tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->assertSee('There are no questions to show.')
        ->call('showTrending')
        ->assertSee('There are no trending questions right now.');
});

it('renders thread context above feed replies like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

    expect(AccountStore::activeToken())->toBeNull();
});

it('toggles bookmarks through the action row', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        // Feed entry also refreshes the tab badge (web nav counts per
        // render), so the suite accounts for that request here.
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => [], 'meta' => ['unread_count' => 0]]),
    ]);

    $test = Native::test(Home::class)
        ->press("toggleBookmark('q1')");

    expect($test->get('posts')[0]['metrics']['bookmarked'])->toBeTrue();

    $test->press("toggleBookmark('q1')");

    expect($test->get('posts')[0]['metrics']['bookmarked'])->toBeFalse();

    Http::assertSentCount(4);
});

it('appends the next page when loading more', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->assertDontSee('Load more')
        ->call('loadMore')
        ->assertSee('Second page answer.')
        ->assertSee('There are no more questions to load, or you have scrolled too far.')
        ->assertDontSee('Load more');
});

it('keeps the feed when loading more fails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->assertSee('Try again')
        ->assertDontSee('Load more');
});

it('does not redirect guests from the home feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->assertSee('There are no questions to show.');
});

it('returns to login when the feed rejects the token', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(null, 401),
    ]);

    Native::test(Home::class)
        ->assertReplacedWith('/login');

    expect(AccountStore::activeToken())->toBeNull();
});

it('reverts the bookmark when the server rejects it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

it('resets the list when switching tabs so the change is always visible', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->call('showTrending')
        ->assertSet('tab', 'trending')
        ->assertSet('selectedTab', 2)
        ->call('showRecent')
        ->assertSet('tab', 'recent')
        ->assertSet('selectedTab', 0);
});

it('reloads the feed on pull-to-refresh', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => [[
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
                'content' => 'First version.',
                'answer' => 'Stale post.',
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]]])
            ->push(['data' => [[
                'id' => 'q2',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
                'content' => 'Second version.',
                'answer' => 'Fresh post.',
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Stale post.')
        ->call('refreshFeed')
        ->assertSee('Fresh post.')
        ->assertDontSee('Stale post.');
});

it('toggles likes on nested thread ancestors like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q2',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Second post.',
            'thread' => [
                'parent_id' => 'q1',
                'root_id' => 'q1',
                'posts' => [[
                    'id' => 'q1',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'content' => '__UPDATE__',
                    'answer' => 'First post.',
                    'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
                ]],
                'more' => false,
                'more_id' => null,
            ],
            'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 1]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('First post.')
        ->press("toggleLike('q1')");

    expect($test->get('posts')[0]['thread']['posts'][0]['metrics'])->toMatchArray(['liked' => true, 'likes' => 1]);
});

it('clamps long answers with Show more and Show less like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    $long = rtrim(str_repeat('Long answer sentence. ', 40));
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q9',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Tell me everything.',
            'answer' => $long,
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Show more')
        ->assertDontSee($long)
        ->call('toggleShowMore', 'q9')
        ->assertSee('Show less')
        ->assertSee($long)
        ->call('toggleShowMore', 'q9')
        ->assertSee('Show more');
});

it('opens post images fullscreen through the in-app browser link handler', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Look at this.',
            'answer' => 'A post with an image.',
            'images' => ['https://pinkary.test/storage/a.png'],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    // press() fails when no element registered the expression, so this
    // proves the image is wrapped in a tap handler wired to openLink.
    Native::test(Home::class)
        ->press("openLink('https://pinkary.test/storage/a.png')")
        ->assertSee('A post with an image.');
});

it('leaves the wordmark left with the search action trailing', function (): void {
    // Web parity: ../pinkary/resources/views/layouts/app.blade.php:83-95 is
    // a left-identity / right-action bar, and the web wordmark ink is
    // #0F172A — not the brand pink. The old dead left slot is gone.
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)->press('openSearch')->assertNavigatedTo('/search');

    $source = file_get_contents(resource_path('views/native/home.blade.php'));

    expect($source)
        ->toContain('flex-1 text-xl font-bold text-theme-on-surface')
        ->not->toContain('text-theme-primary')
        ->not->toContain('justify-between')
        ->not->toContain('w-9 h-9 shrink-0" />');
});

it('pushes the share action trailing with a spacer instead of ml-auto', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'Spaced out?',
            'answer' => 'Share sits trailing.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $tree = Native::test(Home::class)
        ->assertSee('Share sits trailing.')
        ->tree();

    $spacers = 0;
    $walk = function (array $node) use (&$walk, &$spacers): void {
        if (($node['type'] ?? null) === 'spacer') {
            $spacers++;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };
    $walk($tree);

    // A plain post renders no poll-option spacer, so any spacer is the
    // action-row one pushing share trailing (the post-poll-option
    // flex-1 pattern, not ml-auto).
    expect($spacers)->toBeGreaterThanOrEqual(1);
});

it('chains thread posts with the web rail connector', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q2',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => 'https://example.com/a.png'],
            'content' => '__UPDATE__',
            'answer' => 'Second post.',
            'thread' => [
                'parent_id' => 'q1',
                'root_id' => 'q1',
                'posts' => [[
                    'id' => 'q1',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => 'https://example.com/a.png'],
                    'content' => '__UPDATE__',
                    'answer' => 'First post.',
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'more' => false,
                'more_id' => null,
            ],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $tree = Native::test(Home::class)
        ->assertSee('First post.')
        ->assertSee('Second post.')
        ->assertDontSee('Replying to')
        ->assertSee('Recent')
        ->tree();

    $laneRails = 0;
    $railHeights = [];
    $railMargins = [];
    $walk = function (array $node) use (&$walk, &$laneRails): void {
        $layout = $node['layout'] ?? [];

        // Web parity: thread continuity draws a 2px lane rail coming down
        // from the top avatar, and a 2px lane rail going up into the bottom
        // avatar, seamlessly connecting both avatars across the post boundary.
        if (($layout['width'] ?? null) == 2) {
            $laneRails++;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };
    $walk($tree);

    expect($laneRails)->toBe(2);
});

it('renders thread with more comments divider and continuous lane rails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q3',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => 'https://example.com/a.png'],
            'content' => '__UPDATE__',
            'answer' => 'Third post.',
            'thread' => [
                'parent_id' => 'q2',
                'root_id' => 'q1',
                'posts' => [
                    [
                        'id' => 'q1',
                        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => 'https://example.com/a.png'],
                        'content' => '__UPDATE__',
                        'answer' => 'Root post.',
                        'metrics' => ['likes' => 0, 'comments' => 0],
                    ],
                    [
                        'id' => 'q2',
                        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => 'https://example.com/a.png'],
                        'content' => '__UPDATE__',
                        'answer' => 'Middle post.',
                        'metrics' => ['likes' => 0, 'comments' => 0],
                    ],
                ],
                'more' => true,
                'more_id' => 'q1',
            ],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $tree = Native::test(Home::class)
        ->assertSee('Root post.')
        ->assertSee('View more comments')
        ->assertSee('Middle post.')
        ->assertSee('Third post.')
        ->tree();

    $laneRails = 0;
    $walk = function (array $node) use (&$walk, &$laneRails): void {
        $layout = $node['layout'] ?? [];

        if (($layout['width'] ?? null) == 2) {
            $laneRails++;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };
    $walk($tree);

    expect($laneRails)->toBeGreaterThanOrEqual(7);
});

function homeFeedPost(string $id, string $answer, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'content' => '__UPDATE__',
        'answer' => $answer,
        'pinned' => false,
        'metrics' => ['likes' => 2, 'comments' => 0],
        'views' => 10,
    ], $overrides);
}

it('pins a feed post and shows the pinned chip', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Pinnable answer.'),
        ]]),
        'https://pinkary.test/api/v1/questions/q1/pin' => Http::response(['data' => ['pinned' => true]]),
    ]);

    // press() fails when no element registered the expression, so this
    // proves the ••• row button opens the screen menu and the Pin
    // entry fires the pin call.
    $test = Native::test(Home::class)
        ->press("openMenu('q1')")
        ->press('togglePinMenu')
        ->assertSee('Pinned');

    expect($test->get('posts')[0]['pinned'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/pin'));
});

it('hides the pin entry after a pin 403', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Not mine to pin.', ['pinned' => true]),
        ]]),
        'https://pinkary.test/api/v1/questions/q1/pin' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]);

    $test = Native::test(Home::class)
        ->call('openMenu', 'q1')
        ->assertSee('Unpin')
        ->call('togglePinMenu');

    expect($test->get('menuDenied'))->toMatchArray(['q1:pin' => true]);

    $test->call('openMenu', 'q1')
        ->assertDontSee('Unpin');
});

it('removes a feed post after confirming deletion', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Going away.'),
            homeFeedPost('q2', 'Staying put.'),
        ]]),
        'https://pinkary.test/api/v1/questions/q1/ignore' => Http::response(['data' => ['ignored' => true]]),
    ]);

    $test = Native::test(Home::class)
        ->call('openMenu', 'q1')
        ->call('askDeleteMenu')
        ->assertSee('Delete this post?')
        ->call('ignoreMenuPost')
        ->assertDontSee('Going away.')
        ->assertSee('Staying put.');

    expect(array_column($test->get('posts'), 'id'))->toBe(['q2']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/ignore'));
});

it('lists likers from the feed menu with pagination', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Likable answer.'),
        ]]),
        'https://pinkary.test/api/v1/questions/q1/likes*' => Http::sequence()
            ->push([
                'data' => [['name' => 'Grace Hopper', 'username' => 'grace']],
                'links' => ['next' => 'https://pinkary.test/api/v1/questions/q1/likes?page=2'],
            ])
            ->push([
                'data' => [['name' => 'Alan Turing', 'username' => 'alan']],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(Home::class)
        ->call('openMenu', 'q1')
        ->assertSee('View likes')
        ->call('openLikersMenu')
        ->assertSee('Grace Hopper')
        ->assertSee('Load more')
        ->call('loadMoreLikers')
        ->assertSee('Alan Turing')
        ->assertDontSee('Load more');
});

it('hydrates the authenticated user on mount', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store([
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'avatar' => 'https://example.com/ada-avatar.png',
    ]);

    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('Pinkary');

    expect($test->get('currentUser.avatar'))->toBe('https://example.com/ada-avatar.png')
        ->and($test->get('currentUser.username'))->toBe('ada');
});

it('opens and closes the app menu bottom sheet like post 3 dots', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada', 'name' => 'Ada Lovelace']);
    CurrentUser::store([
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'avatar' => 'https://example.com/ada-avatar.png',
    ]);

    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->assertDontSee('Settings')
        ->call('openAppMenu')
        ->assertSet('appMenuOpen', true)
        ->assertSee('@ada')
        ->assertSee('About')
        ->assertSee('Settings')
        ->assertSee('Source code')
        ->assertSee('Add an existing account')
        ->assertSee('Log Out')
        ->call('closeAppMenu')
        ->assertSet('appMenuOpen', false);
});

it('writes the feed payload to the cache after a successful load', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Cached write.'),
        ], 'links' => ['next' => null]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('Cached write.');

    $payload = FeedCache::get(FeedCache::feedKey('ada', 'recent'));

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('refreshing'))->toBeFalse();
    expect($payload)->toBeArray()
        ->and(data_get($payload, 'items.0.answer'))->toBe('Cached write.')
        ->and(data_get($payload, 'page'))->toBe(1)
        ->and(data_get($payload, 'hasMore'))->toBeFalse();
});

it('shows cached posts when a remount cannot reach the api', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => [homeFeedPost('q1', 'Cached post.')]])
            ->pushResponse(function (): never {
                throw new ConnectionException('timeout');
            }),
    ]);

    Native::test(Home::class)->assertSee('Cached post.');

    $test = Native::test(Home::class)
        ->assertSee('Cached post.')
        ->assertDontSee('The Pinkary feed could not be loaded. Please try again.');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('refreshing'))->toBeFalse()
        ->and($test->get('errorMessage'))->toBe('');
});

it('hydrates a cached tab when switching back offline', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => [homeFeedPost('q1', 'Recent cached post.')]])
            ->push(['data' => [homeFeedPost('q2', 'Following cached post.')]])
            ->pushResponse(function (): never {
                throw new ConnectionException('timeout');
            }),
    ]);

    Native::test(Home::class)
        ->assertSee('Recent cached post.')
        ->call('showFollowing')
        ->assertSee('Following cached post.')
        ->call('showRecent')
        ->assertSee('Recent cached post.')
        ->assertDontSee('Following cached post.')
        ->assertDontSee('The Pinkary feed could not be loaded. Please try again.');
});

it('hides pin and delete when the viewer does not own the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [
            homeFeedPost('q1', 'Someone else answered.', [
                'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                'to' => ['name' => 'Grace Hopper', 'username' => 'grace'],
            ]),
        ]]),
    ]);

    $test = Native::test(Home::class)
        ->call('openMenu', 'q1')
        ->assertSee('Share')
        ->assertDontSee('View likes')
        ->assertDontSee('Edit answer')
        ->call('togglePinMenu')
        ->call('askDeleteMenu');

    $hasExact = function (array $node, string $text) use (&$hasExact): bool {
        foreach ((array) ($node['props'] ?? []) as $value) {
            if ($value === $text) {
                return true;
            }
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child) && $hasExact($child, $text)) {
                return true;
            }
        }

        return false;
    };

    expect($hasExact($test->tree(), 'Pin'))->toBeFalse()
        ->and($hasExact($test->tree(), 'Delete'))->toBeFalse();

    Http::assertNotSent(function ($request): bool {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return is_string($path) && (str_ends_with($path, '/pin') || str_ends_with($path, '/ignore'));
    });
});

it('shows the unread count on the notifications tab', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    // The bell only exists on the signed-in bar, so the current user
    // must be hydrated for the tab bar to carry the badge.
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'meta' => ['unread_count' => 4],
        ]),
    ]);

    Native::test(Home::class);

    expect(UnreadCount::get())->toBe(4);

    $stored = false;

    foreach (FakeBridge::current()?->calls ?? [] as $call) {
        if ($call['method'] === 'SecureStorage.Set'
            && ($call['params']['key'] ?? null) === 'pinkary_unread_count'
            && ($call['params']['value'] ?? null) === '4') {
            $stored = true;
        }
    }

    expect($stored)->toBeTrue();

    $badge = null;

    foreach ((new MobileLayout)->tabBar(new Home)->tabElements() as $tab) {
        $props = new ReflectionProperty($tab, 'props');
        $values = $props->getValue($tab);

        if (($values['label'] ?? null) === 'Notifications') {
            $badge = $values['badge'] ?? null;
        }
    }

    expect($badge)->toBe('4');
});
