<?php

declare(strict_types=1);

use App\NativeComponents\Bookmarks;
use App\NativeComponents\EditProfile;
use App\NativeComponents\FollowList;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Search;
use App\NativeComponents\Settings;
use App\NativeLayout\MobileLayout;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\FeedCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function notificationListContainsText(array $node, string $text): bool
{
    if (($node['type'] ?? null) === 'list' && notificationTreeContainsText($node, $text)) {
        return true;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && notificationListContainsText($child, $text)) {
            return true;
        }
    }

    return false;
}

function notificationTreeContainsText(array $node, string $text): bool
{
    foreach ((array) ($node['props'] ?? []) as $value) {
        if (is_string($value) && str_contains($value, $text)) {
            return true;
        }
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && notificationTreeContainsText($child, $text)) {
            return true;
        }
    }

    return false;
}

function bookmarkRow(string $id, string $answer): array
{
    return [
        'id' => $id,
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'content' => '__UPDATE__',
        'answer' => $answer,
        'metrics' => ['likes' => 0, 'comments' => 0, 'bookmarks' => 1, 'liked' => false, 'bookmarked' => true],
        'views' => 0,
    ];
}

it('redirects guests away from the signed-in-only screens', function (): void {
    Native::test(Bookmarks::class)->assertReplacedWith('/login');
    Native::test(Notifications::class)->assertReplacedWith('/login');
});

it('lists bookmarked posts with an empty state', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('No bookmarks yet.');
});

it('renders bookmarked posts and paginates', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

it('removes a post from bookmarks after it is unbookmarked', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => [bookmarkRow('b1', 'Saved answer.')]]),
        'https://pinkary.test/api/v1/questions/b1/bookmark' => Http::response(['data' => ['bookmarked' => false, 'bookmarks' => 0]]),
    ]);

    $test = Native::test(Bookmarks::class)
        ->assertSee('Saved answer.')
        ->call('toggleBookmark', 'b1')
        ->assertDontSee('Saved answer.');

    expect($test->get('posts'))->toBe([]);
});

it('lists web-style rows and opens the question', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/v1/search'));
});

it('clears results when the query is emptied', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [], 'hashtags' => []]]),
    ]);

    Native::test(Search::class)
        ->set('query', 'x')
        ->set('query', '')
        ->assertSee('Discover new profiles to follow.');
});

it('uses in-content headings instead of a native top bar on tab screens', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => [], 'meta' => ['unread_count' => 0]]),
    ]);

    Native::visit('/bookmarks')->assertSee('No bookmarks yet.');
    Native::visit('/notifications')->assertSee('No pending notifications.');
    Native::visit('/search')->assertSee('Search users or hashtags');
});

