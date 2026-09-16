<?php

use App\NativeComponents\Welcome;
use Native\Mobile\Testing\Native;

it('renders the Pinkary entry screen', function (): void {
    Native::test(Welcome::class)
        ->assertSee('Pinkary')
        ->assertSee('Log in')
        ->assertSee('Create an account');
});

it('navigates to login', function (): void {
    Native::test(Welcome::class)
        ->tap('Log in')
        ->assertNavigatedTo('/login');
});
