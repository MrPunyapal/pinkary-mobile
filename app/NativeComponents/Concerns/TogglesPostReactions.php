<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Share;

/**
 * Optimistic like/bookmark toggles, poll votes, and link opening
 * for any screen rendering feed posts.
 *
 * @mixin NativeComponent
 */
trait TogglesPostReactions
{
    /** @var array<string, true> In-flight like/bookmark/vote locks per post+action. */
    private array $reactionLocks = [];

    /**
     * Double-tap guard: returns false when this post+action already has
     * a request in flight. Release in a finally block — replace()
     * redirects may throw past the call site.
     */
    private function acquireReactionLock(string $key): bool
    {
        if (isset($this->reactionLocks[$key])) {
            return false;
        }

        $this->reactionLocks[$key] = true;

        return true;
    }

    private function releaseReactionLock(string $key): void
    {
        unset($this->reactionLocks[$key]);
    }

    /**
     * Toggle the authenticated user's like on the given loaded post row,
     * reconciling with the server response (reverting on failure).
     *
     * @param  array<string, mixed>  $post
     */
    private function reactLike(array &$post, string $id, ?string $token): void
    {
        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $this->acquireReactionLock('like:'.$id)) {
            return;
        }

        try {
            $liked = (bool) data_get($post, 'metrics.liked', false);
            $count = (int) data_get($post, 'metrics.likes', 0);

            data_set($post, 'metrics.liked', ! $liked);
            data_set($post, 'metrics.likes', max(0, $count + ($liked ? -1 : 1)));

            $api = app(PinkaryApi::class);
            $response = $liked
                ? $api->attempt(fn () => $api->unlike($token, $id))
                : $api->attempt(fn () => $api->like($token, $id));

            if ($response === null) {
                data_set($post, 'metrics.liked', $liked);
                data_set($post, 'metrics.likes', $count);

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                data_set($post, 'metrics.liked', $liked);
                data_set($post, 'metrics.likes', $count);

                return;
            }

            data_set($post, 'metrics.liked', (bool) $response->json('data.liked', ! $liked));

            $likes = $response->json('data.likes');

            if (is_int($likes)) {
                data_set($post, 'metrics.likes', $likes);
            }
        } finally {
            $this->releaseReactionLock('like:'.$id);
        }
    }

    /**
     * Toggle the authenticated user's bookmark on the given loaded post
     * row, reverting on failure.
     *
     * @param  array<string, mixed>  $post
     */
    private function reactBookmark(array &$post, string $id, ?string $token): void
    {
        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $this->acquireReactionLock('bookmark:'.$id)) {
            return;
        }

        try {
            $bookmarked = (bool) data_get($post, 'metrics.bookmarked', false);

            data_set($post, 'metrics.bookmarked', ! $bookmarked);

            $api = app(PinkaryApi::class);
            $response = $bookmarked
                ? $api->attempt(fn () => $api->unbookmark($token, $id))
                : $api->attempt(fn () => $api->bookmark($token, $id));

            if ($response === null) {
                data_set($post, 'metrics.bookmarked', $bookmarked);

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                data_set($post, 'metrics.bookmarked', $bookmarked);

                return;
            }

            data_set($post, 'metrics.bookmarked', (bool) $response->json('data.bookmarked', ! $bookmarked));
        } finally {
            $this->releaseReactionLock('bookmark:'.$id);
        }
    }

    /**
     * Vote in the post's poll, replacing its poll state with the
     * server's (votes are toggles server-side too).
     *
     * @param  array<string, mixed>  $post
     */
    private function voteOnPoll(array &$post, string $id, int $optionId, ?string $token): void
    {
        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $this->acquireReactionLock('vote:'.$id)) {
            return;
        }

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->votePoll($token, $id, $optionId));

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

            /** @var array<string, mixed> $poll */
            $poll = $response->json('data', []);

            if ($poll !== []) {
                data_set($post, 'poll', $poll);
            }
        } finally {
            $this->releaseReactionLock('vote:'.$id);
        }
    }

    /**
     * Locate a post by id across a feed list, including nested thread
     * ancestors. Returns [top-level index, nested key|null].
     *
     * @param  list<array<string, mixed>>  $posts
     * @return array{0: int, 1: int|string|null}|null
     */
    private function locateNestedPost(array $posts, string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        foreach ($posts as $index => $post) {
            if (! is_array($post)) {
                continue;
            }

            if ((string) data_get($post, 'id') === $id) {
                return [$index, null];
            }

            foreach ((array) data_get($post, 'thread.posts', []) as $key => $ancestor) {
                if (is_array($ancestor) && (string) data_get($ancestor, 'id') === $id) {
                    return [$index, $key];
                }
            }
        }

        return null;
    }

    /**
     * Read a loaded post (top-level or nested thread ancestor) by id.
     * Returns a copy — mutations must go through the react/vote helpers.
     *
     * @param  list<array<string, mixed>>  $posts
     * @return array<string, mixed>|null
     */
    private function readPostPayload(array $posts, string $id): ?array
    {
        $location = $this->locateNestedPost($posts, $id);

        if ($location === null) {
            return null;
        }

        [$index, $key] = $location;

        $post = $posts[$index];

        if ($key === null) {
            return $post;
        }

        $ancestor = $post['thread']['posts'][$key] ?? null;

        return is_array($ancestor) ? $ancestor : null;
    }

    /**
     * Route a like/bookmark tap to a feed list, reaching nested thread
     * ancestors too (the web renders every chained post with live buttons).
     *
     * @param  list<array<string, mixed>>  $posts
     */
    private function reactOnPostPayload(array &$posts, string $id, string $kind, ?string $token): bool
    {
        $location = $this->locateNestedPost($posts, $id);

        if ($location === null) {
            return false;
        }

        [$index, $key] = $location;

        if ($key === null) {
            $target = &$posts[$index];
        } else {
            $target = &$posts[$index]['thread']['posts'][$key];
        }

        if ($kind === 'like') {
            $this->reactLike($target, $id, $token);
        } elseif ($kind === 'bookmark') {
            $this->reactBookmark($target, $id, $token);
        } else {
            return false;
        }

        return true;
    }

    /**
     * Route a poll vote to a feed list, reaching nested thread
     * ancestors too.
     *
     * @param  list<array<string, mixed>>  $posts
     */
    private function voteOnPostPayload(array &$posts, string $id, int $optionId, ?string $token): bool
    {
        $location = $this->locateNestedPost($posts, $id);

        if ($location === null) {
            return false;
        }

        [$index, $key] = $location;

        if ($key === null) {
            $this->voteOnPoll($posts[$index], $id, $optionId, $token);
        } else {
            $this->voteOnPoll($posts[$index]['thread']['posts'][$key], $id, $optionId, $token);
        }

        return true;
    }

    /**
     * Toggle an id in the screen's $expanded list (Show more/less),
     * mirroring the web's showMore Alpine component.
     */
    private function toggleExpandedId(string $id): void
    {
        if ($id === '') {
            return;
        }

        /** @var list<string> $expanded */
        $expanded = isset($this->expanded) && is_array($this->expanded) ? $this->expanded : [];

        $this->expanded = in_array($id, $expanded, true)
            ? array_values(array_diff($expanded, [$id]))
            : [...$expanded, $id];
    }

    /**
     * Share the post through the system sheet, mirroring the web's
     * share menu (which copies/shares the question URL).
     */
    private function shareQuestion(?string $username, string $id, ?string $html): void
    {
        if ($username === null || $username === '' || $id === '') {
            return;
        }

        $base = rtrim((string) config('services.pinkary.url'), '/');
        $url = $base.'/@'.$username.'/questions/'.$id;

        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

        app(Share::class)->url('Pinkary post', $text === '' ? $url : mb_substr($text, 0, 140), $url);
    }

    /**
     * Open an external link in the in-app browser. Only http(s)
     * URLs from loaded post data ever reach here.
     */
    private function openExternalLink(string $url): void
    {
        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
    }

    /**
     * Open Google Translate for the post's plain text, mirroring the
     * web's per-question and per-answer translate actions.
     */
    private function translateHtml(?string $html): void
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

        if ($text === '' || $text === '__UPDATE__') {
            return;
        }

        $this->openExternalLink('https://translate.google.com/?sl=auto&tl=en&text='.urlencode($text));
    }
}
