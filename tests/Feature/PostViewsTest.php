<?php

use App\NativeComponents\Home;
use App\Support\AccountStore;
use App\Support\PostViews;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config([
        'services.pinkary.url' => 'https://pinkary.test',
        'cache.stores.impressions' => ['driver' => 'array', 'serialize' => false],
    ]);
    Http::preventStrayRequests();
    Cache::flush();
    Cache::purge('impressions');
    AccountStore::clear();
});

function impressionFeed(int $count = 2): array
{
    $posts = [[
        'id' => 'q1', 'is_update' => true, 'answer' => 'A visible post.',
        'to' => ['name' => 'Ada', 'username' => 'ada'],
    ], [
        'id' => 'q2', 'is_update' => true, 'answer' => 'A prefetched post.',
        'to' => ['name' => 'Grace', 'username' => 'grace'],
    ]];

    for ($index = 3; $index <= $count; $index++) {
        $posts[] = ['id' => 'q'.$index, 'is_update' => true, 'answer' => 'Post '.$index, 'to' => ['name' => 'Ada', 'username' => 'ada']];
    }

    return ['data' => $posts];
}

it('does not count fetched posts and sends a guest impression only when native visibility fires', function (): void {
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::response('', 204),
    ]);
    $screen = Native::test(Home::class)->assertElement('impression', fn (array $node): bool => ($node['props']['identity'] ?? '') === 'q1' && ($node['props']['dwell_ms'] ?? 0) === 500);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));

    $screen->call('recordPostView', 'q1')->call('recordPostView', 'q1');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));
    $screen->call('flushPostViews');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pinkary.test/api/v1/questions/views'
        && $request['question_ids'] === ['q1'] && $request->method() === 'POST' && Str::isUuid($request['viewer_id']) && ! $request->hasHeader('Authorization'));
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => in_array('q2', $request['question_ids'] ?? [], true));
});

it('uses the signed in bearer token and lets failed impressions retry without breaking the feed', function (): void {
    AsyncTask::fake();
    $this->freezeTime();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::sequence()->pushStatus(503)->pushStatus(204),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => []]),
    ]);
    $screen = Native::test(Home::class)->call('recordPostView', 'q1')->call('flushPostViews')->call('flushPostViews');
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(1);
    $this->travel(5)->seconds();
    $screen->call('flushPostViews')->assertSee('A visible post.')->assertSet('errorMessage', '');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/questions/views') && $request['question_ids'] === ['q1'] && $request->hasHeader('Authorization', 'Bearer test-token'));
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(2);
});

it('uses a stable guest identity and does not expose a token in cache keys', function (): void {
    $this->freezeTime();
    $viewer = PostViews::viewerId();
    expect(Str::isUuid($viewer))->toBeTrue()->and(PostViews::viewerId())->toBe($viewer);
    PostViews::enqueue('q1', 'secret-token', $viewer);
    $batch = PostViews::takeBatch('secret-token', $viewer, true);
    expect($batch['key'])->not->toContain('secret-token');
    PostViews::finishBatch($batch, true);
    PostViews::enqueue('q1', 'secret-token', $viewer);
    expect(PostViews::takeBatch('secret-token', $viewer, true))->toBeNull();
    $this->travel(121)->minutes();
    PostViews::enqueue('q1', 'secret-token', $viewer);
    expect(PostViews::takeBatch('secret-token', $viewer, true)['ids'])->toBe(['q1']);
});

it('rejects callbacks for posts not loaded on the screen', function (): void {
    AsyncTask::fake();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed())]);
    Native::test(Home::class)->call('recordPostView', 'not-loaded');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));
});

it('sends ten visible posts in one request and flushes the remaining posts on the timer', function (): void {
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed(12)),
        'https://pinkary.test/api/v1/questions/views' => Http::response('', 204),
    ]);
    $screen = Native::test(Home::class);

    foreach (range(1, 9) as $index) {
        $screen->call('recordPostView', 'q'.$index);
    }
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));
    $screen->call('recordPostView', 'q10')->call('recordPostView', 'q11')->call('recordPostView', 'q12');
    Http::assertSent(fn (Request $request): bool => ($request['question_ids'] ?? []) === ['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10']);
    $screen->call('flushPostViews');
    Http::assertSent(fn (Request $request): bool => ($request['question_ids'] ?? []) === ['q11', 'q12']);
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(2);
});

it('flushes a partial batch when a screen unmounts', function (): void {
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::response('', 204),
    ]);

    Native::test(Home::class)->call('recordPostView', 'q1')->call('unmount');

    Http::assertSent(fn (Request $request): bool => ($request['question_ids'] ?? []) === ['q1']);
});

