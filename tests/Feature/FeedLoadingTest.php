<?php

use App\NativeComponents\Home;
use App\Support\AccountStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Async\AsyncTaskFailed;
use Native\Mobile\Events\Async\AsyncTaskFinished;
use Native\Mobile\Support\AsyncTaskTransport;
use Native\Mobile\Testing\Native;

/** @return array<string, mixed> */
function pagingPost(string $id): array
{
    return ['id' => $id, 'to' => ['name' => 'Ada', 'username' => 'ada'], 'content' => '__UPDATE__', 'answer' => 'Post '.$id];
}

/** @return list<array<string, mixed>> */
function pagingNodes(array $tree): array
{
    $nodes = [$tree];
    foreach ($tree['children'] ?? [] as $child) {
        $nodes = [...$nodes, ...pagingNodes($child)];
    }

    return $nodes;
}

it('requests only the next feed page and keeps existing posts when a page overlaps', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    $tasks = AsyncTask::fake();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::sequence()
        ->push(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])
        ->push(['data' => [pagingPost('q1'), pagingPost('q2')], 'links' => ['next' => '?page=3']]),
    ]);

    $screen = Native::test(Home::class)->call('prefetchMore')->assertSet('page', 2)->assertSet('hasMore', true);

    expect(array_column($screen->get('posts'), 'id'))->toBe(['q1', 'q2']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['page'] === 2 && $request['tab'] === 'recent');
    $tasks->assertDispatchedTimes(1);
});

it('shows a loader without replacing posts while a single next page is in flight', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])]);

    $screen = Native::test(Home::class)->call('prefetchMore')->call('prefetchMore')
        ->assertSet('loadingMore', true)->assertSee('Post q1')->assertElement('activity_indicator')
        ->assertDontSee('Load more')
        ->assertNativeCalledTimes('AsyncTask.Dispatch', 1);
    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);
    $screen->emitNative(AsyncTaskFinished::class, ['id' => $id, 'result' => ['status' => 200, 'posts' => [pagingPost('q2')], 'hasMore' => false]])
        ->assertSet('loadingMore', false)->assertSet('page', 2)->assertSee('Post q2')->assertMissingElement('activity_indicator');

    Http::assertSentCount(1);
});

it('offers manual loading when another page exists and removes it after the final page', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    $tasks = AsyncTask::fake();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::sequence()
        ->push(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])
        ->push(['data' => [pagingPost('q2')], 'links' => ['next' => null]]),
    ]);

    Native::test(Home::class)->assertSee('Load more')->tap('Load more')
        ->assertSet('page', 2)->assertSee('Post q1')->assertSee('Post q2')
        ->assertDontSee('Load more')->assertSee("You're all caught up.");

    Http::assertSentCount(2);
    $tasks->assertDispatchedTimes(1);
});

it('stops the loader and retains the retryable page when background paging fails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])]);

    $screen = Native::test(Home::class)->call('prefetchMore');
    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);
    $screen->emitNative(AsyncTaskFailed::class, ['id' => $id, 'message' => 'Connection failed'])
        ->assertSet('loadingMore', false)->assertSet('page', 1)->assertSee('Post q1')->assertSee('Try again')
        ->assertMissingElement('activity_indicator')->call('prefetchMore')->assertNativeCalledTimes('AsyncTask.Dispatch', 1);

    Http::assertSentCount(1);
});

it('ignores the previous tab page when it arrives after switching feeds', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::sequence()
        ->push(['data' => [pagingPost('recent')], 'links' => ['next' => '?page=2']])
        ->push(['data' => [pagingPost('following')], 'links' => ['next' => null]]),
    ]);

    $screen = Native::test(Home::class)->call('prefetchMore');
    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);
    $screen->call('showFollowing')
        ->emitNative(AsyncTaskFinished::class, ['id' => $id, 'result' => ['status' => 200, 'posts' => [pagingPost('old-page')], 'hasMore' => true]])
        ->assertSet('page', 1)->assertSet('loadingMore', false)->assertSee('Post following')->assertDontSee('Post old-page');

    Http::assertSentCount(2);
});

it('counts only posts in the scrolling list so prefetch starts three posts before the end', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response([
        'data' => array_map(fn (int $id): array => pagingPost('q'.$id), range(1, 6)),
        'links' => ['next' => '?page=2'],
    ])]);

    $screen = Native::visit('/feed');
    $list = collect(pagingNodes($screen->tree()))->firstWhere('type', 'list');

    expect($list['children'])->toHaveCount(6);
    $screen->assertSee('Pinkary')->assertSee('Recent');
    Http::assertSentCount(1);
});

it('keeps the failed page retryable without automatically repeating its request', function (int $status): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    $tasks = AsyncTask::fake();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::sequence()
        ->push(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])
        ->push(['message' => 'Unavailable'], $status)
        ->push(['data' => [pagingPost('q2')], 'links' => ['next' => null]]),
    ]);

    $screen = Native::test(Home::class)->call('prefetchMore')->call('prefetchMore')
        ->assertSet('page', 1)->assertSet('loadingMore', false)->assertSee('Post q1')->assertSee('Try again');
    Http::assertSentCount(2);
    $screen->tap('Try again')->assertSet('page', 2)->assertSet('hasMore', false)->assertSee('Post q2')
        ->call('prefetchMore')->assertDontSee('Try again');

    Http::assertSentCount(3);
    $tasks->assertDispatchedTimes(2);
})->with([403, 500]);

it('removes the rejected account when the next feed page returns unauthorized', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'expired-token', ['username' => 'ada']);
    AsyncTask::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])
            ->push([], 401),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)->call('prefetchMore')->assertSet('loadingMore', false)->assertReplacedWith('/login');

    expect(AccountStore::activeId())->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request['page'] === 2 && $request->hasHeader('Authorization', 'Bearer expired-token'));
});

it('restarts a suspended page load on resume and ignores its late completion', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => [pagingPost('q1')], 'links' => ['next' => '?page=2']])]);

    $screen = Native::test(Home::class)->call('prefetchMore');
    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);
    $screen->call('onResume')->assertSet('loadingMore', true)->assertNativeCalledTimes('AsyncTask.Dispatch', 2)
        ->emitNative(AsyncTaskFinished::class, ['id' => $id, 'result' => ['status' => 200, 'posts' => [pagingPost('late')], 'hasMore' => false]])
        ->assertSet('page', 1)->assertDontSee('Post late')->assertSet('loadingMore', true);

    $resumedId = $screen->bridge()->callsTo('AsyncTask.Dispatch')[1]['params']['id'];
    AsyncTaskTransport::forgetPayload($resumedId);
    $screen->emitNative(AsyncTaskFinished::class, ['id' => $resumedId, 'result' => ['status' => 200, 'posts' => [pagingPost('q2')], 'hasMore' => false]])
        ->assertSet('page', 2)->assertSet('loadingMore', false)->assertSee('Post q2');
    Http::assertSentCount(1);
});
