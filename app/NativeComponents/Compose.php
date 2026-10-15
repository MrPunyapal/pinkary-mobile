<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\NativeComponents\Concerns\UsesCaptcha;
use App\NativeComponents\Concerns\UsesComposerImages;
use App\NativeComponents\Concerns\UsesComposerMentions;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\AppearancePreference;
use App\Support\CurrentUser;
use App\Support\DraftStore;
use App\Support\Payload;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class Compose extends NativeComponent implements HasWebHead
{
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;
    use UsesCaptcha;
    use UsesComposerImages;
    use UsesComposerMentions;

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

    public int $channelSearchVersion = 0;

    public string $channelError = '';

    public bool $isPoll = false;

    /** @var list<string> */
    public array $pollOptions = ['', ''];

    public int $pollDuration = 1;

    public ?string $avatar = null;

    public string $askUsername = '';

    public bool $anonymous = false;

    /**
     * @var list<array{url: string, path: string, name: string, postIndex?: int}>
     */
    public array $images = [];

    public int $imagePostIndex = -1;

    public string $imagePickerId = '';

    public bool $pickingImages = false;

    public bool $uploadingImages = false;

    protected bool $hidesDrawer = true;

    public function mount(): void
    {
        AppearancePreference::apply();

        $this->askUsername = Payload::string($this->param('username'));

        $token = $this->requireToken(replaceScreen: true);

        if ($token === null) {
            return;
        }

        $this->hydrateAvatar();
        $this->anonymous = $this->defaultAnonymousPreference();
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
        if ($this->busy || $this->pickingImages || $this->uploadingImages) {
            return false;
        }

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
        if ($this->busy || $this->pickingImages || $this->uploadingImages || ! isset($this->posts[$index])) {
            return;
        }

        $posts = $this->posts;
        $postPolls = $this->postPolls;
        unset($posts[$index], $postPolls[$index]);

        $images = [];

        foreach ($this->images as $image) {
            $postIndex = $image['postIndex'] ?? -1;

            if ($postIndex === $index) {
                continue;
            }

            if ($postIndex > $index) {
                $image['postIndex'] = $postIndex - 1;
            }

            $images[] = $image;
        }

        $this->clearMentionSuggestions();
        $this->posts = array_values($posts);
        $this->postPolls = array_values($postPolls);
        $this->images = $images;
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
        if ($this->busy) {
            return;
        }

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

    public function publish(): void
    {
        if ($this->busy || $this->pickingImages || $this->uploadingImages) {
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

        $this->persistDraft();
        $this->busy = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        try {
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
            $captchaRejected = $this->captchaAttempted($response, 'post', $token);
        } finally {
            $this->busy = false;
        }

        if ($response === null) {
            $this->errorMessage = 'The thread could not be published. Please try again.';
            $this->persistDraft();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = $captchaRejected
                ? ''
                : ApiErrors::first($response, 'The thread could not be published. Please try again.');
            $this->persistDraft();

            return;
        }

        $this->clearDraft();
        $this->content = '';
        $this->posts = [];
        $this->postPolls = [];
        $this->images = [];
        $this->clearMentionSuggestions();
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
        if ($this->busy || $this->pickingImages || $this->uploadingImages || $this->askUsername === '') {
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

        $this->persistDraft();
        $this->busy = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        try {
            $response = $api->attempt(fn () => $api->askQuestion($token, $this->askUsername, $content, $this->anonymous, $this->captchaPayload()));
            $captchaRejected = $this->captchaAttempted($response, 'post', $token);
        } finally {
            $this->busy = false;
        }

        if ($response === null) {
            $this->errorMessage = 'Your question could not be sent. Please try again.';
            $this->persistDraft();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = $captchaRejected
                ? ''
                : ApiErrors::first($response, 'Your question could not be sent. Please try again.');
            $this->persistDraft();

            return;
        }

        $this->clearDraft();
        $this->content = '';
        $this->images = [];
        $this->clearMentionSuggestions();
        $this->anonymous = $this->defaultAnonymousPreference();
        $this->validationMessage = '';
        $this->navigate($this->route('mobile.user', ['username' => $this->askUsername]));
    }

    public function render(): View
    {
        return view('native.compose');
    }

    protected function mentionText(int $postIndex): ?string
    {
        if ($postIndex < -1) {
            return null;
        }

        return $postIndex === -1 ? $this->content : ($this->posts[$postIndex] ?? null);
    }

    protected function replaceMentionText(int $postIndex, string $text): void
    {
        if ($postIndex === -1) {
            $this->content = $text;

            return;
        }

        if (isset($this->posts[$postIndex])) {
            $this->posts[$postIndex] = $text;
        }
    }

    protected function mentionComposerBusy(): bool
    {
        return $this->busy;
    }

    protected function mentionChanged(int $postIndex): void
    {
        $this->touchDraft();
    }

    protected function imageText(int $postIndex): ?string
    {
        if ($postIndex < -1) {
            return null;
        }

        return $postIndex === -1 ? $this->content : ($this->posts[$postIndex] ?? null);
    }

    protected function replaceImageText(int $postIndex, string $text): void
    {
        if ($postIndex === -1) {
            $this->content = $text;

            return;
        }

        if (isset($this->posts[$postIndex])) {
            $this->posts[$postIndex] = $text;
        }
    }

    protected function imageTextLimit(): int
    {
        return $this->maxContentLength();
    }

    protected function imageComposerBusy(): bool
    {
        return $this->busy;
    }

    protected function imageError(string $message): void
    {
        $this->errorMessage = $message;
    }

    protected function imagesChanged(): void
    {
        $this->touchDraft();
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
        $query = mb_trim($this->channelQuery);
        $this->channelSearchVersion++;
        $requestVersion = $this->channelSearchVersion;
        $this->channelResults = [];
        $this->searchingChannels = true;

        $api = app(PinkaryApi::class);
        try {
            $response = $api->attempt(fn () => $api->channels($token, $query));
        } finally {
            if ($requestVersion === $this->channelSearchVersion) {
                $this->searchingChannels = false;
            }
        }

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful() || $requestVersion !== $this->channelSearchVersion
            || $query !== mb_trim($this->channelQuery)) {
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
