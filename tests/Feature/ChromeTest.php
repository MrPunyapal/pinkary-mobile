<?php

use App\NativeComponents\Home;
use App\NativeComponents\Search;
use App\NativeLayout\MobileLayout;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/*
|--------------------------------------------------------------------------
| Chrome parity with the web
|--------------------------------------------------------------------------
|
| Every case here is pinned to a line in the pinkary.com web app, which
| is the visual and copy source of truth for this client. The comment on
| each test names the file it came from so a future drift is traceable.
|
*/

beforeEach(function (): void {
    AccountStore::clear();
    CurrentUser::clear();
});

afterEach(function (): void {
    AccountStore::clear();
    CurrentUser::clear();
});

/**
 * Tab labels in the order the layout builds them.
 *
 * @return list<string>
 */
function tabBarLabels(MobileLayout $layout, $screen): array
{
    $labels = [];

    foreach ($layout->tabBar($screen)->tabElements() as $tab) {
        $props = new ReflectionProperty($tab, 'props');

        $labels[] = (string) ($props->getValue($tab)['label'] ?? '');
    }

    return $labels;
}

it('shows guests only working tabs', function (): void {
    // Web: ../pinkary/resources/views/layouts/navigation.blade.php:292-319
    // branches the bottom bar for guests — Feed / Log in / Register, and
    // the Menu dropdown outside the branch. Profile, Bookmarks and
    // Notifications all require a token, so they are not offered.
    $labels = tabBarLabels(new MobileLayout, new Home);

    expect($labels)->toBe(['Feed', 'Log in', 'Register', 'Menu'])
        ->and($labels)->not->toContain('Profile')
        ->and($labels)->not->toContain('Bookmarks')
        ->and($labels)->not->toContain('Notifications');
});

it('keeps the signed-in tab bar unchanged', function (): void {
    // Web: ../pinkary/resources/views/layouts/navigation.blade.php:254-290
    // TokenStorage::store() always writes the account, so a signed-in
    // layout is seeded through AccountStore — the snapshot alone is a
    // fixture that production never produces on its own.
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    expect(tabBarLabels(new MobileLayout, new Home))
        ->toBe(['Feed', 'Profile', 'Bookmarks', 'Notifications', 'Menu']);
});

it('tints idle tabs like the web', function (): void {
    // Web idle color: ../pinkary/resources/views/layouts/navigation.blade.php:8
    // (#64748B light / #94A3B8 dark). iOS 26 keeps the system tint for
    // unselected items, so this is honored on Android only.
    $bar = (new MobileLayout)->tabBar(new Home);

    expect($bar->toRootProps())
        ->toHaveKey('textColor', '#64748B')
        ->toHaveKey('activeColor', '#EC4899');
});

it('hides the compose fab from guests', function (): void {
    // Web: ../pinkary/resources/views/layouts/app.blade.php:129 wraps the
    // FAB (and its modal) in @auth.
    expect((new MobileLayout)->floatingOverlay(new Home))->toBeNull();

    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);

    expect((new MobileLayout)->floatingOverlay(new Home))->not->toBeNull();
});

it('hides the compose fab while the app menu is open', function (): void {
    // Web: ../pinkary/resources/views/layouts/app.blade.php:131-133 binds
    // x-show to the dropdown's open state.
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

    // One FAB, never two: the overlay either exists or it does not.
    expect($layout->floatingOverlay($closed))->not->toBeNull()
        ->and($layout->floatingOverlay($open))->not->toBeNull();
});

it('wires the menu tab into the sheet on every screen that shows one', function (): void {
    // Screens with a bottom tab bar carry a Menu tab, so each must also
    // include the sheet — otherwise that tab is a dead control.
    foreach (['compose', 'follow-list'] as $screen) {
        expect(file_get_contents(resource_path("views/native/{$screen}.blade.php")))
            ->toContain("@include('native.app-menu-sheet')");
    }
});

