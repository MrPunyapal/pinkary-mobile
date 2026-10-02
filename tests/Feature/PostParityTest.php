<?php

use App\NativeComponents\Home;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/**
 * Screen-by-screen parity for the shared post renderer
 * (resources/views/components/post.blade.php + thread-post.blade.php),
 * checked against the web views in E:\projects\contribute\canrun\pinkary.
 *
 * These assert the WIRE tree, not the Blade source: a Tailwind class only
 * counts as parity once TailwindParser has resolved it into the geometry
 * the web asks for. Note that every layout number on the wire is a double.
 */
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

    // Exactly what the API ships: sharable_answer is parsed HTML with
    // <a> markup around the mention and hashtag.
    $answer = 'Shipping <a href="/@ada">@ada</a> and <a href="/hashtag/laravel">#laravel</a> today.';

    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [parityPost($answer)]]),
    ]);

    Native::test(Home::class)
        ->assertSee('Shipping @ada and #laravel today.')
        ->assertDontSee('<a href');
});

/** The first node in document order matching $match. */
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

/**
 * The ancestors of the first matching node, ROOT first and the node that
 * directly wraps the match LAST — so end() is the direct parent.
 */
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

/** The first ancestor on the path that satisfies $match. */
function parityAncestor(array $tree, callable $match, callable $find): ?array
{
    foreach (parityAncestors($tree, $find) ?? [] as $ancestor) {
        if ($match($ancestor)) {
            return $ancestor;
        }
    }

    return null;
}

/** The whole tree flattened in document order. */
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

    // The row container is the ancestor of the answer that carries a
    // border: home/feed.blade.php:2 paints the web column bg-white/80 /
    // dark #07101f and livewire/feed.blade.php:6 draws the row hairline on
    // it. bg-theme-background (#F1F5F9) is the page BEHIND the column.
    $row = parityAncestor(
        $tree,
        fn (array $n): bool => isset($n['style']['border_width']),
        fn (array $n): bool => ($n['props']['text'] ?? null) === 'A single-post row.'
    );

    expect($row)->not->toBeNull()
        ->and($row['style']['bg_color'])->toBe('#FFFFFF')
        ->and($row['style']['bg_color'])->not->toBe('#F1F5F9')
        ->and($row['props']['dark_bg_color'] ?? null)->toBe('#07101F')
        ->and($row['style']['border_color'] ?? null)->toBe('#E2E8F0')
        ->and($row['style']['border_width'])->toEqual(1.0);
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

    // thread-post.blade.php paints the whole thread on the web column's
    // white, not the slate page background.
    $containers = array_values(array_filter(
        parityOrder($tree),
        fn (array $n): bool => ($n['style']['bg_color'] ?? null) === '#FFFFFF'
            && ($n['props']['dark_bg_color'] ?? null) === '#07101F'
    ));

    expect($containers)->not->toBeEmpty();

    foreach ($containers as $container) {
        expect($container['style']['bg_color'])->not->toBe('#F1F5F9');
    }

    // The thread ancestor and the root both sit inside that white thread.
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

    // press() fails when no element registered the expression, so this
    // proves the ••• pressable is on the row and wired to the screen menu.
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

    // questions/show.blade.php:237-245 draws the chart unconditionally and
    // conditions only the count. The views action is the one metric row in
    // the action row that is NOT a pressable and carries no text.
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

    // Six metrics sit in the row (comments, like, views, translate,
    // bookmark, share) with five separator dots between them — the old
    // zero-views row had only four.
    expect(parityCount($actionRow, fn (array $n): bool => ($n['type'] ?? null) === 'pressable'))->toBe(6)
        ->and(parityCount($actionRow, fn (array $n): bool => ($n['type'] ?? null) === 'rect'))->toBe(5);

    // The action row sits 12px (the web's mt-3) below the text.
    expect($actionRow['layout']['padding'][0] ?? null)->toEqual(12.0);
});

