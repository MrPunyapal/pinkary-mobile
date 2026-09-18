<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;

final class Profile extends NativeComponent
{
    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $username = '';

    public string $errorMessage = '';

    public bool $loading = true;

    public bool $followBusy = false;

    public function navTitle(): string
    {
        return $this->username !== '' ? '@'.$this->username : 'Profile';
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

        $response = $followed
            ? app(PinkaryApi::class)->unfollow($token, $username)
            : app(PinkaryApi::class)->follow($token, $username);

        $this->followBusy = false;

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
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

        app(PinkaryApi::class)->clickLink($token, $id);

        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
    }

    public function logout(): void
    {
        $token = $this->token();

        if ($token !== null) {
            app(PinkaryApi::class)->logout($token);
        }

        session()->forget('pinkary_api_token');
        $this->replace($this->route('mobile.feed'));
    }

    public function compose(): void
    {
        $this->navigate($this->route('mobile.compose'));
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
            ? app(PinkaryApi::class)->profile($token)
            : app(PinkaryApi::class)->user($token, $this->username);

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
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
        $token = session('pinkary_api_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
