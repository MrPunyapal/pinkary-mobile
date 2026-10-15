<?php

declare(strict_types=1);

use App\NativeComponents\Detail;
use App\Support\AccountStore;
use App\Support\DraftStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;

afterEach(function (): void {
    DraftStore::clearReplyDraft('q1');
});

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
        'content' => null,
        'answer' => $content,
        'is_update' => true,
        'metrics' => ['likes' => 0, 'comments' => 0],
        'views' => 0,
    ];
}

it('shows the complete post on its detail page without a View more control', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    $answer = str_repeat('Full post text. ', 100).'The end.';
    Http::fake(detailFakes($answer));

    Native::test(Detail::class, ['id' => 'q1'])->assertSee($answer)
        ->assertDontSee('View more')->assertDontSee('Show more')->assertDontSee('Show less');
});

it('opens a post for guests and sends writes to login', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake(detailFakes(comments: [commentRow('c1', 'A public comment.')]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('A public comment.')
        ->press("toggleLike('q1')")
        ->assertNavigatedTo('/login');
});

it('returns to the feed without an id', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Detail::class)
        ->assertReplacedWith('/feed');

    Http::assertNothingSent();
});

it('renders the post with its comments', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'First comment.'),
        commentRow('c2', 'Second comment.'),
    ]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('First comment.')
        ->assertSee('Second comment.');
});

it('renders working post actions for comments', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(comments: [commentRow('c1', 'Comment with actions.')]), [
        'https://pinkary.test/api/v1/questions/c1/like' => Http::response(['data' => ['liked' => true, 'likes' => 1]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Open comments')
        ->assertSee('Like this post')
        ->assertSee('Repost this post')
        ->assertSee('Bookmark this post')
        ->assertSee('Share this post')
        ->assertSee('Open post menu')
        ->press("toggleLike('c1')");

    expect($test->get('comments.0.metrics'))->toMatchArray(['liked' => true, 'likes' => 1]);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/c1/like'));
});

it('renders images attached to comments', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes(comments: [array_merge(commentRow('c1', 'Comment with an image.'), [
        'images' => ['https://pinkary.test/storage/comment-image.jpg'],
    ])]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Comment with an image.')
        ->assertSee('Open image fullscreen');
});

it('uploads and sends an image from the reply composer', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/images' => Http::response(['data' => [[
            'url' => 'https://pinkary.test/storage/reply.png',
            'path' => 'images/2026/reply.png',
            'original_name' => 'reply.png',
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/comments' => Http::response([
            'data' => commentRow('c9', 'Reply with an image.'),
        ], 201),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A thoughtful mobile experience.',
            'metrics' => ['likes' => 5, 'comments' => 0],
        ]]),
        'https://pinkary.test/api/v1/captcha*' => Http::response(['data' => [
            'required' => false,
            'widget_path' => '/api/v1/captcha/widget',
            'return_path' => '/api/v1/captcha/result',
        ]]),
    ]);

    $path = sys_get_temp_dir().'/pinkary-reply-'.Str::uuid().'.png';
    file_put_contents($path, 'image');

    try {
        Native::test(Detail::class, ['id' => 'q1'])
            ->set('comment', 'Reply with an image.')
            ->call('onMediaSelected', new MediaSelected(
                success: true,
                files: [['path' => $path]],
                count: 1,
            ))
            ->call('postComment');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/questions/q1/comments')
            && $request['content'] === "Reply with an image.\n![reply.png](images/2026/reply.png)");
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('suggests and inserts mentions and hashtags in a reply', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/search*' => Http::sequence()
            ->push(['data' => [
                'users' => [['name' => 'Grace Hopper', 'username' => 'grace']],
                'hashtags' => [],
            ]])
            ->push(['data' => [
                'users' => [],
                'hashtags' => [['name' => 'laravel']],
            ]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', '@gra')
        ->call('updateMentionSelection', -1, '@gra', 4, 4)
        ->assertSee('Grace Hopper')
        ->call('selectMention', 0)
        ->set('comment', '#lar')
        ->call('updateMentionSelection', -1, '#lar', 4, 4)
        ->assertSee('#laravel')
        ->call('selectMention', 0);

    expect($test->get('comment'))->toBe('#laravel ');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/v1/search?')
        && $request['q'] === 'lar');
});

