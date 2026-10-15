<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\DisplayText;
use App\Support\FeedCache;
use App\Support\Payload;
use App\Support\PostViews;
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
    use UsesComposerMentions;

    public string $menuPostId = '';

    public string $activeYoutubePostId = '';

    public string $youtubeEmbedUrl = '';

    /** @var list<string> */
    public array $activeImageGallery = [];

    public int $activeImageIndex = 0;

    /** @var 'menu'|'confirm-delete'|'confirm-ignore'|'confirm-report'|'answer'|'edit'|'likers' */
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

    /** @var array<string, true> In-flight reaction locks per post+action. */
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
        $this->clearMentionSuggestions();
        $this->menuError = '';
        $this->menuBusy = false;
        $this->editAnswer = '';
        $this->likers = [];
        $this->likersPage = 1;
        $this->likersHasMore = false;
        $this->likersError = '';
    }

    public function openPostPreview(string $id, string $feedItemId = ''): void
    {
        $post = $this->findPostForMenu($id);
        $url = DisplayText::url(Payload::string(data_get($post, 'preview.url'))) ?? '';

        if ($url === '') {
            return;
        }

        $playerId = $feedItemId !== '' ? $feedItemId : $id;
        $videoId = $this->youtubeVideoId($url);

        if ($videoId !== null) {
            $this->activeYoutubePostId = $playerId;
            $this->youtubeEmbedUrl = 'https://www.youtube-nocookie.com/embed/'.$videoId.'?playsinline=1&autoplay=1';

            return;
        }

        $videoId = $this->vimeoVideoId($url);

        if ($videoId !== null) {
            $parts = parse_url($url);
            $hash = is_array($parts) ? $this->queryParameter(Payload::string($parts['query'] ?? ''), 'h') : null;
            $hash = is_string($hash) && preg_match('/^[A-Za-z0-9]+$/', $hash) === 1 ? '&h='.$hash : '';
            $this->activeYoutubePostId = $playerId;
            $this->youtubeEmbedUrl = 'https://player.vimeo.com/video/'.$videoId.'?playsinline=1&autoplay=1'.$hash;

            return;
        }

        $this->openExternalLink($url);
    }

    public function closeYoutubeVideo(): void
    {
        $this->activeYoutubePostId = '';
        $this->youtubeEmbedUrl = '';
    }

    /** @param list<mixed> $images */
    public function openImageFullscreen(string $url, array $images = [], int $index = 0): void
    {
        $url = DisplayText::url($url) ?? '';

        if ($url === '') {
            return;
        }

        $gallery = array_values(array_filter(
            array_map(fn (mixed $image): ?string => is_string($image) ? DisplayText::url($image) : null, $images),
            is_string(...),
        ));
        $activeIndex = array_search($url, $gallery, true);

        if ($activeIndex === false) {
            $gallery = [$url];
            $activeIndex = 0;
        } elseif (($gallery[$index] ?? null) === $url) {
            $activeIndex = $index;
        }

        $this->activeImageGallery = $gallery;
        $this->activeImageIndex = $activeIndex;
    }

    public function setImageGalleryPage(int $index): void
    {
        if (isset($this->activeImageGallery[$index])) {
            $this->activeImageIndex = $index;
        }
    }

    public function closeImageFullscreen(): void
    {
        $this->activeImageGallery = [];
        $this->activeImageIndex = 0;
    }

    public function closeMenu(): void
    {
        $this->menuPostId = '';
        $this->menuStep = 'menu';
        $this->clearMentionSuggestions();
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
        $this->clearMentionSuggestions();
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

    public function canAnswerMenuPost(): bool
    {
        $post = $this->menuPostData();

        return $post !== null
            && $this->viewerOwnsPost($post)
            && DisplayText::plain(data_get($post, 'answer')) === ''
            && ! $this->menuDeniedFor($this->menuPostId, 'answer');
    }

    public function canIgnoreMenuPost(): bool
    {
        return $this->canAnswerMenuPost()
            && ! $this->menuDeniedFor($this->menuPostId, 'ignore');
    }

    public function canReportMenuPost(): bool
    {
        $post = $this->menuPostData();

        if ($post === null || ! $this->viewerOwnsPost($post) || $this->menuDeniedFor($this->menuPostId, 'report')) {
            return false;
        }

        $sender = mb_strtolower(mb_trim(Payload::string(data_get($post, 'from.username'))));

        return $sender !== ''
            && $sender !== mb_strtolower(mb_trim($this->viewerUsername()))
            && DisplayText::plain(data_get($post, 'answer')) === ''
            && ! (bool) data_get($post, 'is_update', false);
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

        return Payload::int(data_get($post, 'metrics.likes')) > 0;
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
                $this->handleUnauthorized($response);

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

            $this->updatePostForMenu($id, ['pinned' => $next]);

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

        $this->editAnswer = $this->postSource($post, 'answer') ?? '';
        $this->clearMentionSuggestions();
        $this->menuError = '';
        $this->menuStep = 'edit';
    }

    public function startAnswerMenu(): void
    {
        if (! $this->canAnswerMenuPost()) {
            return;
        }

        $this->editAnswer = '';
        $this->clearMentionSuggestions();
        $this->menuError = '';
        $this->menuStep = 'answer';
    }

    public function askIgnoreMenu(): void
    {
        if (! $this->canIgnoreMenuPost()) {
            return;
        }

        $this->menuError = '';
        $this->menuStep = 'confirm-ignore';
    }

    public function askReportMenu(): void
    {
        if (! $this->canReportMenuPost()) {
            return;
        }

        $this->menuError = '';
        $this->menuStep = 'confirm-report';
    }

    public function reportMenuPost(): void
    {
        $id = $this->menuPostId;

        if ($id === '' || $this->menuStep !== 'confirm-report' || $this->menuBusy || ! $this->canReportMenuPost()) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null || ! $this->acquireReactionLock('report:'.$id)) {
            return;
        }

        $this->menuBusy = true;

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->reportQuestion($token, $id));

            if ($response === null) {
                $this->menuError = 'This question could not be reported. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                $this->handleUnauthorized($response);

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'This question could not be reported. Please try again.');

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, 'report');
                    $this->menuStep = 'menu';
                }

                return;
            }

            $this->afterMenuRemoval($id);
        } finally {
            $this->menuBusy = false;
            $this->releaseReactionLock('report:'.$id);
        }
    }

    public function saveAnswerMenu(): void
    {
        $id = $this->menuPostId;
        $isEditing = $this->menuStep === 'edit';

        if ($id === '' || ! in_array($this->menuStep, ['answer', 'edit'], true) || $this->menuBusy) {
            return;
        }

        $token = $this->menuToken();
        $post = $this->findPostForMenu($id);

        if ($token === null || ! $this->viewerOwnsPost($post)) {
            return;
        }

        if (! $isEditing && DisplayText::plain(data_get($post, 'answer')) !== '') {
            return;
        }

        $answer = $this->editAnswer;

        if (DisplayText::plain($answer) === '') {
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
                $this->handleUnauthorized($response);

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, 'Your answer could not be saved. Please try again.');

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, $isEditing ? 'edit' : 'answer');
                    $this->menuStep = 'menu';
                }

                return;
            }

            $saved = $api->normalizeQuestion(Payload::stringMap($response->json('data')));
            $values = array_intersect_key($saved, array_flip([
                'answer',
                'answer_source',
                'edited',
                'updated_at',
                'preview',
                'images',
                'channel',
                'poll',
                'metrics',
            ]));
            $values['edited'] = (bool) ($values['edited'] ?? $isEditing);
            $this->updatePostForMenu($id, $values);
            $this->forgetPostListCaches();

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

    public function deleteMenuPost(): void
    {
        $this->softIgnoreMenuPost('delete');
    }

    public function ignoreMenuPost(): void
    {
        $this->softIgnoreMenuPost('ignore');
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

        if (Payload::int(data_get($post, 'metrics.likes')) <= 0) {
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
                $this->handleUnauthorized($response);

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
                $this->handleUnauthorized($response);

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

    public function openHashtag(string $hashtagName): void
    {
        $hashtagName = mb_trim($hashtagName, "# \t\n\r\0\x0B");

        if ($hashtagName === '') {
            return;
        }

        $token = $this->token();
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->search($token, $hashtagName));

        if ($response === null) {
            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        foreach (Payload::array($response->json('data.hashtags')) as $hashtag) {
            $id = Payload::int(data_get($hashtag, 'id'));
            $name = Payload::string(data_get($hashtag, 'name'));

            if ($id < 1 || mb_strtolower($name) !== mb_strtolower($hashtagName)) {
                continue;
            }

            $this->navigate(
                $this->route('mobile.hashtag', ['hashtagId' => $id]),
                ['hashtagName' => $name],
            );

            return;
        }
    }

    /** @param array<string, mixed> $post */
    public function feedItemIdentity(array $post): string
    {
        $postId = Payload::string(data_get($post, 'id'));
        $repostId = Payload::string(data_get($post, 'repost_id'));

        if ($repostId !== '') {
            return $repostId;
        }

        $reposterId = Payload::string(data_get($post, 'reposted_by.id'));
        $reposter = $reposterId !== '' ? $reposterId : Payload::string(data_get($post, 'reposted_by.username'));

        return $reposter !== '' ? $postId.':repost:'.$reposter : $postId;
    }

    protected function mentionText(int $postIndex): ?string
    {
        return $postIndex === -2 && in_array($this->menuStep, ['answer', 'edit'], true)
            ? $this->editAnswer
            : null;
    }

    protected function replaceMentionText(int $postIndex, string $text): void
    {
        if ($postIndex === -2 && in_array($this->menuStep, ['answer', 'edit'], true)) {
            $this->editAnswer = $text;
        }
    }

    protected function mentionComposerBusy(): bool
    {
        return $this->menuBusy;
    }

    protected function mentionChanged(int $postIndex): void {}

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<string, mixed>|null
     */
    private static function postAt(array $posts, int $index, int|string|null $key): ?array
    {
        $post = $posts[$index] ?? null;

        if ($key === null) {
            return is_array($post) ? self::payloadRow($post) : null;
        }

        $ancestor = is_array($post) ? data_get($post, 'thread.posts.'.$key) : null;

        return is_array($ancestor) ? self::payloadRow($ancestor) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @param  array<string, mixed>  $post
     */
    private static function writePostAt(array &$posts, int $index, int|string|null $key, array $post): void
    {
        if ($key === null) {
            $posts[$index] = $post;

            return;
        }

        $ancestors = Payload::array(data_get($posts[$index] ?? null, 'thread.posts'));
        $ancestors[$key] = $post;

        self::writeThreadPosts($posts, $index, $ancestors);
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @param  array<array-key, mixed>  $ancestors
     */
    private static function writeThreadPosts(array &$posts, int $index, array $ancestors): void
    {
        $thread = Payload::array(data_get($posts[$index] ?? null, 'thread'));
        $thread['posts'] = $ancestors;
        $posts[$index]['thread'] = $thread;
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @param  array<string, mixed>  $values
     */
    private static function updatePostOccurrences(array &$posts, string $id, array $values): bool
    {
        $changed = false;

        foreach ($posts as $index => $post) {
            if (Payload::string(data_get($post, 'id')) === $id) {
                $posts[$index] = array_replace($post, $values);
                $changed = true;
            }

            $thread = Payload::array(data_get($posts[$index], 'thread'));
            $ancestors = Payload::array($thread['posts'] ?? null);
            $ancestorsChanged = false;

            foreach ($ancestors as $key => $ancestor) {
                if (! is_array($ancestor) || Payload::string(data_get($ancestor, 'id')) !== $id) {
                    continue;
                }

                $ancestors[$key] = array_replace($ancestor, $values);
                $changed = true;
                $ancestorsChanged = true;
            }

            if ($ancestorsChanged) {
                $thread['posts'] = $ancestors;
                $posts[$index]['thread'] = $thread;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private static function withoutPost(array $post, string $id): array
    {
        $thread = Payload::array(data_get($post, 'thread'));
        $thread['posts'] = array_values(array_filter(
            Payload::array(data_get($post, 'thread.posts')),
            static fn (mixed $row): bool => ! is_array($row) || Payload::string(data_get($row, 'id', '')) !== $id
        ));
        $post['thread'] = $thread;

        return $post;
    }

    /**
     * Posts reach the menu as decoded JSON objects, so their keys are strings;
     * rows read back out of a component property are narrowed to that shape here.
     *
     * @return array<string, mixed>
     */
    private static function payloadRow(mixed $row): array
    {
        return is_array($row) ? array_filter($row, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function payloadRows(mixed $rows): array
    {
        $normalized = [];

        foreach (Payload::array($rows) as $row) {
            $normalized[] = self::payloadRow($row);
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @param  list<array<string, mixed>>  $next
     * @return list<array<string, mixed>>
     */
    private function mergeFeedPage(array $posts, array $next): array
    {
        $identities = [];

        foreach ($posts as $post) {
            $identities[$this->feedItemIdentity($post)] = true;
        }

        foreach ($next as $post) {
            $identity = $this->feedItemIdentity($post);

            if (isset($identities[$identity])) {
                continue;
            }

            $identities[$identity] = true;
            $posts[] = $post;
        }

        return $posts;
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
            $count = Payload::int(data_get($post, 'metrics.likes'));

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'liked', ! $liked);
            $this->setPostMetric($post, 'likes', max(0, $count + ($liked ? -1 : 1)));

            $api = app(PinkaryApi::class);
            $response = $liked
                ? $api->attempt(fn () => $api->unlike($token, $id))
                : $api->attempt(fn () => $api->like($token, $id));

            if ($response === null) {
                $this->setPostMetric($post, 'liked', $liked);
                $this->setPostMetric($post, 'likes', $count);
                $this->setReactionError($post, 'Your like could not be updated. Please try again.');

                return;
            }

            if ($this->handleUnauthorized($response)) {
                $this->setPostMetric($post, 'liked', $liked);
                $this->setPostMetric($post, 'likes', $count);

                return;
            }

            if (! $response->successful()) {
                $this->setPostMetric($post, 'liked', $liked);
                $this->setPostMetric($post, 'likes', $count);
                $this->setReactionError($post, ApiErrors::first($response, 'Your like could not be updated. Please try again.'));

                return;
            }

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'liked', (bool) $response->json('data.liked', ! $liked));

            $likes = $response->json('data.likes');

            if (is_int($likes)) {
                $this->setPostMetric($post, 'likes', $likes);
            }

            $this->forgetPostListCaches();
        } finally {
            $this->releaseReactionLock('like:'.$id);
        }
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function reactRepost(array &$post, string $id, ?string $token): void
    {
        if ($token === null) {
            $this->navigate($this->route('mobile.login'));

            return;
        }

        if (! $this->acquireReactionLock('repost:'.$id)) {
            return;
        }

        try {
            $reposted = (bool) data_get($post, 'metrics.reposted', false);
            $count = Payload::int(data_get($post, 'metrics.reposts'));

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'reposted', ! $reposted);
            $this->setPostMetric($post, 'reposts', max(0, $count + ($reposted ? -1 : 1)));

            $api = app(PinkaryApi::class);
            $response = $reposted
                ? $api->attempt(fn () => $api->unrepost($token, $id))
                : $api->attempt(fn () => $api->repost($token, $id));

            if ($response === null) {
                $this->setPostMetric($post, 'reposted', $reposted);
                $this->setPostMetric($post, 'reposts', $count);
                $this->setReactionError($post, 'Your repost could not be updated. Please try again.');

                return;
            }

            if ($this->handleUnauthorized($response)) {
                $this->setPostMetric($post, 'reposted', $reposted);
                $this->setPostMetric($post, 'reposts', $count);

                return;
            }

            if (! $response->successful()) {
                $this->setPostMetric($post, 'reposted', $reposted);
                $this->setPostMetric($post, 'reposts', $count);
                $this->setReactionError($post, ApiErrors::first($response, 'Your repost could not be updated. Please try again.'));

                return;
            }

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'reposted', (bool) $response->json('data.reposted', ! $reposted));

            $reposts = $response->json('data.reposts');

            if (is_int($reposts)) {
                $this->setPostMetric($post, 'reposts', $reposts);
            }

            $this->forgetPostListCaches();
        } finally {
            $this->releaseReactionLock('repost:'.$id);
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
            $count = Payload::int(data_get($post, 'metrics.bookmarks'));

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'bookmarked', ! $bookmarked);
            $this->setPostMetric($post, 'bookmarks', max(0, $count + ($bookmarked ? -1 : 1)));

            $api = app(PinkaryApi::class);
            $response = $bookmarked
                ? $api->attempt(fn () => $api->unbookmark($token, $id))
                : $api->attempt(fn () => $api->bookmark($token, $id));

            if ($response === null) {
                $this->setPostMetric($post, 'bookmarked', $bookmarked);
                $this->setPostMetric($post, 'bookmarks', $count);
                $this->setReactionError($post, 'Your bookmark could not be updated. Please try again.');

                return;
            }

            if ($this->handleUnauthorized($response)) {
                $this->setPostMetric($post, 'bookmarked', $bookmarked);
                $this->setPostMetric($post, 'bookmarks', $count);

                return;
            }

            if (! $response->successful()) {
                $this->setPostMetric($post, 'bookmarked', $bookmarked);
                $this->setPostMetric($post, 'bookmarks', $count);
                $this->setReactionError($post, ApiErrors::first($response, 'Your bookmark could not be updated. Please try again.'));

                return;
            }

            $this->setReactionError($post, '');
            $this->setPostMetric($post, 'bookmarked', (bool) $response->json('data.bookmarked', ! $bookmarked));

            $bookmarks = $response->json('data.bookmarks');

            if (is_int($bookmarks)) {
                $this->setPostMetric($post, 'bookmarks', $bookmarks);
            }

            $this->forgetPostListCaches();
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
            $post['poll_error'] = '';
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $api->votePoll($token, $id, $optionId));

            if ($response === null) {
                $post['poll_error'] = 'Your vote could not be submitted. Please try again.';

                return;
            }

            if ($response->unauthorized()) {
                $this->handleUnauthorized($response);

                return;
            }

            if (! $response->successful()) {
                $post['poll_error'] = ApiErrors::first($response, 'Your vote could not be submitted. Please try again.');

                return;
            }

            $poll = self::payloadRow($response->json('data'));

            if ($poll !== []) {
                $post['poll'] = $poll;
            }

            $this->forgetPostListCaches();
        } finally {
            $this->releaseReactionLock('vote:'.$id);
        }
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function setPostMetric(array &$post, string $metric, mixed $value): void
    {
        $metrics = $post['metrics'] ?? null;

        $metrics = is_array($metrics) ? $metrics : [];
        $metrics[$metric] = $value;
        $post['metrics'] = $metrics;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function setReactionError(array &$post, string $message): void
    {
        $post['reaction_error'] = $message;
    }

    private function forgetPostListCaches(): void
    {
        $username = data_get(AccountStore::active(), 'username');

        if (! is_string($username) || $username === '') {
            return;
        }

        foreach (['recent', 'following', 'trending'] as $tab) {
            FeedCache::forget(FeedCache::feedKey($username, $tab));
        }

        FeedCache::forget(FeedCache::bookmarksKey($username));
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
            if (Payload::string(data_get($post, 'id')) === $id) {
                return [$index, null];
            }

            foreach (Payload::array(data_get($post, 'thread.posts')) as $key => $ancestor) {
                if (is_array($ancestor) && Payload::string(data_get($ancestor, 'id')) === $id) {
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

        return self::postAt($posts, $location[0], $location[1]);
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
        $post = self::postAt($posts, $index, $key);

        if ($post === null) {
            return false;
        }

        if ($kind === 'like') {
            $this->reactLike($post, $id, $token);
        } elseif ($kind === 'repost') {
            $this->reactRepost($post, $id, $token);
        } elseif ($kind === 'bookmark') {
            $this->reactBookmark($post, $id, $token);
        } else {
            return false;
        }

        self::updatePostOccurrences($posts, $id, array_intersect_key($post, array_flip(['metrics', 'reaction_error'])));

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
        $post = self::postAt($posts, $index, $key);

        if ($post === null) {
            return false;
        }

        $this->voteOnPoll($post, $id, $optionId, $token);

        self::updatePostOccurrences($posts, $id, array_intersect_key($post, array_flip(['poll', 'poll_error'])));

        return true;
    }

    private function expandPost(string $id): void
    {
        if ($id === '') {
            return;
        }

        $expanded = array_values(array_filter(Payload::array($this->expanded), is_string(...)));

        if (! in_array($id, $expanded, true)) {
            $this->expanded = [...$expanded, $id];
        }
    }

    private function shareQuestion(?string $username, string $id, ?string $text): void
    {
        $url = $this->questionUrl($username, $id);

        if ($url === null) {
            return;
        }

        $text = mb_trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8'));

        app(Share::class)->url('Pinkary post', $text === '' ? $url : mb_substr($text, 0, 140), $url);
    }

    private function openExternalLink(string $url): void
    {
        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
    }

    private function translateText(?string $text): void
    {
        $text = mb_trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8'));

        if ($text === '') {
            return;
        }

        $this->openExternalLink('https://translate.google.com/?sl=auto&tl=en&text='.urlencode($text));
    }

    /** @param array<string, mixed> $post */
    private function postSource(array $post, string $field): ?string
    {
        $source = data_get($post, $field.'_source') ?? data_get($post, $field);

        return is_string($source) ? $source : null;
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
            if (Payload::string(data_get($row, 'username', '')) === $username) {
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

        $rows[$index]['_busy'] = true;

        $api = app(PinkaryApi::class);
        $response = $following
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $rows[$index]['_busy'] = false;

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $rows[$index]['followed_by_me'] = (bool) $response->json('data.followed', ! $following);
    }

    /**
     * @param  array<string, mixed>|null  $post
     */
    private function viewerOwnsPost(?array $post): bool
    {
        if ($post === null) {
            return false;
        }

        $owner = mb_strtolower(mb_trim(Payload::string(data_get($post, 'to.username', ''))));
        $viewer = mb_strtolower(mb_trim($this->viewerUsername()));

        return $owner !== '' && $viewer !== '' && $owner === $viewer;
    }

    private function viewerUsername(): string
    {
        $active = AccountStore::active();

        if ($active !== null && $active['username'] !== '') {
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

        foreach ($this->menuPostSources() as $posts) {
            $found = $this->readPostPayload($posts, $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    private function menuPostSources(): array
    {
        $sources = [];

        foreach ($this->menuPostProperties() as $property) {
            $rows = self::payloadRows($this->{$property});

            if ($rows !== []) {
                $sources[] = $rows;
            }
        }

        if (isset($this->post)) {
            $post = self::payloadRow($this->post);

            if ($post !== []) {
                $sources[] = [$post];
            }
        }

        if (isset($this->comments)) {
            $comments = self::payloadRows($this->comments);

            if ($comments !== []) {
                $sources[] = $comments;
            }
        }

        return $sources;
    }

    /**
     * Not every component declares every collection, and the name a component
     * keeps its posts under is only known here, so ask each of them.
     *
     * @return list<string>
     */
    private function menuPostProperties(): array
    {
        return array_values(array_filter(
            ['posts', 'questions'],
            fn (string $property): bool => property_exists($this, $property)
        ));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updatePostForMenu(string $id, array $values): bool
    {
        if ($id === '') {
            return false;
        }

        $updated = false;

        foreach ($this->menuPostProperties() as $property) {
            $rows = self::payloadRows($this->{$property});

            if (self::updatePostOccurrences($rows, $id, $values)) {
                $this->{$property} = $rows;
                $updated = true;
            }
        }

        if (isset($this->post)) {
            $post = [self::payloadRow($this->post)];

            if (self::updatePostOccurrences($post, $id, $values)) {
                $this->post = $post[0];
                $this->syncMenuThreadMirror();
                $updated = true;
            }
        }

        if (isset($this->comments)) {
            $comments = self::payloadRows($this->comments);

            if (self::updatePostOccurrences($comments, $id, $values)) {
                $this->comments = $comments;
                $updated = true;
            }
        }

        return $updated;
    }

    private function removePostForMenu(string $id): void
    {
        if ($id === '') {
            return;
        }

        foreach ($this->menuPostProperties() as $property) {
            $rows = self::payloadRows($this->{$property});
            $location = $this->locateNestedPost($rows, $id);

            if ($location === null) {
                continue;
            }

            [$index, $key] = $location;

            if ($key === null) {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $row): bool => Payload::string(data_get($row, 'id', '')) !== $id
                ));
            } else {
                $rows[$index] = self::withoutPost($rows[$index], $id);
            }

            $this->{$property} = $rows;
        }

        if (isset($this->comments)) {
            $comments = array_values(array_filter(
                self::payloadRows($this->comments),
                static fn (array $row): bool => Payload::string(data_get($row, 'id', '')) !== $id
            ));

            foreach ($comments as $index => $comment) {
                $location = $this->locateNestedPost([$comment], $id);

                if ($location !== null && $location[1] !== null) {
                    $comments[$index] = self::withoutPost($comment, $id);
                }
            }

            $this->comments = $comments;
        }

        if (isset($this->post)) {
            $post = self::payloadRow($this->post);
            $location = $this->locateNestedPost([$post], $id);

            if ($location !== null && $location[1] !== null) {
                $this->post = self::withoutPost($post, $id);
                $this->syncMenuThreadMirror();
            }
        }
    }

    private function afterMenuRemoval(string $id): void
    {
        $this->closeMenu();
        $this->forgetPostListCaches();

        if (isset($this->id) && is_string($this->id) && $this->id !== '' && $this->id === $id) {
            $this->navigate($this->route('mobile.feed'));

            return;
        }

        $hasRootPost = false;

        foreach ($this->menuPostProperties() as $property) {
            foreach (self::payloadRows($this->{$property}) as $post) {
                if (Payload::string(data_get($post, 'id')) === $id) {
                    $hasRootPost = true;
                }
            }
        }

        $this->removePostForMenu($id);

        if ($hasRootPost && isset($this->page, $this->hasMore) && is_int($this->page) && $this->hasMore) {
            $this->page = max(0, $this->page - 1);
        }
    }

    private function softIgnoreMenuPost(string $action): void
    {
        if (! in_array($action, ['delete', 'ignore'], true) || $this->menuPostId === '' || $this->menuBusy) {
            return;
        }

        $id = $this->menuPostId;
        $expectedStep = $action === 'delete' ? 'confirm-delete' : 'confirm-ignore';

        if ($this->menuStep !== $expectedStep) {
            return;
        }

        $canAct = $action === 'delete'
            ? $this->viewerOwnsPost($this->findPostForMenu($id))
            : $this->canIgnoreMenuPost();

        if (! $canAct) {
            return;
        }

        $token = $this->menuToken();

        if ($token === null || ! $this->acquireReactionLock('soft-ignore:'.$id)) {
            return;
        }

        $this->menuBusy = true;
        $fallback = $action === 'delete'
            ? 'This post could not be deleted. Please try again.'
            : 'This question could not be ignored. Please try again.';

        try {
            $api = app(PinkaryApi::class);
            $response = $api->attempt(fn () => $action === 'delete'
                ? $api->deleteQuestion($token, $id)
                : $api->ignoreQuestion($token, $id));

            if ($response === null) {
                $this->menuError = $fallback;

                return;
            }

            if ($response->unauthorized()) {
                $this->handleUnauthorized($response);

                return;
            }

            if (! $response->successful()) {
                $this->menuError = ApiErrors::first($response, $fallback);

                if ($response->forbidden()) {
                    $this->hideMenuEntry($id, $action);
                    $this->menuStep = 'menu';
                }

                return;
            }

            $this->afterMenuRemoval($id);
        } finally {
            $this->menuBusy = false;
            $this->releaseReactionLock('soft-ignore:'.$id);
        }
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

        $base = mb_rtrim(Payload::string(config('services.pinkary.url')), '/');

        return $base.'/@'.$username.'/questions/'.$id;
    }

    private function youtubeVideoId(string $url): ?string
    {
        $parts = parse_url($url);
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $path = mb_trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0];
        } elseif ($host === 'youtube.com' || str_ends_with($host, '.youtube.com') || $host === 'youtube-nocookie.com' || str_ends_with($host, '.youtube-nocookie.com')) {
            $segments = explode('/', $path);
            $videoId = match ($segments[0]) {
                'watch' => $this->queryParameter($parts['query'] ?? '', 'v') ?? '',
                'embed', 'shorts', 'live' => (string) ($segments[1] ?? ''),
                default => '',
            };
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
    }

    private function vimeoVideoId(string $url): ?string
    {
        $parts = parse_url($url);
        $host = mb_strtolower(Payload::string(is_array($parts) ? ($parts['host'] ?? '') : ''));

        if (! in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }

        $segments = explode('/', mb_trim(Payload::string(is_array($parts) ? ($parts['path'] ?? '') : ''), '/'));

        for ($index = count($segments) - 1; $index >= 0; $index--) {
            if (preg_match('/^\d+$/', $segments[$index]) === 1) {
                return $segments[$index];
            }
        }

        return null;
    }

    private function queryParameter(string $query, string $key): ?string
    {
        parse_str($query, $parameters);
        $value = $parameters[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function plainText(mixed $html): string
    {
        return mb_trim(html_entity_decode(strip_tags(Payload::string($html)), ENT_QUOTES, 'UTF-8'));
    }

    private function syncMenuThreadMirror(): void
    {
        if (method_exists($this, 'syncThreadMirror')) {
            $this->syncThreadMirror();
        }
    }
}
