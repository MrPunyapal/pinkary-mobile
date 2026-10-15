<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensChannels;
use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\FeedCache;
use App\Support\Payload;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class Bookmarks extends NativeComponent implements HasWebHead
{
    use OpensChannels;
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
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

    public function mount(): void
    {
        $token = $this->requireToken(replaceScreen: true);

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

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readPostPayload($this->posts, $id);

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

    public function toggleLike(string $id): void
    {
        $this->reactOnPostPayload($this->posts, $id, 'like', $this->token());
    }

    public function toggleRepost(string $id): void
    {
        $this->reactOnPostPayload($this->posts, $id, 'repost', $this->token());
    }

    public function toggleBookmark(string $id): void
    {
        $hasRootPost = count(array_filter(
            $this->posts,
            static fn (array $post): bool => Payload::string(data_get($post, 'id')) === $id,
        )) > 0;

        $this->reactOnPostPayload($this->posts, $id, 'bookmark', $this->token());

        $post = $this->readPostPayload($this->posts, $id);

        if ($post === null || (bool) data_get($post, 'metrics.bookmarked', false)) {
            return;
        }

        $this->removePostForMenu($id);

        if ($hasRootPost && $this->hasMore) {
            $this->page = max(0, $this->page - 1);
        }

        $this->rememberBookmarks();
    }

    public function sharePost(string $id): void
    {
        $post = $this->readPostPayload($this->posts, $id);

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            Payload::string(data_get($post, 'to.username', '')),
            $id,
            $this->postSource($post, 'answer') ?? $this->postSource($post, 'content'),
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

        $page = $this->page + 1;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->bookmarks($token, $page));

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
        $this->posts = $this->mergeFeedPage($this->posts, $next);
        $this->page = $page;
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
