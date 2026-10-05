<?php

declare(strict_types=1);

use App\NativeComponents\Home;
use App\NativeComponents\Profile;
use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
});

function ownerCardProfile(array $linkOverrides = []): array
{
    return [
        'id' => 1,
        'name' => 'Pinkary User',
        'username' => 'pinkaryuser',
        'bio' => 'Building in public.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 10, 'following' => 2, 'posts' => 1, 'views' => 5],
        'followed_by_me' => false,
        'is_me' => true,
        'links' => [array_merge([
            'id' => 7,
            'description' => 'Website',
            'url' => 'https://example.com?ref=pinkary',
            'is_visible' => true,
        ], $linkOverrides)],
        'member_since' => ['human' => 'today'],
    ];
}

function otherCardProfile(array $linkOverrides = []): array
{
    return [
        'id' => 2,
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'bio' => 'First programmer.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 3, 'following' => 1, 'posts' => 2, 'views' => 9],
        'followed_by_me' => false,
        'is_me' => false,
        'links' => [array_merge([
            'id' => 9,
            'description' => 'Notes',
            'url' => 'https://ada.test?ref=pinkary',
            'is_visible' => true,
        ], $linkOverrides)],
        'member_since' => ['human' => '2 years ago'],
    ];
}

function fakeCardResponses(string $profilePath, array $profile): void
{
    Http::fake([
        'https://pinkary.test/api/v1/'.$profilePath => Http::response(['data' => $profile]),
        'https://pinkary.test/api/v1/users/*/questions*' => Http::response(['data' => []]),
    ]);
}

it('hides a link from the world with the owner eye toggle', function (): void {
    fakeCardResponses('profile', ownerCardProfile());
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownerCardProfile()]),
        'https://pinkary.test/api/v1/users/*/questions*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/links/7' => Http::sequence()
            ->push(['data' => ['id' => 7, 'is_visible' => false]])
            ->push(['data' => ['id' => 7, 'is_visible' => true]]),
    ]);

    Native::test(Profile::class)
        ->assertSee('Hide link')
        ->press('toggleLinkVisibility(7)')
        ->assertSee('Show link')
        ->press('toggleLinkVisibility(7)')
        ->assertSee('Hide link');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/links/7')
        && $request['is_visible'] === false);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/links/7')
        && $request['is_visible'] === true);
});

it('keeps the visibility eye off other people cards', function (): void {
    fakeCardResponses('users/ada', otherCardProfile());

    Native::test(Profile::class, ['username' => 'ada'])
        ->assertSee('Notes')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array(
            $node['props']['a11y_label'] ?? '',
            ['Hide link', 'Show link'],
            true,
        ));
});

it('refuses to toggle a link that is not yours', function (): void {
    fakeCardResponses('users/ada', otherCardProfile());

    $test = Native::test(Profile::class, ['username' => 'ada'])
        ->call('toggleLinkVisibility', 9);

    expect($test->get('linkError'))->toBe('');

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

it('puts the eye back and explains a failed visibility toggle', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => ownerCardProfile()]),
        'https://pinkary.test/api/v1/users/*/questions*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/links/7' => Http::response(['message' => 'Nope.'], 500),
    ]);

    $test = Native::test(Profile::class)
        ->press('toggleLinkVisibility(7)')
        ->assertSee('Hide link')
        ->assertSee('Nope.');

    expect($test->get('profile')['links'][0]['is_visible'])->toBeTrue()
        ->and($test->get('linkError'))->toBe('Nope.');
});

it('sends a guest tapping the eye to login', function (): void {
    AccountStore::clear();
    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherCardProfile()]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
    ]);

    Native::test(Profile::class, ['username' => 'ada'])
        ->set('profile', array_merge(otherCardProfile(), ['is_me' => true]))
        ->press('toggleLinkVisibility(9)')
        ->assertNavigatedTo('/login');
});

it('opens the avatar sheet from the card and closes it again', function (): void {
    fakeCardResponses('profile', ownerCardProfile());

    $test = Native::test(Profile::class)
        ->assertMissingElement('bottom_sheet', fn (array $node): bool => ($node['props']['visible'] ?? false) === true)
        ->press('showAvatar')
        ->assertElement('bottom_sheet', fn (array $node): bool => ($node['props']['visible'] ?? false) === true)
        ->assertSee('Close')
        ->tap('Close')
        ->assertMissingElement('bottom_sheet', fn (array $node): bool => ($node['props']['visible'] ?? false) === true);

    expect($test->get('showAvatar'))->toBeFalse();
});

