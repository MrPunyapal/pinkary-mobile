<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\CurrentUser;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Settings extends NativeComponent
{
    use UsesAppMenu;

    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $errorMessage = '';

    public bool $loading = true;

    public function navTitle(): string
    {
        return 'Settings';
    }

    public function mount(): void
    {
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
     * Open the profile editor: Settings on the web is the profile/edit
     * page (layouts/navigation:342), so the screen leads there.
     */
    public function editProfile(): void
    {
        $this->appMenuOpen = false;
        $this->navigate($this->route('mobile.profile.edit'));
    }

    public function render(): View
    {
        return view('native.settings');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $cached = CurrentUser::get();

        if (is_array($cached)) {
            $this->profile = $cached;
            $this->loading = false;
        }

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->profile($token));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'Settings could not be loaded. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            CurrentUser::clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'Settings could not be loaded. Please try again.';

            return;
        }

        /** @var array<string, mixed>|null $profile */
        $profile = $response->json('data');
        $this->profile = $profile;
        $this->loading = false;

        if (is_array($profile)) {
            CurrentUser::store($profile);
        }
    }
}
