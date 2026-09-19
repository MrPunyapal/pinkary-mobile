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

        $liked = (bool) data_get($post, 'metrics.liked', false);
        $count = (int) data_get($post, 'metrics.likes', 0);

        data_set($post, 'metrics.liked', ! $liked);
        data_set($post, 'metrics.likes', max(0, $count + ($liked ? -1 : 1)));

        $response = $liked
            ? app(PinkaryApi::class)->unlike($token, $id)
            : app(PinkaryApi::class)->like($token, $id);

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

        $bookmarked = (bool) data_get($post, 'metrics.bookmarked', false);

        data_set($post, 'metrics.bookmarked', ! $bookmarked);

        $response = $bookmarked
            ? app(PinkaryApi::class)->unbookmark($token, $id)
            : app(PinkaryApi::class)->bookmark($token, $id);

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

        $response = app(PinkaryApi::class)->votePoll($token, $id, $optionId);

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
