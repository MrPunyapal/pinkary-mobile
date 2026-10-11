<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\Payload;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Native\Mobile\Share;

final class Profile extends NativeComponent implements HasWebHead
{
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use TogglesPostReactions;
    use UsesAppMenu;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $username = '';

    public string $errorMessage = '';

    public bool $loading = true;

    public bool $followBusy = false;

    public bool $showQr = false;

    public bool $showAvatar = false;

    public string $linkError = '';

    /** @var list<array<string, mixed>> */
    public array $questions = [];

    public bool $questionsLoading = true;

    public string $questionsError = '';

    public int $questionsPage = 1;

    public bool $hasMoreQuestions = false;

    public bool $loadingMoreQuestions = false;

    public string $loadMoreQuestionsError = '';

    /** @var list<string> */
    public array $expanded = [];

    public function hidesDrawer(): bool
    {
        return $this->username !== '';
    }

    public function mount(): void
    {
        $this->username = Payload::string($this->param('username', ''));

        $this->load($this->token());
    }

    public function retry(): void
    {
        $this->load($this->token());
    }

    public function retryQuestions(): void
    {
        $this->loadQuestions($this->token(), $this->questionsUsername());
    }

    public function openFollowers(): void
    {
        $username = $this->questionsUsername();

        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.followers', ['username' => $username]));
    }

    public function openFollowing(): void
    {
        $username = $this->questionsUsername();

        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.following', ['username' => $username]));
    }

    public function askUser(): void
    {
        $username = Payload::string(data_get($this->profile, 'username', ''));
        $canAskQuestion = data_get($this->profile, 'can_ask_question');

        if ($username === '' || (bool) data_get($this->profile, 'is_me', false) || $canAskQuestion === false) {
            return;
        }

        $this->navigate($this->route('mobile.ask', ['username' => $username]));
    }

    public function editProfile(): void
    {
        if (! (bool) data_get($this->profile, 'is_me', false)) {
            return;
        }

        $this->navigate($this->route('mobile.profile.edit'));
    }

    public function openDetail(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->navigate($this->route('mobile.detail', ['id' => $id]));
    }

    public function openLink(string $url): void
    {
        $this->openExternalLink($url);
    }

    public function showMore(string $id): void
    {
        $this->expandPost($id);
    }

    public function toggleLike(string $id): void
    {
        $this->reactOnPostPayload($this->questions, $id, 'like', $this->token());
    }

    public function toggleRepost(string $id): void
    {
        $this->reactOnPostPayload($this->questions, $id, 'repost', $this->token());
    }

    public function toggleBookmark(string $id): void
    {
        $this->reactOnPostPayload($this->questions, $id, 'bookmark', $this->token());
    }

    public function translatePost(string $id, string $field = 'answer'): void
    {
        $post = $this->readPostPayload($this->questions, $id);

        if ($post === null) {
            return;
        }

        $this->closeMenu();
        $this->translateText($this->postSource($post, $field));
    }

