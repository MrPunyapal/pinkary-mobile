<?php

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Home;
use App\NativeComponents\Notifications;
use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function countTreeType(array $node, string $type): int
{
    $count = ($node['type'] ?? null) === $type ? 1 : 0;

    foreach ($node['children'] ?? [] as $child) {
        $count += countTreeType($child, $type);
    }

    return $count;
}

it('uses skeleton feed and refresh bar in the home view source', function (): void {
    $source = file_get_contents(resource_path('views/native/home.blade.php'));

    expect($source)
        ->toContain('x-skeleton-feed')
        ->toContain('x-refresh-bar')
        ->toContain('$refreshing ?? false')
        ->not->toContain('Loading the latest conversations…')
        ->not->toContain('native:activity-indicator');
});

it('shows skeletons instead of a spinner on a cold home feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'A thoughtful mobile experience.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('A thoughtful mobile experience.')
        ->set('loading', true)
        ->assertSee('Pinkary')
        ->assertSee('Recent')
        ->assertDontSee('A thoughtful mobile experience.')
        ->assertDontSee('Loading the latest conversations…');

    expect(countTreeType($test->tree(), 'activity_indicator'))->toBe(0);
});

it('keeps the home list visible after a loaded feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Cached feed post.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Cached feed post.')
        ->assertSee('Pinkary')
        ->assertSee('Recent')
        ->assertDontSee('Loading the latest conversations…');
});

it('shows skeleton rows while searching', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    $test = Native::test(Search::class)
        ->set('searching', true)
        ->assertSee('Search users or hashtags')
        ->assertDontSee('Search people by name');

    expect(countTreeType($test->tree(), 'activity_indicator'))->toBe(0);
});

it('shows bookmark and notification skeletons without loading copy', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Bookmarks::class)
        ->set('loading', true)
        ->assertSee('Bookmarks')
        ->assertDontSee('Loading your bookmarks…')
        ->assertDontSee('No bookmarks yet.');

    Native::test(Notifications::class)
        ->set('loading', true)
        ->assertSee('Notifications')
        ->assertDontSee('Loading notifications…')
        ->assertDontSee('No pending notifications.');
});

it('lazy-loads avatars and post images', function (): void {
    expect(file_get_contents(resource_path('views/components/avatar.blade.php')))
        ->toContain('x-lazy-image')
        ->not->toContain('<native:image')
        ->and(file_get_contents(resource_path('views/components/post.blade.php')))
        ->toContain('x-lazy-image')
        ->not->toContain('<native:image');

    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => [
                'name' => 'Ada Lovelace',
                'username' => 'ada',
                'avatar' => 'https://pinkary.test/ada.png',
            ],
            'content' => '__UPDATE__',
            'answer' => 'A post with an image.',
            'images' => ['https://pinkary.test/storage/a.png'],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('A post with an image.')
        ->tap('Open image fullscreen');

    expect(countTreeType($test->tree(), 'image'))->toBeGreaterThanOrEqual(2);
});

it('renders skeleton and refresh components', function (): void {
    expect(file_get_contents(resource_path('views/components/skeleton-feed.blade.php')))
        ->toContain('x-skeleton-post')
        ->and(file_get_contents(resource_path('views/components/refresh-bar.blade.php')))
        ->toContain('bg-theme-primary');

    expect(view('components.skeleton-feed')->render())->toBeString()
        ->and(view('components.skeleton-notifications')->render())->toBeString()
        ->and(view('components.skeleton-profile')->render())->toBeString()
        ->and(view('components.skeleton-user-row')->render())->toBeString()
        ->and(view('components.skeleton-settings')->render())->toBeString()
        ->and(view('components.refresh-bar')->render())->toBeString()
        ->and(view('components.lazy-image', [
            'src' => 'https://pinkary.test/avatar.png',
            'alt' => 'ada',
            'class' => 'w-10 h-10',
        ])->render())->toBeString();
});
