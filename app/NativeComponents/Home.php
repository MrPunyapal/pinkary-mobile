<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Home extends NativeComponent
{
    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public bool $loading = true;

    public string $errorMessage = '';

    public string $tab = 'recent';

    public function navTitle(): string
    {
        return 'Feed';
    }

    public function mount(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.home'));

            return;
        }

        $this->loadFeed($token);
    }

    public function showRecent(): void
    {
        $this->switchTab('recent');
    }

    public function showFollowing(): void
    {
        $this->switchTab('following');
    }

    public function showTrending(): void
    {
        $this->switchTab('trending');
    }

    public function profile(): void
    {
        $this->navigate($this->route('mobile.profile'));
    }

    public function render(): View
    {
        return view('native.home');
    }

    private function switchTab(string $tab): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.home'));

            return;
        }

        $this->tab = $tab;
        $this->loadFeed($token);
    }

    private function loadFeed(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $response = app(PinkaryApi::class)->feed($token, $this->tab);

        if ($response->unauthorized()) {
            session()->forget('pinkary_api_token');
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'The Pinkary feed could not be loaded. Please try again.';

            return;
        }

        /** @var list<array<string, mixed>> $posts */
        $posts = $response->json('data', []);
        $this->posts = $posts;
        $this->loading = false;
    }

    private function token(): ?string
    {
        $token = session('pinkary_api_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
