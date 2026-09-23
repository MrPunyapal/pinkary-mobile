<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\CurrentUser;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Share;

final class Profile extends NativeComponent
{
    use TogglesPostReactions;
    use UsesAppMenu;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $username = '';

    public string $errorMessage = '';

    public bool $loading = true;

    public bool $followBusy = false;

    public bool $showQr = false;

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

    public function navTitle(): string
    {
        return $this->username !== '' ? '@'.$this->username : 'Profile';
    }

    // Other users' profiles show a back arrow instead of the drawer
    // hamburger (no overlap); own profile keeps the drawer.
    public function hidesDrawer(): bool
    {
        return $this->username !== '';
    }

    public function mount(): void
    {
        $this->username = (string) $this->param('username', '');

        $token = $this->requireToken();

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
     * Retry the posts list without reloading the profile card.
     */
    public function retryQuestions(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->loadQuestions($token, $this->questionsUsername());
    }

    /**
     * Open this profile's followers list.
     */
    public function openFollowers(): void
    {
        $username = $this->questionsUsername();

        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.followers', ['username' => $username]));
    }

    /**
     * Open this profile's following list.
     */
    public function openFollowing(): void
    {
        $username = $this->questionsUsername();

        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.following', ['username' => $username]));
    }

    /**
     * Ask this user a question (other users' profiles only).
     */
    public function askUser(): void
    {
        $username = (string) data_get($this->profile, 'username', '');

        if ($username === '' || (bool) data_get($this->profile, 'is_me', false)) {
            return;
        }

        $this->navigate($this->route('mobile.ask', ['username' => $username]));
    }

    /**
     * Edit your own profile (own profile only).
     */
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

    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }

    public function openLink(string $url): void
    {
        $this->openExternalLink($url);
    }

    public function toggleShowMore(string $id): void
    {
        $this->toggleExpandedId($id);
    }

    public function toggleLike(string $id): void
    {
        $this->reactOnPostPayload($this->questions, $id, 'like', $this->token());
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

        $this->translateHtml(data_get($post, $field));
    }

    public function sharePost(string $id): void
    {
        $post = $this->readPostPayload($this->questions, $id);

        if ($post === null) {
            return;
        }

        $this->shareQuestion(
            (string) data_get($post, 'to.username', ''),
            $id,
            (string) (data_get($post, 'answer') ?? data_get($post, 'content')),
        );
    }

    public function votePoll(string $id, int $optionId): void
    {
        $this->voteOnPostPayload($this->questions, $id, $optionId, $this->token());
    }

    /**
     * Append the next page of this profile's posts.
     */
    public function loadMoreQuestions(): void
    {
        if ($this->loadingMoreQuestions || ! $this->hasMoreQuestions) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

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

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->loadMoreQuestionsError = 'Could not load more posts. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $next */
        $next = $response->json('data', []);
        $this->questions = [...$this->questions, ...$next];
        $this->questionsPage++;
        $this->hasMoreQuestions = $response->json('links.next') !== null;
    }

    /**
     * Follow or unfollow the shown user, reconciling the card with the
     * server's follower count.
     */
    public function toggleFollow(): void
    {
        if ($this->followBusy || $this->profile === null) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $username = (string) data_get($this->profile, 'username', '');

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

        data_set($this->profile, 'followed_by_me', (bool) $response->json('data.followed', ! $followed));

        $followers = $response->json('data.followers');

        if (is_int($followers)) {
            data_set($this->profile, 'stats.followers', $followers);
        }
    }

    /**
     * Record the visit like the web does, then open the link.
     * Looks the URL up from the loaded profile so markup never
     * carries (HTML-escapable) URLs.
     */
    public function openUserLink(int $id): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $url = null;

        foreach ((array) data_get($this->profile, 'links', []) as $link) {
            if ((int) data_get($link, 'id', 0) === $id) {
                $url = data_get($link, 'url');
            }
        }

        if (! is_string($url) || $url === '') {
            return;
        }

        $api = app(PinkaryApi::class);
        $api->attempt(fn () => $api->clickLink($token, $id));

        if (! preg_match('#^https?://#i', $url)) {
            $this->errorMessage = 'This link could not be opened.';

            return;
        }

        app(Browser::class)->inApp($url);
    }

    /**
     * Share this profile through the system sheet (web share menu parity).
     */
    public function shareProfile(): void
    {
        $username = (string) data_get($this->profile, 'username', '');

        if ($username === '') {
            return;
        }

        $base = rtrim((string) config('services.pinkary.url'), '/');

        app(Share::class)->url('Pinkary profile', '@'.$username.' on Pinkary', $base.'/@'.$username);
    }

    /**
     * Toggle the QR code section showing this profile's URL.
     */
    public function toggleQr(): void
    {
        $this->showQr = ! $this->showQr;
    }

    public function render(): View
    {
        return view('native.profile');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $response = $this->username === ''
            ? app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->profile($token))
            : app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->user($token, $this->username));

        if ($response === null) {
            $this->loading = false;
            $this->questionsLoading = false;
            $this->errorMessage = $this->username === ''
                ? 'Your profile could not be loaded. Please try again.'
                : 'This profile could not be loaded. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
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

    /**
     * The username owning the shown card — the route param for other
     * users, the loaded card for your own profile.
     */
    private function questionsUsername(): string
    {
        if ($this->username !== '') {
            return $this->username;
        }

        $username = data_get($this->profile, 'username');

        return is_string($username) ? $username : '';
    }

    /**
     * Load the profile's answered posts, pinned first (server-side).
     */
    private function loadQuestions(string $token, string $username): void
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

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->questionsLoading = false;
            $this->questionsError = 'Posts could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $questions */
        $questions = $response->json('data', []);
        $this->questions = $questions;
        $this->hasMoreQuestions = $response->json('links.next') !== null;
        $this->questionsLoading = false;
    }
}
