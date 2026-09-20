<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Share;

final class Profile extends NativeComponent
{
    use UsesAppMenu;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $username = '';

    public string $errorMessage = '';

    public bool $loading = true;

    public bool $followBusy = false;

    public bool $showQr = false;

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
     * Follow or unfollow the shown user, reconciling the card with the
     * server's follower count.
     */
    public function toggleFollow(): void
    {
        if ($this->followBusy || $this->profile === null) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

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

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

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
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

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

        app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->clickLink($token, $id));

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
            $this->errorMessage = $this->username === ''
                ? 'Your profile could not be loaded. Please try again.'
                : 'This profile could not be loaded. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = $this->username === ''
                ? 'Your profile could not be loaded. Please try again.'
                : 'This profile could not be loaded. Please try again.';

            return;
        }

        /** @var array<string, mixed>|null $profile */
        $profile = $response->json('data');
        $this->profile = $profile;
        $this->loading = false;
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