it('restores a reply draft only for its parent post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    DraftStore::putReplyDraft('q1', [
        'content' => 'A saved reply.',
        'isPoll' => true,
        'pollOptions' => ['Yes', 'No'],
        'pollDuration' => 3,
    ]);
    Http::fake(detailFakes());

    $test = Native::test(Detail::class, ['id' => 'q1']);

    expect($test->get('comment'))->toBe('A saved reply.')
        ->and($test->get('commentIsPoll'))->toBeTrue()
        ->and($test->get('commentPollOptions'))->toBe(['Yes', 'No'])
        ->and($test->get('commentPollDuration'))->toBe(3)
        ->and(DraftStore::getReplyDraft('q2'))->toBeNull();
});

it('lets guests translate replies from the thread', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    Http::fake(detailFakes(comments: [commentRow('c1', 'A public comment.')]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A public comment.')
        ->assertSee('Translate answer')
        ->press("translatePost('c1', 'answer')")
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => str_contains(
            (string) ($params['url'] ?? ''),
            urlencode('A public comment.')
        ));
});

it('resolves through the native route', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'Routed comment.'),
    ]));

    Native::visit('/question/q1')
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('Routed comment.');
});

it('renders the connected thread above the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes());

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('A thoughtful mobile experience.')
        ->assertSee('Reply');

    expect($test->get('comments'))->toBe([]);
});

it('shows an error when comments fail to load', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', 'Brand new comment.')
        ->call('postComment')
        ->assertSee('First comment.')
        ->assertSee('Brand new comment.');

    expect($test->get('comments'))->toHaveCount(2)
        ->and(array_column($test->get('comments'), 'id'))->toBe(['c1', 'c9'])
        ->and($test->get('comment'))->toBe('');
});

it('sends reply poll options and duration using the api contract', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1/comments' => Http::response([
            'data' => commentRow('c9', 'A poll reply.'),
        ], 201),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response([
            'data' => [],
            'links' => ['next' => null],
        ]),
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1',
            'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'to' => ['name' => 'Pinkary'],
            'content' => 'What are you building?',
            'answer' => 'A thoughtful mobile experience.',
            'metrics' => ['likes' => 5, 'comments' => 0],
        ]]),
        'https://pinkary.test/api/v1/captcha*' => Http::response(['data' => [
            'required' => false,
            'widget_path' => '/api/v1/captcha/widget',
            'return_path' => '/api/v1/captcha/result',
        ]]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', 'A poll reply.')
        ->call('toggleCommentPoll')
        ->call('updateCommentPollOption', 0, 'Yes')
        ->call('updateCommentPollOption', 1, 'No')
        ->call('setCommentPollDuration', 3)
        ->call('postComment');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/comments')
        && $request['content'] === 'A poll reply.'
        && $request['poll_options'] === ['Yes', 'No']
        && $request['poll_duration'] === 3);
});

it('requires comment content before posting', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes());

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('postComment')
        ->assertSee('Write a comment first.');

    Http::assertSentCount(2);
});

it('rejects overlong comments without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes());

    Native::test(Detail::class, ['id' => 'q1'])
        ->set('comment', str_repeat('a', 1001))
        ->call('postComment')
        ->assertSee('may not be greater than 1000 characters');

    Http::assertSentCount(2);
});

it('surfaces comment errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 6]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->tap('5');

    expect($test->get('post')['metrics'])->toMatchArray(['liked' => true, 'likes' => 6]);
});

it('toggles reposts on the detail post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/questions/q1/repost' => Http::response(['data' => ['reposted' => true, 'reposts' => 1]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->press("toggleRepost('q1')");

    expect($test->get('post')['metrics'])->toMatchArray(['reposted' => true, 'reposts' => 1]);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/repost'));
});

it('toggles likes on the answered post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(comments: [
        commentRow('c1', 'Likable comment.'),
    ]), [
        'https://pinkary.test/api/v1/questions/q1/like' => Http::response(['data' => ['liked' => true, 'likes' => 6]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Likable comment.')
        ->press("toggleLike('q1')");

    expect($test->get('post')['metrics'])->toMatchArray(['liked' => true, 'likes' => 6]);
});

it('appends older comments when loading more', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
        ->assertDontSee('Load more comments')
        ->call('loadMoreComments')
        ->assertSee('Second comment.')
        ->assertDontSee('Load more comments');
});

