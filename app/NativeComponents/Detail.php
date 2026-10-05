<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\NativeComponents\Concerns\UsesCaptcha;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\DisplayText;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Detail extends NativeComponent
{
    use TogglesPostReactions;
    use UsesAppMenu;
    use UsesCaptcha;

    public const int MAX_COMMENT_LENGTH = 1000;

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

    public function navTitle(): string
    {
        return 'Post';
    }

    public function mount(): void
    {
        $this->id = (string) $this->param('id', '');

        if ($this->id === '') {
            $this->replace($this->route('mobile.feed'));

            return;
        }

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
            (string) data_get($post, 'to.username', ''),
            $id,
            (string) (data_get($post, 'answer') ?? data_get($post, 'content')),
        );
    }

    public function openLink(string $url): void
    {
        $this->openExternalLink($url);
    }

    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readDetailPost($id);

        if ($post === null) {
            return;
        }

        $this->translateHtml(data_get($post, $field));
    }

    public function toggleShowMore(string $id): void
    {
        $this->toggleExpandedId($id);
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

                if ($key === null) {
                    $target = &$this->post;
                } else {
                    $target = &$this->post['thread']['posts'][$key];
                }

                $this->voteWithFeedback($target, $id, $optionId, $token);
                $this->syncThreadMirror();

                return;
            }

            foreach ($this->comments as $index => $comment) {
                $location = $this->locateNestedPost([$comment], $id);

                if ($location === null) {
                    continue;
                }

                [, $key] = $location;

                if ($key === null) {
                    $target = &$this->comments[$index];
                } else {
                    $target = &$this->comments[$index]['thread']['posts'][$key];
                }

                $this->voteWithFeedback($target, $id, $optionId, $token);

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

    public function postComment(): void
    {
        if ($this->commentBusy) {
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

        if (! $this->captchaReadyForSubmission()) {
            return;
        }

        $this->commentBusy = true;
        $this->commentError = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->comment($token, $this->id, $content, $this->captchaPayload()));
        $this->captchaAttempted($response);

        $this->commentBusy = false;

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

        data_set($this->post, 'metrics.comments', (int) data_get($this->post, 'metrics.comments', 0) + 1);
        $this->comment = '';
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
            $seen[(string) data_get($row, 'id', '')] = true;
        }

        $next = array_values(array_filter(
            $next,
            fn (mixed $row): bool => is_array($row) && ! isset($seen[(string) data_get($row, 'id', '')])
        ));

        $this->comments = [...$this->comments, ...$next];
        $this->commentPage++;
        $this->hasMoreComments = $response->json('links.next') !== null;
    }

    public function render(): View
    {
        return view('native.detail');
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
            data_set($post, 'poll', $poll);
        }
    }

    private function reactOnLoadedPost(string $id, string $kind): void
    {
        if ($id === '') {
            return;
        }

        $token = $this->token();

        $posts = [$this->post];

        if ($this->reactOnPostPayload($posts, $id, $kind, $token)) {
            $this->post = $posts[0];
            $this->syncThreadMirror();

            return;
        }

        foreach ($this->comments as $index => $comment) {
            $list = [$comment];

            if ($this->reactOnPostPayload($list, $id, $kind, $token)) {
                $this->comments[$index] = $list[0];

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
        $rendered = array_values(array_filter(
            (array) data_get($this->post, 'thread.posts', []),
            fn (mixed $ancestor): bool => is_array($ancestor)
        ));

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

        data_set($this->post, 'thread.posts', $this->thread);
        data_set($this->post, 'thread.more', false);

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
