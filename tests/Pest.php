<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Shared API fixtures
|--------------------------------------------------------------------------
|
| These live here, not in a single test file, on purpose. They were declared
| inside ProfileTest.php, which made every other file that called them pass
| ONLY when the whole suite loaded — running one file reported bogus
| "undefined function" errors. A helper used by more than one test file
| belongs in this file.
|
*/

/**
 * The signed-in user's own profile payload, as `GET /api/v1/profile` returns it.
 *
 * @return array<string, mixed>
 */
function ownProfile(): array
{
    return [
        'id' => 1,
        'name' => 'Pinkary User',
        'username' => 'pinkaryuser',
        'email' => 'pinkary@example.com',
        'bio' => 'Building in public.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 1500, 'following' => 12, 'posts' => 34, 'views' => 999],
        'followed_by_me' => false,
        'is_me' => true,
        'links' => [
            ['id' => 7, 'description' => 'Website', 'url' => 'https://example.com?ref=pinkary'],
        ],
        'member_since' => ['human' => 'today'],
    ];
}

/**
 * Another user's profile payload, as `GET /api/v1/users/{username}` returns it.
 *
 * @return array<string, mixed>
 */
function otherProfile(): array
{
    return [
        'id' => 2,
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => null,
        'bio' => 'First programmer.',
        'avatar' => null,
        'verification' => ['profile' => true, 'company' => false],
        'stats' => ['followers' => 0, 'following' => 0, 'posts' => 3, 'views' => 10],
        'followed_by_me' => false,
        'is_me' => false,
        'links' => [],
        'member_since' => ['human' => '2 years ago'],
    ];
}
