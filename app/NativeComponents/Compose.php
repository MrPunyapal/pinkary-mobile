<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\NativeComponents\Concerns\UsesCaptcha;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\DraftStore;
use App\Support\Payload;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;

final class Compose extends NativeComponent implements HasWebHead
{
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;
    use UsesCaptcha;

    public const int MAX_CONTENT_LENGTH = 1000;

    public const int MAX_THREAD_POSTS = 9;

    public const int MAX_ASK_LENGTH = 255;

    /**
     * @var array<int, string>
     */
    public const array POLL_DURATIONS = [
        1 => '24 hours',
        2 => '2 days',
        3 => '3 days',
        5 => '5 days',
        7 => '1 week',
    ];

    /**
     * @var array<int, string>
     */
    public const array THREAD_POLL_DURATIONS = [
        1 => '24 hours',
        2 => '2 days',
        3 => '3 days',
        7 => '1 week',
    ];

    public string $content = '';

    /** @var list<array<string, mixed>> */
    public array $mentionResults = [];

    public int $mentionPostIndex = -1;

    public string $mentionQuery = '';

    public string $mentionKind = 'user';

    public int $mentionStart = -1;

    public int $mentionEnd = -1;

    /** @var list<string> */
    public array $posts = [];

    /**
     * @var list<array{isPoll: bool, options: list<string>, duration: int}>
     */
    public array $postPolls = [];

    public bool $busy = false;

    public string $errorMessage = '';

    public string $validationMessage = '';

    public ?int $channelId = null;

    public string $channelName = '';

    public bool $pickerOpen = false;

    public string $channelQuery = '';

    /** @var list<array<string, mixed>> */
    public array $channelResults = [];

    public bool $searchingChannels = false;

    public string $channelError = '';

    public bool $isPoll = false;

    /** @var list<string> */
    public array $pollOptions = ['', ''];

    public int $pollDuration = 1;

    public ?string $avatar = null;

    public string $askUsername = '';

    public bool $anonymous = false;

    /**
     * @var list<array{url: string, path: string, name: string}>
     */
    public array $images = [];

    public bool $uploadingImages = false;

    protected bool $hidesDrawer = true;

    public function mount(): void
    {
        $this->askUsername = Payload::string($this->param('username'));

        $token = $this->requireToken(replaceScreen: true);

        if ($token === null) {
            return;
        }

        $this->hydrateAvatar();
        $this->anonymous = $this->defaultAnonymousPreference();
        $this->loadCaptcha('post', $token);
        $this->restoreDraft();
        $this->validateState();

        if ($this->shouldFetchProfile()) {
            $this->refreshAvatarFromProfile($token);
        }

        if ($this->askUsername !== '') {
            return;
        }

        $this->searchChannels($token);
    }

    public function updatedContent(): void
    {
        $this->touchDraft();
    }

    public function updateMentionSelection(int $postIndex, string $text, int $selectionStart, int $selectionEnd): void
    {
        if ($this->busy || $postIndex < -1 || ($postIndex >= 0 && ! isset($this->posts[$postIndex]))) {
            return;
        }

        $currentText = $postIndex < 0 ? $this->content : $this->posts[$postIndex];
        if ($text !== $currentText) {
            return;
        }

        if ($selectionStart !== $selectionEnd || $selectionStart < 0 || $selectionStart > mb_strlen($text)
            || ! preg_match('/(?:^|\s)([@#])([A-Za-z0-9_-]{1,50})$/u', mb_substr($text, 0, $selectionStart), $match)) {
            $this->clearMentionSuggestions();

            return;
        }

        $trigger = $match[1];
        $query = $match[2];
        $kind = $trigger === '#' ? 'hashtag' : 'user';
        $this->mentionStart = $selectionStart - mb_strlen($query) - 1;
        preg_match('/^[A-Za-z0-9_-]*/', mb_substr($text, $selectionStart), $suffix);
        $this->mentionEnd = $selectionStart + mb_strlen($suffix[0] ?? '');

        if ($query === $this->mentionQuery && $postIndex === $this->mentionPostIndex && $kind === $this->mentionKind) {
            return;
        }

        $this->mentionQuery = $query;
        $this->mentionKind = $kind;
        $this->mentionPostIndex = $postIndex;
        $this->mentionResults = [];

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->search($this->token(), $query));

        if ($response === null || ! $response->successful()) {
            return;
        }

        $results = Payload::array($response->json($kind === 'hashtag' ? 'data.hashtags' : 'data.users'));

