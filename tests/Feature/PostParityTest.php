<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function parityFakeFeed(array $posts): void
{
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => $posts]),
    ]);
}

function parityPost(string $answer, array $overrides = []): array
{
    return array_merge([
        'id' => 'q1',
        'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'content' => 'What are you building?',
        'answer' => $answer,
        'pinned' => false,
        'views' => 0,
        'metrics' => ['likes' => 0, 'comments' => 0],
    ], $overrides);
}

it('shows mentions and hashtags as plain text, since native text cannot tap them', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $answer = 'Shipping <a href="/@ada">@ada</a> and <a href="/hashtag/laravel">#laravel</a> today.';

    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [parityPost($answer)]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Shipping @ada and #laravel today.')
        ->assertDontSee('<a href');
});

function parityFind(array $node, callable $match): ?array
{
    if ($match($node)) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child) && ($found = parityFind($child, $match)) !== null) {
            return $found;
        }
    }

    return null;
}

function parityCount(array $node, callable $match): int
{
    $total = $match($node) ? 1 : 0;

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child)) {
            $total += parityCount($child, $match);
        }
    }

    return $total;
}

function parityAncestors(array $node, callable $match): ?array
{
    foreach ($node['children'] ?? [] as $child) {
        if (! is_array($child)) {
            continue;
        }

        if ($match($child)) {
            return [$node];
        }

        $path = parityAncestors($child, $match);

        if ($path !== null) {
            return [$node, ...$path];
        }
    }

    return null;
}

function parityParent(array $tree, callable $match): ?array
{
    $chain = parityAncestors($tree, $match);

    return $chain === null ? null : end($chain);
}

function parityAncestor(array $tree, callable $match, callable $find): ?array
{
    foreach (parityAncestors($tree, $find) ?? [] as $ancestor) {
        if ($match($ancestor)) {
            return $ancestor;
        }
    }

    return null;
}

function parityOrder(array $node): array
{
    $out = [$node];

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child)) {
            $out = [...$out, ...parityOrder($child)];
        }
    }

    return $out;
}

function parityByText(array $tree, string $text): ?array
{
    return parityFind($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === $text);
}

function parityByA11y(array $tree, string $label): ?array
{
    return parityFind($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === $label);
}

it('paints feed rows with the web column white instead of the page gray', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('A single-post row.')]);

    $tree = Native::test(Home::class)->assertSee('A single-post row.')->tree();

    $impression = parityAncestor(
        $tree,
        fn (array $n): bool => ($n['type'] ?? null) === 'impression',
        fn (array $n): bool => ($n['props']['text'] ?? null) === 'A single-post row.'
    );
    $row = $impression['children'][0] ?? null;
    $separator = $row === null ? null : end($row['children']);

    expect($row)->not->toBeNull()
        ->and($row['style']['bg_color'])->toBe('#FFFFFF')
        ->and($row['style']['bg_color'])->not->toBe('#F1F5F9')
        ->and($row['props']['dark_bg_color'] ?? null)->toBe('#07101F')
        ->and($row['style']['border_width'] ?? null)->toBeNull()
        ->and($separator['layout']['height'] ?? null)->toEqual(1.0)
        ->and($separator['style']['bg_color'] ?? null)->toBe('#EDF1F7')
        ->and($separator['props']['dark_bg_color'] ?? null)->toBe('#131F30');
});

it('paints a chained thread container with the web column white too', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Root post.', [
        'content' => null,
        'is_update' => true,
        'thread' => [
            'parent_id' => 'q0',
            'root_id' => 'q0',
            'posts' => [parityPost('First post.', [
                'id' => 'q0',
                'content' => null,
                'is_update' => true,
            ])],
            'more' => false,
            'more_id' => null,
        ],
    ])]);

    $tree = Native::test(Home::class)
        ->assertSee('First post.')
        ->assertSee('Root post.')
        ->tree();

    $containers = array_values(array_filter(
        parityOrder($tree),
        fn (array $n): bool => ($n['style']['bg_color'] ?? null) === '#FFFFFF'
            && ($n['props']['dark_bg_color'] ?? null) === '#07101F'
    ));

    expect($containers)->not->toBeEmpty();

    foreach ($containers as $container) {
        expect($container['style']['bg_color'])->not->toBe('#F1F5F9');
    }

    $chain = parityAncestors($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'First post.');
    $painted = array_values(array_filter(
        $chain ?? [],
        fn (array $n): bool => ($n['style']['bg_color'] ?? null) === '#FFFFFF'
    ));

    expect($painted)->not->toBeEmpty();
});

