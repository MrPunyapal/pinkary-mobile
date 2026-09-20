<?php

use App\NativeComponents\Detail;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function detailFakes(string $answer = 'A thoughtful mobile experience.', array $comments = []): array
{
    return [
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => $answer,
            'metrics' => ['likes' => 5, 'comments' => count($comments)],
            'views' => 10,
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response([
            'data' => $comments,
            'links' => ['next' => null],
        ]),
    ];
}

function commentRow(string $id, string $content): array
{
    return [
        'id' => $id,
        'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        'to' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        'content' => $content,
        'answer' => null,
        'metrics' => ['likes' => 0, 'comments' => 0],
        'views' => 0,
    ];
}

it('redirects guests to login', function (): void {
    Native::test(Detail::class, ['id' => 'q1'])
        ->assertReplacedWith('/login');
});

it('returns to the feed without an id', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake();

    Native::test(Detail::class)
        ->assertReplacedWith('/feed');

    Http::assertNothingSent();
});

it('renders the post with its comments', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'First comment.'),
        commentRow('c2', 'Second comment.'),
    ]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('First comment.')
        ->assertSee('Second comment.');
});

it('resolves through the native route', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'Routed comment.'),
    ]));

    Native::visit('/question/q1')
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('Routed comment.');
});

it('renders the connected thread above the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q3' => Http::response([
            'data' => [
                'id' => 'q3',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'content' => '__UPDATE__',
                'answer' => 'Third post.',
                'thread' => ['parent_id' => 'q2', 'root_id' => 'q1'],
                'metrics' => ['likes' => 0, 'comments' => 0],
            ],
            'thread' => [
                [
                    'id' => 'q1',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'content' => '__UPDATE__',
                    'answer' => 'First post.',
                    'thread' => ['parent_id' => null, 'root_id' => null],
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ],
                [
                    'id' => 'q2',
                    'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                    'content' => '__UPDATE__',
                    'answer' => 'Second post.',
                    'thread' => ['parent_id' => 'q1', 'root_id' => 'q1'],
                    'metrics' => ['likes' => 0, 'comments' => 0],
                ],
            ],
        ]),
        'https://pinkary.test/api/v1/questions/q3/comments*' => Http::response(['data' => []]),
    ]);

    $test = Native::test(Detail::class, ['id' => 'q3'])
        ->assertSee('First post.')
        ->assertSee('Second post.')
        ->assertSee('Third post.');

    expect($test->get('thread'))->toHaveCount(2);
});

it('votes in a thread ancestor poll', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response([
            'data' => [
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
                'content' => 'Pick one.',
                'answer' => 'Pick one.',
                'poll' => [
                    'expires_at' => now()->addDay()->toIso8601String(),
                    'expired' => false,
                    'time_remaining' => 'in 1 day',
                    'total_votes' => 0,
                    'user_vote_option_id' => null,
                    'options' => [
                        ['id' => 7, 'text' => 'Yes', 'votes' => 0],
                        ['id' => 8, 'text' => 'No', 'votes' => 0],
                    ],
                ],
                'metrics' => ['likes' => 0, 'comments' => 1],
            ],
            'thread' => [],
        ]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => [
            commentRow('c1', 'A comment.'),
        ]]),
        'https://pinkary.test/api/v1/questions/q1/poll/vote' => Http::response(['data' => [
            'expires_at' => now()->addDay()->toIso8601String(),
            'expired' => false,
            'time_remaining' => 'in 1 day',
            'total_votes' => 1,
            'user_vote_option_id' => 7,
            'options' => [
                ['id' => 7, 'text' => 'Yes', 'votes' => 1],
                ['id' => 8, 'text' => 'No', 'votes' => 0],
            ],
        ]]),
    ]);

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('0 votes')
        ->assertSee('Ends in 1 day')
        ->press("votePoll('q1', 7)");

    expect($test->get('post')['poll'])->toMatchArray(['total_votes' => 1, 'user_vote_option_id' => 7]);
});

