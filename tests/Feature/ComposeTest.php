<?php

declare(strict_types=1);

use App\NativeComponents\Compose;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\DraftStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

function assertQuestionNotPublished(): void
{
    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
}

function treeHasPressableFor(array $node, string $text): bool
{
    $pressId = $node['on_press'] ?? $node['props']['on_press'] ?? null;

    if (is_int($pressId) && treeHasText($node, $text)) {
        return true;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && treeHasPressableFor($child, $text)) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<array<string, mixed>>
 */
function composeParityFindAll(array $node, callable $match): array
{
    $found = $match($node) ? [$node] : [];

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child)) {
            $found = array_merge($found, composeParityFindAll($child, $match));
        }
    }

    return $found;
}

function treeHasText(array $node, string $text): bool
{
    foreach ((array) ($node['props'] ?? []) as $value) {
        if (is_string($value) && str_contains($value, $text)) {
            return true;
        }
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && treeHasText($child, $text)) {
            return true;
        }
    }

    return false;
}

it('redirects guests to login', function (): void {
    Native::test(Compose::class)
        ->assertReplacedWith('/login');
});

it('renders the composer', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->assertSee('Share an update')
        ->assertSee('Post');
});

it('requires content before publishing', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->call('publish')
        ->assertSee('Write something first');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('adds and removes thread posts up to the limit', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    $test = Native::test(Compose::class)
        ->call('addPost');

    expect($test->get('posts'))->toBe([]);

    $test = Native::test(Compose::class)
        ->set('content', 'First post.');

    for ($i = 0; $i < 9; $i++) {
        $test->call('addPost');
        $test->fireEvent("updatePost($i)", TestableComponent::EVENT_TEXT_CHANGE, ['text' => "Post $i."]);
    }

    expect($test->get('posts'))->toHaveCount(9);

    $test->call('addPost');

    expect($test->get('posts'))->toHaveCount(9);

    $test->call('removePost', 0);

    expect($test->get('posts'))->toHaveCount(8)
        ->and($test->get('postPolls'))->toHaveCount(8);
});

it('captures follow-up text through change events', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Second post.'])
        ->assertSet('posts', ['Second post.']);
});

it('publishes a thread and returns to the feed', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Second post.'])
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://pinkary.test/api/v1/questions'
        && $request['content'] === 'First post.'
        && $request['thread_posts'] === ['Second post.']);
});

it('returns to login when the token is rejected', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(null, 401),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('publish')
        ->assertReplacedWith('/login');
});

it('rejects overlong follow-up posts without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => str_repeat('a', 1001)])
        ->call('publish')
        ->assertSee('Post 2 may not be greater than');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('surfaces validation errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['content' => ['The content has too many blank characters.']],
        ], 422),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('publish')
        ->assertSee('too many blank characters');
});

it('surfaces rate limit messages from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response([
            'message' => 'You can only send 3 questions per minute.',
        ], 429),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('publish')
        ->assertSee('3 questions per minute');
});

it('clears the composer after publishing', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    $test = Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Second post.'])
        ->call('publish')
        ->assertNavigatedTo('/feed');

    expect($test->get('content'))->toBe('')
        ->and($test->get('posts'))->toBe([]);
});

it('searches and selects a channel', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => [
            ['id' => 3, 'name' => 'Laravel', 'slug' => 'laravel'],
        ]]),
    ]);

    Native::test(Compose::class)
        ->call('togglePicker')
        ->set('channelQuery', 'lara')
        ->assertSee('Laravel')
        ->call('selectChannel', 3)
        ->assertSet('channelId', 3)
        ->assertSet('channelName', 'Laravel');
});

it('toggles a poll and validates its options locally', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'Which framework?')
        ->call('togglePoll')
        ->call('publish')
        ->assertSee('at least 2 options');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('publishes a poll with a channel and duration', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class)
        ->set('content', 'Which framework?')
        ->set('channelResults', [['id' => 3, 'name' => 'Laravel', 'slug' => 'laravel']])
        ->call('selectChannel', 3)
        ->call('togglePoll')
        ->fireEvent('updatePollOption(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Laravel'])
        ->fireEvent('updatePollOption(1)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Rails'])
        ->call('setPollDuration', 3)
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://pinkary.test/api/v1/questions'
        && $request['channel_id'] === 3
        && $request['poll_options'] === ['Laravel', 'Rails']
        && (int) $request['poll_duration'] === 3);
});

it('rejects overlong poll options without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'Which framework?')
        ->call('togglePoll')
        ->fireEvent('updatePollOption(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => str_repeat('a', 41)])
        ->fireEvent('updatePollOption(1)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Rails'])
        ->call('publish')
        ->assertSee('cannot exceed 40 characters');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('stages a new channel name for creation at publish', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class)
        ->set('content', 'A new channel post.')
        ->set('channelQuery', 'Brand New')
        ->call('stageChannel')
        ->assertSet('channelName', 'Brand New')
        ->assertSee('Brand New')
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://pinkary.test/api/v1/questions'
        && $request['channel_name'] === 'Brand New');
});

it('rejects invalid staged channel names without sending', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->call('togglePicker')
        ->set('channelQuery', 'x')
        ->call('stageChannel')
        ->assertSee('between 2 and 50 characters');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('publishes a follow-up poll aligned with its post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Second post.'])
        ->call('togglePostPoll', 0)
        ->fireEvent('updatePostPollOption(0, 0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Laravel'])
        ->fireEvent('updatePostPollOption(0, 1)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Rails'])
        ->call('setPostPollDuration', 0, 7)
        ->assertSee('Post thread')
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://pinkary.test/api/v1/questions'
        && $request['thread_polls'][0]['options'] === ['Laravel', 'Rails']
        && (int) $request['thread_polls'][0]['duration'] === 7);
});

