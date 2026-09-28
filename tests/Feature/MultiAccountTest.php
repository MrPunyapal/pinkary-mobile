<?php

use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    FakeBridge::enable();
    AccountStore::clear();
    session()->forget(['pinkary_accounts', 'pinkary_active_account', 'pinkary_add_account']);
});

afterEach(function (): void {
    AccountStore::clear();
    session()->forget(['pinkary_accounts', 'pinkary_active_account', 'pinkary_add_account']);
    FakeBridge::disable();
});

it('stores multiple accounts newest-first without exposing tokens', function (): void {
    AccountStore::add('ada', 'token-a', ['name' => 'Ada Lovelace']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace Hopper']);

    $list = AccountStore::list();

    expect(AccountStore::count())->toBe(2)
        ->and(array_column($list, 'username'))->toBe(['grace', 'ada'])
        ->and($list[0])->not->toHaveKey('token')
        ->and(AccountStore::activeToken())->toBe('token-b')
        ->and(app(TokenStorage::class)->get())->toBe('token-b');
});

it('replaces the token on re-login instead of duplicating the account', function (): void {
    AccountStore::add('ada', 'token-old', ['name' => 'Ada']);
    AccountStore::add('Ada', 'token-new', ['name' => 'Ada Lovelace']);

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-new');
});

it('falls through to the next account when the active one is removed', function (): void {
    $ada = AccountStore::add('ada', 'token-a', ['name' => 'Ada']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace']);

    AccountStore::remove(AccountStore::activeId() ?? '');

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeId())->toBe($ada)
        ->and(AccountStore::activeToken())->toBe('token-a');
});

it('adds a second account through login and revokes nothing new', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token-a', ['name' => 'Ada', 'username' => 'ada']);
    session(['pinkary_add_account' => true]);
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'token' => 'token-b',
            'data' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        ]),
    ]);

    Native::test(Login::class)
        ->assertSee('Add Account')
        ->assertSee('Currently signed in as')
        ->assertSee('@ada')
        ->input('email', 'grace@example.com')
        ->input('password', 'password')
        ->tap('Add Account')
        ->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(2)
        ->and(AccountStore::activeToken())->toBe('token-b')
        ->and(CurrentUser::get()['username'] ?? null)->toBe('grace')
        ->and(session('pinkary_add_account'))->toBeNull();
});

it('revokes the previous token when the same user logs in again', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('grace', 'token-old', ['name' => 'Grace', 'username' => 'grace']);
    Http::fake([
        'https://pinkary.test/api/v1/auth/login' => Http::response([
            'token' => 'token-new',
            'data' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        ]),
        'https://pinkary.test/api/v1/auth/logout' => Http::response(null, 204),
    ]);

    Native::test(Login::class)
        ->input('email', 'grace@example.com')
        ->input('password', 'password')
        ->tap('Log in')
        ->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-new');

    Http::assertSent(
        fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/logout')
            && $request->header('Authorization') === ['Bearer token-old']
    );
});

it('switches accounts after proving the token', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    CurrentUser::store(['name' => 'Grace', 'username' => 'grace', 'avatar' => 'https://example.com/g.png']);
    $ada = AccountStore::add('ada', 'token-a', ['name' => 'Ada', 'username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace', 'username' => 'grace']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [], 'links' => ['next' => null]]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['meta' => ['unread_count' => 0]]),
        'https://pinkary.test/api/v1/profile' => Http::response([
            'data' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        ]),
    ]);

    Native::test(Home::class)
        ->call('switchAccount', $ada)
        ->assertReplacedWith('/feed');

    expect(AccountStore::activeId())->toBe($ada)
        ->and(CurrentUser::get()['username'] ?? null)->toBe('ada');
});

it('drops a dead token instead of switching to it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    CurrentUser::store(['name' => 'Grace', 'username' => 'grace', 'avatar' => 'https://example.com/g.png']);
    $ada = AccountStore::add('ada', 'token-a', ['name' => 'Ada', 'username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace', 'username' => 'grace']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [], 'links' => ['next' => null]]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['meta' => ['unread_count' => 0]]),
        'https://pinkary.test/api/v1/profile' => Http::response(null, 401),
    ]);

    Native::test(Home::class)->call('switchAccount', $ada);

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-b');
});

it('falls through to the next account on a 401 instead of login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    CurrentUser::store(['name' => 'Grace', 'username' => 'grace', 'avatar' => 'https://example.com/g.png']);
    AccountStore::add('ada', 'token-a', ['name' => 'Ada', 'username' => 'ada']);
    AccountStore::add('grace', 'token-b', ['name' => 'Grace', 'username' => 'grace']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(null, 401),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['meta' => ['unread_count' => 0]]),
    ]);

    Native::test(Home::class)->assertReplacedWith('/feed');

    expect(AccountStore::count())->toBe(1)
        ->and(AccountStore::activeToken())->toBe('token-a');
});