it('shows just the composer without comments', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes());

    // Like the web question page, no comments box renders at all —
    // only the reply composer under the post.
    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('Reply');

    expect($test->get('comments'))->toBe([]);
});

it('shows an error when comments fail to load', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A thoughtful mobile experience.',
            'metrics' => ['likes' => 0, 'comments' => 3],
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(null, 500),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('Comments could not be loaded.');
});

it('retries loading a missing post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::sequence()
            ->push(null, 404)
            ->push(['data' => [
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary'],
                'content' => 'What are you building?',
                'answer' => 'Recovered answer.',
                'metrics' => ['likes' => 0, 'comments' => 0],
            ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('could not be loaded')
        ->call('retry')
        ->assertSee('Recovered answer.');
});

it('posts a comment and appends it last', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A thoughtful mobile experience.',
            'metrics' => ['likes' => 5, 'comments' => 1],
            'views' => 10,
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments' => Http::response(['data' => commentRow('c9', 'Brand new comment.')], 201),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response([
            'data' => [commentRow('c1', 'First comment.')],
            'links' => ['next' => null],
        ]),
    ]);

    // The API lists comments oldest first, so the new row lands at the
    // end — never prepended out of order.
    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', 'Brand new comment.')
        ->call('postComment')
        ->assertSee('First comment.')
        ->assertSee('Brand new comment.');

    expect($test->get('comments'))->toHaveCount(2)
        ->and(array_column($test->get('comments'), 'id'))->toBe(['c1', 'c9'])
        ->and($test->get('comment'))->toBe('');
});

it('requires comment content before posting', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes());

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('postComment')
        ->assertSee('Write a comment first.');

    Http::assertSentCount(2);
});

it('rejects overlong comments without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes());

    Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', str_repeat('a', 1001))
        ->call('postComment')
        ->assertSee('may not be greater than 1000 characters');

    Http::assertSentCount(2);
});

it('surfaces comment errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(array_merge([
        'https://pinkary.test/api/v1/questions/q1/comments' => Http::response(['message' => 'You can only send 3 questions per minute.'], 429),
    ], detailFakes()));

    Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', 'Too fast.')
        ->call('postComment')
        ->assertSee('3 questions per minute');
});

it('toggles likes on the detail post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 6]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->tap('5');

    expect($test->get('post')['metrics'])->toMatchArray(['liked' => true, 'likes' => 6]);
});

it('toggles likes on the answered post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(array_merge(detailFakes(comments: [
        commentRow('c1', 'Likable comment.'),
    ]), [
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 6]]),
    ]));

    // Comments carry no answer, so like the web they render as plain
    // bubbles with no action row — reactions live on the answered post.
    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Likable comment.')
        ->press("toggleLike('q1')");

    expect($test->get('post')['metrics'])->toMatchArray(['liked' => true, 'likes' => 6]);
});

it('appends older comments when loading more', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'Paged answer.',
            'metrics' => ['likes' => 0, 'comments' => 2],
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::sequence()
            ->push([
                'data' => [commentRow('c1', 'First comment.')],
                'links' => ['next' => 'https://pinkary.test/api/v1/questions/q1/comments?page=2'],
            ])
            ->push([
                'data' => [commentRow('c2', 'Second comment.')],
                'links' => ['next' => null],
            ]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('First comment.')
        ->assertSee('Load more comments')
        ->call('loadMoreComments')
        ->assertSee('Second comment.')
        ->assertDontSee('Load more comments');
});

it('toggles bookmarks on the detail post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/questions/q1/bookmark' => Http::response(['data' => ['bookmarked' => true, 'bookmarks' => 1]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->press("toggleBookmark('q1')");

    expect($test->get('post')['metrics']['bookmarked'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/bookmark'));
});

it('opens another post without self-linking the open one', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'Plain bubble comment.'),
    ]));

    // Answer-less comments are plain bubbles on the web too. Tapping the
    // answered post's text would open it — tapping the open post itself
    // is a no-op, so the screen stays put.
    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Plain bubble comment.')
        ->press("openDetail('q1')")
        ->assertSee('A thoughtful mobile experience.');
});

