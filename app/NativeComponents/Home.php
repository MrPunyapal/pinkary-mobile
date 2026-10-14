<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use App\Support\FeedCache;
use App\Support\Payload;
use App\Support\UnreadCount;
use Illuminate\Http\Client\Response;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Throwable;

final class Home extends NativeComponent implements HasWebHead
{
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use TogglesPostReactions;
    use UsesAppMenu;

    /** @var list<string> */
    private const array TABS = ['recent', 'following', 'trending'];

    /** @var array<string, mixed>|null */
    public ?array $currentUser = null;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public bool $loading = true;

    public bool $refreshing = false;

    public string $errorMessage = '';

    public string $tab = 'recent';

    public int $selectedTab = 0;

    /** @var list<string> */
    public array $expanded = [];

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    public string $openedDetailId = '';

    private int $feedRevision = 0;

    public function signedIn(): bool
    {
        return $this->token() !== null;
    }

    public function openSearch(): void
    {
        $this->navigate($this->route('mobile.search'));
    }

    public function mount(): void
    {
        $token = $this->token();
        $this->refreshCurrentUser($token);
        $this->selectDefaultFeed();
        $this->loadFeed($token);
        $this->refreshUnreadBadge($token);
    }

    public function showRecent(): void
    {
        $this->switchTab(0);
    }

    public function showFollowing(): void
    {
        $this->switchTab(1);
    }

    public function showTrending(): void
    {
        $this->switchTab(2);
    }

    public function showMore(string $id): void
    {
        $this->expandPost($id);
    }

    public function retry(): void
    {
        $this->loadFeed($this->token());
    }

    public function refreshFeed(): void
    {
        $this->page = 1;
        $this->hasMore = false;
        $this->loadMoreError = '';
        $token = $this->token();
        $this->refreshCurrentUser($token);
        $this->loadFeed($token);
    }

    public function openDetail(string $id): void
    {
        if ($id === '' || $id === $this->openedDetailId) {
            return;
        }

        $this->openedDetailId = $id;
        $this->navigate($this->route('mobile.detail', ['id' => $id]));
    }

    public function onResume(): void
    {
        $this->openedDetailId = '';

        if ($this->loadingMore) {
            $this->feedRevision++;
            $this->loadingMore = false;
            $this->prefetchMore();
        }
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

    public function votePoll(string $id, int $optionId): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->navigate($this->route('mobile.login'));

            return;
        }

        $this->voteOnPostPayload($this->posts, $id, $optionId, $token);
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
        $this->reactOnPostPayload($this->posts, $id, 'bookmark', $this->token());
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

    public function prefetchMore(): void
    {
        if ($this->loadMoreError === '') {
            $this->loadMore();
        }
    }

