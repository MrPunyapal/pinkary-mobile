<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\DisplayText;
use App\Support\PostViews;
use Closure;
use Illuminate\Support\Carbon;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Share;
use Throwable;

/**
 * @mixin NativeComponent
 */
trait TogglesPostReactions
{
    public string $menuPostId = '';

    /** @var 'menu'|'confirm-delete'|'edit'|'likers' */
    public string $menuStep = 'menu';

    public string $menuError = '';

    public bool $menuBusy = false;

    public string $editAnswer = '';

    /** @var list<array<string, mixed>> */
    public array $likers = [];

    public int $likersPage = 1;

    public bool $likersHasMore = false;

    public string $likersError = '';

    /** @var array<string, true> postId:action entries hidden after a 403. */
    public array $menuDenied = [];

    /** @var array<string, true> In-flight like/bookmark/vote locks per post+action. */
    private array $reactionLocks = [];

    public function recordPostView(string $id): void
    {
        $post = $this->findPostForMenu($id);
        if ($post === null || DisplayText::plain($post['answer'] ?? null) === '') {
            return;
        }

        $token = $this->token();
        $viewer = PostViews::viewerId();
        if (PostViews::enqueue($id, $token, $viewer)) {
            $this->sendPostViews($token, $viewer, false);
        }
    }

    #[Poll(5000)]
    public function flushPostViews(): void
    {
        $this->sendPostViews($this->token(), PostViews::viewerId(), true);
    }

    public function unmount(): void
    {
        $this->flushPostViews();
        parent::unmount();
    }

    public function openMenu(string $id): void
    {
        if ($id === '' || $id === $this->menuPostId) {
            return;
        }

        if ($this->menuToken() === null) {
            return;
        }

        if ($this->findPostForMenu($id) === null) {
            return;
        }

        $this->menuPostId = $id;
        $this->menuStep = 'menu';
        $this->menuError = '';
        $this->menuBusy = false;
        $this->editAnswer = '';
        $this->likers = [];
        $this->likersPage = 1;
        $this->likersHasMore = false;
        $this->likersError = '';
    }

    public function closeMenu(): void
    {
        $this->menuPostId = '';
        $this->menuStep = 'menu';
        $this->menuError = '';
        $this->menuBusy = false;
        $this->editAnswer = '';
        $this->likers = [];
        $this->likersPage = 1;
        $this->likersHasMore = false;
        $this->likersError = '';
    }

    public function menuBack(): void
    {
        $this->menuStep = 'menu';
        $this->menuError = '';
    }

    public function menuDeniedFor(string $postId, string $action): bool
    {
        return isset($this->menuDenied[$postId.':'.$action]);
    }

    public function viewerOwnsMenuPost(): bool
    {
        return $this->viewerOwnsPost($this->menuPostData());
    }

    public function canPinMenuPost(): bool
    {
        $post = $this->menuPostData();

        if ($post === null || ! $this->viewerOwnsPost($post) || $this->menuDeniedFor($this->menuPostId, 'pin')) {
            return false;
        }

        $answer = data_get($post, 'answer');

        return is_string($answer) && mb_trim(strip_tags($answer)) !== '';
    }

    public function canEditMenuPost(): bool
    {
        $post = $this->menuPostData();

        if ($post === null || ! $this->viewerOwnsPost($post) || $this->menuDeniedFor($this->menuPostId, 'edit')) {
            return false;
        }

        $answer = data_get($post, 'answer');

        if (! is_string($answer) || mb_trim(strip_tags($answer)) === '') {
            return false;
        }

        $answeredAt = data_get($post, 'answered_at');

        if (! is_string($answeredAt) || $answeredAt === '') {
            return true;
        }

        try {
            return Carbon::parse($answeredAt)->greaterThan(now()->subHours(24));
        } catch (Throwable) {
            return true;
        }
    }

    public function canDeleteMenuPost(): bool
    {
        $post = $this->menuPostData();

        return $post !== null
            && $this->viewerOwnsPost($post)
            && ! $this->menuDeniedFor($this->menuPostId, 'delete');
    }