    public function sharePost(string $id): void
    {
        $post = $this->readPostPayload($this->questions, $id);

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            Payload::string(data_get($post, 'to.username', '')),
            $id,
            $this->postSource($post, 'answer') ?? $this->postSource($post, 'content'),
        );
    }

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteOnPostPayload($this->questions, $id, $optionId, $this->token());
    }

    public function loadMoreQuestions(): void
    {
        if ($this->loadingMoreQuestions || ! $this->hasMoreQuestions) {
            return;
        }

        $token = $this->token();

        $username = $this->questionsUsername();

        if ($username === '') {
            return;
        }

        $this->loadingMoreQuestions = true;
        $this->loadMoreQuestionsError = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->userQuestions($token, $username, $this->questionsPage + 1));

        $this->loadingMoreQuestions = false;

        if ($response === null) {
            $this->loadMoreQuestionsError = 'Could not load more posts. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loadMoreQuestionsError = 'Could not load more posts. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $api->normalizeQuestions($response->json('data', []));
        $this->questions = [...$this->questions, ...$next];
        $this->questionsPage++;
        $this->hasMoreQuestions = $response->json('links.next') !== null;
    }

    public function toggleFollow(string $username = ''): void
    {
        if ($username !== '') {
            $this->toggleUserFollow($this->likers, $username);

            return;
        }

        if ($this->followBusy || $this->profile === null) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $username = Payload::string(data_get($this->profile, 'username', ''));

        if ($username === '' || (bool) data_get($this->profile, 'is_me', false)) {
            return;
        }

        $this->followBusy = true;

        $followed = (bool) data_get($this->profile, 'followed_by_me', false);

        $api = app(PinkaryApi::class);
        $response = $followed
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $this->followBusy = false;

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->setProfileValue('followed_by_me', (bool) $response->json('data.followed', ! $followed));

        $followers = $response->json('data.followers');

        if (is_int($followers)) {
            $this->setProfileValue('stats.followers', $followers);
        }
    }

    public function openUserLink(int $id): void
    {
        $url = null;

        foreach ((array) data_get($this->profile, 'links', []) as $link) {
            if (Payload::int(data_get($link, 'id', 0)) === $id) {
                $url = data_get($link, 'url');
            }
        }

        if (! is_string($url) || $url === '') {
            return;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $this->errorMessage = 'This link could not be opened.';

            return;
        }

        $api = app(PinkaryApi::class);
        $api->attempt(fn () => $api->clickLink($this->token(), $id));

        app(Browser::class)->inApp($url);
    }

    public function shareProfile(): void
    {
        $username = Payload::string(data_get($this->profile, 'username', ''));

        if ($username === '') {
            return;
        }

        $base = mb_rtrim(Payload::string(config('services.pinkary.url')), '/');

        app(Share::class)->url('Pinkary profile', '@'.$username.' on Pinkary', $base.'/@'.$username);
    }

    public function toggleQr(): void
    {
        $this->showQr = ! $this->showQr;
    }

    public function showAvatar(): void
    {
        if ($this->profile === null) {
            return;
        }

        $this->showAvatar = true;
    }

    public function toggleAvatar(): void
    {
        $this->showAvatar = ! $this->showAvatar;
    }

    public function toggleLinkVisibility(int|string $id): void
    {
        if ($this->profile === null || ! (bool) data_get($this->profile, 'is_me', false)) {
            return;
        }

        $key = null;
        $visible = false;

        foreach (Payload::array(data_get($this->profile, 'links')) as $index => $link) {
            if (Payload::int(data_get($link, 'id', 0)) === (int) $id) {
                $key = $index;
                $visible = (bool) data_get($link, 'is_visible', false);

                break;
            }
        }

        if ($key === null) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $next = ! $visible;

        $this->linkError = '';
        $this->setProfileValue('links.'.$key.'.is_visible', $next);
        $this->setProfileValue('links.'.$key.'._busy', true);

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateLinkVisibility($token, (int) $id, $next));

        $this->setProfileValue('links.'.$key.'._busy', false);

        if ($response === null) {
            $this->restoreLinkVisibility($key, $visible);
            $this->linkError = 'This link could not be updated. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->restoreLinkVisibility($key, $visible);
            $this->linkError = ApiErrors::first($response, 'This link could not be updated. Please try again.');

            return;
        }

        $stored = $response->json('data.is_visible');

        $this->setProfileValue(
            'links.'.$key.'.is_visible',
            is_bool($stored) ? $stored : $next,
        );
    }

    public function render(): View
    {
        return view('native.profile', [
            'showAvatar' => $this->showAvatar,
            'linkError' => $this->linkError,
        ]);
    }

    private function load(?string $token): void
    {
        if ($this->username === '') {
            $token = $this->requireToken(replaceScreen: true);

            if ($token === null) {
                return;
            }
        }

        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $this->username === ''
            ? $api->attempt(fn () => $api->profile($token))
            : $api->attempt(fn () => $api->user($token, $this->username));

        if ($response === null) {
            $this->loading = false;
            $this->questionsLoading = false;
            $this->errorMessage = $this->username === ''
                ? 'Your profile could not be loaded. Please try again.'
                : 'This profile could not be loaded. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->questionsLoading = false;
            $this->errorMessage = $this->username === ''
                ? 'Your profile could not be loaded. Please try again.'
                : 'This profile could not be loaded. Please try again.';

            return;
        }

        /** @var array<string, mixed>|null $profile */
        $profile = $response->json('data');
        $this->profile = $profile;
        $this->loading = false;

        if ($this->username === '' && is_array($profile)) {
            CurrentUser::store($profile);
        }

        $this->loadQuestions($token, $this->questionsUsername());
    }

    private function questionsUsername(): string
    {
        if ($this->username !== '') {
            return $this->username;
        }

        $username = data_get($this->profile, 'username');

        return is_string($username) ? $username : '';
    }

    private function restoreLinkVisibility(int|string $key, bool $visible): void
    {
        $this->setProfileValue('links.'.$key.'.is_visible', $visible);
    }

    /**
     * `Arr::set()` takes its target by reference, which widens `$profile` to a
     * plain `array` and trips every assignment to the property. `data_set()` is
     * worse: its target is `mixed`, so it cannot be narrowed back at all.
     */
    private function setProfileValue(string $path, mixed $value): void
    {
        $profile = $this->profile;

        if ($profile === null) {
            return;
        }

        Arr::set($profile, $path, $value);

        $this->profile = array_filter($profile, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    private function loadQuestions(?string $token, string $username): void
    {
        $this->questionsLoading = true;
        $this->questionsError = '';
        $this->questions = [];
        $this->questionsPage = 1;
        $this->hasMoreQuestions = false;
        $this->loadMoreQuestionsError = '';

        if ($username === '') {
            $this->questionsLoading = false;

            return;
        }

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->userQuestions($token, $username));

        if ($response === null) {
            $this->questionsLoading = false;
            $this->questionsError = 'Posts could not be loaded. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->questionsLoading = false;
            $this->questionsError = 'Posts could not be loaded. Please try again.';

            return;
        }

        $this->questions = $api->normalizeQuestions($response->json('data', []));
        $this->hasMoreQuestions = $response->json('links.next') !== null;
        $this->questionsLoading = false;
    }
}
