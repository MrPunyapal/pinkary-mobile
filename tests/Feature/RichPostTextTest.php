<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\Services\PinkaryApi;
use App\Support\DisplayText;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::preventStrayRequests();
});

it('prefers rich API fields while preserving compatibility with plain servers and nested threads', function (): void {
    $post = app(PinkaryApi::class)->normalizeQuestion([
        'answer' => 'First Second [see the code on Pinkary]',
        'answer_html' => 'First<br><br>Second<pre><code>    return true;</code></pre>',
        'content' => '__UPDATE__',
        'is_update' => true,
        'thread' => ['posts' => [['answer' => 'A B', 'answer_html' => 'A<br>B']]],
    ]);

    expect($post['content'])->toBeNull()
        ->and($post['answer'])->toBe('First<br><br>Second<pre><code>    return true;</code></pre>')
        ->and($post['thread']['posts'][0]['answer'])->toBe('A<br>B')
        ->and(app(PinkaryApi::class)->normalizeQuestion(['answer' => "First\nSecond"])['answer'])->toBe("First\nSecond");
});

it('keeps API source text beside the rich fields used for rendering', function (): void {
    $post = app(PinkaryApi::class)->normalizeQuestion([
        'content' => 'Question source text.',
        'content_html' => '<p>Rendered question.</p>',
        'answer' => 'Answer source text.',
        'answer_html' => '<p>Rendered answer.</p>',
    ]);

    expect($post['content'])->toBe('<p>Rendered question.</p>')
        ->and($post['content_source'])->toBe('Question source text.')
        ->and($post['answer'])->toBe('<p>Rendered answer.</p>')
        ->and($post['answer_source'])->toBe('Answer source text.');
});

it('shares and translates source text instead of rich HTML', function (): void {
    $content = "Question with https://example.com/docs.\n\n```php\n\$value = 1;\n```";
    $answer = "Answer with https://example.com/guide.\n\n```php\nreturn true;\n```";
    $contentHtml = '<p>Question with <a href="https://example.com/docs">docs</a>.</p><pre><code>$value = 1;</code></pre>';
    $answerHtml = '<p>Answer with <a href="https://example.com/guide">guide</a>.</p><pre><code>return true;</code></pre>';

    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
        'id' => 'q1',
        'to' => ['name' => 'Ada', 'username' => 'ada'],
        'content' => $content,
        'content_html' => $contentHtml,
        'answer' => $answer,
        'answer_html' => $answerHtml,
    ]]])]);

    $screen = Native::test(Home::class);

    expect($screen->get('posts.0.content'))->toBe($contentHtml)
        ->and($screen->get('posts.0.answer'))->toBe($answerHtml);

    $screen->call('sharePost', 'q1')->call('translatePost', 'q1', 'content');

    $calls = FakeBridge::current()?->calls ?? [];
    $share = collect($calls)->firstWhere('method', 'Share.Url');
    $browser = collect($calls)->firstWhere('method', 'Browser.OpenInApp');
    parse_str((string) parse_url($browser['params']['url'] ?? '', PHP_URL_QUERY), $query);

    expect($share['params']['text'] ?? null)->toBe($answer)
        ->and($query['text'] ?? null)->toBe($content)
        ->and($share['params']['text'] ?? '')->not->toContain('<a', '<pre', '<code')
        ->and($query['text'] ?? '')->not->toContain('<a', '<pre', '<code');
});

it('keeps bare links tappable with their full target and excludes surrounding punctuation', function (string $text, string $label, string $url): void {
    expect(DisplayText::runs($text))->toContain(['text' => $label, 'type' => 'link', 'target' => $url]);
})->with([
    'production example' => ['and more laravel.com/blog/migrating-pinkary-from-laravel-forge-to-laravel-cloud', 'laravel.com/blog/migrating-pinkary-from-laravel-fo...', 'https://laravel.com/blog/migrating-pinkary-from-laravel-forge-to-laravel-cloud'],
    'www' => ['Visit www.example.com/docs.', 'www.example.com/docs', 'https://www.example.com/docs'],
    'parentheses' => ['See (https://example.com/docs).', 'example.com/docs', 'https://example.com/docs'],
    'balanced path' => ['https://example.com/wiki/Thing_(one)', 'example.com/wiki/Thing_(one)', 'https://example.com/wiki/Thing_(one)'],
]);

