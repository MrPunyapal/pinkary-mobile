<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\Support\AccountStore;
use App\Support\UnreadCount;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    UnreadCount::clear();
});

it('renders the feed without waiting for the optional badge request', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => []])]);

    Native::test(Home::class)->assertSee('Recent');

    Http::assertSentCount(1);
});

it('keeps the previous badge count when its refresh fails', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    UnreadCount::store(7);
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::failedConnection(),
    ]);

    Native::test(Home::class)->assertSee('Recent');

    expect(UnreadCount::get())->toBe(7);
});

it('ignores badge responses for an account that is no longer active', function (): void {
    AccountStore::add('ada', 'ada-token', ['username' => 'ada']);
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => function (): PromiseInterface {
            AccountStore::add('grace', 'grace-token', ['username' => 'grace']);
            UnreadCount::store(2);

            return Http::response(['meta' => ['unread_count' => 99]]);
        },
    ]);

    Native::test(Home::class);

    expect(UnreadCount::get())->toBe(2);
});
