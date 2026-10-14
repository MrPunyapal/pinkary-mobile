@props(['html', 'postId', 'limit' => null, 'question' => false, 'keyPrefix' => ''])

@php
    $runs = \App\Support\DisplayText::runs($html, $limit);
    $interactive = count(array_filter($runs, fn (array $run): bool => $run['type'] !== 'text')) > 0;
    $plain = implode('', array_column($runs, 'text'));
    $lines = [[]];
    if ($interactive) {
        foreach ($runs as $run) {
            if ($run['type'] === 'code') {
                if ($lines[array_key_last($lines)] === []) {
                    array_pop($lines);
                }
                $lines[] = $run;
                $lines[] = [];

                continue;
            }
            foreach (preg_split('/(\n)/', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if ($part === "\n") {
                    $lines[] = [];

                    continue;
                }
                foreach (preg_split('/(?<=\s)(?=\S)/u', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $lines[array_key_last($lines)][] = ['text' => $word, 'type' => $run['type'], 'target' => $run['target']];
                }
            }
        }
    }
@endphp

@if (! $interactive)
    <native:text
        native:key="post-text-{{ $keyPrefix }}-{{ $question ? 'question' : 'answer' }}"
        :text="$plain"
        class="{{ $question ? 'text-sm leading-loose' : 'text-base leading-[1.2]' }} whitespace-pre-wrap text-theme-on-body"
        @tap="openDetail('{{ $postId }}')"
    />
@else
    <native:column
        native:key="post-text-{{ $keyPrefix }}-{{ $question ? 'question' : 'answer' }}"
        class="w-full gap-0"
        a11y-label="{{ $plain }}"
    >
        @foreach ($lines as $lineIndex => $words)
            @if (($words['type'] ?? '') === 'code')
                <native:column
                    native:key="code-block-{{ $lineIndex }}"
                    class="bg-theme-bubble border-theme-outline-variant my-2 w-full gap-2 rounded-lg border p-3"
                >
                    @if ($words['target'] !== '')
                        <native:text :text="$words['target']" class="text-theme-muted text-xs" />
                    @endif
                    <native:text
                        :text="$words['text']"
                        class="text-theme-on-body w-full font-mono text-sm whitespace-pre-wrap select-text"
                    />
                </native:column>
                @continue
            @endif
            <native:row class="w-full flex-wrap items-start gap-0" native:key="text-line-{{ $lineIndex }}">
                @forelse ($words as $wordIndex => $word)
                    @php
                        $tap = match ($word['type']) {
                            'mention' => 'openProfile('.json_encode($word['target']).')',
                            'link' => 'openLink('.json_encode($word['target'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP).')',
                            'hashtag' => 'openHashtag('.json_encode($word['target'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP).')',
                            default => 'openDetail('.json_encode($postId).')',
                        };
                    @endphp
                    <native:text
                        native:key="word-{{ $wordIndex }}"
                        :text="$word['text']"
                        @tap="{{ $tap }}"
                        class="{{ $question ? 'text-sm leading-loose' : 'text-base leading-[1.2]' }} whitespace-pre-wrap {{ $word['type'] === 'code_inline' ? 'font-mono bg-theme-bubble text-theme-on-body select-text' : ($word['type'] === 'text' ? 'text-theme-on-body' : 'text-theme-link') }}"
                    />
                @empty
                    <native:rect class="h-4 w-px" />
                @endforelse
            </native:row>
        @endforeach
    </native:column>
@endif