it('toggles bookmarks on the detail post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(array_merge(detailFakes(), [
        'https://pinkary.test/api/v1/questions/q1/bookmark' => Http::response(['data' => ['bookmarked' => true, 'bookmarks' => 1]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->press("toggleBookmark('q1')");

    expect($test->get('post')['metrics']['bookmarked'])->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/bookmark'));
});

it('opens another post without self-linking the open one', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(detailFakes(comments: [
        commentRow('c1', 'Plain bubble comment.'),
    ]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Plain bubble comment.')
        ->press("openDetail('q1')")
        ->assertSee('A thoughtful mobile experience.');
});

it('hides the reply composer on unanswered questions', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('What are you building?')
        ->assertDontSee('Reply')
        ->assertDontSee('Write a comment');

    $test->set('comment', 'Too early.')->call('postComment');

    expect($test->get('commentError'))->toContain('once this question has an answer');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/comments'));
});

it('does not duplicate a new comment when the next page replays it', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    // A single sequence avoids matching HTTP fakes eagerly consuming each other's responses.
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
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

function menuPost(string $id = 'q1', array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'content' => '__UPDATE__',
        'answer' => 'A thoughtful mobile experience.',
        'pinned' => false,
        'metrics' => ['likes' => 2, 'comments' => 0],
        'views' => 10,
    ], $overrides);
}

function menuFakes(array $post, array $extra = []): array
{
    return array_merge([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => $post]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ], $extra);
}

it('suggests and inserts mentions and hashtags in the answer editor', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(), [
        'https://pinkary.test/api/v1/search*' => Http::sequence()
            ->push(['data' => [
                'users' => [['name' => 'Grace Hopper', 'username' => 'grace']],
                'hashtags' => [],
            ]])
            ->push(['data' => [
                'users' => [],
                'hashtags' => [['name' => 'laravel']],
            ]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('startEditMenu')
        ->set('editAnswer', '@gra')
        ->call('updateMentionSelection', -2, '@gra', 4, 4)
        ->assertSee('Grace Hopper')
        ->call('selectMention', 0)
        ->set('editAnswer', '#lar')
        ->call('updateMentionSelection', -2, '#lar', 4, 4)
        ->assertSee('#laravel')
        ->call('selectMention', 0);

    expect($test->get('editAnswer'))->toBe('#laravel ');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/v1/search?')
        && $request['q'] === 'lar');
});

/**
 * @return array<string, mixed>
 */
function pinQuestionOverrides(): array
{
    return [
        'content' => 'What are you building?',
        'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
    ];
}

it('pins the post and shows the pinned chip', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: pinQuestionOverrides()), [
        'https://pinkary.test/api/v1/questions/q1/pin' => Http::response(['data' => ['pinned' => true]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->press("openMenu('q1')")
        ->press('togglePinMenu');

    expect($test->get('post')['pinned'])->toBeTrue();

    $test->assertSee('Pinned');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions/q1/pin'));
});

it('unpins the post and drops the pinned chip', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: array_merge(pinQuestionOverrides(), ['pinned' => true])), [
        'https://pinkary.test/api/v1/questions/q1/pin' => Http::response(['data' => ['pinned' => false]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->assertSee('Pinned')
        ->call('openMenu', 'q1')
        ->assertSee('Unpin')
        ->press('togglePinMenu');

    expect($test->get('post')['pinned'])->toBeFalse();

    $test->assertDontSee('Pinned');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/questions/q1/pin'));
});

it('hides the pin entry after a pin 403', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: ['pinned' => true]), [
        'https://pinkary.test/api/v1/questions/q1/pin' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertSee('Unpin')
        ->call('togglePinMenu');

    expect($test->get('menuDenied'))->toMatchArray(['q1:pin' => true]);

    $test->call('openMenu', 'q1')
        ->assertDontSee('Unpin');
});

it('edits the answer inline and updates the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    $answerSource = "A thoughtful mobile experience.\n\n- Keep the original Markdown.\n- Preserve line breaks.";
    Http::fake(menuFakes(menuPost(overrides: [
        'answer' => $answerSource,
        'answer_html' => '<p>A thoughtful mobile experience.</p><ul><li>Keep the original Markdown.</li><li>Preserve line breaks.</li></ul>',
    ]), [
        'https://pinkary.test/api/v1/questions/q1/answer' => Http::response(['data' => [
            'id' => 'q1',
            'answer' => '<p>An even better answer.</p>',
            'edited' => true,
        ]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertSee('Edit answer')
        ->call('startEditMenu')
        ->assertSet('editAnswer', $answerSource)
        ->assertSee('Save answer')
        ->set('editAnswer', 'An even better answer.')
        ->call('saveAnswerMenu')
        ->assertSee('An even better answer.')
        ->assertDontSee('Save answer');

    expect($test->get('post')['answer'])->toBe('<p>An even better answer.</p>')
        ->and($test->get('menuPostId'))->toBe('');

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/v1/questions/q1/answer'));
});

it('lets the owner answer an unanswered post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: ['answer' => null]), [
        'https://pinkary.test/api/v1/questions/q1/answer' => Http::response(['data' => [
            'id' => 'q1',
            'answer' => '<p>A first answer.</p>',
            'edited' => false,
        ]]),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertSee('Answer this question')
        ->assertDontSee('Edit answer')
        ->press('startAnswerMenu')
        ->assertSee('Answer question')
        ->set('editAnswer', 'A first answer.')
        ->press('saveAnswerMenu')
        ->assertSee('A first answer.');

    expect($test->get('post'))->toMatchArray([
        'answer' => '<p>A first answer.</p>',
        'edited' => false,
    ]);

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/v1/questions/q1/answer')
        && $request['answer'] === 'A first answer.');
});

it('does not offer the answer action on another users unanswered post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: [
        'answer' => null,
        'to' => ['name' => 'Grace Hopper', 'username' => 'grace'],
    ])));

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertDontSee('Answer this question');
});

