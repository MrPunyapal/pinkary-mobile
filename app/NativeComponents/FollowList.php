<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\Http\Client\Response;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class FollowList extends NativeComponent
{
    use UsesAppMenu;

    // Other users' lists show a back arrow instead of the drawer
    // hamburger (no overlap), like other users' profiles.
    public function hidesDrawer(): bool
    {
        return true;
    }

    public string $username = '';

    /**
     * followers or following — resolved from the route name, with a
     * mode param override for tests (routes carry no extra param).
     */
    public string $mode = 'followers';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    public bool $loading = true;

    public string $errorMessage = '';

    public int $page = 1;

    public bool $hasMore = false;

    public bool $loadingMore = false;

    public string $loadMoreError = '';

    /** @var list<string> */
    public array $busyUsernames = [];

    public function navTitle(): string
    {
        $label = $this->mode === 'following' ? 'Following' : 'Followers';

        return $this->username !== '' ? '@'.$this->username.' · '.$label : $label;
    }

    public function mount(): void
    {
        $this->username = (string) $this->param('username', '');

        $modeParam = (string) $this->param('mode', '');

        if ($modeParam === 'following' || $modeParam === 'followers') {
            $this->mode = $modeParam;
        } else {
            // Both routes render this screen — only the URI tells them
            // apart (the HTTP request is never routed on native
            // clients, so request()->route() is meaningless here).
            $uri = $this->nativeRouter?->currentUri();
            $this->mode = is_string($uri) && str_ends_with($uri, '/following') ? 'following' : 'followers';
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if ($this->username === '') {
            $this->loading = false;
            $this->errorMessage = 'This list could not be loaded. Please try again.';

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

    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }

    /**
     * Follow or unfollow a row inline, updating it in place.
     */
    public function toggleFollow(string $username): void
    {
        if ($username === '' || in_array($username, $this->busyUsernames, true)) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $index = null;
        $followed = false;

        foreach ($this->users as $key => $user) {
            if ((string) data_get($user, 'username', '') === $username) {
                $index = $key;
                $followed = (bool) data_get($user, 'followed_by_me', false);

                break;
            }
        }

        if ($index === null || (bool) data_get($this->users[$index], 'is_me', false)) {
            return;
        }

        $this->busyUsernames[] = $username;

        $api = app(PinkaryApi::class);
        $response = $followed
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $this->busyUsernames = array_values(array_diff($this->busyUsernames, [$username]));

        if ($response === null) {
            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            return;
        }

        data_set($this->users[$index], 'followed_by_me', (bool) $response->json('data.followed', ! $followed));
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
        $response = $api->attempt(fn () => $this->fetchPage($api, $token, $this->page + 1));

        $this->loadingMore = false;

        if ($response === null) {
            $this->loadMoreError = 'Could not load more users. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loadMoreError = 'Could not load more users. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);
        $this->users = [...$this->users, ...$next];
        $this->page++;
        $this->hasMore = $response->json('links.next') !== null;
    }

    public function render(): View
    {
        return view('native.follow-list');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $this->fetchPage($api, $token, 1));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'This list could not be loaded. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'This list could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $users */
        $users = $response->json('data', []);
        $this->users = $users;
        $this->page = 1;
        $this->hasMore = $response->json('links.next') !== null;
        $this->loadMoreError = '';
        $this->loading = false;
    }

    private function fetchPage(PinkaryApi $api, string $token, int $page): Response
    {
        return $this->mode === 'following'
            ? $api->following($token, $this->username, $page)
            : $api->followers($token, $this->username, $page);
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