it('orders the app menu like the web and adds about', function (): void {
    // Web order: ../pinkary/resources/views/layouts/navigation.blade.php:331-346
    // — About, Source code, Settings, then the account switcher.
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
    // Web: ../pinkary/resources/views/layouts/navigation.blade.php:357 and
    // :383 — "Log out of @username" with several accounts, a bare
    // "Log Out" with one.
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
    // Web: ../pinkary/resources/views/layouts/app.blade.php:261-293 guards
    // every log-out with a modal — the first tap only arms the question.
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    $test = Native::test(Home::class)->call('openAppMenu');

    // The first tap only asks.
    $test->call('askLogout')
        ->assertSee('Log out of account?')
        ->assertSee('Are you sure you want to log out of @ada?');

    expect(AccountStore::count())->toBe(1);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/v1/auth/logout'));

    // The second tap acts.
    $test->call('confirm');

    expect(AccountStore::count())->toBe(0);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/auth/logout'));
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

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/v1/auth/logout'));
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

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/v1/auth/logout'));
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
    // A trailing slash in the configured base must not double up.
    config(['services.pinkary.url' => 'https://pinkary.test/']);
    AccountStore::add('ada', 'test-token', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    CurrentUser::store(['name' => 'Ada Lovelace', 'username' => 'ada', 'avatar' => null]);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    Native::test(Home::class)
        ->call('openAppMenu')
        ->call('openAbout')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params) => ($params['url'] ?? null) === 'https://pinkary.test/about');
});

it('renders the feed tabs as pills without an underline', function (): void {
    // Web: ../pinkary/resources/views/components/home-menu.blade.php:15 —
    // rounded-md pills, a pink fill on the active one, no underline.
    $source = file_get_contents(resource_path('views/components/feed-tabs.blade.php'));

    expect($source)
        ->toContain('rounded-md')
        ->toContain('bg-theme-primary')
        ->not->toContain('native:rect')
        ->not->toContain('flex-1 items-center');
});

it('uses the web copy on the search screen', function (): void {
    // Web: ../pinkary/resources/views/livewire/home/users.blade.php:8-10
    // and :29-32.
    $source = file_get_contents(resource_path('views/native/search.blade.php'));

    expect($source)
        ->toContain('No users found.')
        ->toContain('Try another search or clear the query to return to the discovery list.')
        ->toContain('Discover new profiles to follow.');
});

it('titles the search screen in content, and only before a query', function (): void {
    // Search.php's navTitle never renders: the screen's header is in
    // content, so the title needs a band. The web's page has no back
    // arrow, so neither does this one.
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
    // Web: ../pinkary/resources/views/livewire/questions/show.blade.php:445-447
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('Delete this post?')
        ->toContain('Are you sure you want to delete this post?');
});

it('titles the likers sheet like the web', function (): void {
    // Web: ../pinkary/resources/views/livewire/likes/index.blade.php:7-13
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('Liked by')
        ->toContain('No one has liked this post yet');
});

it('labels the edit row as the web menu item does', function (): void {
    // Web menu item: ../pinkary/resources/views/livewire/questions/show.blade.php:394
    // reads "Edit"; its modal heading is the one that says "Edit Answer".
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->toContain('>Edit</native:text>')
        ->toContain('>Edit answer</native:text>')
        ->toContain('a11y-label="Edit answer"');
});

it('drops the duplicated share rows from the post overflow sheet', function (): void {
    // A share control already lives in the post action row
    // (resources/views/components/post.blade.php:220), and the platform
    // has no clipboard — so no Share and no Copy link row here.
    $source = file_get_contents(resource_path('views/native/post-menu-sheet.blade.php'));

    expect($source)
        ->not->toContain('shareMenuPost')
        ->not->toContain('shareToXMenu')
        // The rationale comment names what was deliberately left out.
        ->not->toContain('>Copy link<');
});

it('keeps a clear control on the mobile search field', function (): void {
    // The web's desktop input leans on a browser clear affordance; a bare
    // mobile field needs the explicit tap target.
    $source = file_get_contents(resource_path('views/components/search-bar.blade.php'));

    expect($source)
        ->toContain('clearQuery')
        ->toContain('Clear search');
});

it('drops the in-page notification count pill', function (): void {
    // The web keeps the count on the nav bell only; the mobile tab bar
    // bell badges it (MobileLayout::tabBar), so a second pill in the
    // heading would be a third copy of the same number.
    $source = file_get_contents(resource_path('views/native/notifications.blade.php'));

    expect($source)
        ->toContain('Ignore all')
        ->not->toContain('20+');
});

it('has no dead chrome views', function (): void {
    // The old sidebar duplicated the app menu on every point, and neither
    // layouts/app nor brand-lockup had a single reference.
    foreach ([
        'views/native/sidebar.blade.php',
        'views/components/layouts/app.blade.php',
        'views/native/brand-lockup.blade.php',
    ] as $view) {
        expect(resource_path($view))->not->toBeFile();
    }
});
