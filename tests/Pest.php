<?php

declare(strict_types=1);

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void {}

/**
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
        'gradient' => 'from-blue-500 to-purple-600',
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
