@props(['tab' => 'recent'])

{{-- X-style feed tabs (reference screenshot): plain labels with a short
    brand underline under the active one — no pills. Same three tabs and
    the same showRecent/showFollowing/showTrending methods. --}}
<native:row class="w-full bg-theme-surface border-b border-theme-outline-variant">
    @php
        $tabs = [
            ['key' => 'recent', 'label' => 'Recent', 'tap' => 'showRecent'],
            ['key' => 'following', 'label' => 'Following', 'tap' => 'showFollowing'],
            ['key' => 'trending', 'label' => 'Trending', 'tap' => 'showTrending'],
        ];
    @endphp
    @foreach ($tabs as $item)
        <native:pressable class="flex-1 items-center" @tap="{{ $item['tap'] }}" a11y-label="Show {{ $item['label'] }} feed">
            <native:column class="items-center gap-1.5 px-2 pt-3 pb-2.5">
                <native:text class="text-sm {{ $tab === $item['key'] ? 'font-semibold text-theme-on-surface' : 'text-theme-on-surface-variant' }}">{{ $item['label'] }}</native:text>
                <native:rect class="h-1 w-10 rounded-full {{ $tab === $item['key'] ? 'bg-theme-primary' : 'bg-theme-surface' }}" />
            </native:column>
        </native:pressable>
    @endforeach
</native:row>