    public function canViewMenuLikes(): bool
    {
        $post = $this->menuPostData();

        if ($post === null || ! $this->viewerOwnsPost($post) || $this->menuDeniedFor($this->menuPostId, 'likes')) {
            return false;
        }

        return (int) data_get($post, 'metrics.likes', 0) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function menuPostData(): ?array
    {
        if ($this->menuPostId === '') {
            return null;
        }

        return $this->findPostForMenu($this->menuPostId);
    }

    public function togglePinMenu(): void
    {
        $id = $this->menuPostId;

        if ($id === '') {
            return;
        }

        $token = $this->menuToken();

        if ($token === null) {
            return;
        }

        $post = $this->findPostForMenu($id);

        if ($post === null || ! $this->viewerOwnsPost($post)) {
            $this->closeMenu();

            return;
        }

        if (! $this->acquireReactionLock('pin:'.$id)) {
            return;
        }

        try {
            $pinned = (bool) data_get($post, 'pinned', false);

            $api = app(PinkaryApi::class);
            $response = $pinned
                ? $api->attempt(fn () => $api->unpinQuestion($token, $id))
                : $api->attempt(fn () => $api->pinQuestion($token, $id));

            if ($response === null) {
                $this->menuError = 'The pin could not be updated. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if ($response->forbidden()) {
                $this->hideMenuEntry($id, 'pin');
                $this->closeMenu();

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'The pin could not be updated. Please try again.');

                return;
            }

            $next = (bool) $response->json('data.pinned', ! $pinned);

            $this->updatePostForMenu($id, function (array &$row) use ($next): void {
                data_set($row, 'pinned', $next);
            });

            $this->closeMenu();
        } finally {
            $this->releaseReactionLock('pin:'.$id);
        }
    }

    public function startEditMenu(): void
    {
        $post = $this->menuPostData();

        if ($post === null || ! $this->viewerOwnsPost($post)) {
            return;
        }

        $this->editAnswer = $this->plainText(data_get($post, 'answer'));
        $this->menuError = '';
        $this->menuStep = 'edit';
    }

    public function saveAnswerMenu(): void
    {
        $id = $this->menuPostId;

        if ($id === '' || $this->menuBusy) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null) {
            return;
        }

        $answer = mb_trim($this->editAnswer);

        if ($answer === '') {
            $this->menuError = 'Write an answer first.';

            return;
        }

        if (mb_strlen($answer) > 1000) {
            $this->menuError = 'Answers may not be greater than 1000 characters.';

            return;
        }

        if (! $this->acquireReactionLock('answer:'.$id)) {
            return;
        }

