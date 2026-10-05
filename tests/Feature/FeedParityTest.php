<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Illuminate\View\ComponentSlot;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeElementCollector;
use Native\Mobile\Edge\TailwindParser;
use Native\Mobile\Testing\Native;

function renderTree(string $component, array $data): Element
{
    NativeElementCollector::reset();
    NativeElementCollector::setStreaming(false);

    view($component, $data + ['slot' => new ComponentSlot])->render();

    $element = NativeElementCollector::collect();

    NativeElementCollector::reset();

    return $element;
}

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

function markupOf(string $component): string
{
    $source = (string) file_get_contents(resource_path("views/{$component}.blade.php"));

    $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    $source = implode("\n", array_filter(
        explode("\n", $source),
        fn (string $line): bool => ! str_starts_with(mb_trim($line), '//'),
    ));

    return $source;
}

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
        ->assertNavigatedTo('/login');

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/poll/vote'));
});

it('abbreviates comments and views at precision 0 and likes at precision 1', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    parityFakeFeedPost(parityPollPost([
        'metrics' => ['likes' => 1500, 'comments' => 2300000, 'bookmarks' => 2500000],
        'views' => 1000,
    ]));

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
        ->toContain('text-[13.1px]')
        ->not->toContain('text-xs')
        ->toContain(':size="20"')
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

    expect($markup)
        ->toContain('text-red-500')
        ->toContain('dark-color="#F87171"')
        ->not->toContain('text-theme-destructive')
        ->toContain('pt-2.5');
});

it('puts the poll check beside the percentage and fills mine at 90 percent', function (): void {
    $markup = markupOf('components/post-poll-option');

    expect($markup)
        ->toContain('bg-theme-primary/90')
        ->not->toContain('bg-theme-poll-fill" style="width')
        ->toContain('text-theme-on-body');

    expect(mb_strpos($markup, 'CheckmarkCircleFill'))
        ->toBeGreaterThan(mb_strpos($markup, '{{ $text }}'));
});

it('gates the author badge on verified alone like the web', function (): void {
    $markup = markupOf('components/post-author');

    expect($markup)
        ->toContain("@if (data_get(\$author, 'verified'))")
        ->not->toContain('company_verified');
});

it('draws the divider rails at the web heights', function (): void {
    $markup = markupOf('components/post-divider');

    expect($markup)
        ->toContain('bg-theme-outline my-1 h-1.5 w-0.5 shrink-0')
        ->toContain('bg-theme-outline -mt-1 h-2 w-0.5 shrink-0')
        ->toContain('bg-theme-outline -my-1 h-8 w-0.5 shrink-0');
});

it('keeps the list footer paging state a bare indicator with no loading label', function (): void {
    $markup = markupOf('components/list-footer');

    expect($markup)
        ->toContain('native:activity-indicator')
        ->not->toContain('Loading')
        ->toContain('text-theme-on-surface-variant text-center text-base');
});

it('renders the web empty state without a card, border or background', function (): void {
    $markup = markupOf('components/empty-state');

    expect($markup)
        ->toContain('items-center gap-2 px-4')
        ->not->toContain('rounded-md')
        ->not->toContain('bg-theme-surface')
        ->not->toContain('border')
        ->not->toContain('border-dashed');
});

function renderComponent(string $component, array $data, string $slot = ''): string
{
    return view($component, $data + ['slot' => new ComponentSlot($slot)])->render();
}

it('renders the empty state with a default py-5 and an overridable padding prop', function (): void {
    $default = renderTree('components.empty-state', [
        'title' => 'Nothing here.',
        'subtitle' => 'Try another tab.',
    ]);

    expect($default->getLayout()['padding'])->toEqual([20, 16, 20, 16]);

    $title = findNode($default, 'text', 'Nothing here.');

    expect($title)->toBeInstanceOf(Element::class)
        ->and($title?->getResolvedProps(new CallbackRegistry)['text_align'])->toBe(1);

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

    expect($layout)
        ->not->toHaveKey('background')
        ->not->toHaveKey('border_width')
        ->not->toHaveKey('border_radius')
        ->and($layout['align_items'])->toBe(1);
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
        ->toContain('bg-theme-outline-variant h-4 w-4 rounded-full')
        ->toContain('bg-theme-surface-variant h-2 w-16 rounded')
        ->toContain('bg-theme-surface-variant h-2 w-20 rounded')
        ->toContain('bg-theme-outline-variant h-2 w-32 rounded')
        ->not->toContain('h-3');

    expect(mb_substr_count($markup, 'bg-theme-outline-variant h-4 w-4 rounded-full'))->toBe(4);
});

it('parses every utility the parity components rely on', function (): void {
    expect(TailwindParser::parse('w-0.5 h-8 bg-theme-outline'))
        ->toHaveKey('width', 2)
        ->and(TailwindParser::parse('min-h-12'))->toHaveKey('minHeight', 48)
        ->and(TailwindParser::parse('pt-2.5'))->toHaveKey('paddingTop', 10)
        ->and(TailwindParser::parse('opacity-60'))->toHaveKey('opacity', 0.6)
        ->and(TailwindParser::parse('w-5/6'))->toHaveKey('width', '83%')
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
