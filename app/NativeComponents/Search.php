<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\OpensProfiles;
use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\Payload;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class Search extends NativeComponent implements HasWebHead
{
    use OpensProfiles;
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;

    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    /** @var list<array<string, mixed>> */
    public array $hashtags = [];

    public bool $searching = false;

    public bool $searched = false;

    public string $errorMessage = '';

    /**
     * @var list<string>
     */
    public array $recentSearches = [];

    public function updatedQuery(): void
    {
        $this->search();
    }

    public function clearQuery(): void
    {
        $this->query = '';
        $this->users = [];
        $this->hashtags = [];
        $this->searched = false;
        $this->errorMessage = '';
    }

    public function selectRecent(int $index): void
    {
        $query = $this->recentSearches[$index] ?? null;

        if (! is_string($query) || mb_trim($query) === '') {
            return;
        }

        $this->query = $query;
        $this->search();
    }

    public function search(): void
    {
        $token = $this->token();

        $query = mb_trim($this->query, "@# \t\n\r\0\x0B");

        if ($query === '') {
            $this->users = [];
            $this->hashtags = [];
            $this->searched = false;

            return;
        }

        $this->searching = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->search($token, $query));

        $this->searching = false;

        if ($response === null) {
            $this->errorMessage = 'Search could not be completed. Please try again.';

            return;
        }

        if ($token !== null && $this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        /** @var list<array<string, mixed>> $users */
        $users = $response->json('data.users', []);
        $users = array_map(static function (array $user): array {
            $profileVerified = data_get($user, 'verification.profile');
            $companyVerified = data_get($user, 'verification.company');
            $user['verification'] = [
                'profile' => (bool) ($profileVerified ?? $user['verified'] ?? false),
                'company' => (bool) ($companyVerified ?? $user['company_verified'] ?? false),
            ];
            $user['followed_by_me'] = (bool) ($user['followed'] ?? $user['followed_by_me'] ?? false);

            return $user;
        }, $users);

        /** @var list<array<string, mixed>> $hashtags */
        $hashtags = $response->json('data.hashtags', []);

        $this->users = $users;
        $this->hashtags = $hashtags;
        $this->searched = true;
        $this->rememberRecent($query);
    }

    public function render(): View
    {
        return view('native.search');
    }

    public function toggleFollow(string $username): void
    {
        if ($username === '') {
            return;
        }

        $index = null;
        $following = false;

        foreach ($this->users as $key => $user) {
            if (Payload::string(data_get($user, 'username', '')) === $username) {
                $index = $key;
                $following = (bool) data_get($user, 'followed_by_me', false);

                break;
            }
        }

        if ($index === null) {
            return;
        }

        if ((bool) data_get($this->users[$index], 'is_me', false) || (bool) data_get($this->users[$index], '_busy', false)) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->users[$index]['_busy'] = true;

        $api = app(PinkaryApi::class);
        $response = $following
            ? $api->attempt(fn () => $api->unfollow($token, $username))
            : $api->attempt(fn () => $api->follow($token, $username));

        $this->users[$index]['_busy'] = false;

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->users[$index]['followed_by_me'] = (bool) $response->json('data.followed', ! $following);
    }

    public function openHashtag(string $name): void
    {
        if ($name === '') {
            return;
        }

        $this->query = '#'.$name;
        $this->search();
    }

    private function rememberRecent(string $query): void
    {
        $this->recentSearches = array_slice(
            array_values(array_unique([$query, ...$this->recentSearches])),
            0,
            5,
        );
    }
}
