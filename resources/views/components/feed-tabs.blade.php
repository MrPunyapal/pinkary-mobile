@props(['tab' => 'recent'])

<native:column class="bg-theme-surface w-full">
    <native:row class="w-full items-center gap-1 px-4 py-3">
        @php
            $tabs = [
                ['key' => 'recent', 'label' => 'Recent', 'tap' => 'showRecent'],
                ['key' => 'following', 'label' => 'Following', 'tap' => 'showFollowing'],
                ['key' => 'trending', 'label' => 'Trending', 'tap' => 'showTrending'],
            ];
        @endphp
        @foreach ($tabs as $item)
            <native:pressable
                class="px-3.5 py-1.5 rounded-md {{ $tab === $item['key'] ? 'bg-theme-primary' : '' }}"
                @tap="{{ $item['tap'] }}"
                a11y-label="Show {{ $item['label'] }} feed"
            >
                <native:text class="text-sm font-semibold {{ $tab === $item['key'] ? 'text-white' : 'text-theme-on-surface-variant' }}">{{ $item['label'] }}</native:text>
            </native:pressable>
        @endforeach
    </native:row>
    <native:rect class="bg-theme-outline-variant h-px w-full" />
</native:column>
