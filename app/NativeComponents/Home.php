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

        $response = app(PinkaryApi::class)->feed($token);

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

    public function profile(): void
    {
        $this->navigate($this->route('mobile.profile'));
    }

    public function render(): View
    {
        return view('native.home');
    }

    private function token(): ?string
    {
        $token = session('pinkary_api_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
