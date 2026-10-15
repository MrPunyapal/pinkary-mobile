<?php

declare(strict_types=1);

use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\Register;
use App\Support\AccountStore;
use App\Support\DraftStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    DraftStore::clear();
    AsyncTask::fake();
    Http::preventStrayRequests();
});

/** @return array<string, mixed> */
function mobileCaptchaMetadata(bool $required = true): array
{
    return ['data' => ['required' => $required, 'widget_path' => '/api/v1/captcha/widget', 'return_path' => '/api/v1/captcha/result']];
}

function completedCaptchaUrl(TestableComponent $screen, string $token = 'captcha-token', string $status = 'success'): string
{
    return 'https://pinkary.test/api/v1/captcha/result?'.http_build_query([
        'state' => $screen->get('captchaState'), 'status' => $status,
    ]).'#'.rawurlencode($token);
}

function captchaRegisterForm(): TestableComponent
{
    return Native::test(Register::class)
        ->set('name', 'Pinkary User')->set('username', 'pinkaryuser')->set('email', 'pinkary@example.com')
        ->set('password', 'password')->set('passwordConfirmation', 'password')->set('terms', true);
}

it('renders a loading indicator without waiting for captcha metadata', function (): void {
    AsyncTask::clearFake();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);

    Native::test(Register::class)->assertSet('captchaLoading', true)->assertSee('Loading security check');

    Http::assertNothingSent();
});

it('waits for registration captcha requirements before submitting', function (): void {
    AsyncTask::clearFake();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);

    captchaRegisterForm()->call('createAccount')
        ->assertSet('captchaLoading', true)
        ->assertSet('busy', false);

    Http::assertNothingSent();
});

it('embeds only the hosted security challenge with JavaScript and storage enabled', function (): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata())]);

    $screen = Native::test(Register::class)->assertSet('captchaRequired', true)->assertSet('captchaLoading', false);

    expect($screen->get('captchaUrl'))->toStartWith('https://pinkary.test/api/v1/captcha/widget?action=register&state=');
    $nodes = collect(captchaTreeNodes($screen->tree()))->where('type', 'webview')->values();
    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['props'])->toMatchArray(['javascript' => true, 'dom_storage' => true]);
});

/** @return list<array<string, mixed>> */
function captchaTreeNodes(array $node): array
{
    $nodes = [$node];
    foreach ($node['children'] ?? [] as $child) {
        $nodes = [...$nodes, ...captchaTreeNodes($child)];
    }

    return $nodes;
}

it('keeps the registration form when the security check is not completed', function (): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata())]);

    captchaRegisterForm()->call('createAccount')->assertSet('name', 'Pinkary User')
        ->assertSet('busy', false)->assertSee('Please complete the security check before submitting.');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('submits the verified token and state without putting the token in a request URL', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata()),
        'https://pinkary.test/api/v1/auth/register' => Http::response(['token' => 'account-token', 'data' => ['username' => 'pinkaryuser']], 201),
    ]);
    $screen = captchaRegisterForm();
    $state = $screen->get('captchaState');

    $screen->call('captchaNavigated', completedCaptchaUrl($screen))
        ->assertSet('captchaCompleted', true)->assertSet('captchaUrl', '')
        ->call('createAccount')->assertReplacedWith('/feed');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['cf-turnstile-response'] === 'captcha-token'
        && $request['captcha_state'] === $state && ! str_contains($request->url(), 'captcha-token'));
    expect(AccountStore::activeToken())->toBe('account-token');
});