it('closes the avatar sheet on drag-down', function (): void {
    fakeCardResponses('profile', ownerCardProfile());

    Native::test(Profile::class)
        ->call('toggleAvatar')
        ->assertElement('bottom_sheet', fn (array $node): bool => ($node['props']['visible'] ?? false) === true)
        ->press('toggleAvatar')
        ->assertMissingElement('bottom_sheet', fn (array $node): bool => ($node['props']['visible'] ?? false) === true);
});

it('shows the follow pill on search result rows like the web', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [[
            'id' => 2,
            'name' => 'Ada Lovelace',
            'username' => 'ada',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'followed_by_me' => true,
            'is_me' => false,
        ]], 'hashtags' => []]]),
        'https://pinkary.test/api/v1/users/ada/follow' => Http::response(['data' => ['followed' => false, 'followers' => 2]]),
    ]);

    $test = Native::test(Search::class)
        ->set('query', 'ada')
        ->assertSee('Unfollow')
        ->press("toggleFollow('ada')")
        ->assertSee('Follow');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/users/ada/follow'));

    expect($test->get('users')[0]['followed_by_me'])->toBeFalse();
});

it('hides the follow pill on your own row', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [[
            'id' => 1,
            'name' => 'Pinkary User',
            'username' => 'pinkaryuser',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'followed_by_me' => false,
            'is_me' => true,
        ]], 'hashtags' => []]]),
    ]);

    Native::test(Search::class)
        ->set('query', 'pinkaryuser')
        ->assertSee('Pinkary User')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array(
            $node['props']['a11y_label'] ?? '',
            ['Follow', 'Unfollow'],
            true,
        ));
});

it('ignores a follow tap on your own row', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [[
            'id' => 1,
            'name' => 'Pinkary User',
            'username' => 'pinkaryuser',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'followed_by_me' => false,
            'is_me' => true,
        ]], 'hashtags' => []]]),
    ]);

    $test = Native::test(Search::class)
        ->set('query', 'pinkaryuser')
        ->call('toggleFollow', 'pinkaryuser');

    expect($test->get('users')[0]['followed_by_me'])->toBeFalse();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/follow'));
});

it('follows and unfollows a liker row in the post sheet', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => [[
            'id' => 'q1',
            'from' => ['name' => 'Grace Hopper', 'username' => 'grace'],
            'to' => ['name' => 'Ada Lovelace', 'username' => 'ada'],
            'content' => 'Who are you?',
            'answer' => 'The company account.',
            'metrics' => ['likes' => 1, 'comments' => 0],
        ]]]),
        'https://pinkary.test/api/v1/questions/q1/likes*' => Http::response(['data' => [[
            'id' => 2,
            'name' => 'Ada Lovelace',
            'username' => 'ada',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'followed_by_me' => false,
            'is_me' => false,
        ]]]),
        'https://pinkary.test/api/v1/users/ada/follow' => Http::response(['data' => ['followed' => true, 'followers' => 4]]),
    ]);

    $test = Native::test(Home::class)
        ->press("openMenu('q1')")
        ->press('openLikersMenu')
        ->assertSee('Ada Lovelace')
        ->press("toggleFollow('ada')")
        ->assertSee('Unfollow');

    expect($test->get('likers')[0]['followed_by_me'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/users/ada/follow'));
});

it('routes the profile card follow and the liker row follow apart', function (): void {
    fakeCardResponses('users/ada', otherCardProfile());

    $test = Native::test(Profile::class, ['username' => 'ada'])
        ->set('likers', [[
            'id' => 3,
            'name' => 'Grace Hopper',
            'username' => 'grace',
            'avatar' => null,
            'verification' => ['profile' => true, 'company' => false],
            'followed_by_me' => false,
            'is_me' => false,
        ]]);

    Http::fake([
        'https://pinkary.test/api/v1/users/ada' => Http::response(['data' => otherCardProfile()]),
        'https://pinkary.test/api/v1/users/ada/questions*' => Http::response(['data' => []]),
        'https://pinkary.test/api/v1/users/ada/follow' => Http::response(['data' => ['followed' => true, 'followers' => 4]]),
        'https://pinkary.test/api/v1/users/grace/follow' => Http::response(['data' => ['followed' => true, 'followers' => 1]]),
    ]);

    $test->call('toggleFollow', 'grace');

    expect($test->get('likers')[0]['followed_by_me'])->toBeTrue()
        ->and($test->get('profile')['followed_by_me'])->toBeFalse();
});
