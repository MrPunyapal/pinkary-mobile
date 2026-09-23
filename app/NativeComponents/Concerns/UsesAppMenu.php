<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
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
     * Clear the session and send the user to login on a 401. The dead
     * token's account is dropped first — when other accounts remain the
     * newest one takes over on the feed instead of stranding the user
     * on login with valid accounts still stored.
     * Returns true when the response was handled.
     */
    private function handleUnauthorized(Response $response): bool
    {
        if (! $response->unauthorized()) {
            return false;
        }

        AccountStore::removeActive();
        $this->afterActiveAccountRemoved();

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

        AccountStore::removeActive();
        $this->afterActiveAccountRemoved();
    }

    /**
     * Revoke every stored token server-side (best-effort) and forget all
     * accounts — web parity for "log out of all accounts".
     */
    public function logoutAll(): void
    {
        $this->appMenuOpen = false;

        foreach (AccountStore::allTokens() as $token) {
            app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($token));
        }

        AccountStore::clear();
        CurrentUser::clear();
        UnreadCount::clear();
        $this->replace($this->route('mobile.feed'));
    }

    /**
     * Switch to another stored account after proving its token still
     * works. Dead tokens are dropped instead of activated; when the
     * network itself fails everything is left untouched.
     */
    public function switchAccount(string $id): void
    {
        $this->appMenuOpen = false;

        if ($id === '' || $id === AccountStore::activeId()) {
            return;
        }

        $token = AccountStore::tokenFor($id);

        if ($token === null) {
            return;
        }

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->profile($token));

        if ($response === null) {
            return;
        }

        if ($response->unauthorized()) {
            AccountStore::remove($id);

            return;
        }

        if (! $response->successful()) {
            return;
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json('data');

        if (! is_array($data) || empty($data['username'])) {
            return;
        }

        AccountStore::activate($id);
        AccountStore::refreshProfile($id, $data);
        CurrentUser::store($data);
        $this->replace($this->route('mobile.feed'));
    }

    /**
     * Revoke an account's token server-side (best-effort) and forget it.
     * Removing the active account falls through to the next one.
     */
    public function removeAccount(string $id): void
    {
        $this->appMenuOpen = false;

        if ($id === '') {
            return;
        }

        $wasActive = $id === AccountStore::activeId();
        $token = AccountStore::tokenFor($id);

        if ($token !== null) {
            app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($token));
        }

        AccountStore::remove($id);

        if ($wasActive) {
            $this->afterActiveAccountRemoved();
        }
    }

    /**
     * Open login in add mode: the next successful authentication joins
     * the stored accounts instead of replacing them.
     */
    public function addAccount(): void
    {
        $this->appMenuOpen = false;
        session(['pinkary_add_account' => true]);
        $this->navigate($this->route('mobile.login'));
    }

    /**
     * Land on the feed under the newly-active account, or as a guest
     * when none remain. Call after any removal of the active account.
     */
    private function afterActiveAccountRemoved(): void
    {
        $active = AccountStore::active();

        if ($active === null) {
            CurrentUser::clear();
            UnreadCount::clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        CurrentUser::store($active);
        $this->replace($this->route('mobile.feed'));
    }
}