        $this->menuBusy = true;

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->updateAnswer($token, $id, $answer));

            if ($response === null) {
                $this->menuError = 'Your answer could not be saved. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'Your answer could not be saved. Please try again.');

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, 'edit');
                    $this->menuStep = 'menu';
                }

                return;
            }

            $saved = $response->json('data.answer');

            $this->updatePostForMenu($id, function (array &$row) use ($response, $saved): void {
                if (is_string($saved)) {
                    data_set($row, 'answer', $saved);
                }

                data_set($row, 'edited', (bool) $response->json('data.edited', true));
            });

            $this->closeMenu();
        } finally {
            $this->menuBusy = false;
            $this->releaseReactionLock('answer:'.$id);
        }
    }

    public function askDeleteMenu(): void
    {
        if ($this->menuPostId === '' || ! $this->viewerOwnsPost($this->menuPostData())) {
            return;
        }

        $this->menuError = '';
        $this->menuStep = 'confirm-delete';
    }

    public function ignoreMenuPost(): void
    {
        $id = $this->menuPostId;

        if ($id === '' || $this->menuBusy) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null || ! $this->viewerOwnsPost($this->findPostForMenu($id))) {
            return;
        }

        if (! $this->acquireReactionLock('ignore:'.$id)) {
            return;
        }

        $this->menuBusy = true;

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->ignoreQuestion($token, $id));

            if ($response === null) {
                $this->menuError = 'This post could not be deleted. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'This post could not be deleted. Please try again.');

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, 'delete');
                    $this->menuStep = 'menu';
                }

                return;
            }

            $this->afterMenuRemoval($id);
        } finally {
            $this->menuBusy = false;
            $this->releaseReactionLock('ignore:'.$id);
        }
    }

    public function deleteMenuPost(): void
    {
        $id = $this->menuPostId;

        if ($id === '' || $this->menuBusy) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null || ! $this->viewerOwnsPost($this->findPostForMenu($id))) {
            return;
        }

        if (! $this->acquireReactionLock('delete:'.$id)) {
            return;
        }

        $this->menuBusy = true;

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->deleteQuestion($token, $id));

            if ($response === null) {
                $this->menuError = 'This post could not be deleted. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'This post could not be deleted. Please try again.');

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, 'delete');
                    $this->menuStep = 'menu';
                }

                return;
            }

            $this->afterMenuRemoval($id);
        } finally {
            $this->menuBusy = false;
            $this->releaseReactionLock('delete:'.$id);
        }
    }

    public function openLikersMenu(): void
    {
        $id = $this->menuPostId;

        if ($id === '') {
            return;
        }

        $token = $this->menuToken();

        if ($token === null) {
            return;
        }

        $post = $this->findPostForMenu($id);

        if ($post === null || ! $this->viewerOwnsPost($post)) {
            $this->closeMenu();

            return;
        }

        if ((int) data_get($post, 'metrics.likes', 0) <= 0) {
            return;
        }

        if (! $this->acquireReactionLock('likers:'.$id)) {
            return;
        }

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->likers($token, $id));

            if ($response === null) {
                $this->menuError = 'Likes could not be loaded. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if ($response->forbidden()) {
                $this->hideMenuEntry($id, 'likes');
                $this->menuStep = 'menu';
                $this->menuError = ApiErrors::first($response, 'You cannot view who liked this post.');

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'Likes could not be loaded. Please try again.');

                return;
            }

            /** @var list<array<string, mixed>> $likers */
            $likers = $response->json('data', []);
            $this->likers = $likers;
            $this->likersPage = 1;
            $this->likersHasMore = $response->json('links.next') !== null;
            $this->likersError = '';
            $this->menuError = '';
            $this->menuStep = 'likers';
        } finally {
            $this->releaseReactionLock('likers:'.$id);
        }
    }

    public function loadMoreLikers(): void
    {
        $id = $this->menuPostId;

        if ($id === '' || ! $this->likersHasMore) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null) {
            return;
        }

        if (! $this->acquireReactionLock('likers:'.$id)) {
            return;
        }

        try {
            $api = app(PinkaryApi::class);
            $page = $this->likersPage + 1;
            $response = $api->attempt(fn () => $api->likers($token, $id, $page));

            if ($response === null) {
                $this->likersError = 'More likes could not be loaded. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                app(TokenStorage::class)->clear();
                $this->replace($this->route('mobile.login'));

                return;
            }

            if (! $response->successful()) {
                $this->likersError = ApiErrors::first($response, 'More likes could not be loaded. Please try again.');

                return;
            }

            /** @var list<array<string, mixed>> $next */
            $next = $response->json('data', []);
            $this->likers = [...$this->likers, ...$next];
            $this->likersPage = $page;
            $this->likersHasMore = $response->json('links.next') !== null;
            $this->likersError = '';
        } finally {
            $this->releaseReactionLock('likers:'.$id);
        }
    }

    public function toggleFollow(string $username): void
    {
        $this->toggleUserFollow($this->likers, $username);
    }

    public function shareMenuPost(): void
    {
        $post = $this->menuPostData();

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            (string) data_get($post, 'to.username', ''),
            $this->menuPostId,
            (string) (data_get($post, 'answer') ?? data_get($post, 'content')),
        );
    }

    public function shareToXMenu(): void
    {
        $post = $this->menuPostData();

        if ($post === null) {
            return;
        }

        $url = $this->questionUrl((string) data_get($post, 'to.username', ''), $this->menuPostId);

        if ($url === null) {
            return;
        }

        $text = $this->plainText(data_get($post, 'answer') ?? data_get($post, 'content'));

        $this->openExternalLink(
            'https://twitter.com/intent/tweet?url='.urlencode($url).'&text='.urlencode(mb_substr($text, 0, 140))
        );
    }

    private function sendPostViews(?string $token, string $viewer, bool $force): void
    {
        $batch = PostViews::takeBatch($token, $viewer, $force);
        if ($batch === null) {
            return;
        }

        $this->async(static function () use ($batch, $token, $viewer): void {
            $accepted = false;
            $retryAfter = 0;
            try {
                $response = app(PinkaryApi::class)->recordViews($token, $batch['ids'], $viewer);
                $accepted = $response->successful();
                $retryAfter = (int) $response->header('Retry-After');
            } finally {
                PostViews::finishBatch($batch, $accepted, $retryAfter);
            }
        })->failed(static function (Throwable $exception) use ($batch): void {
            PostViews::finishBatch($batch, false);
        });
    }

    /** Release action locks in finally; navigation redirects can throw. */
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
     * @param  array<string, mixed>  $post
     */
    private function reactLike(array &$post, string $id, ?string $token): void
    {
        if ($token === null) {
            $this->navigate($this->route('mobile.login'));

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
     * @param  array<string, mixed>  $post
     */
    private function reactBookmark(array &$post, string $id, ?string $token): void
    {
        if ($token === null) {
            $this->navigate($this->route('mobile.login'));

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
     * @param  array<string, mixed>  $post
     */
    private function voteOnPoll(array &$post, string $id, int $optionId, ?string $token): void
    {
        if ($token === null) {
            $this->navigate($this->route('mobile.login'));

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

    private function shareQuestion(?string $username, string $id, ?string $html): void
    {
        $url = $this->questionUrl($username, $id);

        if ($url === null) {
            return;
        }

        $text = mb_trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

        app(Share::class)->url('Pinkary post', $text === '' ? $url : mb_substr($text, 0, 140), $url);
    }

    private function openExternalLink(string $url): void
    {
        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
    }

    private function translateHtml(?string $html): void
    {
        $text = mb_trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

        if ($text === '') {
            return;
        }

        $this->openExternalLink('https://translate.google.com/?sl=auto&tl=en&text='.urlencode($text));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function toggleUserFollow(array &$rows, string $username): void
    {
        if ($username === '') {
            return;
        }

        $index = null;
        $following = false;

        foreach ($rows as $key => $row) {
            if ((string) data_get($row, 'username', '') === $username) {
                $index = $key;
                $following = (bool) data_get($row, 'followed_by_me', false);

                break;
            }
        }

        if ($index === null) {
            return;
        }

        if ((bool) data_get($rows[$index], 'is_me', false) || (bool) data_get($rows[$index], '_busy', false)) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        data_set($rows[$index], '_busy', true);

        $api = app(PinkaryApi::class);
        $response = $following
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        data_set($rows[$index], '_busy', false);

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        data_set($rows[$index], 'followed_by_me', (bool) $response->json('data.followed', ! $following));
    }

    /**
     * @param  array<string, mixed>|null  $post
     */
    private function viewerOwnsPost(?array $post): bool
    {
        if ($post === null) {
            return false;
        }

        $owner = mb_strtolower(mb_trim((string) data_get($post, 'to.username', '')));
        $viewer = mb_strtolower(mb_trim($this->viewerUsername()));

        return $owner !== '' && $viewer !== '' && $owner === $viewer;
    }

    private function viewerUsername(): string
    {
        $active = AccountStore::active();

        if (is_array($active) && is_string($active['username'] ?? null) && $active['username'] !== '') {
            return $active['username'];
        }

        $current = CurrentUser::get();

        if (is_array($current) && is_string($current['username'] ?? null)) {
            return $current['username'];
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPostForMenu(string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        foreach (['posts', 'questions'] as $property) {
            if (isset($this->{$property}) && is_array($this->{$property})) {
                $found = $this->readPostPayload($this->{$property}, $id);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        if (isset($this->post) && is_array($this->post) && $this->post !== []) {
            $found = $this->readPostPayload([$this->post], $id);

            if ($found !== null) {
                return $found;
            }
        }

        if (isset($this->comments) && is_array($this->comments)) {
            foreach ($this->comments as $comment) {
                if (! is_array($comment)) {
                    continue;
                }

                $found = $this->readPostPayload([$comment], $id);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function updatePostForMenu(string $id, Closure $mutate): bool
    {
        if ($id === '') {
            return false;
        }

        foreach (['posts', 'questions'] as $property) {
            if (! isset($this->{$property}) || ! is_array($this->{$property})) {
                continue;
            }

            $location = $this->locateNestedPost($this->{$property}, $id);

            if ($location === null) {
                continue;
            }

            [$index, $key] = $location;

            if ($key === null) {
                $mutate($this->{$property}[$index]);
            } else {
                $mutate($this->{$property}[$index]['thread']['posts'][$key]);
            }

            return true;
        }

        if (isset($this->post) && is_array($this->post) && $this->post !== []) {
            $location = $this->locateNestedPost([$this->post], $id);

            if ($location !== null) {
                [, $key] = $location;

                if ($key === null) {
                    $mutate($this->post);
                } else {
                    $mutate($this->post['thread']['posts'][$key]);
                }

                $this->syncMenuThreadMirror();

                return true;
            }
        }

        if (isset($this->comments) && is_array($this->comments)) {
            foreach ($this->comments as $index => $comment) {
                if (! is_array($comment)) {
                    continue;
                }

                $location = $this->locateNestedPost([$comment], $id);

                if ($location === null) {
                    continue;
                }

                [, $key] = $location;

                if ($key === null) {
                    $mutate($this->comments[$index]);
                } else {
                    $mutate($this->comments[$index]['thread']['posts'][$key]);
                }

                return true;
            }
        }

        return false;
    }

    private function removePostForMenu(string $id): void
    {
        if ($id === '') {
            return;
        }

        foreach (['posts', 'questions'] as $property) {
            if (! isset($this->{$property}) || ! is_array($this->{$property})) {
                continue;
            }

            $location = $this->locateNestedPost($this->{$property}, $id);

            if ($location === null) {
                continue;
            }

            [$index, $key] = $location;

            if ($key === null) {
                $this->{$property} = array_values(array_filter(
                    $this->{$property},
                    fn (mixed $row): bool => ! is_array($row) || (string) data_get($row, 'id', '') !== $id
                ));
            } else {
                $thread = array_values(array_filter(
                    (array) data_get($this->{$property}[$index], 'thread.posts', []),
                    fn (mixed $row): bool => ! is_array($row) || (string) data_get($row, 'id', '') !== $id
                ));

                data_set($this->{$property}[$index], 'thread.posts', $thread);
            }
        }

        if (isset($this->comments) && is_array($this->comments)) {
            $this->comments = array_values(array_filter(
                $this->comments,
                fn (mixed $row): bool => ! is_array($row) || (string) data_get($row, 'id', '') !== $id
            ));

            foreach ($this->comments as $index => $comment) {
                if (! is_array($comment)) {
                    continue;
                }

                $location = $this->locateNestedPost([$comment], $id);

                if ($location === null || $location[1] === null) {
                    continue;
                }

                $thread = array_values(array_filter(
                    (array) data_get($comment, 'thread.posts', []),
                    fn (mixed $row): bool => ! is_array($row) || (string) data_get($row, 'id', '') !== $id
                ));

                data_set($this->comments[$index], 'thread.posts', $thread);
            }
        }

        if (isset($this->post) && is_array($this->post) && $this->post !== []) {
            $location = $this->locateNestedPost([$this->post], $id);

            if ($location !== null && $location[1] !== null) {
                $thread = array_values(array_filter(
                    (array) data_get($this->post, 'thread.posts', []),
                    fn (mixed $row): bool => ! is_array($row) || (string) data_get($row, 'id', '') !== $id
                ));

                data_set($this->post, 'thread.posts', $thread);
                $this->syncMenuThreadMirror();
            }
        }
    }

    private function afterMenuRemoval(string $id): void
    {
        $this->closeMenu();

        if (isset($this->id) && is_string($this->id) && $this->id !== '' && $this->id === $id) {
            $this->navigate($this->route('mobile.feed'));

            return;
        }

        $this->removePostForMenu($id);
    }

    private function hideMenuEntry(string $postId, string $action): void
    {
        $this->menuDenied[$postId.':'.$action] = true;
    }

    private function menuToken(): ?string
    {
        $token = app(TokenStorage::class)->get();

        if (! is_string($token) || $token === '') {
            $this->navigate($this->route('mobile.login'));

            return null;
        }

        return $token;
    }

    private function questionUrl(?string $username, string $id): ?string
    {
        if ($username === null || $username === '' || $id === '') {
            return null;
        }

        $base = mb_rtrim((string) config('services.pinkary.url'), '/');

        return $base.'/@'.$username.'/questions/'.$id;
    }

    private function plainText(mixed $html): string
    {
        return mb_trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    }

    private function syncMenuThreadMirror(): void
    {
        if (method_exists($this, 'syncThreadMirror')) {
            $this->syncThreadMirror();
        }
    }
}