it('keeps chrome off content-titled screens and titleless elsewhere', function (): void {
    $layout = new MobileLayout;

    expect($layout->navBar(new Bookmarks))->toBeNull()
        ->and($layout->navBar(new Notifications))->toBeNull()
        ->and($layout->navBar(new Login))->toBeNull()
        ->and($layout->navBar(new Register))->toBeNull();

    $other = new Profile;
    $other->username = 'ada';
    expect($layout->navBar($other))->toBeNull();

    expect($layout->navBar(new Profile))->toBeNull();

    expect($layout->navBar(new Search))->toBeNull()
        ->and($layout->navBar(new Home))->toBeNull()
        ->and($layout->navBar(new Settings))->toBeNull();

    expect($layout->tabBar(new Login))->toBeNull()
        ->and($layout->drawer(new Login))->toBeNull()
        ->and($layout->floatingOverlay(new Login))->toBeNull()
        ->and($layout->tabBar(new Register))->toBeNull()
        ->and($layout->drawer(new Register))->toBeNull()
        ->and($layout->floatingOverlay(new Register))->toBeNull()
        ->and($layout->drawer(new Home))->toBeNull();

    expect($layout->tabBar(new Search))->not->toBeNull()
        ->and($layout->drawer(new Search))->toBeNull()
        ->and($layout->floatingOverlay(new Search))->toBeNull();

    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    expect($layout->floatingOverlay(new Search))->not->toBeNull();

    CurrentUser::clear();
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

it('shows every notification like the web instead of filtering read rows', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

    $screen = Native::test(Notifications::class)
        ->assertSee('Unread snippet.')
        ->assertSee('Read snippet.')
        ->assertSee('Ignore all');

    expect(notificationListContainsText($screen->tree(), 'Notifications'))->toBeFalse()
        ->and(notificationListContainsText($screen->tree(), 'Ignore all'))->toBeFalse();
});

it('removes a notification once it is opened', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

it('bulk ignores notifications and keeps newer notifications', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    $this->freezeTime();

    $question = array_merge(notificationRow('n1', 'Pending question.'), [
        'type' => 'QuestionCreated',
        'read' => false,
        'created_at' => now()->subHour()->toIso8601String(),
        'target' => ['kind' => 'question', 'id' => 'q1'],
    ]);
    $follow = array_merge(notificationRow('n2', 'Follow notification.'), [
        'type' => 'UserFollowed',
        'read' => true,
        'target' => ['kind' => 'user', 'username' => 'ada'],
    ]);
    $newFollow = array_merge(notificationRow('n4', 'New notification.'), [
        'type' => 'UserFollowed',
        'read' => false,
        'created_at' => now()->addMinute()->toIso8601String(),
        'target' => ['kind' => 'user', 'username' => 'new-user'],
    ]);

    $firstPage = [
        'data' => [$question, $follow],
        'links' => ['next' => 'https://pinkary.test/api/v1/notifications?page=2'],
        'meta' => ['unread_count' => 3],
    ];
    $refreshedPage = [
        'data' => [$newFollow],
        'links' => ['next' => null],
        'meta' => ['unread_count' => 1, 'total_count' => 1],
    ];

    Http::fake([
        'https://pinkary.test/api/v1/notifications?page=1' => Http::sequence()
            ->push($firstPage)
            ->push($refreshedPage),
        'https://pinkary.test/api/v1/notifications/ignore' => Http::response(['data' => ['ignored' => true]]),
    ]);

    $test = Native::test(Notifications::class)
        ->assertSee('Ignore all')
        ->call('ignoreAll')
        ->assertDontSee('Pending question.')
        ->assertDontSee('Follow notification.')
        ->assertDontSee('Answered notification.')
        ->assertSee('New notification.');

    $cached = FeedCache::get(FeedCache::notificationsKey('ada'));

    expect($test->get('items'))->toHaveCount(1)
        ->and(data_get($test->get('items'), '0.id'))->toBe('n4')
        ->and($test->get('unreadCount'))->toBe(1)
        ->and($test->get('hasMore'))->toBeFalse()
        ->and(data_get($cached, 'items.0.id'))->toBe('n4')
        ->and(data_get($cached, 'unreadCount'))->toBe(1);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://pinkary.test/api/v1/notifications/ignore'
        && $request['until_datetime'] === now()->format(DATE_ATOM));
    Http::assertSentCount(3);
});

it('ignores read notifications when the unread count is zero', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $page = [
        'data' => [array_merge(notificationRow('n1', 'Read notification.'), [
            'type' => 'UserFollowed',
            'read' => true,
            'target' => ['kind' => 'user', 'username' => 'ada'],
        ])],
        'links' => ['next' => null],
        'meta' => ['unread_count' => 0],
    ];

    Http::fake([
        'https://pinkary.test/api/v1/notifications?page=1' => Http::sequence()
            ->push($page)
            ->push([
                'data' => [],
                'links' => ['next' => null],
                'meta' => ['unread_count' => 0, 'total_count' => 0],
            ]),
        'https://pinkary.test/api/v1/notifications/ignore' => Http::response(['data' => ['ignored' => true]]),
    ]);

    $test = Native::test(Notifications::class)
        ->assertSee('Ignore all')
        ->call('ignoreAll')
        ->assertDontSee('Read notification.')
        ->assertSee('No pending notifications.');

    $cached = FeedCache::get(FeedCache::notificationsKey('ada'));

    expect($test->get('items'))->toBe([])
        ->and($test->get('unreadCount'))->toBe(0)
        ->and(data_get($cached, 'items'))->toBe([]);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://pinkary.test/api/v1/notifications/ignore');
    Http::assertSentCount(3);
});

