<?php

declare(strict_types=1);

use App\NativeComponents\ChannelFeed;
use App\Support\AccountStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Async\AsyncTaskFinished;
use Native\Mobile\Support\AsyncTaskTransport;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
});

/** @return array<string, mixed> */
function channelFeedPost(string $id, string $answer): array
{
    $author = ['name' => 'Ada Lovelace', 'username' => 'ada'];

    return [
        'id' => $id,
        'from' => $author,
        'to' => $author,
        'content' => '__UPDATE__',
        'answer' => $answer,
        'metrics' => ['comments' => 0, 'likes' => 0, 'reposts' => 0, 'bookmarks' => 0],
        'views' => 0,
    ];
}

it('opens a channel feed from a post chip and paginates channel posts', function (): void {
    AccountStore::clear();
    AsyncTask::fake();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            ...channelFeedPost('home-q1', 'Open the Laravel channel.'),
            'channel' => ['id' => 1, 'name' => 'Laravel', 'slug' => 'laravel'],
        ]]]),
        'https://pinkary.test/api/v1/channels/laravel/questions*' => Http::sequence()
            ->push(['data' => [channelFeedPost('q1', 'First channel post.')], 'links' => ['next' => '?page=2']])
            ->push(['data' => [channelFeedPost('q2', 'Second channel post.')], 'links' => ['next' => null]]),
    ]);

    $home = Native::visit('/')
        ->tap('Open #Laravel channel feed')
        ->assertNavigatedTo('/channel/laravel');

    $feed = $home->follow()
        ->assertScreen(ChannelFeed::class)
        ->assertSee('Laravel')
        ->assertSee('First channel post.')
        ->assertSet('hasMore', true)
        ->call('loadMore')
        ->assertSee('Second channel post.')
        ->assertSet('page', 2)
        ->assertSet('hasMore', false);

    expect($feed->get('posts'))->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/channels/laravel/questions')
        && $request['page'] === 1
        && $request['per_page'] === 20);
    Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/channels/laravel/questions')
        && $request['page'] === 2
        && $request['per_page'] === 20);
});

it('shows a retry action when the channel feed request fails', function (): void {
    AccountStore::clear();
    Http::fake([
        'https://pinkary.test/api/v1/channels/laravel/questions*' => Http::sequence()
            ->push(['message' => 'Unavailable'], 503)
            ->push(['data' => [], 'links' => ['next' => null]]),
    ]);

    Native::visit('/channel/laravel')
        ->assertSee('This channel feed could not be loaded. Please try again.')
        ->tap('Try again')
        ->assertSee('No posts in this channel yet.');

    Http::assertSentCount(2);
});

it('shows a loader while the next channel page is being fetched', function (): void {
    AccountStore::clear();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::fake([
        'https://pinkary.test/api/v1/channels/laravel/questions*' => Http::response([
            'data' => [channelFeedPost('q1', 'First channel post.')],
            'links' => ['next' => '?page=2'],
        ]),
    ]);

    $screen = Native::visit('/channel/laravel')
        ->call('loadMore')
        ->assertSet('loadingMore', true)
        ->assertElement('activity_indicator')
        ->assertSee('First channel post.')
        ->assertSet('page', 1);

    $id = $screen->bridge()->callsTo('AsyncTask.Dispatch')[0]['params']['id'];
    AsyncTaskTransport::forgetPayload($id);

    $screen->emitNative(AsyncTaskFinished::class, [
        'id' => $id,
        'result' => [
            'status' => 200,
            'posts' => [channelFeedPost('q2', 'Second channel post.')],
            'hasMore' => false,
        ],
    ])->assertSet('loadingMore', false)
        ->assertSet('page', 2)
        ->assertSet('hasMore', false)
        ->assertSee('Second channel post.')
        ->assertMissingElement('activity_indicator');
});
