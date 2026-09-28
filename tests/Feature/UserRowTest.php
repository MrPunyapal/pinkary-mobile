<?php

use App\NativeComponents\Search;
use App\Support\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada']);
});

function fakeUserSearch(array $user): void
{
    Http::fake([
        'https://pinkary.test/api/v1/search*' => Http::response(['data' => ['users' => [$user], 'hashtags' => []]]),
    ]);
}

function searchUserPayload(array $verification, array $extra = []): array
{
    return array_merge([
        'id' => 2,
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'avatar' => null,
        'verification' => $verification,
        'followed_by_me' => false,
        'is_me' => false,
    ], $extra);
}

it('shows the pink seal for a verified profile', function (): void {
    fakeUserSearch(searchUserPayload(['profile' => true, 'company' => false]));

    // The seal is the only pink/amber icon on the row (the search and
    // clear icons are slate), so its colour proves which branch ran.
    Native::test(Search::class)
        ->set('query', 'ada')
        ->assertSee('Ada Lovelace')
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? '') === '#EC4899');
});

it('uses the amber company seal when both verifications are on', function (): void {
    fakeUserSearch(searchUserPayload(['profile' => true, 'company' => true]));

    Native::test(Search::class)
        ->set('query', 'ada')
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? '') === '#EAB308');
});

it('ignores the dead badge keys and a company-only account', function (): void {
    // `verified` / `company_verified` are not UserResource keys, and a
    // company badge alone is not a verification seal on the web.
    fakeUserSearch(searchUserPayload(['profile' => false, 'company' => true], [
        'verified' => true,
        'company_verified' => true,
    ]));

    Native::test(Search::class)
        ->set('query', 'ada')
        ->assertSee('Ada Lovelace')
        ->assertMissingElement('icon', fn (array $node): bool => in_array($node['props']['color'] ?? '', ['#EC4899', '#EAB308'], true));
});