it('truncates long link labels without changing the target or leaving a partial entity', function (): void {
    $url = 'https://example.com/'.str_repeat('a', 33).'?x=1&amp;b=2';
    $expectedTarget = 'https://example.com/'.str_repeat('a', 33).'?x=1&b=2';
    $runs = DisplayText::runs($url);

    expect($runs)->toContain([
        'text' => 'example.com/'.str_repeat('a', 33).'?x=1...',
        'type' => 'link',
        'target' => $expectedTarget,
    ]);
});

it('does not treat emails as links or mentions', function (): void {
    expect(DisplayText::runs('Email ada@example.com'))->toBe([
        ['text' => 'Email ada@example.com', 'type' => 'text', 'target' => ''],
    ]);
});

it('preserves code indentation and newlines without turning code into links or mentions', function (string $html): void {
    expect(DisplayText::runs($html))->toContain([
        'text' => "    echo '@ada';\n    // https://example.com", 'type' => 'code', 'target' => 'php',
    ]);
    expect(array_filter(DisplayText::runs($html), fn (array $run): bool => in_array($run['type'], ['mention', 'link'], true)))->toBeEmpty();
})->with([
    'HTML' => ['<pre><code class="language-php">    echo \'@ada\';'."\n".'    // https://example.com</code></pre>'],
    'Pinkary highlighted HTML' => ['<pre><code class="p-4 rounded-lg hljs php text-xs">    <span class="hljs-keyword">echo</span> \'@ada\';<br>    // https://example.com</code></pre>'],
    'fenced Markdown' => ["```php\n    echo '@ada';\n    // https://example.com\n```"],
]);

it('preserves literal less-than signs and CRLF line breaks', function (): void {
    expect(DisplayText::plain("1 < 2\r\n\r\n- first\r\n- second"))->toBe("1 < 2\n\n- first\n- second");
});

it('excludes embedded preview cards and dangerous markup from the body', function (): void {
    expect(DisplayText::plain('First<br><br>Second<div id="link-preview-card"><h3>Duplicate title</h3></div><script>hidden()</script><iframe>hidden</iframe>'))->toBe("First\n\nSecond");
    foreach (['javascript:alert(1)', 'data:text/html,hello', 'https://example.com\\evil', 'https://user:secret@example.com'] as $url) {
        expect(DisplayText::url($url))->toBeNull();
    }
});

it('preserves list items and resolves relative or protocol-relative links', function (): void {
    expect(DisplayText::plain('<p>List</p><ul><li>One</li><li>Two</li></ul>'))->toBe("List\n- One\n- Two");
    expect(DisplayText::runs('<a href="//example.com/docs">Docs</a>'))->toBe([['text' => 'Docs', 'type' => 'link', 'target' => 'https://example.com/docs']]);
    expect(DisplayText::runs('<a href="/@ada">@ada</a>'))->toBe([['text' => '@ada', 'type' => 'mention', 'target' => 'ada']]);
});

it('recognizes Pinkary hashtag links in rich post text', function (): void {
    expect(DisplayText::runs('<a href="/hashtag/laravel">#laravel</a>'))->toBe([
        ['text' => '#laravel', 'type' => 'hashtag', 'target' => 'laravel'],
    ]);
});

it('renders rich newlines and a selectable monospace code block through the actual feed', function (): void {
    $code = "    return true;\n\n    // next line";
    Http::fake(['https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
        'id' => 'q1', 'is_update' => true, 'answer' => 'First Second [see code]',
        'answer_html' => 'First<br><br>Second<pre><code class="language-php">'.$code.'</code></pre>',
        'to' => ['name' => 'Ada', 'username' => 'ada'],
    ]]])]);

    Native::test(Home::class)->assertSee('First')->assertSee('Second')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? '') === $code
            && ($node['props']['font_family'] ?? -1) === 2
            && ($node['props']['selectable'] ?? 0) === 1)
        ->assertDontSee('[see code]');
});

it('offers a lightweight link card when metadata is absent without fetching remote metadata', function (): void {
    $post = app(PinkaryApi::class)->normalizeQuestion(['answer' => 'Read laravel.com/blog/example']);
    expect($post['preview'])->toBe(['url' => 'https://laravel.com/blog/example', 'host' => 'laravel.com', 'title' => 'https://laravel.com/blog/example', 'image' => null]);
    Http::assertNothingSent();
});

it('keeps existing API preview metadata', function (): void {
    $preview = ['url' => 'https://example.com', 'title' => 'Article', 'image' => 'https://example.com/image.png'];
    expect(app(PinkaryApi::class)->normalizeQuestion(['answer' => 'https://example.com', 'preview' => $preview])['preview'])->toBe($preview);
});
