<?php

declare(strict_types=1);

use App\NativeComponents\Detail;
use App\NativeComponents\FollowList;
use App\NativeComponents\Home;
use App\NativeComponents\Profile;
use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function guestReadFakes(): array
{
    return [
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A public answer anyone can read.',
            'metrics' => ['likes' => 3, 'comments' => 2],
            'views' => 12,
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::sequence()
            ->push([
                'data' => [[
                    'id' => 'c1',
                    'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                    'to' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                    'content' => 'First public comment.',
                    'answer' => null,
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'links' => ['next' => 'https://pinkary.test/api/v1/questions/q1/comments?page=2'],
            ])
            ->push([
                'data' => [[
                    'id' => 'c2',
                    'from' => ['name' => 'Alan Turing', 'username' => 'alan'],
                    'to' => ['name' => 'Alan Turing', 'username' => 'alan'],
                    'content' => 'Second public comment.',
                    'answer' => null,
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ]],
                'links' => ['next' => null],
            ]),
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => [
            'id' => 2,
            'name' => 'Ada Lovelace',
            'username' => 'ada',
            'bio' => 'First programmer.',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'stats' => ['followers' => 42, 'following' => 7, 'posts' => 1, 'views' => 12],
            'followed_by_me' => false,
            'is_me' => false,
            'links' => [],
            'member_since' => ['human' => '2 years ago'],
        ]]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response([
            'data' => [[
                'id' => 'q2',
                'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
                'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'content' => 'What are you building?',
                'answer' => 'A public profile answer.',
                'metrics' => ['likes' => 0, 'comments' => 0],
                'views' => 0,
            ]],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => [
            'users' => [['id' => 2, 'name' => 'Ada Lovelace', 'username' => 'ada', 'verification' => ['profile' => true, 'company' => false]]],
            'hashtags' => [['id' => 1, 'name' => 'laravel', 'questions_count' => 12]],
        ]]),
        'https://pinkary.test/api/v1/users/ada/followers*' => Http::response([
            'data' => [[
                'id' => 3,
                'name' => 'Grace Hopper',
                'username' => 'grace',
                'verification' => ['profile' => false, 'company' => false],
            ]],
            'links' => ['next' => null],
        ]),
    ];
}

function asGuest(): void
{
    AccountStore::clear();
    Http::fake(guestReadFakes());
}

function assertSentWithoutAuthorization(string $urlFragment): void
{
    Http::assertSent(fn ($request) => str_contains($request->url(), $urlFragment));

    expect(Http::recorded(fn ($request) => $request->hasHeader('Authorization')))->toBeEmpty();
}

it('opens a post and its comments for guests without an Authorization header', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('What are you building?')
        ->assertSee('A public answer anyone can read.')
        ->assertSee('First public comment.')
        ->call('loadMoreComments')
        ->assertSee('Second public comment.');

    assertSentWithoutAuthorization('/api/v1/questions/q1/comments');
});

it('opens a post tapped in the guest recent feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake(array_merge(guestReadFakes(), [
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A public answer anyone can read.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]));

    Native::test(Home::class)
        ->press("openDetail('q1')")
        ->assertNavigatedTo('/question/q1');

    Native::visit('/question/q1')
        ->assertSee('A public answer anyone can read.');
});

it('sends a guest bookmark to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Detail::class, ['id' => 'q1'])
        ->press("toggleBookmark('q1')")
        ->assertNavigatedTo('/login');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('sends a guest comment submit to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Reply')
        ->set('comment', 'Nice work.')
        ->call('postComment')
        ->assertNavigatedTo('/login');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('sends a guest poll vote to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('votePoll', 'q1', 7)
        ->assertNavigatedTo('/login');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('opens another user profile for guests without an Authorization header', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('Ada Lovelace')
        ->assertSee('First programmer.')
        ->assertSee('42 Followers')
        ->assertSee('A public profile answer.')
        ->call('loadMoreQuestions');

    assertSentWithoutAuthorization('/api/v1/users/ada/questions');
});

it('sends a guest follow to login and keeps the card as-is', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    $test = Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('Follow')
        ->press('toggleFollow')
        ->assertNavigatedTo('/login');

    expect($test->get('profile')['followed_by_me'])->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('keeps guests off their own profile', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Profile::class)->assertReplacedWith('/login');
});

it('searches for guests without an Authorization header', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(Search::class)
        ->assertSee('Discover new profiles to follow')
        ->set('query', 'ada')
        ->assertSee('Ada Lovelace')
        ->assertSee('#laravel');

    assertSentWithoutAuthorization('/api/v1/search');
});

it('opens a followers list for guests without an Authorization header', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(FollowList::class, ['username' => 'ada', 'mode' => 'followers'])
        ->assertSee('Grace Hopper')
        ->assertSee('@grace');

    assertSentWithoutAuthorization('/api/v1/users/ada/followers');
});

it('sends a guest follow from the list to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    asGuest();

    Native::test(FollowList::class, ['username' => 'ada', 'mode' => 'followers'])
        ->assertSee('Follow')
        ->press("toggleFollow('grace')")
        ->assertNavigatedTo('/login');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('never renders the legacy shared-update marker', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Legacy marker post.',
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('Legacy marker post.')
        ->assertDontSee('__UPDATE__');

    expect($test->get('posts')[0])->toMatchArray([
        'is_update' => true,
        'content' => null,
    ]);
});

it('keeps the marker out of thread ancestors too', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q3',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => '__UPDATE__',
            'answer' => 'Third post.',
            'thread' => ['parent_id' => 'q2', 'root_id' => 'q1', 'posts' => [[
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'content' => '__UPDATE__',
                'answer' => 'First post.',
                'thread' => ['parent_id' => null, 'root_id' => null, 'posts' => []],
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]]],
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee('First post.')
        ->assertSee('Third post.')
        ->assertDontSee('__UPDATE__');

    expect(data_get($test->get('posts'), '0.thread.posts.0'))->toMatchArray([
        'is_update' => true,
        'content' => null,
    ]);
});