it('hides the answer action after the api rejects answering the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: ['answer' => null]), [
        'https://pinkary.test/api/v1/questions/q1/answer' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->press('startAnswerMenu')
        ->set('editAnswer', 'Not allowed.')
        ->press('saveAnswerMenu')
        ->assertSee('unauthorized')
        ->assertDontSee('Answer this question');

    expect($test->get('menuDenied'))->toMatchArray(['q1:answer' => true]);
});

it('surfaces answer validation errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(), [
        'https://pinkary.test/api/v1/questions/q1/answer' => Http::response([
            'message' => 'The answer must not be greater than 1000 characters.',
            'errors' => ['answer' => ['The answer must not be greater than 1000 characters.']],
        ], 422),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('startEditMenu')
        ->set('editAnswer', 'Too long.')
        ->call('saveAnswerMenu')
        ->assertSee('greater than 1000');

    expect($test->get('post')['answer'])->toBe('A thoughtful mobile experience.');
});

it('hides the edit entry after an answer 403', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(), [
        'https://pinkary.test/api/v1/questions/q1/answer' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('startEditMenu')
        ->set('editAnswer', 'New answer.')
        ->call('saveAnswerMenu')
        ->assertSee('unauthorized')
        ->assertDontSee('Edit answer');

    expect($test->get('menuDenied'))->toMatchArray(['q1:edit' => true]);
});

it('confirms deletion and navigates back to the feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::sequence()
            ->push(['data' => menuPost()])
            ->push(null, 204),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('askDeleteMenu')
        ->assertSee('Delete this post?')
        ->press('deleteMenuPost')
        ->assertNavigatedTo('/feed');

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/questions/q1/ignore'));
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/questions/q1'));
});

it('shows the api error when post deletion fails', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::sequence()
            ->push(['data' => menuPost()])
            ->push(['message' => 'The delete failed.'], 500),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('askDeleteMenu')
        ->press('deleteMenuPost')
        ->assertSee('The delete failed.')
        ->assertSee('Delete this post?');

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/questions/q1/ignore'));
});

it('hides the delete entry after a delete 403', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::sequence()
            ->push(['data' => menuPost()])
            ->push(['message' => 'This action is unauthorized.'], 403),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::response(['data' => []]),
    ]);

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('askDeleteMenu')
        ->call('deleteMenuPost')
        ->assertSee('unauthorized')
        ->assertDontSee('Delete this post?');

    expect($test->get('menuDenied'))->toMatchArray(['q1:delete' => true]);
});

it('lists likers inside the sheet', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(), [
        'https://pinkary.test/api/v1/questions/q1/likes*' => Http::response([
            'data' => [
                ['name' => 'Grace Hopper', 'username' => 'grace'],
                ['name' => 'Alan Turing', 'username' => 'alan'],
            ],
            'links' => ['next' => null],
        ]),
    ]));

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertSee('View likes')
        ->call('openLikersMenu')
        ->assertSee('Grace Hopper')
        ->assertSee('@grace')
        ->assertSee('Alan Turing');
});

it('hides the likes entry when the count is zero', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(overrides: ['metrics' => ['likes' => 0, 'comments' => 0]])));

    Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->assertDontSee('View likes');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/likes'));
});

it('hides the likes entry after a likers 403', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(menuFakes(menuPost(), [
        'https://pinkary.test/api/v1/questions/q1/likes*' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]));

    $test = Native::test(Detail::class, ['id' => 'q1'])
        ->call('openMenu', 'q1')
        ->call('openLikersMenu')
        ->assertSee('unauthorized');

    expect($test->get('menuDenied'))->toMatchArray(['q1:likes' => true]);

    $test->call('openMenu', 'q1')
        ->assertDontSee('View likes');
});
