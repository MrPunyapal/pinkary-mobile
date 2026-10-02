<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\FeedCache;
use App\Support\UnreadCount;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Notifications extends NativeComponent
{
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

    public bool $markingRead = false;

    public function navTitle(): string
    {
        return 'Notifications';
    }

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

    /**
     * Delete the notification (like the web's ignore) and open its
     * subject — the question detail or the follower's profile. A
     * missing row is already gone, so 404 counts as success; when
     * the delete itself fails, fall back to marking it read.
     */
    public function open(string $id): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $target = null;

        foreach ($this->items as $index => $item) {
            if ((string) data_get($item, 'id') === $id) {
                $target = data_get($item, 'target');

                break;
            }
        }

        $api = app(PinkaryApi::class);
        $deleted = $api->attempt(fn () => $api->deleteNotification($token, $id));

        if ($deleted !== null && ($deleted->successful() || $deleted->notFound())) {
            // Deleted rows leave the list like read ones do, and the
            // delete returns no body — decrement locally instead.
            $this->removeNotification($id);
            $this->unreadCount = max(0, $this->unreadCount - 1);
            UnreadCount::store($this->unreadCount);
            $this->openTarget($target);

            return;
        }

        $response = $api->attempt(fn () => $api->markNotificationsRead($token, $id));

        // Fallback when the delete itself fails: mark read, drop the
        // row locally, and still navigate — the row was handled.
        if ($response !== null) {
            $this->removeNotification($id);
        }

        if ($response?->successful()) {
            $this->unreadCount = (int) $response->json('data.unread_count', max(0, $this->unreadCount - 1));
            UnreadCount::store($this->unreadCount);
        }

        $this->openTarget($target);
    }

    public function markAllRead(): void
    {
        if ($this->markingRead || $this->unreadCount === 0) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->markingRead = true;

        $api = app(PinkaryApi::class);

        // Web parity: ignore-all deletes the rows (the web's ignoreAll
        // deletes every notification). No bulk-delete endpoint exists,
        // so delete each loaded row best-effort before marking read —
        // deleted rows stay gone on reload instead of resurrecting.
        foreach ($this->items as $item) {
            $id = (string) data_get($item, 'id', '');

            if ($id !== '') {
                $api->attempt(fn () => $api->deleteNotification($token, $id));
            }
        }

        $response = $api->attempt(fn () => $api->markNotificationsRead($token));

        $this->markingRead = false;

        if ($response === null) {
            $this->errorMessage = 'Notifications could not be marked as read. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = 'Notifications could not be marked as read. Please try again.';

            return;
        }

        // "Ignore all" mirrors the web: the list is cleared, not kept
        // as read rows. Rows were deleted above; the mark-read clears
        // the badge server-side for any stragglers.
        $this->items = [];
        $this->unreadCount = (int) $response->json('data.unread_count', 0);
        UnreadCount::store($this->unreadCount);
        $this->page = 1;
        $this->hasMore = false;
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

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);
        $this->items = [...$this->items, ...$next];
        $this->page++;
        $this->hasMore = $response->json('links.next') !== null;
        $this->unreadCount = (int) $response->json('meta.unread_count', $this->unreadCount);
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

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data', []);
        $this->items = $items;
        $this->unreadCount = (int) $response->json('meta.unread_count', 0);
        UnreadCount::store($this->unreadCount);
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
        $this->loading = false;
        $this->refreshing = false;
        $this->rememberNotifications();
    }

    /**
     * Serve a cached page immediately. `$loading` stays false when the
     * list already has rows so skeletons never flash over content.
     */
    private function hydrateFromCache(): void
    {
        $payload = FeedCache::get(FeedCache::notificationsKey($this->cacheUsername()));

        if ($payload !== null) {
            $this->items = $payload['items'];
            $this->page = $payload['page'];
            $this->hasMore = $payload['hasMore'];
            $this->unreadCount = $payload['unreadCount'] ?? $this->unreadCount;
            UnreadCount::store($this->unreadCount);
            $this->loading = false;
            $this->refreshing = true;

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

    /**
     * Web parity (livewire/notifications/index.blade.php): the list shows
     * every row whose subject still exists — the web never filters on
     * read state. The badge (meta.unread_count) carries the pending
     * count; "Ignore all" deletes the rows like the web's ignoreAll.
     */
    private function removeNotification(string $id): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            fn (mixed $item): bool => (string) data_get($item, 'id') !== $id
        ));
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
