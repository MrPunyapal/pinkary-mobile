<?php

use App\NativeComponents\Login;
use App\Support\AccountStore;
use Native\Mobile\Facades\System;
use Native\Mobile\Testing\Native;

it('renders the wordmark over http and tints it per theme', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    try {
        // Light: the white wordmark tinted to the web's slate-950 fill.
        System::rememberAppearance('light');
        Native::test(Login::class)
            ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? '') === 'https://pinkary.test/img/logo-text.png'
                && ($node['props']['tint_color'] ?? '') === '#0F172A');

        // Dark: the wordmark as shipped.
        System::rememberAppearance('dark');
        Native::test(Login::class)
            ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? '') === 'https://pinkary.test/img/logo-text.png'
                && ($node['props']['tint_color'] ?? '') === '#FFFFFF');
    } finally {
        System::rememberAppearance('light');
    }

    expect(json_encode(Native::test(Login::class)->tree()))->not->toContain(public_path('img'));
});

it('offers the web forgot-password page without a dead remember-me control', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    Native::test(Login::class)
        ->assertSee('Forgot password?')
        ->assertDontSee('Remember me')
        ->tap('Forgot password?')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? '') === 'https://pinkary.test/forgot-password');
});

it('keeps the plain login card for guests', function (): void {
    AccountStore::clear();
    session()->forget('pinkary_add_account');

    Native::test(Login::class)
        ->assertSee('Log in')
        ->assertSee('Continue to your Pinkary account.')
        ->assertDontSee('Currently signed in as');
});

it('switches to the add-account card when the app menu asked for one', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'token-a', ['name' => 'Ada Lovelace', 'username' => 'ada']);
    session(['pinkary_add_account' => true]);

    Native::test(Login::class)
        ->assertSee('Add Account')
        ->assertSee('Sign in with another Pinkary account to switch between them easily.')
        ->assertSee('Currently signed in as')
        ->assertSee('@ada')
        ->assertDontSee('Continue to your Pinkary account.');

    session()->forget('pinkary_add_account');
    AccountStore::clear();
});

it('stays on the plain card when the add flag has no stored account', function (): void {
    AccountStore::clear();
    session(['pinkary_add_account' => true]);

    Native::test(Login::class)
        ->assertSee('Log in')
        ->assertDontSee('Currently signed in as');

    session()->forget('pinkary_add_account');
});
