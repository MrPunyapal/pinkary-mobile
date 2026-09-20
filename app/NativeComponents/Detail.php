<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\ApiErrors;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Detail extends NativeComponent
{
    public const int MAX_COMMENT_LENGTH = 1000;

    use TogglesPostReactions;
    use UsesAppMenu;

    // Back arrow shows instead of the drawer hamburger (no overlap).
    protected bool $hidesDrawer = true;

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

    /**
     * Share the post through the system sheet, mirroring the web's
     * share menu (which copies/shares the question URL).
     */
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

    /**
     * Toggle Show more/less for a long answer, like the web's
     * showMore component.
     */
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

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        // The shared trait swallows vote failures silently; the detail
        // screen votes with feedback instead so a failed tap explains
        // itself instead of looking ignored.
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

    /**
     * Like the web question page, the reply composer only renders for
     * answered questions — an unanswered question takes an answer,
     * not a comment.
     */
    public function canComment(): bool
    {
        $answer = trim(html_entity_decode(strip_tags((string) data_get($this->post, 'answer', '')), ENT_QUOTES, 'UTF-8'));

        return $answer !== '';
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

        if (! $this->canComment()) {
            $this->commentError = 'You can comment once this question has an answer.';

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

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->comment($token, $this->id, $content));

        $this->commentBusy = false;

        if ($response === null) {
            $this->commentError = 'Your comment could not be posted. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->commentError = ApiErrors::first($response, 'Your comment could not be posted. Please try again.');

            return;
        }

        // Append oldest-first like the API lists comments, so the new
        // row stays in order and a later page cannot replay it out of
        // place (loadMoreComments dedupes by id as well).
        /** @var array<string, mixed> $created */
        $created = $response->json('data', []);

        if ($created !== []) {
            $this->comments = [...$this->comments, $created];
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

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->comments($token, $this->id, $this->commentPage + 1));

        $this->loadingMoreComments = false;

        if ($response === null) {
            $this->loadMoreCommentsError = 'Could not load more comments. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loadMoreCommentsError = 'Could not load more comments. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($this->comments as $row) {
            $seen[(string) data_get($row, 'id', '')] = true;
        }

        // A freshly posted comment may reappear inside the next page;
        // skip rows already on screen so none render twice.
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
     * Vote in the target row's poll, replacing its poll state with the
     * server's — and saying so when the vote does not go through.
     *
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

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

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

    /**
     * Route a like/bookmark tap to the rendered post payload, its
     * rendered ancestors, or the matching comment (including a
     * comment's own nested ancestors).
     */
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
     * Read any rendered row on the screen: the post, its ancestors,
     * or a comment (including a comment's nested ancestors).
     *
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

    /**
     * Keep the detached thread mirror consistent with the rendered
     * ancestors after a mutation (the blade renders post.thread.posts).
     */
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

    private function load(string $token): void
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

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
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

        $comments = $api->attempt(fn () => $api->comments($token, $this->id));

        if ($comments === null) {
            $this->comments = [];
            $this->commentsFailed = true;
            $this->commentPage = 1;
            $this->hasMoreComments = false;
            $this->loading = false;

            return;
        }

        if ($comments->unauthorized()) {
            app(TokenStorage::class)->clear();
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
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
