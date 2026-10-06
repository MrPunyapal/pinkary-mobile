<?php

declare(strict_types=1);

use App\NativeComponents\EditProfile;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    CurrentUser::clear();
});

afterEach(function (): void {
    CurrentUser::clear();
    AccountStore::clear();
});

function editProfilePayload(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada@example.com',
        'bio' => 'Building in public.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 1, 'following' => 1, 'posts' => 1, 'views' => 1],
        'followed_by_me' => false,
        'is_me' => true,
        'link_shape' => 'rounded-lg',
        'links' => [],
        'member_since' => ['human' => 'today'],
    ], $overrides);
}

function fakeProfile(array $overrides = []): void
{
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload($overrides)]),
    ]);
}

it('refuses to open without a token', function (): void {
    Native::test(EditProfile::class)->assertNavigatedTo('/login');
});

it('offers a retry that reloads the profile', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);

    $attempt = 0;

    Http::fake([
        'https://pinkary.test/api/v1/profile' => function () use (&$attempt) {
            $attempt++;

            return $attempt === 1
                ? Http::failedConnection()
                : Http::response(['data' => editProfilePayload()]);
        },
    ]);

    Native::test(EditProfile::class)
        ->assertSet('loading', false)
        ->assertSee('Try again')
        ->tap('Try again')
        ->assertSet('name', 'Ada Lovelace')
        ->assertDontSee('Try again');

    expect($attempt)->toBe(2);
});

it('validates the profile fields before calling the API', function (array $fields, string $message): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    fakeProfile();
    Http::preventStrayRequests();

    $screen = Native::test(EditProfile::class);

    foreach ($fields as $field => $value) {
        $screen->set($field, $value);
    }

    $screen->call('save');

    expect($screen->get('fieldErrors'))->not->toBeEmpty();
    expect($screen->get('successMessage'))->toBe('');
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
})->with([
    'blank name' => [['name' => '   '], 'name'],
    'short username' => [['username' => 'ab'], 'username'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'long bio' => [['bio' => str_repeat('x', 256)], 'bio'],
]);

it('saves a valid profile and refreshes the cached user', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'name' => 'Ada King',
            'username' => 'adaking',
            'bio' => 'New bio.',
        ])]),
    ]);

    Native::test(EditProfile::class)
        ->set('name', 'Ada King')
        ->set('username', 'adaking')
        ->set('bio', 'New bio.')
        ->call('save')
        ->assertSet('successMessage', 'Your profile has been saved.');

    expect(CurrentUser::get()['name'] ?? null)->toBe('Ada King');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pinkary.test/api/v1/profile'
        && $request->method() === 'PATCH'
        && $request['name'] === 'Ada King'
        && $request['username'] === 'adaking');
});

it('reports a failed profile save and surfaces server field errors', function (Closure $response): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload()]),
        '*' => $response(),
    ]);

    $screen = Native::test(EditProfile::class)->call('save');

    expect($screen->get('successMessage'))->toBe('');
})->with([
    'connection failure' => [fn () => Http::failedConnection()],
    'validation errors' => [fn () => Http::response(['errors' => ['username' => ['That name is taken.']]], 422)],
    'server error' => [fn () => Http::response(['message' => 'Server error.'], 500)],
]);

it('ignores a save while one is already running', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload()]),
        'https://pinkary.test/api/v1/links*' => Http::response(['data' => []]),
    ]);

    Native::test(EditProfile::class)
        ->set('busy', true)
        ->call('save')
        ->call('saveLinkShape')
        ->call('addLink')
        ->call('deleteLink', 7)
        ->call('saveEdit', 7)
        ->assertSet('busy', true)
        ->assertSet('linkBusy', false);
});

it('rejects a link shape the server would not accept', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    fakeProfile();

    Native::test(EditProfile::class)
        ->set('linkShape', 'not-a-shape')
        ->call('saveLinkShape')
        ->assertSet('linkMessage', '');
});

it('saves a known link shape', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload()]),
    ]);

    Native::test(EditProfile::class)
        ->set('linkShape', 'rounded-full')
        ->call('saveLinkShape')
        ->assertSet('linkMessage', 'Link shape saved.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request['link_shape'] === 'rounded-full');
});

it('validates a new link before creating it', function (string $description, string $url, string $message): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    fakeProfile();

    $screen = Native::test(EditProfile::class)
        ->set('newDescription', $description)
        ->set('newUrl', $url)
        ->call('addLink');

    expect($screen->get('linkMessage'))->toBe($message);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/links'));
})->with([
    'blank description' => ['  ', 'https://example.com', 'Add a short description for the link.'],
    'long description' => [str_repeat('x', 101), 'https://example.com', 'Link descriptions may not be greater than 100 characters.'],
    'no scheme' => ['Site', 'example.com', 'Links must be valid https:// URLs.'],
    'plain http' => ['Site', 'http://example.com', 'Links must be valid https:// URLs.'],
    'over-long url' => ['Site', 'https://example.com/'.str_repeat('a', 100), 'Links must be valid https:// URLs.'],
]);

