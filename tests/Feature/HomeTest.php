<?php

use App\NativeComponents\Home;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('renders the Pinkary feed from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed' => Http::response([
            'data' => [[
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'What are you building?',
                'answer' => 'A thoughtful mobile experience.',
                'metrics' => ['likes' => 4, 'comments' => 2],
            ]],
        ]),
    ]);

    Native::test(Home::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('What are you building?')
        ->assertSee('A thoughtful mobile experience.');
});
