<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\Payload;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Throwable;

final class Search extends NativeComponent implements HasWebHead
{
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;

    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    /** @var list<array<string, mixed>> */
    public array $hashtags = [];

    /** @var list<array<string, mixed>> */
    public array $recommendations = [];

    /** @var list<array<string, mixed>> */
    public array $directoryUsers = [];

    public int $recommendationPage = 0;

    public int $directoryPage = 0;

    public bool $recommendationsHasMore = false;

    public bool $directoryHasMore = false;

    public bool $recommendationsLoading = false;

    public bool $directoryLoading = false;

    public string $recommendationsError = '';

    public string $directoryError = '';

    public bool $searching = false;

    public bool $searched = false;

    public string $errorMessage = '';

    /**
     * @var list<string>
     */
    public array $recentSearches = [];

    private int $discoveryRevision = 0;

    private int $recommendationsRequestId = 0;

    private int $directoryRequestId = 0;

    public function mount(): void
    {
        $this->loadDiscovery();
    }

    public function updatedQuery(): void
    {
        $this->search();
    }

    public function clearQuery(): void
    {
        $this->query = '';
        $this->search();
    }

    public function selectRecent(int $index): void
    {
        $query = $this->recentSearches[$index] ?? null;

        if (! is_string($query) || mb_trim($query) === '') {
            return;
        }

        $this->query = $query;
        $this->search();
    }

    public function search(): void
    {
        $token = $this->token();

        $query = mb_trim($this->query, "@# \t\n\r\0\x0B");

        if ($query === '') {
            $this->users = [];
            $this->hashtags = [];
            $this->searched = false;
            $this->searching = false;
            $this->errorMessage = '';
            $this->loadDiscovery();

            return;
        }

        $this->invalidateDiscovery();
        $this->users = [];
        $this->hashtags = [];
        $this->searched = false;

        $this->searching = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->search($token, $query));

        $this->searching = false;

        if ($response === null) {
            $this->errorMessage = 'Search could not be completed. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->users = $api->normalizeUsers($response->json('data.users', []));
        $this->hashtags = array_values(array_filter(
            Payload::array($response->json('data.hashtags', [])),
            is_array(...),
        ));
        $this->searched = true;
        $this->rememberRecent($query);
    }

    public function render(): View
    {
        return view('native.search');
    }

    public function toggleFollow(string $username): void
    {
        if ($username === '') {
            return;
        }

        $target = $this->followTarget($username);

        if ($target === null) {
            return;
        }

        $user = $target['user'];
        $following = Payload::bool(data_get($user, 'followed_by_me'));

        if (Payload::bool(data_get($user, 'is_me')) || Payload::bool(data_get($user, '_busy'))) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $user['_busy'] = true;
        $this->storeFollowTarget($target, $user);

        $api = app(PinkaryApi::class);
        $response = $following
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $user['_busy'] = false;
        $this->storeFollowTarget($target, $user);

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $user['followed_by_me'] = Payload::bool($response->json('data.followed'), ! $following);
        $this->storeFollowTarget($target, $user);
    }

    public function loadMoreRecommendations(): void
    {
        if ($this->queryText() !== '' || $this->recommendationsLoading) {
            return;
        }

        if (! $this->recommendationsHasMore && $this->recommendationPage > 0 && $this->recommendationsError === '') {
            return;
        }

        $this->fetchRecommendations($this->recommendationPage + 1, $this->discoveryRevision);
    }

    public function loadMoreDirectory(): void
    {
        if ($this->queryText() !== '' || $this->directoryLoading) {
            return;
        }

        if (! $this->directoryHasMore && $this->directoryPage > 0 && $this->directoryError === '') {
            return;
        }

        $this->fetchDirectory($this->directoryPage + 1, $this->discoveryRevision);
    }

    public function openHashtag(int $id): void
    {
        foreach ($this->hashtags as $hashtag) {
            if (Payload::int(data_get($hashtag, 'id')) !== $id) {
                continue;
            }

            $name = Payload::string(data_get($hashtag, 'name'));

            if ($id > 0 && $name !== '') {
                $this->navigate(
                    $this->route('mobile.hashtag', ['hashtagId' => $id]),
                    ['hashtagName' => $name],
                );
            }

            return;
        }

    }

    private function loadDiscovery(): void
    {
        if ($this->queryText() !== '' || $this->recommendationsLoading || $this->directoryLoading) {
            return;
        }

        $revision = ++$this->discoveryRevision;
        $this->recommendations = [];
        $this->directoryUsers = [];
        $this->recommendationPage = 0;
        $this->directoryPage = 0;
        $this->recommendationsHasMore = false;
        $this->directoryHasMore = false;
        $this->recommendationsError = '';
        $this->directoryError = '';

        $this->fetchRecommendations(1, $revision);
        $this->fetchDirectory(1, $revision);
    }

    private function invalidateDiscovery(): void
    {
        $this->discoveryRevision++;
        $this->recommendationsRequestId++;
        $this->directoryRequestId++;
        $this->recommendationsLoading = false;
        $this->directoryLoading = false;
    }