it('hides the post menu from guests because every entry is an owner action', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    parityFakeFeed([parityPost('A public answer.')]);

    Native::test(Home::class)
        ->assertSee('A public answer.')
        ->assertDontSee('Open post menu');
});

it('hides the post menu from a signed-in viewer who does not own the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Someone else answered.', [
        'to' => ['name' => 'Grace Hopper', 'username' => 'grace'],
        'content' => null,
        'is_update' => true,
    ])]);

    Native::test(Home::class)
        ->assertSee('Someone else answered.')
        ->assertDontSee('Open post menu');
});

it('shows the post menu to the signed-in owner of the post', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('My own answer to go with it.')]);

    Native::test(Home::class)
        ->assertSee('My own answer to go with it.')
        ->assertSee('Open post menu')
        ->press("openMenu('q1')")
        ->assertSee('Share');
});

it('matches the owner check case-insensitively like the web policy', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Owned by Ada, capitalized.', [
        'to' => ['name' => 'Ada Lovelace', 'username' => 'Ada'],
    ])]);

    Native::test(Home::class)->assertSee('Owned by Ada, capitalized.')->assertSee('Open post menu');
});

it('keeps the views chart icon visible at zero views like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Nobody has viewed this.', ['views' => 0])]);

    $tree = Native::test(Home::class)->assertSee('Nobody has viewed this.')->tree();

    $actionRow = parityParent($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Share this post');

    expect($actionRow['type'] ?? null)->toBe('row');

    $metricRows = array_values(array_filter(
        $actionRow['children'] ?? [],
        fn (array $child): bool => ($child['type'] ?? null) === 'row'
            && ! isset($child['on_press'])
            && parityCount($child, fn (array $n): bool => ($n['type'] ?? null) === 'icon') === 1
            && parityCount($child, fn (array $n): bool => ($n['type'] ?? null) === 'text') === 0
    ));

    expect($metricRows)->toHaveCount(1);

    expect(parityCount($actionRow, fn (array $n): bool => ($n['type'] ?? null) === 'pressable'))->toBe(6)
        ->and(parityCount($actionRow, fn (array $n): bool => ($n['type'] ?? null) === 'rect'))->toBe(5);

    expect($actionRow['layout']['padding'][0] ?? null)->toEqual(12.0);
});

it('offers the Show more toggle only past the 972 character answer budget', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $atBudget = str_repeat('a', 972);
    $overBudget = str_repeat('b', 1200);

    parityFakeFeed([
        parityPost($atBudget, ['id' => 'q1', 'content' => null, 'is_update' => true]),
        parityPost($overBudget, ['id' => 'q2', 'content' => null, 'is_update' => true]),
    ]);

    $test = Native::test(Home::class)
        ->assertSee($atBudget)
        ->assertDontSee($overBudget)
        ->assertSee('Show more');

    expect(parityCount($test->tree(), fn (array $n): bool => ($n['props']['text'] ?? null) === 'Show more'))->toBe(1);
});

