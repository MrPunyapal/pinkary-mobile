<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensChannels;
use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\Payload;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Throwable;

final class HashtagFeed extends NativeComponent implements HasWebHead
{
    use OpensChannels;
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use TogglesPostReactions;
    use UsesAppMenu;

    public int $hashtagId = 0;

    public string $hashtagName = '';

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

    private int $pageRequestId = 0;

    public function mount(): void
    {
        $this->hashtagId = Payload::int($this->param('hashtagId'));
        $this->hashtagName = mb_ltrim(Payload::string($this->data('hashtagName')), '#');

        if ($this->hashtagId < 1) {
            $this->replace($this->route('mobile.search'));

            return;
        }

        $this->load($this->token());
    }

    public function retry(): void
    {
        if ($this->hashtagId < 1) {
            $this->replace($this->route('mobile.search'));

            return;
        }

        $this->load($this->token());
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

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteOnPostPayload($this->posts, $id, $optionId, $this->token());
    }

    public function loadMore(): void
    {
        if ($this->loading || $this->loadingMore || ! $this->hasMore) {
            return;
        }

        $token = $this->token();
        $hashtagId = $this->hashtagId;
        $page = $this->page;
        $requestId = ++$this->pageRequestId;
        $this->loadingMore = true;
        $this->loadMoreError = '';

        $this->async(static function () use ($token, $hashtagId, $page): array {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn (): Response => $api->hashtagQuestions($token, $hashtagId, $page + 1));

            if ($response === null) {
                return ['status' => 0, 'posts' => [], 'hasMore' => false];
            }

            return [
                'status' => $response->status(),
                'posts' => $response->json('data', []),
                'hasMore' => $response->json('links.next') !== null,
            ];
        })->finished(function (array $result) use ($token, $page, $requestId): void {
            if (! $this->isCurrentPageRequest($page, $requestId)) {
                return;
            }

            $this->loadingMore = false;
            $status = Payload::int($result['status'] ?? null);

            if ($status === 401 && $token !== null) {
                $this->handleUnauthorized(new Response(new PsrResponse($status)));

                return;
            }

            if ($status < 200 || $status >= 300) {
                $this->loadMoreError = 'Could not load more posts. Please try again.';

                return;
            }

            $next = app(PinkaryApi::class)->normalizeQuestions($result['posts'] ?? null);
            $this->posts = $this->mergeFeedPage($this->posts, $next);
            $this->page = $page + 1;
            $this->hasMore = Payload::bool($result['hasMore'] ?? null);
            $this->loadMoreError = '';
        })->failed(function (Throwable $exception) use ($page, $requestId): void {
            if (! $this->isCurrentPageRequest($page, $requestId)) {
                return;
            }

            $this->loadingMore = false;
            $this->loadMoreError = 'Could not load more posts. Please try again.';
        });
    }

    public function onResume(): void
    {
        if (! $this->loadingMore) {
            return;
        }

        $this->pageRequestId++;
        $this->loadingMore = false;
        $this->loadMore();
    }

    public function render(): View
    {
        return view('native.hashtag-feed');
    }

    private function load(?string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn (): Response => $api->hashtagQuestions($token, $this->hashtagId));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'This hashtag feed could not be loaded. Please try again.';

            return;
        }

        $this->loading = false;

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = 'This hashtag feed could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $posts */
        $posts = $api->normalizeQuestions($response->json('data', []));
        $this->posts = $posts;
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
    }

    private function isCurrentPageRequest(int $page, int $requestId): bool
    {
        return $this->loadingMore && $this->page === $page && $this->pageRequestId === $requestId;
    }
}
