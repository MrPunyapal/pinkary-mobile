<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\CurrentUser;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;

/**
 * Shared drawer and compose actions for every screen under MobileLayout.
 *
 * @mixin NativeComponent
 */
trait UsesAppMenu
{
    public function openSettings(): void
    {
        $this->navigate($this->route('mobile.settings'));
    }

    public function openOwnProfile(): void
    {
        $this->navigate($this->route('mobile.profile'));
    }

    public function openSource(): void
    {
        app(Browser::class)->inApp('https://github.com/pinkary-project/pinkary.com');
    }

    public function compose(): void
    {
        $token = app(TokenStorage::class)->get();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->navigate($this->route('mobile.compose'));
    }

    public function logout(): void
    {
        $token = app(TokenStorage::class)->get();

        if (is_string($token) && $token !== '') {
            app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($token));
        }

        app(TokenStorage::class)->clear();
        CurrentUser::clear();
        $this->replace($this->route('mobile.feed'));
    }
}
