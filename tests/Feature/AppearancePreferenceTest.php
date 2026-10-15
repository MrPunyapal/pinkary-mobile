<?php

declare(strict_types=1);

use App\Support\AppearancePreference;
use Native\Mobile\Testing\FakeBridge;

it('uses system appearance when storage is empty or contains an unknown mode', function (): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Get', ['status' => 'not_found', 'value' => null]);

    expect(AppearancePreference::get())->toBe('system');

    $bridge->respondTo('SecureStorage.Get', ['status' => 'found', 'value' => 'automatic']);

    expect(AppearancePreference::get())->toBe('system');
});

it('stores only supported appearance modes', function (): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Set', ['success' => true]);

    expect(AppearancePreference::store('dark'))->toBeTrue()
        ->and(AppearancePreference::store('automatic'))->toBeFalse();

    $bridge->assertCalled('SecureStorage.Set', fn (array $params): bool => $params === [
        'key' => 'pinkary_appearance',
        'value' => 'dark',
    ]);

    $bridge->respondTo('SecureStorage.Get', ['status' => 'found', 'value' => 'dark']);

    expect(AppearancePreference::get())->toBe('dark');
});

it('applies each selected palette to both native appearance modes', function (): void {
    FakeBridge::enable();

    $theme = config('native-ui.theme');

    expect($theme)->toBeArray();

    $lightBackground = $theme['light']['background'];
    $darkBackground = $theme['dark']['background'];

    AppearancePreference::apply('light');
    AppearancePreference::apply('dark');

    expect(config('native-ui.theme.light.background'))->toBe($darkBackground)
        ->and(config('native-ui.theme.dark.background'))->toBe($darkBackground);

    AppearancePreference::apply('system');

    expect(config('native-ui.theme.light.background'))->toBe($lightBackground)
        ->and(config('native-ui.theme.dark.background'))->toBe($darkBackground);
});
