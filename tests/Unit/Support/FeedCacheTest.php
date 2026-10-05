<?php

declare(strict_types=1);

use App\Support\FeedCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

it('builds feed keys for signed-in users and guests', function (): void {
    expect(FeedCache::feedKey('ada', 'recent'))->toBe('pinkary:feed:ada:recent');
    expect(FeedCache::feedKey(null, 'following'))->toBe('pinkary:feed:guest:following');
    expect(FeedCache::feedKey('', 'trending'))->toBe('pinkary:feed:guest:trending');
});

it('builds bookmarks and notifications keys', function (): void {
    expect(FeedCache::bookmarksKey('ada'))->toBe('pinkary:bookmarks:ada');
    expect(FeedCache::notificationsKey('ada'))->toBe('pinkary:notifications:ada');
    expect(FeedCache::bookmarksKey(''))->toBe('pinkary:bookmarks:guest');
});

it('stores and forgets array payloads', function (): void {
    $payload = [
        'items' => [['id' => 'q1', 'answer' => 'Hello']],
        'page' => 2,
        'hasMore' => true,
        'unreadCount' => 3,
    ];

    FeedCache::put('pinkary:test', $payload);

    expect(FeedCache::get('pinkary:test'))->toBe($payload);

    FeedCache::forget('pinkary:test');

    expect(FeedCache::get('pinkary:test'))->toBeNull();
});

it('returns null for missing or malformed payloads', function (): void {
    expect(FeedCache::get('missing'))->toBeNull();

    Cache::put('pinkary:bad', ['nope' => true], 60);
    expect(FeedCache::get('pinkary:bad'))->toBeNull();

    Cache::put('pinkary:bad-items', ['items' => 'nope', 'page' => 1, 'hasMore' => false], 60);
    expect(FeedCache::get('pinkary:bad-items'))->toBeNull();
});
