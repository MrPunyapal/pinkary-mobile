<?php

declare(strict_types=1);

use App\NativeComponents\Search;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\UnreadCount;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Browser;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    CurrentUser::clear();
    UnreadCount::clear();
});

afterEach(function (): void {
    CurrentUser::clear();
    AccountStore::clear();
});

/**
 * @return list<string>
 */
function browserVisits(Browser $browser): array
{
    return $browser->visits;
}

it('offers a guest the auth entries and no account list', function (): void {
    Native::test(Search::class)
        ->call('openAppMenu')
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('About')
        ->assertDontSee('Add an existing account')
        ->tap('Log in')
        ->assertNavigatedTo('/login');
});

it('navigates to register from the guest menu', function (): void {
    Native::test(Search::class)
        ->call('openAppMenu')
        ->tap('Register')
        ->assertNavigatedTo('/register');
});

it('lists the account menu for a signed in viewer', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);

    Native::test(Search::class)
        ->call('openAppMenu')
        ->assertSee('Settings')
        ->assertSee('Add an existing account')
        ->assertSee('Log Out')
        ->assertDontSee('Log out of all accounts')
        ->assertSee('Open your profile');
});

it('opens settings and your own profile from the menu', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);

    Native::test(Search::class)->call('openSettings')->assertNavigatedTo('/settings');

    Native::test(Search::class)->call('openOwnProfile')->assertNavigatedTo('/profile');
});

it('opens about and the source in the app browser', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);

    $browser = new class extends Browser
    {
        /** @var list<string> */
        public array $visits = [];

        public function inApp(string $url): bool
        {
            $this->visits[] = $url;

            return true;
        }
    };

    app()->instance(Browser::class, $browser);

    $screen = Native::test(Search::class)->call('openAppMenu')->tap('About');
    $screen->call('openSource');

    expect(browserVisits($browser))->toBe([
        'https://pinkary.test/about',
        'https://github.com/pinkary-project/pinkary.com',
    ]);
});

it('starts an add account flow without clearing the current one', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);

    Native::test(Search::class)
        ->call('openAppMenu')
        ->tap('Add an existing account')
        ->assertNavigatedTo('/login');

    expect(session('pinkary_add_account'))->toBeTrue()
        ->and(AccountStore::activeToken())->toBe('test-token');
});

it('confirms before logging out and can be cancelled', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    Http::fake(['https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204)]);

    Native::test(Search::class)
        ->call('openAppMenu')
        ->tap('Log Out')
        ->assertSet('confirmAction', 'logout')
        ->assertSee('Log out of account?')
        ->tap('Cancel')
        ->assertSet('confirmAction', null)
        ->assertSet('appMenuOpen', false);

    expect(AccountStore::activeToken())->toBe('test-token');
});

it('logs the account out and returns to the feed', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    Http::fake(['https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204)]);

    Native::test(Search::class)->call('openAppMenu')->tap('Log Out')->tap('Log Out')->assertReplacedWith('/login');

    expect(AccountStore::activeToken())->toBeNull()
        ->and(CurrentUser::get())->toBeNull();
});

it('logs every account out after one confirmation', function (): void {
    AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['username' => 'grace']);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    UnreadCount::store(3);
    Http::fake(['https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204)]);

    Native::test(Search::class)
        ->call('openAppMenu')
        ->assertSee('Log out of all accounts')
        ->tap('Log out of all accounts')
        ->assertSee('Log out of all accounts?')
        ->tap('Log Out')
        ->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(0)
        ->and(CurrentUser::get())->toBeNull()
        ->and(UnreadCount::get())->toBeNull();
});

it('switches to a stored account that still answers', function (): void {
    $ada = AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['username' => 'grace']);
    CurrentUser::store(['username' => 'grace', 'name' => 'Grace']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada', 'name' => 'Ada']]),
    ]);

    Native::test(Search::class)->call('switchAccount', $ada)->assertReplacedWith('/feed');

    expect(AccountStore::activeId())->toBe($ada)
        ->and(CurrentUser::get()['username'] ?? null)->toBe('ada');
});

it('keeps the current account when the switch cannot be verified', function (Closure $response): void {
    $ada = AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    $grace = AccountStore::add('grace', 'token-b', ['username' => 'grace']);
    AccountStore::activate($ada);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    Http::fake(['https://pinkary.test/api/v1/profile' => $response()]);

    Native::test(Search::class)->call('switchAccount', $grace);

    expect(AccountStore::activeToken())->toBe('token-a');
})->with([
    'connection failure' => [fn () => Http::failedConnection()],
    'unauthorized' => [fn () => Http::response(null, 401)],
    'server error' => [fn () => Http::response(['message' => 'Server error.'], 500)],
    'no username in the payload' => [fn () => Http::response(['data' => ['name' => 'Grace']])],
]);

it('ignores a switch to the account already active or to nobody', function (): void {
    $ada = AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    $grace = AccountStore::add('grace', 'token-b', ['username' => 'grace']);
    CurrentUser::store(['username' => 'grace', 'name' => 'Grace']);

    Native::test(Search::class)->call('switchAccount', '')->call('switchAccount', $grace)->call('switchAccount', 'missing');

    expect(AccountStore::activeToken())->toBe('token-b')
        ->and(AccountStore::activeId())->not->toBe($ada);
});

it('removes an inactive account and keeps the active one signed in', function (): void {
    $ada = AccountStore::add('ada', 'token-a', ['username' => 'ada']);
    $grace = AccountStore::add('grace', 'token-b', ['username' => 'grace']);
    AccountStore::activate($ada);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada']);
    Http::fake(['https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204)]);

    Native::test(Search::class)->call('removeAccount', $grace);

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-a');

    Native::test(Search::class)->call('removeAccount', '');
});
