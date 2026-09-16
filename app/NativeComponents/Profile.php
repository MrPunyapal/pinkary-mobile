<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Profile extends NativeComponent
{
    /** @var array<string, mixed>|null */
    public ?array $profile = null;

    public string $errorMessage = '';

    public bool $loading = true;

    public function navTitle(): string
    {
        return 'Profile';
    }

    public function mount(): void
    {
        $this->loadProfile();
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

    private function loadProfile(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $response = app(PinkaryApi::class)->profile($token);

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'Your profile could not be loaded. Please try again.';

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

    public function render(): View
    {
        return view('native.profile');
    }
}
