<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Search extends NativeComponent
{
    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    /** @var list<array<string, mixed>> */
    public array $hashtags = [];

    public bool $searching = false;

    public bool $searched = false;

    public function navTitle(): string
    {
        return 'Search';
    }

    public function mount(): void
    {
        if ($this->token() === null) {
            $this->replace($this->route('mobile.login'));
        }
    }

    public function updatedQuery(): void
    {
        $this->search();
    }

    public function search(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $query = mb_trim($this->query, "@# \t\n\r\0\x0B");

        if ($query === '') {
            $this->users = [];
            $this->hashtags = [];
            $this->searched = false;

            return;
        }

        $this->searching = true;

        $response = app(PinkaryApi::class)->search($token, $query);

        $this->searching = false;

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            return;
        }

        /** @var list<array<string, mixed>> $users */
        $users = $response->json('data.users', []);
        /** @var list<array<string, mixed>> $hashtags */
        $hashtags = $response->json('data.hashtags', []);

        $this->users = $users;
        $this->hashtags = $hashtags;
        $this->searched = true;
    }

    public function render(): View
    {
        return view('native.search');
    }

    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
