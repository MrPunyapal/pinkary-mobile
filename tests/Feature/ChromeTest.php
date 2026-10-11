<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\NativeComponents\Search;
use App\NativeLayout\MobileLayout;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    AccountStore::clear();
    CurrentUser::clear();
});

afterEach(function (): void {
    AccountStore::clear();
    CurrentUser::clear();
});

/**
 * @return list<string>
 */
function tabBarLabels(MobileLayout $layout, \Native\Mobile\Edge\NativeComponent $screen): array
{
    $labels = [];

    foreach ($layout->tabBar($screen)->tabElements() as $tab) {
        $props = new ReflectionProperty($tab, 'props');

        $labels[] = (string) ($props->getValue($tab)['label'] ?? '');
    }

    return $labels;
}

it('shows guests only working tabs', function (): void {
    $labels = tabBarLabels(new MobileLayout, new Home);

    expect($labels)->toBe(['Feed', 'Log in', 'Register', 'Menu'])
        ->and($labels)->not->toContain('Profile')
        ->and($labels)->not->toContain('Bookmarks')
        ->and($labels)->not->toContain('Notifications');
});

it('keeps the signed-in tab bar unchanged', function (): void {
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    expect(tabBarLabels(new MobileLayout, new Home))
        ->toBe(['Feed', 'Profile', 'Bookmarks', 'Notifications', 'Menu']);
});

it('tints idle tabs like the web', function (): void {
    $bar = (new MobileLayout)->tabBar(new Home);

    expect($bar->toRootProps())
        ->toHaveKey('textColor', '#64748B')
        ->toHaveKey('activeColor', '#EC4899');
});

it('hides the compose fab from guests', function (): void {
    expect((new MobileLayout)->floatingOverlay(new Home))->toBeNull();

    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    expect((new MobileLayout)->floatingOverlay(new Home))->not->toBeNull();
});

it('hides the compose fab while the app menu is open', function (): void {
    expect(file_get_contents(resource_path('views/native/compose-fab.blade.php')))
        ->toContain('appMenuOpen')
        ->toContain('opacity-0')
        ->toContain('a11y-label="Post"');

    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    $closed = new Home;
    $open = new Home;
    $open->appMenuOpen = true;

    $layout = new MobileLayout;

    expect($layout->floatingOverlay($closed))->not->toBeNull()
        ->and($layout->floatingOverlay($open))->not->toBeNull();
});

it('wires the menu tab into the sheet on every screen that shows one', function (): void {
    foreach (['compose', 'follow-list'] as $screen) {
        expect(file_get_contents(resource_path("views/native/{$screen}.blade.php")))
            ->toContain("@include('native.app-menu-sheet')");
    }
});

it('orders the app menu like the web and adds about', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->assertSee('About')
        ->assertSee('Source code')
        ->assertSee('Settings')
        ->assertSee('Accounts')
        ->assertSee('Add an existing account')
        ->assertSee('Log Out');
});

it('labels the log out row by account count', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->assertSee('Log Out')
        ->assertDontSee('Log out of all accounts');

    AccountStore::add('grace', 'token-b', ['name' => 'Grace Hopper', 'username' => 'grace']);
    CurrentUser::store(['name' => 'Grace Hopper', 'username' => 'grace', 'avatar' => null]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->assertSee('Log out of @grace')
        ->assertSee('Log out of all accounts');
});

it('routes destructive app menu actions through a confirm step', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    $test = Native::test(Home::class)->call('openAppMenu');

    $test->call('askLogout')
        ->assertSee('Log out of account?')
        ->assertSee('Are you sure you want to log out of @ada?');

    expect(AccountStore::count())->toBe(1);

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/logout'));

    $test->call('confirm');

    expect(AccountStore::count())->toBe(0);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/logout'));
});

it('cancels a destructive confirm without touching the account', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->call('askLogout')
        ->assertSee('Log out of account?')
        ->call('cancelConfirm')
        ->assertDontSee('Log out of account?')
        ->assertSet('appMenuOpen', false);

    expect(AccountStore::count())->toBe(1);

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/logout'));
});

it('treats a stray confirm as a no-op', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    Native::test(Home::class)->call('confirm');

    expect(AccountStore::count())->toBe(1);

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/logout'));
});

it('confirms removing a second account only on the second tap', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    $ada = AccountStore::add('ada', 'token-a', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace Hopper', 'username' => 'grace']);
    CurrentUser::store(['name' => 'Grace Hopper', 'username' => 'grace', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    $test = Native::test(Home::class)->call('openAppMenu');

    $test->call('askRemoveAccount', $ada)
        ->assertSee('Log out of account?')
        ->assertSee('Are you sure you want to log out of @ada?');

    expect(AccountStore::count())->toBe(2);

    $test->call('confirm');

    expect(AccountStore::count())->toBe(1);
});

it('opens the about page in the in-app browser', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test/']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->call('openAbout')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? null) === 'https://pinkary.test/about');
});

it('renders the feed tabs as pills with only a subtle section separator', function (): void {
    $source = file_get_contents(resource_path('views/components/feed-tabs.blade.php'));

    expect($source)
        ->toContain('rounded-md')
        ->toContain('bg-theme-primary')
        ->toContain('<native:rect class="bg-theme-outline-variant h-px w-full" />')
        ->not->toContain('border-b')
        ->not->toContain('flex-1 items-center');
});

it('uses the web copy on the search screen', function (): void {
    $source = file_get_contents(resource_path('views/native/search.blade.php'));

    expect($source)
        ->toContain('No users found.')
        ->toContain('Try another search or clear the query to return to the discovery list.')
        ->toContain('Discover new profiles to follow.');
});

it('titles the search screen in content, and only before a query', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [], 'hashtags' => []]]),
    ]);

    Native::test(Search::class)
        ->assertSee('Search')
        ->set('query', 'ada')
        ->assertDontSee('Discover new profiles to follow.');

    expect(file_get_contents(resource_path('views/native/search.blade.php')))
        ->toContain('@if ($query === \'\')')
        ->not->toContain('a11y-label="Go back"');
});

it('keeps the delete confirmation copy with the web', function (): void {
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('Delete this post?')
        ->toContain('Are you sure you want to delete this post?');
});

it('titles the likers sheet like the web', function (): void {
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('Liked by')
        ->toContain('No one has liked this post yet');
});

it('labels the edit row as the web menu item does', function (): void {
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('>Edit</native:text>')
        ->toContain('Answer question')
        ->toContain('Edit answer')
        ->toContain('a11y-label="Edit answer"');
});

it('drops the duplicated share rows from the post overflow sheet', function (): void {
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->not->toContain('shareMenuPost')
        ->not->toContain('shareToXMenu')
        ->not->toContain('>Copy link<');
});

it('keeps a clear control on the mobile search field', function (): void {
    $source = file_get_contents(resource_path('views/components/search-bar.blade.php'));

    expect($source)
        ->toContain('clearQuery')
        ->toContain('Clear search');
});

it('drops the in-page notification count pill', function (): void {
    $source = file_get_contents(resource_path('views/native/notifications.blade.php'));

    expect($source)
        ->toContain('Ignore all')
        ->not->toContain('20+');
});

it('has no dead chrome views', function (): void {
    foreach ([
        'views/native/sidebar.blade.php',
        'views/components/layouts/app.blade.php',
        'views/native/brand-lockup.blade.php',
    ] as $view) {
        expect(resource_path($view))->not->toBeFile();
    }
});
