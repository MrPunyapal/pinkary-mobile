<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Notifications extends NativeComponent
{
    use UsesAppMenu;

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public int $unreadCount = 0;

    public bool $loading = true;

    public string $errorMessage = '';

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    public bool $markingRead = false;

    /**
     * Client-side only filter over the loaded page ("all" or "unread").
     * No API support for server-side filtering, so this never triggers
     * a request — the view filters $items by $filter instead.
     */
    public string $filter = 'all';

    public function navTitle(): string
    {
        return 'Notifications';
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

    /**
     * Mark the notification as read and open its subject — the question
     * detail or the follower's profile, like the web row link.
     */
    public function open(string $id): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $target = null;

        foreach ($this->items as $index => $item) {
            if ((string) data_get($item, 'id') === $id) {
                $target = data_get($item, 'target');
                data_set($this->items[$index], 'read', true);

                break;
            }
        }

        $response = app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->markNotificationsRead($token, $id));

        if ($response?->successful()) {
            $this->unreadCount = (int) $response->json('data.unread_count', max(0, $this->unreadCount - 1));
        }

        if (! is_array($target)) {
            return;
        }

        if (($target['kind'] ?? null) === 'question' && is_string($target['id'] ?? null) && $target['id'] !== '') {
            $this->navigate($this->route('mobile.detail', ['id' => $target['id']]));
        } elseif (($target['kind'] ?? null) === 'user' && is_string($target['username'] ?? null) && $target['username'] !== '') {
            $this->navigate($this->route('mobile.user', ['username' => $target['username']]));
        }
    }

    public function markAllRead(): void
    {
        if ($this->markingRead || $this->unreadCount === 0) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->markingRead = true;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->markNotificationsRead($token));

        $this->markingRead = false;

        if ($response === null) {
            $this->errorMessage = 'Notifications could not be marked as read. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = 'Notifications could not be marked as read. Please try again.';

            return;
        }

        foreach ($this->items as $index => $item) {
            data_set($this->items[$index], 'read', true);
        }

        $this->unreadCount = (int) $response->json('data.unread_count', 0);
    }

    /**
     * Show every loaded notification (client-side only).
     */
    public function showAll(): void
    {
        $this->filter = 'all';
    }

    /**
     * Show only unread loaded notifications (client-side only).
     */
    public function showUnread(): void
    {
        $this->filter = 'unread';
    }

    public function goFeed(): void
    {
        $this->navigate($this->route('mobile.feed'));
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

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->notifications($token, $this->page + 1));

        $this->loadingMore = false;

        if ($response === null) {
            $this->loadMoreError = 'Could not load more notifications. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

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
    }

    public function render(): View
    {
        return view('native.notifications');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->notifications($token));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'Your notifications could not be loaded. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'Your notifications could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data', []);
        $this->items = $items;
        $this->unreadCount = (int) $response->json('meta.unread_count', 0);
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
