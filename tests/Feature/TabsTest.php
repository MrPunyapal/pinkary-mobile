<?php

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Search;
use App\NativeComponents\Settings;
use App\NativeLayout\MobileLayout;
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

it('keeps exactly one title per screen and hides chrome on auth screens', function (): void {
    $layout = new MobileLayout;

    // Screens with their own in-content heading suppress the chrome bar
    // (the old Jump app left-aligns chrome titles; new clients would
    // render both) — auth cards carry their own titles too.
    expect($layout->navBar(new Bookmarks))->toBeNull()
        ->and($layout->navBar(new Notifications))->toBeNull()
        ->and($layout->navBar(new Login))->toBeNull()
        ->and($layout->navBar(new Register))->toBeNull();

    $other = new Profile;
    $other->username = 'ada';
    expect($layout->navBar($other))->toBeNull();

    // Search, Settings, and your own profile have no in-content title,
    // so they keep the chrome one.
    expect($layout->navBar(new Search))->not->toBeNull()
        ->and($layout->navBar(new Settings))->not->toBeNull()
        ->and($layout->navBar(new Profile))->not->toBeNull();

    // Auth screens never get tabs, drawer, or the compose entry (they
    // also sit outside the layout route group).
    expect($layout->tabBar(new Login))->toBeNull()
        ->and($layout->drawer(new Login))->toBeNull()
        ->and($layout->floatingOverlay(new Login))->toBeNull()
        ->and($layout->tabBar(new Register))->toBeNull()
        ->and($layout->drawer(new Register))->toBeNull()
        ->and($layout->floatingOverlay(new Register))->toBeNull();

    expect($layout->tabBar(new Search))->not->toBeNull()
        ->and($layout->drawer(new Search))->not->toBeNull()
        ->and($layout->floatingOverlay(new Search))->not->toBeNull();
});

function notificationRow(string $id, string $snippet): array
{
    return [
        'id' => $id,
        'type' => 'QuestionAnswered',
        'read' => false,
        'created_at' => now()->subHour()->toIso8601String(),
        'actor' => ['id' => 2, 'name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null, 'verified' => false, 'company_verified' => false],
        'action' => 'answered your question:',
        'snippet' => $snippet,
        'target' => ['kind' => 'question', 'id' => 'q9'],
    ];
}

it('paginates notifications and surfaces load-more failures', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::sequence()
            ->push([
                'data' => [notificationRow('n1', 'First snippet.')],
                'links' => ['next' => 'https://pinkary.test/api/v1/notifications?page=2'],
                'meta' => ['unread_count' => 2],
            ])
            ->push([
                'data' => [notificationRow('n2', 'Second snippet.')],
                'links' => ['next' => 'https://pinkary.test/api/v1/notifications?page=3'],
                'meta' => ['unread_count' => 2],
            ])
            ->push(['message' => 'Server error'], 500),
    ]);

    Native::test(Notifications::class)
        ->assertSee('First snippet.')
        ->call('loadMore')
        ->assertSee('Second snippet.')
        ->call('loadMore')
        ->assertSee('Could not load more notifications. Please try again.');
});

it('shows per-field login errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'message' => 'These credentials do not match our records.',
            'errors' => [
                'email' => ['The email address is not registered.'],
                'password' => ['The password is incorrect.'],
            ],
        ], 422),
    ]);

    Native::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertSee('The email address is not registered.')
        ->assertSee('The password is incorrect.');
});

it('shows client-side login validation errors per field', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    Native::test(Login::class)
        ->set('email', 'not-an-email')
        ->set('password', '')
        ->call('authenticate')
        ->assertSee('must be a valid email address');
});

it('shows per-field registration errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/auth/register' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => [
                'username' => ['The username is taken.'],
                'password' => ['The password is too short.'],
                'terms' => ['You must accept the terms.'],
            ],
        ], 422),
    ]);

    Native::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('username', 'adalovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('terms', true)
        ->call('createAccount')
        ->assertSee('The username is taken.')
        ->assertSee('The password is too short.')
        ->assertSee('You must accept the terms.');
});

it('drops read notifications on load like ignored ones on the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [
                array_merge(notificationRow('n1', 'Unread snippet.'), ['read' => false]),
                array_merge(notificationRow('n2', 'Read snippet.'), ['read' => true]),
            ],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('Unread snippet.')
        ->assertDontSee('Read snippet.')
        ->assertSee('Ignore all');
});

it('removes a notification once it is opened', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [array_merge(notificationRow('n1', 'Opened snippet.'), ['read' => false])],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/read' => Http::response([
            'data' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('Opened snippet.')
        ->call('open', 'n1')
        ->assertDontSee('Opened snippet.')
        ->assertSee('No pending notifications.');
});

it('clears the list on ignore all like the web delete', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [array_merge(notificationRow('n1', 'Pending snippet.'), ['read' => false])],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/read' => Http::response([
            'data' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('Pending snippet.')
        ->call('markAllRead')
        ->assertDontSee('Pending snippet.')
        ->assertSee('No pending notifications.');
});

it('returns to the feed from the bookmarks empty state', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('Back to Feed')
        ->call('goFeed')
        ->assertNavigatedTo('/feed');
});

it('returns to the feed from the notifications empty state', function (): void {
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
        ->assertSee('Back to Feed')
        ->call('goFeed')
        ->assertNavigatedTo('/feed');
});

it('clears the search and recalls recent searches locally', function (): void {
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
            'hashtags' => [],
        ]]),
    ]);

    $test = Native::test(Search::class)
        ->set('query', 'ada')
        ->assertSee('Ada Lovelace')
        ->assertSee('Clear');

    expect($test->get('recentSearches'))->toBe(['ada']);

    $test
        ->call('clearQuery')
        ->assertSee('Search people by name')
        ->assertSee('Recent searches')
        ->press('selectRecent(0)')
        ->assertSee('Ada Lovelace');

    Http::assertSentCount(2);
});

it('toggles password visibility on the auth screens', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    $login = Native::test(Login::class)
        ->assertSee('Show password')
        ->call('togglePasswordVisibility')
        ->assertSee('Hide password');

    expect($login->get('showPassword'))->toBeTrue();

    $register = Native::test(Register::class)
        ->assertSee('Show password')
        ->call('togglePasswordVisibility')
        ->assertSee('Hide password');

    expect($register->get('showPassword'))->toBeTrue();
});
