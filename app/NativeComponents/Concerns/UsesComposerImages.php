<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\Payload;
use Illuminate\Support\Str;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;
use Throwable;

/** @mixin NativeComponent */
trait UsesComposerImages
{
    public const int MAX_IMAGES = 3;

    abstract protected function imageText(int $postIndex): ?string;

    abstract protected function replaceImageText(int $postIndex, string $text): void;

    abstract protected function imageTextLimit(): int;

    abstract protected function imageComposerBusy(): bool;

    abstract protected function imageError(string $message): void;

    abstract protected function imagesChanged(): void;

    public function maxImages(): int
    {
        return self::MAX_IMAGES;
    }

    public function supportsImagePicker(): bool
    {
        return nativephp_can('Camera.PickMedia');
    }

    public function canAddImage(): bool
    {
        return $this->supportsImagePicker() && ! $this->imageComposerBusy()
            && ! $this->pickingImages && ! $this->uploadingImages && $this->imageCount() < self::MAX_IMAGES;
    }

    public function pickImages(int $postIndex = -1): void
    {
        if (! $this->canAddImage() || $this->imageText($postIndex) === null) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->imagePostIndex = $postIndex;
        $pickerId = (string) Str::uuid();
        $this->imagePickerId = $pickerId;
        $this->pickingImages = true;

        try {
            $started = Camera::pickImages('image', true, self::MAX_IMAGES - $this->imageCount())
                ->id($pickerId)
                ->mediaSelected(function (MediaSelected $event) use ($token, $postIndex, $pickerId): void {
                    if (($event->id !== null && $event->id !== $pickerId) || $this->imagePickerId !== $pickerId) {
                        return;
                    }

                    $this->pickingImages = false;
                    $this->imagePickerId = '';

                    if ($token !== $this->token() || $postIndex !== $this->imagePostIndex) {
                        return;
                    }

                    $this->onMediaSelected($event);
                })
                ->start();
        } catch (Throwable) {
            $started = false;
        }

        if (! $started) {
            $this->pickingImages = false;
            $this->imagePickerId = '';
            $this->imageError('Could not open the photo picker. Please try again.');
        }
    }

    public function onMediaSelected(MediaSelected $event): void
    {
        if ($event->cancelled || $this->imageComposerBusy() || $this->uploadingImages
            || $this->imageText($this->imagePostIndex) === null) {
            return;
        }

        if (! $event->success) {
            $this->imageError($event->error ?? 'That image could not be added.');

            return;
        }

        $paths = [];

        foreach ($event->files as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        if ($paths === []) {
            $this->imageError($event->error ?? 'That image could not be added.');

            return;
        }

        $readable = array_values(array_filter(array_unique($paths), is_readable(...)));

        if ($readable === []) {
            $this->imageError(getenv('JUMP_BRIDGE_PORT') !== false
                ? 'Photos cannot be uploaded in the Jump app. The file is on your phone and the app server is on this machine, so it needs a real build.'
                : 'That image could not be read from this device.');

            return;
        }

        $token = $this->requireToken();
        $room = self::MAX_IMAGES - $this->imageCount();

        if ($token === null || $room <= 0) {
            return;
        }

        $this->uploadingImages = true;
        $this->imageError('');
        $api = app(PinkaryApi::class);

        try {
            $response = $api->attempt(fn () => $api->uploadImages($token, array_slice($readable, 0, $room)));
        } finally {
            $this->uploadingImages = false;
        }

        if ($response === null) {
            $this->imageError('That image could not be uploaded. Please try again.');

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->imageError(ApiErrors::first($response, 'That image could not be uploaded.'));

            return;
        }

        foreach (Payload::array($response->json('data')) as $image) {
            $url = Payload::string(data_get($image, 'url'));
            $name = Payload::string(data_get($image, 'original_name'));
            $path = Payload::string(data_get($image, 'path'));

            if ($url === '' || $name === '' || $path === '') {
                continue;
            }

            $existing = array_find($this->images, fn (array $image): bool => $image['path'] === $path
                && ($image['postIndex'] ?? -1) === $this->imagePostIndex);

            if ($existing !== null) {
                $this->insertImage($existing);
            } elseif ($this->imageCount() < self::MAX_IMAGES) {
                $image = ['url' => $url, 'path' => $path, 'name' => $name, 'postIndex' => $this->imagePostIndex];

                if ($this->insertImage($image)) {
                    $this->images[] = $image;
                }
            }
        }

        $this->imagesChanged();
    }

    /** @return array<int, array{url: string, path: string, name: string, postIndex?: int}> */
    public function imagesForPost(int $postIndex = -1): array
    {
        return array_filter($this->images, fn (array $image): bool => ($image['postIndex'] ?? -1) === $postIndex);
    }

    public function removeImage(int $index): void
    {
        if ($this->imageComposerBusy() || ! isset($this->images[$index])) {
            return;
        }

        $image = $this->images[$index];
        $postIndex = $image['postIndex'] ?? -1;
        $text = $this->imageText($postIndex);

        if ($text !== null) {
            $snippet = $this->imageSnippet($image);
            $this->replaceImageText($postIndex, str_replace([$snippet."\n", $snippet], '', $text));
        }

        $images = [];

        foreach ($this->images as $imageIndex => $candidate) {
            if ($imageIndex !== $index) {
                $images[] = $candidate;
            }
        }

        $this->images = $images;
        $this->imagesChanged();
    }

    public function reinsertImage(int $index): void
    {
        if ($this->imageComposerBusy() || ! isset($this->images[$index])) {
            return;
        }

        $this->insertImage($this->images[$index]);
        $this->imagesChanged();
    }

    private function imageCount(): int
    {
        return count(array_unique(array_map(fn (array $image): string => $image['path'] !== '' ? $image['path'] : $image['url'], $this->images)));
    }

    /** @param array{url: string, path: string, name: string, postIndex?: int} $image */
    private function insertImage(array $image): bool
    {
        $postIndex = $image['postIndex'] ?? -1;
        $text = $this->imageText($postIndex);

        if ($text === null) {
            return false;
        }

        $snippet = $this->imageSnippet($image);

        if (str_contains($text, $snippet)) {
            return true;
        }

        $candidate = $text !== '' && ! str_ends_with($text, "\n") ? "\n".$snippet : $snippet;

        if (mb_strlen($text.$candidate) > $this->imageTextLimit()) {
            $this->imageError('Adding this image will exceed the maximum content length.');

            return false;
        }

        $this->replaceImageText($postIndex, $text.$candidate);

        return true;
    }

    /** @param array{url: string, path: string, name: string, postIndex?: int} $image */
    private function imageSnippet(array $image): string
    {
        return '!['.$image['name'].']('.($image['path'] !== '' ? $image['path'] : $image['url']).')';
    }
}