it('hides the reply composer on unanswered questions', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
            'content' => 'What are you building?',
            'answer' => null,
            'metrics' => ['likes' => 0, 'comments' => 0],
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    // Like the web question page, an unanswered question takes an
    // answer — not a comment — so no reply composer renders.
    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('What are you building?')
        ->assertDontSee('Reply')
        ->assertDontSee('Write a comment');

    $test->set('comment', 'Too early.')->call('postComment');

    expect($test->get('commentError'))->toContain('once this question has an answer');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/comments'));
});

it('does not duplicate a new comment when the next page replays it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    // One ordered sequence for every comments request: Laravel
    // evaluates all matching stubs eagerly, so an exact POST stub next
    // to a wildcard sequence would silently drain the sequence.
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'Paged answer.',
            'metrics' => ['likes' => 0, 'comments' => 3],
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::sequence()
            ->push([
                'data' => [commentRow('c1', 'First comment.')],
                'links' => ['next' => 'https://pinkary.test/api/v1/questions/q1/comments?page=2'],
            ])
            ->push(['data' => commentRow('c9', 'Brand new comment.')], 201)
            ->push([
                'data' => [commentRow('c9', 'Brand new comment.'), commentRow('c2', 'Second comment.')],
                'links' => ['next' => null],
            ]),
    ]);

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', 'Brand new comment.')
        ->call('postComment')
        ->call('loadMoreComments')
        ->assertSee('First comment.')
        ->assertSee('Second comment.')
        ->assertDontSee('Load more comments');

    expect(array_column($test->get('comments'), 'id'))->toBe(['c1', 'c9', 'c2']);
});

it('surfaces poll vote failures instead of failing silently', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response([
            'data' => [
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
                'content' => 'Pick one.',
                'answer' => 'Pick one.',
                'poll' => [
                    'expires_at' => now()->addDay()->toIso8601String(),
                    'expired' => false,
                    'time_remaining' => 'in 1 day',
                    'total_votes' => 0,
                    'user_vote_option_id' => null,
                    'options' => [
                        ['id' => 7, 'text' => 'Yes', 'votes' => 0],
                        ['id' => 8, 'text' => 'No', 'votes' => 0],
                    ],
                ],
                'metrics' => ['likes' => 0, 'comments' => 0],
            ],
            'thread' => [],
        ]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions/q1/poll/vote' => Http::sequence()
            ->push(null, 500)
            ->push(['data' => [
                'expires_at' => now()->addDay()->toIso8601String(),
                'expired' => false,
                'time_remaining' => 'in 1 day',
                'total_votes' => 1,
                'user_vote_option_id' => 7,
                'options' => [
                    ['id' => 7, 'text' => 'Yes', 'votes' => 1],
                    ['id' => 8, 'text' => 'No', 'votes' => 0],
                ],
            ]]),
    ]);

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->press("votePoll('q1', 7)")
        ->assertSee('could not be recorded');

    expect($test->get('post')['poll'])->toMatchArray(['total_votes' => 0]);

    $test->press("votePoll('q1', 7)")
        ->assertSee('1 vote')
        ->assertDontSee('could not be recorded');

    expect($test->get('post')['poll'])->toMatchArray(['total_votes' => 1, 'user_vote_option_id' => 7]);
});

it('renders expired polls static', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    session(['pinkary_api_token' => 'test-token']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response([
            'data' => [
                'id' => 'q1',
                'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
                'content' => '__UPDATE__',
                'answer' => 'Closed poll post.',
                'poll' => [
                    'expired' => true,
                    'total_votes' => 5,
                    'user_vote_option_id' => 1,
                    'options' => [
                        ['id' => 1, 'text' => 'Laravel', 'votes' => 4],
                        ['id' => 2, 'text' => 'Rails', 'votes' => 1],
                    ],
                ],
                'metrics' => ['likes' => 0, 'comments' => 0],
            ],
            'thread' => [],
        ]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Laravel')
        ->assertSee('Poll expired')
        ->assertSee('5 votes');
});
