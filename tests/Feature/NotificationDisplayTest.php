<?php

declare(strict_types=1);

use App\NativeComponents\Notifications;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('does not render the internal update marker in notification snippets', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [[
                'id' => 'n1',
                'type' => 'QuestionAnswered',
                'read' => false,
                'created_at' => now()->subHour()->toIso8601String(),
                'actor' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'action' => 'reposted your post:',
                'snippet' => '__UPDATE__',
                'target' => ['kind' => 'question', 'id' => 'q1'],
            ]],
            'links' => ['next' => null],
            'meta' => ['unread_count' => 1],
        ]),
    ]);

    $test = Native::test(Notifications::class)
        ->assertSee('reposted your post:')
        ->assertDontSee('__UPDATE__');

    expect(data_get($test->get('items'), '0.snippet'))->toBeNull();
});