it('keeps notifications cached when the ignore-all request fails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $page = [
        'data' => [notificationRow('n1', 'Pending notification.')],
        'links' => ['next' => null],
        'meta' => ['unread_count' => 1],
    ];

    Http::fake([
        'https://pinkary.test/api/v1/notifications?page=1' => Http::response($page),
        'https://pinkary.test/api/v1/notifications/ignore' => Http::response(['message' => 'Unavailable.'], 500),
    ]);

    $test = Native::test(Notifications::class)
        ->call('ignoreAll')
        ->assertSee('Notifications could not be ignored. Please try again.');

    $cached = FeedCache::get(FeedCache::notificationsKey('ada'));

    expect($test->get('items'))->toHaveCount(1)
        ->and($test->get('unreadCount'))->toBe(1)
        ->and($test->get('ignoringAll'))->toBeFalse()
        ->and(data_get($cached, 'items.0.id'))->toBe('n1');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://pinkary.test/api/v1/notifications/ignore');
    Http::assertSentCount(2);
});

it('keeps the bookmarks empty state text-only like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('No bookmarks yet.')
        ->assertDontSee('Back to Feed');
});

it('keeps the notifications empty state text-only like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Notifications::class)
        ->assertSee('No pending notifications.')
        ->assertDontSee('Back to Feed');
});

it('clears the search and recalls recent searches locally', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->assertSee('Discover new profiles to follow.')
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

function followRow(string $username, string $name, bool $followed = false, bool $isMe = false): array
{
    return [
        'id' => $username === 'ada' ? 2 : 3,
        'name' => $name,
        'username' => $username,
        'avatar' => null,
        'verification' => ['profile' => false, 'company' => false],
        'followed_by_me' => $followed,
        'is_me' => $isMe,
    ];
}

it('lists followers with pagination', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::sequence()
            ->push([
                'data' => [followRow('grace', 'Grace Hopper')],
                'links' => ['next' => 'https://pinkary.test/api/v1/users/ada/followers?page=2'],
            ])
            ->push([
                'data' => [followRow('linus', 'Linus Torvalds')],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(FollowList::class, ['username' => 'ada'])
        ->assertSee('Grace Hopper')
        ->assertSee('@ada followers')
        ->call('loadMore')
        ->assertSee('Linus Torvalds');
});

it('lists following in following mode and resolves the routes', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada/following*' => Http::response([
            'data' => [followRow('grace', 'Grace Hopper', true)],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::response([
            'data' => [followRow('linus', 'Linus Torvalds')],
            'links' => ['next' => null],
        ]),
    ]);

    Native::test(FollowList::class, ['username' => 'ada', 'mode' => 'following'])
        ->assertSee('Grace Hopper')
        ->assertSee('@ada following');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/v1/users/ada/following'));

    Native::visit('/user/ada/followers')->assertSee('Linus Torvalds');
    Native::visit('/user/ada/following')->assertSee('Grace Hopper');
});

it('opens profiles from follow rows', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::response([
            'data' => [followRow('grace', 'Grace Hopper')],
            'links' => ['next' => null],
        ]),
    ]);

    Native::test(FollowList::class, ['username' => 'ada'])
        ->press("openProfile('grace')")
        ->assertNavigatedTo('/user/grace');
});

it('follows and unfollows inline from the list', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::response([
            'data' => [followRow('grace', 'Grace Hopper')],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['data' => ['followed' => true]]),
    ]);

    $test = Native::test(FollowList::class, ['username' => 'ada'])
        ->assertSee('Follow')
        ->call('toggleFollow', 'grace')
        ->assertSee('Unfollow');

    expect($test->get('users')[0])->toMatchArray(['followed_by_me' => true]);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/users/grace/follow'));
});

it('shows an empty follow list like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::response(['data' => []]),
    ]);

    Native::test(FollowList::class, ['username' => 'ada'])
        ->assertSee('@ada does not have any followers');
});

it('edits the profile and refreshes the cached card', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    $card = ownProfile();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::sequence()
            ->push(['data' => $card])
            ->push(['data' => array_merge($card, ['name' => 'New Name'])]),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Pinkary User')
        ->assertSee('Change photo')
        ->assertSee('JPG or PNG, up to 2 MB.')
        ->set('name', 'New Name')
        ->call('save')
        ->assertSee('Your profile has been saved.');

    expect($test->get('name'))->toBe('New Name');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/profile')
        && $request['name'] === 'New Name');
});

