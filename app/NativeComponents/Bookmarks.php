<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Bookmarks extends NativeComponent
{
    use TogglesPostReactions;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public bool $loading = true;

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
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

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

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->loadingMore = true;
        $this->loadMoreError = '';

        $response = app(PinkaryApi::class)->bookmarks($token, $this->page + 1);

        $this->loadingMore = false;

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loadMoreError = 'Could not load more bookmarks. Please try again.';

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
        return view('native.bookmarks');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $response = app(PinkaryApi::class)->bookmarks($token);

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'Your bookmarks could not be loaded. Please try again.';

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