it('renders the pinned chip in the question header', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('A pinnable answer.', ['pinned' => true])]);

    $tree = Native::test(Home::class)
        ->assertSee('A pinnable answer.')
        ->assertSee('Pinned')
        ->tree();

    $order = parityOrder($tree);
    $pinned = parityByText($tree, 'Pinned');
    $question = parityByText($tree, 'What are you building?');

    expect($pinned)->not->toBeNull()
        ->and($question)->not->toBeNull();

    expect(array_search($pinned, $order, true))
        ->toBeLessThan(array_search($question, $order, true));

    $chip = parityParent($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinned');

    expect($chip['type'] ?? null)->toBe('row')
        ->and($chip['style']['bg_color'] ?? null)->toBe('#F1F5F9')
        ->and($chip['style']['border_radius'] ?? null)->toEqual(9999.0)
        ->and($pinned['props']['color'] ?? null)->toBe('#475569')
        ->and($pinned['props']['dark_color'] ?? null)->toBe('#94A3B8')
        ->and($pinned['props']['font_weight'] ?? null)->toBe(4);

    $icons = array_values(array_filter(
        $chip['children'] ?? [],
        fn (array $child): bool => ($child['type'] ?? null) === 'icon'
    ));

    expect($icons)->toHaveCount(1)
        ->and($icons[0]['props']['size'] ?? null)->toEqual(16.0)
        ->and($icons[0]['props']['color'] ?? null)->toBe('#64748B')
        ->and($icons[0]['props']['dark_color'] ?? null)->toBe('#94A3B8');

    $pinnedChain = parityAncestors($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinned');

    $pinnedGroup = $pinnedChain === null ? null : $pinnedChain[count($pinnedChain) - 2];

    expect($pinnedGroup)->not->toBeNull()
        ->and(parityCount($pinnedGroup, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinned'))->toBe(1);
});

it('puts post translation actions in the options menu', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Translatable answer.')]);

    $tree = Native::test(Home::class)
        ->call('openMenu', 'q1')
        ->assertSee('Translate answer')
        ->assertSee('Translate question')
        ->tree();

    $translateAnswer = parityByA11y($tree, 'Translate answer');
    $translateQuestion = parityByA11y($tree, 'Translate question');
    $actionRow = parityParent($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Share this post');

    expect($translateAnswer)->not->toBeNull()
        ->and($translateQuestion)->not->toBeNull()
        ->and(in_array($translateAnswer, $actionRow['children'] ?? [], true))->toBeFalse()
        ->and(in_array($translateQuestion, $actionRow['children'] ?? [], true))->toBeFalse();
});

it('renders body text in the web body tone with the web line heights', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('The answer body copy.')]);

    $tree = Native::test(Home::class)->assertSee('The answer body copy.')->tree();

    $question = parityByText($tree, 'What are you building?');

    expect($question['props']['font_size'])->toEqual(14.0)
        ->and($question['props']['line_height'])->toEqual(2.0)
        ->and($question['props']['color'])->toBe('#334155')
        ->and($question['props']['dark_color'])->toBe('#E2E8F0');

    $answer = parityByText($tree, 'The answer body copy.');

    expect($answer['props']['font_size'])->toEqual(16.0)
        ->and($answer['props']['line_height'])->toEqual(1.2)
        ->and($answer['props']['color'])->toBe('#334155')
        ->and($answer['props']['dark_color'])->toBe('#E2E8F0');
});

it('shows the full preview image with its title below and no card border', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Linked answer.', [
        'preview' => [
            'url' => 'https://example.test/a',
            'host' => 'example.test',
            'title' => 'A link preview',
            'image' => 'https://example.test/a.png',
        ],
    ])]);

    $tree = Native::test(Home::class)->assertSee('Linked answer.')->assertSee('A link preview')->tree();

    $pressable = parityByA11y($tree, 'Open link: example.test');

    expect($pressable)->not->toBeNull()
        ->and($pressable['on_press'] ?? null)->not->toBeNull();

    $card = $pressable['children'][0];

    expect($card['type'] ?? null)->toBe('column')
        ->and($card['style']['border_width'] ?? null)->toBeNull()
        ->and($card['style']['border_radius'] ?? null)->toEqual(8.0);

    $caption = $card['children'][1];

    expect($caption['layout']['position_type'] ?? null)->not->toBe(1);
    expect($caption['style']['bg_color'])->toBe('#8C9D174D');
    expect($card['children'][0]['props']['fit'])->toBe(1);

    $title = parityByText($card, 'A link preview');

    expect($title['props']['color'])->toBe('#FFFFFF')
        ->and($title['props']['font_weight'])->toEqual(5.0)
        ->and($title['props']['font_size'])->toEqual(12.0);

    expect($card['children'])->toHaveCount(2)
        ->and($card['children'][0]['type'] ?? null)->toBe('image')
        ->and($card['children'][1]['type'] ?? null)->toBe('column')
        ->and(parityCount($card, fn (array $n): bool => ($n['props']['text'] ?? null) === 'example.test'))->toBe(0);
});