it('validates follow-up polls locally without sending', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'Second post.'])
        ->call('togglePostPoll', 0)
        ->call('publish')
        ->assertSee('at least 2 options');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('only makes add-post tappable once a follow-up is allowed', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    $test = Native::test(Compose::class)
        ->assertSee('Add another post');

    expect(treeHasPressableFor($test->tree(), 'Add another post'))->toBeFalse();

    $test->set('content', 'First post.');

    expect(treeHasPressableFor($test->tree(), 'Add another post'))->toBeTrue();
});

it('renders ask mode for another user without thread UI', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
    ]);

    Native::test(Compose::class, ['username' => 'ada'])
        ->assertSee('Ask @ada')
        ->assertSee('Anonymously')
        ->assertSee('Ask')
        ->assertDontSee('Add another post')
        ->assertDontSee('Channel');

    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/v1/channels'));
});

it('stitches thread posts onto one avatar rail, like the web composer', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    DraftStore::clear();

    $test = Native::test(Compose::class)
        ->set('content', 'Opening post.')
        ->set('posts', ['Opening post.', 'Second post.', 'Third post.']);

    $rail = composeParityFindAll(
        $test->tree(),
        fn (array $node): bool => ($node['type'] ?? '') === 'column'
            && (float) data_get($node, 'layout.width') === 1.0
            && (float) data_get($node, 'layout.flex_grow') === 1.0
    );

    expect($rail)->toHaveCount(8);

    $bare = composeParityFindAll(
        $test->tree(),
        fn (array $node): bool => ($node['type'] ?? '') === 'bare_text_input'
    );

    expect($bare)->toHaveCount(4);

    $test->assertSee('Say more...')
        ->assertSee('Post thread')
        ->assertDontSee('Add another option');
});

it('sends the question and opens the asked profile', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class, ['username' => 'ada'])
        ->set('content', 'What are you building?')
        ->set('anonymous', true)
        ->call('publish')
        ->assertNavigatedTo('/user/ada');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://pinkary.test/api/v1/questions'
        && $request['content'] === 'What are you building?'
        && $request['to_username'] === 'ada'
        && (bool) $request['anonymously']);
});

it('requires a question before asking', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
    ]);

    Native::test(Compose::class, ['username' => 'ada'])
        ->call('publish')
        ->assertSee('Write your question first');

    assertQuestionNotPublished();
});

it('rejects overlong questions without sending', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
    ]);

    Native::test(Compose::class, ['username' => 'ada'])
        ->set('content', str_repeat('a', 256))
        ->call('publish')
        ->assertSee('255 characters');

    assertQuestionNotPublished();
});

it('opens the composer without waiting on a profile response', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', [
        'username' => 'ada',
        'name' => 'Ada',
        'avatar' => 'https://example.com/ada.png',
    ]);
    CurrentUser::store([
        'username' => 'ada',
        'name' => 'Ada',
        'avatar' => 'https://example.com/ada.png',
    ]);

    Http::fake(function ($request) {
        if (str_contains((string) $request->url(), '/api/v1/profile')) {
            throw new RuntimeException('profile must not block compose');
        }

        return Http::response(['data' => []]);
    });

    Native::test(Compose::class)
        ->assertSee('Share an update');

    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/v1/profile'));
});

it('restores a thread draft when the composer is reopened', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    Native::test(Compose::class)
        ->set('content', 'Half-written thought')
        ->call('addPost')
        ->fireEvent('updatePost(0)', TestableComponent::EVENT_TEXT_CHANGE, ['text' => 'A follow-up.'])
        ->call('togglePoll');

    Native::test(Compose::class)
        ->assertSet('content', 'Half-written thought')
        ->assertSet('posts', ['A follow-up.'])
        ->assertSet('isPoll', true)
        ->assertSee('Share an update');
});

it('clears the draft after a successful publish', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => [[]]], 201),
    ]);

    Native::test(Compose::class)
        ->set('content', 'First post.')
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Native::test(Compose::class)
        ->assertSet('content', '');
});

it('shows live over-limit validation without publishing', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake();

    $test = Native::test(Compose::class)
        ->set('content', str_repeat('a', 1001))
        ->assertSee('Over the 1000 character limit.');

    expect($test->instance())->toBeInstanceOf(Compose::class)
        ->and($test->instance()->canPublish())->toBeFalse();

    $test->call('publish');

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions'));
});

it('uses the 255 character ask limit and does not restore thread drafts', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
    ]);

    Native::test(Compose::class)
        ->set('content', 'A thread draft that should stay in the thread composer.');

    $ask = Native::test(Compose::class, ['username' => 'grace'])
        ->assertSet('content', '')
        ->assertDontSee('thread draft')
        ->set('content', str_repeat('a', 256))
        ->assertSee('Over the 255 character limit.');

    expect($ask->instance())->toBeInstanceOf(Compose::class)
        ->and($ask->instance()->canPublish())->toBeFalse();

    $ask->call('publish');

    assertQuestionNotPublished();
});

it('surfaces ask validation errors from the API', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['content' => ['The content has too many blank characters.']],
        ], 422),
    ]);

    Native::test(Compose::class, ['username' => 'ada'])
        ->set('content', 'What are you building?')
        ->call('publish')
        ->assertSee('too many blank characters');
});
