<?php

declare(strict_types=1);

use App\Support\AccountStore;
use App\Support\DraftStore;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    AccountStore::clear();
    AccountStore::add('ada', 'test-token', ['username' => 'ada', 'name' => 'Ada']);
});

afterEach(function (): void {
    DraftStore::clear(null);
    DraftStore::clear('grace');
    AccountStore::clear();
});

it('stores and restores a thread draft', function (): void {
    DraftStore::put(null, [
        'content' => 'Half-written thought',
        'posts' => ['A follow-up.'],
        'isPoll' => true,
        'pollOptions' => ['Yes', 'No'],
        'pollDuration' => 3,
        'channelId' => 7,
        'channelName' => 'Laravel',
        'anonymous' => false,
    ]);

    $draft = DraftStore::get(null);

    expect($draft)->not->toBeNull()
        ->and($draft['content'])->toBe('Half-written thought')
        ->and($draft['posts'])->toBe(['A follow-up.'])
        ->and($draft['isPoll'])->toBeTrue()
        ->and($draft['pollOptions'])->toBe(['Yes', 'No'])
        ->and($draft['pollDuration'])->toBe(3)
        ->and($draft['channelId'])->toBe(7)
        ->and($draft['channelName'])->toBe('Laravel');
});

it('does not restore a thread draft into an ask target', function (): void {
    DraftStore::put(null, ['content' => 'Thread only']);

    expect(DraftStore::get('grace'))->toBeNull()
        ->and(DraftStore::get(null)['content'])->toBe('Thread only');
});

it('keeps ask drafts per target username', function (): void {
    DraftStore::put('grace', ['content' => 'Ask Grace', 'anonymous' => true]);
    DraftStore::put('linus', ['content' => 'Ask Linus']);

    expect(DraftStore::get('grace')['content'])->toBe('Ask Grace')
        ->and(DraftStore::get('grace')['anonymous'])->toBeTrue()
        ->and(DraftStore::get('linus')['content'])->toBe('Ask Linus')
        ->and(DraftStore::get(null))->toBeNull();
});

it('ignores empty drafts', function (): void {
    DraftStore::put(null, ['content' => '   ', 'posts' => ['', '']]);

    expect(DraftStore::get(null))->toBeNull();
});

it('clears a stored draft', function (): void {
    DraftStore::put(null, ['content' => 'Keep me']);
    DraftStore::clear(null);

    expect(DraftStore::get(null))->toBeNull();
});

it('replaces a draft with empty content by clearing it', function (): void {
    DraftStore::put(null, ['content' => 'Keep me']);
    DraftStore::put(null, ['content' => '']);

    expect(DraftStore::get(null))->toBeNull();
});
