@props(['hashtags'])

@if (count($hashtags) > 0)
    <native:column
        class="bg-theme-surface-variant border-theme-outline-variant w-full gap-0 rounded-md border"
        native:key="hashtag-suggestions"
    >
        @foreach ($hashtags as $index => $hashtag)
            <native:pressable
                class="w-full px-3 py-2"
                @tap="selectMention({{ $index }})"
                a11y-label="Insert hashtag {{ '#'.$hashtag['name'] }}"
            >
                <native:text class="text-theme-on-surface text-sm font-medium">#{{ $hashtag['name'] }}</native:text>
            </native:pressable>
        @endforeach
    </native:column>
@endif
