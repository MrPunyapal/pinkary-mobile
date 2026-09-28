<?php

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Home;
use App\NativeComponents\Notifications;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Illuminate\View\ComponentSlot;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeElementCollector;
use Native\Mobile\Edge\TailwindParser;
use Native\Mobile\Testing\Native;

/**
 * Render a Blade component through the real element collector and return the
 * resulting element, so a component's layout and props can be asserted on in
 * isolation without standing up a whole screen. An empty slot is supplied
 * because the component body always echoes `$slot`.
 */
function renderTree(string $component, array $data): Element
{
    NativeElementCollector::reset();
    NativeElementCollector::setStreaming(false);

    view($component, $data + ['slot' => new ComponentSlot])->render();

    $element = NativeElementCollector::collect();

    NativeElementCollector::reset();

    return $element;
}

/** Find the first node of a given type in a rendered tree. */
function findNode(Element $node, string $type, ?string $text = null): ?Element
{
    if ($node->getType() === $type
        && ($text === null || ($node->getResolvedProps(new CallbackRegistry)['text'] ?? null) === $text)) {
        return $node;
    }

    foreach ($node->getChildren() as $child) {
        if (($found = findNode($child, $type, $text)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * A component's markup with Blade comments and PHPDoc stripped, so an
 * assertion about a class name can never accidentally match the prose
 * explaining why that class was chosen.
 */
function markupOf(string $component): string
{
    $source = (string) file_get_contents(resource_path("views/{$component}.blade.php"));

    // Strip Blade {{-- --}} block comments, then /* *-style docblocks, then
    // whole-line `//` comments.
    $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    $source = implode("\n", array_filter(
        explode("\n", $source),
        fn (string $line): bool => ! str_starts_with(trim($line), '//'),
    ));

    return $source;
}

/**
 * Web parity for the shared feed components: the poll, the action row, the
 * empty state, the infinite-scroll footer and the skeleton placeholder.
 */
function parityPollPost(array $overrides = []): array
{
    return array_merge([
        'id' => 'q1',
        'from' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
        'to' => ['name' => 'Pinkary', 'username' => 'pinkary'],
        'content' => 'Pick a framework.',
        'answer' => 'Laravel, of course.',
        'poll' => [
            'expires_at' => now()->addDay()->toIso8601String(),
            'expired' => false,
            'total_votes' => 3,
            'user_vote_option_id' => null,
            'time_remaining' => '2 days',
            'options' => [
                ['id' => 1, 'text' => 'Laravel', 'votes' => 2],
                ['id' => 2, 'text' => 'Rails', 'votes' => 1],
            ],
        ],
        'metrics' => ['likes' => 0, 'comments' => 0, 'liked' => false, 'bookmarked' => false],
        'views' => 0,
    ], $overrides);
}

function parityFakeFeedPost(array $post): void
{
    config(['services.pinkary.url' => 'https://pinkary.test']);

    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [$post]]),
    ]);
}

it('offers a guest the web sign-in link and never a tappable option', function (): void {
    AccountStore::clear();
    parityFakeFeedPost(parityPollPost());

    $test = Native::test(Home::class)
        ->assertSee('Laravel')
        ->assertSee('3 votes')
        ->assertSee('Sign in to vote')
        // The sign-in link replaces the poll option's tap: a guest must
        // never see a control that silently does nothing.
        ->assertDontSee('Vote for Laravel');

    expect($test->tree())->toBeArray();
});

it('keeps poll options tappable for a signed-in viewer without the sign-in link', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeedPost(parityPollPost());

    Native::test(Home::class)
        ->assertSee('Laravel')
        ->assertSee('Vote for Laravel')
        ->assertDontSee('Sign in to vote');
});

it('sends a guest poll tap to login before any api call', function (): void {
    AccountStore::clear();
    parityFakeFeedPost(parityPollPost());

    Native::test(Home::class)
        ->call('votePoll', 'q1', 1)
        ->assertReplacedWith('/login');

    // The redirect is the whole behaviour — no poll/vote request was made.
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/poll/vote'));
});

it('abbreviates comments and views at precision 0 and likes at precision 1', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeedPost(parityPollPost([
        'metrics' => ['likes' => 1500, 'comments' => 2300000, 'bookmarks' => 2500000],
        'views' => 1000,
    ]));

    // Web truth: comments/views go through Number::abbreviate() (precision 0)
    // and likes/bookmarks through the JS helper (precision 1). A stray
    // thousands separator would show up as '2,300,000' — the '.', '' args in
    // the closure exist to prevent exactly that.
    Native::test(Home::class)
        ->assertSee('2M')
        ->assertSee('1K')
        ->assertSee('1.5K')
        ->assertSee('2.5M')
        ->assertDontSee('2,300,000')
        ->assertDontSee('1,000');
});

