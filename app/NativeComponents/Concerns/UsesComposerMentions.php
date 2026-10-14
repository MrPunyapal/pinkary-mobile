<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use App\Support\Payload;
use Native\Mobile\Edge\NativeComponent;

/** @mixin NativeComponent */
trait UsesComposerMentions
{
    public function updateMentionSelection(int $postIndex, string $text, int $selectionStart, int $selectionEnd): void
    {
        if ($this->mentionComposerBusy() || ($currentText = $this->mentionText($postIndex)) === null || $text !== $currentText) {
            $this->clearMentionSuggestions();

            return;
        }

        if ($selectionStart !== $selectionEnd || $selectionStart < 0 || $selectionStart > mb_strlen($text)
            || ! preg_match('/(?:^|\s)([@#])([A-Za-z0-9_-]{1,50})$/u', mb_substr($text, 0, $selectionStart), $match)) {
            $this->clearMentionSuggestions();

            return;
        }

        $trigger = $match[1];
        $query = $match[2];
        $kind = $trigger === '#' ? 'hashtag' : 'user';
        $this->mentionStart = $selectionStart - mb_strlen($query) - 1;
        preg_match('/^[A-Za-z0-9_-]*/', mb_substr($text, $selectionStart), $suffix);
        $this->mentionEnd = $selectionStart + mb_strlen($suffix[0] ?? '');

        if ($query === $this->mentionQuery && $postIndex === $this->mentionPostIndex && $kind === $this->mentionKind) {
            return;
        }

        $this->mentionQuery = $query;
        $this->mentionKind = $kind;
        $this->mentionPostIndex = $postIndex;
        $this->mentionResults = [];
        $this->mentionRequestVersion++;
        $requestVersion = $this->mentionRequestVersion;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->search($this->token(), $query));

        if ($response === null) {
            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful() || $requestVersion !== $this->mentionRequestVersion
            || $this->mentionText($postIndex) !== $text || $this->mentionQuery !== $query
            || $this->mentionPostIndex !== $postIndex || $this->mentionKind !== $kind) {
            return;
        }

        $results = Payload::array($response->json($kind === 'hashtag' ? 'data.hashtags' : 'data.users'));
        $suggestions = array_filter(array_map(
            fn (mixed $result): array => is_array($result) ? array_filter($result, is_string(...), ARRAY_FILTER_USE_KEY) : [],
            $results
        ), fn (array $result): bool => preg_match(
            '/^[A-Za-z0-9_-]+$/D',
            Payload::string($result[$kind === 'hashtag' ? 'name' : 'username'] ?? '')
        ) === 1);

        $this->mentionResults = array_slice(array_values($suggestions), 0, 5);
    }

    public function selectMention(int $index): void
    {
        if ($this->mentionComposerBusy() || ! isset($this->mentionResults[$index])) {
            return;
        }

        $trigger = $this->mentionKind === 'hashtag' ? '#' : '@';
        $resultKey = $this->mentionKind === 'hashtag' ? 'name' : 'username';
        $value = Payload::string($this->mentionResults[$index][$resultKey] ?? '');
        $postIndex = $this->mentionPostIndex;
        $text = $this->mentionText($postIndex) ?? '';
        $selection = mb_substr($text, $this->mentionStart, $this->mentionEnd - $this->mentionStart);

        if ($value === '' || $this->mentionStart < 0 || ! str_starts_with($selection, $trigger.$this->mentionQuery)
            || ! preg_match('/^'.$trigger.'[A-Za-z0-9_-]+$/D', $selection)) {
            $this->clearMentionSuggestions();

            return;
        }

        $suffix = mb_substr($text, $this->mentionEnd);
        $text = mb_substr($text, 0, $this->mentionStart).$trigger.$value.($suffix === '' ? ' ' : '').$suffix;

        if ($this->mentionText($postIndex) !== null) {
            $this->replaceMentionText($postIndex, $text);
        }

        $this->clearMentionSuggestions();
        $this->mentionChanged();
    }

    private function clearMentionSuggestions(): void
    {
        $this->mentionRequestVersion++;
        $this->mentionResults = [];
        $this->mentionQuery = '';
        $this->mentionKind = 'user';
        $this->mentionPostIndex = -1;
        $this->mentionStart = -1;
        $this->mentionEnd = -1;
    }

    abstract protected function mentionText(int $postIndex): ?string;

    abstract protected function replaceMentionText(int $postIndex, string $text): void;

    abstract protected function mentionComposerBusy(): bool;

    abstract protected function mentionChanged(): void;
}
