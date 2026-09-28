<?php

use App\NativeComponents\Bookmarks;
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
 * The page-heading title node's font size, for both heading branches.
 *
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
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    // Bookmarks renders the plain bar, notifications the slot bar (the
    // unread pill + Ignore all). Both must read text-2xl like the web.
    expect(headingFontSize(Native::test(Bookmarks::class)->tree(), 'Bookmarks'))->toBe(24);
    expect(headingFontSize(Native::test(Notifications::class)->tree(), 'Notifications'))->toBe(24);
});
