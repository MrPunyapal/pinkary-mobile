<?php

declare(strict_types=1);

use App\NativeComponents\EditProfile;
use App\Support\AccountStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
});

function editedProfilePayload(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'Pinkary User',
        'username' => 'pinkaryuser',
        'email' => 'pinkary@example.com',
        'bio' => 'Building in public.',
        'avatar' => null,
        'link_shape' => 'rounded-lg',
        'gradient' => 'from-blue-500 to-purple-600',
        'question_preference' => 'everyone',
        'links' => [],
    ], $overrides);
}

/**
 * @return list<string>
 */
function inputLabelsOf(array $node): array
{
    $labels = [];

    if (in_array($node['type'] ?? '', ['outlined_text_input', 'select'], true)) {
        $labels[] = (string) ($node['props']['label'] ?? '');
    }

    foreach ($node['children'] ?? [] as $child) {
        $labels = array_merge($labels, inputLabelsOf($child));
    }

    return $labels;
}

it('uses the web profile information heading and field order', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload()]),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Profile Information')
        ->assertSee('Update your account’s profile information and email address.')
        ->assertSee('Save');

    $labels = [];
    foreach ($test->tree()['children'] ?? [] as $child) {
        $labels = array_merge($labels, inputLabelsOf($child));
    }

    expect($labels)->toBe([
        'Name',
        'Username',
        'Email',
        'Bio',
        'Mail Preference Time',
        'Default Feed',
        'Who can ask you questions?',
        'How would you like to do questions by default?',
        'Link Shape',
        'Link Color',
        'Description',
        'URL',
    ]);
});

it('sends the changed preference fields the web owns', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload()]),
    ]);

    $test = Native::test(EditProfile::class)
        ->set('mailPreferenceTime', 'weekly')
        ->set('defaultFeed', 'trending')
        ->set('questionPreference', 'following')
        ->set('questionVisibility', 'false')
        ->call('save')
        ->assertSee('Your profile has been saved.');

    expect($test->get('mailPreferenceTime'))->toBe('weekly');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/profile')
        && $request['mail_preference_time'] === 'weekly'
        && $request['default_feed'] === 'trending'
        && $request['question_preference'] === 'following'
        && $request['prefers_anonymous_questions'] === false);
});

it('never sends a preference the user did not touch', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload()]),
    ]);

    Native::test(EditProfile::class)
        ->set('bio', 'Shipping things.')
        ->call('save')
        ->assertSee('Your profile has been saved.');

    Http::assertSent(function ($request): bool {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        return ! array_key_exists('mail_preference_time', $request->data())
            && ! array_key_exists('default_feed', $request->data())
            && ! array_key_exists('question_preference', $request->data())
            && ! array_key_exists('prefers_anonymous_questions', $request->data());
    });
});

it('loads the question preference from the profile response', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload([
            'question_preference' => 'no_one',
        ])]),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Who can ask you questions?')
        ->assertSee('No one');

    expect($test->get('questionPreference'))->toBe('no_one');
});

it('reorders links and saves the new order through the API', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload([
            'links' => [
                ['id' => 7, 'description' => 'Website', 'url' => 'https://example.com'],
                ['id' => 8, 'description' => 'Blog', 'url' => 'https://blog.example.com'],
            ],
        ])]),
        'https://pinkary.test/api/v1/links/sort' => Http::response(['data' => ['sorted' => true]]),
    ]);

    $test = Native::test(EditProfile::class)
        ->call('moveLinkDown', 7)
        ->assertSee('Save order')
        ->call('saveLinkOrder')
        ->assertSee('Link order saved.');

    expect(array_column($test->get('links'), 'id'))->toBe([8, 7])
        ->and($test->get('linkOrderDirty'))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/links/sort')
        && $request['sort'] === [8, 7]);
});

it('patches the link shape like the web link settings form', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::sequence()
            ->push(['data' => editedProfilePayload()])
            ->push(['data' => editedProfilePayload(['link_shape' => 'rounded-full'])]),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Link Shape')
        ->assertSee('In what shape do you want to present your links?')
        ->set('linkShape', 'rounded-full')
        ->call('saveLinkShape')
        ->assertSee('Link shape saved.');

    expect($test->get('linkShape'))->toBe('rounded-full');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/profile')
        && $request['link_shape'] === 'rounded-full');
});

it('loads and saves the selected link color', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::sequence()
            ->push(['data' => editedProfilePayload()])
            ->push(['data' => editedProfilePayload(['gradient' => 'from-red-500 to-orange-600'])]),
    ]);

    $test = Native::test(EditProfile::class)
        ->assertSee('Link Color')
        ->set('linkGradient', 'from-red-500 to-orange-600')
        ->call('saveLinkGradient')
        ->assertSee('Link gradient saved.');

    expect($test->get('linkGradient'))->toBe('from-red-500 to-orange-600');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/profile')
        && $request['gradient'] === 'from-red-500 to-orange-600');
});

it('uploads a valid profile photo as multipart form data', function (): void {
    $file = UploadedFile::fake()->image('avatar.png');
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::sequence()
            ->push(['data' => editedProfilePayload()])
            ->push(['data' => editedProfilePayload(['avatar' => 'https://pinkary.test/storage/avatars/ada.png'])])
            ->push(['data' => editedProfilePayload(['avatar' => 'https://pinkary.test/storage/avatars/ada.png'])]),
    ]);

    $test = Native::test(EditProfile::class)
        ->call('onAvatarSelected', new MediaSelected(
            success: true,
            files: [['path' => $file->getPathname()]],
            count: 1,
        ))
        ->assertSee('Profile photo updated.');

    expect($test->get('avatar'))->toBe('https://pinkary.test/storage/avatars/ada.png');

    Http::assertSent(function ($request): bool {
        if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/v1/profile')) {
            return false;
        }

        return str_contains($request->header('Content-Type')[0] ?? '', 'multipart/form-data')
            && $request->hasFile('avatar');
    });
});

it('rejects unsupported profile photo files before upload', function (): void {
    $file = UploadedFile::fake()->create('avatar.gif', 1, 'image/gif');
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload()]),
    ]);

    $test = Native::test(EditProfile::class)
        ->call('onAvatarSelected', new MediaSelected(
            success: true,
            files: [['path' => $file->getPathname()]],
            count: 1,
        ));

    expect($test->get('avatarMessage'))->toBe('Choose a JPG or PNG photo up to 2 MB.');
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/profile') && $request->method() === 'POST');
});

it('warns that editing a link URL resets its clicks', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload([
            'links' => [[
                'id' => 7,
                'description' => 'Website',
                'url' => 'https://example.com?ref=pinkary',
                'click_count' => 12,
            ]],
        ])]),
    ]);

    Native::test(EditProfile::class)
        ->call('startEdit', 7)
        ->assertDontSee('Editing the URL will reset the click counter to zero')
        ->input('editUrl', 'https://example.org')
        ->assertSee('Editing the URL will reset the click counter to zero');
});

it('labels the link create button like the web', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/profile' => Http::response(['data' => editedProfilePayload()]),
    ]);

    Native::test(EditProfile::class)
        ->assertSee('Create')
        ->assertDontSee('Add link');
});
