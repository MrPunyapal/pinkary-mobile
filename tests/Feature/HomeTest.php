<?php

use App\NativeComponents\Home;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('renders the Pinkary feed from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response([
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

it('shows the following feed when switching tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => []])
            ->push(['data' => [[
                'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'What did you ship today?',
                'answer' => 'A compiler for the mobile age.',
                'metrics' => ['likes' => 7, 'comments' => 3],
            ]]]),
    ]);

    Native::test(Home::class)
        ->tap('Following')
        ->assertSet('tab', 'following')
        ->assertSee('A compiler for the mobile age.');
});

it('shows the trending feed when switching tabs', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::sequence()
            ->push(['data' => []])
            ->push(['data' => [[
                'from' => ['name' => 'Alan Turing', 'username' => 'alan'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'Can machines think on mobile?',
                'answer' => 'The whole feed is talking about it.',
                'metrics' => ['likes' => 42, 'comments' => 9],
            ]]]),
    ]);

    Native::test(Home::class)
        ->tap('Trending')
        ->assertSet('tab', 'trending')
        ->assertSee('The whole feed is talking about it.');
});