it('matches the web on the action-row metric size and the 20px ellipsis', function (): void {
    $markup = markupOf('components/post-action');

    expect($markup)
        // livewire/questions/show.blade.php:74 uses text-[0.82rem], which this
        // renderer takes as a bare point value.
        ->toContain('text-[13.1px]')
        ->not->toContain('text-xs')
        // livewire/questions/show.blade.php:363 is an h-5 w-5 (20px) icon.
        ->toContain(':size="20"')
        // #31: the web uses x-heroicon-o-language, a globe.
        ->toContain('Ios::Globe')
        ->toContain('AndroidOutlined::Language')
        ->not->toContain('CharacterBubble');
});

it('renders the on-body token pair used for body copy', function (): void {
    $theme = config('native-ui.theme');

    expect($theme['light']['on-body'])->toBe('#334155')
        ->and($theme['dark']['on-body'])->toBe('#E2E8F0');
});

it('shows the web poll expired tone and the mt-4 poll top pad', function (): void {
    $markup = markupOf('components/post-poll');

    // livewire/questions/poll-voting.blade.php:66 is text-red-500, not the
    // dull theme destructive red.
    expect($markup)
        ->toContain('text-red-500')
        ->toContain('dark-color="#F87171"')
        ->not->toContain('text-theme-destructive')
        // line 1 is `mt-4 space-y-2`.
        ->toContain('pt-2.5');
});

it('puts the poll check beside the percentage and fills mine at 90 percent', function (): void {
    $markup = markupOf('components/post-poll-option');

    // poll-voting.blade.php:44-55 groups check + percent on the right of the
    // label; line 31 fills the voted bar with pink at 90%.
    expect($markup)
        ->toContain('bg-theme-primary/90')
        ->not->toContain('bg-theme-poll-fill" style="width')
        ->toContain('text-theme-on-body');

    // The check icon must come after the label text in document order.
    expect(strpos($markup, 'CheckmarkCircleFill'))
        ->toBeGreaterThan(strpos($markup, '{{ $text }}'));
});

it('gates the author badge on verified alone like the web', function (): void {
    $markup = markupOf('components/post-author');

    // avatar-with-name.blade.php:19-23 — company_verified only changes the
    // badge art, it never grants the badge.
    expect($markup)
        ->toContain("@if (data_get(\$author, 'verified'))")
        ->not->toContain('company_verified');
});

it('draws the divider rails at the web heights', function (): void {
    $markup = markupOf('components/post-divider');

    // components/post-divider.blade.php:7 min-h-12 dotted rail spanning 48px,
    // and :22-25 the plain h-8 solid rail.
    expect($markup)
        ->toContain('h-1.5 bg-theme-outline shrink-0 my-1')
        ->toContain('h-2 bg-theme-outline shrink-0 -mt-1')
        ->toContain('w-0.5 h-8 bg-theme-outline shrink-0 -my-1');
});

it('keeps the list footer paging state a bare indicator with no loading label', function (): void {
    $markup = markupOf('components/list-footer');

    expect($markup)
        ->toContain('native:activity-indicator')
        ->not->toContain('Loading')
        // The end-of-list copy carries no size class on the web, so it is 16px.
        ->toContain('text-base text-center text-theme-on-surface-variant');
});

it('renders the web empty state without a card, border or background', function (): void {
    $markup = markupOf('components/empty-state');

    expect($markup)
        ->toContain('items-center gap-2 px-4')
        ->not->toContain('rounded-md')
        ->not->toContain('bg-theme-surface')
        ->not->toContain('border')
        // The `border-dashed` class never parsed, so the old markup shipped a
        // solid box the web never shows.
        ->not->toContain('border-dashed');
});

/**
 * Render an anonymous Blade component with an explicit (possibly empty) slot.
 * The collector buffers native:text into the wire tree rather than echoing it,
 * so assertions read the rendered markup, not the collector's stream.
 */
function renderComponent(string $component, array $data, string $slot = ''): string
{
    return view($component, $data + ['slot' => new ComponentSlot($slot)])->render();
}

