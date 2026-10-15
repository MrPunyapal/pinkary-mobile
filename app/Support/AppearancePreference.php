<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\SecureStorage;
use Native\Mobile\UI\Theme;
use Throwable;

final class AppearancePreference
{
    private const string KEY = 'pinkary_appearance';

    /** @var list<string> */
    private const array MODES = ['system', 'light', 'dark'];

    private static ?string $appliedMode = null;

    /** @var array<string, mixed>|null */
    private static ?array $defaultTheme = null;

    public static function get(): string
    {
        try {
            $result = SecureStorage::read(self::KEY);

            if ($result->found() && is_string($result->value) && in_array($result->value, self::MODES, true)) {
                return $result->value;
            }
        } catch (Throwable) {
        }

        return 'system';
    }

    public static function store(string $mode): bool
    {
        if (! in_array($mode, self::MODES, true)) {
            return false;
        }

        try {
            return SecureStorage::set(self::KEY, $mode);
        } catch (Throwable) {
            return false;
        }
    }

    public static function apply(?string $mode = null): void
    {
        $mode ??= self::get();

        if (self::$appliedMode === $mode) {
            return;
        }

        if (self::$defaultTheme === null) {
            $theme = config('native-ui.theme');

            if (! is_array($theme)) {
                return;
            }

            self::$defaultTheme = Payload::stringMap($theme);
        }

        $tokens = self::$defaultTheme;

        if ($mode !== 'system') {
            $palette = $tokens[$mode] ?? null;

            if (! is_array($palette)) {
                return;
            }

            $tokens['light'] = $palette;
            $tokens['dark'] = $palette;
        }

        Theme::load($tokens);
        self::$appliedMode = $mode;
    }
}
