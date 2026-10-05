<?php

declare(strict_types=1);

use App\NativeComponents\Bookmarks;
use App\NativeComponents\FollowList;
use App\NativeComponents\Notifications;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
});

/**
 * @param  array<string, mixed>  $node
 */
function headingFontSize(array $node, string $title): ?int
{
    if (($node['type'] ?? '') === 'text' && ($node['props']['text'] ?? '') === $title) {
        return (int) ($node['props']['font_size'] ?? 0);
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && ($size = headingFontSize($child, $title)) !== null) {
            return $size;
        }
    }

    return null;
}

it('keeps the same title scale with and without a trailing action', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 0],
        ]),
        'https://pinkary.test/api/v1/users/*/followers*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
        ]),
    ]);

    expect(headingFontSize(Native::test(Notifications::class)->tree(), 'Notifications'))->toBe(24);
    expect(headingFontSize(
        Native::test(FollowList::class, ['username' => 'ada'])->tree(),
        '@ada followers',
    ))->toBe(24);
});

it('scales short static titles to the web 32px, sparing bars with a trailing action', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    expect(headingFontSize(Native::test(Bookmarks::class)->tree(), 'Bookmarks'))->toBe(30);
    expect(headingFontSize(Native::test(Notifications::class)->tree(), 'Notifications'))->toBe(24);
});
