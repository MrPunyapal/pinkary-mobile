<?php

use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Register;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Platform;
use Native\Mobile\Testing\Native;

afterEach(fn () => Platform::set(null));

it('returns from a guest auth tab to the same feed and keeps its state', function (string $label, string $path, string $component): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => []])]);

    $feed = Native::visit('/feed')->set('expanded', ['kept-post']);
    $auth = $feed->tap($label)->assertNavigatedTo($path)->follow()->assertScreen($component);
    $returned = $auth->pressBack()->assertWentBack()->goBack();

    $returned->assertScreen(Home::class)->assertSet('expanded', ['kept-post']);
    Http::assertSentCount(1);
})->with([
    'login' => ['Log in', '/login', Login::class],
    'register' => ['Register', '/register', Register::class],
]);

it('returns from registration to the existing login form', function (): void {
    $login = Native::test(Login::class)->input('email', 'ada@example.com');

    $login->call('register')->follow()->pressBack()->goBack()
        ->assertScreen(Login::class)->assertSet('email', 'ada@example.com');
});

it('returns a root auth screen to the feed instead of minimizing the app', function (string $component): void {
    $screen = Native::test($component, platform: Platform::ANDROID);
    $screen->instance()->setRouter(app(NativeRouter::class));

    $screen->pressBack()->assertReplacedWith('/feed');
})->with([Login::class, Register::class]);

it('returns from the two factor step to the password form first', function (): void {
    Native::test(Login::class)->set('needsTwoFactor', true)->set('twoFactorChallenge', 'short-lived-challenge')
        ->pressBack()->assertNoNavigation()->assertSet('needsTwoFactor', false)->assertSet('twoFactorChallenge', '');
});

it('keeps the feed behind a guest compose login prompt', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::preventStrayRequests();
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => []])]);

    Native::visit('/feed')->call('compose')->assertNavigatedTo('/login')->follow()->goBack()->assertScreen(Home::class);

    Http::assertSentCount(1);
});