    public function loadMore(): void
    {
        if ($this->loading || $this->loadingMore || ! $this->hasMore) {
            return;
        }

        $token = $this->token();
        $accountId = AccountStore::activeId();
        $tab = $this->tab;
        $page = $this->page;
        $revision = $this->feedRevision;
        $this->loadingMore = true;
        $this->loadMoreError = '';

        $this->async(static function () use ($token, $tab, $page): array {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn (): Response => $api->feed($token, $tab, $page + 1));

            if ($response === null) {
                return ['status' => 0, 'posts' => [], 'hasMore' => false];
            }

            return [
                'status' => $response->status(),
                'posts' => $response->json('data', []),
                'hasMore' => $response->json('links.next') !== null,
            ];
        })->finished(function (array $result) use ($token, $accountId, $tab, $page, $revision): void {
            if (! $this->isCurrentFeedRequest($accountId, $tab, $page, $revision)) {
                return;
            }

            $this->loadingMore = false;

            if ($result['status'] === 401 && $token !== null) {
                AccountStore::removeActive();
                $this->afterActiveAccountRemoved();

                return;
            }

            if ($result['status'] < 200 || $result['status'] >= 300) {
                $this->loadMoreError = 'Could not load more posts. Please try again.';

                return;
            }

            $next = app(PinkaryApi::class)->normalizeQuestions($result['posts']);
            $this->posts = $this->mergeFeedPage($this->posts, $next);
            $this->page = $page + 1;
            $this->hasMore = Payload::bool($result['hasMore']);
            $this->rememberFeed();
        })->failed(function (Throwable $exception) use ($accountId, $tab, $page, $revision): void {
            if ($this->isCurrentFeedRequest($accountId, $tab, $page, $revision)) {
                $this->loadingMore = false;
                $this->loadMoreError = 'Could not load more posts. Please try again.';
            }
        });
    }

    public function render(): View
    {
        return view('native.home');
    }

    private function switchTab(int $index): void
    {
        $index = isset(self::TABS[$index]) ? $index : 0;
        $this->selectedTab = $index;
        $this->tab = self::TABS[$index];
        $this->loadMoreError = '';

        $this->posts = [];
        $this->page = 1;
        $this->hasMore = false;

        $this->loadFeed($this->token());
    }

    private function loadFeed(?string $token): void
    {
        $this->feedRevision++;
        $this->loadingMore = false;
        $this->hydrateFromCache();
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn (): Response => $api->feed($token, $this->tab));

        if ($response === null) {
            $this->failFeedLoad();

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->failFeedLoad();

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
        $this->rememberFeed();
    }

    private function hydrateFromCache(): void
    {
        $payload = FeedCache::get(FeedCache::feedKey($this->cacheUsername(), $this->tab));

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

    private function rememberFeed(): void
    {
        FeedCache::put(FeedCache::feedKey($this->cacheUsername(), $this->tab), [
            'items' => $this->posts,
            'page' => $this->page,
            'hasMore' => $this->hasMore,
        ]);
    }

    private function isCurrentFeedRequest(?string $accountId, string $tab, int $page, int $revision): bool
    {
        return $this->feedRevision === $revision
            && $this->tab === $tab
            && $this->page === $page
            && AccountStore::activeId() === $accountId;
    }

    private function failFeedLoad(): void
    {
        $keepStale = $this->refreshing || $this->posts !== [];
        $this->loading = false;
        $this->refreshing = false;

        if ($keepStale) {
            return;
        }

        $this->errorMessage = 'The Pinkary feed could not be loaded. Please try again.';
    }

    private function cacheUsername(): ?string
    {
        $username = AccountStore::active()['username'] ?? null;

        return is_string($username) && $username !== '' ? $username : null;
    }

    private function refreshUnreadBadge(?string $token): void
    {
        if ($token === null) {
            return;
        }

        $this->async(static function () use ($token): array {
            $response = app(PinkaryApi::class)->notifications($token, 1, 1);

            return [
                'success' => $response->successful(),
                'count' => Payload::int($response->json('meta.unread_count', 0)),
            ];
        })->finished(static function (array $result) use ($token): void {
            if ($result['success'] && AccountStore::activeToken() === $token) {
                UnreadCount::store(Payload::int($result['count']));
            }
        })->failed(static function (Throwable $exception): void {});
    }

    private function refreshCurrentUser(?string $token): void
    {
        $this->currentUser = CurrentUser::get();

        if ($token === null) {
            return;
        }

        $needsProfile = $this->currentUser === null
            || empty($this->currentUser['avatar'])
            || ! in_array(data_get($this->currentUser, 'default_feed'), self::TABS, true)
            || ! is_bool(data_get($this->currentUser, 'prefers_anonymous_questions'));

        if ($needsProfile) {
            if (app()->runningUnitTests()) {
                return;
            }

            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn (): Response => $api->profile($token));

            if ($response?->successful() && is_array($data = $response->json('data')) && ! empty($data['username'])) {
                CurrentUser::store(Payload::stringMap($data));
                $this->currentUser = CurrentUser::get();
            }
        }
    }

    private function selectDefaultFeed(): void
    {
        $defaultFeed = data_get($this->currentUser, 'default_feed');

        if (! is_string($defaultFeed) || ! in_array($defaultFeed, self::TABS, true)) {
            return;
        }

        $this->tab = $defaultFeed;
        $selectedTab = array_search($defaultFeed, self::TABS, true);
        $this->selectedTab = is_int($selectedTab) ? $selectedTab : 0;
    }
}