it('offers the Show more toggle only past the 972 character answer budget', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    // 972 characters is 27 lines at the 36 characters per line the rail
    // math assumes — the web's 640px / 24px-per-line budget expressed in
    // characters. One post exactly at the budget, one past it.
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

it('renders the pinned chip in the question header next to Translate', function (): void {
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

    // Header row, not a row under the text: the chip is painted before
    // the question paragraph (questions/show.blade.php:47-52).
    expect(array_search($pinned, $order, true))
        ->toBeLessThan(array_search($question, $order, true));

    // The chip is a grey pill: rounded-full on the surface-variant fill,
    // muted medium-weight text, and a slate pin icon at 16 — NOT pink.
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

    // The two chips share one trailing header group: the group holding
    // Translate is the same node that holds Pinned.
    $translateChain = parityAncestors($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Translate question');
    $pinnedChain = parityAncestors($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinned');

    $translateGroup = $translateChain === null ? null : $translateChain[count($translateChain) - 2];
    $pinnedGroup = $pinnedChain === null ? null : $pinnedChain[count($pinnedChain) - 2];

    expect($translateGroup)->not->toBeNull()
        ->and($pinnedGroup)->toBe($translateGroup)
        ->and(parityCount($translateGroup, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinned'))->toBe(1)
        ->and(parityCount($translateGroup, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Translate question'))->toBe(1);
});

it('wraps the question Translate action in a web pill chip', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Translatable question.')]);

    $tree = Native::test(Home::class)->assertSee('Translatable question.')->tree();

    $pill = parityParent($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Translate question');

    expect($pill['type'] ?? null)->toBe('row')
        ->and($pill['style']['bg_color'] ?? null)->toBe('#F1F5F9')
        ->and($pill['style']['border_radius'] ?? null)->toEqual(9999.0)
        ->and($pill['layout']['padding'] ?? null)->toEqual([6.0, 10.0, 6.0, 10.0]);

    // The answer's own Translate stays a bare icon, a direct child of the
    // action row with no chip between (questions/show:249-257).
    $actionRow = parityParent($tree, fn (array $n): bool => ($n['props']['a11y_label'] ?? null) === 'Share this post');

    expect(in_array(
        parityByA11y($tree, 'Translate answer'),
        $actionRow['children'] ?? [],
        true
    ))->toBeTrue();
});

it('renders body text in the web body tone with the web line heights', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('The answer body copy.')]);

    $tree = Native::test(Home::class)->assertSee('The answer body copy.')->tree();

    // questions/show.blade.php:65 — text-sm leading-7 over slate-700 /
    // slate-200. leading-7 is dropped by the parser, so leading-loose
    // (2.0 x 14px) reproduces the 28px. The tone is the `on-body` token
    // (#334155 / #E2E8F0), which is the web's slate-700/slate-200.
    $question = parityByText($tree, 'What are you building?');

    expect($question['props']['font_size'])->toEqual(14.0)
        ->and($question['props']['line_height'])->toEqual(2.0)
        ->and($question['props']['color'])->toBe('#334155')
        ->and($question['props']['dark_color'])->toBe('#E2E8F0');

    // questions/show.blade.php:166-171 applies no leading class at all, so
    // the browser default ~1.2 governs.
    $answer = parityByText($tree, 'The answer body copy.');

    expect($answer['props']['font_size'])->toEqual(16.0)
        ->and($answer['props']['line_height'])->toEqual(1.2)
        ->and($answer['props']['color'])->toBe('#334155')
        ->and($answer['props']['dark_color'])->toBe('#E2E8F0');
});

it('draws the link preview title as a bottom overlay with no card border', function (): void {
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

    // The whole card is tappable, and the card is the one column inside.
    $pressable = parityByA11y($tree, 'Open link: example.test');

    expect($pressable)->not->toBeNull()
        ->and($pressable['on_press'] ?? null)->not->toBeNull();

    $card = $pressable['children'][0];

    expect($card['type'] ?? null)->toBe('column')
        ->and($card['style']['border_width'] ?? null)->toBeNull()
        ->and($card['style']['border_radius'] ?? null)->toEqual(8.0);

    // The title is an absolutely positioned bottom overlay tinted
    // pink-800/55 (#8C9D174D on the wire), and the host line is gone —
    // it lives in the a11y label only.
    $overlay = parityFind($card, fn (array $n): bool => ($n['layout']['position_type'] ?? null) === 1);

    expect($overlay)->not->toBeNull()
        ->and($overlay['style']['bg_color'])->toBe('#8C9D174D')
        ->and($overlay['layout']['position'])->toEqual([0, -0, -0, -0])
        ->and($overlay['layout']['padding'] ?? null)->toEqual([4.0, 8.0, 4.0, 8.0]);

    $title = parityByText($card, 'A link preview');

    expect($title['props']['color'])->toBe('#FFFFFF')
        ->and($title['props']['font_weight'])->toEqual(5.0)
        ->and($title['props']['font_size'])->toEqual(12.0);

    // The image is the card's only other child — no bubble strip, and no
    // host text node anywhere under the card.
    expect($card['children'])->toHaveCount(2)
        ->and($card['children'][0]['type'] ?? null)->toBe('image')
        ->and($card['children'][1]['type'] ?? null)->toBe('column')
        ->and(parityCount($card, fn (array $n): bool => ($n['props']['text'] ?? null) === 'example.test'))->toBe(0);
});

it('renders an embed preview as a tappable watch-over-there card', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    // A YouTube/Vimeo link takes the web's `html` branch: the stored card
    // holds a provider iframe, so QuestionResource finds no <img> and no
    // <h3> and falls the title back to the URL. The app cannot render an
    // iframe, so it must still offer the link honestly.
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

    // Host first, then the URL, so the card never renders as a bare
    // unlabelled box. A play glyph marks it as a video.
    expect($card['type'] ?? null)->toBe('column')
        ->and(parityByText($card, 'www.youtube.com'))->not->toBeNull()
        ->and(parityByText($card, 'https://www.youtube.com/watch?v=abc123'))->not->toBeNull()
        // No image node: there is no thumbnail to load.
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
        ->and($image['style']['border_width'] ?? null)->toBeNull()
        ->and($image['style']['border_radius'])->toEqual(12.0);
});

it('insets the asker box and the avatar lane 8px like the web per-thread padding', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeed([parityPost('Inset answer.')]);

    $tree = Native::test(Home::class)->assertSee('Inset answer.')->tree();

    // livewire/feed.blade.php:6 wraps every thread in px-2, so the asker
    // box and the avatar-lane row must pad 8px, not the old 16px.
    // The lane now lives in a relative wrapper so its avatar can
    // overlay the content-sized connector.
    $row = parityAncestor(
        $tree,
        fn (array $n): bool => isset($n['style']['border_width']),
        fn (array $n): bool => ($n['props']['text'] ?? null) === 'Inset answer.'
    );

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

    // A company-verified account that is not profile-verified earns no
    // seal (questions/show.blade.php:124-133).
    parityFakeFeed([parityPost('Company answer.', [
        'to' => ['name' => 'Pinkary Inc', 'username' => 'pinkary', 'company_verified' => true],
    ])]);

    $tree = Native::test(Home::class)->assertSee('Company answer.')->tree();

    $nameRow = parityParent($tree, fn (array $n): bool => ($n['props']['text'] ?? null) === 'Pinkary Inc');

    expect($nameRow)->not->toBeNull()
        ->and(parityCount($nameRow, fn (array $n): bool => ($n['type'] ?? null) === 'icon'))->toBe(0);
});