it('shows per-field profile errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::sequence()
            ->push(['data' => ownProfile()])
            ->push([
                'message' => 'The given data was invalid.',
                'errors' => ['username' => ['The username is taken.']],
            ], 422),
    ]);

    Native::test(EditProfile::class)
        ->set('username', 'taken')
        ->call('save')
        ->assertSee('The username is taken.');
});

it('adds and deletes profile links with a confirm tap', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/links' => Http::response(['data' => [
            'id' => 9, 'description' => 'Blog', 'url' => 'https://blog.example.com?ref=pinkary', 'click_count' => 0,
        ]], 201),
        'https://pinkary.test/api/v1/links/9' => Http::response(null, 204),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Website')
        ->set('newDescription', 'Blog')
        ->set('newUrl', 'https://blog.example.com')
        ->call('addLink')
        ->assertSee('Blog');

    expect($test->get('links'))->toHaveCount(2);

    $test->call('deleteLink', 9)->assertSee('Tap again to confirm');

    expect($test->get('links'))->toHaveCount(2);

    $test->call('deleteLink', 9)->assertSee('Link deleted.');

    expect($test->get('links'))->toHaveCount(1);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/links/9'));
});

it('rejects HTTP profile links without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
    ]);

    Native::test(EditProfile::class)
        ->set('newDescription', 'Blog')
        ->set('newUrl', 'http://blog.example.com')
        ->call('addLink')
        ->assertSee('https://');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/links'));
});

it('surfaces link limit messages from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownProfile()]),
        'https://pinkary.test/api/v1/links' => Http::response([
            'message' => 'You can only have 10 links at a time.',
        ], 422),
    ]);

    Native::test(EditProfile::class)
        ->set('newDescription', 'Extra')
        ->set('newUrl', 'https://extra.example.com')
        ->call('addLink')
        ->assertSee('10 links at a time');
});

it('deletes a notification when opening it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [array_merge(notificationRow('n1', 'Opened snippet.'), ['read' => false])],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/n1' => Http::response(null, 204),
    ]);

    Native::test(Notifications::class)
        ->assertSee('Opened snippet.')
        ->call('open', 'n1')
        ->assertDontSee('Opened snippet.')
        ->assertNavigatedTo('/question/q9');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/notifications/n1'));
    Http::assertNotSent(fn ($request): bool => str_ends_with((string) $request->url(), '/api/v1/notifications/read'));
});

it('treats a missing notification as already gone', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [array_merge(notificationRow('n1', 'Gone snippet.'), ['read' => false])],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
        'https://pinkary.test/api/v1/notifications/n1' => Http::response(['message' => 'Not found.'], 404),
    ]);

    Native::test(Notifications::class)
        ->call('open', 'n1')
        ->assertDontSee('Gone snippet.')
        ->assertNavigatedTo('/question/q9');

    Http::assertNotSent(fn ($request): bool => str_ends_with((string) $request->url(), '/api/v1/notifications/read'));
});

it('keeps cached bookmarks when a remount cannot reach the api', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::sequence()
            ->push(['data' => [bookmarkRow('b1', 'Cached bookmark.')]])
            ->pushResponse(function (): never {
                throw new ConnectionException('timeout');
            }),
    ]);

    Native::test(Bookmarks::class)->assertSee('Cached bookmark.');

    $test = Native::test(Bookmarks::class)
        ->assertSee('Cached bookmark.')
        ->assertDontSee('Your bookmarks could not be loaded.');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('refreshing'))->toBeFalse()
        ->and($test->get('errorMessage'))->toBe('');
});

it('keeps cached notifications when a remount cannot reach the api', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::sequence()
            ->push([
                'data' => [notificationRow('n1', 'Cached snippet.')],
                'links' => ['next' => null],
                'meta' => ['unread_count' => 1],
            ])
            ->pushResponse(function (): never {
                throw new ConnectionException('timeout');
            }),
    ]);

    Native::test(Notifications::class)->assertSee('Cached snippet.');

    $test = Native::test(Notifications::class)
        ->assertSee('Cached snippet.')
        ->assertDontSee('Your notifications could not be loaded.');

    expect($test->get('loading'))->toBeFalse()
        ->and($test->get('refreshing'))->toBeFalse()
        ->and($test->get('unreadCount'))->toBe(1)
        ->and($test->get('errorMessage'))->toBe('');
});