it('keeps queued impressions when another screen becomes active', function (): void {
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::response('', 204),
    ]);
    Native::test(Home::class)->call('recordPostView', 'q1');

    Native::test(Home::class)->call('recordPostView', 'q2')->call('flushPostViews');

    Http::assertSent(fn (Request $request): bool => ($request['question_ids'] ?? []) === ['q1', 'q2']);
});

it('isolates pending batches between accounts and guests', function (): void {
    $viewer = PostViews::viewerId();
    PostViews::enqueue('guest-post', null, $viewer);
    PostViews::enqueue('ada-post', 'ada-token', $viewer);
    PostViews::enqueue('grace-post', 'grace-token', $viewer);

    $batch = PostViews::takeBatch('ada-token', $viewer, true);
    PostViews::finishBatch($batch, true);

    expect($batch['ids'])->toBe(['ada-post']);
    expect(PostViews::takeBatch('grace-token', $viewer, true)['ids'])->toBe(['grace-post']);
    expect(PostViews::takeBatch(null, $viewer, true)['ids'])->toBe(['guest-post']);
});

it('keeps new arrivals while a batch is in flight and recovers an expired delivery lease', function (): void {
    $this->freezeTime();
    $viewer = PostViews::viewerId();
    PostViews::enqueue('q1', null, $viewer);
    $original = PostViews::takeBatch(null, $viewer, true);
    PostViews::enqueue('q2', null, $viewer);
    expect(PostViews::takeBatch(null, $viewer, true))->toBeNull();

    $this->travel(31)->seconds();
    $replacement = PostViews::takeBatch(null, $viewer, true);
    PostViews::finishBatch($original, true);
    expect(PostViews::takeBatch(null, $viewer, true))->toBeNull();
    PostViews::enqueue('q3', null, $viewer);
    PostViews::finishBatch($replacement, true);

    expect($replacement['ids'])->toBe(['q1', 'q2']);
    expect(PostViews::takeBatch(null, $viewer, true)['ids'])->toBe(['q3']);
});

it('retains a batch after a connection failure and retries without a second visibility event', function (): void {
    $this->freezeTime();
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::failedConnection(),
    ]);
    $screen = Native::test(Home::class)->call('recordPostView', 'q1')->call('flushPostViews')->assertSet('errorMessage', '');
    Http::fake(['https://pinkary.test/api/v1/questions/views' => Http::response('', 204)]);

    $this->travel(5)->seconds();
    $screen->call('flushPostViews');

    Http::assertSent(fn (Request $request): bool => ($request['question_ids'] ?? []) === ['q1']);
});

it('honors the APIs retry after interval for a rate limited batch', function (): void {
    $this->freezeTime();
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/views' => Http::sequence()->push('', 429, ['Retry-After' => '45'])->pushStatus(204),
    ]);
    $screen = Native::test(Home::class)->call('recordPostView', 'q1')->call('flushPostViews');
    $this->travel(44)->seconds();
    $screen->call('flushPostViews');
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(1);

    $this->travel(1)->seconds();
    $screen->call('flushPostViews');

    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(2);
});

it('preserves queued impressions and delivered deduplication when startup clears the default cache', function (): void {
    $viewer = PostViews::viewerId();
    PostViews::enqueue('q1', null, $viewer);
    PostViews::enqueue('q2', null, $viewer);
    $batch = PostViews::takeBatch(null, $viewer, true);
    PostViews::finishBatch($batch, true);
    PostViews::enqueue('q3', null, $viewer);

    $this->artisan('cache:clear')->assertSuccessful();
    PostViews::enqueue('q1', null, $viewer);

    expect(PostViews::takeBatch(null, $viewer, true)['ids'])->toBe(['q3']);
});

it('recovers pending batches after reopening the persistent file store', function (): void {
    $this->freezeTime();
    $directory = storage_path('framework/testing/impressions-'.Str::uuid());
    config(['cache.stores.impressions' => ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory]]);
    Cache::purge('impressions');
    $viewer = PostViews::viewerId();

    try {
        PostViews::enqueue('q1', null, $viewer);
        $interrupted = PostViews::takeBatch(null, $viewer, true);
        Cache::purge('impressions');
        $this->artisan('cache:clear')->assertSuccessful();
        $this->travel(31)->seconds();

        $recovered = PostViews::takeBatch(null, $viewer, true);
        expect($recovered['ids'])->toBe(['q1']);
        expect($recovered['lease'])->not->toBe($interrupted['lease']);
        PostViews::finishBatch($recovered, true);
        Cache::purge('impressions');
        PostViews::enqueue('q1', null, $viewer);

        expect(PostViews::takeBatch(null, $viewer, true))->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});