it('adds a valid link to the list', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload()]),
        'https://pinkary.test/api/v1/links' => Http::response(['data' => [
            'id' => 9, 'description' => 'Website', 'url' => 'https://example.com',
        ]]),
    ]);

    $screen = Native::test(EditProfile::class)
        ->set('newDescription', 'Website')
        ->set('newUrl', 'https://example.com')
        ->call('addLink')
        ->assertSet('linkMessage', 'Link added.')
        ->assertSet('newDescription', '')
        ->assertSet('newUrl', '');

    expect($screen->get('links'))->toHaveCount(1)
        ->and($screen->get('links')[0]['id'])->toBe(9);
});

it('reports an unsuccessful link creation', function (Closure $response, string $expected): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload()]),
        'https://pinkary.test/api/v1/links' => $response(),
    ]);

    Native::test(EditProfile::class)
        ->set('newDescription', 'Website')
        ->set('newUrl', 'https://example.com')
        ->call('addLink')
        ->assertSet('linkMessage', $expected);
})->with([
    'connection failure' => [fn () => Http::failedConnection(), 'This link could not be added. Please try again.'],
    'server error' => [fn () => Http::response(['message' => 'Server error.'], 500), 'Server error.'],
]);

it('edits an existing link in place', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => Http::response(['data' => [
            'id' => 9, 'description' => 'New', 'url' => 'https://new.test',
        ]]),
    ]);

    $screen = Native::test(EditProfile::class)
        ->call('startEdit', 9)
        ->assertSet('editDescription', 'Old')
        ->set('editDescription', 'New')
        ->set('editUrl', 'https://new.test')
        ->call('saveEdit', 9)
        ->assertSet('linkMessage', 'Link saved.')
        ->assertSet('editingLinkId', null);

    expect($screen->get('links')[0]['description'])->toBe('New');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pinkary.test/api/v1/links/9'
        && $request->method() === 'PUT');
});

it('validates an edited link the same way as a new one', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => Http::response(['data' => []]),
    ]);

    $screen = Native::test(EditProfile::class)->call('startEdit', 9);

    $screen->set('editDescription', '')->call('saveEdit', 9)
        ->assertSet('linkMessage', 'Add a short description for the link.');

    $screen->set('editDescription', str_repeat('x', 101))->call('saveEdit', 9)
        ->assertSet('linkMessage', 'Link descriptions may not be greater than 100 characters.');

    $screen->set('editDescription', 'Fine')->set('editUrl', 'nope')->call('saveEdit', 9)
        ->assertSet('linkMessage', 'Links must be valid https:// URLs.');

    $screen->call('cancelEdit')->assertSet('editingLinkId', null);
});

it('reports an unsuccessful link edit', function (Closure $response, string $expected): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => $response(),
    ]);

    Native::test(EditProfile::class)
        ->call('startEdit', 9)
        ->set('editDescription', 'New')
        ->set('editUrl', 'https://new.test')
        ->call('saveEdit', 9)
        ->assertSet('linkMessage', $expected);
})->with([
    'connection failure' => [fn () => Http::failedConnection(), 'This link could not be saved. Please try again.'],
    'server error' => [fn () => Http::response(['message' => 'Server error.'], 500), 'Server error.'],
]);

it('asks for a second tap before deleting a link', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => Http::response(null, 204),
    ]);

    $screen = Native::test(EditProfile::class);

    $screen->call('deleteLink', 9)
        ->assertSet('confirmingDeleteId', 9)
        ->assertSet('linkMessage', 'Tap delete again to confirm.');

    expect($screen->get('links'))->toHaveCount(1);

    $screen->call('deleteLink', 9)
        ->assertSet('linkMessage', 'Link deleted.')
        ->assertSet('confirmingDeleteId', null);

    expect($screen->get('links'))->toBe([]);
});

it('treats an already deleted link as deleted', function (): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => Http::response(null, 404),
    ]);

    Native::test(EditProfile::class)
        ->call('deleteLink', 9)
        ->call('deleteLink', 9)
        ->assertSet('linkMessage', 'Link deleted.');
});

it('reports a failed link delete', function (Closure $response, string $expected): void {
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editProfilePayload([
            'links' => [['id' => 9, 'description' => 'Old', 'url' => 'https://old.test']],
        ])]),
        'https://pinkary.test/api/v1/links/9' => $response(),
    ]);

    Native::test(EditProfile::class)
        ->call('deleteLink', 9)
        ->call('deleteLink', 9)
        ->assertSet('linkMessage', $expected);
})->with([
    'connection failure' => [fn () => Http::failedConnection(), 'This link could not be deleted. Please try again.'],
    'server error' => [fn () => Http::response(['message' => 'Server error.'], 500), 'This link could not be deleted. Please try again.'],
]);