it('renders the empty state with a default py-5 and an overridable padding prop', function (): void {
    $default = renderTree('components.empty-state', [
        'title' => 'Nothing here.',
        'subtitle' => 'Try another tab.',
    ]);

    // py-5 / px-4 land as [top, right, bottom, left] in points.
    expect($default->getLayout()['padding'])->toEqual([20, 16, 20, 16]);

    $title = findNode($default, 'text', 'Nothing here.');

    expect($title)->toBeInstanceOf(Element::class)
        ->and($title?->getResolvedProps(new CallbackRegistry)['text_align'])->toBe(1);

    // The following/trending tabs sit at py-8 on the web, so those screens
    // can pass the web's own spacing without editing this component.
    expect(renderTree('components.empty-state', [
        'title' => 'Your following feed is empty.',
        'padding' => 'py-8',
    ])->getLayout()['padding'])->toEqual([32, 16, 32, 16]);
});

it('renders the empty state without a card surface, border or fill', function (): void {
    $layout = renderTree('components.empty-state', [
        'title' => 'No bookmarks yet.',
        'subtitle' => 'Posts you save for later will show up here.',
    ])->getLayout();

    // The old markup was a rounded, filled, solid-bordered card (the
    // border-dashed class never parsed). The web shows plain centred copy.
    expect($layout)
        ->not->toHaveKey('background')
        ->not->toHaveKey('border_width')
        ->not->toHaveKey('border_radius')
        ->and($layout['align_items'])->toBe(1);
});

it('still renders the empty state slot for its existing consumers', function (): void {
    // native/bookmarks.blade.php and native/notifications.blade.php pass a
    // "Back to Feed" pressable through the slot. The slot is exercised
    // through those real screens, because a slot's content is a pre-compiled
    // string and so never passes back through the element collector.
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/bookmarks*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/notifications*' => Http::response([
            'data' => [],
            'meta' => ['unread_count' => 0],
        ]),
    ]);

    Native::test(Bookmarks::class)
        ->assertSee('No bookmarks yet.')
        ->assertSee('Posts you save for later will show up here.')
        ->assertSee('Back to Feed');

    Native::test(Notifications::class)
        ->assertSee('No pending notifications.')
        ->assertSee('Back to Feed');
});

it('shows the empty state on the real feed screens with its title and subtitle', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => []])]);

    Native::test(Home::class)
        ->assertSee('There are no questions to show.')
        ->assertSee('Try switching to another feed view')
        ->call('showFollowing')
        ->assertSee('Your following feed is empty.')
        ->call('showTrending')
        ->assertSee('There are no trending questions right now.');
});

it('rebuilds the skeleton placeholder to the web shape', function (): void {
    $markup = markupOf('components/skeleton-post');

    expect($markup)
        // The action row of three dots on the left, a bar and a dot on the right.
        ->toContain('w-4 h-4 rounded-full')
        ->toContain('w-16 h-2')
        // h-2 bars, not the old h-3.
        ->toContain('w-20 h-2')
        ->toContain('w-32 h-2')
        ->not->toContain('h-3');

    // Three action dots plus the trailing right-hand dot.
    expect(substr_count($markup, 'w-4 h-4 rounded-full'))->toBe(4);
});

it('parses every utility the parity components rely on', function (): void {
    // Guards the renderer contract the markup depends on: no dropped class.
    expect(TailwindParser::parse('w-0.5 h-8 bg-theme-outline'))
        ->toHaveKey('width', 2)
        ->and(TailwindParser::parse('min-h-12'))->toHaveKey('minHeight', 48)
        ->and(TailwindParser::parse('pt-2.5'))->toHaveKey('paddingTop', 10)
        ->and(TailwindParser::parse('opacity-60'))->toHaveKey('opacity', 0.6)
        ->and(TailwindParser::parse('w-5/6'))->toHaveKey('width', '83%')
        // Arbitrary text size takes a bare point value, so 0.82rem → 13.12.
        ->and(TailwindParser::parse('text-[13.1px]'))->toHaveKey('fontSize', 13.1)
        ->and(TailwindParser::parse('text-red-500'))->toHaveKey('color', '#EF4444')
        ->and(TailwindParser::parse('bg-theme-primary/90'))->toHaveKey('bg')
        ->and(TailwindParser::parse('text-base'))->toHaveKey('fontSize', 16);
});

it('reports whether the home feed has a signed-in viewer', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => []])]);

    AccountStore::clear();

    expect(Native::test(Home::class)->instance()->signedIn())->toBeFalse();

    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    expect(Native::test(Home::class)->instance()->signedIn())->toBeTrue();
});
