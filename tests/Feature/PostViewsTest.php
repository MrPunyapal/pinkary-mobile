<?php

use App\NativeComponents\Home;
use App\Support\AccountStore;
use App\Support\PostViews;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
    Cache::flush();
    AccountStore::clear();
});

function impressionFeed(): array
{
    return ['data' => [[
        'id' => 'q1', 'is_update' => true, 'answer' => 'A visible post.',
        'to' => ['name' => 'Ada', 'username' => 'ada'],
    ], [
        'id' => 'q2', 'is_update' => true, 'answer' => 'A prefetched post.',
        'to' => ['name' => 'Grace', 'username' => 'grace'],
    ]]];
}

it('does not count fetched posts and sends a guest impression only when native visibility fires', function (): void {
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/q1/views' => Http::response('', 204),
    ]);
    $screen = Native::test(Home::class)->assertElement('impression', fn (array $node): bool => ($node['props']['identity'] ?? '') === 'q1' && ($node['props']['dwell_ms'] ?? 0) === 500);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));

    $screen->call('recordPostView', 'q1')->call('recordPostView', 'q1');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pinkary.test/api/v1/questions/q1/views'
        && $request->method() === 'POST' && Str::isUuid($request['viewer_id']) && ! $request->hasHeader('Authorization'));
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/q2/views'));
});

it('uses the signed in bearer token and lets failed impressions retry without breaking the feed', function (): void {
    AsyncTask::fake();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed()),
        'https://pinkary.test/api/v1/questions/q1/views' => Http::sequence()->pushStatus(503)->pushStatus(204),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => []]),
    ]);
    Native::test(Home::class)->call('recordPostView', 'q1')->call('recordPostView', 'q1')->assertSee('A visible post.')->assertSet('errorMessage', '');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/q1/views') && $request->hasHeader('Authorization', 'Bearer test-token'));
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/views')))->toHaveCount(2);
});

it('uses a stable guest identity and does not expose a token in cache keys', function (): void {
    $viewer = PostViews::viewerId();
    expect(Str::isUuid($viewer))->toBeTrue()->and(PostViews::viewerId())->toBe($viewer);
    $key = PostViews::claim('q1', 'secret-token', $viewer);
    expect($key)->not->toContain('secret-token');
    PostViews::finish($key, true);
    expect(PostViews::claim('q1', 'secret-token', $viewer))->toBeNull();
    $this->travel(121)->minutes();
    expect(PostViews::claim('q1', 'secret-token', $viewer))->not->toBeNull();
});

it('rejects callbacks for posts not loaded on the screen', function (): void {
    AsyncTask::fake();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(impressionFeed())]);
    Native::test(Home::class)->call('recordPostView', 'not-loaded');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/views'));
});
