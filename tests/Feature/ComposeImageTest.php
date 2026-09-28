<?php

use App\NativeComponents\Compose;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\DraftStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;

/**
 * The composer's photo button: pick, upload, and the markdown it writes.
 *
 * Uploaded as each file is chosen rather than at publish, matching the web
 * (resources/js/image-upload.js dispatches on image.uploaded), so the post
 * body already carries the image markdown by the time the user presses Post.
 */
function fakeImageUpload(array $images): void
{
    Http::fake([
        'https://pinkary.test/api/v1/images' => Http::response(['data' => $images]),
        'https://pinkary.test/api/v1/*' => Http::response(['data' => []]),
    ]);
}

function pickedEvent(array $paths, bool $cancelled = false, ?string $error = null): MediaSelected
{
    return new MediaSelected(
        success: ! $cancelled && $error === null,
        files: array_map(fn (string $path): array => ['path' => $path], $paths),
        count: count($paths),
        error: $error,
        cancelled: $cancelled,
    );
}

/**
 * A real file on disk, because uploadImages() only attaches paths that exist.
 */
function pickedFile(string $name = 'shot.png'): UploadedFile
{
    $path = sys_get_temp_dir().'/pinkary-pick-'.uniqid().'.png';

    file_put_contents($path, 'not-really-a-png');

    return new UploadedFile($path, $name, 'image/png', null, true);
}

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
    CurrentUser::clear();
});

it('offers a photo button wired to the picker', function (): void {
    fakeImageUpload([]);

    Native::test(Compose::class)
        ->assertSee('Upload an image');
});

it('inserts image markdown into the post', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
        'size' => 10,
    ]]);

    $test = Native::test(Compose::class)
        ->set('content', 'Look at this')
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    expect($test->get('content'))->toBe("Look at this\n![a.png](https://pinkary.test/storage/1/a.png)")
        ->and($test->get('images'))->toHaveCount(1);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/images')
        && $request->method() === 'POST');
});

it('inserts markdown without a leading newline on an empty post', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    expect($test->get('content'))->toBe('![a.png](https://pinkary.test/storage/1/a.png)');
});

it('says nothing when the pick is cancelled', function (): void {
    fakeImageUpload([]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([], cancelled: true));

    // The web is silent on a cancel, so this is too: no error, no upload.
    expect($test->get('errorMessage'))->toBe('')
        ->and($test->get('images'))->toBe([]);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/v1/images'));
});

it('reports a picker that failed outright', function (): void {
    fakeImageUpload([]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([], error: 'Could not open the photo picker'));

    expect($test->get('errorMessage'))->toBe('Could not open the photo picker');
});

it('surfaces the API validation message', function (): void {
    Http::fake([
        'https://pinkary.test/api/v1/images' => Http::response([
            'message' => 'The image may not be greater than 8192 kilobytes.',
            'errors' => ['images.0' => ['The image may not be greater than 8192 kilobytes.']],
        ], 422),
    ]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    expect($test->get('errorMessage'))->toBe('The image may not be greater than 8192 kilobytes.')
        ->and($test->get('images'))->toBe([]);
});

it('stops at three images, like the web composer', function (): void {
    // Distinct URLs per pick: the web refuses to write the same snippet twice
    // (image-upload.js checks includes() first), so reusing one URL would test
    // the de-duplication rather than the cap.
    Http::fake([
        'https://pinkary.test/api/v1/images' => Http::sequence()
            ->push(['data' => [['url' => 'https://pinkary.test/storage/1.png', 'path' => 'images/1.png', 'original_name' => '1.png']]])
            ->push(['data' => [['url' => 'https://pinkary.test/storage/2.png', 'path' => 'images/2.png', 'original_name' => '2.png']]])
            ->push(['data' => [['url' => 'https://pinkary.test/storage/3.png', 'path' => 'images/3.png', 'original_name' => '3.png']]])
            ->push(['data' => [['url' => 'https://pinkary.test/storage/4.png', 'path' => 'images/4.png', 'original_name' => '4.png']]]),
        'https://pinkary.test/api/v1/*' => Http::response(['data' => []]),
    ]);

    $test = Native::test(Compose::class);

    for ($i = 0; $i < 4; $i++) {
        $test->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));
    }

    expect($test->get('images'))->toHaveCount(3)
        ->and($test->get('content'))->not->toContain('4.png');
});

it('removes an image and takes its markdown with it', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    $test = $test->call('removeImage', 0);

    expect($test->get('images'))->toBe([])
        ->and($test->get('content'))->toBe('');
});

it('puts a removed images markdown back on request', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    $snippet = '![a.png](https://pinkary.test/storage/1/a.png)';

    // The user deleted the markdown by hand; the thumbnail is still staged, so
    // tapping it puts the image back (the web's createMarkdownImage(index)).
    $test = $test->set('content', str_replace($snippet, '', (string) $test->get('content')))
        ->call('reinsertImage', 0);

    expect($test->get('content'))->toContain($snippet);
});

it('does not write the same image markdown twice', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    $test = Native::test(Compose::class)
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    $before = (string) $test->get('content');

    $test = $test->call('reinsertImage', 0);

    expect($test->get('content'))->toBe($before);
});

it('refuses an image that would push the post over the limit', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    $test = Native::test(Compose::class)
        ->set('content', str_repeat('a', Compose::MAX_CONTENT_LENGTH - 5))
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    // The markdown is not truncated into the post, and the user is told why.
    expect($test->get('errorMessage'))->toBe('Adding this image will exceed the maximum content length.')
        ->and($test->get('content'))->not->toContain('![');
});

it('carries staged images through a draft', function (): void {
    fakeImageUpload([[
        'url' => 'https://pinkary.test/storage/1/a.png',
        'path' => 'images/2026/a.png',
        'original_name' => 'a.png',
    ]]);

    Native::test(Compose::class)
        ->set('content', 'Hi')
        ->call('onMediaSelected', pickedEvent([pickedFile()->getPathname()]));

    CurrentUser::clear();

    $test = Native::test(Compose::class);

    // A restored draft can still show the thumbnail and take the markdown out.
    expect($test->get('images'))->toHaveCount(1)
        ->and($test->get('content'))->toContain('![a.png]');

    $test->call('removeImage', 0);

    expect(DraftStore::get()['images'])->toBe([]);
});