it('ignores callbacks from an untrusted origin or stale challenge', function (string $template): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata())]);
    $screen = captchaRegisterForm();
    $url = str_replace('{state}', $screen->get('captchaState'), $template);

    $screen->call('captchaNavigated', $url)->assertSet('captchaCompleted', false)->call('createAccount');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
})->with([
    'wrong hostname' => 'https://evil.test/api/v1/captcha/result?state={state}&status=success#token',
    'wrong scheme' => 'http://pinkary.test/api/v1/captcha/result?state={state}&status=success#token',
    'wrong port' => 'https://pinkary.test:8443/api/v1/captcha/result?state={state}&status=success#token',
    'wrong path' => 'https://pinkary.test/elsewhere?state={state}&status=success#token',
    'wrong state' => 'https://pinkary.test/api/v1/captcha/result?state=stale&status=success#token',
    'missing token' => 'https://pinkary.test/api/v1/captcha/result?state={state}&status=success',
    'control characters' => 'https://pinkary.test/api/v1/captcha/result?state={state}&status=success#bad%0atoken',
    'oversized token' => 'https://pinkary.test/api/v1/captcha/result?state={state}&status=success#'.str_repeat('a', 2049),
]);

it('refreshes failed challenges without losing entered fields', function (string $status): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata())]);
    $screen = captchaRegisterForm();
    $state = $screen->get('captchaState');

    $screen->call('captchaNavigated', completedCaptchaUrl($screen, status: $status))
        ->assertSet('captchaCompleted', false)->assertSet('username', 'pinkaryuser')->assertSee('expired or failed');

    expect($screen->get('captchaState'))->not->toBe($state);
})->with(['expired', 'error', 'timeout']);

it('expires solved tokens before reuse and preserves the form', function (): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata())]);
    $screen = captchaRegisterForm();
    $screen->call('captchaNavigated', completedCaptchaUrl($screen));
    $this->travel(241)->seconds();

    $screen->call('createAccount')->assertSet('captchaCompleted', false)->assertSet('email', 'pinkary@example.com');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('replaces a consumed challenge after server rejection or network failure', function (bool $networkFailure): void {
    Http::fake([
        'https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata()),
        'https://pinkary.test/api/v1/auth/register' => $networkFailure ? Http::failedConnection() : Http::response([
            'errors' => ['cf-turnstile-response' => ['Security check rejected.']],
        ], 422),
    ]);
    $screen = captchaRegisterForm();
    $state = $screen->get('captchaState');

    $screen->call('captchaNavigated', completedCaptchaUrl($screen))->call('createAccount')
        ->assertSet('captchaCompleted', false)->assertSet('busy', false)->assertSet('name', 'Pinkary User');

    expect($screen->get('captchaState'))->not->toBe($state);
    expect(AccountStore::activeToken())->toBeNull();
})->with(['server rejection' => false, 'offline' => true]);

it('does not display a challenge when the API does not require it', function (): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata(false))]);

    Native::test(Register::class)->assertSet('captchaRequired', false)->assertSet('captchaUrl', '')
        ->assertDontSee('Security check')->assertDontSee('Loading security check');
});

it('offers retry after metadata errors and rejects unsafe widget URLs', function (mixed $response): void {
    Http::fake(['https://pinkary.test/api/v1/captcha*' => Http::response($response, 200)]);

    Native::test(Register::class)->assertSet('captchaUrl', '')->assertSet('captchaLoading', false)->assertSee('Retry security check');
})->with([
    'missing metadata' => [[]],
    'cross-origin widget' => [['data' => ['required' => true, 'widget_path' => '//evil.test/widget', 'return_path' => '/api/v1/captcha/result']]],
]);

it('does not fetch or show captcha when the API accepts a post without it', function (): void {
    AccountStore::add('ada', 'account-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response(['data' => []], 201),
    ]);

    Native::test(Compose::class)->set('content', 'Public thread.')
        ->assertSet('captchaRequired', false)
        ->assertSet('captchaLoading', false)
        ->assertDontSee('Loading security check')
        ->call('publish')
        ->assertNavigatedTo('/feed');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/captcha'));
});

