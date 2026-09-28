<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Search extends NativeComponent
{
    use UsesAppMenu;

    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    /** @var list<array<string, mixed>> */
    public array $hashtags = [];

    public bool $searching = false;

    public bool $searched = false;

    public string $errorMessage = '';

    /**
     * Recent queries from this session only (local state, never sent to
     * the API). Newest first, capped, shown when the query is empty.
     *
     * @var list<string>
     */
    public array $recentSearches = [];

    public function navTitle(): string
    {
        return 'Search';
    }

    public function updatedQuery(): void
    {
        $this->search();
    }

    /**
     * Clear the query and its results (clear-button path).
     */
    public function clearQuery(): void
    {
        $this->query = '';
        $this->users = [];
        $this->hashtags = [];
        $this->searched = false;
        $this->errorMessage = '';
    }

    /**
     * Re-run a recent search by list index (int arg, like the
     * profile's openUserLink — user-typed queries are never
     * interpolated into handler strings).
     */
    public function selectRecent(int $index): void
    {
        $query = $this->recentSearches[$index] ?? null;

        if (! is_string($query) || trim($query) === '') {
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

            return;
        }

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

        /** @var list<array<string, mixed>> $users */
        $users = $response->json('data.users', []);
        /** @var list<array<string, mixed>> $hashtags */
        $hashtags = $response->json('data.hashtags', []);

        $this->users = $users;
        $this->hashtags = $hashtags;
        $this->searched = true;
        $this->rememberRecent($query);
    }

    public function render(): View
    {
        return view('native.search');
    }

    /**
     * Follow or unfollow a user result row in place, mirroring
     * FollowList::toggleFollow: the row carries the in-flight lock (so a
     * double tap cannot fire twice), your own row is skipped like the
     * web's follow-button (components/follow-button.blade.php:7), and a
     * 401 goes through the shared unauthorized path.
     *
     * The label follows the web (js/follow-button.js:29): 'Unfollow'
     * once you follow them, 'Follow' otherwise. The web's third state,
     * "Follow Back", needs `isFollower` — UserResource ships no such
     * key, so it is not invented here.
     */
    public function toggleFollow(string $username): void
    {
        if ($username === '') {
            return;
        }

        $index = null;
        $following = false;

        foreach ($this->users as $key => $user) {
            if ((string) data_get($user, 'username', '') === $username) {
                $index = $key;
                $following = (bool) data_get($user, 'followed_by_me', false);

                break;
            }
        }

        if ($index === null) {
            return;
        }

        if ((bool) data_get($this->users[$index], 'is_me', false) || (bool) data_get($this->users[$index], '_busy', false)) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->users[$index]['_busy'] = true;

        $api = app(PinkaryApi::class);
        $response = $following
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $this->users[$index]['_busy'] = false;

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->users[$index]['followed_by_me'] = (bool) $response->json('data.followed', ! $following);
    }

    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }

    /**
     * Re-run the search for a tapped hashtag.
     */
    public function openHashtag(string $name): void
    {
        if ($name === '') {
            return;
        }

        $this->query = '#'.$name;
        $this->search();
    }

    /**
     * Remember a successful query for the recents list (newest
     * first, deduped, capped at five).
     */
    private function rememberRecent(string $query): void
    {
        $this->recentSearches = array_slice(
            array_values(array_unique([$query, ...$this->recentSearches])),
            0,
            5,
        );
    }
}
