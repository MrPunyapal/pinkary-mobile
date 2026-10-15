<?php

declare(strict_types=1);

use App\NativeComponents\HashtagFeed;
use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
});

function searchResults(array $users = [], array $hashtags = []): array
{
    return ['data' => ['users' => $users, 'hashtags' => $hashtags]];
}

/** @param array<string, mixed> $responses */
function fakeSearchApi(array $responses): void
{
    AsyncTask::fake();
    Http::fake(array_merge([
        'https://pinkary.test/api/v1/users/recommendations*' => Http::response(['data' => [], 'links' => ['next' => null]]),
        'https://pinkary.test/api/v1/users/directory*' => Http::response(['data' => [], 'links' => ['next' => null]]),
    ], $responses));
}

function foundUser(array $overrides = []): array
{
    return array_merge([
        'id' => 2,
        'name' => 'Grace Hopper',
        'username' => 'grace',
        'avatar' => null,
        'verified' => false,
        'company_verified' => false,
        'followed' => false,
        'is_me' => false,
    ], $overrides);
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function discoveredUser(array $overrides = []): array
{
    return array_merge([
        'id' => 3,
        'name' => 'Katherine Johnson',
        'username' => 'katherine',
        'avatar' => null,
        'verification' => ['profile' => false, 'company' => false],
        'followed_by_me' => false,
        'follows_me' => false,
        'is_me' => false,
    ], $overrides);
}

it('shows recommendations and the paginated people directory without a query', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/users/recommendations*' => Http::response([
            'data' => [discoveredUser()],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/users/directory*' => Http::response([
            'data' => [discoveredUser(['id' => 4, 'name' => 'Dorothy Vaughan', 'username' => 'dorothy'])],
            'links' => ['next' => '?page=2'],
        ]),
    ]);

    Native::visit('/search')
        ->assertSee('People to follow')
        ->assertSee('Katherine Johnson')
        ->assertSee('Discover people')
        ->assertSee('Dorothy Vaughan');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/recommendations')
        && $request['context'] === 'generic'
        && $request['per_page'] === 5
        && $request['page'] === 1);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/directory')
        && $request['per_page'] === 20
        && $request['page'] === 1);
});

it('loads the next recommendation and directory pages independently', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/users/recommendations*' => Http::sequence()
            ->push(['data' => [discoveredUser()], 'links' => ['next' => '?page=2']])
            ->push(['data' => [discoveredUser(['id' => 5, 'name' => 'Mary Jackson', 'username' => 'mary'])], 'links' => ['next' => null]]),
        'https://pinkary.test/api/v1/users/directory*' => Http::sequence()
            ->push(['data' => [discoveredUser(['id' => 4, 'name' => 'Dorothy Vaughan', 'username' => 'dorothy'])], 'links' => ['next' => '?page=2']])
            ->push(['data' => [discoveredUser(['id' => 6, 'name' => 'Annie Easley', 'username' => 'annie'])], 'links' => ['next' => null]]),
    ]);

    $screen = Native::visit('/search')
        ->call('loadMoreRecommendations')
        ->call('loadMoreDirectory')
        ->assertSee('Mary Jackson')
        ->assertSee('Annie Easley')
        ->assertSet('recommendationPage', 2)
        ->assertSet('directoryPage', 2)
        ->assertSet('recommendationsHasMore', false)
        ->assertSet('directoryHasMore', false);

    expect($screen->get('recommendations'))->toHaveCount(2)
        ->and($screen->get('directoryUsers'))->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/recommendations')
        && $request['page'] === 2
        && $request['per_page'] === 5);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/directory')
        && $request['page'] === 2
        && $request['per_page'] === 20);
});

it('keeps the query and results in step as it is typed', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSee('Grace Hopper')
        ->assertSet('searched', true)
        ->set('query', '')
        ->assertSet('searched', false)
        ->assertDontSee('Grace Hopper');
});

it('maps flat search flags to the profile row fields', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser([
            'verified' => true,
            'company_verified' => true,
            'followed' => true,
        ])])),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSee('Unfollow')
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? '') === '#EAB308');

    expect($screen->get('users')[0])->toMatchArray([
        'verification' => ['profile' => true, 'company' => true],
        'followed_by_me' => true,
    ]);
});

