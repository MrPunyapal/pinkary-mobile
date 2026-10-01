<?php

use App\NativeComponents\Compose;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Register;
use App\Support\AccountStore;
use App\Support\DisplayText;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/** @return array<string, mixed> */
function interactionPost(string $id, string $answer): array
{
    return [
        'id' => $id,
        'from' => ['name' => 'Ada', 'username' => 'ada'],
        'to' => ['name' => 'Ada', 'username' => 'ada'],
        'content' => '__UPDATE__',
        'answer' => $answer,
        'metrics' => ['likes' => 0, 'comments' => 0],
    ];
}

/** @return array<string, mixed>|null */
function interactionNode(array $tree, string $type): ?array
{
    if (($tree['type'] ?? '') === $type) {
        return $tree;
    }

    foreach ($tree['children'] ?? [] as $child) {
        if (($found = interactionNode($child, $type)) !== null) {
            return $found;
        }
    }

    return null;
}

/** @return list<array<string, mixed>> */
function interactionNodes(array $tree): array
{
    $nodes = [$tree];
    foreach ($tree['children'] ?? [] as $child) {
        $nodes = [...$nodes, ...interactionNodes($child)];
    }

    return $nodes;
}

it('uses compact supported password inputs and preserves values when revealing them', function (string $component, int $passwordCount): void {
    $screen = Native::test($component)->input('password', 'test-password');
    $passwords = array_values(array_filter(interactionNodes($screen->tree()), fn (array $node): bool => ($node['props']['secure'] ?? false) === true));

    expect($passwords)->toHaveCount($passwordCount);
    foreach ($passwords as $input) {
        expect($input['type'])->toBe('outlined_text_input')
            ->and($input['props'])->toMatchArray(['size' => 'sm', 'keyboard' => 'password', 'sync_mode' => 'live']);
    }

    $screen->tap('Show password')->assertSet('password', 'test-password')->assertSet('showPassword', true);

    expect(array_filter(interactionNodes($screen->tree()), fn (array $node): bool => ($node['props']['secure'] ?? false) === true))->toBeEmpty();
})->with([
    'login' => [Login::class, 1],
    'registration' => [Register::class, 2],
]);

it('opens a mentioned profile from post text', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', 'Hello <a href="/@grace">@grace</a>!')]]),
    ]);

    Native::test(Home::class)
        ->tap('@grace')
        ->assertNavigatedTo('/user/grace');
});

it('opens an inline link containing quotes without changing the target', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', '<a href="https://example.com/?q=it&#039;s">Documentation</a>')]]),
    ]);

    Native::test(Home::class)
        ->tap('Documentation')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => ($params['url'] ?? '') === "https://example.com/?q=it's");
});

it('preserves paragraph breaks and excludes executable link targets', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);

    expect(DisplayText::plain('<p>First<br>Second</p><p>Third</p>'))->toBe("First\nSecond\nThird");
    expect(DisplayText::runs('<p>First<br>Second</p><p>Third</p>'))->toBe([
        ['text' => "First\nSecond\nThird", 'type' => 'text', 'target' => ''],
    ]);
    expect(DisplayText::runs('<a href="javascript:alert(1)">Click</a><script>hidden()</script>'))->toBe([
        ['text' => 'Click', 'type' => 'text', 'target' => ''],
    ]);
    expect(DisplayText::runs('Email ada@example.com and mention @grace'))->toContain([
        'text' => '@grace', 'type' => 'mention', 'target' => 'grace',
    ])->not->toContain(['text' => '@example', 'type' => 'mention', 'target' => 'example']);
});

it('preserves authored paragraphs and list lines in rendered post text', function (string $answer): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', $answer)]]),
    ]);

    Native::test(Home::class)->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? '') === "Intro.\n\nSomething\n\n- something 1\n- 2\n- 3");
})->with([
    'raw newlines' => ["Intro.\n\nSomething\n\n- something 1\n- 2\n- 3"],
    'API line break markup' => ['Intro.<br /><br />Something<br /><br />- something 1<br />- 2<br />- 3'],
]);

it('preserves spacing and blank lines around tappable mentions and links', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    $answer = "Hello  @grace\n\nLinks: https://example.com\n\n- something 1\n- 2\n- 3";
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', $answer)]]),
    ]);

    $screen = Native::test(Home::class);
    $paragraph = collect(interactionNodes($screen->tree()))->first(fn (array $node): bool => ($node['props']['a11y_label'] ?? '') === $answer);
    $lines = array_map(fn (array $row): string => implode('', array_map(fn (array $word): string => $word['props']['text'] ?? '', $row['children'] ?? [])), $paragraph['children']);

    expect(implode("\n", $lines))->toBe($answer);
    $screen->tap('@grace')->assertNavigatedTo('/user/grace');
});

