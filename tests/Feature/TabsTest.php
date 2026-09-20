<?php

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Notifications;
use App\NativeComponents\Search;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function bookmarkRow(string $id, string $answer): array
{
    return [
        'id' => $id,
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'content' => '__UPDATE__',
        'answer' => $answer,
        'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => true],
        'views' => 0,
    ];
}

it('redirects guests away from the new screens', function (): void {
    Native::test(Bookmarks::class)->assertReplacedWith('/login');
    Native::test(Notifications::class)->assertReplacedWith('/login');
    Native::test(Search::class)->assertReplacedWith('/login');
});

it('lists bookmarked posts with an empty state', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('No bookmarks yet.');
});

it('renders bookmarked posts and paginates', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::sequence()
            ->push([
                'data' => [bookmarkRow('b1', 'Saved answer.')],
                'links' => ['next' => 'https://pinkary.test/api/v1/bookmarks?page=2'],
            ])
            ->push([
                'data' => [bookmarkRow('b2', 'Older saved answer.')],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('Saved answer.')
        ->call('loadMore')
        ->assertSee('Older saved answer.');
});

it('lists web-style rows and opens the question', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [[
                'id' => 'n1',
                'type' => 'QuestionAnswered',
                'read' => false,
                'created_at' => now()->subHour()->toIso8601String(),
                'actor' => ['id' => 2, 'name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null, 'verified' => true, 'company_verified' => false],
                'action' => 'answered your question:',
                'snippet' => 'What are you building?',
                'target' => ['kind' => 'question', 'id' => 'q9'],
            ]],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/read' => Http::response(['data' => ['unread_count' => 0]]),
    ]);

    $test = Native::test(Notifications::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('answered your question:')
        ->assertSee('What are you building?')
        ->assertSee('Ignore all')
        ->press("open('n1')")
        ->assertNavigatedTo('/question/q9');

    expect($test->get('unreadCount'))->toBe(0);
});

it('opens follower profiles from follow rows', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [[
                'id' => 'n2',
                'type' => 'UserFollowed',
                'read' => false,
                'created_at' => now()->subHour()->toIso8601String(),
                'actor' => ['id' => 2, 'name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null, 'verified' => false, 'company_verified' => false],
                'action' => 'followed you',
                'snippet' => null,
                'target' => ['kind' => 'user', 'username' => 'ada'],
            ]],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/read' => Http::response(['data' => ['unread_count' => 0]]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('followed you')
        ->press("open('n2')")
        ->assertNavigatedTo('/user/ada');
});

it('shows the web empty state', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('No pending notifications.')
        ->assertSee('New replies, mentions, followers');
});

it('searches people and hashtags', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => [
            'users' => [[
                'id' => 1,
                'name' => 'Ada Lovelace',
                'username' => 'ada',
                'avatar' => null,
                'verified' => true,
                'company_verified' => false,
                'followed' => true,
            ]],
            'hashtags' => [['id' => 1, 'name' => 'laravel', 'questions_count' => 12]],
        ]]),
    ]);

    Native::test(Search::class)
        ->set('query', 'ada')
        ->assertSee('Ada Lovelace')
        ->assertSee('#laravel')
        ->assertSee('12 posts')
        ->press("openProfile('ada')")
        ->assertNavigatedTo('/user/ada');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/api/v1/search'));
});

it('clears results when the query is emptied', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [], 'hashtags' => []]]),
    ]);

    Native::test(Search::class)
        ->set('query', 'x')
        ->set('query', '')
        ->assertSee('Search people by name');
});

it('uses in-content headings instead of a native top bar on tab screens', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => [], 'meta' => ['unread_count' => 0]]),
    ]);

    Native::test(Bookmarks::class)
        ->assertMissingElement('top_bar')
        ->assertSee('Bookmarks');

    Native::test(Notifications::class)
        ->assertMissingElement('top_bar')
        ->assertSee('Notifications');

    Native::test(Search::class)
        ->assertMissingElement('top_bar')
        ->assertSee('Search users or hashtags');
});

it('resolves the tabbed routes', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => [], 'meta' => ['unread_count' => 0]]),
    ]);

    Native::visit('/bookmarks')->assertSee('No bookmarks yet.');
    Native::visit('/notifications')->assertSee('No pending notifications.');
    Native::visit('/search')->assertSee('Search users or hashtags');
});
