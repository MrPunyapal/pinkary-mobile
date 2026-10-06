<?php

declare(strict_types=1);

use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

function foundUser(array $overrides = []): array
{
    return array_merge([
        'id' => 2,
        'name' => 'Grace Hopper',
        'username' => 'grace',
        'avatar' => null,
        'verification' => ['profile' => false, 'company' => false],
        'followed_by_me' => false,
        'is_me' => false,
    ], $overrides);
}

it('keeps the query and results in step as it is typed', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSee('Grace Hopper')
        ->assertSet('searched', true)
        ->set('query', '')
        ->assertSet('searched', false)
        ->assertDontSee('Grace Hopper');
});

it('replays a recent search and ignores an unusable one', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

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
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

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
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::failedConnection()]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSet('searching', false)
        ->assertSet('errorMessage', 'Search could not be completed. Please try again.')
        ->assertSee('Search could not be completed. Please try again.');
});

it('drops the dead token when the search is unauthorized', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(null, 401)]);

    Native::test(Search::class)->set('query', 'grace');

    expect(AccountStore::activeToken())->toBeNull();
});

it('leaves results empty on an unsuccessful search response', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(['message' => 'Server error.'], 500)]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->assertSet('searched', false)
        ->assertSet('users', []);
});

it('follows a person and reflects the new state', function (): void {
    Http::fake([
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
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser(['followed_by_me' => true])])),
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
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser(['is_me' => true])]))]);

    $screen = Native::test(Search::class)->set('query', 'grace')->assertDontSee('Follow');

    $screen->call('toggleFollow', 'grace')->call('toggleFollow', 'nobody')->call('toggleFollow', '');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/follow'));
});

it('asks for a token before following', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    $screen = Native::test(Search::class)->set('query', 'grace');

    AccountStore::clear();

    $screen->call('toggleFollow', 'grace')->assertNavigatedTo('/login');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/follow'));
});

it('clears a busy row when the follow request fails', function (): void {
    Http::fake([
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
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(null, 401),
    ]);

    Native::test(Search::class)->set('query', 'grace')->tap('Follow');

    expect(AccountStore::activeToken())->toBeNull();
});

it('leaves the follow state alone on an unsuccessful response', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()])),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['message' => 'Server error.'], 500),
    ]);

    $screen = Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Follow');

    expect($screen->get('users')[0]['followed_by_me'])->toBeFalse();
});

it('opens a profile from a result row', function (): void {
    Http::fake(['https://pinkary.test/api/v1/search*' => Http::response(searchResults([foundUser()]))]);

    Native::test(Search::class)
        ->set('query', 'grace')
        ->tap('Open @grace’s profile')
        ->assertNavigatedTo('/user/grace');

    Native::test(Search::class)->call('openProfile', '');
});

it('turns a hashtag result into the next search', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(searchResults([], [['name' => 'laravel', 'questions_count' => 12]])),
    ]);

    Native::test(Search::class)
        ->set('query', 'web')
        ->assertSee('#laravel')
        ->assertSee('12 posts')
        ->tap('Search hashtag #laravel')
        ->assertSet('query', '#laravel')
        ->assertSet('searched', true)
        ->call('openHashtag', '');
});
