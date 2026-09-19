<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Home extends NativeComponent
{
    use TogglesPostReactions;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public bool $loading = true;

    public string $errorMessage = '';

    public string $tab = 'recent';

    public int $selectedTab = 0;

    /** @var list<string> */
    public array $expanded = [];

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    /** @var list<string> */
    private const TABS = ['recent', 'following', 'trending'];

    public function navTitle(): string
    {
        return 'Feed';
    }

    public function mount(): void
    {
        $this->loadFeed($this->token());
    }

    /**
     * Explicit per-tab handlers (no callback args) so every pill tap
     * resolves to a plain method call on the device.
     */
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

    /**
     * Toggle Show more/less for a long answer, like the web's
     * showMore component (600px clamp with a pink toggle button).
     */
    public function toggleShowMore(string $id): void
    {
        $this->toggleExpandedId($id);
    }

    public function retry(): void
    {
        $this->loadFeed($this->token());
    }

    public function openSearch(): void
    {
        $this->navigate($this->route('mobile.search'));
    }

    public function compose(): void
    {
        if ($this->token() === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->navigate($this->route('mobile.compose'));
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

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readPostPayload($this->posts, $id);

        if ($post === null) {
            return;
        }

        $this->translateHtml(data_get($post, $field));
    }

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteOnPostPayload($this->posts, $id, $optionId, $this->token());
    }

    /**
     * Toggle the authenticated user's like, updating the row optimistically
     * and reconciling with the server response (reverting on failure).
     * Nested thread ancestors resolve too.
     */
    public function toggleLike(string $id): void
    {
        $this->reactOnPostPayload($this->posts, $id, 'like', $this->token());
    }

    /**
     * Toggle the authenticated user's bookmark, updating the row
     * optimistically and reverting on failure. Nested thread
     * ancestors resolve too.
     */
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

    /**
     * Append the next feed page to the current list.
     */
    public function loadMore(): void
    {
        if ($this->loadingMore || ! $this->hasMore) {
            return;
        }

        $token = $this->token();

        $this->loadingMore = true;
        $this->loadMoreError = '';

        $response = app(PinkaryApi::class)->feed($token, $this->tab, $this->page + 1);

        $this->loadingMore = false;

        if ($response->unauthorized() && $token !== null) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loadMoreError = 'Could not load more posts. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);
        $this->posts = [...$this->posts, ...$next];
        $this->page++;
        $this->hasMore = $response->json('links.next') !== null;
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

        // Reset first so the switch is always visible (spinner), even
        // when the incoming page overlaps the previous tab's posts.
        $this->posts = [];
        $this->page = 1;
        $this->hasMore = false;
        $this->loadMoreError = '';

        $this->loadFeed($this->token());
    }

    private function loadFeed(?string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $response = app(PinkaryApi::class)->feed($token, $this->tab);

        if ($response->unauthorized() && $token !== null) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'The Pinkary feed could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $posts */
        $posts = $response->json('data', []);
        $this->posts = $posts;
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
        $this->loading = false;
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