it('renders an embed preview as a tappable watch-over-there card', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    parityFakeFeed([parityPost('Watch this.', [
        'preview' => [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'host' => 'www.youtube.com',
            'title' => 'https://www.youtube.com/watch?v=abc123',
            'image' => null,
        ],
    ])]);

    $tree = Native::test(Home::class)->assertSee('Watch this.')->tree();

    $pressable = parityByA11y($tree, 'Open link: www.youtube.com');

    expect($pressable)->not->toBeNull()
        ->and($pressable['on_press'] ?? null)->not->toBeNull();

    $card = $pressable['children'][0];

    expect($card['type'] ?? null)->toBe('column')
        ->and(parityByText($card, 'www.youtube.com'))->not->toBeNull()
        ->and(parityByText($card, 'https://www.youtube.com/watch?v=abc123'))->not->toBeNull()
        ->and(parityCount($card, fn (array $n): bool => ($n['type'] ?? '') === 'image'))->toBe(0)
        ->and(parityCount($card, fn (array $n): bool => ($n['type'] ?? '') === 'icon'))->toBe(1);
});

it('drops the border from answer images and rounds them like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Look at this.', [
        'images' => ['https://example.test/a.png'],
    ])]);

    $tree = Native::test(Home::class)->assertSee('Look at this.')->tree();

    $image = parityFind($tree, fn (array $n): bool => ($n['props']['src'] ?? null) === 'https://example.test/a.png');

    expect($image)->not->toBeNull()
        ->and($image['props']['fit'])->toBe(1)
        ->and($image['style']['border_width'] ?? null)->toBeNull()
        ->and($image['style']['border_radius'])->toEqual(12.0);
});

it('gives uploaded images priority over a stored link preview', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    parityFakeFeed([parityPost('An image and a link.', [
        'images' => ['https://example.test/upload.png'],
        'preview' => ['url' => 'https://example.test/article', 'host' => 'example.test', 'image' => 'https://example.test/preview.png'],
    ])]);

    $screen = Native::test(Home::class);

    $screen->assertDontSee('Open link: example.test');
    expect(parityFind($screen->tree(), fn (array $n): bool => ($n['props']['src'] ?? null) === 'https://example.test/upload.png'))->not->toBeNull();
    expect(parityFind($screen->tree(), fn (array $n): bool => ($n['props']['src'] ?? null) === 'https://example.test/preview.png'))->toBeNull();
});

it('keeps multiple images in an ordered horizontal gallery without cropping them', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    parityFakeFeed([parityPost('Two images.', ['images' => ['https://example.test/one.png', 'https://example.test/two.png']])]);

    $screen = Native::test(Home::class)->assertSee('Swipe to see all 2 images');
    $gallery = parityFind($screen->tree(), fn (array $n): bool => ($n['type'] ?? null) === 'list' && ($n['props']['horizontal'] ?? false));

    expect($gallery['children'])->toHaveCount(2);
    expect($gallery['children'][0]['children'][0]['props'])->toMatchArray(['src' => 'https://example.test/one.png', 'fit' => 1]);
    expect($gallery['children'][1]['children'][0]['props'])->toMatchArray(['src' => 'https://example.test/two.png', 'fit' => 1]);
});

it('insets the asker box and the avatar lane 8px like the web per-thread padding', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Inset answer.')]);

    $tree = Native::test(Home::class)->assertSee('Inset answer.')->tree();

    $impression = parityAncestor(
        $tree,
        fn (array $n): bool => ($n['type'] ?? null) === 'impression',
        fn (array $n): bool => ($n['props']['text'] ?? null) === 'Inset answer.'
    );
    $row = $impression['children'][0] ?? null;

    expect($row)->not->toBeNull();

    $insets = [$row['children'][0], $row['children'][1]['children'][0]];

    expect($insets)->toHaveCount(2);

    foreach ($insets as $inset) {
        expect($inset['layout']['padding'][1])->toEqual(8.0)
            ->and($inset['layout']['padding'][3])->toEqual(8.0);
    }
});

it('gates the verified seal on is_verified alone like the web', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    parityFakeFeed([parityPost('Company answer.', [
        'to' => ['name' => 'Pinkary Inc', 'username' => 'pinkary', 'company_verified' => true],
    ])]);

    $tree = Native::test(Home::class)->assertSee('Company answer.')->tree();

    $nameRow = parityParent($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinkary Inc');

    expect($nameRow)->not->toBeNull()
        ->and(parityCount($nameRow, fn (array $n): bool => ($n['type'] ?? null) === 'icon'))->toBe(0);
});
