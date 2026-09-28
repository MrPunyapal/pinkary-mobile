<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\FeedCache;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Bookmarks extends NativeComponent
{
    use TogglesPostReactions;
    use UsesAppMenu;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public bool $loading = true;

    public bool $refreshing = false;

    public string $errorMessage = '';

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    /** @var list<string> */
    public array $expanded = [];

    public function navTitle(): string
    {
        return 'Bookmarks';
    }

    public function mount(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->load($token);
    }

    public function retry(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->load($token);
    }

    public function openDetail(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->navigate($this->route('mobile.detail', ['id' => $id]));
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

    public function goFeed(): void
    {
        $this->navigate($this->route('mobile.feed'));
    }

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readPostPayload($this->posts, $id);

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

    public function toggleLike(string $id): void
    {
        $this->reactOnPostPayload($this->posts, $id, 'like', $this->token());
    }

    public function toggleBookmark(string $id): void
    {
        $this->reactOnPostPayload($this->posts, $id, 'bookmark', $this->token());
    }

    /**
     * Share the post through the system sheet, mirroring the web's
     * share menu (which copies/shares the question URL).
     */
    public function sharePost(string $id): void
    {
        $post = $this->readPostPayload($this->posts, $id);

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            (string) data_get($post, 'to.username', ''),
            $id,
            (string) (data_get($post, 'answer') ?? data_get($post, 'content')),
        );
    }

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteOnPostPayload($this->posts, $id, $optionId, $this->token());
    }

    public function loadMore(): void
    {
        if ($this->loadingMore || ! $this->hasMore) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->loadingMore = true;
        $this->loadMoreError = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->bookmarks($token, $this->page + 1));

        $this->loadingMore = false;

        if ($response === null) {
            $this->loadMoreError = 'Could not load more bookmarks. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loadMoreError = 'Could not load more bookmarks. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $api->normalizeQuestions($response->json('data', []));
        $this->posts = [...$this->posts, ...$next];
        $this->page++;
        $this->hasMore = $response->json('links.next') !== null;
        $this->rememberBookmarks();
    }

    public function render(): View
    {
        return view('native.bookmarks');
    }

    private function load(string $token): void
    {
        $this->hydrateFromCache();
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->bookmarks($token));

        if ($response === null) {
            $this->failBookmarksLoad();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->failBookmarksLoad();

            return;
        }

        /** @var list<array<string, mixed>> $posts */
        $posts = $response->json('data', []);
        $this->posts = $api->normalizeQuestions($posts);
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
        $this->loading = false;
        $this->refreshing = false;
        $this->rememberBookmarks();
    }

    /**
     * Serve a cached page immediately. `$loading` stays false when the
     * list already has rows so skeletons never flash over content.
     */
    private function hydrateFromCache(): void
    {
        $payload = FeedCache::get(FeedCache::bookmarksKey($this->cacheUsername()));

        if ($payload !== null) {
            $this->posts = $payload['items'];
            $this->page = $payload['page'];
            $this->hasMore = $payload['hasMore'];
            $this->loading = false;
            $this->refreshing = true;

            return;
        }

        if ($this->posts !== []) {
            $this->loading = false;
            $this->refreshing = true;

            return;
        }

        $this->loading = true;
        $this->refreshing = false;
    }

    private function rememberBookmarks(): void
    {
        FeedCache::put(FeedCache::bookmarksKey($this->cacheUsername()), [
            'items' => $this->posts,
            'page' => $this->page,
            'hasMore' => $this->hasMore,
        ]);
    }

    private function failBookmarksLoad(): void
    {
        $keepStale = $this->refreshing || $this->posts !== [];
        $this->loading = false;
        $this->refreshing = false;

        if ($keepStale) {
            return;
        }

        $this->errorMessage = 'Your bookmarks could not be loaded. Please try again.';
    }

    private function cacheUsername(): string
    {
        $username = AccountStore::active()['username'] ?? null;

        return is_string($username) && $username !== '' ? $username : 'guest';
    }
}
