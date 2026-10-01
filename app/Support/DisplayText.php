<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Carbon;

/**
 * Text helpers for the native views.
 *
 * Converts API HTML into plain text or tappable native runs, and formats
 * timestamps consistently across the feed, profiles, and detail screens.
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

        $html = preg_replace('/<br\s*\/?\s*>|<\/(?:p|div|li|blockquote)>/i', "\n", (string) $html);

        return trim(html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Preserve link targets while producing text safe for native rendering.
     *
     * @return list<array{text: string, type: string, target: string}>
     */
    public static function runs(mixed $html, ?int $limit = null): array
    {
        $text = self::plain($html);

        if ($text === '') {
            return [];
        }

        $runs = [];
        if (is_string($html) && str_contains($html, '<')) {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $walk = function (DOMNode $node, string $target = '') use (&$walk, &$runs): void {
                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['script', 'style', 'iframe'], true)) {
                    return;
                }

                if ($node instanceof DOMElement && $node->tagName === 'a') {
                    $target = $node->getAttribute('href');
                }

                if ($node->nodeType === XML_TEXT_NODE) {
                    $runs = [...$runs, ...self::textRuns($node->textContent, $target)];

                    return;
                }

                foreach ($node->childNodes as $child) {
                    $walk($child, $target);
                }

                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['br', 'p', 'div', 'li', 'blockquote'], true)) {
                    $runs[] = ['text' => "\n", 'type' => 'text', 'target' => ''];
                }
            };
            $walk($document->getElementsByTagName('body')->item(0) ?? $document);
        } else {
            $runs = self::textRuns($text);
        }

        $merged = [];
        foreach ($runs as $run) {
            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['type'] === $run['type'] && $merged[$last]['target'] === $run['target']) {
                $merged[$last]['text'] .= $run['text'];
            } else {
                $merged[] = $run;
            }
        }
        $runs = $merged;

        while ($runs !== []) {
            $runs[0]['text'] = ltrim($runs[0]['text']);
            if ($runs[0]['text'] !== '') {
                break;
            }
            array_shift($runs);
        }
        while ($runs !== []) {
            $last = array_key_last($runs);
            $runs[$last]['text'] = rtrim($runs[$last]['text']);
            if ($runs[$last]['text'] !== '') {
                break;
            }
            array_pop($runs);
        }

        $remaining = $limit;
        $result = [];
        foreach ($runs as $run) {
            if ($remaining !== null && mb_strlen($run['text']) > $remaining) {
                $run['text'] = mb_substr($run['text'], 0, $remaining).'…';
                $result[] = $run;

                break;
            }

            if ($run['text'] !== '') {
                $result[] = $run;
            }

            if ($remaining !== null) {
                $remaining -= mb_strlen($run['text']);
            }
        }

        return $result;
    }

    /** @return list<array{text: string, type: string, target: string}> */
    private static function textRuns(string $text, string $href = ''): array
    {
        $baseUrl = rtrim((string) config('services.pinkary.url'), '/');
        $path = parse_url($href, PHP_URL_PATH);
        $host = parse_url($href, PHP_URL_HOST);
        $internal = $host === null || $host === parse_url($baseUrl, PHP_URL_HOST);

        if ($internal && is_string($path) && preg_match('~^/@([A-Za-z0-9_-]+)$~D', $path, $match)) {
            return [['text' => $text, 'type' => 'mention', 'target' => $match[1]]];
        }

        if (in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['https', 'http'], true)) {
            return [['text' => $text, 'type' => 'link', 'target' => $href]];
        }

        $pieces = preg_split('~((?<![\p{L}\p{N}_@])@[A-Za-z0-9_-]+|https?://[^\s<>]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(function (string $piece): array {
            if (preg_match('~^@([A-Za-z0-9_-]+)$~D', $piece, $match)) {
                return ['text' => $piece, 'type' => 'mention', 'target' => $match[1]];
            }

            if (preg_match('~^https?://~i', $piece)) {
                return ['text' => $piece, 'type' => 'link', 'target' => $piece];
            }

            return ['text' => $piece, 'type' => 'text', 'target' => ''];
        }, $pieces);
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
