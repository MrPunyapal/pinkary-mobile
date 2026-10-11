<?php

declare(strict_types=1);

use App\Support\AccountStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Async\AsyncTaskFinished;
use Native\Mobile\Support\AsyncTaskTransport;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
});

/** @return array<string, mixed> */
function hashtagPagePost(string $id, string $answer): array
{
    $author = ['name' => 'Ada Lovelace', 'username' => 'ada'];

    return [
        'id' => $id,
        'from' => $author,
        'to' => $author,
        'content' => '__UPDATE__',
        'answer' => $answer,
        'metrics' => ['comments' => 0, 'likes' => 0, 'reposts' => 0, 'bookmarks' => 0],
        'views' => 0,
    ];
}

it('loads public hashtag posts in pages of twenty', function (): void {
    $tasks = AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::sequence()
            ->push(['data' => [hashtagPagePost('q1', 'First public post.')], 'links' => ['next' => '?page=2']])
            ->push(['data' => [hashtagPagePost('q2', 'Second public post.')], 'links' => ['next' => null]]),
    ]);

    $screen = Native::visit('/hashtag/42')
        ->assertSee('Hashtag posts')
        ->assertSee('First public post.')
        ->assertSet('page', 1)
        ->assertSet('hasMore', true)
        ->call('loadMore')
        ->assertSee('Second public post.')
        ->assertSet('page', 2)
        ->assertSet('hasMore', false)
        ->assertSet('loadingMore', false)
        ->assertSee("You're all caught up.");

    expect($screen->get('posts'))->toHaveCount(2);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/hashtags/42/questions')
        && $request['per_page'] === 20
        && $request['page'] === 1
        && ! $request->hasHeader('Authorization'));
    Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/hashtags/42/questions')
        && $request['per_page'] === 20
        && $request['page'] === 2
        && ! $request->hasHeader('Authorization'));
    $tasks->assertDispatchedTimes(1);
});

it('keeps the failed next page retryable without discarding loaded posts', function (): void {
    $tasks = AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::sequence()
            ->push(['data' => [hashtagPagePost('q1', 'First public post.')], 'links' => ['next' => '?page=2']])
            ->push(['message' => 'Unavailable'], 500)
            ->push(['data' => [hashtagPagePost('q2', 'Second public post.')], 'links' => ['next' => null]]),
    ]);

    $screen = Native::visit('/hashtag/42')
        ->call('loadMore')
        ->assertSet('page', 1)
        ->assertSet('loadingMore', false)
        ->assertSee('First public post.')
        ->assertSee('Could not load more posts. Please try again.')
        ->tap('Try again')
        ->assertSet('page', 2)
        ->assertSet('hasMore', false)
        ->assertSee('First public post.')
        ->assertSee('Second public post.')
        ->assertDontSee('Could not load more posts. Please try again.');

    expect($screen->get('posts'))->toHaveCount(2);

    Http::assertSentCount(3);

    expect(Http::recorded(fn (Request $request): bool => $request['page'] === 2 && $request['per_page'] === 20))
        ->toHaveCount(2);
    $tasks->assertDispatchedTimes(2);
});

it('shows a retry action when the first hashtag page cannot be loaded', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::sequence()
            ->push(['message' => 'Unavailable'], 503)
            ->push(['data' => [hashtagPagePost('q1', 'Recovered public post.')], 'links' => ['next' => null]]),
    ]);

    Native::visit('/hashtag/42')
        ->assertSet('loading', false)
        ->assertSet('errorMessage', 'This hashtag feed could not be loaded. Please try again.')
        ->tap('Try again')
        ->assertSet('errorMessage', '')
        ->assertSee('Recovered public post.');

    Http::assertSentCount(2);
});

it('sends the active account token when available', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::response([
            'data' => [hashtagPagePost('q1', 'Authenticated public post.')],
            'links' => ['next' => null],
        ]),
    ]);

    Native::visit('/hashtag/42')->assertSee('Authenticated public post.');

    Http::assertSent(fn (Request $request): bool => $request['per_page'] === 20
        && $request['page'] === 1
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('shows a loader while the next hashtag page is being fetched', function (): void {
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::response([
            'data' => [hashtagPagePost('q1', 'First public post.')],
            'links' => ['next' => '?page=2'],
        ]),
    ]);

    $screen = Native::visit('/hashtag/42')
        ->call('loadMore')
        ->assertSet('loadingMore', true)
        ->assertElement('activity_indicator')
        ->assertSee('First public post.')
        ->assertSet('page', 1);

    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);

    $screen->emitNative(AsyncTaskFinished::class, [
        'id' => $id,
        'result' => [
            'status' => 200,
            'posts' => [hashtagPagePost('q2', 'Second public post.')],
            'hasMore' => false,
        ],
    ])->assertSet('loadingMore', false)
        ->assertSet('page', 2)
        ->assertSet('hasMore', false)
        ->assertSee('Second public post.')
        ->assertMissingElement('activity_indicator');

    Http::assertSentCount(1);
});

it('removes the active account when the next hashtag page returns unauthorized', function (): void {
    AccountStore::add('ada', 'expired-token', ['username' => 'ada']);
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/hashtags/42/questions*' => Http::sequence()
            ->push(['data' => [hashtagPagePost('q1', 'First public post.')], 'links' => ['next' => '?page=2']])
            ->push([], 401),
    ]);

    Native::visit('/hashtag/42')->call('loadMore')->assertReplacedWith('/login');

    expect(AccountStore::activeId())->toBeNull();
});

it('returns to search when the hashtag route has an invalid id', function (): void {
    Native::visit('/hashtag/0')->assertReplacedWith('/search');

    Http::assertNothingSent();
});
