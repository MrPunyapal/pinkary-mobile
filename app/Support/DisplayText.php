<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

final class DisplayText
{
    private const int MAX_DISPLAYED_LINK_TEXT_LENGTH = 50;

    public static function plain(mixed $html): string
    {
        return implode('', array_column(self::runs($html), 'text'));
    }

    /**
     * @return list<array{text: string, type: string, target: string}>
     */
    public static function runs(mixed $html, ?int $limit = null): array
    {
        if (! is_string($html) && ! is_numeric($html)) {
            return [];
        }

        $html = str_replace(["\r\n", "\r"], "\n", (string) $html);
        if (preg_match('/^ {0,3}(?:`{3,}|~{3,})/m', $html)) {
            $html = Str::markdown($html, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        }

        $runs = [];
        if (preg_match('/<\/?[a-z][^>]*>/i', $html)) {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $walk = function (DOMNode $node, string $target = '') use (&$walk, &$runs): void {
                $tag = $node instanceof DOMElement ? mb_strtolower($node->tagName) : '';
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg'], true)
                    || ($node instanceof DOMElement && $node->getAttribute('id') === 'link-preview-card')) {
                    return;
                }

                if ($tag === 'pre') {
                    $code = $node->getElementsByTagName('code')->item(0);
                    $language = $code instanceof DOMElement && preg_match('/(?:language-|(?:^|\s)hljs\s+)([\w+-]+)/', $code->getAttribute('class'), $match) ? $match[1] : '';
                    $runs[] = ['text' => "\n", 'type' => 'text', 'target' => ''];
                    $runs[] = ['text' => Payload::string(preg_replace('/\n$/', '', self::codeText($code ?? $node))), 'type' => 'code', 'target' => $language];
                    $runs[] = ['text' => "\n", 'type' => 'text', 'target' => ''];

                    return;
                }

                if ($tag === 'code') {
                    $runs[] = ['text' => $node->textContent, 'type' => 'code_inline', 'target' => ''];

                    return;
                }

                if ($tag === 'li') {
                    $prefix = '- ';
                    if ($node->parentNode?->nodeName === 'ol') {
                        $index = 1;
                        for ($sibling = $node->previousSibling; $sibling instanceof DOMNode; $sibling = $sibling->previousSibling) {
                            if ($sibling->nodeName === 'li') {
                                $index++;
                            }
                        }
                        $prefix = $index.'. ';
                    }
                    $runs[] = ['text' => $prefix, 'type' => 'text', 'target' => ''];
                }

                if ($tag === 'a') {
                    $target = $node->getAttribute('href');
                }

                if ($node->nodeType === XML_TEXT_NODE) {
                    $runs = [...$runs, ...self::textRuns(Payload::string($node->textContent), $target)];

                    return;
                }

                foreach ($node->childNodes as $child) {
                    $walk($child, $target);
                }

                if (in_array($tag, ['br', 'p', 'div', 'li', 'blockquote', 'ul', 'ol'], true)) {
                    $runs[] = ['text' => "\n", 'type' => 'text', 'target' => ''];
                }
            };
            $walk($document->getElementsByTagName('body')->item(0) ?? $document);
        } else {
            $runs = self::textRuns(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $merged = [];
        foreach ($runs as $run) {
            $last = array_key_last($merged);
            if ($last !== null && $run['type'] !== 'code' && $merged[$last]['type'] === $run['type'] && $merged[$last]['target'] === $run['target']) {
                $merged[$last]['text'] .= $run['text'];
            } else {
                $merged[] = $run;
            }
        }
        $runs = $merged;

        while ($runs !== []) {
            if ($runs[0]['type'] !== 'code') {
                $runs[0]['text'] = mb_ltrim($runs[0]['text']);
            }
            if ($runs[0]['text'] !== '') {
                break;
            }
            array_shift($runs);
        }
        while ($runs !== []) {
            $last = array_key_last($runs);
            if ($runs[$last]['type'] !== 'code') {
                $runs[$last]['text'] = mb_rtrim($runs[$last]['text']);
            }
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

    public static function url(string $url): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (str_starts_with($url, '/')) {
            $url = mb_rtrim(Payload::string(config('services.pinkary.url')), '/').$url;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(mb_strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    /** @return array{url: string, host: string, title: string, image: null}|null */
    public static function preview(mixed $html): ?array
    {
        foreach (self::runs($html) as $run) {
            if ($run['type'] === 'link') {
                return ['url' => $run['target'], 'host' => Payload::string(parse_url($run['target'], PHP_URL_HOST)), 'title' => $run['target'], 'image' => null];
            }
        }

        return null;
    }

    public static function relativeTime(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        try {
            return Carbon::parse($iso)->diffForHumans(short: true);
        } catch (Throwable) {
            return '';
        }
    }

    public static function absoluteTime(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        try {
            return Carbon::parse($iso)
                ->setTimezone(Payload::string(config('app.timezone')))
                ->format('F j, Y \\a\\t g:i A T');
        } catch (Throwable) {
            return '';
        }
    }

    private static function codeText(DOMNode $node): string
    {
        if ($node->nodeName === 'br') {
            return "\n";
        }
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::codeText($child);
        }

        return $text;
    }

    /** @return list<array{text: string, type: string, target: string}> */
    private static function textRuns(string $text, string $href = ''): array
    {
        $url = self::url($href);
        $path = $url === null ? null : parse_url($url, PHP_URL_PATH);
        $host = $url === null ? null : parse_url($url, PHP_URL_HOST);
        $internal = in_array($host, [parse_url(Payload::string(config('services.pinkary.url')), PHP_URL_HOST), 'pinkary.com'], true);

        if ($internal && is_string($path) && preg_match('~^/hashtag/([a-z0-9]+)$~iD', $path, $match)) {
            return [['text' => $text, 'type' => 'hashtag', 'target' => $match[1]]];
        }

        if ($internal && is_string($path) && preg_match('~^/@([A-Za-z0-9_-]+)$~D', $path, $match)) {
            return [['text' => $text, 'type' => 'mention', 'target' => $match[1]]];
        }

        if ($url !== null) {
            return [['text' => self::displayedLinkText($text, $url), 'type' => 'link', 'target' => $url]];
        }

        $pieces = preg_split('~((?<![\p{L}\p{N}_@])@[A-Za-z0-9_-]+|(?<![\p{L}\p{N}_])#[\p{L}\p{N}_]+|(?<![\p{L}\p{N}_@./])(?:https?://[^\s<>]+|(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}(?:[/:?#][^\s<>]*)?))~iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $runs = [];
        foreach ($pieces as $piece) {
            if (preg_match('~^@([A-Za-z0-9_-]+)$~D', $piece, $match)) {
                $runs[] = ['text' => $piece, 'type' => 'mention', 'target' => $match[1]];

                continue;
            }

            if (preg_match('~^#([\p{L}\p{N}_]+)$~uD', $piece, $match)) {
                $runs[] = ['text' => $piece, 'type' => 'hashtag', 'target' => $match[1]];

                continue;
            }

            $label = mb_rtrim($piece, '.,!?;:');
            while (str_ends_with($label, ')') && mb_substr_count($label, ')') > mb_substr_count($label, '(')) {
                $label = mb_substr($label, 0, -1);
            }
            $url = self::url(preg_match('~^https?://~i', $label) ? $label : 'https://'.$label);
            if ($url !== null && preg_match('~^(?:https?://|(?:[a-z0-9-]+\.)+[a-z]{2,63}(?:[/:?#]|$))~i', $label)) {
                $runs[] = ['text' => self::displayedLinkText($label, $url), 'type' => 'link', 'target' => $url];
                if ($label !== $piece) {
                    $runs[] = ['text' => mb_substr($piece, mb_strlen($label)), 'type' => 'text', 'target' => ''];
                }
            } else {
                $runs[] = ['text' => $piece, 'type' => 'text', 'target' => ''];
            }
        }

        return $runs;
    }

    private static function displayedLinkText(string $text, string $url): string
    {
        $displayText = preg_replace('/^https?:\/\//i', '', $text) ?? $text;
        $displayText = mb_rtrim($displayText, '/');
        $displayUrl = preg_replace('/^https?:\/\//i', '', $url) ?? $url;
        $displayUrl = mb_rtrim($displayUrl, '/');

        if ($displayText !== $displayUrl) {
            return $text;
        }

        if (mb_strlen($displayText) <= self::MAX_DISPLAYED_LINK_TEXT_LENGTH) {
            return $displayText;
        }

        $trimmed = mb_substr($displayText, 0, self::MAX_DISPLAYED_LINK_TEXT_LENGTH);
        $trimmed = preg_replace('/&[#a-zA-Z0-9]*$/', '', $trimmed) ?? $trimmed;

        return $trimmed.'...';
    }
}
