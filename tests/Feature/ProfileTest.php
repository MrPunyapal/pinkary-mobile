<?php

use App\NativeComponents\Profile;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('renders the authenticated profile', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response([
            'data' => [
                'name' => 'Pinkary User',
                'username' => 'pinkaryuser',
                'email' => 'pinkary@example.com',
                'bio' => 'Building in public.',
                'member_since' => ['human' => 'today'],
            ],
        ]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Pinkary User')
        ->assertSee('@pinkaryuser')
        ->assertSee('Building in public.');
});
