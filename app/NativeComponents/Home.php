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

    public function tabChanged(int $index): void
    {
        $index = isset(self::TABS[$index]) ? $index : 0;
        $this->selectedTab = $index;
        $this->switchTab(self::TABS[$index]);
    }

    public function retry(): void
    {
        $this->loadFeed($this->token());
    }

    public function profile(): void
    {
        $this->navigate($this->route('mobile.profile'));
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
        $index = $this->postIndex($id);

        if ($index === null) {
            return;
        }

        $this->translateHtml(data_get($this->posts[$index], $field));
    }

    public function votePoll(string $id, int $optionId): void
    {
        $index = $this->postIndex($id);

        if ($index === null) {
            return;
        }

        $this->voteOnPoll($this->posts[$index], $id, $optionId, $this->token());
    }

    /**
     * Toggle the authenticated user's like, updating the row optimistically
     * and reconciling with the server response (reverting on failure).
     */
    public function toggleLike(string $id): void
    {
        $index = $this->postIndex($id);

        if ($index === null) {
            return;
        }

        $this->reactLike($this->posts[$index], $id, $this->token());
    }

    /**
     * Toggle the authenticated user's bookmark, updating the row
     * optimistically and reverting on failure.
     */
    public function toggleBookmark(string $id): void
    {
        $index = $this->postIndex($id);

        if ($index === null) {
            return;
        }

        $this->reactBookmark($this->posts[$index], $id, $this->token());
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

    private function switchTab(string $tab): void
    {
        $this->tab = $tab;
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

    /**
     * Find a loaded post by its API id.
     */
    private function postIndex(string $id): ?int
    {
        if ($id === '') {
            return null;
        }

        foreach ($this->posts as $index => $post) {
            if ((string) data_get($post, 'id') === $id) {
                return $index;
            }
        }

        return null;
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
