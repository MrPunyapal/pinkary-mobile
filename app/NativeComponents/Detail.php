<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\NativeComponents\Concerns\UsesCaptcha;
use App\NativeComponents\Concerns\UsesComposerImages;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\DisplayText;
use App\Support\DraftStore;
use App\Support\Payload;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class Detail extends NativeComponent implements HasWebHead
{
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use TogglesPostReactions;
    use UsesAppMenu;
    use UsesCaptcha;
    use UsesComposerImages;

    public const int MAX_COMMENT_LENGTH = 1000;

    public const int MIN_REPLY_POLL_OPTIONS = 2;

    public const int MAX_REPLY_POLL_OPTIONS = 4;

    public const int MAX_REPLY_POLL_OPTION_LENGTH = 40;

    /**
     * @var array<int, string>
     */
    public const array REPLY_POLL_DURATIONS = [
        1 => '1 day',
        2 => '2 days',
        3 => '3 days',
        4 => '4 days',
        5 => '5 days',
        6 => '6 days',
        7 => '7 days',
    ];

    public string $id = '';

    /** @var array<string, mixed> */
    public array $post = [];

    /** @var list<array<string, mixed>> */
    public array $thread = [];

    /** @var list<array<string, mixed>> */
    public array $comments = [];

    public bool $loading = true;

    public string $errorMessage = '';

    public string $comment = '';

    public bool $commentIsPoll = false;

    /** @var list<string> */
    public array $commentPollOptions = ['', ''];

    public int $commentPollDuration = 1;

    /** @var list<array{url: string, path: string, name: string, postIndex?: int}> */
    public array $images = [];

    public int $imagePostIndex = -1;

    public string $imagePickerId = '';

    public bool $pickingImages = false;

    public bool $uploadingImages = false;

    public bool $commentBusy = false;

    public string $commentError = '';

    public int $commentPage = 1;

    public bool $hasMoreComments = false;

    public bool $loadingMoreComments = false;

    public string $loadMoreCommentsError = '';

    public bool $commentsFailed = false;

    public string $voteError = '';

    /** @var list<string> */
    public array $expanded = [];

    protected bool $hidesDrawer = true;

    public function mount(): void
    {
        $this->id = Payload::string($this->param('id'));

        if ($this->id === '') {
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->restoreReplyDraft();
        $this->load($this->token());
        if (($token = $this->token()) !== null) {
            $this->loadCaptcha('comment', $token);
        }
    }

    public function retry(): void
    {
        if ($this->id === '') {
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->load($this->token());
    }

    public function openDetail(string $id): void
    {
        if ($id === '' || $id === $this->id) {
            return;
        }

        $this->navigate($this->route('mobile.detail', ['id' => $id]));
    }

    public function toggleLike(string $id): void
    {
        $this->reactOnLoadedPost($id, 'like');
    }

    public function toggleRepost(string $id): void
    {
        $this->reactOnLoadedPost($id, 'repost');
    }

    public function toggleBookmark(string $id): void
    {
        $this->reactOnLoadedPost($id, 'bookmark');
    }

    public function sharePost(string $id): void
    {
        $post = $this->readDetailPost($id);

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            Payload::string(data_get($post, 'to.username', '')),
            $id,
            $this->postSource($post, 'answer') ?? $this->postSource($post, 'content'),
        );
    }

    public function openLink(string $url): void
    {
        $this->openExternalLink($url);
    }

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readDetailPost($id);

        if ($post === null) {
            return;
        }

        $this->closeMenu();
        $this->translateText($this->postSource($post, $field));
    }

    public function showMore(string $id): void
    {
        $this->expandPost($id);
    }

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteError = '';

        if ($id === '') {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        if (! $this->acquireReactionLock('vote:'.$id)) {
            return;
        }

        try {
            $location = $this->locateNestedPost([$this->post], $id);

            if ($location !== null) {
                [, $key] = $location;

                $this->post = $this->voteOnPost($this->post, $key, $id, $optionId, $token);
                $this->syncThreadMirror();

                return;
            }

            foreach ($this->comments as $index => $comment) {
                $location = $this->locateNestedPost([$comment], $id);

                if ($location === null) {
                    continue;
                }

                [, $key] = $location;

                $this->comments[$index] = $this->voteOnPost($comment, $key, $id, $optionId, $token);

                return;
            }
        } finally {
            $this->releaseReactionLock('vote:'.$id);
        }
    }

    public function canComment(): bool
    {
        return DisplayText::plain(data_get($this->post, 'answer', '')) !== '';
    }

    public function updatedComment(): void
    {
        $this->persistReplyDraft();
    }

    public function toggleCommentPoll(): void
    {
        if ($this->commentBusy) {
            return;
        }

        $this->commentIsPoll = ! $this->commentIsPoll;

        if ($this->commentIsPoll && count($this->commentPollOptions) < self::MIN_REPLY_POLL_OPTIONS) {
            $this->commentPollOptions = array_fill(0, self::MIN_REPLY_POLL_OPTIONS, '');
        }

        $this->persistReplyDraft();
    }

    public function addCommentPollOption(): void
    {
        if ($this->commentBusy || count($this->commentPollOptions) >= self::MAX_REPLY_POLL_OPTIONS) {
            return;
        }

        $this->commentPollOptions[] = '';
        $this->persistReplyDraft();
    }

    public function removeCommentPollOption(int $index): void
    {
        if ($this->commentBusy || count($this->commentPollOptions) <= self::MIN_REPLY_POLL_OPTIONS || ! isset($this->commentPollOptions[$index])) {
            return;
        }

        unset($this->commentPollOptions[$index]);
        $this->commentPollOptions = array_values($this->commentPollOptions);
        $this->persistReplyDraft();
    }

    public function updateCommentPollOption(int $index, string $text): void
    {
        if ($this->commentBusy || ! isset($this->commentPollOptions[$index])) {
            return;
        }

        $this->commentPollOptions[$index] = mb_substr($text, 0, self::MAX_REPLY_POLL_OPTION_LENGTH);
        $this->persistReplyDraft();
    }

    public function setCommentPollDuration(int $days): void
    {
        if ($this->commentBusy || ! isset(self::REPLY_POLL_DURATIONS[$days])) {
            return;
        }

        $this->commentPollDuration = $days;
        $this->persistReplyDraft();
    }

    public function postComment(): void
    {
        if ($this->commentBusy || $this->pickingImages || $this->uploadingImages) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        if (! $this->canComment()) {
            $this->commentError = 'You can comment once this question has an answer.';

            return;
        }

        $content = mb_trim($this->comment);

        if ($content === '') {
            $this->commentError = 'Write a comment first.';

            return;
        }

        if (mb_strlen($content) > self::MAX_COMMENT_LENGTH) {
            $this->commentError = 'Comments may not be greater than 1000 characters.';

            return;
        }

        $pollOptions = [];

        if ($this->commentIsPoll) {
            if (count($this->commentPollOptions) < self::MIN_REPLY_POLL_OPTIONS
                || count($this->commentPollOptions) > self::MAX_REPLY_POLL_OPTIONS) {
                $this->commentError = 'A reply poll must have 2 to 4 options.';

                return;
            }

            $pollOptions = array_map(mb_trim(...), $this->commentPollOptions);

            foreach ($pollOptions as $option) {
                if ($option === '') {
                    $this->commentError = 'All reply poll options are required.';

                    return;
                }

                if (mb_strlen($option) > self::MAX_REPLY_POLL_OPTION_LENGTH) {
                    $this->commentError = 'Reply poll options cannot exceed 40 characters.';

                    return;
                }
            }

            if (! isset(self::REPLY_POLL_DURATIONS[$this->commentPollDuration])) {
                $this->commentError = 'Choose a reply poll duration from 1 to 7 days.';

                return;
            }
        }

        if (! $this->captchaReadyForSubmission()) {
            return;
        }

        $this->persistReplyDraft();
        $this->commentBusy = true;
        $this->commentError = '';

        $api = app(PinkaryApi::class);
        try {
            $response = $api->attempt(fn () => $api->comment(
                $token,
                $this->id,
                $content,
                $this->captchaPayload(),
                $pollOptions,
                $this->commentPollDuration,
            ));
            $this->captchaAttempted($response);
        } finally {
            $this->commentBusy = false;
        }

        if ($response === null) {
            $this->commentError = 'Your comment could not be posted. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->commentError = ApiErrors::first($response, 'Your comment could not be posted. Please try again.');

            return;
        }

        /** @var array<string, mixed> $created */
        $created = $response->json('data', []);

        if ($created !== []) {
            $this->comments = [...$this->comments, $api->normalizeQuestion($created)];
        }

        $metrics = Payload::array($this->post['metrics'] ?? null);
        $metrics['comments'] = Payload::int($metrics['comments'] ?? null) + 1;

        $this->post['metrics'] = $metrics;
        $this->comment = '';
        $this->commentIsPoll = false;
        $this->commentPollOptions = ['', ''];
        $this->commentPollDuration = 1;
        $this->images = [];
        $this->clearMentionSuggestions();
        DraftStore::clearReplyDraft($this->id);
    }

    public function loadMoreComments(): void
    {
        if ($this->loadingMoreComments || ! $this->hasMoreComments) {
            return;
        }

        $token = $this->token();

        $this->loadingMoreComments = true;
        $this->loadMoreCommentsError = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->comments($token, $this->id, $this->commentPage + 1));

        $this->loadingMoreComments = false;

        if ($response === null) {
            $this->loadMoreCommentsError = 'Could not load more comments. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loadMoreCommentsError = 'Could not load more comments. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $api->normalizeQuestions($response->json('data', []));

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($this->comments as $row) {
            $seen[Payload::string(data_get($row, 'id', ''))] = true;
        }

        $next = array_values(array_filter(
            $next,
            fn (array $row): bool => ! isset($seen[Payload::string(data_get($row, 'id', ''))])
        ));

        $this->comments = [...$this->comments, ...$next];
        $this->commentPage++;
        $this->hasMoreComments = $response->json('links.next') !== null;
    }

    public function render(): View
    {
        return view('native.detail');
    }

    protected function mentionText(int $postIndex): ?string
    {
        if ($postIndex === -2) {
            return in_array($this->menuStep, ['answer', 'edit'], true) ? $this->editAnswer : null;
        }

        return $postIndex === -1 ? $this->comment : null;
    }

    protected function replaceMentionText(int $postIndex, string $text): void
    {
        if ($postIndex === -2 && in_array($this->menuStep, ['answer', 'edit'], true)) {
            $this->editAnswer = $text;
        } elseif ($postIndex === -1) {
            $this->comment = $text;
        }
    }

    protected function mentionComposerBusy(): bool
    {
        return in_array($this->menuStep, ['answer', 'edit'], true) ? $this->menuBusy : $this->commentBusy;
    }

    protected function mentionChanged(int $postIndex): void
    {
        if ($postIndex === -1) {
            $this->persistReplyDraft();
        }
    }

    protected function imageText(int $postIndex): ?string
    {
        return $postIndex === -1 ? $this->comment : null;
    }

    protected function replaceImageText(int $postIndex, string $text): void
    {
        if ($postIndex === -1) {
            $this->comment = $text;
        }
    }

    protected function imageTextLimit(): int
    {
        return self::MAX_COMMENT_LENGTH;
    }

    protected function imageComposerBusy(): bool
    {
        return $this->commentBusy;
    }

    protected function imageError(string $message): void
    {
        $this->commentError = $message;
    }

    protected function imagesChanged(): void
    {
        $this->persistReplyDraft();
    }

    private function restoreReplyDraft(): void
    {
        $draft = DraftStore::getReplyDraft($this->id);

        if ($draft === null) {
            return;
        }

        $this->comment = $draft['content'];
        $this->images = $draft['images'];
        $this->commentIsPoll = $draft['isPoll'];
        $this->commentPollOptions = $draft['pollOptions'];
        $this->commentPollDuration = $draft['pollDuration'];
    }

    private function persistReplyDraft(): void
    {
        if ($this->id === '') {
            return;
        }

        DraftStore::putReplyDraft($this->id, [
            'content' => $this->comment,
            'images' => $this->images,
            'isPoll' => $this->commentIsPoll,
            'pollOptions' => $this->commentPollOptions,
            'pollDuration' => $this->commentPollDuration,
        ]);
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function voteWithFeedback(array &$post, string $id, int $optionId, ?string $token): void
    {
        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->votePoll($token, $id, $optionId));

        if ($response === null) {
            $this->voteError = 'Your vote could not be recorded. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->voteError = ApiErrors::first($response, 'Your vote could not be recorded. Please try again.');

            return;
        }

        /** @var array<string, mixed> $poll */
        $poll = $response->json('data', []);

        if ($poll !== []) {
            $post['poll'] = $poll;
        }
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function voteOnPost(array $post, int|string|null $key, string $id, int $optionId, ?string $token): array
    {
        if ($key === null) {
            $this->voteWithFeedback($post, $id, $optionId, $token);

            return $post;
        }

        $ancestors = Payload::array(data_get($post, 'thread.posts'));
        $ancestor = self::payloadRow($ancestors[$key] ?? null);

        $this->voteWithFeedback($ancestor, $id, $optionId, $token);

        $ancestors[$key] = $ancestor;
        $thread = Payload::array(data_get($post, 'thread'));
        $thread['posts'] = $ancestors;
        $post['thread'] = $thread;

        return $post;
    }

    private function reactOnLoadedPost(string $id, string $kind): void
    {
        if ($id === '') {
            return;
        }

        $token = $this->token();

        $posts = [$this->post];

        if ($this->reactOnPostPayload($posts, $id, $kind, $token)) {
            $this->post = self::payloadRow($posts[0]);
            $this->syncThreadMirror();

            return;
        }

        foreach ($this->comments as $index => $comment) {
            $list = [$comment];

            if ($this->reactOnPostPayload($list, $id, $kind, $token)) {
                $this->comments[$index] = self::payloadRow($list[0]);

                return;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDetailPost(string $id): ?array
    {
        $found = $this->readPostPayload([$this->post], $id);

        if ($found !== null) {
            return $found;
        }

        foreach ($this->comments as $comment) {
            $found = $this->readPostPayload([$comment], $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function syncThreadMirror(): void
    {
        $rendered = self::payloadRows(data_get($this->post, 'thread.posts'));

        if ($rendered !== []) {
            $this->thread = $rendered;
        }
    }

    private function load(?string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->show($token, $this->id));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'This post could not be loaded. It may have been removed.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'This post could not be loaded. It may have been removed.';

            return;
        }

        /** @var array<string, mixed> $post */
        $post = $response->json('data', []);
        $this->post = $api->normalizeQuestion($post);

        /** @var list<array<string, mixed>> $thread */
        $thread = $api->normalizeQuestions($response->json('thread', []));
        $this->thread = $thread;

        $mirror = Payload::array($this->post['thread'] ?? null);
        $mirror['posts'] = $this->thread;
        $mirror['more'] = false;

        $this->post['thread'] = $mirror;

        $comments = $api->attempt(fn () => $api->comments($token, $this->id));

        if ($comments === null) {
            $this->comments = [];
            $this->commentsFailed = true;
            $this->commentPage = 1;
            $this->hasMoreComments = false;
            $this->loading = false;

            return;
        }

        if ($token !== null && $this->handleUnauthorized($comments)) {
            return;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $comments->successful() ? $api->normalizeQuestions($comments->json('data', [])) : [];
        $this->comments = $rows;
        $this->commentsFailed = ! $comments->successful();
        $this->commentPage = 1;
        $this->hasMoreComments = $comments->json('links.next') !== null;
        $this->loading = false;
    }
}
