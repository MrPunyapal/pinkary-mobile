<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Detail extends NativeComponent
{
    public const int MAX_COMMENT_LENGTH = 1000;

    use TogglesPostReactions;

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

    public function navTitle(): string
    {
        return 'Post';
    }

    public function mount(): void
    {
        $this->id = (string) $this->param('id', '');

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if ($this->id === '') {
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->load($token);
    }

    public function retry(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if ($this->id === '') {
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->load($token);
    }

    /**
     * Drill into another post's detail. Tapping the open post does
     * nothing — the web question page never links a post to itself.
     */
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
        if ($id !== '' && (string) data_get($this->post, 'id') === $id) {
            $this->translateHtml(data_get($this->post, $field));

            return;
        }

        foreach ($this->thread as $ancestor) {
            if ($id !== '' && (string) data_get($ancestor, 'id') === $id) {
                $this->translateHtml(data_get($ancestor, $field));

                return;
            }
        }

        foreach ($this->comments as $comment) {
            if ($id !== '' && (string) data_get($comment, 'id') === $id) {
                $this->translateHtml(data_get($comment, $field));

                return;
            }
        }
    }

    public function votePoll(string $id, int $optionId): void
    {
        if ($id !== '' && (string) data_get($this->post, 'id') === $id) {
            $this->voteOnPoll($this->post, $id, $optionId, $this->token());

            return;
        }

        foreach ($this->thread as $index => $ancestor) {
            if ($id !== '' && (string) data_get($ancestor, 'id') === $id) {
                $this->voteOnPoll($this->thread[$index], $id, $optionId, $this->token());

                return;
            }
        }

        foreach ($this->comments as $index => $comment) {
            if ($id !== '' && (string) data_get($comment, 'id') === $id) {
                $this->voteOnPoll($this->comments[$index], $id, $optionId, $this->token());

                return;
            }
        }
    }

    public function postComment(): void
    {
        if ($this->commentBusy) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $content = trim($this->comment);

        if ($content === '') {
            $this->commentError = 'Write a comment first.';

            return;
        }

        if (mb_strlen($content) > self::MAX_COMMENT_LENGTH) {
            $this->commentError = 'Comments may not be greater than 1000 characters.';

            return;
        }

        $this->commentBusy = true;
        $this->commentError = '';

        $response = app(PinkaryApi::class)->comment($token, $this->id, $content);

        $this->commentBusy = false;

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->commentError = ApiErrors::first($response, 'Your comment could not be posted. Please try again.');

            return;
        }

        // Prepend so the new comment is immediately visible; the API lists
        // oldest first, so this is the only out-of-order row by design.
        /** @var array<string, mixed> $created */
        $created = $response->json('data', []);

        if ($created !== []) {
            $this->comments = [$created, ...$this->comments];
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

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->loadingMoreComments = true;
        $this->loadMoreCommentsError = '';

        $response = app(PinkaryApi::class)->comments($token, $this->id, $this->commentPage + 1);

        $this->loadingMoreComments = false;

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loadMoreCommentsError = 'Could not load more comments. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);
        $this->comments = [...$this->comments, ...$next];
        $this->commentPage++;
        $this->hasMoreComments = $response->json('links.next') !== null;
    }

    public function render(): View
    {
        return view('native.detail');
    }

    /**
     * Route a like/bookmark tap to the thread, the main post,
     * or the matching comment.
     */
    private function reactOnLoadedPost(string $id, string $kind): void
    {
        if ($id !== '' && (string) data_get($this->post, 'id') === $id) {
            $this->react($this->post, $id, $kind);

            return;
        }

        foreach ($this->thread as $index => $ancestor) {
            if ($id !== '' && (string) data_get($ancestor, 'id') === $id) {
                $this->react($this->thread[$index], $id, $kind);

                return;
            }
        }

        foreach ($this->comments as $index => $comment) {
            if ($id !== '' && (string) data_get($comment, 'id') === $id) {
                $this->react($this->comments[$index], $id, $kind);

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function react(array &$post, string $id, string $kind): void
    {
        if ($kind === 'like') {
            $this->reactLike($post, $id, $this->token());
        } else {
            $this->reactBookmark($post, $id, $this->token());
        }
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $response = app(PinkaryApi::class)->show($token, $this->id);

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'This post could not be loaded. It may have been removed.';

            return;
        }

        /** @var array<string, mixed> $post */
        $post = $response->json('data', []);
        $this->post = $post;

        /** @var list<array<string, mixed>> $thread */
        $thread = $response->json('thread', []);
        $this->thread = $thread;

        // The thread renders through the shared thread-post component,
        // which reads ancestors from the post's own thread payload.
        // The detail endpoint returns the full chain, never truncated.
        data_set($this->post, 'thread.posts', $this->thread);
        data_set($this->post, 'thread.more', false);

        $comments = app(PinkaryApi::class)->comments($token, $this->id);

        if ($comments->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $comments->successful() ? $comments->json('data', []) : [];
        $this->comments = $rows;
        $this->commentsFailed = ! $comments->successful();
        $this->commentPage = 1;
        $this->hasMoreComments = $comments->json('links.next') !== null;
        $this->loading = false;
    }

    private function token(): ?string
    {
        $token = session('pinkary_api_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