it('labels a mutual-follow action as Follow Back when the API provides it', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser(['follows_me' => true])])),
    ]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSee('Follow Back');
});

it('replays a recent search and ignores an unusable one', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->set('query', '')
        ->assertSee('Recent searches')
        ->assertSee('grace');

    $screen->call('selectRecent', 0)
        ->assertSet('query', 'grace')
        ->assertSee('Grace Hopper');

    $screen->call('selectRecent', 99)->assertSet('query', 'grace');
});

it('clears the query but keeps the recent searches on offer', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->set('query', '')
        ->tap('Search grace again')
        ->call('clearQuery')
        ->assertSet('query', '')
        ->assertSet('users', [])
        ->assertSee('Recent searches');
});

it('reports a failed search and keeps the screen usable', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::failedConnection()]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSet('searching', false)
        ->assertSet('errorMessage', 'Search could not be completed. Please try again.')
        ->assertSee('Search could not be completed. Please try again.');
});

it('drops the dead token when the search is unauthorized', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(null, 401)]);

    Native::test(Search::class)->set('query', 'grace');

    expect(AccountStore::activeToken())->toBeNull();
});

it('leaves results empty on an unsuccessful search response', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(['message' => 'Server error.'], 500)]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSet('searched', false)
        ->assertSet('users', []);
});

it('follows a person and reflects the new state', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['data' => ['followed' => true]]),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Follow')
        ->assertSee('Unfollow');

    expect($screen->get('users')[0]['followed_by_me'])->toBeTrue()
        ->and($screen->get('users')[0]['_busy'])->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pinkary.test/api/v1/users/grace/follow'
        && $request->method() === 'POST');
});

it('unfollows someone already followed', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser(['followed' => true])])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['data' => ['followed' => false]]),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Unfollow')
        ->assertSee('Follow');

    expect($screen->get('users')[0]['followed_by_me'])->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/grace/follow')
        && $request->method() === 'DELETE');
});

it('refuses to follow yourself or an unknown person', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser(['is_me' => true])]))]);

    $screen = Native::test(Search::class)->set('query', 'grace')->assertDontSee('Follow');

    $screen->call('toggleFollow', 'grace')->call('toggleFollow', 'nobody')->call('toggleFollow', '');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/follow'));
});

it('asks for a token before following', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    $screen = Native::test(Search::class)->set('query', 'grace');

    AccountStore::clear();

    $screen->call('toggleFollow', 'grace')->assertNavigatedTo('/login');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/follow'));
});

it('clears a busy row when the follow request fails', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::failedConnection(),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Follow')
        ->assertSee('Follow');

    expect($screen->get('users')[0]['_busy'])->toBeFalse()
        ->and($screen->get('users')[0]['followed_by_me'])->toBeFalse();
});

it('drops the dead token when following is unauthorized', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(null, 401),
    ]);

    Native::test(Search::class)->set('query', 'grace')->tap('Follow');

    expect(AccountStore::activeToken())->toBeNull();
});

it('leaves the follow state alone on an unsuccessful response', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['message' => 'Server error.'], 500),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Follow');

    expect($screen->get('users')[0]['followed_by_me'])->toBeFalse();
});

it('opens a profile from a result row', function (): void {
    fakeSearchApi(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Open @grace’s profile')
        ->assertNavigatedTo('/user/grace');

    Native::test(Search::class)->call('openProfile', '');
});

it('opens a hashtag feed and returns to the existing search', function (): void {
    fakeSearchApi([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([], [[
            'id' => 42,
            'name' => 'laravel',
            'questions_count' => 12,
        ]])),
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::response([
            'data' => [[
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'content' => '__UPDATE__',
                'answer' => 'A public post tagged with Laravel.',
            ]],
            'links' => ['next' => null],
        ]),
    ]);

    $search = Native::visit('/search')
        ->set('query', 'web')
        ->assertSee('#laravel')
        ->assertSee('12 posts')
        ->tap('Open posts for hashtag #laravel')
        ->assertNavigatedTo('/hashtag/42');

    $feed = $search->follow()
        ->assertScreen(HashtagFeed::class)
        ->assertSee('#laravel')
        ->assertSee('A public post tagged with Laravel.');

    $feed->goBack()
        ->assertScreen(Search::class)
        ->assertSet('query', 'web')
        ->assertSee('#laravel');
});