    private function fetchRecommendations(int $page, int $revision): void
    {
        $token = $this->token();
        $requestId = ++$this->recommendationsRequestId;
        $this->recommendationsLoading = true;
        $this->recommendationsError = '';

        $this->async(static function () use ($token, $page): array {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn (): Response => $api->userRecommendations($token, $page));

            if ($response === null) {
                return ['status' => 0, 'users' => [], 'hasMore' => false];
            }

            return [
                'status' => $response->status(),
                'users' => $response->json('data', []),
                'hasMore' => $response->json('links.next') !== null,
            ];
        })->finished(function (array $result) use ($token, $page, $revision, $requestId): void {
            if (! $this->isCurrentDiscoveryRequest($revision, $requestId, true)) {
                return;
            }

            $this->recommendationsLoading = false;
            $status = Payload::int($result['status'] ?? null);

            if ($status === 401 && $token !== null) {
                $this->handleUnauthorized(new Response(new PsrResponse($status)));

                return;
            }

            if ($status < 200 || $status >= 300) {
                $this->recommendationsError = 'Could not load recommendations. Please try again.';

                return;
            }

            $users = app(PinkaryApi::class)->normalizeUsers($result['users'] ?? null);
            $this->recommendations = $this->uniqueUsers(
                $page === 1 ? $users : [...$this->recommendations, ...$users],
            );
            $this->directoryUsers = $this->uniqueUsers($this->directoryUsers, $this->recommendations);
            $this->recommendationPage = $page;
            $this->recommendationsHasMore = Payload::bool($result['hasMore'] ?? null);
        })->failed(function (Throwable $exception) use ($revision, $requestId): void {
            if (! $this->isCurrentDiscoveryRequest($revision, $requestId, true)) {
                return;
            }

            $this->recommendationsLoading = false;
            $this->recommendationsError = 'Could not load recommendations. Please try again.';
        });
    }

    private function fetchDirectory(int $page, int $revision): void
    {
        $token = $this->token();
        $requestId = ++$this->directoryRequestId;
        $this->directoryLoading = true;
        $this->directoryError = '';

        $this->async(static function () use ($token, $page): array {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn (): Response => $api->userDirectory($token, $page));

            if ($response === null) {
                return ['status' => 0, 'users' => [], 'hasMore' => false];
            }

            return [
                'status' => $response->status(),
                'users' => $response->json('data', []),
                'hasMore' => $response->json('links.next') !== null,
            ];
        })->finished(function (array $result) use ($token, $page, $revision, $requestId): void {
            if (! $this->isCurrentDiscoveryRequest($revision, $requestId, false)) {
                return;
            }

            $this->directoryLoading = false;
            $status = Payload::int($result['status'] ?? null);

            if ($status === 401 && $token !== null) {
                $this->handleUnauthorized(new Response(new PsrResponse($status)));

                return;
            }

            if ($status < 200 || $status >= 300) {
                $this->directoryError = 'Could not load people. Please try again.';

                return;
            }

            $users = app(PinkaryApi::class)->normalizeUsers($result['users'] ?? null);
            $this->directoryUsers = $this->uniqueUsers(
                $page === 1 ? $users : [...$this->directoryUsers, ...$users],
                $this->recommendations,
            );
            $this->directoryPage = $page;
            $this->directoryHasMore = Payload::bool($result['hasMore'] ?? null);
        })->failed(function (Throwable $exception) use ($revision, $requestId): void {
            if (! $this->isCurrentDiscoveryRequest($revision, $requestId, false)) {
                return;
            }

            $this->directoryLoading = false;
            $this->directoryError = 'Could not load people. Please try again.';
        });
    }

    /**
     * @return array{collection: 'search'|'recommendations'|'directory', index: int, user: array<string, mixed>}|null
     */
    private function followTarget(string $username): ?array
    {
        foreach ([
            'search' => $this->users,
            'recommendations' => $this->recommendations,
            'directory' => $this->directoryUsers,
        ] as $collection => $users) {
            foreach ($users as $index => $user) {
                if (Payload::string(data_get($user, 'username')) === $username) {
                    return ['collection' => $collection, 'index' => $index, 'user' => $user];
                }
            }
        }

        return null;
    }

    /** @param array{collection: 'search'|'recommendations'|'directory', index: int, user: array<string, mixed>} $target */
    private function storeFollowTarget(array $target, array $user): void
    {
        match ($target['collection']) {
            'search' => $this->users[$target['index']] = $user,
            'recommendations' => $this->recommendations[$target['index']] = $user,
            'directory' => $this->directoryUsers[$target['index']] = $user,
        };
    }

    private function isCurrentDiscoveryRequest(int $revision, int $requestId, bool $recommendations): bool
    {
        return $this->queryText() === ''
            && $this->discoveryRevision === $revision
            && ($recommendations ? $this->recommendationsRequestId : $this->directoryRequestId) === $requestId;
    }

    private function queryText(): string
    {
        return mb_trim($this->query, "@# \t\n\r\0\x0B");
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @param  list<array<string, mixed>>  $excluded
     * @return list<array<string, mixed>>
     */
    private function uniqueUsers(array $users, array $excluded = []): array
    {
        $seen = [];

        foreach ($excluded as $user) {
            $username = Payload::string(data_get($user, 'username'));

            if ($username !== '') {
                $seen[$username] = true;
            }
        }

        $unique = [];

        foreach ($users as $user) {
            $username = Payload::string(data_get($user, 'username'));

            if ($username === '' || isset($seen[$username])) {
                continue;
            }

            $seen[$username] = true;
            $unique[] = $user;
        }

        return $unique;
    }

    private function rememberRecent(string $query): void
    {
        $this->recentSearches = array_slice(
            array_values(array_unique([$query, ...$this->recentSearches])),
            0,
            5,
        );
    }
}
