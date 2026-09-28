<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\TogglesPostReactions;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
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

    /**
     * The tap-to-zoom avatar lightbox (links/index:105-124).
     */
    public bool $showAvatar = false;

    /**
     * Why the last owner link action failed, shown on the card itself —
     * a card-level action must not blow away the whole screen the way
     * $errorMessage (the load-failure banner) does.
     */
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

        $this->load($this->token());
    }

    public function retry(): void
    {
        $this->load($this->token());
    }

    /**
     * Retry the posts list without reloading the profile card.
     */
    public function retryQuestions(): void
    {
        $this->loadQuestions($this->token(), $this->questionsUsername());
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

    /**
     * Follow or unfollow the shown user, reconciling the card with the
     * server's follower count.
     *
     * The same name serves the card pill (no argument) and the follow
     * button on a liker row inside this screen's post menu sheet (a
     * username) — the shared TogglesPostReactions implementation is
     * shadowed by this class, so the row case is routed explicitly
     * instead of silently falling through to the card.
     */
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
     * Open a profile link, recording the visit like the web does.
     * Looks the URL up from the loaded profile so markup never
     * carries (HTML-escapable) URLs. Guests open the link too — the
     * click record is a signed-in-only write and is simply skipped.
     */
    public function openUserLink(int $id): void
    {
        $url = null;

        foreach ((array) data_get($this->profile, 'links', []) as $link) {
            if ((int) data_get($link, 'id', 0) === $id) {
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

        $token = $this->token();

        if ($token !== null) {
            $api = app(PinkaryApi::class);
            $api->attempt(fn () => $api->clickLink($token, $id));
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

    /**
     * Open the large-avatar sheet (links/index:105-124). Only a loaded
     * card can show one, so an empty profile opens nothing.
     */
    public function showAvatar(): void
    {
        if ($this->profile === null) {
            return;
        }

        $this->showAvatar = true;
    }

    /**
     * Flip the large-avatar sheet — the tap target, the drag-down
     * dismiss and the Close button all land here.
     */
    public function toggleAvatar(): void
    {
        $this->showAvatar = ! $this->showAvatar;
    }

    /**
     * Show or hide one of your own links (the web's eye toggle,
     * links/index:285-298). Flips the cached row optimistically, then
     * reconciles with the server's LinkResource; a failure puts the row
     * back and surfaces a message on the card.
     */
    public function toggleLinkVisibility(int|string $id): void
    {
        // The web only ever renders the eye on your own rows, and the
        // endpoint is owner-gated (LinkPolicy) — refuse anything else so
        // a stray tap cannot fire a write.
        if ($this->profile === null || ! (bool) data_get($this->profile, 'is_me', false)) {
            return;
        }

        $key = null;
        $visible = false;

        foreach ((array) data_get($this->profile, 'links', []) as $index => $link) {
            if ((int) data_get($link, 'id', 0) === (int) $id) {
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
        data_set($this->profile, 'links.'.$key.'.is_visible', $next);
        data_set($this->profile, 'links.'.$key.'._busy', true);

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateLinkVisibility($token, (string) $id, $next));

        data_set($this->profile, 'links.'.$key.'._busy', false);

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

        data_set(
            $this->profile,
            'links.'.$key.'.is_visible',
            is_bool($stored) ? $stored : $next,
        );
    }

    public function render(): View
    {
        // native/profile.blade.php hands the card nothing but the profile
        // array, and a Blade component cannot reach back to the screen —
        // so the card's own transient state (the avatar sheet, the link
        // error) is mirrored onto it under `_`-prefixed keys. Client-side
        // only: UserResource never sends keys like these, and nothing here
        // is ever posted back to the API.
        if ($this->profile !== null) {
            $this->profile['_show_avatar'] = $this->showAvatar;
            $this->profile['_link_error'] = $this->linkError;
        }

        return view('native.profile');
    }

    private function load(?string $token): void
    {
        // Your own profile is signed-in only; someone else's is public
        // on the web, so guests read it with a null token.
        if ($this->username === '') {
            $token = $this->requireToken();

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
     * Put a link row's visibility back after a failed write, so the eye
     * never lies about what the server holds.
     */
    private function restoreLinkVisibility(int $key, bool $visible): void
    {
        data_set($this->profile, 'links.'.$key.'.is_visible', $visible);
    }

    /**
     * Load the profile's answered posts, pinned first (server-side).
     */
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
