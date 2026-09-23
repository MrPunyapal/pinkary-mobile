<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\CurrentUser;
use App\Support\UnreadCount;
use Illuminate\Http\Client\Response;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;

/**
 * Shared drawer and compose actions for every screen under MobileLayout.
 *
 * @mixin NativeComponent
 */
trait UsesAppMenu
{
    public bool $appMenuOpen = false;

    /**
     * Read the API token from secure storage. Thin wrapper over
     * TokenStorage so screens share one null/empty normalization.
     */
    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Resolve the token or send guests to login. Returns null after
     * redirecting so callers can simply return early.
     */
    private function requireToken(): ?string
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));
        }

        return $token;
    }

    /**
     * Clear the session and send the user to login on a 401.
     * Returns true when the response was handled.
     */
    private function handleUnauthorized(Response $response): bool
    {
        if (! $response->unauthorized()) {
            return false;
        }

        app(TokenStorage::class)->clear();
        $this->replace($this->route('mobile.login'));

        return true;
    }

    public function openAppMenu(): void
    {
        $this->appMenuOpen = true;
    }

    public function closeAppMenu(): void
    {
        $this->appMenuOpen = false;
    }

    public function toggleAppMenu(): void
    {
        $this->appMenuOpen = ! $this->appMenuOpen;
    }

    public function openSettings(): void
    {
        $this->appMenuOpen = false;
        $this->navigate($this->route('mobile.settings'));
    }

    public function openOwnProfile(): void
    {
        $this->appMenuOpen = false;
        $this->navigate($this->route('mobile.profile'));
    }

    public function openSource(): void
    {
        $this->appMenuOpen = false;
        app(Browser::class)->inApp('https://github.com/pinkary-project/pinkary.com');
    }

    public function openLogin(): void
    {
        $this->appMenuOpen = false;
        $this->navigate($this->route('mobile.login'));
    }

    public function openRegister(): void
    {
        $this->appMenuOpen = false;
        $this->navigate($this->route('mobile.register'));
    }

    public function compose(): void
    {
        $this->appMenuOpen = false;

        if ($this->requireToken() === null) {
            return;
        }

        $this->navigate($this->route('mobile.compose'));
    }

    public function logout(): void
    {
        $this->appMenuOpen = false;
        $token = $this->token();

        if ($token !== null) {
            app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($token));
        }

        app(TokenStorage::class)->clear();
        CurrentUser::clear();
        UnreadCount::clear();
        $this->replace($this->route('mobile.feed'));
    }
}