        $suggestions = array_filter(array_map(
            fn (mixed $result): array => is_array($result) ? array_filter($result, is_string(...), ARRAY_FILTER_USE_KEY) : [],
            $results
        ), fn (array $result): bool => preg_match(
            '/^[A-Za-z0-9_-]+$/D',
            Payload::string($result[$kind === 'hashtag' ? 'name' : 'username'] ?? '')
        ) === 1);

        $this->mentionResults = array_slice(array_values($suggestions), 0, 5);
    }

    public function selectMention(int $index): void
    {
        if ($this->busy || ! isset($this->mentionResults[$index])) {
            return;
        }

        $trigger = $this->mentionKind === 'hashtag' ? '#' : '@';
        $resultKey = $this->mentionKind === 'hashtag' ? 'name' : 'username';
        $value = Payload::string($this->mentionResults[$index][$resultKey] ?? '');
        $postIndex = $this->mentionPostIndex;
        $text = $postIndex < 0 ? $this->content : ($this->posts[$postIndex] ?? '');

        $selection = mb_substr($text, $this->mentionStart, $this->mentionEnd - $this->mentionStart);
        if ($value === '' || $this->mentionStart < 0 || ! str_starts_with($selection, $trigger.$this->mentionQuery)
            || ! preg_match('/^'.$trigger.'[A-Za-z0-9_-]+$/D', $selection)) {
            $this->clearMentionSuggestions();

            return;
        }

        $suffix = mb_substr($text, $this->mentionEnd);
        $text = mb_substr($text, 0, $this->mentionStart).$trigger.$value.($suffix === '' ? ' ' : '').$suffix;
        if ($postIndex < 0) {
            $this->content = $text;
        } elseif (isset($this->posts[$postIndex])) {
            $this->posts[$postIndex] = $text;
        }

        $this->clearMentionSuggestions();
        $this->touchDraft();
    }

    public function updatedAnonymous(): void
    {
        $this->persistDraft();
    }

    public function contentTooLong(): bool
    {
        return mb_strlen($this->content) > $this->maxContentLength();
    }

    public function canPublish(): bool
    {
        if ($this->contentTooLong()) {
            return false;
        }

        if ($this->askUsername !== '') {
            return true;
        }

        foreach ($this->posts as $post) {
            if (mb_strlen($post) > self::MAX_CONTENT_LENGTH) {
                return false;
            }
        }

        if ($this->isPoll && ! $this->pollOptionsAreValid($this->pollOptions)) {
            return false;
        }

        return array_all($this->postPolls, fn (array $poll): bool => ! $poll['isPoll'] || $this->pollOptionsAreValid($poll['options']));
    }

    public function validateState(): void
    {
        $this->validationMessage = $this->overLimitMessage();
    }

    public function canAddPost(): bool
    {
        if (mb_trim($this->content) === '') {
            return false;
        }

        return array_all($this->posts, fn (string $post): bool => mb_trim($post) !== '');
    }

    public function addPost(): void
    {
        if ($this->busy || count($this->posts) >= self::MAX_THREAD_POSTS || ! $this->canAddPost()) {
            return;
        }

        $this->posts[] = '';
        $this->postPolls[] = $this->emptyPostPoll();
        $this->touchDraft();
    }

    public function removePost(int $index): void
    {
        if ($this->busy) {
            return;
        }

        $posts = $this->posts;
        $postPolls = $this->postPolls;
        unset($posts[$index], $postPolls[$index]);

        $this->clearMentionSuggestions();
        $this->posts = array_values($posts);
        $this->postPolls = array_values($postPolls);
        $this->touchDraft();
    }

    public function updatePost(int $index, string $text): void
    {
        if ($this->busy || ! isset($this->posts[$index])) {
            return;
        }

        $this->posts[$index] = $text;
        $this->touchDraft();
    }

    public function togglePostPoll(int $index): void
    {
        if ($this->busy || ! isset($this->posts[$index])) {
            return;
        }

        $poll = $this->postPolls[$index] ?? $this->emptyPostPoll();
        $poll['isPoll'] = ! $poll['isPoll'];

        if ($poll['isPoll'] && count($poll['options']) < 2) {
            $poll['options'] = ['', ''];
        }

        $this->postPolls[$index] = $poll;
        $this->touchDraft();
    }

    public function addPostPollOption(int $index): void
    {
        if ($this->busy || ! isset($this->posts[$index])) {
            return;
        }

        $poll = $this->postPolls[$index] ?? $this->emptyPostPoll();

        if (count($poll['options']) >= 4) {
            return;
        }

        $poll['options'][] = '';
        $this->postPolls[$index] = $poll;
        $this->touchDraft();
    }

    public function removePostPollOption(int $index, int $optionIndex): void
    {
        if ($this->busy || ! isset($this->postPolls[$index])) {
            return;
        }

        if (count($this->postPolls[$index]['options']) <= 2) {
            return;
        }

        $options = $this->postPolls[$index]['options'];
        unset($options[$optionIndex]);

        $this->postPolls[$index]['options'] = array_values($options);
        $this->touchDraft();
    }

    public function updatePostPollOption(int $index, int $optionIndex, string $text): void
    {
        if ($this->busy || ! isset($this->postPolls[$index]['options'][$optionIndex])) {
            return;
        }

        $this->postPolls[$index]['options'][$optionIndex] = $text;
        $this->touchDraft();
    }

    public function setPostPollDuration(int $index, int $days): void
    {
        if ($this->busy || ! isset($this->postPolls[$index]) || ! isset(self::THREAD_POLL_DURATIONS[$days])) {
            return;
        }

        $this->postPolls[$index]['duration'] = $days;
        $this->persistDraft();
    }

    public function togglePicker(): void
    {
        $this->pickerOpen = ! $this->pickerOpen;
        $this->channelError = '';
    }

    public function updatedChannelQuery(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->searchChannels($token);
    }

    public function canCreateChannel(): bool
    {
        $query = mb_trim($this->channelQuery);

        if (mb_strlen($query) < 2) {
            return false;
        }

        return array_all($this->channelResults, fn (array $channel): bool => mb_strtolower(Payload::string(data_get($channel, 'name', ''))) !== mb_strtolower($query));
    }

    public function selectChannel(int $id): void
    {
        $name = '';

        foreach ($this->channelResults as $channel) {
            if (Payload::int(data_get($channel, 'id')) === $id) {
                $name = Payload::string(data_get($channel, 'name', ''));
            }
        }

        if ($name === '') {
            return;
        }

        $this->channelId = $id;
        $this->channelName = $name;
        $this->channelQuery = '';
        $this->channelError = '';
        $this->pickerOpen = false;
        $this->persistDraft();
    }

    public function stageChannel(): void
    {
        $name = mb_trim($this->channelQuery);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            $this->channelError = 'Channel names must be between 2 and 50 characters.';

            return;
        }

        if (! preg_match('/^[\pL\pN\s\-_]+$/u', $name)) {
            $this->channelError = 'Channel names may only contain letters, numbers, spaces, dashes, and underscores.';

            return;
        }

        $this->channelId = null;
        $this->channelName = $name;
        $this->channelQuery = '';
        $this->channelError = '';
        $this->pickerOpen = false;
        $this->persistDraft();
    }

    public function clearChannel(): void
    {
        $this->channelId = null;
        $this->channelName = '';
        $this->channelError = '';
        $this->persistDraft();
    }

    public function togglePoll(): void
    {
        if ($this->busy) {
            return;
        }

        $this->isPoll = ! $this->isPoll;

        if ($this->isPoll && count($this->pollOptions) < 2) {
            $this->pollOptions = ['', ''];
        }

        $this->touchDraft();
    }

    public function addPollOption(): void
    {
        if ($this->busy || count($this->pollOptions) >= 4) {
            return;
        }

        $this->pollOptions[] = '';
        $this->touchDraft();
    }

    public function removePollOption(int $index): void
    {
        if ($this->busy || count($this->pollOptions) <= 2) {
            return;
        }

        $options = $this->pollOptions;
        unset($options[$index]);

        $this->pollOptions = array_values($options);
        $this->touchDraft();
    }

    public function updatePollOption(int $index, string $text): void
    {
        if ($this->busy || ! isset($this->pollOptions[$index])) {
            return;
        }

        $this->pollOptions[$index] = $text;
        $this->touchDraft();
    }

    public function setPollDuration(int $days): void
    {
        if ($this->busy || ! isset(self::POLL_DURATIONS[$days])) {
            return;
        }

        $this->pollDuration = $days;
        $this->persistDraft();
    }

    public function maxImages(): int
    {
        return 3;
    }

    public function supportsImagePicker(): bool
    {
        return nativephp_can('Camera.PickMedia');
    }

    public function canAddImage(): bool
    {
        return $this->supportsImagePicker()
            && ! $this->uploadingImages
            && count($this->images) < $this->maxImages();
    }

    public function pickImages(): void
    {
        if ($this->busy || ! $this->canAddImage()) {
            return;
        }

        Camera::pickImages('image', true, $this->maxImages() - count($this->images))
            ->mediaSelected(function (MediaSelected $event): void {
                $this->onMediaSelected($event);
            })
            ->start();
    }

    public function onMediaSelected(MediaSelected $event): void
    {
        if ($event->cancelled) {
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
            $this->errorMessage = $event->error ?? 'That image could not be added.';

            return;
        }

        $readable = array_values(array_filter($paths, is_readable(...)));

        if ($readable === []) {
            $this->errorMessage = $this->unreadableImageMessage();

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $room = $this->maxImages() - count($this->images);

        if ($room <= 0) {
            return;
        }

        $this->uploadingImages = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->uploadImages($token, array_slice($readable, 0, $room)));

        $this->uploadingImages = false;

        if ($response === null) {
            $this->errorMessage = 'That image could not be uploaded. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, 'That image could not be uploaded.');

            return;
        }

        /** @var list<array<string, mixed>> $uploaded */
        $uploaded = $response->json('data', []);

        foreach ($uploaded as $image) {
            $url = Payload::string(data_get($image, 'url', ''));
            $name = Payload::string(data_get($image, 'original_name', ''));
            $storedPath = Payload::string(data_get($image, 'path', ''));

            if ($url === '' || $name === '' || $storedPath === '') {
                continue;
            }

            $this->addImage($url, $name, $storedPath);
        }

        $this->touchDraft();
    }

    public function removeImage(int $index): void
    {
        if ($this->busy || ! isset($this->images[$index])) {
            return;
        }

        $image = $this->images[$index];

        $snippet = $this->imageSnippet($image);

        $this->content = str_replace($snippet."\n", '', $this->content);
        $this->content = str_replace($snippet, '', $this->content);

        $images = $this->images;
        unset($images[$index]);

        $this->images = array_values($images);

        $this->touchDraft();
    }

    public function reinsertImage(int $index): void
    {
        if ($this->busy || ! isset($this->images[$index])) {
            return;
        }

        $image = $this->images[$index];

        $this->addImage($image['url'], $image['name'], $image['path']);
        $this->touchDraft();
    }

    public function publish(): void
    {
        if ($this->busy) {
            return;
        }

        if ($this->askUsername !== '') {
            $this->publishAsk();

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $content = mb_trim($this->content);

        if ($content === '') {
            $this->errorMessage = 'Write something first — a thread needs a first post.';

            return;
        }

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            $this->errorMessage = 'The first post may not be greater than 1000 characters.';
            $this->persistDraft();

            return;
        }

        /** @var list<string> $threadPosts */
        $threadPosts = [];
        /** @var array<int, array{options: list<string>, duration: int}|null> $threadPolls */
        $threadPolls = [];

        foreach ($this->posts as $index => $post) {
            $trimmed = mb_trim($post);

            if ($trimmed === '') {
                continue;
            }

            if (mb_strlen($trimmed) > self::MAX_CONTENT_LENGTH) {
                $this->errorMessage = 'Post '.($index + 2).' may not be greater than 1000 characters.';
                $this->persistDraft();

                return;
            }

            $poll = $this->validatedPostPoll($index);

            if ($poll === false) {
                $this->persistDraft();

                return;
            }

            $threadPosts[] = $trimmed;
            $threadPolls[] = $poll;
        }

        $pollOptions = [];

        if ($this->isPoll) {
            $pollOptions = array_values(array_filter(
                array_map(fn (mixed $option): string => mb_trim((string) $option), $this->pollOptions),
                fn (string $option): bool => $option !== ''
            ));

            if (count($pollOptions) < 2) {
                $this->errorMessage = 'A poll must have at least 2 options.';
                $this->persistDraft();

                return;
            }

            if (count($pollOptions) !== count($this->pollOptions)) {
                $this->errorMessage = 'All poll options are required.';
                $this->persistDraft();

                return;
            }

            foreach ($pollOptions as $option) {
                if (mb_strlen($option) > 40) {
                    $this->errorMessage = 'Poll options cannot exceed 40 characters.';
                    $this->persistDraft();

                    return;
                }
            }
        }

        if (! $this->captchaReadyForSubmission()) {
            return;
        }

        $this->busy = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->createThread(
            $token,
            $content,
            $threadPosts,
            $this->channelId,
            $this->channelId === null && $this->channelName !== '' ? $this->channelName : null,
            $pollOptions,
            $this->pollDuration,
            $threadPolls,
            $this->captchaPayload(),
        ));
        $this->captchaAttempted($response);

        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'The thread could not be published. Please try again.';
            $this->persistDraft();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, 'The thread could not be published. Please try again.');
            $this->persistDraft();

            return;
        }

        $this->clearDraft();
        $this->content = '';
        $this->posts = [];
        $this->postPolls = [];
        $this->images = [];
        $this->channelId = null;
        $this->channelName = '';
        $this->channelQuery = '';
        $this->channelResults = [];
        $this->pickerOpen = false;
        $this->isPoll = false;
        $this->pollOptions = ['', ''];
        $this->pollDuration = 1;
        $this->validationMessage = '';
        $this->navigate($this->route('mobile.feed'));
    }

    public function publishAsk(): void
    {
        if ($this->busy || $this->askUsername === '') {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $content = mb_trim($this->content);

        if ($content === '') {
            $this->errorMessage = 'Write your question first — it will be sent to @'.$this->askUsername.'.';

            return;
        }

        if (mb_strlen($content) > self::MAX_ASK_LENGTH) {
            $this->errorMessage = 'Questions may not be greater than 255 characters.';
            $this->persistDraft();

            return;
        }

        if (! $this->captchaReadyForSubmission()) {
            return;
        }

        $this->busy = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->askQuestion($token, $this->askUsername, $content, $this->anonymous, $this->captchaPayload()));
        $this->captchaAttempted($response);

        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'Your question could not be sent. Please try again.';
            $this->persistDraft();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, 'Your question could not be sent. Please try again.');
            $this->persistDraft();

            return;
        }

        $this->clearDraft();
        $this->content = '';
        $this->anonymous = $this->defaultAnonymousPreference();
        $this->validationMessage = '';
        $this->navigate($this->route('mobile.user', ['username' => $this->askUsername]));
    }

    public function render(): View
    {
        return view('native.compose');
    }

    private function clearMentionSuggestions(): void
    {
        $this->mentionResults = [];
        $this->mentionQuery = '';
        $this->mentionKind = 'user';
        $this->mentionPostIndex = -1;
        $this->mentionStart = -1;
        $this->mentionEnd = -1;
    }

    /** Jump runs PHP on the host, so device-local picker paths are inaccessible. */
    private function unreadableImageMessage(): string
    {
        if (getenv('JUMP_BRIDGE_PORT') !== false) {
            return 'Photos cannot be uploaded in the Jump app. The file is on your phone and the app server is on this machine, so it needs a real build.';
        }

        return 'That image could not be read from this device.';
    }

    private function addImage(string $url, string $name, string $path = ''): void
    {
        // Store image paths, not URLs: server cleanup matches paths, and hosts can change.
        $snippet = '!['.$name.']('.($path !== '' ? $path : $url).')';

        if (str_contains($this->content, $snippet)) {
            return;
        }

        $candidate = ($this->content !== '' && ! str_ends_with($this->content, "\n"))
            ? "\n".$snippet
            : $snippet;

        if (mb_strlen($this->content.$candidate) > $this->maxContentLength()) {
            $this->errorMessage = 'Adding this image will exceed the maximum content length.';

            return;
        }

        $this->images[] = ['url' => $url, 'path' => $path, 'name' => $name];

        $this->content .= $candidate;
    }

    /**
     * @param  array{url: string, name: string, path: string}  $image
     */
    private function imageSnippet(array $image): string
    {
        return '!['.$image['name'].']('.($image['path'] !== '' ? $image['path'] : $image['url']).')';
    }

    /**
     * @return array{options: list<string>, duration: int}|null|false
     *                                                                The payload, null when no poll, or false (with error set) when invalid.
     */
    private function validatedPostPoll(int $index): array|null|false
    {
        $poll = $this->postPolls[$index] ?? $this->emptyPostPoll();

        if (! $poll['isPoll']) {
            return null;
        }

        $options = array_values(array_filter(
            array_map(fn (mixed $option): string => mb_trim((string) $option), $poll['options']),
            fn (string $option): bool => $option !== ''
        ));

        if (count($options) < 2) {
            $this->errorMessage = 'Post '.($index + 2).'’s poll must have at least 2 options.';

            return false;
        }

        if (count($options) !== count($poll['options'])) {
            $this->errorMessage = 'All poll options are required.';

            return false;
        }

        foreach ($options as $option) {
            if (mb_strlen($option) > 40) {
                $this->errorMessage = 'Poll options cannot exceed 40 characters.';

                return false;
            }
        }

        $duration = $poll['duration'];

        if (! isset(self::THREAD_POLL_DURATIONS[$duration])) {
            $this->errorMessage = 'Post '.($index + 2).'’s poll has an invalid duration.';

            return false;
        }

        return ['options' => $options, 'duration' => $duration];
    }

    /** @return array{isPoll: bool, options: list<string>, duration: int} */
    private function emptyPostPoll(): array
    {
        return ['isPoll' => false, 'options' => ['', ''], 'duration' => 1];
    }

    private function searchChannels(string $token): void
    {
        $this->searchingChannels = true;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->channels($token, mb_trim($this->channelQuery)));

        $this->searchingChannels = false;

        if ($response === null || ! $response->successful()) {
            return;
        }

        /** @var list<array<string, mixed>> $channels */
        $channels = $response->json('data', []);
        $this->channelResults = $channels;
    }

    private function hydrateAvatar(): void
    {
        $user = CurrentUser::get() ?? AccountStore::active();

        if (! is_array($user)) {
            return;
        }

        $avatar = $user['avatar'] ?? null;
        $this->avatar = is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    private function defaultAnonymousPreference(): bool
    {
        return Payload::bool(data_get(CurrentUser::get(), 'prefers_anonymous_questions', false));
    }

    private function shouldFetchProfile(): bool
    {
        if ($this->avatar !== null) {
            return false;
        }

        $user = CurrentUser::get() ?? AccountStore::active();
        $name = is_array($user) ? Payload::string($user['name'] ?? '') : '';

        if ($name !== '') {
            return false;
        }

        return ! app()->runningUnitTests();
    }

    private function refreshAvatarFromProfile(string $token): void
    {
        $api = app(PinkaryApi::class);
        $profile = $api->attempt(fn () => $api->profile($token));

        if ($profile?->successful()) {
            $avatar = $profile->json('data.avatar');

            $this->avatar = is_string($avatar) && $avatar !== '' ? $avatar : null;
        }
    }

    private function restoreDraft(): void
    {
        $draft = DraftStore::get($this->draftTarget());

        if ($draft === null) {
            return;
        }

        $this->content = $draft['content'];
        $this->posts = $draft['posts'];
        $this->postPolls = $draft['postPolls'];
        $this->images = $draft['images'];
        $this->channelId = $draft['channelId'];
        $this->channelName = $draft['channelName'];
        $this->isPoll = $draft['isPoll'];
        $this->pollOptions = $draft['pollOptions'];
        $this->pollDuration = $draft['pollDuration'];
        $this->anonymous = $draft['anonymous'];
    }

    private function persistDraft(): void
    {
        DraftStore::put($this->draftTarget(), [
            'content' => $this->content,
            'posts' => $this->posts,
            'postPolls' => $this->postPolls,
            'images' => $this->images,
            'channelId' => $this->channelId,
            'channelName' => $this->channelName,
            'isPoll' => $this->isPoll,
            'pollOptions' => $this->pollOptions,
            'pollDuration' => $this->pollDuration,
            'anonymous' => $this->anonymous,
        ]);
    }

    private function clearDraft(): void
    {
        DraftStore::clear($this->draftTarget());
    }

    private function draftTarget(): ?string
    {
        return $this->askUsername !== '' ? $this->askUsername : null;
    }

    private function touchDraft(): void
    {
        $this->validateState();
        $this->persistDraft();
    }

    private function maxContentLength(): int
    {
        return $this->askUsername !== '' ? self::MAX_ASK_LENGTH : self::MAX_CONTENT_LENGTH;
    }

    private function overLimitMessage(): string
    {
        if ($this->contentTooLong()) {
            return 'Over the '.$this->maxContentLength().' character limit.';
        }

        foreach ($this->posts as $index => $post) {
            if (mb_strlen($post) > self::MAX_CONTENT_LENGTH) {
                return 'Post '.($index + 2).' is over the '.self::MAX_CONTENT_LENGTH.' character limit.';
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $options
     */
    private function pollOptionsAreValid(array $options): bool
    {
        $filled = 0;

        foreach ($options as $option) {
            $text = mb_trim($option);

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) > 40) {
                return false;
            }

            $filled++;
        }

        return $filled >= 2;
    }
}
