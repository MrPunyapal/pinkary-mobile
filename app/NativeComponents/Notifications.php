<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\FeedCache;
use App\Support\Payload;
use App\Support\UnreadCount;
use DateTimeImmutable;
use Exception;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class Notifications extends NativeComponent implements HasWebHead
{
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public int $unreadCount = 0;

    public bool $loading = true;

    public bool $refreshing = false;

    public string $errorMessage = '';

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    public bool $ignoringAll = false;

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

    public function open(string $id): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $notification = null;

        foreach ($this->items as $item) {
            if (Payload::string(data_get($item, 'id')) === $id) {
                $notification = $item;

                break;
            }
        }

        if ($notification === null) {
            return;
        }

        $target = data_get($notification, 'target');

        $isPendingQuestion = data_get($notification, 'is_pending_question') === true;
        $apiDoesNotIdentifyPendingQuestions = ! array_key_exists('is_pending_question', $notification)
            && data_get($notification, 'type') === 'QuestionCreated';

        if ($isPendingQuestion || $apiDoesNotIdentifyPendingQuestions) {
            $this->openTarget($target);

            return;
        }

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->deleteNotification($token, $id));

        if ($response === null) {
            $this->openTarget($target);

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if ($response->successful() || $response->notFound()) {
            if ($this->removeNotification($id)) {
                $this->unreadCount = max(0, $this->unreadCount - 1);
            }

            UnreadCount::store($this->unreadCount);
            $this->rememberNotifications();
            $this->openTarget($target);

            return;
        }

        $this->openTarget($target);
    }

    public function ignoreAll(PinkaryApi $api): void
    {
        if ($this->ignoringAll || ($this->items === [] && ! $this->hasMore && $this->unreadCount === 0)) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->ignoringAll = true;
        $until = new DateTimeImmutable(now()->toIso8601String());

        try {
            $items = [];
            $page = 1;
            $hasMore = true;
            $unreadCount = 0;

            while ($hasMore) {
                $response = $api->attempt(fn () => $api->notifications($token, $page));

                if ($response === null) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($this->handleUnauthorized($response)) {
                    return;
                }

                if (! $response->successful()) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($page === 1) {
                    $unreadCount = Payload::int($response->json('meta.unread_count'));
                }

                $items = [...$items, ...$api->normalizeNotifications($response->json('data', []))];
                $hasMore = $response->json('links.next') !== null;
                $page++;
            }

            $loadedPage = $page - 1;
            $notificationsToIgnore = array_values(array_filter(
                $items,
                fn (array $item): bool => $this->notificationIsInIgnoreWindow($item, $until)
            ));
            $this->items = $items;
            $this->page = $loadedPage;
            $this->hasMore = false;
            $this->unreadCount = $unreadCount;
            UnreadCount::store($this->unreadCount);
            $this->rememberNotifications();

            $questionIds = [];

            foreach ($notificationsToIgnore as $item) {
                if (data_get($item, 'type') !== 'QuestionCreated') {
                    continue;
                }

                $questionId = Payload::string(data_get($item, 'target.id'));

                if ($questionId !== '') {
                    $questionIds[$questionId] = $questionId;
                }
            }

            $ignoredQuestionIds = [];

            foreach ($questionIds as $questionId) {
                $response = $api->attempt(fn () => $api->ignoreQuestion($token, $questionId));

                if ($response === null) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($this->handleUnauthorized($response)) {
                    return;
                }

                if (! $response->successful() && ! $response->notFound()) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($response->successful()) {
                    $ignoredQuestionIds[$questionId] = true;
                    $this->removeQuestionNotifications($questionId);
                }
            }

            foreach ($notificationsToIgnore as $item) {
                $id = Payload::string(data_get($item, 'id', ''));

                if ($id === '') {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                $questionId = Payload::string(data_get($item, 'target.id'));

                if (data_get($item, 'target.kind') === 'question' && isset($ignoredQuestionIds[$questionId])) {
                    continue;
                }

                $response = $api->attempt(fn () => $api->deleteNotification($token, $id));

                if ($response === null) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($this->handleUnauthorized($response)) {
                    return;
                }

                if (! $response->successful() && ! $response->notFound()) {
                    $this->errorMessage = 'Notifications could not be ignored. Please try again.';

                    return;
                }

                if ($this->removeNotification($id)) {
                    $this->unreadCount = max(0, $this->unreadCount - 1);
                }

                UnreadCount::store($this->unreadCount);
                $this->rememberNotifications();
            }

            $this->errorMessage = '';
            $this->loadMoreError = '';
            $this->rememberNotifications();
        } finally {
            $this->ignoringAll = false;
        }
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
        $response = $api->attempt(fn () => $api->notifications($token, $this->page + 1));

        $this->loadingMore = false;

        if ($response === null) {
            $this->loadMoreError = 'Could not load more notifications. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loadMoreError = 'Could not load more notifications. Please try again.';

            return;
        }

        $next = $api->normalizeNotifications($response->json('data', []));
        $this->items = [...$this->items, ...$next];
        $this->page++;
        $this->hasMore = $response->json('links.next') !== null;
        $this->unreadCount = Payload::int($response->json('meta.unread_count'), $this->unreadCount);
        UnreadCount::store($this->unreadCount);
        $this->rememberNotifications();
    }

    public function render(): View
    {
        return view('native.notifications');
    }

    private function load(string $token): void
    {
        $this->hydrateFromCache();
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->notifications($token));

        if ($response === null) {
            $this->failNotificationsLoad();

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->failNotificationsLoad();

            return;
        }

        $this->items = $api->normalizeNotifications($response->json('data', []));
        $this->unreadCount = Payload::int($response->json('meta.unread_count'));
        UnreadCount::store($this->unreadCount);
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
        $this->loading = false;
        $this->refreshing = false;
        $this->rememberNotifications();
    }

    private function hydrateFromCache(): void
    {
        $payload = FeedCache::get(FeedCache::notificationsKey($this->cacheUsername()));

        if ($payload !== null) {
            $this->items = app(PinkaryApi::class)->normalizeNotifications($payload['items']);
            $this->page = $payload['page'];
            $this->hasMore = $payload['hasMore'];
            $this->unreadCount = $payload['unreadCount'] ?? $this->unreadCount;
            UnreadCount::store($this->unreadCount);
            $this->loading = false;
            $this->refreshing = true;
            $this->rememberNotifications();

            return;
        }

        if ($this->items !== []) {
            $this->loading = false;
            $this->refreshing = true;

            return;
        }

        $this->loading = true;
        $this->refreshing = false;
    }

    private function rememberNotifications(): void
    {
        FeedCache::put(FeedCache::notificationsKey($this->cacheUsername()), [
            'items' => $this->items,
            'page' => $this->page,
            'hasMore' => $this->hasMore,
            'unreadCount' => $this->unreadCount,
        ]);
    }

    private function failNotificationsLoad(): void
    {
        $keepStale = $this->refreshing || $this->items !== [];
        $this->loading = false;
        $this->refreshing = false;

        if ($keepStale) {
            return;
        }

        $this->errorMessage = 'Your notifications could not be loaded. Please try again.';
    }

    private function cacheUsername(): string
    {
        $username = AccountStore::active()['username'] ?? null;

        return is_string($username) && $username !== '' ? $username : 'guest';
    }

    /** @param array<string, mixed> $item */
    private function notificationIsInIgnoreWindow(array $item, DateTimeImmutable $until): bool
    {
        $createdAt = data_get($item, 'created_at');

        if (! is_string($createdAt) || $createdAt === '') {
            return true;
        }

        try {
            return new DateTimeImmutable($createdAt) <= $until;
        } catch (Exception) {
            return true;
        }
    }

    private function removeQuestionNotifications(string $questionId): void
    {
        $removedUnreadCount = 0;
        $this->items = array_values(array_filter(
            $this->items,
            function (mixed $item) use ($questionId, &$removedUnreadCount): bool {
                if (data_get($item, 'target.kind') !== 'question' || Payload::string(data_get($item, 'target.id')) !== $questionId) {
                    return true;
                }

                if (data_get($item, 'read') === false) {
                    $removedUnreadCount++;
                }

                return false;
            }
        ));
        $this->unreadCount = max(0, $this->unreadCount - $removedUnreadCount);
        UnreadCount::store($this->unreadCount);
        $this->rememberNotifications();
    }

    private function removeNotification(string $id): bool
    {
        $removedUnread = false;
        $this->items = array_values(array_filter(
            $this->items,
            function (mixed $item) use ($id, &$removedUnread): bool {
                if (Payload::string(data_get($item, 'id')) !== $id) {
                    return true;
                }

                $removedUnread = $removedUnread || data_get($item, 'read') === false;

                return false;
            }
        ));

        return $removedUnread;
    }

    private function openTarget(mixed $target): void
    {
        if (! is_array($target)) {
            return;
        }

        if (($target['kind'] ?? null) === 'question' && is_string($target['id'] ?? null) && $target['id'] !== '') {
            $this->navigate($this->route('mobile.detail', ['id' => $target['id']]));
        } elseif (($target['kind'] ?? null) === 'user' && is_string($target['username'] ?? null) && $target['username'] !== '') {
            $this->navigate($this->route('mobile.user', ['username' => $target['username']]));
        }
    }
}