it('keeps each feed post as an individual native list item', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', 'First.'), interactionPost('q2', 'Second.')]]),
    ]);

    $screen = Native::test(Home::class);
    $list = interactionNode($screen->tree(), 'list');
    $postIds = array_column(array_slice($list['children'], 2, 2), 'id');
    $screen->call('openMenu', 'missing');

    expect($postIds)->toHaveCount(2)
        ->and(count(array_unique($postIds)))->toBe(2)
        ->and(array_column(array_slice(interactionNode($screen->tree(), 'list')['children'], 2, 2), 'id'))->toBe($postIds);
});

it('does not give a thread connector an estimated content height', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    $post = interactionPost('q2', 'Reply.');
    $ancestor = interactionPost('q1', str_repeat('Long text ', 60));
    $ancestor['preview'] = ['url' => 'https://example.com', 'image' => 'https://example.com/card.png'];
    $post['thread'] = ['root_id' => 'q1', 'posts' => [$ancestor]];
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [$post]]),
    ]);

    $rails = array_values(array_filter(interactionNodes(Native::test(Home::class)->tree()), fn (array $node): bool => ($node['type'] ?? '') === 'rect' && ($node['layout']['width'] ?? null) == 2));

    expect($rails)->toHaveCount(2);
    foreach ($rails as $rail) {
        expect($rail['layout']['height'])->toBeLessThanOrEqual(16);
    }
});

it('assigns distinct node identities to linked questions and answers', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    $post = interactionPost('q1', 'Answer for @grace');
    $post['content'] = 'Question from @ada';
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [$post]]),
    ]);

    $screen = Native::test(Home::class);
    $nodeIds = array_column(interactionNodes($screen->tree()), 'id');

    expect(array_unique($nodeIds))->toHaveCount(count($nodeIds));
    $screen->tap('@grace')->assertNavigatedTo('/user/grace');
});

it('keeps the post sheet identity and editor state when a menu tap repeats', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/notifications*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [interactionPost('q1', 'Editable answer.')]]),
    ]);

    $screen = Native::test(Home::class);
    $sheetId = interactionNode($screen->tree(), 'bottom_sheet')['id'];
    $screen->tap('Open post menu')->call('startEditMenu')->call('openMenu', 'q1');

    $screen->assertSet('menuStep', 'edit')->assertSet('editAnswer', 'Editable answer.');
    expect(interactionNode($screen->tree(), 'bottom_sheet')['id'])->toBe($sheetId);

    $screen->call('closeMenu');

    expect(interactionNode($screen->tree(), 'bottom_sheet')['props']['visible'])->toBeFalse();
});

it('suggests and inserts a mention in the primary composer', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [['name' => 'Grace Hopper', 'username' => 'grace']]]]),
    ]);

    Native::test(Compose::class)
        ->input('content', 'Hello @gr', 9)
        ->tap('Mention @grace')
        ->assertSet('content', 'Hello @grace ')
        ->assertSet('mentionResults', []);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/search') && $request['q'] === 'gr');
});

it('inserts mentions in a follow-up without overwriting the root post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [['name' => 'Grace Hopper', 'username' => 'grace']]]]),
    ]);

    Native::test(Compose::class)
        ->input('content', 'Root post.')
        ->call('addPost')
        ->input('post-0', 'Thanks @gr', 10)
        ->tap('Mention @grace')
        ->assertSet('content', 'Root post.')
        ->assertSet('posts', ['Thanks @grace ']);
});

it('does not search for mentions while typing an email address', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
    ]);

    Native::test(Compose::class)
        ->input('content', 'Email ada@example.com')
        ->assertSet('mentionResults', []);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/search'));
});

it('searches only on settled caret events and preserves text after an inserted mention', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [['name' => 'Grace Hopper', 'username' => 'grace']]]]),
    ]);

    $screen = Native::test(Compose::class)->input('content', 'Hi 👋 @gr for your help.');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/search'));

    $screen->moveCaret('content', 8)->moveCaret('content', 8)->tap('Mention @grace')
        ->assertSet('content', 'Hi 👋 @grace for your help.');

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/search')))->toHaveCount(1);
});

it('clears suggestions when the caret selects a range or leaves a mention', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'token', ['username' => 'ada']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ['username' => 'ada']]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [['name' => 'Grace Hopper', 'username' => 'grace']]]]),
    ]);

    Native::test(Compose::class)->input('content', 'Hi @gr', 6)->assertSee('Mention @grace')
        ->moveCaret('content', 3, 6)->assertSet('mentionResults', [])
        ->moveCaret('content', 6)->assertSee('Mention @grace')
        ->moveCaret('content', 0)->assertSet('mentionResults', []);
});
