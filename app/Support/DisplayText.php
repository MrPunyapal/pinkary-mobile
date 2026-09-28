<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Text helpers for the native views.
 *
 * The API ships the same parsed HTML the web renders (answers carry links,
 * mentions, embeds and entities). Native text cannot render HTML, so every
 * screen has to flatten it to plain words first, and every screen has to
 * phrase timestamps the way the web feed does. Both used to be re-declared
 * per Blade view, which is how they drift apart; there is one definition
 * of each here instead.
 */
final class DisplayText
{
    /**
     * Flatten the API's parsed HTML to the words a native:text can show.
     *
     * strip_tags removes the markup (a mention's <a> wrapper, an embed's
     * <iframe>) but keeps its text, so "@ada" and "#laravel" still read the
     * way the web shows them.
     */
    public static function plain(mixed $html): string
    {
        if (! is_string($html) && ! is_numeric($html)) {
            return '';
        }

        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Phrase a timestamp the way the web feed does: short diffForHumans,
     * empty string when there is no usable date.
     */
    public static function relativeTime(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        try {
            return Carbon::parse($iso)->diffForHumans(short: true);
        } catch (\Throwable) {
            return '';
        }
    }
}
