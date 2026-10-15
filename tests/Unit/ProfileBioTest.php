<?php

declare(strict_types=1);

use App\Support\ProfileBio;

it('turns mentions and hashtags into links without changing surrounding text', function (): void {
    expect(ProfileBio::runs('Laravel developer @Punyapal #PHP'))
        ->toBe([
            ['text' => 'Laravel developer ', 'type' => 'text', 'target' => ''],
            ['text' => '@Punyapal', 'type' => 'mention', 'target' => 'Punyapal'],
            ['text' => ' ', 'type' => 'text', 'target' => ''],
            ['text' => '#PHP', 'type' => 'hashtag', 'target' => 'PHP'],
        ]);
});

it('leaves malformed and non-string bios alone', function (): void {
    $bio = 'Email me at@person.com, @abc, and @1234.';
    $runs = ProfileBio::runs($bio);

    expect(ProfileBio::runs(null))->toBe([])
        ->and(ProfileBio::runs(''))->toBe([])
        ->and(implode('', array_column($runs, 'text')))->toBe($bio)
        ->and(array_unique(array_column($runs, 'type')))->toBe(['text']);
});

it('falls back to plain text when the bio is not valid UTF-8', function (): void {
    expect(ProfileBio::runs("Invalid byte: \xB1"))->toBe([
        ['text' => "Invalid byte: \xB1", 'type' => 'text', 'target' => ''],
    ]);
});