it('loads captcha only after the API requires it for a thread', function (): void {
    AccountStore::add('ada', 'account-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata()),
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::sequence()
            ->push(['errors' => ['cf-turnstile-response' => ['Required.']]], 422)
            ->push(['data' => []], 201),
    ]);
    $screen = Native::test(Compose::class)->set('content', 'Protected thread.')
        ->assertSet('captchaRequired', false)
        ->call('publish')
        ->assertSet('captchaRequired', true)
        ->assertSet('captchaLoading', false)
        ->assertSee('Security check');

    expect($screen->get('captchaUrl'))->toStartWith('https://pinkary.test/api/v1/captcha/widget?action=post&state=');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions')
        && ! isset($request['cf-turnstile-response']));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/api/v1/captcha')
        && $request['action'] === 'post'
        && $request->hasHeader('Authorization', 'Bearer account-token'));

    $screen->call('captchaNavigated', completedCaptchaUrl($screen))->call('publish')->assertNavigatedTo('/feed');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['content'] === 'Protected thread.'
        && ($request->data()['cf-turnstile-response'] ?? null) === 'captcha-token'
        && $request->hasHeader('Authorization', 'Bearer account-token'));
});

it('shows captcha loading after the API requires a check', function (): void {
    AccountStore::add('ada', 'account-token', ['username' => 'ada']);
    AsyncTask::clearFake();
    Native::fakeBridge()->respondTo('AsyncTask.Dispatch', ['success' => true]);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/channels*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::response([
            'errors' => ['cf-turnstile-response' => ['Required.']],
        ], 422),
    ]);

    Native::test(Compose::class)->set('content', 'Protected update.')->call('publish')
        ->assertSet('captchaLoading', true)
        ->assertSet('busy', false);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/questions')
        && ! isset($request['cf-turnstile-response']));
});

it('also sends the challenge with anonymous questions', function (): void {
    AccountStore::add('ada', 'account-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata()),
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/questions' => Http::sequence()
            ->push(['errors' => ['cf-turnstile-response' => ['Required.']]], 422)
            ->push(['data' => []], 201),
    ]);
    $screen = Native::test(Compose::class, ['username' => 'grace'])->set('content', 'Protected question.')->set('anonymous', true);

    $screen->call('publish')->assertSet('captchaRequired', true)
        ->call('captchaNavigated', completedCaptchaUrl($screen))
        ->call('publish')->assertNavigatedTo('/user/grace');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['to_username'] === 'grace'
        && $request['anonymously'] === true
        && ($request->data()['cf-turnstile-response'] ?? null) === 'captcha-token');
});

it('sends a comment challenge and keeps the reply when it is rejected', function (): void {
    AccountStore::add('ada', 'account-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/questions/q1' => Http::response(['data' => [
            'id' => 'q1', 'from' => ['username' => 'ada'], 'to' => ['username' => 'ada'],
            'answer' => 'Public post.', 'content' => null, 'is_update' => true,
        ]]),
        'https://pinkary.test/api/v1/questions/q1/comments*' => Http::sequence()
            ->push(['data' => [], 'links' => ['next' => null]])
            ->push(['errors' => ['cf-turnstile-response' => ['Required.']]], 422)
            ->push(['data' => ['id' => 'c1', 'answer' => 'Protected reply.']], 201),
        'https://pinkary.test/api/v1/captcha*' => Http::response(mobileCaptchaMetadata()),
        'https://pinkary.test/api/v1/questions/q1/views' => Http::response([], 204),
    ]);
    $screen = Native::test(Detail::class, ['id' => 'q1'])->set('comment', 'Protected reply.');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/captcha'));

    $screen->call('postComment')->assertSet('captchaRequired', true)->assertSet('captchaLoading', false)
        ->assertSet('comment', 'Protected reply.')
        ->assertSee('Security check');

    expect($screen->get('captchaUrl'))->toStartWith('https://pinkary.test/api/v1/captcha/widget?action=comment&state=');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/api/v1/captcha')
        && $request['action'] === 'comment'
        && $request->hasHeader('Authorization', 'Bearer account-token'));

    $screen->call('captchaNavigated', completedCaptchaUrl($screen))
        ->call('postComment')->assertSet('comment', '');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['content'] === 'Protected reply.'
        && ($request->data()['cf-turnstile-response'] ?? null) === 'captcha-token');
});
