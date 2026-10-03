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
 * @mixin NativeComponent
 */
trait UsesAppMenu
{
    public bool $appMenuOpen = false;

    public ?string $confirmAction = null;

    public string $confirmAccountId = '';

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function requireToken(bool $replaceScreen = false): ?string
    {
        $token = $this->token();

        if ($token === null) {
            if ($replaceScreen) {
                $this->replace($this->route('mobile.login'));
            } else {
                $this->navigate($this->route('mobile.login'));
            }
        }

        return $token;
    }

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

    public function openAbout(): void
    {
        $this->appMenuOpen = false;
        app(Browser::class)->inApp($this->aboutUrl());
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

    public function askLogout(): void
    {
        $this->confirmAction = 'logout';
        $this->confirmAccountId = AccountStore::activeId() ?? '';
    }

    public function askLogoutAll(): void
    {
        $this->confirmAction = 'logout-all';
        $this->confirmAccountId = '';
    }

    public function askRemoveAccount(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->confirmAction = 'remove-account';
        $this->confirmAccountId = $id;
    }

    public function confirm(): void
    {
        $action = $this->confirmAction;
        $accountId = $this->confirmAccountId;

        $this->cancelConfirm();

        match ($action) {
            'logout' => $this->logout(),
            'logout-all' => $this->logoutAll(),
            'remove-account' => $this->removeAccount($accountId),
            default => null,
        };
    }

    public function cancelConfirm(): void
    {
        $this->confirmAction = null;
        $this->confirmAccountId = '';
        $this->appMenuOpen = false;
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

    private function aboutUrl(): string
    {
        return rtrim((string) config('services.pinkary.url'), '/').'/about';
    }

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

    public function addAccount(): void
    {
        $this->appMenuOpen = false;
        session(['pinkary_add_account' => true]);
        $this->navigate($this->route('mobile.login'));
    }

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
